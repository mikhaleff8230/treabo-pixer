<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class ProSubscription extends Model
{
    protected $fillable = [
        'seller_id',
        'amount', // 249.00 руб (обновлено)
        'start_date',
        'end_date',
        'status',
        'invoice_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Связь с продавцом
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Связь со счетом
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Проверить, активна ли подписка (подписка активна, если оплачена, не истекла, либо находится в 30-дневном грейс-периоде для автосписания).
     * После окончания end_date начинается ежедневная попытка автосписания ещё в течение 30 дней.
     * Если за эти 30 дней не удалось оплатить — просто блокируется создание новых товаров, ранее созданные не трогаются.
     */
    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->status === 'active' 
            && $this->start_date <= $now 
            && $this->end_date >= $now;
    }

    /**
     * Проверить, истекла ли подписка
     */
    public function isExpired(): bool
    {
        return $this->end_date < Carbon::now();
    }

    /**
     * Получить активную подписку продавца
     */
    public static function getActive(int $sellerId): ?self
    {
        return self::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->orderBy('end_date', 'desc')
            ->first();
    }

    /**
     * Проверить, есть ли активная подписка у продавца
     */
    public static function hasActive(int $sellerId): bool
    {
        return self::getActive($sellerId) !== null;
    }
}

