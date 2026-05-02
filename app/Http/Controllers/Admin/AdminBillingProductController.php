<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\Log;

class AdminBillingProductController extends Controller
{
    /**
     * Получить список товаров с фильтрами
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with(['shop:id,name,owner_id', 'shop.owner:id,name,email']);

            // Фильтр по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтр по продавцу
            if ($request->has('seller_id') && $request->seller_id) {
                $query->whereHas('shop', function ($q) use ($request) {
                    $q->where('owner_id', $request->seller_id);
                });
            }

            $perPage = $request->get('per_page', 15);
            $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingProductController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении товаров'
            ], 500);
        }
    }

    /**
     * Изменить статус товара
     */
    public function updateStatus(Request $request, Product $product)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:publish,unpublish,draft,under_review,approved,rejected'
            ]);

            $product->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingProductController@updateStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении статуса товара'
            ], 500);
        }
    }
}





