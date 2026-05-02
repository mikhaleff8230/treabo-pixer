<?php

/**
 * Простой и рабочий скрипт очистки storage
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ПРОСТАЯ ОЧИСТКА STORAGE ===\n\n";

$publicPath = base_path('storage/app/public');

echo "Путь: $publicPath\n";
echo "Существует: " . (is_dir($publicPath) ? 'Да' : 'Нет') . "\n\n";

if (!is_dir($publicPath)) {
    echo "❌ Директория не существует!\n";
    exit;
}

// Получаем все файлы простым способом
$allFiles = [];
$totalSize = 0;

function scanDirectory($dir, $baseDir) {
    global $allFiles, $totalSize;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $dir . '/' . $item;
        $relativePath = str_replace($baseDir . '/', '', $fullPath);
        
        if (is_file($fullPath)) {
            $size = filesize($fullPath);
            $allFiles[] = [
                'path' => $relativePath,
                'size' => $size,
                'modified' => filemtime($fullPath),
                'full_path' => $fullPath
            ];
            $totalSize += $size;
        } elseif (is_dir($fullPath)) {
            scanDirectory($fullPath, $baseDir);
        }
    }
}

scanDirectory($publicPath, $publicPath);

echo "Найдено файлов: " . count($allFiles) . "\n";
echo "Общий размер: " . formatBytes($totalSize) . "\n\n";

if (empty($allFiles)) {
    echo "✅ Файлов для удаления не найдено\n";
    exit;
}

// Показываем первые 10 файлов
echo "Первые 10 файлов:\n";
echo "----------------------------------------\n";
foreach (array_slice($allFiles, 0, 10) as $file) {
    echo sprintf("%-50s %8s\n", 
        $file['path'], 
        formatBytes($file['size'])
    );
}

if (count($allFiles) > 10) {
    echo "... и еще " . (count($allFiles) - 10) . " файлов\n";
}

echo "\n";

// Спрашиваем подтверждение
$confirm = readline("Удалить " . count($allFiles) . " файлов? (yes/no): ");

if (strtolower($confirm) !== 'yes') {
    echo "Отменено\n";
    exit;
}

// Удаляем файлы
$deleted = 0;
$errors = 0;
$deletedSize = 0;

echo "\nУдаление файлов...\n";

foreach ($allFiles as $file) {
    try {
        if (file_exists($file['full_path']) && is_file($file['full_path'])) {
            if (unlink($file['full_path'])) {
                $deleted++;
                $deletedSize += $file['size'];
                
                if ($deleted % 100 == 0) {
                    echo "Удалено: $deleted файлов\n";
                }
            } else {
                $errors++;
                echo "❌ Не удалось удалить: " . $file['path'] . "\n";
            }
        } else {
            echo "⚠️  Файл не существует: " . $file['path'] . "\n";
            $errors++;
        }
    } catch (Exception $e) {
        $errors++;
        echo "❌ Ошибка: " . $file['path'] . " - " . $e->getMessage() . "\n";
    }
}

echo "\nРезультат:\n";
echo "✅ Удалено файлов: $deleted\n";
echo "❌ Ошибок: $errors\n";
echo "💾 Освобождено места: " . formatBytes($deletedSize) . "\n";

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

echo "\n=== ОЧИСТКА ЗАВЕРШЕНА ===\n";
