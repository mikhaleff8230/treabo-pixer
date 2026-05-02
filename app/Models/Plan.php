<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'name',
        'price',
        'limit_products',
        'limit_playlists',
        'extra_product_price',
        'extra_playlist_price',
        'link_ozon_wb',
        'utm_tracking',
        'chat_enabled',
        'featured_collections',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'limit_products' => 'integer',
        'limit_playlists' => 'integer',
        'extra_product_price' => 'decimal:2',
        'extra_playlist_price' => 'decimal:2',
        'link_ozon_wb' => 'boolean',
        'utm_tracking' => 'boolean',
        'chat_enabled' => 'boolean',
        'featured_collections' => 'boolean',
    ];

    /**
     * Связь с пользователями (продавцами)
     */
    public function users(): HasMany
    {
        return $this->hasMany(\Marvel\Database\Models\User::class, 'plan_id');
    }

    /**
     * Рассчитать стоимость за месяц на основе количества товаров и плейсов
     * 
     * @param int $totalProducts Общее количество товаров
     * @param int $totalPlaylists Общее количество плейсов
     * @return float Сумма к оплате
     */
    public function calculateMonthlyAmount(int $totalProducts = 0, int $totalPlaylists = 0): float
    {
        $amount = $this->price;

        // Если лимит товаров установлен и превышен
        if ($this->limit_products > 0 && $totalProducts > $this->limit_products) {
            $extraProducts = $totalProducts - $this->limit_products;
            $amount += $extraProducts * ($this->extra_product_price ?? 0);
        }

        // Если лимит плейсов установлен и превышен
        if ($this->limit_playlists > 0 && $totalPlaylists > $this->limit_playlists) {
            $extraPlaylists = $totalPlaylists - $this->limit_playlists;
            $amount += $extraPlaylists * ($this->extra_playlist_price ?? 0);
        }

        return max(0, $amount);
    }

    /**
     * Получить тариф по умолчанию (Free)
     */
    public static function getDefault(): ?self
    {
        return self::where('name', 'Free')->first();
    }

    /**
     * Получить тариф по имени
     */
    public static function getByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }
}

