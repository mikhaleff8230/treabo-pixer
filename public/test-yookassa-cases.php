<?php

require '../vendor/autoload.php';
require '../bootstrap/app.php';

use App\Services\YooKassa\YooKassaService;
use App\Services\YooKassa\YooKassaConfig;

header('Content-Type: application/json');

class YooKassaTestCases {
    private YooKassaService $yookassa;
    private string $baseUrl;

    public function __construct() {
        $config = config('services.yookassa');
        $this->yookassa = new YooKassaService(
            new YooKassaConfig(
                shopId: $config['shop_id'],
                secretKey: $config['secret_key'],
                isTest: $config['is_test']
            )
        );
        $this->baseUrl = config('app.url');
    }

    public function createPayment() {
        try {
            $result = $this->yookassa->createPayment(
                orderId: 'TEST-' . time(),
                amount: 100.00,
                description: 'Тестовый платеж',
                successUrl: $this->baseUrl . '/test-yookassa?method=check',
                failUrl: $this->baseUrl . '/test-yookassa?method=help',
                receipt: [
                    'customer' => [
                        'email' => 'test@example.com'
                    ],
                    'items' => [
                        [
                            'description' => 'Тестовый товар',
                            'quantity' => 1,
                            'amount' => [
                                'value' => '100.00',
                                'currency' => 'RUB'
                            ],
                            'vat_code' => 1,
                            'payment_mode' => 'full_payment',
                            'payment_subject' => 'commodity'
                        ]
                    ]
                ]
            );

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function checkPayment($paymentId) {
        try {
            $result = $this->yookassa->checkPayment($paymentId);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelPayment($paymentId) {
        try {
            $result = $this->yookassa->cancelPayment($paymentId);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function refundPayment($paymentId) {
        try {
            $result = $this->yookassa->refundPayment(
                $paymentId,
                100.00,
                [
                    'customer' => [
                        'email' => 'test@example.com'
                    ],
                    'items' => [
                        [
                            'description' => 'Возврат: Тестовый товар',
                            'quantity' => 1,
                            'amount' => [
                                'value' => '100.00',
                                'currency' => 'RUB'
                            ],
                            'vat_code' => 1
                        ]
                    ]
                ]
            );
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Обработка запроса
$tester = new YooKassaTestCases();
$method = $_GET['method'] ?? 'help';
$paymentId = $_GET['payment_id'] ?? null;

switch ($method) {
    case 'create':
        $response = $tester->createPayment();
        break;
    case 'check':
        if (!$paymentId) {
            $response = ['success' => false, 'error' => 'Не указан payment_id'];
        } else {
            $response = $tester->checkPayment($paymentId);
        }
        break;
    case 'cancel':
        if (!$paymentId) {
            $response = ['success' => false, 'error' => 'Не указан payment_id'];
        } else {
            $response = $tester->cancelPayment($paymentId);
        }
        break;
    case 'refund':
        if (!$paymentId) {
            $response = ['success' => false, 'error' => 'Не указан payment_id'];
        } else {
            $response = $tester->refundPayment($paymentId);
        }
        break;
    default:
        $response = [
            'success' => false,
            'error' => 'Неизвестный метод'
        ];
}

echo json_encode($response); 