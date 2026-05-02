<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class AdditionalPurchase extends Model
{
    protected $fillable = [
        'seller_id',
        'type',
        'quantity',
        'price_per_unit',
        'total_amount',
        'discount',
        'plan_id',
        'payment_method',
        'payment_id',
        'status',
        'valid_until',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'valid_until' => 'date',
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
     * Проверить, действительна ли покупка
     */
    public function isValid(): bool
    {
        if ($this->status !== 'paid') {
            return false;
        }

        if ($this->type === 'playlist' && $this->valid_until) {
            return $this->valid_until >= Carbon::now();
        }

        // Для товаров покупка бессрочная (пока не отменена)
        return true;
    }

    /**
     * Рассчитать цену с учетом скидки по тарифу
     * 
     * @param Plan $plan Текущий тариф продавца
     * @param string $type Тип покупки: 'product' или 'playlist'
     * @param int $quantity Количество
     * @return array ['price_per_unit' => float, 'total' => float, 'discount' => float]
     */
    public static function calculatePriceWithDiscount(?Plan $plan, string $type, int $quantity = 1): array
    {
        $basePrice = 0;
        
        if ($type === 'product') {
            $basePrice = $plan?->extra_product_price ?? 0.5;
        } elseif ($type === 'playlist') {
            $basePrice = $plan?->extra_playlist_price ?? 2.0;
        }

        // Скидки по тарифам (уже учтены в ценах тарифа)
        // Pro: 1 ₽ за плейс, Standard/Free: 2 ₽
        // Дополнительных скидок нет, цены уже установлены в тарифах

        $total = $basePrice * $quantity;

        return [
            'price_per_unit' => $basePrice,
            'total' => $total,
            'discount' => 0, // Скидки уже учтены в ценах тарифа
        ];
    }
}

