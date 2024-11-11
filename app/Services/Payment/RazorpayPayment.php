<?php

namespace App\Services\Payment;

use JetBrains\PhpStorm\Pure;
use Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Razorpay\Api\Api;

class RazorpayPayment implements PaymentInterface {
    private Api $api;
    private string $currencyCode;

    #[Pure] public function __construct($secretKey, $publicKey, $currencyCode) {
        // Call Stripe Class and Create Payment Intent
        $this->api = new Api($publicKey, $secretKey);
        $this->currencyCode = $currencyCode;
    }

    /**
     * @param $amount
     * @param $customMetaData
     * @return PaymentIntent
     * @throws ApiErrorException
     */
    public function createPaymentIntent($amount, $customMetaData) {
        try {
            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $amount *= 100;
    
            $paymentData = [
                'amount'   => $amount,
                'currency' => $this->currencyCode,
                'notes' => $customMetaData,
            ];
            return $this->api->order->create($paymentData);
            
        } catch (ApiErrorException $e) {
            Log::error('Failed to create payment intent: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param $paymentId
     * @return PaymentIntent
     * @throws ApiErrorException
     */
    public function retrievePaymentIntent($paymentId) {
        try {
            return $this->api->order->fetch($paymentId);
        } catch (ApiErrorException $e) {
            throw $e;
        }
    }


    /**
     * @param $currency
     * @param $amount
     * @return float|int
     */
    public function minimumAmountValidation($currency, $amount) {
        $minimumAmount = match ($currency) {
            'USD', 'EUR', 'INR', 'NZD', 'SGD', 'BRL', 'CAD', 'AUD', 'CHF' => 0.50,
            'AED', 'PLN', 'RON' => 2.00,
            'BGN' => 1.00,
            'CZK' => 15.00,
            'DKK' => 2.50,
            'GBP' => 0.30,
            'HKD' => 4.00,
            'HUF' => 175.00,
            'JPY' => 50,
            'MXN', 'THB' => 10,
            'MYR' => 2,
            'NOK', 'SEK' => 3.00,
            default => null,
        };
        if (!empty($minimumAmount)) {
            if ($amount > $minimumAmount) {
                return $amount;
            }

            return $minimumAmount;
        }

        return $amount;
    }
}
