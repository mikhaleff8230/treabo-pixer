<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminBillingReportsController extends Controller
{
    /**
     * Получить отчёты биллинга
     */
    public function index(Request $request)
    {
        try {
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Общий доход за месяц
            $monthlyRevenue = Invoice::where('status', 'paid')
                ->whereMonth('paid_at', $month)
                ->whereYear('paid_at', $year)
                ->sum('total_amount');

            // Количество активных продавцов
            $activeSellers = User::whereHas('shops', function ($query) {
                $query->whereHas('products', function ($q) {
                    $q->where('status', 'publish');
                });
            })->count();

            // Количество неоплаченных счетов
            $unpaidInvoices = Invoice::whereIn('status', ['pending', 'overdue'])->count();

            // Просроченные счета
            $overdueInvoices = Invoice::where('status', 'overdue')->count();

            // Топ продавцов по оплатам
            $topSellers = Invoice::select('seller_id', DB::raw('SUM(total_amount) as total_paid'), DB::raw('COUNT(*) as invoices_count'))
                ->where('status', 'paid')
                ->whereMonth('paid_at', $month)
                ->whereYear('paid_at', $year)
                ->groupBy('seller_id')
                ->orderBy('total_paid', 'desc')
                ->limit(10)
                ->with('seller:id,name,email')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_revenue' => (float) $monthlyRevenue,
                    'active_sellers' => $activeSellers,
                    'unpaid_invoices' => $unpaidInvoices,
                    'overdue_invoices' => $overdueInvoices,
                    'top_sellers' => $topSellers,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingReportsController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении отчётов'
            ], 500);
        }
    }
}





