<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Marvel\Payments\Tinkoff;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;

// Проверка окружения
if (!app()->environment('production')) {
    die("Этот скрипт предназначен только для боевого сервера!\n");
}

// Проверка наличия необходимых переменных окружения
$requiredEnvVars = [
    'TINKOFF_TERMINAL_KEY',
    'TINKOFF_PASSWORD',
    'TINKOFF_API_URL'
];

foreach ($requiredEnvVars as $var) {
    if (empty(env($var))) {
        die("Ошибка: Отсутствует переменная окружения {$var}\n");
    }
}

try {
    $tinkoff = new Tinkoff();
    
    // Создаем тестовый платеж
    $paymentData = [
        'order_id' => 'TEST-' . time(),
        'amount' => 1.00,
        'success_url' => config('app.url') . '/payment/success',
        'cancel_url' => config('app.url') . '/payment/fail'
    ];

    $payment = $tinkoff->getIntent($paymentData);

    echo "=== Информация о платеже ===\n";
    echo "ID платежа: " . ($payment['payment_id'] ?? 'Н/Д') . "\n";
    echo "URL для оплаты: " . ($payment['payment_url'] ?? 'Н/Д') . "\n";
    echo "Сумма: 1.00 руб.\n";
    echo "===========================\n\n";

    if (isset($payment['payment_id'])) {
        // Проверяем статус платежа
        $status = $tinkoff->verify($payment['payment_id']);
        echo "=== Статус платежа ===\n";
        echo "Статус: " . ($status ? 'Подтвержден' : 'Не подтвержден') . "\n";
        echo "=====================\n\n";

        // Проверяем все возможные статусы заказа
        echo "=== Статусы заказа ===\n";
        foreach (OrderStatus::cases() as $status) {
            echo $status->name . " => " . $status->value . "\n";
        }
        echo "=====================\n\n";

        // Проверяем все возможные статусы платежа
        echo "=== Статусы платежа ===\n";
        foreach (PaymentStatus::cases() as $status) {
            echo $status->name . " => " . $status->value . "\n";
        }
        echo "=====================\n\n";

        // Проверяем возможность отмены платежа
        echo "=== Проверка отмены платежа ===\n";
        try {
            $cancel = $tinkoff->cancelPayment($payment['payment_id']);
            echo "Результат отмены: " . ($cancel['Success'] ? 'Успешно' : 'Ошибка') . "\n";
            if (!$cancel['Success']) {
                echo "Причина: " . ($cancel['Message'] ?? 'Неизвестно') . "\n";
            }
        } catch (\Exception $e) {
            echo "Ошибка при отмене: " . $e->getMessage() . "\n";
        }
        echo "==============================\n";
    }
} catch (\Exception $e) {
    echo "=== Ошибка ===\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
    if (isset($e->errorData)) {
        echo "Детали ошибки:\n";
        print_r($e->errorData);
    }
    echo "==============\n";
} 