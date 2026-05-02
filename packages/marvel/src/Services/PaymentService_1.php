<?php

namespace Marvel\Services;

use App\Models\SellerBalance;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Marvel\Enums\Permission;

class PaymentService
{
    // Стоимость размещения товара (рублей)
    // ОТМЕНЕНО: С февраля 2026 оплата за публикацию товара не требуется, стоимость публикации НЕ используется!
    const PRODUCT_PLACEMENT_COST = 0;
    
    // Период оплаты (дней)
    const PAYMENT_PERIOD_DAYS = 180;

    /**
     * Оплатить размещение товара или продлить период
     * Автоматически определяет: новый товар или продление периода
     * 
     * @param Product $product
     * @return array ['success' => bool, 'message' => string, 'paid_until' => Carbon|null, 'is_renewal' => bool]
     */
    public function payForProduct(Product $product): array
    {
        try {
            // Проверяем, является ли текущий пользователь супер-админом
            // Если да, пропускаем оплату
            $currentUser = Auth::user();
            if ($currentUser && method_exists($currentUser, 'hasPermissionTo')) {
                if ($currentUser->hasPermissionTo(Permission::SUPER_ADMIN)) {
                    Log::info('PaymentService::payForProduct - Супер-админ, пропускаем оплату', [
                        'product_id' => $product->id,
                        'user_id' => $currentUser->id,
                    ]);
                    
                    // Устанавливаем дату окончания оплаченного периода без списания средств
                    $paidUntil = Carbon::now()->addDays(self::PAYMENT_PERIOD_DAYS);
                    $product->paid_until = $paidUntil;
                    $product->payment_amount = 0; // Для супер-админа оплата = 0
                    $product->payment_date = Carbon::now();
                    $product->save();
                    
                    return [
                        'success' => true,
                        'message' => 'Оплата пропущена для супер-админа',
                        'paid_until' => $paidUntil,
                        'is_renewal' => false
                    ];
                }
            }
            
            // Получаем shop и owner_id (seller_id)
            $shop = Shop::find($product->shop_id);
            if (!$shop) {
                return [
                    'success' => false,
                    'message' => 'Магазин не найден',
                    'paid_until' => null,
                    'is_renewal' => false
                ];
            }

            $sellerId = $shop->owner_id;
            if (!$sellerId) {
                return [
                    'success' => false,
                    'message' => 'Владелец магазина не найден',
                    'paid_until' => null,
                    'is_renewal' => false
                ];
            }

            // Определяем, это новый товар или продление периода
            $isRenewal = false;
            $periodExpired = false;
            $oldPaidUntil = null;
            
            if ($product->paid_until) {
                $oldPaidUntil = Carbon::parse($product->paid_until);
                $periodExpired = $oldPaidUntil->isPast() || $oldPaidUntil->isToday();
                $isRenewal = $periodExpired;
            }

            // Получаем или создаем баланс продавца
            $balance = SellerBalance::getOrCreate($sellerId);

            // Проверяем, достаточно ли средств
            if (!$balance->hasEnough(self::PRODUCT_PLACEMENT_COST)) {
                Log::warning('PaymentService::payForProduct - Недостаточно средств', [
                    'product_id' => $product->id,
                    'seller_id' => $sellerId,
                    'required' => self::PRODUCT_PLACEMENT_COST,
                    'available' => $balance->balance,
                    'is_renewal' => $isRenewal
                ]);

                // Устанавливаем статус черновик
                $product->status = 'draft';
                $product->save();

                $message = $isRenewal 
                    ? 'Недостаточно средств на балансе для продления периода. Товар переведен в черновик.'
                    : 'Недостаточно средств на балансе. Товар переведен в черновик.';

                return [
                    'success' => false,
                    'message' => $message,
                    'paid_until' => $product->paid_until,
                    'is_renewal' => $isRenewal
                ];
            }

            // Списываем средства
            if (!$balance->withdraw(self::PRODUCT_PLACEMENT_COST)) {
                return [
                    'success' => false,
                    'message' => 'Ошибка при списании средств',
                    'paid_until' => $product->paid_until,
                    'is_renewal' => $isRenewal
                ];
            }

            // Устанавливаем дату окончания оплаченного периода
            $newPaidUntil = Carbon::now()->addDays(self::PAYMENT_PERIOD_DAYS);

            // Обновляем товар
            $product->paid_until = $newPaidUntil;
            $product->payment_amount = self::PRODUCT_PLACEMENT_COST;
            $product->payment_date = Carbon::now();
            $product->save();

            $logMessage = $isRenewal 
                ? 'PaymentService::payForProduct - Период продлен'
                : 'PaymentService::payForProduct - Оплата успешна';

            Log::info($logMessage, [
                'product_id' => $product->id,
                'seller_id' => $sellerId,
                'amount' => self::PRODUCT_PLACEMENT_COST,
                'paid_until' => $newPaidUntil->toDateString(),
                'balance_after' => $balance->balance,
                'is_renewal' => $isRenewal,
                'old_paid_until' => $isRenewal && $oldPaidUntil ? $oldPaidUntil->toDateString() : null
            ]);

            $message = $isRenewal 
                ? 'Период оплаты продлен на 180 дней'
                : 'Оплата успешно списана';

            return [
                'success' => true,
                'message' => $message,
                'paid_until' => $newPaidUntil,
                'is_renewal' => $isRenewal
            ];

        } catch (\Exception $e) {
            Log::error('PaymentService::payForProduct - Ошибка', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка при обработке оплаты: ' . $e->getMessage(),
                'paid_until' => null,
                'is_renewal' => false
            ];
        }
    }

