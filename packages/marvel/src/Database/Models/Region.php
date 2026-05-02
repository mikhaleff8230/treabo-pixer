<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Модель иерархических регионов.
 * 
 * Design decisions:
 * - Self-referencing parent_id для иерархии (страна → регион → город → район)
 * - type строго контролирует уровень
 * - slug для SEO-friendly URL (например /catalog/moscow)
 * - fias_code и yandex_region_id для интеграции с внешними сервисами
 */
class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'slug',
        'fias_code',
        'yandex_region_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Родительский регион (например, город для района)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    /**
     * Дочерние регионы
     */
    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    /**
     * Города в этом регионе (для country/region)
     */
    public function cities(): HasMany
    {
        return $this->children()->where('type', 'city');
    }

    /**
     * Продукты, привязанные напрямую к этому региону (city-level)
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Продукты, которые также покрывают этот регион (через product_region_relations)
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_region_relations');
    }

    /**
     * Соседние регионы
     */
    public function neighbors(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'region_neighbors', 'region_id', 'neighbor_region_id');
    }

    /**
     * Scope для получения только активных регионов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для получения регионов определенного типа
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Получить полный путь от корня (для отображения "Россия → Москва → Центральный округ")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        
        return implode(' → ', $path);
    }
}
