<?php

use App\Services\Sberbank\SberbankConfig;
use App\Services\Sberbank\SberbankService;

$config = config('services.sberbank');

$sberbank = new SberbankService(
    new SberbankConfig(
        username: $config['username'],
        password: $config['password'],
        apiUrl: $config['api_url'],
        successUrl: $config['success_url'],
        failUrl: $config['fail_url'],
        testMode: $config['test_mode']
    )
);

// Create a payment
$payment = $sberbank->createPayment(
    orderId: 123,
    amount: 1000.00,
    description: 'Test payment'
);

// Check payment status
$status = $sberbank->checkPayment(123);

// Cancel payment if needed
$cancel = $sberbank->cancelPayment(123);

// Refund payment
$refund = $sberbank->refundPayment(123, 1000.00); 