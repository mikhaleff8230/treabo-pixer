<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class PlanSubscription extends Model
{
    protected $fillable = [
        'seller_id',
        'plan_id',
        'start_date',
        'end_date',
        'amount',
        'is_proportional',
        'days_paid',
        'status',
        'invoice_id',
        'auto_renewal_at',
        'auto_renewal_enabled',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'is_proportional' => 'boolean',
        'auto_renewal_at' => 'datetime',
        'auto_renewal_enabled' => 'boolean',
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
     * Связь со счетом
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Проверить, активна ли подписка
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
     * Рассчитать пропорциональную стоимость тарифа
     * 
     * @param Plan $plan Тарифный план
     * @param Carbon $startDate Дата начала
     * @param Carbon $endDate Дата окончания
     * @return float Сумма к оплате
     */
    public static function calculateProportionalPrice(Plan $plan, Carbon $startDate, Carbon $endDate): float
    {
        $daysInMonth = $startDate->daysInMonth;
        $daysToPay = $endDate->diffInDays($startDate) + 1; // +1 чтобы включить оба дня
        
        $pricePerDay = $plan->price / $daysInMonth;
        return round($pricePerDay * $daysToPay, 2);
    }
}

