<?php

/**
 * Тестовый скрипт для проверки chunked импорта
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ CHUNKED ИМПОРТА ===\n\n";

use Marvel\Services\ChunkedImportService;
use Marvel\Services\XmlImportService;

// Создаем тестовый CSV контент
$testCsvContent = "name,sku,price,description\n";
for ($i = 1; $i <= 1000; $i++) {
    $testCsvContent .= "Test Product $i,SKU-$i," . (100 + $i) . ",Description for product $i\n";
}

echo "1. Создан тестовый CSV с 1000 товарами\n";

// Тестируем ChunkedImportService
$chunkedService = new ChunkedImportService();

echo "2. Тестируем подсчет товаров...\n";
$productCount = $chunkedService->countProducts($testCsvContent, 'csv');
echo "   Найдено товаров: $productCount\n";

echo "3. Тестируем расчет размера чанка...\n";
$chunkSize = $chunkedService->calculateOptimalChunkSize($testCsvContent, 'csv');
echo "   Рекомендуемый размер чанка: $chunkSize\n";

echo "4. Тестируем запуск chunked импорта...\n";
$fieldMapping = [
    'name' => 'name',
    'sku' => 'sku', 
    'price' => 'price',
    'description' => 'description'
];

$options = [
    'shop_id' => 2, // Используем другой ID для тестирования
    'update_existing' => false,
    'dry_run' => true // Тестовый режим
];

$result = $chunkedService->startChunkedImport(
    $testCsvContent,
    'csv',
    $options,
    $fieldMapping,
    50 // Маленький размер чанка для теста
);

if ($result['success']) {
    echo "   ✅ Chunked импорт запущен успешно\n";
    echo "   Token: " . $result['token'] . "\n";
    echo "   Всего товаров: " . $result['total_products'] . "\n";
    echo "   Размер чанка: " . $result['chunk_size'] . "\n";
    echo "   Всего чанков: " . $result['total_chunks'] . "\n";
    
    $token = $result['token'];
    
    echo "\n5. Проверяем прогресс...\n";
    sleep(2); // Небольшая пауза
    
    $progress = $chunkedService->getImportProgress($token);
    if ($progress['success']) {
        echo "   Статус: " . $progress['progress']['status'] . "\n";
        echo "   Прогресс: " . $progress['progress']['progress_percent'] . "%\n";
        echo "   Чанков завершено: " . $progress['progress']['chunks_completed'] . "/" . $progress['progress']['total_chunks'] . "\n";
    }
    
    echo "\n6. Проверяем статистику...\n";
    $stats = $chunkedService->getImportStats($token);
    if ($stats['success']) {
        echo "   Всего обработано: " . $stats['stats']['total'] . "\n";
        echo "   Импортировано: " . $stats['stats']['imported'] . "\n";
        echo "   Обновлено: " . $stats['stats']['updated'] . "\n";
        echo "   Ошибок: " . $stats['stats']['errors'] . "\n";
    }
    
    echo "\n7. Очищаем тестовые данные...\n";
    $cleanup = $chunkedService->cleanupImport($token);
    if ($cleanup) {
        echo "   ✅ Данные очищены\n";
    } else {
        echo "   ⚠️  Не удалось очистить данные\n";
    }
    
} else {
    echo "   ❌ Ошибка запуска chunked импорта: " . $result['message'] . "\n";
}

echo "\n8. Проверяем активные импорты...\n";
$activeImports = $chunkedService->getActiveImports();
echo "   Активных импортов: " . count($activeImports) . "\n";

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";

// Дополнительные проверки
echo "\n=== ДОПОЛНИТЕЛЬНЫЕ ПРОВЕРКИ ===\n";

echo "1. Проверка директорий...\n";
$dirs = [
    'storage/app/xml-import-progress',
    'storage/app/xml-import-stats', 
    'storage/app/xml-import-chunks'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "   ✅ $dir существует\n";
    } else {
        echo "   ❌ $dir не существует\n";
    }
}

echo "\n2. Проверка настроек очереди...\n";
$queueConnection = env('QUEUE_CONNECTION', 'sync');
echo "   QUEUE_CONNECTION: $queueConnection\n";

if ($queueConnection === 'sync') {
    echo "   ⚠️  Внимание: Очередь настроена на синхронное выполнение\n";
    echo "   Рекомендуется изменить на 'database' или 'redis'\n";
}

echo "\n3. Проверка прав доступа...\n";
$storageDir = 'storage/app';
if (is_writable($storageDir)) {
    echo "   ✅ $storageDir доступен для записи\n";
} else {
    echo "   ❌ $storageDir недоступен для записи\n";
}

echo "\n=== РЕКОМЕНДАЦИИ ===\n";
echo "1. Запустите ./queue-setup.sh для настройки очереди\n";
echo "2. Запустите воркер очереди: ./start-queue-worker.sh\n";
echo "3. Для продакшена используйте Supervisor или systemd\n";
echo "4. Мониторьте логи: tail -f storage/logs/laravel.log\n";




