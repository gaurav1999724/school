<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use App\Models\Students;
use App\Models\Subscription;
use App\Models\SubscriptionBill;
use App\Models\User;
use App\Repositories\AddonSubscription\AddonSubscriptionInterface;
use App\Repositories\Package\PackageInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\Staff\StaffInterface;
use App\Repositories\Subscription\SubscriptionInterface;
use App\Repositories\SubscriptionBill\SubscriptionBillInterface;
use App\Repositories\SubscriptionFeature\SubscriptionFeatureInterface;
use App\Repositories\User\UserInterface;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class SubscriptionService
{
    private UserInterface $user;
    private SubscriptionInterface $subscription;
    private PackageInterface $package;
    private SubscriptionFeatureInterface $subscriptionFeature;
    private CachingService $cache;
    private AddonSubscriptionInterface $addonSubscription;
    private StaffInterface $staff;
    private SubscriptionBillInterface $subscriptionBill;
    private PaymentTransactionInterface $paymentTransaction;

    public function __construct(UserInterface $user, SubscriptionInterface $subscription, PackageInterface $package, SubscriptionFeatureInterface $subscriptionFeature, CachingService $cache, AddonSubscriptionInterface $addonSubscription, StaffInterface $staff, SubscriptionBillInterface $subscriptionBill, PaymentTransactionInterface $paymentTransaction)
    {
        $this->user = $user;
        $this->subscription = $subscription;
        $this->package = $package;
        $this->subscriptionFeature = $subscriptionFeature;
        $this->cache = $cache;
        $this->addonSubscription = $addonSubscription;
        $this->staff = $staff;
        $this->subscriptionBill = $subscriptionBill;
        $this->paymentTransaction = $paymentTransaction;
    }


    /**
     * @param $package_id
     * @param $school_id
     * @param $isCurrentPlan
     * @return Model|null
     */
    public function createSubscription($package_id, $school_id = null, $subscription_id = null, $isCurrentPlan = null)
    {
        // package_id => Create that package
        // school_id => if super admin can assign package, then school id is compulsory
        // subscription_id => if school admin already set upcoming plan update only that plan
        // isCurrentPlan => school admin can set current plan & upcoming plan also

        $settings = $this->cache->getSystemSettings();
        $package = $this->package->builder()->with('package_feature')->where('id', $package_id)->first();
        $end_date = '';
        if (!$school_id) {
            $school_id = Auth::user()->school_id;
        }
        if ($package->is_trial) {
            $end_date = Carbon::now()->addDays(($settings['trial_days']))->format('Y-m-d');
        } else {
            $end_date = Carbon::now()->addDays(($package->days - 1))->format('Y-m-d');
        }
        $start_date = Carbon::now()->format('Y-m-d');



        // If not current subscription plan
        if (!$isCurrentPlan) {
            // $current_subscription = $this->subscription->default()->first();
            $current_subscription = $this->active_subscription($school_id);
            $start_date = Carbon::parse($current_subscription->end_date)->addDays()->format('Y-m-d');
            $end_date = Carbon::parse($start_date)->addDays(($package->days - 1))->format('Y-m-d');
        }
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
            'school_id'      => $school_id,
            'charges'        => $package->charges
        ];

        // Check subscription update or create
        // If school has already set upcoming plan
        if ($subscription_id) {
            $subscription = $this->subscription->update($subscription_id, $subscription_data);
        } else {
            $subscription = $this->subscription->create($subscription_data);
        }


        // If current subscription plan then set package features
        if ($isCurrentPlan) {
            $subscription_features = array();
            foreach ($package->package_feature as $key => $feature) {
                $subscription_features[] = [
                    'subscription_id' => $subscription->id,
                    'feature_id'      => $feature->feature_id
                ];
            }
            $this->subscriptionFeature->upsert($subscription_features, ['subscription_id', 'feature_id'], ['subscription_id', 'feature_id']);
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.FEATURES'), $subscription->school_id);


            // If prepaid plan generate bill first
            if ($package->type == 0) {
                $subscription_bill[] = [
                    'subscription_id' => $subscription->id,
                    'amount'          => $package->charges,
                    'total_student'   => $package->no_of_students,
                    'total_staff'     => $package->no_of_staffs,
                    'due_date'        => Carbon::now(),
                    'school_id'       => $subscription->school_id
                ];
                if (Auth::user() && !Auth::user()->hasRole('School Admin')) {
                    $billData = [
                        'user_id' => $subscription->school->admin_id,
                        'amount' => $package->charges,
                        'payment_gateway' => 'Cash',
                        'school_id' => $subscription->school_id,
                        'payment_status' => 'succeed'
                    ];

                    $paymentTransaction = $this->paymentTransaction->create($billData);
                    $subscription_bill[0]['payment_transaction_id'] = $paymentTransaction->id;
                }
                // $subscription_bill = $this->subscriptionBill->create($subscription_bill);
                SubscriptionBill::upsert($subscription_bill, ['subscription_id', 'school_id'], ['amount', 'total_student', 'total_staff', 'due_date']);
                // return $subscription_bill = $this->subscriptionBill->upsert($subscription_bill,['subscription_id','school_id'],['amount','total_student','total_staff','due_date']);
            }
        }

        return $subscription;
    }

    /**
     * @param $generateBill
     * @return Model|null
     */
    public function createSubscriptionBill($subscription, $generateBill = null)
    {
        // GenerateBill [ null => Generate immediate bill, 1 => Generate regular bill ]

        // Set school database connection for getting user counts
        Config::set('database.connections.school.database', $subscription->school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        // $students = User::on('school')->withTrashed()->where(function ($q) use ($subscription) {
        //     $q->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date]);
        // })->orWhereNull('deleted_at')->role('Student')->where('school_id', $subscription->school_id)->count();

        $students = Students::on('school')->whereHas('user',function($q) use($subscription) {
            $q->withTrashed()->where(function ($q) use ($subscription) {
                $q->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date]);
            })->orWhereNull('deleted_at')->where('school_id', $subscription->school_id);
        })->has('user')->count();

        $staffs = $this->staff->builder()->whereHas('user', function ($q) use ($subscription) {
            $q->where(function ($q) use ($subscription) {
                $q->withTrashed()->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date])
                    ->orWhereNull('deleted_at');
            })->where('school_id', $subscription->school_id);
        })->count();

        DB::setDefaultConnection('mysql');

        $today_date = Carbon::now()->format('Y-m-d');
        $start_date = Carbon::parse($subscription->start_date);
        if ($generateBill) {
            $usage_days = $start_date->diffInDays($subscription->end_date) + 1;
        } else {
            $usage_days = $start_date->diffInDays($today_date) + 1;
        }
        $bill_cycle_days = $subscription->billing_cycle;


        // Get addon total
        $addons = $this->addonSubscription->builder()->where('subscription_id', $subscription->id)->sum('price');

        $student_charges = number_format((($usage_days * $subscription->student_charge) / $bill_cycle_days), 2) * $students;
        $staff_charges = number_format((($usage_days * $subscription->staff_charge) / $bill_cycle_days), 2) * $staffs;

        $systemSettings = $this->cache->getSystemSettings();

        $subscription_bill = [
            'subscription_id' => $subscription->id,
            'amount'          => ($student_charges + $staff_charges + $addons),
            'total_student'   => $students,
            'total_staff'     => $staffs,
            'due_date'        => Carbon::now()->addDays($systemSettings['additional_billing_days'])->format('Y-m-d'),
            'school_id'       => $subscription->school_id
        ];
        // Create bill for active plan
        return $subscription_bill = $this->subscriptionBill->create($subscription_bill);
    }

    // Check subscription pending bills
    public function subscriptionPendingBill()
    {
        $subscriptionBill = $this->subscriptionBill->builder()->whereHas('transaction', function ($q) {
            $q->whereNot('payment_status', "succeed");
        })->orDoesntHave('transaction')->where('school_id', Auth::user()->school_id)->whereNot('amount', 0)->first();
        return $subscriptionBill;
    }


    // Stripe payment gateway
    public function stripe_payment($subscriptionBill_id = null, $package_id = null, $type = null, $subscription_id = null, $isCurrentPlan = null)
    {
        try {

            $settings = app(CachingService::class)->getSystemSettings();
            $name = '';
            $amount = 0;

            if ($subscriptionBill_id) {
                $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
                $name = $subscriptionBill->subscription->name;
                $amount = $subscriptionBill->amount;
                $package_id = -1;
            }
            if ($package_id != -1) {
                $package = $this->package->findById($package_id);
                $name = $package->name;
                $amount = $package->charges;
                $subscriptionBill_id = -1;
            }

            if ($type == null) {
                $type = -1;
            }

            if (!$subscription_id) {
                $subscription_id = -1;
            }
            if (!$isCurrentPlan) {
                $isCurrentPlan = -1;
            }


            // Access the model directly via data for super admin data, use the interface builder for school-specific data.
            DB::setDefaultConnection('mysql');
            $paymentConfiguration = PaymentConfiguration::where('school_id', null)->first();
            if ($paymentConfiguration && !$paymentConfiguration->status) {
                return redirect()->back()->with('error', trans('Current stripe payment not available'));
            }
            $stripe_secret_key = $paymentConfiguration->secret_key ?? null;
            if (empty($stripe_secret_key)) {
                return redirect()->back()->with('error', trans('No API key provided'));
            }
            $currency = $paymentConfiguration->currency_code;

            $checkAmount = $this->checkMinimumAmount(strtoupper($currency), $amount);
            $checkAmount = (float)$checkAmount;
            $checkAmount = round($checkAmount, 2);
            Stripe::setApiKey($stripe_secret_key);
            $session = StripeSession::create([
                'line_items'  => [
                    [
                        'price_data' => [
                            'currency'     => $currency,
                            'product_data' => [
                                'name'   => $name,
                                'images' => [$settings['horizontal_logo'] ?? 'logo.svg'],
                            ],
                            'unit_amount'  => $checkAmount * 100,
                        ],
                        'quantity'   => 1,
                    ],
                ],
                'mode'        => 'payment',
                'success_url' => url('subscriptions/payment/success') . '/{CHECKOUT_SESSION_ID}' . '/' . $subscriptionBill_id . '/' . $package_id . '/' . $type . '/' . $subscription_id . '/' . $isCurrentPlan,
                'cancel_url'  => url('subscriptions/payment/cancel') . '/' . $subscriptionBill_id,
            ]);

            return redirect()->away($session->url);
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', trans('server_not_responding'));
        }
    }

    public function active_subscription($schoolId)
    {
        $today_date = Carbon::now()->format('Y-m-d');
        $subscription = Subscription::where('school_id', $schoolId)->whereDate('start_date', '<=', $today_date)->whereDate('end_date', '>=', $today_date)->latest()->first();

        if ($subscription) {
            if ($subscription->package_type == 1) {
                // Postpaid
                $subscription = Subscription::where('school_id', $schoolId)->where('package_type', 1)->whereDate('start_date', '<=', $today_date)->whereDate('end_date', '>=', $today_date)->with('subscription_feature.feature', 'addons.feature', 'package.package_feature.feature')->doesntHave('subscription_bill')->has('subscription_feature')->latest()->first();
            } else {
                // Prepaid
                $subscription = Subscription::where('school_id', $schoolId)->where('package_type', 0)->whereDate('start_date', '<=', $today_date)->whereDate('end_date', '>=', $today_date)->with('package.package_feature.feature')->has('subscription_bill')->with('subscription_feature.feature')->with(['addons' => function ($q) {
                    $q->has('transaction')->with('feature')->whereHas('transaction', function ($q) {
                        $q->where('payment_status', "succeed");
                    });
                }])->whereHas('subscription_bill.transaction', function ($q) {
                    $q->where('payment_status', "succeed");
                })->has('subscription_feature')->latest()->first();
            }
        } else {
            return null;
        }

        return $subscription;
    }

    public function check_user_limit($subscription, $type)
    {
        // type [ Students / Staffs ]
        if ($type == "Students") {
            $students = $this->user->builder()->where('status', 1)->role('Student')->where('school_id', $subscription->school_id)->count();
            if ($students >= $subscription->no_of_students) {
                return false;
            }
            return true;
        } else {
            $staffs = $this->staff->builder()->whereHas('user', function ($q) use ($subscription) {
                $q->where('status', 1)->where('school_id', $subscription->school_id);
            })->count();
            if ($staffs >= $subscription->no_of_staffs) {
                return false;
            }
            return true;
        }
    }

    public function prepaid_addon_payment($addonSubscriptionId)
    {
        try {
            $settings = app(CachingService::class)->getSystemSettings();
            // $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
            $addonSubscription = $this->addonSubscription->findById($addonSubscriptionId);

            // Access the model directly via data for super admin data, use the interface builder for school-specific data.
            DB::setDefaultConnection('mysql');
            $paymentConfiguration = PaymentConfiguration::where('school_id', null)->first();
            if ($paymentConfiguration && !$paymentConfiguration->status) {
                return redirect()->back()->with('error', trans('Current stripe payment not available'));
            }
            $stripe_secret_key = $paymentConfiguration->secret_key ?? null;
            if (empty($stripe_secret_key)) {
                return redirect()->back()->with('error', trans('No API key provided'));
            }
            $amount = number_format(ceil($addonSubscription->price * 100) / 100, 2);
            $currency = $paymentConfiguration->currency_code;

            $checkAmount = $this->checkMinimumAmount(strtoupper($currency), $amount);

            Stripe::setApiKey($stripe_secret_key);
            $session = StripeSession::create([
                'line_items'  => [
                    [
                        'price_data' => [
                            'currency'     => $currency,
                            'product_data' => [
                                'name'   => $addonSubscription->feature->name,
                                'images' => [$settings['horizontal_logo'] ?? 'logo.svg'],
                            ],
                            'unit_amount'  => $checkAmount * 100,
                        ],
                        'quantity'   => 1,
                    ],
                ],
                'mode'        => 'payment',
                'success_url' => url('addons/payment/success') . '/{CHECKOUT_SESSION_ID}' . '/' . $addonSubscriptionId,
                'cancel_url'  => url('addons/payment/cancel'),
            ]);
            return redirect()->away($session->url);
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', trans('server_not_responding'));
        }
    }


    /**
     * @param $currency
     * @param $amount
     */
    public function checkMinimumAmount($currency, $amount)
    {
        $currencies = array(
            'USD' => 0.50,
            'AED' => 2.00,
            'AUD' => 0.50,
            'BGN' => 1.00,
            'BRL' => 0.50,
            'CAD' => 0.50,
            'CHF' => 0.50,
            'CZK' => 15.00,
            'DKK' => 2.50,
            'EUR' => 0.50,
            'GBP' => 0.30,
            'HKD' => 4.00,
            'HUF' => 175.00,
            'INR' => 0.50,
            'JPY' => 50,
            'MXN' => 10,
            'MYR' => 2.00,
            'NOK' => 3.00,
            'NZD' => 0.50,
            'PLN' => 2.00,
            'RON' => 2.00,
            'SEK' => 3.00,
            'SGD' => 0.50,
            'THB' => 10
        );
        if ($amount != 0) {
            if (array_key_exists($currency, $currencies)) {
                if ($currencies[$currency] >= $amount) {
                    return $currencies[$currency];
                } else {
                    return $amount;
                }
            } else {
                return $amount;
            }
        }
        return 0;
    }
}
