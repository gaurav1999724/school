<?php

namespace App\Http\Controllers;

use App\Models\CompulsoryFee;
use App\Models\Fee;
use App\Models\FeesAdvance;
use App\Models\FeesPaid;
use App\Models\OptionalFee;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\School;
use App\Models\User;
use App\Repositories\User\UserInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class WebhookController extends Controller {

    public function __construct(UserInterface $user) {

    }

    public function stripe() {
        $payload = @file_get_contents('php://input');
        Log::info(PHP_EOL . "----------------------------------------------------------------------------------------------------------------------");
        try {
            // Verify webhook signature and extract the event.
            // See https://stripe.com/docs/webhooks/signatures for more information.
            $data = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);

            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

            $school_id = $data->data->object->metadata->school_id;
            $school = School::on('mysql')->where('id',$school_id)->first();

            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');

            // You can find your endpoint's secret in your webhook settings
            $paymentConfiguration = PaymentConfiguration::select('webhook_secret_key')->where('payment_method', 'stripe')->where('school_id', $data->data->object->metadata->school_id ?? null)->first();
            $endpoint_secret = $paymentConfiguration['webhook_secret_key'];
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );

            $metadata = $event->data->object->metadata;
            // Log::info("School ID : ", $metadata['school_id']);




           // Use this lines to Remove Signature verification for debugging purpose
        //    $event = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        //    $metadata = (array)$event->data->object->metadata;


            //get the current today's date
            $current_date = date('Y-m-d');

            Log::info("Stripe Webhook : ", [$event->type]);

            // handle the events
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentTransactionData = PaymentTransaction::where('id', $metadata['payment_transaction_id'])->first();
                    if ($paymentTransactionData == null) {
                        Log::error("Stripe Webhook : Payment Transaction id not found");
                        break;
                    }

                    if ($paymentTransactionData->status == "succeed") {
                        Log::info("Stripe Webhook : Transaction Already Successes");
                        break;
                    }
                    $fees = Fee::where('id', $metadata['fees_id'])->with(['fees_class_type', 'fees_class_type.fees_type'])->firstOrFail();

                    DB::beginTransaction();
                    PaymentTransaction::find($metadata['payment_transaction_id'])->update(['payment_status' => "succeed"]);
                    $feesPaidDB = FeesPaid::where([
                        'fees_id'    => $metadata['fees_id'],
                        'student_id' => $metadata['student_id'],
                        'school_id'  => $metadata['school_id']
                    ])->first();

                    // Check if Fees Paid Exists Then Add The optional Fees Amount with Fess Paid Amount
                    $totalAmount = !empty($feesPaidDB) ? $feesPaidDB->amount + $paymentTransactionData->amount : $paymentTransactionData->amount;
                    // Fees Paid Array
                    $feesPaidData = array(
                        'amount'     => $totalAmount,
                        'date'       => date('Y-m-d', strtotime($current_date)),
                        "school_id"  => $metadata['school_id'],
                        'fees_id'    => $metadata['fees_id'],
                        'student_id' => $metadata['student_id'],
                    );

                    $feesPaidResult = FeesPaid::updateOrCreate(['id' => $feesPaidDB->id ?? null], $feesPaidData);

                    if ($metadata['fees_type'] == "compulsory") {
                        $installments = json_decode($metadata['installment'], true, 512, JSON_THROW_ON_ERROR);
                        if (count($installments) > 0) {
                            foreach ($installments as $installment) {
                                CompulsoryFee::create([
                                    'student_id'             => $metadata['student_id'],
                                    'payment_transaction_id' => $paymentTransactionData->id,
                                    'type'                   => 'Installment Payment',
                                    'installment_id'         => $installment['id'],
                                    'mode'                   => 'Online',
                                    'cheque_no'              => null,
                                    'amount'                 => $installment['amount'],
                                    'due_charges'            => $installment['dueChargesAmount'],
                                    'fees_paid_id'           => $feesPaidResult->id,
                                    'status'                 => "Success",
                                    'date'                   => date('Y-m-d'),
                                    'school_id'              => $metadata['school_id'],
                                ]);
                            }
                        } else if ($metadata['advance_amount'] == 0) {
                            CompulsoryFee::create([
                                'student_id'             => $metadata['student_id'],
                                'payment_transaction_id' => $paymentTransactionData->id,
                                'type'                   => 'Full Payment',
                                'installment_id'         => null,
                                'mode'                   => 'Online',
                                'cheque_no'              => null,
                                'amount'                 => $paymentTransactionData->amount,
                                'due_charges'            => $metadata['dueChargesAmount'],
                                'fees_paid_id'           => $feesPaidResult->id,
                                'status'                 => "Success",
                                'date'                   => date('Y-m-d'),
                                'school_id'              => $metadata['school_id'],
                            ]);
                        }

                        // Add advance amount in installment
                        if ($metadata['advance_amount'] > 0) {
                            $updateCompulsoryFees = CompulsoryFee::where('student_id', $metadata['student_id'])->with('fees_paid')->whereHas('fees_paid', function ($q) use ($metadata) {
                                $q->where('fees_id', $metadata['fees_id']);
                            })->orderBy('id', 'DESC')->first();

                            $updateCompulsoryFees->amount += $metadata['advance_amount'];
                            $updateCompulsoryFees->save();

                            FeesAdvance::create([
                                'compulsory_fee_id' => $updateCompulsoryFees->id,
                                'student_id'        => $metadata['student_id'],
                                'parent_id'         => $metadata['parent_id'],
                                'amount'            => $metadata['advance_amount']
                            ]);
                        }
                        $feesPaidResult->is_fully_paid = $totalAmount >= $fees->total_compulsory_fees;
                        $feesPaidResult->is_used_installment = !empty($metadata['installment']);
                        $feesPaidResult->save();

                    } else if ($metadata['fees_type'] == "optional") {
                        $optional_fees = json_decode($metadata['optional_fees_id'], false, 512, JSON_THROW_ON_ERROR);
                        foreach ($optional_fees as $optional_fee) {
                            OptionalFee::create([
                                'student_id'             => $metadata['student_id'],
                                'class_id'               => $metadata['class_id'],
                                'payment_transaction_id' => $paymentTransactionData->id,
                                'fees_class_id'          => $optional_fee->id,
                                'mode'                   => 'Online',
                                'cheque_no'              => null,
                                'amount'                 => $optional_fee->amount,
                                'fees_paid_id'           => $feesPaidResult->id,
                                'date'                   => date('Y-m-d'),
                                'school_id'              => $metadata['school_id'],
                                'status'                 => "Success",
                            ]);
                        }
                    }

                    Log::info("payment_intent.succeeded called successfully");
                    $user = User::where('id', $metadata['parent_id'])->first();
                    $body = 'Amount :- ' . $paymentTransactionData->amount;
                    $type = 'payment';
                    send_notification([$user->id], 'Fees Payment Successful', $body, $type, ['is_payment_success'=> "true"]);
                    http_response_code(200);
                    DB::commit();
                    break;
                case
                'payment_intent.payment_failed':
                    $paymentTransactionData = PaymentTransaction::find($metadata['payment_transaction_id']);
                    if (!$paymentTransactionData) {
                        Log::error("Stripe Webhook : Payment Transaction id not found --->");
                        break;
                    }

                    PaymentTransaction::find($metadata['payment_transaction_id'])->update(['payment_status' => "0"]);
                    if ($metadata['fees_type'] == "compulsory") {
                        CompulsoryFee::where('payment_transaction_id', $paymentTransactionData->id)->update([
                            'status' => "failed",
                        ]);
                    } else if ($metadata['fees_type'] == "optional") {
                        OptionalFee::where('payment_transaction_id', $paymentTransactionData->id)->update([
                            'status' => "failed",
                        ]);
                    }

                    http_response_code(400);
                    $user = User::where('id', $metadata['parent_id'])->first();
                    $body = 'Amount :- ' . $paymentTransactionData->amount;
                    $type = 'payment';
                    send_notification([$user->id], 'Fees Payment Failed', $body, $type,['is_payment_success'=> "false"]);
                    break;
                default:
                    Log::error('Stripe Webhook : Received unknown event type');
            }
        } catch (UnexpectedValueException) {
            // Invalid payload
            echo "Stripe Webhook : Payload Mismatch";
            Log::error("Stripe Webhook : Payload Mismatch");
            http_response_code(400);
            exit();
        } catch (SignatureVerificationException) {
            // Invalid signature
            echo "Stripe Webhook : Signature Verification Failed";
            Log::error("Stripe Webhook : Signature Verification Failed");
            http_response_code(400);
            exit();
        } catch
        (Throwable $e) {
            DB::rollBack();
            Log::error("Stripe Webhook : Error occurred", [$e->getMessage() . ' --> ' . $e->getFile() . ' At Line : ' . $e->getLine()]);
            http_response_code(400);
            exit();
        }
    }

    public function razorpay() {
        $webhookBody = file_get_contents('php://input');
        Log::info(PHP_EOL . "----------------------------------------------------------------------------------------------------------------------");
        try {
           
            $data = json_decode($webhookBody, false, 512, JSON_THROW_ON_ERROR);
            // Log::info("Razorpay Webhook : ", [$data]);
             
            $metadata = $data->payload->payment->entity->notes;
           
            $school_id = $metadata->school_id;
            $school = School::on('mysql')->where('id',$school_id)->first();

            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');
            
            // You can find your endpoint's secret in your webhook settings
            $paymentConfiguration = PaymentConfiguration::select('secret_key','api_key')->where('payment_method', 'razorpay')->where('school_id', $school_id ?? null)->first();
          
           
            $webhookSecret = $paymentConfiguration['secret_key'];
            $webhookPublic = $paymentConfiguration['api_key'];

         
            $api = new Api($webhookPublic, $webhookSecret);

            // Log::info("Data Event : " , [$data->event]);
            //get the current today's date
            $current_date = date('Y-m-d');

            if (isset($data->event) && $data->event == 'payment.captured') {

                Log::info('Payment captured');
               
                //checks the signature
                $expectedSignature = hash_hmac("SHA256", $webhookBody, $webhookSecret);

                $api->utility->verifyWebhookSignature($webhookBody, $expectedSignature, $webhookSecret);

                $paymentTransactionData = PaymentTransaction::where('id', $metadata->payment_transaction_id)->first();

                Log::info("Payment Transaction : ",[$paymentTransactionData]);

                if ($paymentTransactionData == null) {
                    Log::error("Razorpay Webhook : Payment Transaction id not found");
                }

                if ($paymentTransactionData->status == "succeed") {
                    Log::info("Razorpay Webhook : Transaction Already Succeed");
                }
              
                $fees = Fee::where('id', $metadata->fees_id)->with(['fees_class_type', 'fees_class_type.fees_type'])->firstOrFail();

                DB::beginTransaction();
                PaymentTransaction::find($metadata->payment_transaction_id)->update(['payment_status' => "succeed"]);
                $feesPaidDB = FeesPaid::where([
                    'fees_id'    => $metadata->fees_id,
                    'student_id' => $metadata->student_id,
                    'school_id'  => $metadata->school_id
                ])->first();

                // Check if Fees Paid Exists Then Add The optional Fees Amount with Fess Paid Amount
                $totalAmount = !empty($feesPaidDB) ? $feesPaidDB->amount + $paymentTransactionData->amount : $paymentTransactionData->amount;
                // Fees Paid Array
                $feesPaidData = array(
                    'amount'     => $totalAmount,
                    'date'       => date('Y-m-d', strtotime($current_date)),
                    "school_id"  => $metadata->school_id,
                    'fees_id'    => $metadata->fees_id,
                    'student_id' => $metadata->student_id,
                );

                $feesPaidResult = FeesPaid::updateOrCreate(['id' => $feesPaidDB->id ?? null], $feesPaidData);

                if ($metadata->fees_type == "compulsory") {
                    $installments = json_decode($metadata->installment, true, 512, JSON_THROW_ON_ERROR);
                    if (count($installments) > 0) {
                        foreach ($installments as $installment) {
                            CompulsoryFee::create([
                                'student_id'             => $metadata->student_id,
                                'payment_transaction_id' => $paymentTransactionData->id,
                                'type'                   => 'Installment Payment',
                                'installment_id'         => $installment['id'],
                                'mode'                   => 'Online',
                                'cheque_no'              => null,
                                'amount'                 => $installment['amount'],
                                'due_charges'            => $installment['dueChargesAmount'],
                                'fees_paid_id'           => $feesPaidResult->id,
                                'status'                 => "Success",
                                'date'                   => date('Y-m-d'),
                                'school_id'              => $metadata->school_id,
                            ]);
                        }
                    } else if ($metadata->advance_amount == 0) {
                        CompulsoryFee::create([
                            'student_id'             => $metadata->student_id,
                            'payment_transaction_id' => $paymentTransactionData->id,
                            'type'                   => 'Full Payment',
                            'installment_id'         => null,
                            'mode'                   => 'Online',
                            'cheque_no'              => null,
                            'amount'                 => $paymentTransactionData->amount,
                            'due_charges'            => $metadata->dueChargesAmount,
                            'fees_paid_id'           => $feesPaidResult->id,
                            'status'                 => "Success",
                            'date'                   => date('Y-m-d'),
                            'school_id'              => $metadata->school_id,
                        ]);
                    }

                    // Add advance amount in installment
                    if ($metadata->advance_amount > 0) {
                        $updateCompulsoryFees = CompulsoryFee::where('student_id', $metadata->student_id)->with('fees_paid')->whereHas('fees_paid', function ($q) use ($metadata) {
                            $q->where('fees_id', $metadata->fees_id);
                        })->orderBy('id', 'DESC')->first();

                        $updateCompulsoryFees->amount += $metadata->advance_amount;
                        $updateCompulsoryFees->save();

                        FeesAdvance::create([
                            'compulsory_fee_id' => $updateCompulsoryFees->id,
                            'student_id'        => $metadata->student_id,
                            'parent_id'         => $metadata->parent_id,
                            'amount'            => $metadata->advance_amount
                        ]);
                    }
                    $feesPaidResult->is_fully_paid = $totalAmount >= $fees->total_compulsory_fees;
                    $feesPaidResult->is_used_installment = !empty($metadata->installment);
                    $feesPaidResult->save();

                } else if ($metadata->fees_type == "optional") {
                    $optional_fees = json_decode($metadata->optional_fees_id, false, 512, JSON_THROW_ON_ERROR);
                    foreach ($optional_fees as $optional_fee) {
                        OptionalFee::create([
                            'student_id'             => $metadata->student_id,
                            'class_id'               => $metadata->class_id,
                            'payment_transaction_id' => $paymentTransactionData->id,
                            'fees_class_id'          => $optional_fee->id,
                            'mode'                   => 'Online',
                            'cheque_no'              => null,
                            'amount'                 => $optional_fee->amount,
                            'fees_paid_id'           => $feesPaidResult->id,
                            'date'                   => date('Y-m-d'),
                            'school_id'              => $metadata->school_id,
                            'status'                 => "Success",
                        ]);
                    }
                }

                Log::info("payment_intent.succeeded called successfully");
                $user = User::where('id', $metadata->parent_id)->first();
                $body = 'Amount :- ' . $paymentTransactionData->amount;
                $type = 'payment';
                send_notification([$user->id], 'Fees Payment Successful', $body, $type, ['is_payment_success'=> 'true']);
              
                http_response_code(200);
                DB::commit();
               
            } elseif (isset($data->event) && $data->event == 'payment.failed') {
                $paymentTransactionData = PaymentTransaction::find($metadata->payment_transaction_id);
                if (!$paymentTransactionData) {
                    Log::error("Razorpay Webhook : Payment Transaction id not found --->");
                }

                PaymentTransaction::find($metadata->payment_transaction_id)->update(['payment_status' => "failed"]);
                if ($metadata->fees_type == "compulsory") {
                    CompulsoryFee::where('payment_transaction_id', $paymentTransactionData->id)->update([
                        'status' => "failed",
                    ]);
                } else if ($metadata->fees_type == "optional") {
                    OptionalFee::where('payment_transaction_id', $paymentTransactionData->id)->update([
                        'status' => "failed",
                    ]);
                }

                http_response_code(400);
                $user = User::where('id', $metadata->parent_id)->first();
                $body = 'Amount :- ' . $paymentTransactionData->amount;
                $type = 'payment';
                send_notification([$user->id], 'Fees Payment Failed', $body, $type,['is_payment_success'=>'false']);
            } elseif (isset($data->event) && $data->event == 'payment.authorized') {
                http_response_code(200);
            }
            else {
                Log::error('Razorpay Webhook : Received unknown event type');
            }
        }catch (UnexpectedValueException) {
            // Invalid payload
            echo "Razorpay Webhook : Payload Mismatch";
            Log::error("Razorpay  : Payload Mismatch");
            http_response_code(400);
            exit();
        } catch (SignatureVerificationException) {
            // Invalid signature
            echo "Razorpay  Webhook : Signature Verification Failed";
            Log::error("Razorpay  Webhook : Signature Verification Failed");
            http_response_code(400);
            exit();
        } catch(Throwable $e) {
            DB::rollBack();
            Log::error("Razorpay Webhook : Error occurred", [$e->getMessage() . ' --> ' . $e->getFile() . ' At Line : ' . $e->getLine()]);
            http_response_code(400);
            exit();
        }
    }

}
