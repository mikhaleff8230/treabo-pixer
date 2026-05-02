<?php

/**
 * Скрипт для проверки безопасности перед удалением файлов
 * Показывает детальную информацию о файлах, которые будут удалены
 */

require_once __DIR__ . '/vendor/autoload.php';

use Marvel\Database\Models\Product;
use Marvel\Database\Models\Place;

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ПРОВЕРКА БЕЗОПАСНОСТИ УДАЛЕНИЯ ФАЙЛОВ ===\n\n";

// 1. Получаем все используемые файлы из БД
echo "1. ПОЛУЧЕНИЕ ИСПОЛЬЗУЕМЫХ ФАЙЛОВ ИЗ БД:\n";
echo "----------------------------------------\n";

$usedFiles = [];

// Товары
$products = Product::select('id', 'name', 'image', 'gallery', 'video')->get();
echo "Товаров в БД: " . $products->count() . "\n";

foreach ($products as $product) {
    // Основное изображение
    if (!empty($product->image)) {
        $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
        if (is_array($imageData)) {
            if (isset($imageData['original'])) {
                $localPath = extractLocalPath($imageData['original']);
                if ($localPath) {
                    $usedFiles[] = [
                        'path' => $localPath,
                        'type' => 'product_image',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ];
                }
            }
            if (isset($imageData['thumbnail'])) {
                $localPath = extractLocalPath($imageData['thumbnail']);
                if ($localPath) {
                    $usedFiles[] = [
                        'path' => $localPath,
                        'type' => 'product_thumbnail',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ];
                }
            }
        }
    }

    // Галерея
    if (!empty($product->gallery)) {
        $galleryData = is_string($product->gallery) ? json_decode($product->gallery, true) : $product->gallery;
        if (is_array($galleryData)) {
            foreach ($galleryData as $index => $image) {
                if (is_array($image)) {
                    if (isset($image['original'])) {
                        $localPath = extractLocalPath($image['original']);
                        if ($localPath) {
                            $usedFiles[] = [
                                'path' => $localPath,
                                'type' => 'product_gallery_original',
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'gallery_index' => $index
                            ];
                        }
                    }
                    if (isset($image['thumbnail'])) {
                        $localPath = extractLocalPath($image['thumbnail']);
                        if ($localPath) {
                            $usedFiles[] = [
                                'path' => $localPath,
                                'type' => 'product_gallery_thumbnail',
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'gallery_index' => $index
                            ];
                        }
                    }
                }
            }
        }
    }

    // Видео
    if (!empty($product->video)) {
        $videoData = is_string($product->video) ? json_decode($product->video, true) : $product->video;
        if (is_array($videoData) && isset($videoData['url'])) {
            $localPath = extractLocalPath($videoData['url']);
            if ($localPath) {
                $usedFiles[] = [
                    'path' => $localPath,
                    'type' => 'product_video',
                    'product_id' => $product->id,
                    'product_name' => $product->name
                ];
            }
        }
    }
}

// Места
$places = Place::with(['images', 'videos'])->get();
echo "Мест в БД: " . $places->count() . "\n";

foreach ($places as $place) {
    foreach ($place->images as $image) {
        $localPath = extractLocalPath($image->url);
        if ($localPath) {
            $usedFiles[] = [
                'path' => $localPath,
                'type' => 'place_image',
                'place_id' => $place->id,
                'place_title' => $place->title
            ];
        }
    }
    foreach ($place->videos as $video) {
        $localPath = extractLocalPath($video->url);
        if ($localPath) {
            $usedFiles[] = [
                'path' => $localPath,
                'type' => 'place_video',
                'place_id' => $place->id,
                'place_title' => $place->title
            ];
        }
    }
}

// MediaLibrary
$mediaFiles = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('disk', 'public')->get();
echo "MediaLibrary файлов: " . $mediaFiles->count() . "\n";

foreach ($mediaFiles as $media) {
    $usedFiles[] = [
        'path' => $media->file_name,
        'type' => 'media_library',
        'media_id' => $media->id,
        'model_type' => $media->model_type,
        'model_id' => $media->model_id
    ];
}

echo "Всего используемых файлов: " . count($usedFiles) . "\n\n";

// 2. Получаем все файлы в storage/app/public
echo "2. ПОЛУЧЕНИЕ ВСЕХ ФАЙЛОВ В STORAGE/APP/PUBLIC:\n";
echo "----------------------------------------\n";

$publicPath = base_path('storage/app/public');
$allFiles = [];

if (is_dir($publicPath)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($publicPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            
            $allFiles[] = [
                'path' => $relativePath,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'full_path' => $file->getPathname()
            ];
        }
    }
}

echo "Всего файлов в storage: " . count($allFiles) . "\n\n";

// 3. Находим неиспользуемые файлы
echo "3. АНАЛИЗ НЕИСПОЛЬЗУЕМЫХ ФАЙЛОВ:\n";
echo "----------------------------------------\n";

$usedPaths = array_column($usedFiles, 'path');
$unusedFiles = [];

foreach ($allFiles as $file) {
    if (!in_array($file['path'], $usedPaths)) {
        $unusedFiles[] = $file;
    }
}

echo "Неиспользуемых файлов: " . count($unusedFiles) . "\n";

