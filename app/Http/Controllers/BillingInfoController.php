<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Plan;
use App\Models\Invoice;
use App\Models\PlanSubscription;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Place;
use Carbon\Carbon;

class BillingInfoController extends Controller
{
    /**
     * Получить информацию о текущем тарифе и расчет следующего платежа
     */
    public function current(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Для супер-админа можно указать seller_id
            $sellerId = $user->id;
            if ($user->hasPermissionTo(\Marvel\Enums\Permission::SUPER_ADMIN) && $request->has('seller_id')) {
                $sellerId = $request->input('seller_id');
                $seller = User::findOrFail($sellerId);
            } else {
                $seller = $user;
            }

            // Загружаем тарифный план
            $seller->load('plan');
            $plan = $seller->plan ?? Plan::getDefault();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Тарифный план не найден'
                ], 404);
            }

            // Подсчитываем активные товары
            $shops = $seller->shops;
            $totalActiveProducts = 0;
            
            foreach ($shops as $shop) {
                $activeCount = Product::where('shop_id', $shop->id)
                    ->where('status', 'publish')
                    ->count();
                $totalActiveProducts += $activeCount;
            }

            // Подсчитываем плейсы
            $totalPlaylists = Place::where('user_id', $seller->id)->count();

            // Определяем период следующего платежа
            $now = Carbon::now();
            $nextPeriodStart = $now->copy()->startOfMonth();
            $nextPeriodEnd = $now->copy()->endOfMonth();
            $nextPaymentDate = $nextPeriodStart->copy()->addMonth()->startOfMonth();

            // Рассчитываем стоимость следующего платежа
            $basePrice = $plan->price;
            
            // Дополнительные товары
            $extraProducts = 0;
            $extraProductsCost = 0;
            if ($plan->limit_products > 0 && $totalActiveProducts > $plan->limit_products) {
                $extraProducts = $totalActiveProducts - $plan->limit_products;
                $extraProductsCost = $extraProducts * ($plan->extra_product_price ?? 0);
            }

            // Дополнительные плейсы с расчетом по дням
            $extraPlaylists = 0;
            $extraPlaylistsCost = 0;
            $daysRemaining = 0;
            if ($plan->limit_playlists > 0 && $totalPlaylists > $plan->limit_playlists) {
                $extraPlaylists = $totalPlaylists - $plan->limit_playlists;
                
                // Расчет стоимости плейсов по дням до конца месяца
                $daysInMonth = $now->daysInMonth;
                $daysRemaining = $daysInMonth - $now->day + 1; // Оставшиеся дни включая сегодня
                
                // Стоимость за плейс в месяц
                $playlistPricePerMonth = $plan->extra_playlist_price ?? 0;
                
                // Стоимость за плейс в день
                $playlistPricePerDay = $playlistPricePerMonth / $daysInMonth;
                
                // Общая стоимость дополнительных плейсов за оставшиеся дни
                $extraPlaylistsCost = $extraPlaylists * $playlistPricePerDay * $daysRemaining;
            }

            $totalNextPayment = $basePrice + $extraProductsCost + $extraPlaylistsCost;

            // Получаем последний оплаченный счет для информации
            $lastPaidInvoice = Invoice::where('seller_id', $seller->id)
                ->where('status', 'paid')
                ->orderBy('paid_at', 'desc')
                ->first();

            // Проверяем, можно ли менять тариф (только один раз в месяц)
            $monthStart = $now->copy()->startOfMonth();
            $subscriptionsThisMonth = PlanSubscription::where('seller_id', $seller->id)
                ->where('created_at', '>=', $monthStart)
                ->count();
            
            $canSwitchPlan = $subscriptionsThisMonth === 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => (float) $plan->price,
                        'limit_products' => $plan->limit_products,
                        'limit_playlists' => $plan->limit_playlists,
                        'extra_product_price' => $plan->extra_product_price ? (float) $plan->extra_product_price : null,
                        'extra_playlist_price' => $plan->extra_playlist_price ? (float) $plan->extra_playlist_price : null,
                        'link_ozon_wb' => (bool) $plan->link_ozon_wb,
                        'utm_tracking' => $plan->utm_tracking,
                        'chat_enabled' => $plan->chat_enabled,
                        'featured_collections' => $plan->featured_collections,
                    ],
                    'current_usage' => [
                        'total_products' => $totalActiveProducts,
                        'total_playlists' => $totalPlaylists,
                        'products_within_limit' => min($totalActiveProducts, $plan->limit_products > 0 ? $plan->limit_products : $totalActiveProducts),
                        'products_over_limit' => $extraProducts,
                        'playlists_within_limit' => min($totalPlaylists, $plan->limit_playlists > 0 ? $plan->limit_playlists : $totalPlaylists),
                        'playlists_over_limit' => $extraPlaylists,
                    ],
                    'next_payment' => [
                        'date' => $nextPaymentDate->format('Y-m-d'),
                        'date_formatted' => $nextPaymentDate->format('d.m.Y'),
                        'period_start' => $nextPeriodStart->format('Y-m-d'),
                        'period_end' => $nextPeriodEnd->format('Y-m-d'),
                        'base_price' => (float) $basePrice,
                        'extra_products_cost' => round($extraProductsCost, 2),
                        'extra_playlists_cost' => round($extraPlaylistsCost, 2),
                        'total_amount' => round($totalNextPayment, 2),
                        'breakdown' => [
                            'base_plan' => round($basePrice, 2),
                            'extra_products' => [
                                'count' => $extraProducts,
                                'price_per_item' => $plan->extra_product_price ? (float) $plan->extra_product_price : 0,
                                'total' => round($extraProductsCost, 2),
                            ],
                            'extra_playlists' => [
                                'count' => $extraPlaylists,
                                'price_per_item_per_month' => $plan->extra_playlist_price ? (float) $plan->extra_playlist_price : 0,
                                'days_remaining' => $daysRemaining,
                                'total' => round($extraPlaylistsCost, 2),
                            ],
                        ],
                    ],
                    'last_payment' => $lastPaidInvoice ? [
                        'date' => $lastPaidInvoice->paid_at->format('Y-m-d'),
                        'date_formatted' => $lastPaidInvoice->paid_at->format('d.m.Y'),
                        'amount' => (float) $lastPaidInvoice->total_amount,
                    ] : null,
                    'can_switch_plan' => $canSwitchPlan,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('BillingInfoController@current: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о тарифе'
            ], 500);
        }
    }
}

