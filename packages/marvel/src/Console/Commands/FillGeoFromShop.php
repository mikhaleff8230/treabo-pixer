<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\GeoPoint;
use Marvel\Database\Models\Region;
use Illuminate\Support\Facades\DB;

class FillGeoFromShop extends Command
{
    protected $signature = 'products:fill-geo-from-shop 
        {--shop= : ID конкретного магазина (опционально)} 
        {--demo : Заполнить демо-данными магазины без региона (Москва)}
        {--force : Перезаписывать существующие геоданные}';

    protected $description = 'Наполняет товары геолокацией из связанного магазина. С флагом --demo заполняет демо-локацией Москва.';

    public function handle()
    {
        $shopId = $this->option('shop');
        $demo = $this->option('demo');
        $force = $this->option('force');

        if ($demo) {
            return $this->runDemoMode();
        }

        $query = Shop::with(['region', 'products']);

        if ($shopId) {
            $query->where('id', $shopId);
        }

        $shops = $query->get();

        if ($shops->isEmpty()) {
            $this->error('Магазины не найдены.');
            return 1;
        }

        $updated = 0;
        $createdPoints = 0;

        foreach ($shops as $shop) {
            if (!$shop->region) {
                $this->warn("Магазин {$shop->name} ({$shop->id}) не имеет региона. Пропускаем.");
                continue;
            }

            $this->info("Обрабатываем магазин: {$shop->name} (ID: {$shop->id}) — регион: {$shop->region->name}");

            foreach ($shop->products as $product) {
                $updatedProduct = false;

                if (!$product->region_id || $force) {
                    $product->region_id = $shop->region->id;
                    $updatedProduct = true;
                }

                if ($shop->address && isset($shop->address['lat'], $shop->address['lng'])) {
                    $lat = (float)$shop->address['lat'];
                    $lng = (float)$shop->address['lng'];

                    $geoPoint = GeoPoint::firstOrCreate(
                        ['lat' => $lat, 'lng' => $lng],
                        ['lat' => $lat, 'lng' => $lng]
                    );

                    if (!$product->geo_point_id || $force) {
                        $product->geo_point_id = $geoPoint->id;
                        $updatedProduct = true;
                        $createdPoints++;
                    }
                }

                if ($updatedProduct) {
                    $product->save();
                    $updated++;
                }
            }
        }

        $this->info("Готово!");
        $this->info("Обновлено товаров: {$updated}");
        $this->info("Создано геоточек: {$createdPoints}");

        return 0;
    }

    private function runDemoMode()
    {
        $this->info('Запуск демо-режима: назначение Москвы магазинам без региона...');

        $moscow = Region::where('slug', 'moscow')->orWhere('name', 'like', '%Москва%')->first();

        if (!$moscow) {
            $this->error('Регион "Москва" не найден в таблице regions!');
            $this->info('Сначала запустите seeder: php artisan db:seed --class=Marvel\\Database\\Seeders\\RegionSeeder');
            return 1;
        }

        $demoLat = 55.855;
        $demoLng = 37.415;

        $geoPoint = GeoPoint::firstOrCreate(
            ['lat' => $demoLat, 'lng' => $demoLng],
            ['lat' => $demoLat, 'lng' => $demoLng]
        );

        $shops = Shop::whereNull('region_id')->orWhere('region_id', 0)->get();

        $updatedShops = 0;
        $updatedProducts = 0;

        foreach ($shops as $shop) {
            $shop->region_id = $moscow->id;
            $shop->save();
            $updatedShops++;

            $products = Product::where('shop_id', $shop->id)->get();

            foreach ($products as $product) {
                $product->region_id = $moscow->id;
                $product->geo_point_id = $geoPoint->id;
                $product->address = 'Москва, Вилиса Лациса 3 к1';
                $product->save();
                $updatedProducts++;
            }
        }

        $this->info("✅ Демо-режим завершён!");
        $this->info("Обновлено магазинов: {$updatedShops}");
        $this->info("Обновлено товаров: {$updatedProducts}");
        $this->info("Использован регион: Москва (ID: {$moscow->id})");
        $this->info("Координаты: {$demoLat}, {$demoLng}");

        return 0;
    }
}
