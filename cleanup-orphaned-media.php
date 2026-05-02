<?php

/**
 * Скрипт для очистки старых медиа-файлов, которые не привязаны к товарам или местам
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceImage;
use Marvel\Database\Models\PlaceVideo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ОЧИСТКА СТАРЫХ МЕДИА-ФАЙЛОВ ===\n\n";

// 1. Получаем все файлы из S3
echo "1. ПОЛУЧЕНИЕ СПИСКА ФАЙЛОВ ИЗ S3:\n";
echo "----------------------------------------\n";

try {
    $allFiles = Storage::disk('s3')->allFiles();
    echo "Всего файлов в S3: " . count($allFiles) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения к S3: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Получаем все используемые URL'ы из товаров
echo "\n2. АНАЛИЗ ИСПОЛЬЗУЕМЫХ ФАЙЛОВ:\n";
echo "----------------------------------------\n";

$usedUrls = [];

// Анализируем товары
$products = Product::select('id', 'name', 'image', 'gallery', 'video')->get();
echo "Товаров для анализа: " . $products->count() . "\n";

foreach ($products as $product) {
    // Основное изображение
    if (!empty($product->image)) {
        $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
        if (is_array($imageData)) {
            if (isset($imageData['original'])) {
                $usedUrls[] = $this->extractS3Key($imageData['original']);
            }
            if (isset($imageData['thumbnail'])) {
                $usedUrls[] = $this->extractS3Key($imageData['thumbnail']);
            }
        }
    }

    // Галерея
    if (!empty($product->gallery)) {
        $galleryData = is_string($product->gallery) ? json_decode($product->gallery, true) : $product->gallery;
        if (is_array($galleryData)) {
            foreach ($galleryData as $image) {
                if (is_array($image)) {
                    if (isset($image['original'])) {
                        $usedUrls[] = $this->extractS3Key($image['original']);
                    }
                    if (isset($image['thumbnail'])) {
                        $usedUrls[] = $this->extractS3Key($image['thumbnail']);
                    }
                }
            }
        }
    }

    // Видео
    if (!empty($product->video)) {
        $videoData = is_string($product->video) ? json_decode($product->video, true) : $product->video;
        if (is_array($videoData) && isset($videoData['url'])) {
            $usedUrls[] = $this->extractS3Key($videoData['url']);
        }
    }
}

// Анализируем места
$places = Place::with(['images', 'videos'])->get();
echo "Мест для анализа: " . $places->count() . "\n";

foreach ($places as $place) {
    // Изображения мест
    foreach ($place->images as $image) {
        $usedUrls[] = $this->extractS3Key($image->url);
    }

    // Видео мест
    foreach ($place->videos as $video) {
        $usedUrls[] = $this->extractS3Key($video->url);
    }
}

// Анализируем MediaLibrary
$mediaFiles = Media::select('file_name', 'disk')->where('disk', 's3')->get();
echo "MediaLibrary файлов: " . $mediaFiles->count() . "\n";

foreach ($mediaFiles as $media) {
    $usedUrls[] = $media->file_name;
}

$usedUrls = array_filter(array_unique($usedUrls));
echo "Уникальных используемых файлов: " . count($usedUrls) . "\n\n";

// 3. Находим неиспользуемые файлы
echo "3. ПОИСК НЕИСПОЛЬЗУЕМЫХ ФАЙЛОВ:\n";
echo "----------------------------------------\n";

$orphanedFiles = [];
$totalSize = 0;

foreach ($allFiles as $file) {
    if (!in_array($file, $usedUrls)) {
        $orphanedFiles[] = $file;
        try {
            $size = Storage::disk('s3')->size($file);
            $totalSize += $size;
        } catch (Exception $e) {
            // Игнорируем ошибки получения размера
        }
    }
}

echo "Неиспользуемых файлов: " . count($orphanedFiles) . "\n";
echo "Размер неиспользуемых файлов: " . $this->formatBytes($totalSize) . "\n\n";

// 4. Показываем примеры неиспользуемых файлов
if (count($orphanedFiles) > 0) {
    echo "Примеры неиспользуемых файлов:\n";
    $examples = array_slice($orphanedFiles, 0, 10);
    foreach ($examples as $file) {
        echo "  - " . $file . "\n";
    }
    if (count($orphanedFiles) > 10) {
        echo "  ... и еще " . (count($orphanedFiles) - 10) . " файлов\n";
    }
    echo "\n";
}

// 5. Предлагаем варианты действий
echo "4. ВАРИАНТЫ ДЕЙСТВИЙ:\n";
echo "----------------------------------------\n";
echo "1. Показать детальную информацию о файлах\n";
echo "2. Удалить неиспользуемые файлы (ОСТОРОЖНО!)\n";
echo "3. Создать резервную копию перед удалением\n";
echo "4. Выход\n\n";

$choice = readline("Выберите действие (1-4): ");

switch ($choice) {
    case '1':
        showDetailedInfo($orphanedFiles);
        break;
    case '2':
        deleteOrphanedFiles($orphanedFiles);
        break;
    case '3':
        createBackupAndDelete($orphanedFiles);
        break;
    case '4':
        echo "Выход...\n";
        break;
    default:
        echo "Неверный выбор\n";
}

/**
 * Извлекает ключ S3 из URL
 */
