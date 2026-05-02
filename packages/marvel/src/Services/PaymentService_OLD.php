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
    const PRODUCT_PLACEMENT_COST = 49.00;
    
    // Период оплаты (дней)
    const PAYMENT_PERIOD_DAYS = 180;

    /**
     * Оплатить размещение товара
     * 
     * @param Product $product
     * @return array ['success' => bool, 'message' => string, 'paid_until' => Carbon|null]
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
                        'paid_until' => $paidUntil
                    ];
                }
            }
            
            // Получаем shop и owner_id (seller_id)
            $shop = Shop::find($product->shop_id);
            if (!$shop) {
                return [
                    'success' => false,
                    'message' => 'Магазин не найден',
                    'paid_until' => null
                ];
            }

            $sellerId = $shop->owner_id;
            if (!$sellerId) {
                return [
                    'success' => false,
                    'message' => 'Владелец магазина не найден',
                    'paid_until' => null
                ];
            }

            // Получаем или создаем баланс продавца
            $balance = SellerBalance::getOrCreate($sellerId);

            // Проверяем, достаточно ли средств
            if (!$balance->hasEnough(self::PRODUCT_PLACEMENT_COST)) {
                Log::warning('PaymentService::payForProduct - Недостаточно средств', [
                    'product_id' => $product->id,
                    'seller_id' => $sellerId,
                    'required' => self::PRODUCT_PLACEMENT_COST,
                    'available' => $balance->balance
                ]);

                // Устанавливаем статус черновик
                $product->status = 'draft';
                $product->save();

                return [
                    'success' => false,
                    'message' => 'Недостаточно средств на балансе. Товар переведен в черновик.',
                    'paid_until' => null
                ];
            }

            // Списываем средства
            if (!$balance->withdraw(self::PRODUCT_PLACEMENT_COST)) {
                return [
                    'success' => false,
                    'message' => 'Ошибка при списании средств',
                    'paid_until' => null
                ];
            }

            // Устанавливаем дату окончания оплаченного периода
            $paidUntil = Carbon::now()->addDays(self::PAYMENT_PERIOD_DAYS);

            // Обновляем товар
            $product->paid_until = $paidUntil;
            $product->payment_amount = self::PRODUCT_PLACEMENT_COST;
            $product->payment_date = Carbon::now();
            $product->save();

            Log::info('PaymentService::payForProduct - Оплата успешна', [
                'product_id' => $product->id,
                'seller_id' => $sellerId,
                'amount' => self::PRODUCT_PLACEMENT_COST,
                'paid_until' => $paidUntil->toDateString(),
                'balance_after' => $balance->balance
            ]);

            return [
                'success' => true,
                'message' => 'Оплата успешно списана',
                'paid_until' => $paidUntil
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
                'paid_until' => null
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

