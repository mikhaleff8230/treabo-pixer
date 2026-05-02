<?php

/**
 * Скрипт для проверки групповых товаров в базе данных
 * Запуск: php artisan tinker < check_group_products.php
 * Или: php check_group_products.php (если настроен autoload)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\DB;

echo "=== ПРОВЕРКА ГРУППОВЫХ ТОВАРОВ ===\n\n";

// 1. Проверяем товары с group_key
echo "1. Товары с group_key:\n";
$groupedProducts = Product::whereNotNull('group_key')
    ->select('id', 'name', 'slug', 'group_key', 'status')
    ->orderBy('group_key')
    ->orderBy('id')
    ->get();

if ($groupedProducts->isEmpty()) {
    echo "   ❌ Нет товаров с group_key в базе данных\n\n";
} else {
    $groupKeys = $groupedProducts->pluck('group_key')->unique();
    echo "   ✅ Найдено " . $groupedProducts->count() . " товаров в " . $groupKeys->count() . " группах\n";
    
    foreach ($groupKeys as $groupKey) {
        $productsInGroup = $groupedProducts->where('group_key', $groupKey);
        echo "\n   Группа: {$groupKey} (" . $productsInGroup->count() . " товаров)\n";
        foreach ($productsInGroup as $product) {
            echo "      - ID: {$product->id}, Name: {$product->name}, Status: {$product->status}\n";
        }
    }
}

echo "\n\n";

// 2. Проверяем атрибуты для товаров с group_key
echo "2. Атрибуты товаров с group_key:\n";
$groupedProductsWithAttributes = Product::whereNotNull('group_key')
    ->with('attributes')
    ->get();

if ($groupedProductsWithAttributes->isEmpty()) {
    echo "   ❌ Нет товаров для проверки атрибутов\n\n";
} else {
    foreach ($groupedProductsWithAttributes->groupBy('group_key') as $groupKey => $products) {
        echo "\n   Группа: {$groupKey}\n";
        foreach ($products as $product) {
            $attributes = $product->attributes;
            echo "      Товар ID {$product->id} ({$product->name}):\n";
            
            if ($attributes->isEmpty()) {
                echo "         ❌ Нет атрибутов\n";
            } else {
                echo "         ✅ Атрибутов: " . $attributes->count() . "\n";
                foreach ($attributes as $attr) {
                    $value = $attr->pivot->value ?? 'NULL';
                    $attrValueId = $attr->pivot->attribute_value_id ?? 'NULL';
                    echo "            - Атрибут ID {$attr->id} ({$attr->name}): value='{$value}', attribute_value_id={$attrValueId}\n";
                }
            }
        }
    }
}

echo "\n\n";

// 3. Проверяем таблицу product_attribute_values напрямую
echo "3. Данные из таблицы product_attribute_values для товаров с group_key:\n";
$productIds = Product::whereNotNull('group_key')->pluck('id');
if ($productIds->isEmpty()) {
    echo "   ❌ Нет товаров для проверки\n\n";
} else {
    $attributeValues = DB::table('product_attribute_values')
        ->whereIn('product_id', $productIds)
        ->join('attributes', 'product_attribute_values.attribute_id', '=', 'attributes.id')
        ->select(
            'product_attribute_values.product_id',
            'product_attribute_values.attribute_id',
            'attributes.name as attribute_name',
            'product_attribute_values.value',
            'product_attribute_values.attribute_value_id'
        )
        ->orderBy('product_attribute_values.product_id')
        ->orderBy('product_attribute_values.attribute_id')
        ->get();
    
    if ($attributeValues->isEmpty()) {
        echo "   ❌ Нет записей в product_attribute_values для товаров с group_key\n\n";
    } else {
        echo "   ✅ Найдено " . $attributeValues->count() . " записей\n";
        foreach ($attributeValues->groupBy('product_id') as $productId => $attrs) {
            $product = Product::find($productId);
            echo "\n      Товар ID {$productId} ({$product->name ?? 'N/A'}, group_key: {$product->group_key ?? 'NULL'}):\n";
            foreach ($attrs as $attr) {
                echo "         - Атрибут ID {$attr->attribute_id} ({$attr->attribute_name}): value='{$attr->value}', attribute_value_id=" . ($attr->attribute_value_id ?? 'NULL') . "\n";
            }
        }
    }
}

echo "\n\n";

// 4. Проверяем конкретную группу (если есть)
if ($groupKeys->isNotEmpty()) {
    $testGroupKey = $groupKeys->first();
    echo "4. Детальная проверка группы: {$testGroupKey}\n";
    
    $testProducts = Product::where('group_key', $testGroupKey)
        ->with('attributes')
        ->orderBy('id')
        ->get();
    
    echo "   Товаров в группе: " . $testProducts->count() . "\n";
    
    // Собираем все атрибуты
    $allAttributes = [];
    foreach ($testProducts as $product) {
        foreach ($product->attributes as $attr) {
            $attrId = $attr->id;
            if (!isset($allAttributes[$attrId])) {
                $allAttributes[$attrId] = [
                    'name' => $attr->name,
                    'values' => []
                ];
            }
            $value = $attr->pivot->value ?? null;
            if ($value) {
                $allAttributes[$attrId]['values'][$product->id] = $value;
            }
        }
    }
    
    echo "\n   Атрибуты в группе:\n";
    foreach ($allAttributes as $attrId => $attrData) {
        $uniqueValues = array_unique(array_values($attrData['values']));
        echo "      - Атрибут ID {$attrId} ({$attrData['name']}):\n";
        echo "         Значения: " . implode(', ', $uniqueValues) . "\n";
        echo "         Есть у всех товаров: " . (count($attrData['values']) === $testProducts->count() ? '✅' : '❌') . "\n";
    }
    
    // Проверяем через accessor
    echo "\n   Проверка через accessor getAttributeValuesAttribute():\n";
    foreach ($testProducts as $product) {
        $attributeValues = $product->attribute_values;
        echo "      Товар ID {$product->id}:\n";
        if (empty($attributeValues)) {
            echo "         ❌ attribute_values пустой\n";
        } else {
            echo "         ✅ attribute_values: " . json_encode($attributeValues, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

echo "\n\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";

