<?php

use App\Services\Tinkoff\TinkoffConfig;
use App\Services\Tinkoff\TinkoffService;

$config = config('services.tinkoff');

$tinkoff = new TinkoffService(
    new TinkoffConfig(
        terminal: $config['terminal'],
        password: $config['password'],
        isTest: $config['is_test'],
        apiUrl: $config['api_url']
    )
);

// Пример создания платежа
$payment = $tinkoff->createPayment(
    orderId: 'ORDER-' . time(),
    amount: 1000.00,
    description: 'Оплата заказа',
    successUrl: 'https://ваш-сайт.ru/payment/success',
    failUrl: 'https://ваш-сайт.ru/payment/fail',
    receipt: [
        'Email' => 'client@example.com',
        'Taxation' => 'osn',
        'Items' => [
            [
                'Name' => 'Товар 1',
                'Price' => 1000.00,
                'Quantity' => 1,
                'Amount' => 1000.00,
                'Tax' => 'vat20'
            ]
        ]
    ]
);

// Проверка статуса платежа
if (isset($payment['PaymentId'])) {
    $status = $tinkoff->checkPayment($payment['PaymentId']);
    print_r($status);
}

// Подтверждение платежа
// $confirm = $tinkoff->confirmPayment($payment['PaymentId']);

// Отмена платежа
// $cancel = $tinkoff->cancelPayment($payment['PaymentId']);

// Возврат платежа
// $refund = $tinkoff->refundPayment($payment['PaymentId'], 1000.00);