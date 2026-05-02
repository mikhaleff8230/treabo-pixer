<?php

require '../vendor/autoload.php';
require '../bootstrap/app.php';

use App\Services\YooKassa\YooKassaService;
use App\Services\YooKassa\YooKassaConfig;
use Marvel\Database\Models\Order;

// Включаем отображение ошибок для тестирования
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Получаем конфигурацию
$config = config('services.yookassa');
$yookassa = new YooKassaService(
    new YooKassaConfig(
        shopId: $config['shop_id'],
        secretKey: $config['secret_key'],
        isTest: $config['is_test']
    )
);

// Функция для вывода результата
function showResult($title, $data) {
    echo "<h2>{$title}</h2>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<hr>";
}

// Получаем метод из GET параметра
$method = $_GET['method'] ?? 'help';

try {
    switch ($method) {
        case 'create':
            // Создание тестового платежа
            $result = $yookassa->createPayment(
                orderId: 'TEST-' . time(),
                amount: 100.00,
                description: 'Тестовый платеж',
                successUrl: 'https://' . $_SERVER['HTTP_HOST'] . '/test-yookassa.php?method=check',
                failUrl: 'https://' . $_SERVER['HTTP_HOST'] . '/test-yookassa.php?method=help',
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
            showResult('Создание платежа', $result);
            
            // Добавляем кнопку для перехода к оплате
            if (isset($result['payment_url'])) {
                echo "<a href='{$result['payment_url']}' target='_blank'>Перейти к оплате</a>";
            }
            break;

        case 'check':
            // Проверка статуса платежа
            if (!isset($_GET['payment_id'])) {
                throw new Exception('Не указан payment_id');
            }
            $result = $yookassa->checkPayment($_GET['payment_id']);
            showResult('Проверка статуса платежа', $result);
            break;

        case 'cancel':
            // Отмена платежа
            if (!isset($_GET['payment_id'])) {
                throw new Exception('Не указан payment_id');
            }
            $result = $yookassa->cancelPayment($_GET['payment_id']);
            showResult('Отмена платежа', $result);
            break;

        case 'refund':
            // Возврат платежа
            if (!isset($_GET['payment_id'])) {
                throw new Exception('Не указан payment_id');
            }
            $result = $yookassa->refundPayment(
                $_GET['payment_id'],
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
            showResult('Возврат платежа', $result);
            break;

        default:
            // Показываем справку
            echo "
            <h1>Тестирование методов ЮKassa</h1>
            <p>Доступные методы:</p>
            <ul>
                <li><a href='?method=create'>Создать тестовый платеж</a></li>
                <li><a href='?method=check&payment_id=PAYMENT_ID'>Проверить статус платежа</a></li>
                <li><a href='?method=cancel&payment_id=PAYMENT_ID'>Отменить платеж</a></li>
                <li><a href='?method=refund&payment_id=PAYMENT_ID'>Сделать возврат платежа</a></li>
            </ul>
            <p>Примечание: Замените PAYMENT_ID на реальный идентификатор платежа после его создания.</p>
            ";
    }
} catch (Exception $e) {
    showResult('Ошибка', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 