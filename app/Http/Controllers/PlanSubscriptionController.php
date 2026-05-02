<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SellerBalance;
use App\Models\Invoice;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class PlanSubscriptionController extends Controller
{
    /**
     * Подключить тарифный план
     * POST /api/plan/subscribe
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
                'plan_id' => 'required|exists:plans,id',
                'payment_method' => 'required|in:balance,yookassa',
            ]);

            $plan = Plan::findOrFail($request->plan_id);

            // Проверяем активную подписку
            $activeSubscription = PlanSubscription::getActive($user->id);
            
            // Если уже есть активная подписка на этот тариф
            if ($activeSubscription && $activeSubscription->plan_id === $plan->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Тариф уже подключен и активен'
                ], 400);
            }
            
            // Если есть активная подписка на другой тариф - помечаем как истекшую
            if ($activeSubscription && $activeSubscription->plan_id !== $plan->id) {
                $activeSubscription->update(['status' => 'expired']);
                Log::info('PlanSubscriptionController@subscribe: Старая подписка помечена как истекшая', [
                    'old_subscription_id' => $activeSubscription->id,
                    'old_plan_id' => $activeSubscription->plan_id,
                    'new_plan_id' => $plan->id
                ]);
            }

            if ($plan->name === 'Free') {
                // Для Free тарифа просто назначаем без оплаты
                $user->plan_id = $plan->id;
                $user->save();

                $now = Carbon::now();
                $endDate = $now->copy()->endOfMonth();

                $subscription = PlanSubscription::create([
                    'seller_id' => $user->id,
                    'plan_id' => $plan->id,
                    'start_date' => $now,
                    'end_date' => $endDate,
                    'amount' => 0,
                    'is_proportional' => false,
                    'status' => 'active',
                    'auto_renewal_enabled' => false, // Free не продлевается
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Тариф Free подключен',
                    'data' => $subscription
                ]);
            }

            $now = Carbon::now();
            $dayOfMonth = $now->day;
            $startDate = $now;
            $endDate = $now->copy()->endOfMonth();
            
            // Определяем стоимость
            $isProportional = $dayOfMonth > 5;
            $amount = 0;
            $daysPaid = 0;

            if ($isProportional) {
                // Пропорциональная оплата
                $daysInMonth = $now->daysInMonth;
                $daysRemaining = $daysInMonth - $dayOfMonth + 1;
                $amount = PlanSubscription::calculateProportionalPrice($plan, $startDate, $endDate);
                $daysPaid = $daysRemaining;
            } else {
                // Полная оплата за месяц
                $amount = $plan->price;
                $daysPaid = $now->daysInMonth;
            }

            // Проверяем способ оплаты
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
                    'plan_id' => $plan->id,
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
                $subscription = PlanSubscription::create([
                    'seller_id' => $user->id,
                    'plan_id' => $plan->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount' => $amount,
                    'is_proportional' => $isProportional,
                    'days_paid' => $daysPaid,
                    'status' => 'active',
                    'invoice_id' => $invoice->id,
                    'auto_renewal_at' => $endDate->copy()->addDay()->startOfDay(),
                    'auto_renewal_enabled' => true,
                ]);

                // Обновляем тариф пользователя
                $user->plan_id = $plan->id;
                $user->save();

                Log::info('PlanSubscriptionController@subscribe: Тариф подключен через баланс', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $balance->balance,
                    'is_proportional' => $isProportional
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Тариф подключен успешно',
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
                // Создаем подписку со статусом pending
                $subscription = PlanSubscription::create([
                    'seller_id' => $user->id,
                    'plan_id' => $plan->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount' => $amount,
                    'is_proportional' => $isProportional,
                    'days_paid' => $daysPaid,
                    'status' => 'active', // Будет активирована после оплаты
                    'auto_renewal_at' => $endDate->copy()->addDay()->startOfDay(),
                    'auto_renewal_enabled' => true,
                ]);

                // Создаем счет для оплаты
                $invoice = Invoice::create([
                    'seller_id' => $user->id,
                    'plan_id' => $plan->id,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'total_products' => 0,
                    'total_places' => 0,
                    'price_per_product' => 0,
                    'total_amount' => $amount,
                    'status' => 'pending',
                ]);

                $subscription->invoice_id = $invoice->id;
                $subscription->save();

                // Создаем платеж в YooKassa
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

                $returnUrl = config('app.frontend_url', 'https://sancan.ru') . '/dashboard/billing?payment=success';
                $description = "Оплата тарифа {$plan->name} за период {$startDate->format('d.m.Y')} - {$endDate->format('d.m.Y')}";

                // Формируем receipt для ЮKassa (54-ФЗ) - обязателен для боевого режима
                $receipt = null;
                if (!$isTest) {
                    $receipt = [
                        'items' => [
                            [
                                'description' => "Тариф {$plan->name}",
                                'quantity' => '1.00',
                                'amount' => [
                                    'value' => number_format($amount, 2, '.', ''),
                                    'currency' => 'RUB'
                                ],
                                'vat_code' => 1, // НДС не облагается
                                'payment_mode' => 'full_payment',
                                'payment_subject' => 'service' // Услуга
                            ]
                        ]
                    ];

                    // Добавляем данные клиента (email обязателен в боевом режиме)
                    if (!empty($user->email)) {
                        $receipt['customer'] = [
                            'email' => $user->email
                        ];
                    } else {
                        Log::error('PlanSubscriptionController@subscribe: Нет email для receipt', [
                            'user_id' => $user->id,
                            'plan_id' => $plan->id
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Не удалось получить email для формирования чека. Обратитесь в поддержку.'
                        ], 500);
                    }
                }

                try {
                    $payment = $service->createPayment(
                        "subscription_{$subscription->id}",
                        (float) $amount,
                        $description,
                        $returnUrl,
                        $returnUrl . '?payment=failed',
                        $receipt
                    );
                } catch (\Exception $e) {
                    Log::error('PlanSubscriptionController@subscribe: Ошибка при создании платежа YooKassa', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'amount' => $amount,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Ошибка при создании платежа: ' . $e->getMessage()
                    ], 500);
                }

                $invoice->payment_id = $payment['id'];
                $invoice->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Перейдите на страницу оплаты',
                    'payment_url' => $payment['payment_url'],
                    'payment_id' => $payment['id'],
                    'subscription_id' => $subscription->id,
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('PlanSubscriptionController@subscribe: Ошибка валидации', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors()))
            ], 400);
        } catch (\Exception $e) {
            Log::error('PlanSubscriptionController@subscribe: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'plan_id' => $request->plan_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подключении тарифа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить текущую подписку
     * GET /api/plan/subscription
     */
    public function current()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $subscription = PlanSubscription::getActive($user->id);
            $balance = SellerBalance::getOrCreate($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $user->plan,
                    'balance' => [
                        'amount' => (float) $balance->balance,
                        'total_deposited' => (float) $balance->total_deposited,
                        'total_spent' => (float) $balance->total_spent,
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PlanSubscriptionController@current: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении подписки'
            ], 500);
        }
    }
}




