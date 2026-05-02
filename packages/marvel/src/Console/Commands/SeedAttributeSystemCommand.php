<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Seeders\AttributeSystemSeeder;

class SeedAttributeSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marvel:seed-attributes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the attribute system with test data (categories, attributes, products)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌱 Запуск создания системы атрибутов...');
        
        try {
            $seeder = new AttributeSystemSeeder();
            $seeder->run();
            
            $this->info('✅ Система атрибутов успешно создана!');
            $this->info('');
            $this->info('📋 Что было создано:');
            $this->info('   • 3 категории (Одежда, Смартфоны, Обувь)');
            $this->info('   • 5 атрибутов (Цвет, Размер, Память, Материал, Бренд)');
            $this->info('   • 16 значений атрибутов');
            $this->info('   • 3 товара с заполненными атрибутами');
            $this->info('');
            $this->info('🔗 Доступные API endpoints:');
            $this->info('   • GET /api/categories/{id}/attributes - атрибуты категории');
            $this->info('   • POST /api/categories/attributes/attach - привязать атрибут к категории');
            $this->info('   • GET /api/products/{id}/attributes - атрибуты товара');
            $this->info('   • POST /api/products/attributes/set - установить значение атрибута');
            $this->info('   • POST /api/products/filter-by-attributes - фильтрация товаров');
            
        } catch (\Exception $e) {
            $this->error('❌ Ошибка при создании системы атрибутов: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
