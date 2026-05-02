<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Category;

// Подключаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Тест иерархии категорий ===\n\n";

// Получаем все категории с их иерархией
$categories = Category::where('language', 'ru')
    ->with(['children' => function($query) {
        $query->with('children');
    }])
    ->whereNull('parent')
    ->get();

echo "Родительские категории:\n";
foreach ($categories as $category) {
    echo "- {$category->name} (slug: {$category->slug})\n";
    
    if ($category->children->count() > 0) {
        echo "  Дочерние категории:\n";
        foreach ($category->children as $child) {
            echo "    - {$child->name} (slug: {$child->slug})\n";
            
            if ($child->children->count() > 0) {
                echo "      Внуки:\n";
                foreach ($child->children as $grandchild) {
                    echo "        - {$grandchild->name} (slug: {$grandchild->slug})\n";
                }
            }
        }
    }
    echo "\n";
}

// Тестируем функцию получения всех дочерних категорий
function getAllChildCategorySlugs($parentId, $language) {
    $childSlugs = [];
    
    $children = Category::where('parent', $parentId)
        ->where('language', $language)
        ->get(['id', 'slug', 'name']);

    foreach ($children as $child) {
        if (!empty($child->slug)) {
            $childSlugs[] = $child->slug;
            $grandChildren = getAllChildCategorySlugs($child->id, $language);
            $childSlugs = array_merge($childSlugs, $grandChildren);
        }
    }

    return $childSlugs;
}

echo "=== Тест функции получения дочерних категорий ===\n\n";

foreach ($categories as $category) {
    $childSlugs = getAllChildCategorySlugs($category->id, 'ru');
    echo "Категория: {$category->name} (slug: {$category->slug})\n";
    echo "Все дочерние slug'ы: " . implode(', ', $childSlugs) . "\n\n";
}

echo "=== Тест API запроса ===\n\n";

// Симулируем API запрос
$testCategories = ['electronics', 'clothing', 'books'];

foreach ($testCategories as $testCategory) {
    echo "Тестируем категорию: {$testCategory}\n";
    
    $category = Category::where('slug', $testCategory)
        ->where('language', 'ru')
        ->first();
    
    if ($category) {
        $childSlugs = getAllChildCategorySlugs($category->id, 'ru');
        $allSlugs = array_unique(array_merge([$testCategory], $childSlugs));
        echo "Все slug'ы для поиска товаров: " . implode(', ', $allSlugs) . "\n";
        
        // Проверяем количество товаров
        $productsCount = DB::table('products')
            ->join('category_product', 'products.id', '=', 'category_product.product_id')
            ->join('categories', 'category_product.category_id', '=', 'categories.id')
            ->whereIn('categories.slug', $allSlugs)
            ->where('products.language', 'ru')
            ->where('products.status', 'publish')
            ->distinct('products.id')
            ->count();
            
        echo "Найдено товаров: {$productsCount}\n";
    } else {
        echo "Категория не найдена!\n";
    }
    echo "\n";
}

echo "=== Готово ===\n";