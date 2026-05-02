<?php

/**
 * Анализ использования S3 хранилища
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Place;

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== АНАЛИЗ ИСПОЛЬЗОВАНИЯ S3 ХРАНИЛИЩА ===\n\n";

// Получаем все файлы из S3
$allFiles = Storage::disk('s3')->allFiles();
echo "Всего файлов в S3: " . count($allFiles) . "\n\n";

// Анализируем по папкам
$folders = [
    'products/images' => 0,
    'products/thumbnails' => 0,
    'products/gallery' => 0,
    'places/images' => 0,
    'places/videos' => 0,
    'media' => 0,
    'other' => 0
];

$folderSizes = [
    'products/images' => 0,
    'products/thumbnails' => 0,
    'products/gallery' => 0,
    'places/images' => 0,
    'places/videos' => 0,
    'media' => 0,
    'other' => 0
];

$totalSize = 0;

foreach ($allFiles as $file) {
    try {
        $size = Storage::disk('s3')->size($file);
        $totalSize += $size;
        
        $folder = 'other';
        foreach ($folders as $folderName => $count) {
            if (strpos($file, $folderName) === 0) {
                $folder = $folderName;
                break;
            }
        }
        
        $folders[$folder]++;
        $folderSizes[$folder] += $size;
        
    } catch (Exception $e) {
        // Игнорируем ошибки
    }
}

echo "РАСПРЕДЕЛЕНИЕ ПО ПАПКАМ:\n";
echo "----------------------------------------\n";

foreach ($folders as $folder => $count) {
    $size = $folderSizes[$folder];
    $percentage = $totalSize > 0 ? round(($size / $totalSize) * 100, 2) : 0;
    
    echo sprintf("%-20s: %6d файлов (%8s, %5.1f%%)\n", 
        $folder, 
        $count, 
        formatBytes($size), 
        $percentage
    );
}

echo "\nОБЩИЙ РАЗМЕР: " . formatBytes($totalSize) . "\n\n";

// Анализируем по типам файлов
echo "РАСПРЕДЕЛЕНИЕ ПО ТИПАМ ФАЙЛОВ:\n";
echo "----------------------------------------\n";

$fileTypes = [];
$typeSizes = [];

foreach ($allFiles as $file) {
    try {
        $size = Storage::disk('s3')->size($file);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (empty($extension)) {
            $extension = 'no-extension';
        }
        
        $fileTypes[$extension] = ($fileTypes[$extension] ?? 0) + 1;
        $typeSizes[$extension] = ($typeSizes[$extension] ?? 0) + $size;
        
    } catch (Exception $e) {
        // Игнорируем ошибки
    }
}

// Сортируем по размеру
arsort($typeSizes);

foreach ($typeSizes as $type => $size) {
    $count = $fileTypes[$type];
    $percentage = $totalSize > 0 ? round(($size / $totalSize) * 100, 2) : 0;
    
    echo sprintf("%-10s: %6d файлов (%8s, %5.1f%%)\n", 
        $type, 
        $count, 
        formatBytes($size), 
        $percentage
    );
}

// Анализируем использование в базе данных
echo "\nАНАЛИЗ ИСПОЛЬЗОВАНИЯ В БАЗЕ ДАННЫХ:\n";
echo "----------------------------------------\n";

$usedUrls = [];
$usedCount = 0;

// Товары
$products = Product::select('id', 'image', 'gallery', 'video')->get();
foreach ($products as $product) {
    // Основное изображение
    if (isset($product->image) && !empty($product->image)) {
        $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
        if (is_array($imageData)) {
            if (isset($imageData['original'])) {
                $usedUrls[] = extractS3Key($imageData['original']);
                $usedCount++;
            }
            if (isset($imageData['thumbnail'])) {
                $usedUrls[] = extractS3Key($imageData['thumbnail']);
                $usedCount++;
            }
        }
    }

    // Галерея
    if (isset($product->gallery) && !empty($product->gallery)) {
        $galleryData = is_string($product->gallery) ? json_decode($product->gallery, true) : $product->gallery;
        if (is_array($galleryData)) {
            foreach ($galleryData as $image) {
                if (is_array($image)) {
                    if (isset($image['original'])) {
                        $usedUrls[] = extractS3Key($image['original']);
                        $usedCount++;
                    }
                    if (isset($image['thumbnail'])) {
                        $usedUrls[] = extractS3Key($image['thumbnail']);
                        $usedCount++;
                    }
                }
            }
        }
    }

    // Видео
    if (isset($product->video) && !empty($product->video)) {
        $videoData = is_string($product->video) ? json_decode($product->video, true) : $product->video;
        if (is_array($videoData) && isset($videoData['url'])) {
            $usedUrls[] = extractS3Key($videoData['url']);
            $usedCount++;
        }
    }
}

// Места
$places = Place::with(['images', 'videos'])->get();
foreach ($places as $place) {
    foreach ($place->images as $image) {
        $usedUrls[] = extractS3Key($image->url);
        $usedCount++;
    }
    foreach ($place->videos as $video) {
        $usedUrls[] = extractS3Key($video->url);
        $usedCount++;
    }
}

$usedUrls = array_unique($usedUrls);
$usedCount = count($usedUrls);

echo "Используемых файлов в БД: " . $usedCount . "\n";
echo "Неиспользуемых файлов: " . (count($allFiles) - $usedCount) . "\n";

$unusedSize = 0;
foreach ($allFiles as $file) {
    if (!in_array($file, $usedUrls)) {
        try {
            $unusedSize += Storage::disk('s3')->size($file);
        } catch (Exception $e) {
            // Игнорируем ошибки
        }
    }
}

echo "Размер неиспользуемых файлов: " . formatBytes($unusedSize) . "\n";
echo "Экономия при очистке: " . formatBytes($unusedSize) . "\n\n";

echo "=== АНАЛИЗ ЗАВЕРШЕН ===\n";

function extractS3Key($url)
{
    if (strpos($url, 's3.twcstorage.ru') !== false) {
        $parsedUrl = parse_url($url);
        return ltrim($parsedUrl['path'], '/');
    }
    return $url;
}

function formatBytes($size, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
