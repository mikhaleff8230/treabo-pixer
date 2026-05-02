<?php

/**
 * Тестовый скрипт для проверки логики очистки
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ ОЧИСТКИ STORAGE ===\n\n";

$publicPath = base_path('storage/app/public');

echo "Путь: $publicPath\n";
echo "Существует: " . (is_dir($publicPath) ? 'Да' : 'Нет') . "\n\n";

if (!is_dir($publicPath)) {
    echo "❌ Директория не существует!\n";
    exit;
}

// Получаем все файлы
$allFiles = [];
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
            'is_file' => is_file($file->getPathname()),
            'is_dir' => is_dir($file->getPathname())
        ];
    }
}

echo "Найдено файлов: " . count($allFiles) . "\n\n";

// Показываем первые 10 файлов
echo "Первые 10 файлов:\n";
echo "----------------------------------------\n";
foreach (array_slice($allFiles, 0, 10) as $file) {
    echo sprintf("%-50s %8s %s %s\n", 
        $file['path'], 
        formatBytes($file['size']),
        $file['is_file'] ? '[FILE]' : '[NOT_FILE]',
        $file['is_dir'] ? '[DIR]' : '[NOT_DIR]'
    );
}

if (count($allFiles) > 10) {
    echo "... и еще " . (count($allFiles) - 10) . " файлов\n";
}

echo "\nСтатистика:\n";
echo "----------------------------------------\n";

$fileCount = 0;
$dirCount = 0;
$totalSize = 0;

foreach ($allFiles as $file) {
    if ($file['is_file']) {
        $fileCount++;
        $totalSize += $file['size'];
    } else {
        $dirCount++;
    }
}

echo "Файлов: $fileCount\n";
echo "Директорий: $dirCount\n";
echo "Общий размер: " . formatBytes($totalSize) . "\n";

// Тестируем удаление одного файла
if (!empty($allFiles)) {
    $testFile = $allFiles[0];
    echo "\nТест удаления файла:\n";
    echo "----------------------------------------\n";
    echo "Файл: " . $testFile['path'] . "\n";
    echo "Размер: " . formatBytes($testFile['size']) . "\n";
    echo "Полный путь: " . base_path('storage/app/public/' . $testFile['path']) . "\n";
    
    $fullPath = base_path('storage/app/public/' . $testFile['path']);
    echo "Существует: " . (file_exists($fullPath) ? 'Да' : 'Нет') . "\n";
    echo "Это файл: " . (is_file($fullPath) ? 'Да' : 'Нет') . "\n";
    echo "Это директория: " . (is_dir($fullPath) ? 'Да' : 'Нет') . "\n";
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

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
