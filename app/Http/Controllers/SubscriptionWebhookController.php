<?php

namespace App\Http\Controllers;

use App\Models\AddonSubscription;
use App\Models\Package;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionBill;
use App\Models\SubscriptionFeature;
use App\Repositories\AddonSubscription\AddonSubscriptionInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\Subscription\SubscriptionInterface;
use App\Repositories\SubscriptionFeature\SubscriptionFeatureInterface;
use App\Services\CachingService;
use App\Services\SubscriptionService;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Stripe\Exception\SignatureVerificationException;
use Throwable;
use UnexpectedValueException;

class SubscriptionWebhookController extends Controller
{
    //
    private CachingService $cache;
    private PaymentTransactionInterface $paymentTransaction;
    private SubscriptionService $subscriptionService;
    private SubscriptionInterface $subscription;
    private SubscriptionFeatureInterface $subscriptionFeature;
    private AddonSubscriptionInterface $addonSubscription;

    public function __construct(CachingService $cachingService, PaymentTransactionInterface $paymentTransaction, SubscriptionService $subscriptionService, SubscriptionInterface $subscription, SubscriptionFeatureInterface $subscriptionFeature, AddonSubscriptionInterface $addonSubscription)
    {
        $this->cache = $cachingService;
        $this->paymentTransaction = $paymentTransaction;
        $this->subscriptionService = $subscriptionService;

        $this->subscription = $subscription;
        $this->subscriptionFeature = $subscriptionFeature;
        $this->addonSubscription = $addonSubscription;
    }

    public function stripe(Request $request)
    {
        DB::setDefaultConnection('mysql');
        $systemSettings = PaymentConfiguration::where('school_id',NULL)->where('payment_method','Stripe')->first();
        $endpoint_secret = $systemSettings->webhook_secret_key;
        
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
            Log::error("Signature Verified");
        } catch (UnexpectedValueException $e) {
            // Invalid payload
            Log::error("Payload Mismatch");
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error("Signature Verification Failed");
            http_response_code(400);
            exit();
        }
        
