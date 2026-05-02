<?php

/**
 * Тестовый скрипт для проверки API товара
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ API ТОВАРА ===\n\n";

use Marvel\Database\Models\Product;

// Получаем товар с изображениями
$product = Product::whereNotNull('image')
    ->orWhereNotNull('gallery')
    ->first();

if (!$product) {
    echo "❌ Товары с изображениями не найдены\n";
    exit;
}

echo "Товар ID: {$product->id}\n";
echo "Название: {$product->name}\n\n";

// Проверяем данные изображений
echo "1. Данные изображений в модели:\n";
echo "Image: " . json_encode($product->image) . "\n";
echo "Gallery: " . json_encode($product->gallery) . "\n\n";

// Проверяем сериализацию в массив
echo "2. Сериализация в массив:\n";
$productArray = $product->toArray();
echo "Image в массиве: " . json_encode($productArray['image'] ?? null) . "\n";
echo "Gallery в массиве: " . json_encode($productArray['gallery'] ?? null) . "\n\n";

// Проверяем типы данных
echo "3. Типы данных:\n";
echo "Image type: " . gettype($productArray['image'] ?? null) . "\n";
echo "Gallery type: " . gettype($productArray['gallery'] ?? null) . "\n\n";

// Проверяем структуру изображений
if (is_array($productArray['image'])) {
    echo "4. Структура основного изображения:\n";
    foreach ($productArray['image'] as $key => $value) {
        echo "  $key: " . (is_string($value) ? $value : json_encode($value)) . "\n";
    }
    echo "\n";
}

if (is_array($productArray['gallery'])) {
    echo "5. Структура галереи:\n";
    echo "  Количество изображений: " . count($productArray['gallery']) . "\n";
    foreach ($productArray['gallery'] as $index => $image) {
        if (is_array($image)) {
            echo "  Изображение $index:\n";
            foreach ($image as $key => $value) {
                echo "    $key: " . (is_string($value) ? $value : json_encode($value)) . "\n";
            }
        }
    }
    echo "\n";
}

// Проверяем, что данные корректны для фронтенда
echo "6. Проверка для фронтенда:\n";
$imageData = $productArray['image'];
if ($imageData && is_array($imageData)) {
    $hasOriginal = !empty($imageData['original']);
    $hasThumbnail = !empty($imageData['thumbnail']);
    $hasId = !empty($imageData['id']);
    
    echo "  Основное изображение:\n";
    echo "    Есть original: " . ($hasOriginal ? 'Да' : 'Нет') . "\n";
    echo "    Есть thumbnail: " . ($hasThumbnail ? 'Да' : 'Нет') . "\n";
    echo "    Есть ID: " . ($hasId ? 'Да' : 'Нет') . "\n";
    
    if ($hasOriginal) {
        echo "    Original URL: " . $imageData['original'] . "\n";
    }
    if ($hasThumbnail) {
        echo "    Thumbnail URL: " . $imageData['thumbnail'] . "\n";
    }
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
