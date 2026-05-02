<?php

/**
 * Тестовый скрипт для проверки email системы
 * Запуск: php test-email-system.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Marvel\Services\EmailService;

// Создаем Laravel приложение
$app = new Application(realpath(__DIR__));
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Тестирование Email системы ===\n\n";

try {
    $emailService = app(EmailService::class);
    
    // 1. Проверка конфигурации
    echo "1. Проверка конфигурации email...\n";
    $config = $emailService->isEmailConfigurationValid();
    
    if ($config['is_valid']) {
        echo "✓ Конфигурация email корректна\n";
        echo "  - From: {$config['from_address']}\n";
        echo "  - Admin: {$config['admin_email']}\n";
        echo "  - Driver: {$config['mail_driver']}\n\n";
    } else {
        echo "✗ Конфигурация email некорректна\n";
        echo "  - From: " . ($config['from_address'] ?: 'НЕ УСТАНОВЛЕНО') . "\n";
        echo "  - Admin: " . ($config['admin_email'] ?: 'НЕ УСТАНОВЛЕНО') . "\n";
        echo "  - Driver: " . ($config['mail_driver'] ?: 'НЕ УСТАНОВЛЕНО') . "\n\n";
        exit(1);
    }
    
    // 2. Тест отправки тестового email
    echo "2. Отправка тестового email...\n";
    $testEmail = $config['admin_email'];
    
    if ($emailService->sendTestEmail($testEmail)) {
        echo "✓ Тестовый email отправлен на {$testEmail}\n\n";
    } else {
        echo "✗ Не удалось отправить тестовый email\n\n";
    }
    
    // 3. Проверка существования заказов для тестирования
    echo "3. Поиск заказов для тестирования...\n";
    $order = \Marvel\Database\Models\Order::with(['customer', 'shop.owner'])->first();
    
    if ($order) {
        echo "✓ Найден заказ #{$order->tracking_number}\n";
        
        // Тест уведомлений о заказе
        echo "4. Тестирование уведомлений о заказе...\n";
        $results = $emailService->sendOrderEventNotifications($order, 'created');
        
        echo "  - Клиент: " . ($results['customer'] ? '✓' : '✗') . "\n";
        echo "  - Владелец магазина: " . ($results['store_owner'] ? '✓' : '✗') . "\n";
        echo "  - Админ: " . ($results['admin'] ? '✓' : '✗') . "\n\n";
    } else {
        echo "✗ Заказы не найдены, пропускаем тест уведомлений о заказах\n\n";
    }
    
    // 4. Проверка существования товаров для тестирования
    echo "5. Поиск товаров для тестирования...\n";
    $product = \Marvel\Database\Models\Product::with(['shop.owner'])->first();
    
    if ($product) {
        echo "✓ Найден товар: {$product->name}\n";
        
        // Тест уведомлений о товаре
        echo "6. Тестирование уведомлений о товаре...\n";
        $results = $emailService->sendProductEventNotifications($product, 'approved');
        
        echo "  - Продавец: " . ($results['vendor'] ? '✓' : '✗') . "\n";
        echo "  - Админ: " . ($results['admin'] ? '✓' : '✗') . "\n\n";
    } else {
        echo "✗ Товары не найдены, пропускаем тест уведомлений о товарах\n\n";
    }
    
    // 5. Тест массовой рассылки
    echo "7. Тестирование массовой рассылки...\n";
    $users = \Marvel\Database\Models\User::limit(3)->get();
    
    if ($users->count() > 0) {
        $userIds = $users->pluck('id')->toArray();
        $sentCount = $emailService->sendBulkEmail(
            $userIds,
            'Тестовое сообщение',
            'Это тестовое сообщение для проверки массовой рассылки.',
            'emails.custom'
        );
        
        echo "✓ Массовая рассылка: отправлено {$sentCount} из " . count($userIds) . " писем\n\n";
    } else {
        echo "✗ Пользователи не найдены, пропускаем тест массовой рассылки\n\n";
    }
    
    echo "=== Тестирование завершено ===\n";
    echo "Проверьте почтовые ящики для подтверждения получения писем.\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}