        $transaction_id = $event->data->object->id;
        $paymentTransaction = PaymentTransaction::where('order_id',$transaction_id)->first();
        if ($paymentTransaction) {
            $transaction_id = $paymentTransaction->id;
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    Log::error($transaction_id);
                    $paymentTransactionData = $this->paymentTransaction->findById($transaction_id);
                    if (!empty($paymentTransactionData)) {
                        if ($paymentTransactionData->status != 1) {
                            $school_id = $paymentTransactionData->school_id;
    
                            $this->paymentTransaction->update($transaction_id,['payment_status' => "succeed",'school_id' => $school_id]);
                            Log::error("Payment Success");
                        }else{
                            Log::error("Transaction Already Successes --->");
                            break;
                        }
                    }else {
                        Log::error("Payment Transaction id not found --->");
                        break;
                    }
                    http_response_code(200);
                    break;
    
                case 'payment_intent.payment_failed':
                    $paymentTransactionData = $this->paymentTransaction->findById($transaction_id);
                    if (!empty($paymentTransactionData)) {
                        if ($paymentTransactionData->status != 1) {
                            $school_id = $paymentTransactionData->school_id;
    
                            $this->paymentTransaction->update($transaction_id,['payment_status' => "failed",'school_id' => $school_id]);
                            http_response_code(400);
                            break;
                        }
                    }else {
                        Log::error("Payment Transaction id not found --->");
                        break;
                    }
                case 'charge.succeeded':
                    Log::error($transaction_id);
                    $paymentTransactionData = $this->paymentTransaction->findById($transaction_id);
                    if (!empty($paymentTransactionData)) {
                        if ($paymentTransactionData->status != 1) {
                            $school_id = $paymentTransactionData->school_id;
    
                            $this->paymentTransaction->update($transaction_id,['payment_status' => "succeed",'school_id' => $school_id]);
                        }else{
                            Log::error("Transaction Already Successes --->");
                            break;
                        }
                    }else {
                        Log::error("Payment Transaction id not found --->");
                        break;
                    }
                    http_response_code(200);
                    break;
                default:
                    // Unexpected event type
                    Log::error('Received unknown event type');
            }
        }

        

        // End Stripe
    }

    public function razorpay(Request $request)
    {

        Log::info('Called');
        $webhookBody = file_get_contents('php://input');
        try {
            $data = json_decode($webhookBody, false, 512, JSON_THROW_ON_ERROR);
            Log::info("Razorpay Webhook Data : ", [$data]);

            $payload = $request->all();
            // Log::info("Payload", $payload);
            $data = (object)$payload;
            $metadata = $data->payload['payment']['entity']['notes'];

            // You can find your endpoint's secret in your webhook settings
            DB::setDefaultConnection('mysql');
            $paymentConfiguration = PaymentConfiguration::select('webhook_secret_key')->where('payment_method', 'razorpay')->where('school_id', null)->first();
          
            $webhookSecret = $paymentConfiguration['webhook_secret_key'];
            $webhookPublic = $paymentConfiguration["webhook_public_key"];


            $api = new Api($webhookPublic, $webhookSecret);
            $new_subscription = '';

            // $metadata = $data->payload->payment->entity->notes;
            Log::info($metadata);
            $metadata = json_decode(json_encode($metadata));
            

            if ($metadata && isset($data->event) && $data->event == 'payment.captured') {

                //checks the signature
                // $expectedSignature = hash_hmac("SHA256", $webhookBody, $webhookSecret);
                // $api->utility->verifyWebhookSignature($webhookBody, $expectedSignature, $webhookSecret);
                $paymentTransactionData = PaymentTransaction::where('id', $metadata->payment_transaction_id)->first();
                
                if ($paymentTransactionData == null) {
                    Log::error("Razorpay Webhook : Payment Transaction id not found");
                }

                if ($paymentTransactionData && $paymentTransactionData->status == "succeed") {
                    Log::info("Razorpay Webhook : Transaction Already Succeed");
                } else {
                    DB::beginTransaction();
                    $paymentTransactionStatus = PaymentTransaction::find($metadata->payment_transaction_id);
                    if ($paymentTransactionStatus) {
                        $paymentTransactionStatus->payment_status = "succeed";
                        $paymentTransactionStatus->save();
                    }
                    
                    // Addon
                    if ($metadata->type == 'addon') {
                        
                        $addon_data = [
                            'subscription_id' => $metadata->subscription_id,
                            'school_id' => $metadata->school_id,
                            'feature_id' => $metadata->feature_id,
                            'price' => $metadata->amount,
                            'start_date' => Carbon::now(),
                            'end_date' => $metadata->end_date,
                            'status' => 1,
                            'payment_transaction_id' => $metadata->payment_transaction_id,
                        ];

                        AddonSubscription::create($addon_data);
                    }
                    
                    // Package
                    if ($metadata->type == 'package') {
                        // New package subscription
                        if ($metadata->package_type == 'new') {
                            $new_subscription = $this->subscriptionService->createSubscription($metadata->package_id, $metadata->school_id, null, 1);
                        }

                        // Upcoming prepaid plan
                        if ($metadata->package_type == 'upcoming') {

                            $active_plan = $this->subscriptionService->active_subscription($metadata->school_id);
                            $package = Package::find($metadata->package_id);

                            $start_date = Carbon::parse($active_plan->end_date)->addDays()->format('Y-m-d');
                            $end_date = Carbon::parse($start_date)->addDays(($package->days - 1))->format('Y-m-d');

                            $subscription_data = [
                                'package_id'     => $package->id,
                                'name'           => $package->name,
                                'student_charge' => $package->student_charge,
                                'staff_charge'   => $package->staff_charge,
                                'start_date'     => $start_date,
                                'end_date'       => $end_date,
                                'package_type'   => $package->type,
                                'no_of_students' => $package->no_of_students,
                                'no_of_staffs'   => $package->no_of_staffs,
                                'billing_cycle'  => $package->days,
                                'school_id'      => $metadata->school_id,
                                'charges'        => $package->charges
                            ];

                            if ($active_plan->id == $metadata->subscription_id) {
                                // Same upcoming plan
                                $new_subscription = Subscription::create($subscription_data);

                            } else {
                                // Already set, update records
                                $new_subscription = Subscription::find($metadata->subscription_id)->update($subscription_data);
                                $new_subscription = Subscription::find($metadata->subscription_id);
                            }

                            // Add features
                            $subscription_features = array();
                            foreach ($new_subscription->package->package_feature as $key => $feature) {
                                $subscription_features[] = [
                                    'subscription_id' => $new_subscription->id,
                                    'feature_id'      => $feature->feature_id
                                ];
                            }
                            SubscriptionFeature::upsert($subscription_features, ['subscription_id', 'feature_id'], ['subscription_id', 'feature_id']);

                            // Generate bill
                            $systemSettings = $this->cache->getSystemSettings();

                            $subscription_bill = [
                                'subscription_id'        => $new_subscription->id,
                                'amount'                 => $new_subscription->charges,
                                'total_student'          => 0,
                                'total_staff'            => 0,
                                'due_date'               => Carbon::now()->addDays($systemSettings['additional_billing_days'])->format('Y-m-d'),
                                'school_id'              => $metadata->school_id,
                                'payment_transaction_id' => $metadata->payment_transaction_id
                            ];
                            // Create bill for active plan
                            SubscriptionBill::create($subscription_bill);

                        }

                        // Immediate change current package
                        if ($metadata->package_type == 'immediate') {
                            // Create current subscription bill
                            // Get current plan
                            $subscription = $this->subscriptionService->active_subscription($metadata->school_id);

                            // Postpaid plan generate bill
                            if ($subscription->package_type == 1) {
                                // Create current subscription plan bill
                                $this->subscriptionService->createSubscriptionBill($subscription, null);
                            }
                            $current_subscription_expiry = Subscription::find($subscription->id)->update(['end_date' => Carbon::now()->format('Y-m-d')]);
                            $current_subscription_expiry = Subscription::find($subscription->id);
                            Log::info('I am here................');
                            $this->subscriptionFeature->builder()->where('subscription_id', $subscription->id)->delete();

                            // Delete upcoming
                            $this->subscription->builder()->with('package')->doesntHave('subscription_bill')->whereDate('start_date', '>', $subscription->end_date)->delete();

                            // Delete addons
                            $addons = $this->addonSubscription->builder()->where('subscription_id', $subscription->id)->get();

                            $soft_delete_addon = array();
                            foreach ($addons as $key => $addon) {
                                AddonSubscription::find($addon->id)->update(['end_date' => $current_subscription_expiry->end_date]);
                                $soft_delete_addon[] = $addon->id;
                            }

                            $this->addonSubscription->builder()->whereIn('id', $soft_delete_addon)->delete();

                            // Set new plan
                            $package = Package::find($metadata->package_id);
                            $start_date = Carbon::now();
                            $end_date = Carbon::now()->addDays(($package->days - 1))->format('Y-m-d');
                            $subscription_data = [
                                'package_id'     => $package->id,
                                'name'           => $package->name,
                                'student_charge' => $package->student_charge,
                                'staff_charge'   => $package->staff_charge,
                                'start_date'     => $start_date,
                                'end_date'       => $end_date,
                                'package_type'   => $package->type,
                                'no_of_students' => $package->no_of_students,
                                'no_of_staffs'   => $package->no_of_staffs,
                                'billing_cycle'  => $package->days,
                                'school_id'      => $metadata->school_id,
                                'charges'        => $package->charges
                            ];

                            $new_subscription = Subscription::create($subscription_data);

                            // Add features
                            $subscription_features = array();
                            foreach ($new_subscription->package->package_feature as $key => $feature) {
                                $subscription_features[] = [
                                    'subscription_id' => $new_subscription->id,
                                    'feature_id'      => $feature->feature_id
                                ];
                            }
                            SubscriptionFeature::upsert($subscription_features, ['subscription_id', 'feature_id'], ['subscription_id', 'feature_id']);

                            // Generate bill
                            $systemSettings = $this->cache->getSystemSettings();

                            $subscription_bill = [
                                'subscription_id'        => $new_subscription->id,
                                'amount'                 => $new_subscription->charges,
                                'total_student'          => 0,
                                'total_staff'            => 0,
                                'due_date'               => Carbon::now()->addDays($systemSettings['additional_billing_days'])->format('Y-m-d'),
                                'school_id'              => $metadata->school_id,
                                'payment_transaction_id' => $metadata->payment_transaction_id
                            ];
                            // Create bill for active plan
                            SubscriptionBill::create($subscription_bill);
                        }

                        if ($new_subscription && $metadata->package_type != 'upcoming' && $metadata->package_type != 'immediate') {
                            SubscriptionBill::find($new_subscription->subscription_bill->id)->update(['payment_transaction_id' => $metadata->payment_transaction_id]);
                        }
                        if ($metadata->subscription_id && $metadata->package_type != 'upcoming' && $metadata->package_type != 'immediate') {
                            SubscriptionBill::find($metadata->subscription_id)->update(['payment_transaction_id' => $metadata->payment_transaction_id]);
                        }
                    }
                }
                

                $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.FEATURES'),$metadata->school_id);

                Log::info("Razorpay Webhook : payment.captured");
              
                http_response_code(200);
                DB::commit();
               
            } elseif ($metadata && isset($data->event) && $data->event == 'payment.failed') {
                $paymentTransactionData = PaymentTransaction::find($metadata->payment_transaction_id);
                if (!$paymentTransactionData) {
                    Log::error("Razorpay Webhook : Payment Transaction id not found --->");
                }

                PaymentTransaction::find($metadata->payment_transaction_id)->update(['payment_status' => "failed"]);

                http_response_code(400);
            } elseif (isset($data->event) && $data->event == 'payment.authorized') {
                Log::error('Razorpay Webhook : payment.authorized');
                http_response_code(200);
            }
            else {
                Log::error('Razorpay Webhook : Received unknown event type');
            }

            
            
            
        } catch (UnexpectedValueException) {
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

        Log::error('Webhook class');
    }
}
