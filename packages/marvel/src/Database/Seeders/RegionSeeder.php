<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Region;

class RegionSeeder extends Seeder
{
    /**
     * Заполняет базу базовыми регионами России.
     * Структура: Страна → Область/Край → Город → Район.
     */
    public function run(): void
    {
        // 1. Создаем страну
        $russia = Region::firstOrCreate(
            ['slug' => 'russia'],
            [
                'parent_id' => null,
                'type' => 'country',
                'name' => 'Россия',
                'fias_code' => '0c5b2444-70a0-4932-980c-b4dc0d3f02b5',
                'is_active' => true,
            ]
        );

        // 2. Создаем крупные регионы (области)
        $moscowRegion = Region::firstOrCreate(
            ['slug' => 'moscow-oblast'],
            [
                'parent_id' => $russia->id,
                'type' => 'region',
                'name' => 'Московская область',
                'fias_code' => 'c2deb16a-0330-4f05-961d-d1df4c9b1a0d',
                'is_active' => true,
            ]
        );

        $spbRegion = Region::firstOrCreate(
            ['slug' => 'leningrad-oblast'],
            [
                'parent_id' => $russia->id,
                'type' => 'region',
                'name' => 'Ленинградская область',
                'is_active' => true,
            ]
        );

        // 3. Создаем города (city-level — обязательный уровень для продуктов)
        $moscow = Region::firstOrCreate(
            ['slug' => 'moscow'],
            [
                'parent_id' => $moscowRegion->id,
                'type' => 'city',
                'name' => 'Москва',
                'fias_code' => '0c5b2444-70a0-4932-980c-b4dc0d3f02b5',
                'yandex_region_id' => '213',
                'is_active' => true,
            ]
        );

        $spb = Region::firstOrCreate(
            ['slug' => 'saint-petersburg'],
            [
                'parent_id' => $spbRegion->id,
                'type' => 'city',
                'name' => 'Санкт-Петербург',
                'fias_code' => 'c2deb16a-0330-4f05-961d-d1df4c9b1a0d',
                'yandex_region_id' => '2',
                'is_active' => true,
            ]
        );

        $novosibirsk = Region::firstOrCreate(
            ['slug' => 'novosibirsk'],
            [
                'parent_id' => $russia->id,
                'type' => 'city',
                'name' => 'Новосибирск',
                'is_active' => true,
            ]
        );

        // 4. Создаем несколько районов в Москве
        $central = Region::firstOrCreate(
            ['slug' => 'central-district'],
            [
                'parent_id' => $moscow->id,
                'type' => 'district',
                'name' => 'Центральный административный округ',
                'is_active' => true,
            ]
        );

        $sokolniki = Region::firstOrCreate(
            ['slug' => 'sokolniki'],
            [
                'parent_id' => $moscow->id,
                'type' => 'district',
                'name' => 'Сокольники',
                'is_active' => true,
            ]
        );

        // 5. Настраиваем соседей (для "рядом" поиска)
        $moscow->neighbors()->syncWithoutDetaching([
            $spb->id,
            $novosibirsk->id,
            $central->id,
            $sokolniki->id,
        ]);

        $spb->neighbors()->syncWithoutDetaching([$moscow->id]);

        $this->command->info('✅ Регионы успешно созданы! (Россия, Москва, СПб и др.)');
        $this->command->info('   - ' . Region::count() . ' регионов в базе.');
    }
}
