<?php

/**
 * Тестовый скрипт для проверки импорта изображений
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ ИМПОРТА ИЗОБРАЖЕНИЙ ===\n\n";

use Marvel\Services\XmlImportService;

$xmlService = new XmlImportService();

// Тестовый URL изображения
$testImageUrl = 'https://cs2.livemaster.ru/storage/ab/ee/0b246c269786cefbfe6421f640ax--ukrasheniya-sergi-moon.jpg';

echo "Тестируем URL: $testImageUrl\n\n";

// Тестируем создание аттачмента
echo "1. Создание аттачмента с загрузкой:\n";
$attachment = $xmlService->createAttachmentFromUrl($testImageUrl, true);

if ($attachment) {
    echo "✅ Аттачмент создан успешно:\n";
    echo "  ID: " . $attachment['id'] . "\n";
    echo "  Original: " . $attachment['original'] . "\n";
    echo "  Thumbnail: " . $attachment['thumbnail'] . "\n";
} else {
    echo "❌ Ошибка создания аттачмента\n";
}

echo "\n2. Создание аттачмента без загрузки (внешняя ссылка):\n";
$externalAttachment = $xmlService->createAttachmentFromUrl($testImageUrl, false);

if ($externalAttachment) {
    echo "✅ Внешняя ссылка обработана:\n";
    echo "  ID: " . $externalAttachment['id'] . "\n";
    echo "  Original: " . $externalAttachment['original'] . "\n";
    echo "  Thumbnail: " . $externalAttachment['thumbnail'] . "\n";
} else {
    echo "❌ Ошибка обработки внешней ссылки\n";
}

echo "\n3. Проверка настроек S3:\n";
echo "FILESYSTEM_DISK: " . env('FILESYSTEM_DISK', 'local') . "\n";
echo "MEDIA_DISK: " . env('MEDIA_DISK', 'local') . "\n";
echo "AWS_BUCKET: " . env('AWS_BUCKET', 'not set') . "\n";

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
