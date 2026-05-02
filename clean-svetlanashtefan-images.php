<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Очистка товаров с изображениями с домена svetlanashtefan.com...\n";

// Находим товары с внешними ссылками на изображения
$products = Product::where('image', 'like', '%svetlanashtefan.com%')
    ->orWhere('gallery', 'like', '%svetlanashtefan.com%')
    ->get();

echo "Найдено товаров с внешними изображениями: " . $products->count() . "\n";

if ($products->count() > 0) {
    echo "\nСписок товаров для очистки:\n";
    foreach ($products as $product) {
        echo "- ID: {$product->id}, Название: {$product->name}\n";
        if (!empty($product->image)) {
            $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
            if (is_array($imageData) && isset($imageData['original'])) {
                echo "  Основное изображение: {$imageData['original']}\n";
            }
        }
        if (!empty($product->gallery)) {
            $galleryData = is_string($product->gallery) ? json_decode($product->gallery, true) : $product->gallery;
            if (is_array($galleryData)) {
                echo "  Галерея (" . count($galleryData) . " изображений):\n";
                foreach ($galleryData as $img) {
                    if (is_array($img) && isset($img['original'])) {
                        echo "    - {$img['original']}\n";
                    }
                }
            }
        }
        echo "\n";
    }
    
    echo "Хотите продолжить очистку? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) === 'y' || trim($line) === 'Y') {
        $cleanedCount = 0;
        
        foreach ($products as $product) {
            echo "Очищаем товар ID: {$product->id} - {$product->name}\n";
            
            // Очищаем изображения
            $product->image = null;
            $product->gallery = null;
            $product->save();
            
            $cleanedCount++;
            echo "  ✓ Очищены изображения\n";
        }
        
        echo "\nГотово! Очищено изображений у {$cleanedCount} товаров.\n";
        echo "Теперь можно запустить импорт заново с загрузкой изображений на сервер.\n";
    } else {
        echo "Очистка отменена.\n";
    }
} else {
    echo "Товары с внешними изображениями не найдены.\n";
}
