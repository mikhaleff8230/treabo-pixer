<?php

/**
 * Тестовый скрипт для проверки скачивания изображений
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ СКАЧИВАНИЯ ИЗОБРАЖЕНИЙ ===\n\n";

$testImageUrl = 'https://cs2.livemaster.ru/storage/ab/ee/0b246c269786cefbfe6421f640ax--ukrasheniya-sergi-moon.jpg';

echo "Тестируем URL: $testImageUrl\n\n";

// Тест 1: Простое скачивание
echo "1. ПРОСТОЕ СКАЧИВАНИЕ:\n";
$imageData = @file_get_contents($testImageUrl);
if ($imageData !== false) {
    echo "✅ Изображение скачано, размер: " . strlen($imageData) . " байт\n";
} else {
    echo "❌ Ошибка скачивания изображения\n";
    exit;
}

// Тест 2: Создание временного файла
echo "\n2. СОЗДАНИЕ ВРЕМЕННОГО ФАЙЛА:\n";
$tempFile = tempnam(sys_get_temp_dir(), 'xml_import_');
echo "Временный файл: $tempFile\n";

if (file_put_contents($tempFile, $imageData)) {
    echo "✅ Данные записаны во временный файл\n";
} else {
    echo "❌ Ошибка записи во временный файл\n";
    exit;
}

// Тест 3: Определение расширения
echo "\n3. ОПРЕДЕЛЕНИЕ РАСШИРЕНИЯ:\n";
$extension = pathinfo(parse_url($testImageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
if (empty($extension)) {
    $extension = 'jpg';
}
echo "Расширение: $extension\n";

// Тест 4: Переименование файла
echo "\n4. ПЕРЕИМЕНОВАНИЕ ФАЙЛА:\n";
$tempFileWithExt = $tempFile . '.' . $extension;
if (rename($tempFile, $tempFileWithExt)) {
    echo "✅ Файл переименован: $tempFileWithExt\n";
} else {
    echo "❌ Ошибка переименования файла\n";
    exit;
}

// Тест 5: Создание UploadedFile
echo "\n5. СОЗДАНИЕ UPLOADEDFILE:\n";
try {
    $uploadedFile = new \Illuminate\Http\UploadedFile(
        $tempFileWithExt,
        basename($testImageUrl),
        mime_content_type($tempFileWithExt),
        null,
        true
    );
    echo "✅ UploadedFile создан\n";
    echo "  Имя файла: " . $uploadedFile->getClientOriginalName() . "\n";
    echo "  MIME тип: " . $uploadedFile->getMimeType() . "\n";
    echo "  Размер: " . $uploadedFile->getSize() . " байт\n";
} catch (Exception $e) {
    echo "❌ Ошибка создания UploadedFile: " . $e->getMessage() . "\n";
    exit;
}

// Тест 6: Создание Attachment
echo "\n6. СОЗДАНИЕ ATTACHMENT:\n";
try {
    $attachment = new \Marvel\Database\Models\Attachment();
    $attachment->save();
    echo "✅ Attachment создан с ID: {$attachment->id}\n";
} catch (Exception $e) {
    echo "❌ Ошибка создания Attachment: " . $e->getMessage() . "\n";
    exit;
}

// Тест 7: Добавление медиа
echo "\n7. ДОБАВЛЕНИЕ МЕДИА:\n";
try {
    $media = $attachment->addMedia($uploadedFile)
        ->toMediaCollection();
    echo "✅ Медиа добавлено с ID: {$media->id}\n";
    echo "  Диск: {$media->disk}\n";
    echo "  URL: {$media->getUrl()}\n";
} catch (Exception $e) {
    echo "❌ Ошибка добавления медиа: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}

// Очистка
if (file_exists($tempFileWithExt)) {
    unlink($tempFileWithExt);
    echo "\n✅ Временный файл удален\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
