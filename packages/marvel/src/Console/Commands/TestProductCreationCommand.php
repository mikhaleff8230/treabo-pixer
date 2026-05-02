<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Type;

class TestProductCreationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marvel:test-product-creation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests product creation to debug the ID issue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ТЕСТ СОЗДАНИЯ ТОВАРА ===');
        
        // Проверяем существование таблицы products
        $this->info('1. Проверка таблицы products...');
        try {
            $count = Product::count();
            $this->info("   ✅ Таблица products существует, записей: $count");
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при обращении к таблице products: " . $e->getMessage());
            return;
        }
        
        // Проверяем Shop
        $this->info('2. Проверка Shop...');
        $shop = Shop::first();
        if (!$shop) {
            $this->warn('   ⚠️ Shop не найден, создаем тестовый...');
            $shop = Shop::create([
                'name' => 'Тестовый магазин',
                'slug' => 'test-shop',
                'owner_id' => 1,
                'is_active' => true,
            ]);
        }
        $this->info("   ✅ Shop ID: " . $shop->id);
        
        // Проверяем Type
        $this->info('3. Проверка Type...');
        $type = Type::first();
        if (!$type) {
            $this->warn('   ⚠️ Type не найден, создаем тестовый...');
            $type = Type::create([
                'name' => 'Товары',
                'slug' => 'products',
            ]);
        }
        $this->info("   ✅ Type ID: " . $type->id);
        
        // Пробуем создать товар с минимальными данными
        $this->info('4. Создание товара с минимальными данными...');
        try {
            $product = Product::create([
                'name' => 'Тестовый товар',
                'slug' => 'test-product',
                'type_id' => $type->id,
                'unit' => 'шт',
            ]);
            $this->info("   ✅ Товар создан успешно, ID: " . $product->id);
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при создании товара: " . $e->getMessage());
            $this->error("   Детали ошибки: " . $e->getTraceAsString());
        }
        
        // Пробуем создать товар с полными данными
        $this->info('5. Создание товара с полными данными...');
        try {
            $product = Product::create([
                'name' => 'Тестовый товар полный',
                'slug' => 'test-product-full',
                'description' => 'Описание товара',
                'price' => 1000,
                'shop_id' => $shop->id,
                'type_id' => $type->id,
                'status' => 'publish',
                'unit' => 'шт',
                'language' => 'ru',
            ]);
            $this->info("   ✅ Товар с полными данными создан, ID: " . $product->id);
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при создании товара с полными данными: " . $e->getMessage());
        }
        
        $this->info('=== КОНЕЦ ТЕСТА ===');
    }
}



