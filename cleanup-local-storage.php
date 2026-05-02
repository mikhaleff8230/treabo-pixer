<?php

/**
 * Очистка локального хранилища storage/
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Place;

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ОЧИСТКА ЛОКАЛЬНОГО ХРАНИЛИЩА STORAGE/ ===\n\n";

// 1. Анализируем папки storage
echo "1. АНАЛИЗ ПАПОК STORAGE:\n";
echo "----------------------------------------\n";

$storageDirectories = [
    'storage/app/public' => 'Публичные файлы',
    'storage/app/media' => 'Медиа-файлы',
    'storage/logs' => 'Логи',
    'storage/framework/cache' => 'Кэш',
    'storage/framework/sessions' => 'Сессии',
    'storage/framework/views' => 'Шаблоны',
    'storage/app' => 'Общие файлы приложения'
];

$totalSize = 0;
$directorySizes = [];

foreach ($storageDirectories as $dir => $description) {
    $fullPath = base_path($dir);
    if (is_dir($fullPath)) {
        $size = getDirectorySize($fullPath);
        $totalSize += $size;
        $directorySizes[$dir] = $size;
        
        echo sprintf("%-30s: %s\n", $description, formatBytes($size));
    } else {
        echo sprintf("%-30s: Папка не существует\n", $description);
    }
}

echo "\nОБЩИЙ РАЗМЕР STORAGE: " . formatBytes($totalSize) . "\n\n";

// 2. Анализируем медиа-файлы в storage/app/public
echo "2. АНАЛИЗ МЕДИА-ФАЙЛОВ В STORAGE/APP/PUBLIC:\n";
echo "----------------------------------------\n";

$publicPath = base_path('storage/app/public');
if (is_dir($publicPath)) {
    $mediaFiles = [];
    $mediaSize = 0;
    
    // Сканируем рекурсивно
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($publicPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath); // Нормализуем слэши
            
            $mediaFiles[] = [
                'path' => $relativePath,
                'size' => $file->getSize(),
                'modified' => $file->getMTime()
            ];
            $mediaSize += $file->getSize();
        }
    }
    
    echo "Найдено медиа-файлов: " . count($mediaFiles) . "\n";
    echo "Размер медиа-файлов: " . formatBytes($mediaSize) . "\n\n";
    
    // Группируем по папкам
    $folders = [];
    foreach ($mediaFiles as $file) {
        $folder = dirname($file['path']);
        if ($folder === '.') $folder = 'root';
        
        if (!isset($folders[$folder])) {
            $folders[$folder] = ['count' => 0, 'size' => 0];
        }
        $folders[$folder]['count']++;
        $folders[$folder]['size'] += $file['size'];
    }
    
    echo "Распределение по папкам:\n";
    foreach ($folders as $folder => $data) {
        echo sprintf("  %-20s: %4d файлов (%s)\n", 
            $folder, 
            $data['count'], 
            formatBytes($data['size'])
        );
    }
    echo "\n";
}

// 3. Проверяем, какие файлы используются в базе данных
echo "3. ПРОВЕРКА ИСПОЛЬЗОВАНИЯ ФАЙЛОВ В БД:\n";
echo "----------------------------------------\n";

$usedFiles = [];
$unusedFiles = [];

// Получаем все используемые URL'ы из товаров
$products = Product::select('id', 'name', 'image', 'gallery', 'video')->get();
echo "Анализируем товары: " . $products->count() . "\n";

foreach ($products as $product) {
    // Основное изображение
    if (!empty($product->image)) {
        $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
        if (is_array($imageData)) {
            if (isset($imageData['original'])) {
                $localPath = extractLocalPath($imageData['original']);
                if ($localPath) {
                    $usedFiles[] = $localPath;
                }
            }
            if (isset($imageData['thumbnail'])) {
                $localPath = extractLocalPath($imageData['thumbnail']);
                if ($localPath) {
                    $usedFiles[] = $localPath;
                }
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
                        $localPath = extractLocalPath($image['original']);
                        if ($localPath) {
                            $usedFiles[] = $localPath;
                        }
                    }
                    if (isset($image['thumbnail'])) {
                        $localPath = extractLocalPath($image['thumbnail']);
                        if ($localPath) {
                            $usedFiles[] = $localPath;
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
                $usedFiles[] = $localPath;
            }
        }
    }
}

// Получаем все используемые URL'ы из мест
$places = Place::with(['images', 'videos'])->get();
echo "Анализируем места: " . $places->count() . "\n";

foreach ($places as $place) {
    foreach ($place->images as $image) {
        $localPath = extractLocalPath($image->url);
        if ($localPath) {
            $usedFiles[] = $localPath;
        }
    }
    foreach ($place->videos as $video) {
        $localPath = extractLocalPath($video->url);
        if ($localPath) {
            $usedFiles[] = $localPath;
        }
    }
}

// Проверяем MediaLibrary
$mediaFiles = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('disk', 'public')->get();
echo "Анализируем MediaLibrary: " . $mediaFiles->count() . "\n";

foreach ($mediaFiles as $media) {
    $usedFiles[] = $media->file_name;
}

$usedFiles = array_filter(array_unique($usedFiles));
echo "Используемых файлов в БД: " . count($usedFiles) . "\n";

// Показываем примеры используемых файлов
echo "\nПримеры используемых файлов:\n";
$examples = array_slice($usedFiles, 0, 10);
foreach ($examples as $file) {
    echo "  - " . $file . "\n";
}
if (count($usedFiles) > 10) {
    echo "  ... и еще " . (count($usedFiles) - 10) . " файлов\n";
}
echo "\n";

// Находим неиспользуемые файлы
if (isset($mediaFiles)) {
    foreach ($mediaFiles as $file) {
        if (!in_array($file['path'], $usedFiles)) {
            $unusedFiles[] = $file;
        }
    }
}

echo "Неиспользуемых файлов: " . count($unusedFiles) . "\n";

$unusedSize = 0;
foreach ($unusedFiles as $file) {
    $unusedSize += $file['size'];
}

echo "Размер неиспользуемых файлов: " . formatBytes($unusedSize) . "\n\n";

// 4. Очистка старых логов
echo "4. ОЧИСТКА СТАРЫХ ЛОГОВ:\n";
echo "----------------------------------------\n";

$logsPath = base_path('storage/logs');
if (is_dir($logsPath)) {
    $logFiles = glob($logsPath . '/*.log');
    $oldLogs = [];
    $oldLogsSize = 0;
    
    foreach ($logFiles as $logFile) {
        $fileTime = filemtime($logFile);
        $fileSize = filesize($logFile);
        
        // Логи старше 30 дней
        if ($fileTime < (time() - 30 * 24 * 60 * 60)) {
            $oldLogs[] = basename($logFile);
            $oldLogsSize += $fileSize;
        }
    }
    
    echo "Старых логов (старше 30 дней): " . count($oldLogs) . "\n";
    echo "Размер старых логов: " . formatBytes($oldLogsSize) . "\n";
    
    if (count($oldLogs) > 0) {
        echo "Примеры старых логов:\n";
        foreach (array_slice($oldLogs, 0, 5) as $log) {
            echo "  - " . $log . "\n";
        }
        if (count($oldLogs) > 5) {
            echo "  ... и еще " . (count($oldLogs) - 5) . " файлов\n";
        }
    }
    echo "\n";
}

// 5. Очистка кэша
echo "5. ОЧИСТКА КЭША:\n";
echo "----------------------------------------\n";

$cachePath = base_path('storage/framework/cache');
if (is_dir($cachePath)) {
    $cacheSize = getDirectorySize($cachePath);
    echo "Размер кэша: " . formatBytes($cacheSize) . "\n";
}

$sessionsPath = base_path('storage/framework/sessions');
if (is_dir($sessionsPath)) {
    $sessionsSize = getDirectorySize($sessionsPath);
    echo "Размер сессий: " . formatBytes($sessionsSize) . "\n";
}

$viewsPath = base_path('storage/framework/views');
if (is_dir($viewsPath)) {
    $viewsSize = getDirectorySize($viewsPath);
    echo "Размер шаблонов: " . formatBytes($viewsSize) . "\n";
}

echo "\n";

// 6. Предлагаем варианты действий
echo "6. ВАРИАНТЫ ДЕЙСТВИЙ:\n";
echo "----------------------------------------\n";
echo "1. Удалить неиспользуемые медиа-файлы\n";
echo "2. Очистить старые логи\n";
echo "3. Очистить кэш\n";
echo "4. Полная очистка (все выше)\n";
echo "5. Выход\n\n";

$choice = readline("Выберите действие (1-5): ");

switch ($choice) {
    case '1':
        deleteUnusedMediaFiles($unusedFiles);
        break;
    case '2':
        deleteOldLogs($logsPath);
        break;
    case '3':
        clearCache();
        break;
    case '4':
        deleteUnusedMediaFiles($unusedFiles);
        deleteOldLogs($logsPath);
        clearCache();
        break;
    case '5':
        echo "Выход...\n";
        break;
    default:
        echo "Неверный выбор\n";
}

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
        
        // Проверяем, что это локальный файл
        if (strpos($path, '..') !== false || strpos($path, '//') !== false) {
            return ''; // Небезопасный путь
        }
        
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

/**
 * Вычисляет размер директории
 */
