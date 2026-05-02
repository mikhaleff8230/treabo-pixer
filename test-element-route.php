<?php

/**
 * Скрипт для тестирования /element/{slugId} роута
 * Запуск: php test-element-route.php
 */

// Определяем правильный путь в зависимости от того, откуда запускается скрипт
$baseDir = __DIR__;
if (basename($baseDir) === 'pixer-api') {
    // Запускается из pixer-api/
    require $baseDir . '/vendor/autoload.php';
    $app = require_once $baseDir . '/bootstrap/app.php';
} else {
    // Запускается из корня
    require $baseDir . '/pixer-api/vendor/autoload.php';
    $app = require_once $baseDir . '/pixer-api/bootstrap/app.php';
}
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Marvel\Database\Models\Product;

echo "=== ТЕСТ РОУТА /element/{slugId} ===\n\n";

// 1. Проверяем parseSlugId
$testCases = [
    'kartina-v-loft-abstraktnaya-kaliforniya-1-2-140h100-sm-5860',
    'kartina-dlya-interera-pesochna-zheltaya-abstraktciya',
    'product-123',
    'simple-slug',
];

echo "1. Тест парсинга slug и ID:\n";
foreach ($testCases as $slugId) {
    $parsed = Product::parseSlugId($slugId);
    echo "   Input: {$slugId}\n";
    echo "   Parsed: slug='{$parsed['slug']}', id='{$parsed['id']}'\n\n";
}

// 2. Проверяем товары в БД
echo "\n2. Проверка товаров в БД:\n";

// Находим несколько товаров
$products = Product::where('language', 'ru')
    ->take(5)
    ->get(['id', 'name', 'slug', 'language']);

foreach ($products as $product) {
    echo "   ID: {$product->id}\n";
    echo "   Name: {$product->name}\n";
    echo "   Slug: {$product->slug}\n";
    echo "   Language: {$product->language}\n";
    echo "   URL должен быть: /element/{$product->slug}-{$product->id}\n\n";
}

// 3. Проверяем конкретный товар из ошибки
echo "\n3. Проверка конкретного товара из ошибки:\n";
$slugId = 'kartina-v-loft-abstraktnaya-kaliforniya-1-2-140h100-sm-5860';
$parsed = Product::parseSlugId($slugId);

echo "   Ищем по ID: {$parsed['id']}\n";
$productById = Product::where('id', $parsed['id'])
    ->where('language', 'ru')
    ->first();

if ($productById) {
    echo "   ✅ Товар найден по ID!\n";
    echo "   ID: {$productById->id}\n";
    echo "   Name: {$productById->name}\n";
    echo "   Slug: {$productById->slug}\n";
    echo "   Expected slug: {$parsed['slug']}\n";
    echo "   Slugs match: " . ($productById->slug === $parsed['slug'] ? 'YES' : 'NO') . "\n";
} else {
    echo "   ❌ Товар НЕ найден по ID {$parsed['id']} с language=ru\n";
    
    // Проверяем есть ли товар с таким ID вообще
    $anyProduct = Product::where('id', $parsed['id'])->first();
    if ($anyProduct) {
        echo "   ⚠️  Товар найден с language={$anyProduct->language}\n";
    } else {
        echo "   ❌ Товара с ID {$parsed['id']} вообще нет в БД\n";
    }
}

// Пробуем найти по slug
echo "\n   Ищем по slug: {$parsed['slug']}\n";
$productBySlug = Product::where('slug', $parsed['slug'])
    ->where('language', 'ru')
    ->first();

if ($productBySlug) {
    echo "   ✅ Товар найден по slug!\n";
    echo "   ID: {$productBySlug->id}\n";
    echo "   Name: {$productBySlug->name}\n";
} else {
    echo "   ❌ Товар НЕ найден по slug\n";
    
    // Пробуем найти похожий slug
    $similarProducts = Product::where('slug', 'like', substr($parsed['slug'], 0, 20) . '%')
        ->where('language', 'ru')
        ->take(3)
        ->get(['id', 'slug']);
    
    if ($similarProducts->count() > 0) {
        echo "   Похожие товары:\n";
        foreach ($similarProducts as $p) {
            echo "      ID: {$p->id}, slug: {$p->slug}\n";
        }
    }
}

echo "\n=== КОНЕЦ ТЕСТА ===\n";

