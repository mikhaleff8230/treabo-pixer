<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Product;

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Массовая очистка внешних изображений у всех товаров...\n";

$externalDomains = [
    'svetlanashtefan.com',
    'example.com',
    // Добавьте другие внешние домены
];

$totalCleaned = 0;

// Получаем все товары
$products = Product::all();

echo "Обрабатываем " . $products->count() . " товаров...\n";

foreach ($products as $product) {
    $needsUpdate = false;
    $cleanedImages = 0;
    $cleanedGallery = 0;

    // Очищаем основное изображение
    if (!empty($product->image)) {
        $imageData = is_string($product->image) ? json_decode($product->image, true) : $product->image;
        if (is_array($imageData) && isset($imageData['original'])) {
            foreach ($externalDomains as $domain) {
                if (strpos($imageData['original'], $domain) !== false) {
                    $product->image = null;
                    $needsUpdate = true;
                    $cleanedImages++;
                    echo "  - Очищено основное изображение: {$imageData['original']}\n";
                    break;
                }
            }
        }
    }

    // Очищаем галерею
    if (!empty($product->gallery)) {
        $galleryData = is_string($product->gallery) ? json_decode($product->gallery, true) : $product->gallery;
        if (is_array($galleryData)) {
            $cleanedGalleryData = [];
            foreach ($galleryData as $image) {
                if (is_array($image) && isset($image['original'])) {
                    $isExternal = false;
                    foreach ($externalDomains as $domain) {
                        if (strpos($image['original'], $domain) !== false) {
                            $isExternal = true;
                            $cleanedGallery++;
                            echo "  - Очищено изображение из галереи: {$image['original']}\n";
                            break;
                        }
                    }
                    if (!$isExternal) {
                        $cleanedGalleryData[] = $image;
                    }
                }
            }
            $product->gallery = $cleanedGalleryData;
            $needsUpdate = true;
        }
    }

    if ($needsUpdate) {
        $product->save();
        $totalCleaned++;
        echo "Товар ID {$product->id} ({$product->name}): очищено {$cleanedImages} основных изображений, {$cleanedGallery} из галереи\n";
    }
}

echo "\nГотово! Очищено внешних изображений у {$totalCleaned} товаров.\n";
echo "Теперь можно запустить импорт заново с загрузкой изображений на сервер.\n";