function extractS3Key($url)
{
    if (strpos($url, 's3.twcstorage.ru') !== false) {
        $parsedUrl = parse_url($url);
        return ltrim($parsedUrl['path'], '/');
    }
    return $url;
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

/**
 * Показывает детальную информацию о файлах
 */
function showDetailedInfo($files)
{
    echo "\n=== ДЕТАЛЬНАЯ ИНФОРМАЦИЯ ===\n\n";
    
    $totalSize = 0;
    $byType = [];
    
    foreach ($files as $file) {
        try {
            $size = Storage::disk('s3')->size($file);
            $totalSize += $size;
            
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $byType[$extension] = ($byType[$extension] ?? 0) + 1;
            
        } catch (Exception $e) {
            // Игнорируем ошибки
        }
    }
    
    echo "Общий размер: " . formatBytes($totalSize) . "\n";
    echo "Распределение по типам:\n";
    
    foreach ($byType as $type => $count) {
        echo "  .$type: $count файлов\n";
    }
}

/**
 * Удаляет неиспользуемые файлы
 */
function deleteOrphanedFiles($files)
{
    echo "\n=== УДАЛЕНИЕ НЕИСПОЛЬЗУЕМЫХ ФАЙЛОВ ===\n\n";
    
    $confirm = readline("Вы уверены? Это действие необратимо! (yes/no): ");
    
    if (strtolower($confirm) !== 'yes') {
        echo "Отменено\n";
        return;
    }
    
    $deleted = 0;
    $errors = 0;
    
    foreach ($files as $file) {
        try {
            Storage::disk('s3')->delete($file);
            $deleted++;
            
            if ($deleted % 100 == 0) {
                echo "Удалено: $deleted файлов\n";
            }
            
        } catch (Exception $e) {
            $errors++;
            echo "Ошибка удаления $file: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nРезультат:\n";
    echo "Удалено файлов: $deleted\n";
    echo "Ошибок: $errors\n";
}

/**
 * Создает резервную копию и удаляет файлы
 */
function createBackupAndDelete($files)
{
    echo "\n=== РЕЗЕРВНАЯ КОПИЯ И УДАЛЕНИЕ ===\n\n";
    
    $backupDir = 'backup/orphaned-media-' . date('Y-m-d-H-i-s');
    echo "Создание резервной копии в: $backupDir\n";
    
    $copied = 0;
    $errors = 0;
    
    foreach ($files as $file) {
        try {
            $content = Storage::disk('s3')->get($file);
            $backupPath = $backupDir . '/' . $file;
            Storage::disk('s3')->put($backupPath, $content);
            
            // Удаляем оригинал
            Storage::disk('s3')->delete($file);
            
            $copied++;
            
            if ($copied % 100 == 0) {
                echo "Обработано: $copied файлов\n";
            }
            
        } catch (Exception $e) {
            $errors++;
            echo "Ошибка обработки $file: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nРезультат:\n";
    echo "Скопировано в резерв: $copied файлов\n";
    echo "Ошибок: $errors\n";
    echo "Резервная копия: $backupDir\n";
}

echo "\n=== СКРИПТ ЗАВЕРШЕН ===\n";
