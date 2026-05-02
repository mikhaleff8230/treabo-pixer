<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AdditionalPurchase;
use App\Models\SellerBalance;
use App\Models\Plan;
use Marvel\Database\Models\User;
use Carbon\Carbon;

class AdditionalPurchaseController extends Controller
{
    /**
     * Купить дополнительные товары или плейсы
     * POST /api/additional-purchases
     */
    public function purchase(Request $request)
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
                'type' => 'required|in:product,playlist',
                'quantity' => 'required|integer|min:1',
                'payment_method' => 'required|in:balance,yookassa',
            ]);

            $user->load('plan');
            $plan = $user->plan;

            // Рассчитываем цену с учетом скидки по тарифу
            $pricing = AdditionalPurchase::calculatePriceWithDiscount($plan, $request->type, $request->quantity);
            
            $totalAmount = $pricing['total'];

            // Для плейсов определяем срок действия (до конца месяца)
            $validUntil = null;
            if ($request->type === 'playlist') {
                $validUntil = Carbon::now()->endOfMonth();
            }

            if ($request->payment_method === 'balance') {
                $balance = SellerBalance::getOrCreate($user->id);
                
                if (!$balance->hasEnough($totalAmount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Недостаточно средств на балансе. Требуется: ' . $totalAmount . ' ₽, доступно: ' . $balance->balance . ' ₽'
                    ], 400);
                }

                // Списываем с баланса
                $balance->withdraw($totalAmount);

                // Создаем покупку
                $purchase = AdditionalPurchase::create([
                    'seller_id' => $user->id,
                    'type' => $request->type,
                    'quantity' => $request->quantity,
                    'price_per_unit' => $pricing['price_per_unit'],
                    'total_amount' => $totalAmount,
                    'discount' => $pricing['discount'],
                    'plan_id' => $plan?->id,
                    'payment_method' => 'balance',
                    'status' => 'paid',
                    'valid_until' => $validUntil,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Покупка успешно совершена',
                    'data' => [
                        'purchase' => $purchase,
                        'balance' => $balance->balance,
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

                $returnUrl = config('app.frontend_url', 'https://sancan.ru') . '/dashboard/billing?purchase=success';
                $typeLabel = $request->type === 'product' ? 'товаров' : 'плейсов';
                $description = "Покупка {$request->quantity} дополнительных {$typeLabel}";

                $payment = $service->createPayment(
                    "additional_purchase_{$user->id}_" . time(),
                    $totalAmount,
                    $description,
                    $returnUrl,
                    $returnUrl . '?purchase=failed',
                    []
                );

                // Создаем покупку со статусом pending
                $purchase = AdditionalPurchase::create([
                    'seller_id' => $user->id,
                    'type' => $request->type,
                    'quantity' => $request->quantity,
                    'price_per_unit' => $pricing['price_per_unit'],
                    'total_amount' => $totalAmount,
                    'discount' => $pricing['discount'],
                    'plan_id' => $plan?->id,
                    'payment_method' => 'yookassa',
                    'payment_id' => $payment['id'],
                    'status' => 'pending',
                    'valid_until' => $validUntil,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Перейдите на страницу оплаты',
                    'payment_url' => $payment['payment_url'],
                    'payment_id' => $payment['id'],
                    'purchase_id' => $purchase->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AdditionalPurchaseController@purchase: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при покупке: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить список покупок продавца
     * GET /api/additional-purchases
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $purchases = AdditionalPurchase::where('seller_id', $user->id)
                ->where('status', 'paid')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $purchases
            ]);
        } catch (\Exception $e) {
            Log::error('AdditionalPurchaseController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении покупок'
            ], 500);
        }
    }
}

