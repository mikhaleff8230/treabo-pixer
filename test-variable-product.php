<?php

/**
 * Скрипт для тестирования создания вариативных товаров через PHP
 * Использование: php test-variable-product.php [API_URL] [TOKEN] [SHOP_ID] [TYPE_ID]
 */

// Цвета для вывода
define('RED', "\033[0;31m");
define('GREEN', "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

// Параметры
$apiUrl = $argv[1] ?? 'https://api.sancan.ru/api';
$token = $argv[2] ?? getenv('TOKEN') ?: '';
$shopId = (int)($argv[3] ?? 1);
$typeId = (int)($argv[4] ?? 1);

echo BLUE . "========================================\n" . NC;
echo BLUE . "Тестирование создания вариативного товара\n" . NC;
echo BLUE . "========================================\n" . NC;
echo "\n";

// Функции для вывода
function error($message) {
    echo RED . "❌ ОШИБКА: $message\n" . NC;
}

function success($message) {
    echo GREEN . "✅ $message\n" . NC;
}

function info($message) {
    echo YELLOW . "ℹ️  $message\n" . NC;
}

function step($number, $title) {
    echo "\n";
    echo BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . NC;
    echo BLUE . "ЭТАП $number: $title\n" . NC;
    echo BLUE . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . NC;
}

// Функция для выполнения HTTP запроса
function httpRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false, // Отключаем проверку SSL для локальных сертификатов
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    if ($data && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'body' => $response,
        'http_code' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

// ЭТАП 1: Проверка доступности API
step(1, "Проверка доступности API");
info("URL: $apiUrl");

$response = httpRequest("$apiUrl/test/variable-product/check");
if ($response['http_code'] === 200) {
    success("API доступен (HTTP {$response['http_code']})");
    if (isset($response['data'])) {
        echo json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    error("API недоступен (HTTP {$response['http_code']})");
    if (isset($response['error'])) {
        error($response['error']);
    }
    info("Проверьте, что сервер запущен и URL правильный");
    exit(1);
}

// ЭТАП 2: Проверка endpoint для проверки данных
step(2, "Проверка endpoint /test/variable-product/check");
if ($response['http_code'] === 200) {
    success("Endpoint отвечает корректно");
} else {
    error("Endpoint не отвечает (HTTP {$response['http_code']})");
    exit(1);
}

// ЭТАП 3: Проверка авторизации
step(3, "Проверка авторизации");
if (empty($token)) {
    error("Токен не указан");
    info("Использование: php test-variable-product.php [API_URL] [TOKEN] [SHOP_ID] [TYPE_ID]");
    info("Или установите переменную окружения: export TOKEN=your_token");
    exit(1);
} else {
    success("Токен указан: " . substr($token, 0, 20) . "...");
}

// Проверка валидности токена
$authCheck = httpRequest("$apiUrl/test/variable-product", 'POST', [], $token);
if ($authCheck['http_code'] === 401 || $authCheck['http_code'] === 403) {
    error("Токен невалиден или нет прав доступа (HTTP {$authCheck['http_code']})");
    exit(1);
} elseif ($authCheck['http_code'] === 422 || $authCheck['http_code'] === 500) {
    success("Токен валиден (получен ответ HTTP {$authCheck['http_code']} - это нормально для пустого запроса)");
} else {
    info("Статус авторизации: HTTP {$authCheck['http_code']}");
}

// ЭТАП 4: Подготовка тестовых данных
step(4, "Подготовка тестовых данных");
info("Shop ID: $shopId");
info("Type ID: $typeId");

$timestamp = time();
$testData = [
    'name' => "Тестовый вариативный товар $timestamp",
    'product_type' => 'variable',
    'shop_id' => $shopId,
    'type_id' => $typeId,
    'description' => 'Тестовое описание',
    'unit' => 'шт.',
    'status' => 'draft',
    'variations' => [1, 2],
    'variation_options' => [
        'upsert' => [
            [
                'price' => '100.00',
                'quantity' => 10,
                'sku' => "TEST-$timestamp-1",
                'title' => 'Вариант 1',
                'options' => json_encode([['name' => 'Цвет', 'value' => 'Красный']]),
            ],
            [
                'price' => '150.00',
                'quantity' => 5,
                'sku' => "TEST-$timestamp-2",
                'title' => 'Вариант 2',
                'options' => json_encode([['name' => 'Цвет', 'value' => 'Синий']]),
            ],
        ],
    ],
];

success("Тестовые данные подготовлены");
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// ЭТАП 5: Отправка запроса на создание товара
step(5, "Отправка запроса на создание товара");
info("Отправка POST запроса...");

$createResponse = httpRequest("$apiUrl/test/variable-product", 'POST', $testData, $token);

echo "\n";
info("HTTP Status Code: {$createResponse['http_code']}");

if ($createResponse['http_code'] === 201 || $createResponse['http_code'] === 200) {
    success("Товар создан успешно!");
    echo json_encode($createResponse['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    if (isset($createResponse['data']['product']['id'])) {
        echo "\n";
        info("ID созданного товара: {$createResponse['data']['product']['id']}");
    }
} elseif ($createResponse['http_code'] === 422) {
    error("Ошибка валидации (HTTP 422)");
    echo json_encode($createResponse['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} elseif ($createResponse['http_code'] === 500) {
    error("Внутренняя ошибка сервера (HTTP 500)");
    echo $createResponse['body'] . "\n";
    info("Проверьте логи Laravel: storage/logs/laravel.log");
} elseif ($createResponse['http_code'] === 401 || $createResponse['http_code'] === 403) {
    error("Ошибка авторизации (HTTP {$createResponse['http_code']})");
    echo $createResponse['body'] . "\n";
} else {
    error("Неожиданный статус (HTTP {$createResponse['http_code']})");
    echo $createResponse['body'] . "\n";
}

// ЭТАП 6: Проверка логов Laravel
step(6, "Проверка логов");
info("Проверьте логи Laravel для детальной информации:");
info("tail -f storage/logs/laravel.log | grep 'ProductRepository::storeProduct'");
info("или");
info("tail -f storage/logs/laravel.log | grep 'TEST:'");

echo "\n";
echo BLUE . "========================================\n" . NC;
if ($createResponse['http_code'] === 201 || $createResponse['http_code'] === 200) {
    echo GREEN . "✅ Тестирование завершено успешно!\n" . NC;
    exit(0);
} else {
    echo RED . "❌ Тестирование завершено с ошибками\n" . NC;
    exit(1);
}

