<?php

/**
 * Скрипт для тестирования полного цикла групповых товаров
 * Запуск: php artisan tinker < test_group_products_flow.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== ТЕСТИРОВАНИЕ ГРУППОВЫХ ТОВАРОВ ===\n\n";

// Шаг 1: Проверка существующих групп
echo "ШАГ 1: Проверка существующих групп\n";
echo str_repeat("=", 50) . "\n";

$groupedProducts = Product::whereNotNull('group_key')
    ->select('id', 'name', 'slug', 'group_key', 'status')
    ->orderBy('group_key')
    ->orderBy('id')
    ->get();

if ($groupedProducts->isEmpty()) {
    echo "❌ Нет товаров с group_key в базе данных\n";
    echo "   Создайте группу товаров в админке и запустите тест снова\n\n";
    exit(1);
}

$groupKeys = $groupedProducts->pluck('group_key')->unique();
echo "✅ Найдено " . $groupedProducts->count() . " товаров в " . $groupKeys->count() . " группах\n\n";

// Выбираем первую группу для тестирования
$testGroupKey = $groupKeys->first();
echo "📦 Тестируем группу: {$testGroupKey}\n\n";

// Шаг 2: Проверка структуры group_key
echo "ШАГ 2: Проверка формата group_key\n";
echo str_repeat("=", 50) . "\n";

$isNumeric = is_numeric($testGroupKey);
echo "   Формат group_key: " . ($isNumeric ? "✅ Числовой" : "❌ Не числовой") . "\n";
echo "   Значение: {$testGroupKey}\n";

if (!$isNumeric) {
    echo "   ⚠️ ВНИМАНИЕ: group_key должен быть числовым!\n";
}

echo "\n";

// Шаг 3: Проверка товаров в группе
echo "ШАГ 3: Проверка товаров в группе\n";
echo str_repeat("=", 50) . "\n";

$testProducts = Product::where('group_key', $testGroupKey)
    ->orderBy('id')
    ->get();

echo "   Товаров в группе: " . $testProducts->count() . "\n";
foreach ($testProducts as $product) {
    echo "   - ID: {$product->id}, Name: {$product->name}, Status: {$product->status}\n";
}

if ($testProducts->count() < 2) {
    echo "   ⚠️ ВНИМАНИЕ: В группе должно быть минимум 2 товара!\n";
}

echo "\n";

// Шаг 4: Проверка атрибутов в БД
echo "ШАГ 4: Проверка атрибутов в таблице product_attribute_values\n";
echo str_repeat("=", 50) . "\n";

$attributeValues = DB::table('product_attribute_values')
    ->whereIn('product_id', $testProducts->pluck('id'))
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
    echo "   ❌ Нет атрибутов в product_attribute_values для товаров группы\n";
    echo "   ⚠️ ПРОБЛЕМА: Атрибуты не сохранились!\n\n";
} else {
    echo "   ✅ Найдено " . $attributeValues->count() . " записей атрибутов\n";
    foreach ($attributeValues->groupBy('product_id') as $productId => $attrs) {
        $product = $testProducts->find($productId);
        $productName = $product ? $product->name : 'N/A';
        echo "\n   Товар ID {$productId} ({$productName}):\n";
        foreach ($attrs as $attr) {
            echo "      - Атрибут ID {$attr->attribute_id} ({$attr->attribute_name}): value='{$attr->value}'\n";
        }
    }
}

echo "\n";

// Шаг 5: Проверка через модель (relation)
echo "ШАГ 5: Проверка через модель Product (relation attributes)\n";
echo str_repeat("=", 50) . "\n";

$testProductsWithAttributes = Product::where('group_key', $testGroupKey)
    ->with('attributes')
    ->orderBy('id')
    ->get();

foreach ($testProductsWithAttributes as $product) {
    echo "   Товар ID {$product->id}:\n";
    $attributes = $product->attributes;
    
    if ($attributes->isEmpty()) {
        echo "      ❌ Атрибуты не загружены через relation\n";
    } else {
        echo "      ✅ Атрибутов через relation: " . $attributes->count() . "\n";
        foreach ($attributes as $attr) {
            $value = isset($attr->pivot->value) ? $attr->pivot->value : 'NULL';
            echo "         - {$attr->id} ({$attr->name}): {$value}\n";
        }
    }
}

echo "\n";

// Шаг 6: Проверка accessor attribute_values
echo "ШАГ 6: Проверка accessor getAttributeValuesAttribute()\n";
echo str_repeat("=", 50) . "\n";

foreach ($testProductsWithAttributes as $product) {
    echo "   Товар ID {$product->id}:\n";
    
    // Убеждаемся, что атрибуты загружены
    if (!$product->relationLoaded('attributes')) {
        $product->load('attributes');
    }
    
    $attributeValues = $product->attribute_values;
    
    if (empty($attributeValues)) {
        echo "      ❌ attribute_values пустой\n";
    } else {
        echo "      ✅ attribute_values: " . json_encode($attributeValues, JSON_UNESCAPED_UNICODE) . "\n";
        echo "      Количество: " . count($attributeValues) . "\n";
    }
}

echo "\n";

// Шаг 7: Проверка общих атрибутов
echo "ШАГ 7: Проверка общих атрибутов (логика фронтенда)\n";
echo str_repeat("=", 50) . "\n";

$allAttributes = [];
foreach ($testProductsWithAttributes as $product) {
    $product->load('attributes');
    foreach ($product->attributes as $attr) {
        $attrId = $attr->id;
        if (!isset($allAttributes[$attrId])) {
            $allAttributes[$attrId] = [
                'name' => $attr->name,
                'values' => [],
                'products' => []
            ];
        }
        $value = isset($attr->pivot->value) ? $attr->pivot->value : null;
        if ($value) {
            $allAttributes[$attrId]['values'][$product->id] = $value;
            $allAttributes[$attrId]['products'][$product->id] = $product->id;
        }
    }
}

echo "   Всего уникальных атрибутов: " . count($allAttributes) . "\n\n";

$commonAttributes = [];
foreach ($allAttributes as $attrId => $attrData) {
    $hasInAll = count($attrData['products']) === $testProducts->count();
    $uniqueValues = array_unique(array_values($attrData['values']));
    $hasMultipleValues = count($uniqueValues) > 1;
    
    echo "   Атрибут ID {$attrId} ({$attrData['name']}):\n";
    echo "      Значения: " . implode(', ', $uniqueValues) . "\n";
    echo "      Есть у всех товаров: " . ($hasInAll ? "✅" : "❌") . "\n";
    echo "      Несколько значений: " . ($hasMultipleValues ? "✅" : "❌") . "\n";
    
    if ($hasInAll && $hasMultipleValues) {
        $commonAttributes[] = $attrId;
        echo "      ✅ ОБЩИЙ АТРИБУТ (будет показан на фронте)\n";
    } else {
        echo "      ❌ Не общий (не будет показан на фронте)\n";
    }
    echo "\n";
}

if (empty($commonAttributes)) {
    echo "   ⚠️ ВНИМАНИЕ: Нет общих атрибутов с разными значениями!\n";
    echo "   Компонент ProductVariations не будет отображаться на фронте.\n";
} else {
    echo "   ✅ Общих атрибутов для отображения: " . count($commonAttributes) . "\n";
    echo "   Атрибуты: " . implode(', ', $commonAttributes) . "\n";
}

echo "\n";

// Шаг 8: Симуляция API запроса
echo "ШАГ 8: Симуляция API запроса\n";
echo str_repeat("=", 50) . "\n";

$apiProducts = Product::where('group_key', $testGroupKey)
    ->where('status', 'publish')
    ->with(['shop', 'type', 'categories', 'attributes'])
    ->orderBy('id')
    ->get();

echo "   Товаров со статусом 'publish': " . $apiProducts->count() . "\n";

if ($apiProducts->isEmpty()) {
    echo "   ⚠️ ВНИМАНИЕ: Нет опубликованных товаров!\n";
    echo "   API не вернет товары, компонент на фронте не отобразится.\n";
} else {
    echo "   ✅ Товары будут возвращены API\n\n";
    
    foreach ($apiProducts as $product) {
        echo "   Товар ID {$product->id}:\n";
        echo "      group_key: {$product->group_key}\n";
        echo "      attributes loaded: " . ($product->relationLoaded('attributes') ? "✅" : "❌") . "\n";
        echo "      attributes count: " . $product->attributes->count() . "\n";
        echo "      attribute_values: " . json_encode($product->attribute_values, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n";

// Итоговый отчет
echo "=== ИТОГОВЫЙ ОТЧЕТ ===\n";
echo str_repeat("=", 50) . "\n";

$issues = [];
$success = [];

if (!$isNumeric) {
    $issues[] = "group_key не числовой";
} else {
    $success[] = "group_key числовой";
}

if ($testProducts->count() < 2) {
    $issues[] = "В группе меньше 2 товаров";
} else {
    $success[] = "В группе достаточно товаров";
}

// Проверяем атрибуты из шага 4 (это коллекция из БД)
$hasAttributesInDb = !empty($attributeValues) && (is_object($attributeValues) ? !$attributeValues->isEmpty() : count($attributeValues) > 0);
if ($hasAttributesInDb) {
    $success[] = "Атрибуты сохранены в БД";
} else {
    $issues[] = "Атрибуты не сохранены в БД";
}

if (empty($commonAttributes)) {
    $issues[] = "Нет общих атрибутов для отображения";
} else {
    $success[] = "Есть общие атрибуты для отображения";
}

if ($apiProducts->isEmpty()) {
    $issues[] = "Нет опубликованных товаров";
} else {
    $success[] = "Есть опубликованные товары";
}

echo "✅ Успешно:\n";
foreach ($success as $item) {
    echo "   - {$item}\n";
}

if (!empty($issues)) {
    echo "\n❌ Проблемы:\n";
    foreach ($issues as $item) {
        echo "   - {$item}\n";
    }
} else {
    echo "\n🎉 Все проверки пройдены! Группа товаров готова к использованию.\n";
}

echo "\n";

