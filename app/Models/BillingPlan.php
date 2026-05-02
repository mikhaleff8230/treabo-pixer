<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'monthly_price',
        'product_limit',
        'place_limit',
        'extra_product_price',
        'extra_place_price',
        'photos_per_product',
        'has_shop',
        'has_extended_shop',
        'has_ozon_wb_link',
        'has_utm_tags',
        'analytics_level',
        'search_priority',
        'featured_in_collections',
        'support_level',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'extra_product_price' => 'decimal:2',
        'extra_place_price' => 'decimal:2',
        'has_shop' => 'boolean',
        'has_extended_shop' => 'boolean',
        'has_ozon_wb_link' => 'boolean',
        'has_utm_tags' => 'boolean',
        'featured_in_collections' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Связь с пользователями (продавцами)
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Marvel\Database\Models\User::class, 'billing_plan_id');
    }

    /**
     * Рассчитать стоимость за месяц на основе количества товаров и плейсов
     * 
     * @param int $totalProducts Общее количество товаров
     * @param int $totalPlaces Общее количество плейсов
     * @return float Сумма к оплате
     */
    public function calculateMonthlyAmount(int $totalProducts = 0, int $totalPlaces = 0): float
    {
        $amount = $this->monthly_price;

        // Если лимит товаров установлен и превышен
        if ($this->product_limit > 0 && $totalProducts > $this->product_limit) {
            $extraProducts = $totalProducts - $this->product_limit;
            $amount += $extraProducts * $this->extra_product_price;
        }

        // Если лимит плейсов установлен и превышен
        if ($this->place_limit > 0 && $totalPlaces > $this->place_limit) {
            $extraPlaces = $totalPlaces - $this->place_limit;
            $amount += $extraPlaces * $this->extra_place_price;
        }

        return max(0, $amount);
    }

    /**
     * Проверить, превышен ли лимит товаров
     */
    public function isProductLimitExceeded(int $totalProducts): bool
    {
        if ($this->product_limit === 0) {
            return false; // Безлимит
        }
        return $totalProducts > $this->product_limit;
    }

    /**
     * Проверить, превышен ли лимит плейсов
     */
    public function isPlaceLimitExceeded(int $totalPlaces): bool
    {
        if ($this->place_limit === 0) {
            return false; // Безлимит
        }
        return $totalPlaces > $this->place_limit;
    }

    /**
     * Получить тариф по умолчанию (FREE)
     */
    public static function getDefault(): ?self
    {
        return self::where('name', 'FREE')->where('is_active', true)->first();
    }

    /**
     * Получить тариф по имени
     */
    public static function getByName(string $name): ?self
    {
        return self::where('name', $name)->where('is_active', true)->first();
    }

    /**
     * Получить все активные тарифы, отсортированные по sort_order
     */
    public static function getActivePlans()
    {
        return self::where('is_active', true)->orderBy('sort_order')->get();
    }
}

