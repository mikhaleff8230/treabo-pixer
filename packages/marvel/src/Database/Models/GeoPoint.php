<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Модель географических точек.
 * 
 * Если PostGIS включен — использует колонку location типа geography.
 * Иначе — lat/lng с составным индексом.
 * 
 * Рекомендуется установить PostGIS для продакшена (ST_DWithin, GIST индекс).
 */
class GeoPoint extends Model
{
    use HasFactory;

    protected $table = 'geo_points';

    protected $fillable = [
        'lat',
        'lng',
        'location', // для PostGIS
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /**
     * Связь с продуктами
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Связь с user_locations
     */
    public function userLocations()
    {
        return $this->hasMany(\Marvel\Database\Models\UserLocation::class);
    }

    /**
     * Вычисление расстояния до другой точки (если PostGIS)
     */
    public function distanceTo(float $lat, float $lng): float
    {
        // Проверяем, есть ли колонка location (PostGIS)
        if (Schema::hasColumn('geo_points', 'location')) {
            return DB::selectOne(
                "SELECT ST_Distance(
                    location, 
                    ST_MakePoint(?, ?)::geography
                ) as distance FROM geo_points WHERE id = ?",
                [$lng, $lat, $this->id]
            )->distance ?? 999999;
        }

        // Простой расчет по формуле Haversine (fallback)
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat - $this->lat);
        $dLon = deg2rad($lng - $this->lng);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($this->lat)) * cos(deg2rad($lat)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}