    /**
     * Проверить и продлить период оплаты для товара
     * Если период истек, списать средства с баланса
     * 
     * @param Product $product
     * @return array ['success' => bool, 'message' => string, 'paid_until' => Carbon|null, 'status_changed' => bool]
     */
    public function checkAndRenewProductPayment(Product $product): array
    {
        try {
            // Если товар уже в архиве (deleted_at), не проверяем
            if ($product->trashed()) {
                return [
                    'success' => true,
                    'message' => 'Товар в архиве, проверка не требуется',
                    'paid_until' => $product->paid_until,
                    'status_changed' => false
                ];
            }

            // Если нет даты оплаты, значит товар еще не оплачивался
            if (!$product->paid_until) {
                return [
                    'success' => true,
                    'message' => 'Товар еще не оплачивался',
                    'paid_until' => null,
                    'status_changed' => false
                ];
            }

            $now = Carbon::now();
            $paidUntil = Carbon::parse($product->paid_until);

            // Если период еще не истек, ничего не делаем
            if ($paidUntil->isFuture()) {
                return [
                    'success' => true,
                    'message' => 'Период оплаты еще не истек',
                    'paid_until' => $paidUntil,
                    'status_changed' => false
                ];
            }

            // Период истек, нужно продлить
            Log::info('PaymentService::checkAndRenewProductPayment - Период истек, продлеваем', [
                'product_id' => $product->id,
                'paid_until' => $paidUntil->toDateString(),
                'days_expired' => $now->diffInDays($paidUntil)
            ]);

            // Получаем shop и owner_id (seller_id)
            $shop = Shop::find($product->shop_id);
            if (!$shop) {
                $product->status = 'draft';
                $product->save();
                return [
                    'success' => false,
                    'message' => 'Магазин не найден, товар переведен в черновик',
                    'paid_until' => $paidUntil,
                    'status_changed' => true
                ];
            }

            $sellerId = $shop->owner_id;
            $balance = SellerBalance::getOrCreate($sellerId);

            // Проверяем баланс
            if (!$balance->hasEnough(self::PRODUCT_PLACEMENT_COST)) {
                $product->status = 'draft';
                $product->save();

                Log::warning('PaymentService::checkAndRenewProductPayment - Недостаточно средств', [
                    'product_id' => $product->id,
                    'seller_id' => $sellerId,
                    'required' => self::PRODUCT_PLACEMENT_COST,
                    'available' => $balance->balance
                ]);

                return [
                    'success' => false,
                    'message' => 'Недостаточно средств на балансе. Товар переведен в черновик.',
                    'paid_until' => $paidUntil,
                    'status_changed' => true
                ];
            }

            // Списываем средства
            if (!$balance->withdraw(self::PRODUCT_PLACEMENT_COST)) {
                $product->status = 'draft';
                $product->save();
                return [
                    'success' => false,
                    'message' => 'Ошибка при списании средств, товар переведен в черновик',
                    'paid_until' => $paidUntil,
                    'status_changed' => true
                ];
            }

            // Продлеваем период
            $newPaidUntil = Carbon::now()->addDays(self::PAYMENT_PERIOD_DAYS);
            $product->paid_until = $newPaidUntil;
            $product->payment_amount = self::PRODUCT_PLACEMENT_COST;
            $product->payment_date = Carbon::now();
            $product->save();

            Log::info('PaymentService::checkAndRenewProductPayment - Период продлен', [
                'product_id' => $product->id,
                'seller_id' => $sellerId,
                'amount' => self::PRODUCT_PLACEMENT_COST,
                'paid_until' => $newPaidUntil->toDateString(),
                'balance_after' => $balance->balance
            ]);

            return [
                'success' => true,
                'message' => 'Период оплаты продлен',
                'paid_until' => $newPaidUntil,
                'status_changed' => false
            ];

        } catch (\Exception $e) {
            Log::error('PaymentService::checkAndRenewProductPayment - Ошибка', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Ошибка при проверке оплаты: ' . $e->getMessage(),
                'paid_until' => $product->paid_until ?? null,
                'status_changed' => false
            ];
        }
    }

}

