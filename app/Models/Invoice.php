<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Database\Models\User;

class Invoice extends Model
{
    protected $fillable = [
        'seller_id',
        'plan_id',
        'period_start',
        'period_end',
        'total_products',
        'total_places',
        'price_per_product',
        'total_amount',
        'status',
        'paid_at',
        'payment_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'price_per_product' => 'decimal:2',
    ];

    /**
     * Связь с продавцом
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Связь с тарифным планом
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Рассчитать сумму счета по тарифному плану
     * 
     * @param Plan|null $plan Тарифный план (если null, используется старый тариф)
     * @param int $totalProducts Количество товаров
     * @param int $totalPlaylists Количество плейсов
     * @return float Сумма счета
     */
    public static function calculateTariffAmount(?Plan $plan, int $totalProducts = 0, int $totalPlaylists = 0): float
    {
        if ($totalProducts <= 0) {
            return 0.0;
        }

        // Если тарифный план не указан, используем старую логику для обратной совместимости
        if (!$plan) {
            return self::calculateLegacyTariffAmount($totalProducts);
        }

        // Используем новый расчет на основе тарифного плана
        return $plan->calculateMonthlyAmount($totalProducts, $totalPlaylists);
    }

    /**
     * Старый метод расчета тарифа (для обратной совместимости):
     * - 200 руб за первые 200 товаров
     * - 0.5 руб за каждый последующий товар
     * 
     * @param int $totalProducts Количество товаров
     * @return float Сумма счета
     */
    public static function calculateLegacyTariffAmount(int $totalProducts): float
    {
        if ($totalProducts <= 0) {
            return 0.0;
        }

        if ($totalProducts <= 200) {
            return 200.0;
        }

        // 200 руб за первые 200 товаров + 0.5 руб за каждый последующий
        return 200.0 + (($totalProducts - 200) * 0.5);
    }
}
