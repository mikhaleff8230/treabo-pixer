<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Очистка товаров с внешними изображениями...\n";

// Находим товары с внешними ссылками на изображения
$products = Product::where('image', 'like', '%svetlanashtefan.com%')
    ->orWhere('gallery', 'like', '%svetlanashtefan.com%')
    ->get();

echo "Найдено товаров с внешними изображениями: " . $products->count() . "\n";

foreach ($products as $product) {
    echo "Обрабатываем товар ID: {$product->id} - {$product->name}\n";
    
    // Очищаем изображения
    $product->image = null;
    $product->gallery = null;
    $product->save();
    
    echo "  - Очищены изображения\n";
}

echo "Готово! Товары очищены от внешних изображений.\n";
echo "Теперь можно запустить импорт заново с загрузкой изображений на сервер.\n";

