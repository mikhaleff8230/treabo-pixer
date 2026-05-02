<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Модель для кэширования результатов геолокации по IP-адресам.
 * 
 * Позволяет избежать повторных запросов к DaData API и экономить лимит (10 000 запросов/день).
 * Рекомендация DaData: "Запоминать результат, который вернула «Дадата» — и не делать повторных вызовов"
 */
class GeoIpCache extends Model
{
    use HasFactory;

    protected $table = 'geo_ip_cache';

    protected $fillable = [
        'ip_address',
        'city',
        'city_with_type',
        'region',
        'region_with_type',
        'state_name',
        'country',
        'iso_code',
        'region_iso_code',
        'postal_code',
        'federal_district',
        'lat',
        'lon',
        'kladr_id',
        'city_kladr_id',
        'region_kladr_id',
        'fias_id',
        'city_fias_id',
        'region_fias_id',
        'timezone',
        'source',
        'full_address',
        'unrestricted_value',
        'request_count',
        'last_used_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'request_count' => 'integer',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Найти или создать запись для IP-адреса
     */
    public static function findByIp(string $ip): ?self
    {
        return self::where('ip_address', $ip)->first();
    }

    /**
     * Сохранить результат геолокации
     */
    public static function saveLocation(string $ip, array $locationData): self
    {
        $cache = self::findByIp($ip);
        
        if ($cache) {
            // Обновляем существующую запись
            $cache->update([
                'city' => $locationData['city'] ?? null,
                'city_with_type' => $locationData['city_with_type'] ?? null,
                'region' => $locationData['region'] ?? null,
                'region_with_type' => $locationData['region_with_type'] ?? null,
                'state_name' => $locationData['state_name'] ?? null,
                'country' => $locationData['country'] ?? null,
                'iso_code' => $locationData['iso_code'] ?? null,
                'region_iso_code' => $locationData['region_iso_code'] ?? null,
                'postal_code' => $locationData['postal_code'] ?? null,
                'federal_district' => $locationData['federal_district'] ?? null,
                'lat' => $locationData['lat'] ?? null,
                'lon' => $locationData['lon'] ?? null,
                'kladr_id' => $locationData['kladr_id'] ?? null,
                'city_kladr_id' => $locationData['city_kladr_id'] ?? null,
                'region_kladr_id' => $locationData['region_kladr_id'] ?? null,
                'fias_id' => $locationData['fias_id'] ?? null,
                'city_fias_id' => $locationData['city_fias_id'] ?? null,
                'region_fias_id' => $locationData['region_fias_id'] ?? null,
                'timezone' => $locationData['timezone'] ?? null,
                'source' => $locationData['source'] ?? 'unknown',
                'full_address' => $locationData['full_address'] ?? null,
                'unrestricted_value' => $locationData['unrestricted_value'] ?? null,
                'request_count' => $cache->request_count + 1,
                'last_used_at' => now(),
            ]);
        } else {
            // Создаем новую запись
            $cache = self::create([
                'ip_address' => $ip,
                'city' => $locationData['city'] ?? null,
                'city_with_type' => $locationData['city_with_type'] ?? null,
                'region' => $locationData['region'] ?? null,
                'region_with_type' => $locationData['region_with_type'] ?? null,
                'state_name' => $locationData['state_name'] ?? null,
                'country' => $locationData['country'] ?? null,
                'iso_code' => $locationData['iso_code'] ?? null,
                'region_iso_code' => $locationData['region_iso_code'] ?? null,
                'postal_code' => $locationData['postal_code'] ?? null,
                'federal_district' => $locationData['federal_district'] ?? null,
                'lat' => $locationData['lat'] ?? null,
                'lon' => $locationData['lon'] ?? null,
                'kladr_id' => $locationData['kladr_id'] ?? null,
                'city_kladr_id' => $locationData['city_kladr_id'] ?? null,
                'region_kladr_id' => $locationData['region_kladr_id'] ?? null,
                'fias_id' => $locationData['fias_id'] ?? null,
                'city_fias_id' => $locationData['city_fias_id'] ?? null,
                'region_fias_id' => $locationData['region_fias_id'] ?? null,
                'timezone' => $locationData['timezone'] ?? null,
                'source' => $locationData['source'] ?? 'unknown',
                'full_address' => $locationData['full_address'] ?? null,
                'unrestricted_value' => $locationData['unrestricted_value'] ?? null,
                'request_count' => 1,
                'last_used_at' => now(),
            ]);
        }
        
        return $cache;
    }

    /**
     * Увеличить счетчик использования
     */
    public function incrementUsage(): void
    {
        $this->increment('request_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Преобразовать в массив для GeoLocationService
     */
    public function toLocationArray(): array
    {
        return [
            'ip' => $this->ip_address,
            'city' => $this->city,
            'city_with_type' => $this->city_with_type,
            'region' => $this->region,
            'region_with_type' => $this->region_with_type,
            'state_name' => $this->state_name,
            'country' => $this->country,
            'iso_code' => $this->iso_code,
            'region_iso_code' => $this->region_iso_code,
            'postal_code' => $this->postal_code,
            'federal_district' => $this->federal_district,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'kladr_id' => $this->kladr_id,
            'city_kladr_id' => $this->city_kladr_id,
            'region_kladr_id' => $this->region_kladr_id,
            'fias_id' => $this->fias_id,
            'city_fias_id' => $this->city_fias_id,
            'region_fias_id' => $this->region_fias_id,
            'timezone' => $this->timezone,
            'source' => $this->source . '_cached',
            'full_address' => $this->full_address,
            'unrestricted_value' => $this->unrestricted_value,
        ];
    }
}





