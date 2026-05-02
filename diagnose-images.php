<?php

/**
 * Скрипт диагностики изображений товаров
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ДИАГНОСТИКА ИЗОБРАЖЕНИЙ ТОВАРОВ ===\n\n";

use Marvel\Database\Models\Product;
use Marvel\Database\Models\Attachment;

// 1. Проверяем последние импортированные товары с изображениями
echo "1. ПОСЛЕДНИЕ ТОВАРЫ С ИЗОБРАЖЕНИЯМИ:\n";
echo str_repeat("=", 70) . "\n\n";

$products = Product::whereNotNull('image')
    ->orWhereNotNull('gallery')
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

echo "Найдено товаров: " . $products->count() . "\n\n";

foreach ($products as $product) {
    echo "┌─ Товар ID: {$product->id}\n";
    echo "│  Название: {$product->name}\n";
    echo "│  SKU: {$product->sku}\n";
    echo "│  Создан: {$product->created_at}\n";
    echo "│\n";
    
    // Основное изображение - RAW из БД
    echo "│  📷 ОСНОВНОЕ ИЗОБРАЖЕНИЕ (RAW из БД):\n";
    $imageRaw = $product->getAttributes()['image'] ?? null;
    if ($imageRaw) {
        echo "│     Тип: " . gettype($imageRaw) . "\n";
        echo "│     Содержимое: " . (is_string($imageRaw) ? substr($imageRaw, 0, 200) : json_encode($imageRaw)) . "\n";
    } else {
        echo "│     NULL\n";
    }
    echo "│\n";
    
    // Основное изображение - после JSON cast
    echo "│  📷 ОСНОВНОЕ ИЗОБРАЖЕНИЕ (после JSON cast):\n";
    $imageCast = $product->image;
    if ($imageCast) {
        echo "│     Тип: " . gettype($imageCast) . "\n";
        if (is_array($imageCast)) {
            echo "│     Структура:\n";
            echo "│       - original: " . ($imageCast['original'] ?? 'НЕТ') . "\n";
            echo "│       - thumbnail: " . ($imageCast['thumbnail'] ?? 'НЕТ') . "\n";
            echo "│       - id: " . ($imageCast['id'] ?? 'НЕТ') . "\n";
        } else {
            echo "│     Содержимое: " . json_encode($imageCast) . "\n";
        }
    } else {
        echo "│     NULL\n";
    }
    echo "│\n";
    
    // Галерея - RAW из БД
    echo "│  🖼️  ГАЛЕРЕЯ (RAW из БД):\n";
    $galleryRaw = $product->getAttributes()['gallery'] ?? null;
    if ($galleryRaw) {
        echo "│     Тип: " . gettype($galleryRaw) . "\n";
        echo "│     Содержимое: " . (is_string($galleryRaw) ? substr($galleryRaw, 0, 200) : json_encode($galleryRaw)) . "\n";
    } else {
        echo "│     NULL\n";
    }
    echo "│\n";
    
    // Галерея - после JSON cast
    echo "│  🖼️  ГАЛЕРЕЯ (после JSON cast):\n";
    $galleryCast = $product->gallery;
    if ($galleryCast && is_array($galleryCast)) {
        echo "│     Количество: " . count($galleryCast) . "\n";
        foreach ($galleryCast as $idx => $img) {
            if (is_array($img)) {
                echo "│     [{$idx}] original: " . ($img['original'] ?? 'НЕТ') . "\n";
                echo "│     [{$idx}] thumbnail: " . ($img['thumbnail'] ?? 'НЕТ') . "\n";
            }
        }
    } else {
        echo "│     NULL или не массив\n";
    }
    echo "└" . str_repeat("─", 68) . "\n\n";
}

// 2. Проверяем последние Attachment записи
echo "\n2. ПОСЛЕДНИЕ ATTACHMENT ЗАПИСИ:\n";
echo str_repeat("=", 70) . "\n\n";

$attachments = Attachment::with('media')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "Найдено attachments: " . $attachments->count() . "\n\n";

foreach ($attachments as $attachment) {
    echo "┌─ Attachment ID: {$attachment->id}\n";
    echo "│  Создан: {$attachment->created_at}\n";
    echo "│  Медиа файлов: " . $attachment->media->count() . "\n";
    
    foreach ($attachment->media as $media) {
        echo "│\n";
        echo "│  📎 Media ID: {$media->id}\n";
        echo "│     Имя файла: {$media->file_name}\n";
        echo "│     Mime: {$media->mime_type}\n";
        echo "│     Размер: " . round($media->size / 1024, 2) . " KB\n";
        echo "│     Disk: {$media->disk}\n";
        echo "│     Collection: {$media->collection_name}\n";
        echo "│\n";
        echo "│  🔗 URL (original): {$media->getUrl()}\n";
        
        if (strpos($media->mime_type, 'image/') !== false) {
            try {
                $thumbnailUrl = $media->getUrl('thumbnail');
                echo "│  🔗 URL (thumbnail): {$thumbnailUrl}\n";
            } catch (\Exception $e) {
                echo "│  🔗 URL (thumbnail): ERROR - " . $e->getMessage() . "\n";
            }
        }
    }
    echo "└" . str_repeat("─", 68) . "\n\n";
}

// 3. Проверяем конфигурацию S3
echo "\n3. КОНФИГУРАЦИЯ ХРАНИЛИЩА:\n";
echo str_repeat("=", 70) . "\n\n";

echo "Default filesystem disk: " . config('filesystems.default') . "\n";
echo "Media disk (если задано): " . (config('media-library.disk_name') ?? 'не задано') . "\n\n";

$s3Config = config('filesystems.disks.s3');
echo "S3 Configuration:\n";
echo "  Bucket: " . ($s3Config['bucket'] ?? 'НЕ ЗАДАНО') . "\n";
echo "  Region: " . ($s3Config['region'] ?? 'НЕ ЗАДАНО') . "\n";
echo "  Endpoint: " . ($s3Config['endpoint'] ?? 'НЕ ЗАДАНО') . "\n";
echo "  URL: " . ($s3Config['url'] ?? 'НЕ ЗАДАНО') . "\n";
echo "  Visibility: " . ($s3Config['visibility'] ?? 'НЕ ЗАДАНО') . "\n";
echo "  Use path style: " . (($s3Config['use_path_style_endpoint'] ?? false) ? 'true' : 'false') . "\n";

// 4. Тестируем доступность изображений
echo "\n\n4. ПРОВЕРКА ДОСТУПНОСТИ ИЗОБРАЖЕНИЙ:\n";
echo str_repeat("=", 70) . "\n\n";

$testProduct = Product::whereNotNull('image')->latest()->first();
if ($testProduct && $testProduct->image) {
    $imageData = is_array($testProduct->image) ? $testProduct->image : json_decode($testProduct->image, true);
    
    if (is_array($imageData) && isset($imageData['original'])) {
        $url = $imageData['original'];
        echo "Тестируем URL: {$url}\n\n";
        
        // Проверяем доступность через CURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        echo "HTTP Status: {$httpCode}\n";
        echo "Content-Type: {$contentType}\n";
        
        if ($httpCode === 200) {
            echo "✅ Изображение ДОСТУПНО\n";
        } else {
            echo "❌ Изображение НЕ ДОСТУПНО (код {$httpCode})\n";
        }
    } else {
        echo "❌ Неправильная структура данных изображения\n";
        echo "Получено: " . json_encode($imageData) . "\n";
    }
} else {
    echo "❌ Не найдено товаров с изображениями для теста\n";
}

echo "\n\n=== ДИАГНОСТИКА ЗАВЕРШЕНА ===\n";
