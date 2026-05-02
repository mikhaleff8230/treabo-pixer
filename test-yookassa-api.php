<?php

/**
 * Тестовый скрипт для проверки работы API ЮKassa
 * 
 * Запуск: php test-yookassa-api.php
 */

// Раскомментируйте одну из строк в зависимости от окружения:
// Для локальной разработки:
// $apiUrl = 'http://localhost:8000/api/test-yookassa';
// $apiUrlOrder = 'http://localhost:8000/api/custom-yookassa-order';

// Для продакшена:
$apiUrl = 'https://api.sancan.ru/api/test-yookassa';
$apiUrlOrder = 'https://api.sancan.ru/api/custom-yookassa-order';

echo "=== Тестирование API ЮKassa ===\n\n";

// Тест 1: Проверка простого GET endpoint
echo "1. Тестируем GET /api/test-yookassa\n";
echo "URL: {$apiUrl}\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

if ($httpCode !== 200) {
    echo "❌ ОШИБКА: Роут не работает! (HTTP {$httpCode})\n\n";
    echo "Возможные причины:\n";
    echo "- Сервер не запущен (php artisan serve)\n";
    echo "- Роуты не загружены правильно\n";
    echo "- Ошибка в коде контроллера\n\n";
    exit(1);
} else {
    echo "✅ Роут работает!\n\n";
}

// Тест 2: Создание тестового заказа
echo "2. Тестируем POST /api/custom-yookassa-order\n";
echo "URL: {$apiUrlOrder}\n";

$testData = [
    'name' => 'Тестовый заказ',
    'email' => 'test@example.com',
    'phone' => '+79000000000',
    'amount' => 100.00,
    'shipping_address' => [
        'name' => 'Иван Иванов',
        'phone' => '+79000000000',
        'address' => 'Москва, ул. Тестовая, д. 1',
        'delivery_type' => 'courier'
    ]
];

$ch = curl_init($apiUrlOrder);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

if ($httpCode === 200 || $httpCode === 201) {
    $data = json_decode($response, true);
    if (isset($data['confirmation_token'])) {
        echo "✅ Заказ создан успешно!\n";
        echo "Confirmation Token: {$data['confirmation_token']}\n";
        echo "Order ID: {$data['order_id']}\n\n";
    } else {
        echo "⚠️ Заказ создан, но confirmation_token отсутствует\n";
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
} else {
    echo "❌ ОШИБКА: Заказ не создан (HTTP {$httpCode})\n\n";
    echo "Возможные причины:\n";
    echo "- Нет доступа к БД\n";
    echo "- Неправильная конфигурация ЮKassa (Shop ID, Secret Key)\n";
    echo "- Ошибка в коде контроллера\n\n";
    
    $errorData = json_decode($response, true);
    if (isset($errorData['message'])) {
        echo "Error message: {$errorData['message']}\n";
    }
}

echo "\n=== Тестирование завершено ===\n";
