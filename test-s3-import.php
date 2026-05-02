<?php

/**
 * Тестовый скрипт для проверки импорта в S3
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ ИМПОРТА В S3 ===\n\n";

use Marvel\Services\XmlImportService;
use Marvel\Database\Models\Attachment;
use Illuminate\Support\Facades\Storage;

// Проверяем настройки
echo "1. ПРОВЕРКА НАСТРОЕК:\n";
echo "FILESYSTEM_DISK: " . env('FILESYSTEM_DISK', 'local') . "\n";
echo "MEDIA_DISK: " . env('MEDIA_DISK', 'local') . "\n";
echo "AWS_BUCKET: " . env('AWS_BUCKET', 'not set') . "\n";
echo "AWS_ENDPOINT: " . env('AWS_ENDPOINT', 'not set') . "\n\n";

// Тестируем подключение к S3
echo "2. ПРОВЕРКА ПОДКЛЮЧЕНИЯ К S3:\n";
try {
    $files = Storage::disk('s3')->files('products');
    echo "✅ S3 подключен, найдено файлов в products/: " . count($files) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения к S3: " . $e->getMessage() . "\n";
    exit;
}

// Тестируем импорт
echo "\n3. ТЕСТ ИМПОРТА ИЗОБРАЖЕНИЯ:\n";
$xmlService = new XmlImportService();

$testImageUrl = 'https://cs2.livemaster.ru/storage/ab/ee/0b246c269786cefbfe6421f640ax--ukrasheniya-sergi-moon.jpg';

echo "Тестируем URL: $testImageUrl\n";

try {
    $attachment = $xmlService->createAttachmentFromUrl($testImageUrl, true);
} catch (Exception $e) {
    echo "❌ Исключение при создании аттачмента: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    $attachment = null;
}

if ($attachment) {
    echo "✅ Аттачмент создан:\n";
    echo "  ID: " . $attachment['id'] . "\n";
    echo "  Original: " . $attachment['original'] . "\n";
    echo "  Thumbnail: " . $attachment['thumbnail'] . "\n";
    
    // Проверяем, что файлы действительно в S3
    echo "\n4. ПРОВЕРКА ФАЙЛОВ В S3:\n";
    
    $attachmentModel = Attachment::find($attachment['id']);
    if ($attachmentModel) {
        $mediaFiles = $attachmentModel->getMedia();
        echo "Найдено медиа файлов: " . $mediaFiles->count() . "\n";
        
        foreach ($mediaFiles as $media) {
            echo "  Медиа ID: {$media->id}\n";
            echo "  Имя файла: {$media->file_name}\n";
            echo "  Диск: {$media->disk}\n";
            echo "  URL: {$media->getUrl()}\n";
            echo "  Путь в S3: {$media->getPath()}\n";
            
            // Проверяем, что файл существует в S3
            if (Storage::disk('s3')->exists($media->getPath())) {
                echo "  ✅ Файл существует в S3\n";
            } else {
                echo "  ❌ Файл НЕ найден в S3\n";
            }
        }
    }
} else {
    echo "❌ Ошибка создания аттачмента\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
