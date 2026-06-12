<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ProSubscription;
use App\Models\SellerBalance;
use App\Models\Invoice;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class ProSubscriptionController extends Controller
{
    /**
     * Получить информацию о текущей подписке PRO
     * GET /api/pro-subscription/status
     */
    public function status(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $subscription = ProSubscription::getActive($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'has_active' => $subscription !== null,
                    'subscription' => $subscription ? [
                        'id' => $subscription->id,
                        'start_date' => $subscription->start_date->format('Y-m-d'),
                        'end_date' => $subscription->end_date->format('Y-m-d'),
                        'days_remaining' => max(0, Carbon::now()->diffInDays($subscription->end_date, false)),
                        'status' => $subscription->status,
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('ProSubscriptionController@status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статуса подписки'
            ], 500);
        }
    }

    /**
     * Подключить подписку PRO
     * POST /api/pro-subscription/subscribe
     */
    public function subscribe(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $request->validate([
                'payment_method' => 'required|in:balance,yookassa',
            ]);

            // Проверяем активную подписку
            $activeSubscription = ProSubscription::getActive($user->id);
            
            if ($activeSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Подписка PRO уже активна до ' . $activeSubscription->end_date->format('d.m.Y')
                ], 400);
            }

            $amount = 249.00; // Новая цена подписки PRO: 249 руб
            $now = Carbon::now();
            $startDate = $now;
            $endDate = $now->copy()->addDays(30); // 30 дней подписки

            if ($request->payment_method === 'balance') {
                $balance = SellerBalance::getOrCreate($user->id);
                
                if (!$balance->hasEnough($amount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Недостаточно средств на балансе. Требуется: ' . number_format($amount, 2, '.', '') . ' ₽, доступно: ' . number_format($balance->balance, 2, '.', '') . ' ₽'
                    ], 400);
                }

                // Списываем с баланса
                $oldBalance = $balance->balance;
                if (!$balance->withdraw($amount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ошибка при списании средств с баланса'
                    ], 500);
                }

                // Создаем счет для учета
                $invoice = Invoice::create([
                    'seller_id' => $user->id,
                    'plan_id' => null, // Больше не используем plan_id
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'total_products' => 0,
                    'total_places' => 0,
                    'price_per_product' => 0,
                    'total_amount' => $amount,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Создаем подписку
                $subscription = ProSubscription::create([
                    'seller_id' => $user->id,
                    'amount' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'invoice_id' => $invoice->id,
                ]);

                Log::info('ProSubscriptionController@subscribe: Подписка PRO подключена через баланс', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $balance->balance,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Подписка PRO успешно подключена',
                    'data' => [
                        'subscription' => $subscription,
                        'balance' => [
                            'old' => (float) $oldBalance,
                            'new' => (float) $balance->balance,
                            'spent' => (float) $amount,
                        ],
                    ]
                ]);
            } else {
                // Оплата через YooKassa
                $shopId = config('services.yookassa.shop_id');
                $secretKey = config('services.yookassa.secret_key');
                $isTest = config('services.yookassa.is_test', false);

                if (empty($shopId) || empty($secretKey)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Платёжная система не настроена'
                    ], 500);
                }

                $config = new \App\Services\YooKassa\YooKassaConfig($shopId, $secretKey, $isTest);
                $service = new \App\Services\YooKassa\YooKassaService($config);

                $returnUrl = config('shop.dashboard_url') . '/dashboard/billing?subscription=success';
                $description = "Подписка PRO на 30 дней";

                // Формируем receipt для ЮKassa (54-ФЗ)
                $receipt = null;
                if (!$isTest) {
                    $receipt = [
                        'items' => [
                            [
                                'description' => 'Подписка PRO',
                                'quantity' => '1.00',
                                'amount' => [
                                    'value' => number_format($amount, 2, '.', ''),
                                    'currency' => 'RUB'
                                ],
                                'vat_code' => 1,
                                'payment_mode' => 'full_payment',
                                'payment_subject' => 'service'
                            ]
                        ]
                    ];

                    if (!empty($user->email)) {
                        $receipt['customer'] = ['email' => $user->email];
                    } else {
                        Log::error('ProSubscriptionController@subscribe: Нет email для receipt', [
                            'user_id' => $user->id
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Не удалось получить email для формирования чека. Обратитесь в поддержку.'
                        ], 500);
                    }
                }

                try {
                    $payment = $service->createPayment(
                        "pro_subscription_{$user->id}_" . time(),
                        (float) $amount,
                        $description,
                        $returnUrl,
                        $returnUrl . '?subscription=failed',
                        $receipt
                    );
                } catch (\Exception $e) {
                    Log::error('ProSubscriptionController@subscribe: Ошибка при создании платежа YooKassa', [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'error' => $e->getMessage(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Ошибка при создании платежа: ' . $e->getMessage()
                    ], 500);
                }

                // Создаем счет для оплаты
                $invoice = Invoice::create([
                    'seller_id' => $user->id,
                    'plan_id' => null,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'total_products' => 0,
                    'total_places' => 0,
                    'price_per_product' => 0,
                    'total_amount' => $amount,
                    'status' => 'pending',
                    'payment_id' => $payment['id'],
                ]);

                // Создаем подписку со статусом pending
                $subscription = ProSubscription::create([
                    'seller_id' => $user->id,
                    'amount' => $amount,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active', // Будет активирована после оплаты
                    'invoice_id' => $invoice->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Перейдите на страницу оплаты',
                    'payment_url' => $payment['payment_url'],
                    'payment_id' => $payment['id'],
                    'subscription_id' => $subscription->id,
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ProSubscriptionController@subscribe: Ошибка валидации', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors()))
            ], 400);
        } catch (\Exception $e) {
            Log::error('ProSubscriptionController@subscribe: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подключении подписки: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Публичная проверка подписки (для фронта)
     * GET /api/pro-subscription/check/{sellerId}
     */
    public function checkPublic($sellerId)
    {
        try {
            $hasActive = ProSubscription::hasActive((int) $sellerId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'has_active' => $hasActive,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('ProSubscriptionController@checkPublic: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке подписки'
            ], 500);
        }
    }
}

