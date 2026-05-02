<?php

/**
 * Тестовый скрипт для проверки работы галереи товаров
 * Запуск: php artisan tinker < tests/GalleryTestScript.php
 * Или: php -r "require 'vendor/autoload.php'; require 'tests/GalleryTestScript.php';"
 */

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Product;
use Illuminate\Http\Request;

class GalleryTestScript
{
    public function run()
    {
        echo "\n=== ТЕСТ ГАЛЕРЕИ ТОВАРОВ ===\n\n";
        
        // 1. Проверка существующих товаров в БД
        $this->testExistingProducts();
        
        // 2. Тест создания товара с галереей
        $this->testCreateProductWithGallery();
        
        // 3. Тест обновления галереи
        $this->testUpdateGallery();
        
        // 4. Тест получения товара с галереей
        $this->testGetProductWithGallery();
        
        // 5. Проверка структуры данных
        $this->testDataStructure();
        
        echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";
    }
    
    /**
     * Проверка существующих товаров в БД
     */
    private function testExistingProducts()
    {
        echo "1. Проверка существующих товаров в БД...\n";
        
        $products = Product::take(10)->get();
        $productsWithGallery = 0;
        $productsWithoutGallery = 0;
        $totalGalleryItems = 0;
        
        foreach ($products as $product) {
            $gallery = $product->gallery;
            
            if (is_array($gallery) && count($gallery) > 0) {
                $productsWithGallery++;
                $totalGalleryItems += count($gallery);
                echo "  ✓ Товар #{$product->id} ({$product->name}): галерея содержит " . count($gallery) . " фото\n";
                
                // Проверяем структуру каждого элемента галереи
                foreach ($gallery as $index => $item) {
                    if (!is_array($item)) {
                        echo "    ⚠ Элемент #{$index} не является массивом!\n";
                    } else {
                        $hasThumbnail = isset($item['thumbnail']) && !empty($item['thumbnail']);
                        $hasOriginal = isset($item['original']) && !empty($item['original']);
                        $hasId = isset($item['id']) && !empty($item['id']);
                        
                        if (!$hasThumbnail && !$hasOriginal) {
                            echo "    ⚠ Элемент #{$index} не имеет thumbnail или original!\n";
                        }
                    }
                }
            } else {
                $productsWithoutGallery++;
                echo "  ✗ Товар #{$product->id} ({$product->name}): галерея пуста или отсутствует\n";
            }
        }
        
        echo "\n  Статистика:\n";
        echo "  - Товаров с галереей: {$productsWithGallery}\n";
        echo "  - Товаров без галереи: {$productsWithoutGallery}\n";
        echo "  - Всего фото в галереях: {$totalGalleryItems}\n\n";
    }
    
