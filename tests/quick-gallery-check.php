#!/usr/bin/env php
<?php

/**
 * Быстрая проверка галереи
 * Использование: php tests/quick-gallery-check.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;

echo "=== БЫСТРАЯ ПРОВЕРКА ГАЛЕРЕИ ===\n\n";

// 1. Статистика
$totalProducts = Product::count();
$productsWithGallery = Product::whereNotNull('gallery')
    ->where('gallery', '!=', '[]')
    ->where('gallery', '!=', 'null')
    ->count();
$productsWithoutGallery = $totalProducts - $productsWithGallery;

echo "Статистика:\n";
echo "  Всего товаров: {$totalProducts}\n";
echo "  С галереей: {$productsWithGallery}\n";
echo "  Без галереи: {$productsWithoutGallery}\n\n";

// 2. Проверка последних 5 товаров
echo "Последние 5 товаров:\n";
$recentProducts = Product::latest()->take(5)->get();
foreach ($recentProducts as $product) {
    $gallery = $product->gallery;
    $galleryCount = is_array($gallery) ? count($gallery) : 0;
    $status = $galleryCount > 0 ? '✓' : '✗';
    echo "  {$status} #{$product->id} - {$product->name}: {$galleryCount} фото\n";
}

echo "\n";

// 3. Проверка структуры БД
echo "Проверка структуры БД:\n";
$sampleProduct = Product::whereNotNull('gallery')
    ->where('gallery', '!=', '[]')
    ->where('gallery', '!=', 'null')
    ->first();

if ($sampleProduct) {
    $gallery = $sampleProduct->gallery;
    echo "  Товар #{$sampleProduct->id}:\n";
    echo "    Тип данных: " . gettype($gallery) . "\n";
    
    if (is_array($gallery)) {
        echo "    Количество фото: " . count($gallery) . "\n";
        if (count($gallery) > 0) {
            $firstItem = $gallery[0];
            echo "    Первый элемент:\n";
            echo "      - id: " . ($firstItem['id'] ?? 'нет') . "\n";
            echo "      - thumbnail: " . (isset($firstItem['thumbnail']) ? 'есть' : 'нет') . "\n";
            echo "      - original: " . (isset($firstItem['original']) ? 'есть' : 'нет') . "\n";
        }
    }
    
    // Проверяем что в БД
    $dbProduct = DB::table('products')->where('id', $sampleProduct->id)->first();
    $dbGallery = json_decode($dbProduct->gallery ?? '[]', true);
    echo "    В БД (JSON): " . (is_array($dbGallery) ? count($dbGallery) . " фото" : "не массив") . "\n";
} else {
    echo "  ✗ Не найден товар с галереей для проверки\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";