$unusedSize = 0;
foreach ($unusedFiles as $file) {
    $unusedSize += $file['size'];
}

echo "Размер неиспользуемых файлов: " . formatBytes($unusedSize) . "\n\n";

// 4. Детальный анализ неиспользуемых файлов
echo "4. ДЕТАЛЬНЫЙ АНАЛИЗ НЕИСПОЛЬЗУЕМЫХ ФАЙЛОВ:\n";
echo "----------------------------------------\n";

// Группируем по папкам
$folders = [];
foreach ($unusedFiles as $file) {
    $folder = dirname($file['path']);
    if ($folder === '.') $folder = 'root';
    
    if (!isset($folders[$folder])) {
        $folders[$folder] = ['count' => 0, 'size' => 0, 'files' => []];
    }
    $folders[$folder]['count']++;
    $folders[$folder]['size'] += $file['size'];
    $folders[$folder]['files'][] = $file;
}

echo "Распределение по папкам:\n";
foreach ($folders as $folder => $data) {
    echo sprintf("  %-30s: %4d файлов (%s)\n", 
        $folder, 
        $data['count'], 
        formatBytes($data['size'])
    );
}
echo "\n";

// 5. Показываем примеры файлов для удаления
echo "5. ПРИМЕРЫ ФАЙЛОВ ДЛЯ УДАЛЕНИЯ:\n";
echo "----------------------------------------\n";

$examples = array_slice($unusedFiles, 0, 20);
foreach ($examples as $file) {
    $age = time() - $file['modified'];
    $ageText = $age > 86400 ? round($age / 86400) . ' дней' : round($age / 3600) . ' часов';
    
    echo sprintf("  %-50s %8s (возраст: %s)\n", 
        $file['path'], 
        formatBytes($file['size']),
        $ageText
    );
}

if (count($unusedFiles) > 20) {
    echo "  ... и еще " . (count($unusedFiles) - 20) . " файлов\n";
}
echo "\n";

// 6. Проверка безопасности
echo "6. ПРОВЕРКА БЕЗОПАСНОСТИ:\n";
echo "----------------------------------------\n";

$dangerousFiles = [];
$safeFiles = [];

foreach ($unusedFiles as $file) {
    $isDangerous = false;
    $reasons = [];
    
    // Проверяем на небезопасные пути
    if (strpos($file['path'], '..') !== false) {
        $isDangerous = true;
        $reasons[] = 'содержит ..';
    }
    
    if (strpos($file['path'], '//') !== false) {
        $isDangerous = true;
        $reasons[] = 'содержит //';
    }
    
    if (strpos($file['path'], '/') === 0) {
        $isDangerous = true;
        $reasons[] = 'начинается с /';
    }
    
    // Проверяем, что файл находится в правильной директории
    $realPath = realpath($file['full_path']);
    $storagePath = realpath(base_path('storage/app/public'));
    
    if (!$realPath || strpos($realPath, $storagePath) !== 0) {
        $isDangerous = true;
        $reasons[] = 'вне storage/app/public';
    }
    
    if ($isDangerous) {
        $dangerousFiles[] = [
            'file' => $file,
            'reasons' => $reasons
        ];
    } else {
        $safeFiles[] = $file;
    }
}

echo "Безопасных файлов для удаления: " . count($safeFiles) . "\n";
echo "Опасных файлов (будут пропущены): " . count($dangerousFiles) . "\n\n";

if (!empty($dangerousFiles)) {
    echo "⚠️  ОПАСНЫЕ ФАЙЛЫ (будут пропущены):\n";
    foreach ($dangerousFiles as $dangerous) {
        echo "  - " . $dangerous['file']['path'] . " (" . implode(', ', $dangerous['reasons']) . ")\n";
    }
    echo "\n";
}

// 7. Итоговая статистика
echo "7. ИТОГОВАЯ СТАТИСТИКА:\n";
echo "----------------------------------------\n";

$safeSize = 0;
foreach ($safeFiles as $file) {
    $safeSize += $file['size'];
}

echo "✅ Безопасно удалить: " . count($safeFiles) . " файлов (" . formatBytes($safeSize) . ")\n";
echo "⚠️  Пропустить: " . count($dangerousFiles) . " файлов\n";
echo "📊 Общий размер storage: " . formatBytes(array_sum(array_column($allFiles, 'size'))) . "\n";
echo "💾 Можно освободить: " . formatBytes($safeSize) . "\n";

$percentage = count($allFiles) > 0 ? round(($safeSize / array_sum(array_column($allFiles, 'size'))) * 100, 2) : 0;
echo "📈 Процент освобождения: " . $percentage . "%\n\n";

echo "=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";

/**
 * Извлекает локальный путь из URL
 */
function extractLocalPath($url)
{
    if (empty($url)) {
        return '';
    }
    
    // Убираем домен и оставляем только путь
    if (strpos($url, '/storage/') !== false) {
        $parts = explode('/storage/', $url);
        $path = $parts[1] ?? '';
        
        // Убираем параметры запроса
        $path = explode('?', $path)[0];
        
        return $path;
    }
    
    // Если это уже локальный путь
    if (strpos($url, 'storage/') === 0) {
        return $url;
    }
    
    return '';
}

/**
 * Форматирует размер в байтах
 */
function formatBytes($size, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
