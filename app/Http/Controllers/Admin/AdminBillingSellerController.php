<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Product;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class AdminBillingSellerController extends Controller
{
    /**
     * Получить список продавцов
     */
    public function index(Request $request)
    {
        try {
            $query = User::whereHas('shops');

            // Фильтр по статусу
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            $perPage = $request->get('per_page', 15);
            $sellers = $query->withCount('shops')->paginate($perPage);

            // Добавляем дополнительную информацию для каждого продавца
            $sellers->getCollection()->transform(function ($seller) {
                $shops = $seller->shops;
                $totalActiveProducts = 0;
                $unpaidInvoices = Invoice::where('seller_id', $seller->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->count();
                
                $lastPayment = Invoice::where('seller_id', $seller->id)
                    ->where('status', 'paid')
                    ->orderBy('paid_at', 'desc')
                    ->first();

                foreach ($shops as $shop) {
                    $totalActiveProducts += Product::where('shop_id', $shop->id)
                        ->where('status', 'publish')
                        ->count();
                }

                return [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'email' => $seller->email,
                    'is_active' => $seller->is_active,
                    'active_products' => $totalActiveProducts,
                    'unpaid_invoices' => $unpaidInvoices,
                    'last_payment' => $lastPayment ? $lastPayment->paid_at : null,
                    'created_at' => $seller->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $sellers
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingSellerController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении продавцов'
            ], 500);
        }
    }

    /**
     * Просмотр продавца
     */
    public function show(User $seller)
    {
        try {
            $seller->load('shops');

            $shops = $seller->shops;
            $totalActiveProducts = 0;
            
            foreach ($shops as $shop) {
                $totalActiveProducts += Product::where('shop_id', $shop->id)
                    ->where('status', 'publish')
                    ->count();
            }

            $invoices = Invoice::where('seller_id', $seller->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'seller' => $seller,
                    'active_products' => $totalActiveProducts,
                    'invoices' => $invoices,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingSellerController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных продавца'
            ], 500);
        }
    }

    /**
     * Заблокировать/разблокировать продавца
     */
    public function toggleStatus(Request $request, User $seller)
    {
        try {
            $seller->update([
                'is_active' => !$seller->is_active
            ]);

            return response()->json([
                'success' => true,
                'data' => $seller
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingSellerController@toggleStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при изменении статуса продавца'
            ], 500);
        }
    }
}





