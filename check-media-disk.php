<?php

/**
 * Скрипт для проверки настроек MediaLibrary
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ПРОВЕРКА НАСТРОЕК MEDIALIBRARY ===\n\n";

// Проверяем конфигурацию
echo "1. КОНФИГУРАЦИЯ MEDIALIBRARY:\n";
echo "MEDIA_DISK: " . config('media-library.disk_name') . "\n";
echo "FILESYSTEM_DISK: " . config('filesystems.default') . "\n\n";

// Проверяем доступные диски
echo "2. ДОСТУПНЫЕ ДИСКИ:\n";
$disks = config('filesystems.disks');
foreach ($disks as $name => $config) {
    echo "  $name: " . ($config['driver'] ?? 'unknown') . "\n";
}
echo "\n";

// Проверяем S3 конфигурацию
echo "3. КОНФИГУРАЦИЯ S3:\n";
$s3Config = config('filesystems.disks.s3');
if ($s3Config) {
    echo "  Driver: " . ($s3Config['driver'] ?? 'unknown') . "\n";
    echo "  Bucket: " . ($s3Config['bucket'] ?? 'not set') . "\n";
    echo "  Endpoint: " . ($s3Config['endpoint'] ?? 'not set') . "\n";
    echo "  Region: " . ($s3Config['region'] ?? 'not set') . "\n";
} else {
    echo "  ❌ S3 конфигурация не найдена\n";
}
echo "\n";

// Тестируем создание Attachment
echo "4. ТЕСТ СОЗДАНИЯ ATTACHMENT:\n";
try {
    $attachment = new \Marvel\Database\Models\Attachment();
    $attachment->save();
    echo "✅ Attachment создан с ID: {$attachment->id}\n";
    
    // Проверяем, какой диск будет использован
    echo "  Диск по умолчанию для медиа: " . config('media-library.disk_name') . "\n";
    
    // Удаляем тестовый attachment
    $attachment->delete();
    echo "✅ Тестовый attachment удален\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";










