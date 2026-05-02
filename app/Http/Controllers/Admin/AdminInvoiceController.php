<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\BillingSettings;
use Marvel\Database\Models\User;
use Illuminate\Support\Facades\Log;

class AdminInvoiceController extends Controller
{
    /**
     * Получить список всех счетов
     */
    public function index(Request $request)
    {
        try {
            $query = Invoice::with('seller:id,name,email');

            // Фильтр по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтр по продавцу
            if ($request->has('seller_id') && $request->seller_id) {
                $query->where('seller_id', $request->seller_id);
            }

            // Фильтр по периоду
            if ($request->has('period_start') && $request->period_start) {
                $query->where('period_start', '>=', $request->period_start);
            }
            if ($request->has('period_end') && $request->period_end) {
                $query->where('period_end', '<=', $request->period_end);
            }

            $perPage = $request->get('per_page', 15);
            $invoices = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('AdminInvoiceController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении счетов'
            ], 500);
        }
    }

    /**
     * Просмотр счёта
     */
    public function show(Invoice $invoice)
    {
        try {
            $invoice->load('seller');
            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('AdminInvoiceController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении счёта'
            ], 500);
        }
    }

    /**
     * Изменить сумму счёта
     */
    public function update(Request $request, Invoice $invoice)
    {
        try {
            $validated = $request->validate([
                'total_amount' => 'sometimes|numeric|min:0',
                'price_per_product' => 'sometimes|numeric|min:0',
            ]);

            if (isset($validated['total_amount'])) {
                $invoice->total_amount = $validated['total_amount'];
            }

            if (isset($validated['price_per_product'])) {
                $invoice->price_per_product = $validated['price_per_product'];
            }

            $invoice->save();

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('AdminInvoiceController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении счёта'
            ], 500);
        }
    }

    /**
     * Отметить счёт как оплаченный
     */
    public function markPaid(Request $request, Invoice $invoice)
    {
        try {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('AdminInvoiceController@markPaid: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении счёта'
            ], 500);
        }
    }

    /**
     * Создать счёт вручную
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'seller_id' => 'required|exists:users,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start',
                'total_products' => 'required|integer|min:0',
                'price_per_product' => 'sometimes|numeric|min:0',
            ]);

            // Рассчитываем сумму по новому тарифу: 200 руб за первые 200 товаров, 0.5 руб за каждый последующий
            $totalAmount = Invoice::calculateTariffAmount($validated['total_products']);
            
            // Для совместимости сохраняем среднюю цену за товар
            $averagePricePerProduct = $validated['total_products'] > 0 ? $totalAmount / $validated['total_products'] : 0;

            $invoice = Invoice::create([
                'seller_id' => $validated['seller_id'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'total_products' => $validated['total_products'],
                'price_per_product' => $averagePricePerProduct,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            $invoice->load('seller');

            return response()->json([
                'success' => true,
                'data' => $invoice
            ], 201);
        } catch (\Exception $e) {
            Log::error('AdminInvoiceController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании счёта: ' . $e->getMessage()
            ], 500);
        }
    }
}





