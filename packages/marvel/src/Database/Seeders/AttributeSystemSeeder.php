<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Type;

class AttributeSystemSeeder extends Seeder
{
    /**
     * Safe output helper method
     */
    private function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        } else {
            echo $message . "\n";
        }
    }

    /**
     * Safe error output helper method
     */
    private function error(string $message): void
    {
        if ($this->command) {
            $this->command->error($message);
        } else {
            echo "ERROR: " . $message . "\n";
        }
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем первый магазин или создаем тестовый
        $shop = Shop::first();
        if (!$shop) {
            $shop = Shop::create([
                'name' => 'Тестовый магазин',
                'slug' => 'test-shop',
                'owner_id' => 1,
                'is_active' => true,
            ]);
        }
        
        // Получаем ID магазина
        $shopId = $shop->id;
        if (!$shopId) {
            throw new \Exception('Не удалось получить ID магазина');
        }

        // Получаем первый тип товара или создаем тестовый
        $productType = Type::first();
        if (!$productType) {
            $productType = Type::create([
                'name' => 'Товары',
                'slug' => 'products',
            ]);
        }
        
        // Получаем ID типа товара
        $productTypeId = $productType->id;
        if (!$productTypeId) {
            throw new \Exception('Не удалось получить ID типа товара');
        }

        // Создаем категории
        $clothesCategory = Category::create([
            'name' => 'Одежда',
            'slug' => 'clothes',
            'language' => 'ru',
        ]);

        $phonesCategory = Category::create([
            'name' => 'Смартфоны',
            'slug' => 'phones',
            'language' => 'ru',
        ]);

        $shoesCategory = Category::create([
            'name' => 'Обувь',
            'slug' => 'shoes',
            'language' => 'ru',
        ]);
        
        // Получаем ID категорий
        $clothesCategoryId = $clothesCategory->id;
        $phonesCategoryId = $phonesCategory->id;
        $shoesCategoryId = $shoesCategory->id;
        
        if (!$clothesCategoryId || !$phonesCategoryId || !$shoesCategoryId) {
            throw new \Exception('Не удалось получить ID категорий');
        }

        // Создаем атрибуты
        $colorAttribute = Attribute::create([
            'name' => 'Цвет',
            'slug' => 'color',
            'shop_id' => $shopId,
            'type' => 'select',
            'input_type' => 'select',
            'is_required' => true,
            'description' => 'Основной цвет товара',
            'sort_order' => 1,
            'language' => 'ru',
        ]);

        $sizeAttribute = Attribute::create([
            'name' => 'Размер',
            'slug' => 'size',
            'shop_id' => $shopId,
            'type' => 'select',
            'input_type' => 'select',
            'is_required' => true,
            'description' => 'Размер товара',
            'sort_order' => 2,
            'language' => 'ru',
        ]);

        $memoryAttribute = Attribute::create([
            'name' => 'Объем памяти',
            'slug' => 'memory',
            'shop_id' => $shopId,
            'type' => 'number',
            'input_type' => 'select',
            'is_required' => true,
            'description' => 'Объем внутренней памяти в ГБ',
            'unit' => 'ГБ',
            'min_value' => 32,
            'max_value' => 1024,
            'sort_order' => 1,
            'language' => 'ru',
        ]);

        $materialAttribute = Attribute::create([
            'name' => 'Материал',
            'slug' => 'material',
            'shop_id' => $shopId,
            'type' => 'text',
            'input_type' => 'text',
            'is_required' => false,
            'description' => 'Основной материал изготовления',
            'sort_order' => 3,
            'language' => 'ru',
        ]);

        $brandAttribute = Attribute::create([
            'name' => 'Бренд',
            'slug' => 'brand',
            'shop_id' => $shopId,
            'type' => 'text',
            'input_type' => 'text',
            'is_required' => true,
            'description' => 'Производитель товара',
            'sort_order' => 0,
            'language' => 'ru',
        ]);

        // Создаем значения для атрибутов
        $colorValues = [
            ['value' => 'Красный', 'meta' => '#FF0000'],
            ['value' => 'Синий', 'meta' => '#0000FF'],
            ['value' => 'Зеленый', 'meta' => '#00FF00'],
            ['value' => 'Черный', 'meta' => '#000000'],
            ['value' => 'Белый', 'meta' => '#FFFFFF'],
        ];

        foreach ($colorValues as $colorValue) {
            AttributeValue::create([
                'attribute_id' => $colorAttribute->id,
                'value' => $colorValue['value'],
                'meta' => $colorValue['meta'],
                'language' => 'ru',
            ]);
        }

        $sizeValues = [
            ['value' => 'XS'],
            ['value' => 'S'],
            ['value' => 'M'],
            ['value' => 'L'],
            ['value' => 'XL'],
            ['value' => 'XXL'],
        ];

        foreach ($sizeValues as $sizeValue) {
            AttributeValue::create([
                'attribute_id' => $sizeAttribute->id,
                'value' => $sizeValue['value'],
                'language' => 'ru',
            ]);
        }

        $memoryValues = [
            ['value' => '64'],
            ['value' => '128'],
            ['value' => '256'],
            ['value' => '512'],
            ['value' => '1024'],
        ];

        foreach ($memoryValues as $memoryValue) {
            AttributeValue::create([
                'attribute_id' => $memoryAttribute->id,
                'value' => $memoryValue['value'],
                'language' => 'ru',
            ]);
        }

        // Привязываем атрибуты к категориям
        $clothesCategory->attributes()->attach([
            $colorAttribute->id => ['is_required' => true, 'sort_order' => 1],
            $sizeAttribute->id => ['is_required' => true, 'sort_order' => 2],
            $materialAttribute->id => ['is_required' => false, 'sort_order' => 3],
            $brandAttribute->id => ['is_required' => true, 'sort_order' => 0],
        ]);

        $phonesCategory->attributes()->attach([
            $colorAttribute->id => ['is_required' => true, 'sort_order' => 1],
            $memoryAttribute->id => ['is_required' => true, 'sort_order' => 2],
            $brandAttribute->id => ['is_required' => true, 'sort_order' => 0],
        ]);

        $shoesCategory->attributes()->attach([
            $colorAttribute->id => ['is_required' => true, 'sort_order' => 1],
            $sizeAttribute->id => ['is_required' => true, 'sort_order' => 2],
            $materialAttribute->id => ['is_required' => false, 'sort_order' => 3],
            $brandAttribute->id => ['is_required' => true, 'sort_order' => 0],
        ]);

        // Создаем тестовые товары
        $this->info('Создание товаров...');
        
        try {
            $tshirt = Product::create([
                'name' => 'Футболка Adidas',
                'slug' => 'adidas-tshirt',
                'description' => 'Классическая футболка Adidas',
                'price' => 2500,
                'shop_id' => $shopId,
                'type_id' => $productTypeId,
                'status' => 'publish',
                'unit' => 'шт',
                'language' => 'ru',
            ]);
            
            if (!isset($tshirt->id) || !$tshirt->id) {
                $this->error("Товар 1 создан, но не получил ID. Попытка повторного сохранения...");
                $tshirt->save();
            }
            
            if (!isset($tshirt->id) || !$tshirt->id) {
                throw new \Exception("Товар 1 не получил ID после создания. Возможна проблема с моделью или БД.");
            }
            
            $this->info("Товар 1 создан, ID: " . $tshirt->id);
        } catch (\Exception $e) {
            $this->error("Ошибка создания товара 1: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            throw $e;
        }

        try {
            $iphone = Product::create([
                'name' => 'iPhone 15',
                'slug' => 'iphone-15',
                'description' => 'Новый iPhone 15',
                'price' => 120000,
                'shop_id' => $shopId,
                'type_id' => $productTypeId,
                'status' => 'publish',
                'unit' => 'шт',
                'language' => 'ru',
            ]);
            
            if (!isset($iphone->id) || !$iphone->id) {
                $this->error("Товар 2 создан, но не получил ID. Попытка повторного сохранения...");
                $iphone->save();
            }
            
            if (!isset($iphone->id) || !$iphone->id) {
                throw new \Exception("Товар 2 не получил ID после создания. Возможна проблема с моделью или БД.");
            }
            
            $this->info("Товар 2 создан, ID: " . $iphone->id);
        } catch (\Exception $e) {
            $this->error("Ошибка создания товара 2: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            throw $e;
        }

        try {
            $sneakers = Product::create([
                'name' => 'Кроссовки Nike Air Max',
                'slug' => 'nike-air-max',
                'description' => 'Классические кроссовки Nike',
                'price' => 15000,
                'shop_id' => $shopId,
                'type_id' => $productTypeId,
                'status' => 'publish',
                'unit' => 'шт',
                'language' => 'ru',
            ]);
            
            if (!isset($sneakers->id) || !$sneakers->id) {
                $this->error("Товар 3 создан, но не получил ID. Попытка повторного сохранения...");
                $sneakers->save();
            }
            
            if (!isset($sneakers->id) || !$sneakers->id) {
                throw new \Exception("Товар 3 не получил ID после создания. Возможна проблема с моделью или БД.");
            }
            
            $this->info("Товар 3 создан, ID: " . $sneakers->id);
        } catch (\Exception $e) {
            $this->error("Ошибка создания товара 3: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            throw $e;
        }
        
        // Привязываем товары к категориям
        $tshirt->categories()->attach($clothesCategoryId);
        $iphone->categories()->attach($phonesCategoryId);
        $sneakers->categories()->attach($shoesCategoryId);

        // Устанавливаем значения атрибутов для товаров
        $this->info('Установка значений атрибутов для товаров...');
        
        try {
            $tshirt->setAttributeValue($colorAttribute->id, 'Красный');
            $tshirt->setAttributeValue($sizeAttribute->id, 'M');
            $tshirt->setAttributeValue($materialAttribute->id, 'Хлопок 100%');
            $tshirt->setAttributeValue($brandAttribute->id, 'Adidas');
            $this->info('Атрибуты для товара 1 установлены');
        } catch (\Exception $e) {
            $this->error("Ошибка установки атрибутов для товара 1: " . $e->getMessage());
        }

        try {
            $iphone->setAttributeValue($colorAttribute->id, 'Черный');
            $iphone->setAttributeValue($memoryAttribute->id, '256');
            $iphone->setAttributeValue($brandAttribute->id, 'Apple');
            $this->info('Атрибуты для товара 2 установлены');
        } catch (\Exception $e) {
            $this->error("Ошибка установки атрибутов для товара 2: " . $e->getMessage());
        }

        try {
            $sneakers->setAttributeValue($colorAttribute->id, 'Белый');
            $sneakers->setAttributeValue($sizeAttribute->id, '42');
            $sneakers->setAttributeValue($materialAttribute->id, 'Кожа');
            $sneakers->setAttributeValue($brandAttribute->id, 'Nike');
            $this->info('Атрибуты для товара 3 установлены');
        } catch (\Exception $e) {
            $this->error("Ошибка установки атрибутов для товара 3: " . $e->getMessage());
        }

        $this->info('Система атрибутов успешно создана!');
        $this->info('Создано:');
        $this->info('- 3 категории (Одежда, Смартфоны, Обувь)');
        $this->info('- 5 атрибутов (Цвет, Размер, Память, Материал, Бренд)');
        $this->info('- 16 значений атрибутов');
        $this->info('- 3 товара с заполненными атрибутами');
    }
}