function getDirectorySize($directory)
{
    $size = 0;
    
    if (is_dir($directory)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    
    return $size;
}

/**
 * Удаляет неиспользуемые медиа-файлы
 */
function deleteUnusedMediaFiles($files)
{
    if (empty($files)) {
        echo "Неиспользуемых медиа-файлов не найдено\n";
        return;
    }
    
    echo "\n=== УДАЛЕНИЕ НЕИСПОЛЬЗУЕМЫХ МЕДИА-ФАЙЛОВ ===\n\n";
    
    // Показываем примеры файлов для удаления
    echo "Примеры файлов для удаления:\n";
    $examples = array_slice($files, 0, 10);
    foreach ($examples as $file) {
        echo "  - " . $file['path'] . " (" . formatBytes($file['size']) . ")\n";
    }
    if (count($files) > 10) {
        echo "  ... и еще " . (count($files) - 10) . " файлов\n";
    }
    echo "\n";
    
    $confirm = readline("⚠️  ВНИМАНИЕ! Удалить " . count($files) . " неиспользуемых файлов? (yes/no): ");
    
    if (strtolower($confirm) !== 'yes') {
        echo "Отменено\n";
        return;
    }
    
    // Дополнительная проверка
    $doubleConfirm = readline("⚠️  ВЫ УВЕРЕНЫ? Это действие необратимо! (DELETE): ");
    
    if ($doubleConfirm !== 'DELETE') {
        echo "Отменено\n";
        return;
    }
    
    $deleted = 0;
    $errors = 0;
    $deletedSize = 0;
    $skipped = 0;
    
    foreach ($files as $file) {
        try {
            $filePath = $file['path'];
            
            // Дополнительная проверка безопасности
            if (strpos($filePath, '..') !== false || 
                strpos($filePath, '//') !== false ||
                strpos($filePath, '/') === 0) {
                echo "⚠️  Пропущен небезопасный путь: " . $filePath . "\n";
                $skipped++;
                continue;
            }
            
            $fullPath = base_path('storage/app/public/' . $filePath);
            
            // Проверяем, что файл находится в правильной директории
            $realPath = realpath($fullPath);
            $storagePath = realpath(base_path('storage/app/public'));
            
            if (!$realPath || strpos($realPath, $storagePath) !== 0) {
                echo "⚠️  Пропущен файл вне storage: " . $filePath . "\n";
                $skipped++;
                continue;
            }
            
            if (file_exists($fullPath) && is_file($fullPath)) {
                $fileSize = filesize($fullPath);
                if (unlink($fullPath)) {
                    $deleted++;
                    $deletedSize += $fileSize;
                    
                    if ($deleted % 50 == 0) {
                        echo "Удалено: $deleted файлов\n";
                    }
                } else {
                    $errors++;
                    echo "❌ Не удалось удалить файл: " . $filePath . "\n";
                }
            } else {
                echo "⚠️  Файл не существует или это директория: " . $filePath . "\n";
                $skipped++;
            }
        } catch (Exception $e) {
            $errors++;
            echo "❌ Ошибка удаления " . $file['path'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nРезультат:\n";
    echo "✅ Удалено файлов: $deleted\n";
    echo "⚠️  Пропущено файлов: $skipped\n";
    echo "❌ Ошибок: $errors\n";
    echo "💾 Освобождено места: " . formatBytes($deletedSize) . "\n";
}

/**
 * Удаляет старые логи
 */
function deleteOldLogs($logsPath)
{
    echo "\n=== УДАЛЕНИЕ СТАРЫХ ЛОГОВ ===\n\n";
    
    $logFiles = glob($logsPath . '/*.log');
    $oldLogs = [];
    
    foreach ($logFiles as $logFile) {
        $fileTime = filemtime($logFile);
        if ($fileTime < (time() - 30 * 24 * 60 * 60)) {
            $oldLogs[] = $logFile;
        }
    }
    
    if (empty($oldLogs)) {
        echo "Старых логов не найдено\n";
        return;
    }
    
    $confirm = readline("Удалить " . count($oldLogs) . " старых логов? (yes/no): ");
    
    if (strtolower($confirm) !== 'yes') {
        echo "Отменено\n";
        return;
    }
    
    $deleted = 0;
    foreach ($oldLogs as $logFile) {
        try {
            unlink($logFile);
            $deleted++;
        } catch (Exception $e) {
            // Игнорируем ошибки
        }
    }
    
    echo "Удалено логов: $deleted\n";
}

/**
 * Очищает кэш
 */
function clearCache()
{
    echo "\n=== ОЧИСТКА КЭША ===\n\n";
    
    try {
        \Artisan::call('cache:clear');
        echo "✅ Кэш приложения очищен\n";
        
        \Artisan::call('config:clear');
        echo "✅ Кэш конфигурации очищен\n";
        
        \Artisan::call('view:clear');
        echo "✅ Кэш шаблонов очищен\n";
        
        \Artisan::call('route:clear');
        echo "✅ Кэш маршрутов очищен\n";
        
    } catch (Exception $e) {
        echo "❌ Ошибка очистки кэша: " . $e->getMessage() . "\n";
    }
}

echo "\n=== СКРИПТ ЗАВЕРШЕН ===\n";
