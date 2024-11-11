<?php

namespace App\Services\Payment;

interface PaymentInterface {
    public function createPaymentIntent($amount, $customMetaData);

    public function retrievePaymentIntent($paymentId);

    public function minimumAmountValidation($currency, $amount);
//
//    public function checkPayment(Order $order): PaymentStatus;
}
