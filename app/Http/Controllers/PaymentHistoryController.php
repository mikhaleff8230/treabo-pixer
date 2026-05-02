<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\BalanceDeposit;
use App\Models\Invoice;
use App\Models\PlanSubscription;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission;

class PaymentHistoryController extends Controller
{
    /**
     * Получить историю платежей (только для супер-админа)
     * GET /api/admin/payment-history
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Проверяем, что пользователь - супер-админ
            if (!$user->hasPermissionTo(Permission::SUPER_ADMIN)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещён. Только для супер-админа.'
                ], 403);
            }

            // Получаем все успешно оплаченные платежи
            $payments = [];

            // Пополнения баланса - только успешно оплаченные
            $balanceDeposits = BalanceDeposit::where('status', 'succeeded')
                ->whereNotNull('payment_id')
                ->whereNotNull('paid_at')
                ->with(['seller.shops'])
                ->orderBy('paid_at', 'desc')
                ->get();

            foreach ($balanceDeposits as $deposit) {
                $seller = $deposit->seller;
                if (!$seller) {
                    continue;
                }
                
                $shop = $seller->shops->first();
                $ownerName = $seller->name ?: $seller->email;
                
                $payments[] = [
                    'id' => $deposit->id,
                    'type' => 'balance_deposit',
                    'owner_name' => $ownerName,
                    'shop_name' => $shop ? ($shop->name ?? '-') : '-',
                    'amount' => (float) $deposit->amount,
                    'date' => $deposit->paid_at ? $deposit->paid_at->format('Y-m-d H:i:s') : $deposit->created_at->format('Y-m-d H:i:s'),
                    'date_formatted' => $deposit->paid_at ? $deposit->paid_at->format('d.m.Y H:i') : $deposit->created_at->format('d.m.Y H:i'),
                ];
            }

            // Оплаченные счета
            $invoices = Invoice::where('status', 'paid')
                ->with(['seller.shops'])
                ->orderBy('paid_at', 'desc')
                ->get();

            foreach ($invoices as $invoice) {
                $seller = $invoice->seller;
                if (!$seller) {
                    continue;
                }
                
                $shop = $seller->shops->first();
                $ownerName = $seller->name ?: $seller->email;
                
                $payments[] = [
                    'id' => $invoice->id,
                    'type' => 'invoice',
                    'owner_name' => $ownerName,
                    'shop_name' => $shop ? ($shop->name ?? '-') : '-',
                    'amount' => (float) $invoice->total_amount,
                    'date' => $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : $invoice->created_at->format('Y-m-d H:i:s'),
                    'date_formatted' => $invoice->paid_at ? $invoice->paid_at->format('d.m.Y H:i') : $invoice->created_at->format('d.m.Y H:i'),
                ];
            }

            // Сортируем по дате (новые сверху)
            usort($payments, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            Log::error('PaymentHistoryController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории платежей'
            ], 500);
        }
    }
}