    /**
     * Тест создания товара с галереей
     */
    private function testCreateProductWithGallery()
    {
        echo "2. Тест создания товара с галереей...\n";
        
        $testGallery = [
            [
                'id' => 'test-1',
                'thumbnail' => 'https://example.com/thumb1.jpg',
                'original' => 'https://example.com/orig1.jpg',
            ],
            [
                'id' => 'test-2',
                'thumbnail' => 'https://example.com/thumb2.jpg',
                'original' => 'https://example.com/orig2.jpg',
            ],
        ];
        
        try {
            // Находим первый тип товара
            $type = DB::table('types')->first();
            if (!$type) {
                echo "  ✗ Не найден тип товара для теста\n\n";
                return;
            }
            
            // Находим первый магазин
            $shop = DB::table('shops')->first();
            if (!$shop) {
                echo "  ✗ Не найден магазин для теста\n\n";
                return;
            }
            
            $product = Product::create([
                'name' => 'ТЕСТОВЫЙ ТОВАР ДЛЯ ГАЛЕРЕИ ' . time(),
                'slug' => 'test-gallery-' . time(),
                'type_id' => $type->id,
                'shop_id' => $shop->id,
                'price' => 100,
                'product_type' => 'simple',
                'status' => 'draft',
                'gallery' => $testGallery,
            ]);
            
            $product->refresh();
            $savedGallery = $product->gallery;
            
            if (is_array($savedGallery) && count($savedGallery) === 2) {
                echo "  ✓ Товар создан с галереей из 2 фото\n";
                echo "  ✓ Галерея сохранена в БД\n";
                
                // Проверяем что данные совпадают
                if ($savedGallery[0]['id'] === 'test-1' && $savedGallery[1]['id'] === 'test-2') {
                    echo "  ✓ Данные галереи совпадают\n";
                } else {
                    echo "  ✗ Данные галереи не совпадают!\n";
                }
                
                // Удаляем тестовый товар
                $product->delete();
                echo "  ✓ Тестовый товар удален\n";
            } else {
                echo "  ✗ Галерея не сохранилась! Ожидалось 2 фото, получено: " . (is_array($savedGallery) ? count($savedGallery) : 'не массив') . "\n";
                if ($product) {
                    $product->delete();
                }
            }
        } catch (\Exception $e) {
            echo "  ✗ Ошибка при создании товара: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Тест обновления галереи
     */
    private function testUpdateGallery()
    {
        echo "3. Тест обновления галереи...\n";
        
        // Находим товар для теста
        $product = Product::whereNotNull('gallery')->first();
        
        if (!$product) {
            echo "  ⚠ Не найден товар с галереей для теста\n\n";
            return;
        }
        
        $originalGallery = $product->gallery;
        $originalCount = is_array($originalGallery) ? count($originalGallery) : 0;
        
        echo "  Товар #{$product->id}: исходная галерея содержит {$originalCount} фото\n";
        
        // Добавляем новое фото в галерею
        $newGallery = is_array($originalGallery) ? $originalGallery : [];
        $newGallery[] = [
            'id' => 'test-update-' . time(),
            'thumbnail' => 'https://example.com/thumb-update.jpg',
            'original' => 'https://example.com/orig-update.jpg',
        ];
        
        try {
            $product->gallery = $newGallery;
            $product->save();
            $product->refresh();
            
            $updatedGallery = $product->gallery;
            $updatedCount = is_array($updatedGallery) ? count($updatedGallery) : 0;
            
            if ($updatedCount === $originalCount + 1) {
                echo "  ✓ Галерея обновлена: добавлено 1 фото (теперь {$updatedCount})\n";
                
                // Восстанавливаем исходную галерею
                $product->gallery = $originalGallery;
                $product->save();
                echo "  ✓ Исходная галерея восстановлена\n";
            } else {
                echo "  ✗ Галерея не обновилась! Ожидалось {$originalCount} + 1 = " . ($originalCount + 1) . ", получено {$updatedCount}\n";
                
                // Восстанавливаем исходную галерею
                $product->gallery = $originalGallery;
                $product->save();
            }
        } catch (\Exception $e) {
            echo "  ✗ Ошибка при обновлении галереи: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Тест получения товара с галереей
     */
    private function testGetProductWithGallery()
    {
        echo "4. Тест получения товара с галереей...\n";
        
        $product = Product::whereNotNull('gallery')->first();
        
        if (!$product) {
            echo "  ⚠ Не найден товар с галереей для теста\n\n";
            return;
        }
        
        // Проверяем прямое получение из БД
        $dbProduct = DB::table('products')->where('id', $product->id)->first();
        $dbGallery = json_decode($dbProduct->gallery ?? '[]', true);
        
        echo "  Товар #{$product->id}:\n";
        echo "  - Галерея из модели: " . (is_array($product->gallery) ? count($product->gallery) . " фото" : "не массив") . "\n";
        echo "  - Галерея из БД (JSON): " . (is_array($dbGallery) ? count($dbGallery) . " фото" : "не массив") . "\n";
        
        if (is_array($product->gallery) && is_array($dbGallery) && count($product->gallery) === count($dbGallery)) {
            echo "  ✓ Данные совпадают\n";
        } else {
            echo "  ✗ Данные не совпадают!\n";
            echo "    Модель: " . json_encode($product->gallery) . "\n";
            echo "    БД: " . json_encode($dbGallery) . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Проверка структуры данных
     */
    private function testDataStructure()
    {
        echo "5. Проверка структуры данных галереи...\n";
        
        $products = Product::whereNotNull('gallery')->take(5)->get();
        
        $validStructures = 0;
        $invalidStructures = 0;
        
        foreach ($products as $product) {
            $gallery = $product->gallery;
            
            if (!is_array($gallery)) {
                $invalidStructures++;
                echo "  ✗ Товар #{$product->id}: gallery не является массивом (тип: " . gettype($gallery) . ")\n";
                continue;
            }
            
            $isValid = true;
            foreach ($gallery as $index => $item) {
                if (!is_array($item)) {
                    $isValid = false;
                    echo "  ✗ Товар #{$product->id}, элемент #{$index}: не является массивом\n";
                    continue;
                }
                
                // Проверяем наличие хотя бы одного из полей
                $hasThumbnail = isset($item['thumbnail']) && !empty($item['thumbnail']);
                $hasOriginal = isset($item['original']) && !empty($item['original']);
                $hasUrl = isset($item['url']) && !empty($item['url']);
                
                if (!$hasThumbnail && !$hasOriginal && !$hasUrl) {
                    $isValid = false;
                    echo "  ✗ Товар #{$product->id}, элемент #{$index}: нет thumbnail, original или url\n";
                }
            }
            
            if ($isValid) {
                $validStructures++;
            } else {
                $invalidStructures++;
            }
        }
        
        echo "\n  Статистика структуры:\n";
        echo "  - Валидных структур: {$validStructures}\n";
        echo "  - Невалидных структур: {$invalidStructures}\n\n";
    }
}

// Запуск скрипта
if (php_sapi_name() === 'cli') {
    $script = new GalleryTestScript();
    $script->run();
}

