<?php

namespace Marvel\Services;

use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentPeriodService
{
    // Период оплаты (дней)
    const PAYMENT_PERIOD_DAYS = 180;

    /**
     * ПРОСТАЯ ПРОВЕРКА: Оплачен ли период для товара
     * 
     * @param Product $product
     * @return bool true если период оплачен и не истек, false в противном случае
     */
    public function isPeriodPaid(Product $product): bool
    {
        try {
            // Если нет даты оплаты, период не оплачен
            if (!$product->paid_until) {
                Log::debug('PaymentPeriodService::isPeriodPaid - Нет даты оплаты', [
                    'product_id' => $product->id ?? null,
                ]);
                return false;
            }

            $now = Carbon::now();
            $paidUntil = Carbon::parse($product->paid_until);

            // Период считается оплаченным, если paid_until >= сегодня (включительно)
            // Используем startOfDay для корректного сравнения дат
            $isPaid = $paidUntil->startOfDay()->greaterThanOrEqualTo($now->startOfDay());

            Log::debug('PaymentPeriodService::isPeriodPaid - Проверка периода', [
                'product_id' => $product->id ?? null,
                'paid_until' => $paidUntil->toDateString(),
                'now' => $now->toDateString(),
                'is_paid' => $isPaid,
            ]);

            return $isPaid;

        } catch (\Exception $e) {
            Log::error('PaymentPeriodService::isPeriodPaid - Ошибка', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Проверить, оплачен ли период для товара (расширенная информация)
     * 
     * @param Product $product
     * @return array [
     *   'is_paid' => bool,
     *   'paid_until' => Carbon|null,
     *   'days_remaining' => int|null,
     *   'is_expired' => bool,
     *   'expiration_date' => Carbon|null
     * ]
     */
    public function checkPaymentPeriod(Product $product): array
    {
        try {
            // Если нет даты оплаты, период не оплачен
            if (!$product->paid_until) {
                return [
                    'is_paid' => false,
                    'paid_until' => null,
                    'days_remaining' => null,
                    'is_expired' => true,
                    'expiration_date' => null,
                ];
            }

            $now = Carbon::now();
            $paidUntil = Carbon::parse($product->paid_until);

            // Проверяем, истек ли период
            // ВАЖНО: Период считается активным, если paid_until >= сегодня (включительно)
            // Товар должен оставаться опубликованным до конца дня paid_until
            $isExpired = $paidUntil->startOfDay()->isPast();

            if ($isExpired) {
                return [
                    'is_paid' => false,
                    'paid_until' => $paidUntil,
                    'days_remaining' => 0,
                    'is_expired' => true,
                    'expiration_date' => $paidUntil,
                ];
            }

            // Период еще активен, считаем оставшиеся дни
            $daysRemaining = $now->diffInDays($paidUntil, false);

            return [
                'is_paid' => true,
                'paid_until' => $paidUntil,
                'days_remaining' => max(0, $daysRemaining),
                'is_expired' => false,
                'expiration_date' => $paidUntil,
            ];

        } catch (\Exception $e) {
            Log::error('PaymentPeriodService::checkPaymentPeriod - Ошибка', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'is_paid' => false,
                'paid_until' => null,
                'days_remaining' => null,
                'is_expired' => true,
                'expiration_date' => null,
            ];
        }
    }

    /**
     * Проверить, можно ли установить статус "опубликовано" для товара
     * 
     * @param Product $product
     * @return bool
     */
    public function canPublish(Product $product): bool
    {
        return $this->isPeriodPaid($product);
    }

    /**
     * Получить форматированную дату окончания периода
     * 
     * @param Product $product
     * @return string|null Формат: "до 5 февраля"
     */
    public function getFormattedExpirationDate(Product $product): ?string
    {
        $periodInfo = $this->checkPaymentPeriod($product);
        
        if (!$periodInfo['expiration_date']) {
            return null;
        }

        $date = $periodInfo['expiration_date'];
        
        // Получаем русские названия месяцев
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];

        $day = $date->day;
        $month = $months[$date->month] ?? '';

        return "до {$day} {$month}";
    }

    /**
     * Получить информацию о периоде для API ответа
     * 
     * @param Product $product
     * @return array
     */
    public function getPeriodInfo(Product $product): array
    {
        $periodInfo = $this->checkPaymentPeriod($product);
        
        return [
            'is_paid' => $periodInfo['is_paid'],
            'paid_until' => $periodInfo['paid_until']?->toDateString(),
            'days_remaining' => $periodInfo['days_remaining'],
            'is_expired' => $periodInfo['is_expired'],
            'expiration_date' => $periodInfo['expiration_date']?->toDateString(),
            'formatted_expiration_date' => $this->getFormattedExpirationDate($product),
            'can_publish' => $this->canPublish($product),
        ];
    }

    /**
     * Проверить период и автоматически перевести товар в черновик, если период истек
     * 
     * @param Product $product
     * @return bool true если статус был изменен
     */
    public function checkAndSetDraftIfExpired(Product $product): bool
    {
        $periodInfo = $this->checkPaymentPeriod($product);
        
        // Если период истек и товар опубликован, переводим в черновик
        if ($periodInfo['is_expired'] && $product->status === 'publish') {
            $product->status = 'draft';
            $product->save();
            
            Log::info('PaymentPeriodService::checkAndSetDraftIfExpired - Товар переведен в черновик', [
                'product_id' => $product->id,
                'paid_until' => $periodInfo['paid_until']?->toDateString(),
            ]);
            
            return true;
        }
        
        return false;
    }
}

