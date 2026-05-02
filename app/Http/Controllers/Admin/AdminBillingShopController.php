<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Product;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class AdminBillingShopController extends Controller
{
    /**
     * Получить список магазинов с данными биллинга
     */
    public function index(Request $request)
    {
        try {
            $query = Shop::with('owner:id,name,email');

            // Фильтр по статусу
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            $perPage = $request->get('per_page', 15);
            $shops = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Добавляем данные биллинга для каждого магазина
            $shops->getCollection()->transform(function ($shop) {
                $owner = $shop->owner;
                
                // Подсчитываем активные товары
                $activeProducts = Product::where('shop_id', $shop->id)
                    ->where('status', 'publish')
                    ->count();

                // Получаем текущий счёт (pending или overdue)
                $currentInvoice = Invoice::where('seller_id', $owner->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Получаем последний оплаченный счёт
                $lastPaidInvoice = Invoice::where('seller_id', $owner->id)
                    ->where('status', 'paid')
                    ->orderBy('paid_at', 'desc')
                    ->first();

                // Подсчитываем неоплаченные счета
                $unpaidInvoices = Invoice::where('seller_id', $owner->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->count();

                // Подсчитываем предстоящий платеж (сумма всех pending счетов)
                $upcomingPayment = Invoice::where('seller_id', $owner->id)
                    ->where('status', 'pending')
                    ->sum('total_amount');

                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'logo' => $shop->logo,
                    'owner' => [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ],
                    'is_active' => $shop->is_active,
                    'billing' => [
                        'active_products' => $activeProducts,
                        'current_invoice' => $currentInvoice ? [
                            'id' => $currentInvoice->id,
                            'period_start' => $currentInvoice->period_start->format('Y-m-d'),
                            'period_end' => $currentInvoice->period_end->format('Y-m-d'),
                            'total_products' => $currentInvoice->total_products,
                            'total_amount' => (float) $currentInvoice->total_amount,
                            'status' => $currentInvoice->status,
                            'created_at' => $currentInvoice->created_at->format('Y-m-d H:i:s'),
                        ] : null,
                        'last_paid_invoice' => $lastPaidInvoice ? [
                            'id' => $lastPaidInvoice->id,
                            'paid_at' => $lastPaidInvoice->paid_at->format('Y-m-d H:i:s'),
                            'total_amount' => (float) $lastPaidInvoice->total_amount,
                        ] : null,
                        'unpaid_invoices_count' => $unpaidInvoices,
                        'upcoming_payment' => (float) $upcomingPayment,
                    ],
                    'created_at' => $shop->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $shops
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingShopController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении магазинов с биллингом'
            ], 500);
        }
    }
}



