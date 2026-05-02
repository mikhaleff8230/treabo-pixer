<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Настройка подключения к БД
$config = require 'config/database.php';
$pdo = new PDO(
    "mysql:host={$config['connections']['mysql']['host']};dbname={$config['connections']['mysql']['database']}",
    $config['connections']['mysql']['username'],
    $config['connections']['mysql']['password']
);

echo "=== Тест API динамических товаров ===\n\n";

// Тест 1: Проверка индексов
echo "1. Проверка индексов в БД:\n";
$indexes = $pdo->query("SHOW INDEX FROM products")->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_column($indexes, 'Key_name');
$requiredIndexes = [
    'idx_products_status_language',
    'idx_products_shop_status',
    'idx_products_type_status',
    'idx_products_updated_status',
    'idx_products_status_lang_updated'
];

foreach ($requiredIndexes as $index) {
    if (in_array($index, $indexNames)) {
        echo "✅ $index - создан\n";
    } else {
        echo "❌ $index - НЕ НАЙДЕН\n";
    }
}

echo "\n";

// Тест 2: Проверка производительности запроса
echo "2. Тест производительности запроса товаров:\n";
$startTime = microtime(true);

$query = "
    SELECT p.*, s.name as shop_name, t.name as type_name
    FROM products p
    LEFT JOIN shops s ON p.shop_id = s.id
    LEFT JOIN types t ON p.type_id = t.id
    WHERE p.status = 'publish' 
    AND p.language = 'ru'
    ORDER BY p.updated_at DESC
    LIMIT 20
";

$result = $pdo->query($query);
$products = $result->fetchAll(PDO::FETCH_ASSOC);

$endTime = microtime(true);
$executionTime = ($endTime - $startTime) * 1000; // в миллисекундах

echo "✅ Запрос выполнен за " . round($executionTime, 2) . " мс\n";
echo "✅ Найдено товаров: " . count($products) . "\n";

if ($executionTime > 100) {
    echo "⚠️  Время выполнения превышает 100мс - рекомендуется оптимизация\n";
} else {
    echo "✅ Производительность в норме\n";
}

echo "\n";

// Тест 3: Проверка кэширования
echo "3. Тест кэширования:\n";
if (class_exists('Illuminate\Support\Facades\Cache')) {
    echo "✅ Кэширование доступно\n";
    
    // Тест записи в кэш
    $testKey = 'test_products_cache';
    $testData = ['test' => 'data', 'timestamp' => time()];
    
    try {
        Cache::put($testKey, $testData, 60);
        $cachedData = Cache::get($testKey);
        
        if ($cachedData && $cachedData['test'] === 'data') {
            echo "✅ Запись и чтение из кэша работает\n";
        } else {
            echo "❌ Проблема с кэшированием\n";
        }
        
        Cache::forget($testKey);
    } catch (Exception $e) {
        echo "❌ Ошибка кэширования: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Кэширование недоступно\n";
}

echo "\n";

// Тест 4: Проверка API endpoints
echo "4. Тест API endpoints:\n";
$baseUrl = 'http://localhost:8000/api';

$endpoints = [
    '/products/dynamic?limit=5',
    '/products/search?q=тест&limit=5',
    '/products/filters'
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Тестируем: $url\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Accept: application/json',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && !isset($data['error'])) {
            echo "✅ $endpoint - работает\n";
        } else {
            echo "❌ $endpoint - ошибка в ответе\n";
        }
    } else {
        echo "❌ $endpoint - недоступен\n";
    }
}

echo "\n=== Тест завершен ===\n";
