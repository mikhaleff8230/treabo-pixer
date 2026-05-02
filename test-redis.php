<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тест Redis и кэширования ===\n\n";

// 1. Проверяем текущий драйвер кэширования
echo "1. Текущий драйвер кэширования:\n";
echo "CACHE_DRIVER: " . env('CACHE_DRIVER', 'file') . "\n";
echo "SESSION_DRIVER: " . env('SESSION_DRIVER', 'file') . "\n";
echo "QUEUE_CONNECTION: " . env('QUEUE_CONNECTION', 'sync') . "\n\n";

// 2. Проверяем настройки Redis
echo "2. Настройки Redis:\n";
echo "REDIS_HOST: " . env('REDIS_HOST', '127.0.0.1') . "\n";
echo "REDIS_PORT: " . env('REDIS_PORT', '6379') . "\n";
echo "REDIS_PASSWORD: " . (env('REDIS_PASSWORD') ? 'установлен' : 'не установлен') . "\n";
echo "REDIS_DB: " . env('REDIS_DB', '0') . "\n";
echo "REDIS_CACHE_DB: " . env('REDIS_CACHE_DB', '1') . "\n\n";

// 3. Тестируем подключение к Redis
echo "3. Тест подключения к Redis:\n";
try {
    $redis = Redis::connection();
    $redis->ping();
    echo "✅ Redis подключение успешно!\n";
    
    // Тестируем запись и чтение
    $testKey = 'test_redis_connection';
    $testValue = 'test_value_' . time();
    
    $redis->set($testKey, $testValue);
    $retrievedValue = $redis->get($testKey);
    
    if ($retrievedValue === $testValue) {
        echo "✅ Запись и чтение из Redis работают!\n";
    } else {
        echo "❌ Ошибка при записи/чтении из Redis\n";
    }
    
    // Очищаем тестовый ключ
    $redis->del($testKey);
    
} catch (Exception $e) {
    echo "❌ Ошибка подключения к Redis: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Тестируем кэширование
echo "4. Тест кэширования:\n";
try {
    $cacheKey = 'test_cache_key';
    $cacheValue = 'test_cache_value_' . time();
    
    // Записываем в кэш
    Cache::put($cacheKey, $cacheValue, 60); // 60 секунд
    echo "✅ Запись в кэш выполнена\n";
    
    // Читаем из кэша
    $cachedValue = Cache::get($cacheKey);
    if ($cachedValue === $cacheValue) {
        echo "✅ Чтение из кэша работает!\n";
    } else {
        echo "❌ Ошибка при чтении из кэша\n";
    }
    
    // Очищаем тестовый ключ
    Cache::forget($cacheKey);
    
} catch (Exception $e) {
    echo "❌ Ошибка кэширования: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Проверяем статистику Redis
echo "5. Статистика Redis:\n";
try {
    $info = $redis->info();
    echo "Версия Redis: " . $info['redis_version'] . "\n";
    echo "Подключенные клиенты: " . $info['connected_clients'] . "\n";
    echo "Использованная память: " . round($info['used_memory_human'], 2) . "\n";
    echo "Количество ключей: " . $info['db0'] . "\n";
} catch (Exception $e) {
    echo "❌ Не удалось получить статистику Redis: " . $e->getMessage() . "\n";
}

echo "\n=== Тест завершен ===\n"; 