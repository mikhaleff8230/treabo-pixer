<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\Shop;

class TestAttributeCreationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marvel:test-attribute-creation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests attribute creation to debug the admin panel issue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ТЕСТ СОЗДАНИЯ АТРИБУТОВ ===');
        
        // Проверяем существование таблицы attributes
        $this->info('1. Проверка таблицы attributes...');
        try {
            $count = Attribute::count();
            $this->info("   ✅ Таблица attributes существует, записей: $count");
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при обращении к таблице attributes: " . $e->getMessage());
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
        
        // Пробуем создать атрибут с минимальными данными
        $this->info('3. Создание атрибута с минимальными данными...');
        try {
            $attribute = Attribute::create([
                'name' => 'Тестовый атрибут',
                'slug' => 'test-attribute',
                'shop_id' => $shop->id,
            ]);
            $this->info("   ✅ Атрибут создан успешно, ID: " . $attribute->id);
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при создании атрибута: " . $e->getMessage());
            $this->error("   Детали ошибки: " . $e->getTraceAsString());
        }
        
        // Пробуем создать атрибут с полными данными
        $this->info('4. Создание атрибута с полными данными...');
        try {
            $attribute = Attribute::create([
                'name' => 'Тестовый атрибут полный',
                'slug' => 'test-attribute-full',
                'shop_id' => $shop->id,
                'type' => 'select',
                'input_type' => 'select',
                'is_required' => true,
                'description' => 'Описание атрибута',
                'sort_order' => 1,
                'language' => 'ru',
            ]);
            $this->info("   ✅ Атрибут с полными данными создан, ID: " . $attribute->id);
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка при создании атрибута с полными данными: " . $e->getMessage());
        }
        
        $this->info('=== КОНЕЦ ТЕСТА ===');
    }
}



