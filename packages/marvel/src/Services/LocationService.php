<?php

namespace Marvel\Services;

use Marvel\Database\Models\Region;
use Marvel\Database\Models\GeoPoint;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Сервис геолокации — центральный класс для всей location-aware логики.
 * 
 * Отвечает за:
 * - Определение региона пользователя (по IP, GPS, manual)
 * - Поиск товаров по региону, соседям, радиусу
 * - Управление иерархией регионов
 * - Кэширование часто используемых данных
 */
class LocationService
{
    /**
     * Получить товары для пользователя с учетом его геолокации
     */
    public function getProductsForUser($user = null, array $filters = [])
    {
        $region = $this->getUserRegion($user);
        
        $query = Product::published()
            ->with(['region', 'geoPoint'])
            ->inRegionOrNeighbors($region->id);

        if (!empty($filters['category_id'])) {
            $query->where('type_id', $filters['category_id']);
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 24);
    }

    /**
     * Получить регион пользователя (с кэшированием)
     */
    public function getUserRegion($user = null): Region
    {
        $cacheKey = $user ? "user_region_{$user->id}" : 'default_region';

        $region = Cache::remember($cacheKey, 3600, function () use ($user) {
            if ($user && $location = $user->userLocation()->first()) {
                $r = $location->region;
                if ($r instanceof Region) {
                    return $r;
                }
            }

            // По умолчанию — Москва, иначе любой активный город, иначе любая запись regions
            return Region::active()
                ->ofType('city')
                ->where('slug', 'moscow')
                ->first()
                ?? Region::active()->ofType('city')->first()
                ?? Region::query()->first();
        });

        if (!$region instanceof Region) {
            throw new \RuntimeException(
                'В БД нет записей в таблице regions. Добавьте хотя бы один регион (например, Москва).'
            );
        }

        return $region;
    }

    /**
     * Поиск товаров в радиусе (PostGIS)
     */
    public function findProductsByRadius(float $lat, float $lng, int $radiusMeters = 50000)
    {
        if (!$this->isPostgisEnabled()) {
            // Fallback — простая фильтрация по городу
            $region = $this->findNearestCity($lat, $lng);
            return Product::published()
                ->where('region_id', $region->id)
                ->with('geoPoint')
                ->latest()
                ->paginate(20);
        }

        return DB::table('products as p')
            ->join('geo_points as g', 'g.id', '=', 'p.geo_point_id')
            ->select('p.*', DB::raw("ST_Distance(g.location, ST_MakePoint({$lng}, {$lat})::geography) as distance"))
            ->whereRaw('ST_DWithin(g.location, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radiusMeters])
            ->where('p.is_active', true)
            ->where('p.is_published', true)
            ->orderBy('distance')
            ->paginate(20);
    }

    /**
     * Проверка наличия PostGIS
     */
    private function isPostgisEnabled(): bool
    {
        static $enabled = null;
        
        if ($enabled === null) {
            // Проверяем, используется ли PostgreSQL и есть ли колонка location (PostGIS)
            try {
                if (config('database.default') !== 'pgsql' && DB::getDriverName() !== 'pgsql') {
                    $enabled = false;
                    return $enabled;
                }
                
                // Проверяем наличие колонки location вместо pg_extension (работает и на MySQL)
                $enabled = Schema::hasColumn('geo_points', 'location');
            } catch (\Exception $e) {
                $enabled = false;
            }
        }
        
        return $enabled;
    }

    /**
     * Найти ближайший город по координатам (fallback)
     */
    private function findNearestCity(float $lat, float $lng): Region
    {
        return Region::active()
            ->ofType('city')
            ->first();
    }

    /**
     * Создать или обновить геоточку
     */
    public function createGeoPoint(float $lat, float $lng): GeoPoint
    {
        return GeoPoint::firstOrCreate(
            ['lat' => $lat, 'lng' => $lng],
            ['lat' => $lat, 'lng' => $lng]
        );
    }
}
