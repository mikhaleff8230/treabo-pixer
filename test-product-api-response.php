<?php

/**
 * Тестовый скрипт для проверки как API отдает данные товаров
 */

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ТЕСТ API ОТВЕТА ДЛЯ ТОВАРОВ ===\n\n";

use Marvel\Database\Models\Product;

// Получаем товар с изображениями
$product = Product::whereNotNull('image')->latest()->first();

if (!$product) {
    echo "❌ Товары с изображениями не найдены\n";
    exit;
}

echo "Товар ID: {$product->id}\n";
echo "Название: {$product->name}\n";
echo "SKU: {$product->sku}\n\n";

echo "1. ДАННЫЕ ИЗ МОДЕЛИ:\n";
echo str_repeat("=", 70) . "\n";
echo "Image: " . json_encode($product->image, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "Gallery: " . json_encode($product->gallery, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "2. ДАННЫЕ ЧЕРЕЗ toArray():\n";
echo str_repeat("=", 70) . "\n";
$productArray = $product->toArray();
echo "Image: " . json_encode($productArray['image'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "Gallery: " . json_encode($productArray['gallery'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "3. СИМУЛЯЦИЯ API ОТВЕТА (как JSON):\n";
echo str_repeat("=", 70) . "\n";
$apiResponse = json_encode([
    'id' => $product->id,
    'name' => $product->name,
    'sku' => $product->sku,
    'image' => $product->image,
    'gallery' => $product->gallery,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo $apiResponse . "\n\n";

// Проверяем что после JSON decode все работает правильно
echo "4. ПОСЛЕ JSON DECODE (как получит фронтенд):\n";
echo str_repeat("=", 70) . "\n";
$decoded = json_decode($apiResponse, true);
echo "Image: " . json_encode($decoded['image'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "Gallery: " . json_encode($decoded['gallery'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Проверяем структуру image
if (is_array($decoded['image'])) {
    echo "5. СТРУКТУРА IMAGE:\n";
    echo str_repeat("=", 70) . "\n";
    echo "✅ Image is array\n";
    echo "   - original: " . ($decoded['image']['original'] ?? '❌ ОТСУТСТВУЕТ') . "\n";
    echo "   - thumbnail: " . ($decoded['image']['thumbnail'] ?? '❌ ОТСУТСТВУЕТ') . "\n";
    echo "   - id: " . ($decoded['image']['id'] ?? '❌ ОТСУТСТВУЕТ') . "\n\n";
    
    // Проверяем что поля НЕ null
    if (!empty($decoded['image']['original'])) {
        echo "✅ original URL присутствует и не пустой\n";
    } else {
        echo "❌ ПРОБЛЕМА: original пустой или null\n";
    }
    
    if (!empty($decoded['image']['thumbnail'])) {
        echo "✅ thumbnail URL присутствует и не пустой\n";
    } else {
        echo "❌ ПРОБЛЕМА: thumbnail пустой или null\n";
    }
} else {
    echo "❌ ПРОБЛЕМА: Image не является массивом!\n";
    echo "   Тип: " . gettype($decoded['image']) . "\n";
    echo "   Значение: " . json_encode($decoded['image']) . "\n";
}

echo "\n6. ПРОВЕРКА СОВМЕСТИМОСТИ С ФРОНТЕНДОМ:\n";
echo str_repeat("=", 70) . "\n";

// Проверяем формат как ожидает фронтенд
// Из product-image-slider.tsx строка 32-36:
// if (product.image) {
//   images.push(product.image);
// }

$frontendCompatible = true;
$issues = [];

if ($decoded['image']) {
    if (is_array($decoded['image'])) {
        if (!isset($decoded['image']['original']) && !isset($decoded['image']['thumbnail'])) {
            $frontendCompatible = false;
            $issues[] = "Image не содержит ни 'original', ни 'thumbnail'";
        }
    } else {
        $frontendCompatible = false;
        $issues[] = "Image не является объектом/массивом";
    }
}

if ($decoded['gallery']) {
    if (is_array($decoded['gallery'])) {
        foreach ($decoded['gallery'] as $idx => $img) {
            if (!is_array($img)) {
                $frontendCompatible = false;
                $issues[] = "Gallery[{$idx}] не является объектом";
            } elseif (!isset($img['original']) && !isset($img['thumbnail'])) {
                $frontendCompatible = false;
                $issues[] = "Gallery[{$idx}] не содержит ни 'original', ни 'thumbnail'";
            }
        }
    }
}

if ($frontendCompatible) {
    echo "✅ ФОРМАТ ДАННЫХ СОВМЕСТИМ С ФРОНТЕНДОМ\n";
} else {
    echo "❌ ПРОБЛЕМЫ С ФОРМАТОМ ДАННЫХ:\n";
    foreach ($issues as $issue) {
        echo "   - {$issue}\n";
    }
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
