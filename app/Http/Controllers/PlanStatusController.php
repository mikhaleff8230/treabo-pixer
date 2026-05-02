<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\User;
use App\Models\Invoice;
use Carbon\Carbon;

class PlanStatusController extends Controller
{
    /**
     * Проверить статус тарифа продавца
     * GET /api/plan/status
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

            // Для супер-админа можно указать seller_id
            $sellerId = $user->id;
            if ($user->hasPermissionTo(\Marvel\Enums\Permission::SUPER_ADMIN) && $request->has('seller_id')) {
                $sellerId = $request->input('seller_id');
                $seller = User::findOrFail($sellerId);
            } else {
                $seller = $user;
            }

            $seller->load('plan');

            $now = Carbon::now();
            $currentPeriodStart = $now->copy()->startOfMonth();
            $currentPeriodEnd = $now->copy()->endOfMonth();

            // Проверяем оплату за текущий период
            $currentInvoice = Invoice::where('seller_id', $seller->id)
                ->where('period_start', $currentPeriodStart)
                ->where('period_end', $currentPeriodEnd)
                ->first();

            $isPaid = $seller->isPlanPaidForCurrentPeriod();
            $isActive = $seller->isPlanActive();
            $features = $seller->getAvailablePlanFeatures();

            // Проверяем, есть ли просроченные счета
            $overdueInvoices = Invoice::where('seller_id', $seller->id)
                ->where('status', 'overdue')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'plan' => $seller->plan ? [
                        'id' => $seller->plan->id,
                        'name' => $seller->plan->name,
                    ] : null,
                    'current_period' => [
                        'start' => $currentPeriodStart->format('Y-m-d'),
                        'end' => $currentPeriodEnd->format('Y-m-d'),
                        'start_formatted' => $currentPeriodStart->format('d.m.Y'),
                        'end_formatted' => $currentPeriodEnd->format('d.m.Y'),
                    ],
                    'is_paid' => $isPaid,
                    'is_active' => $isActive,
                    'current_invoice' => $currentInvoice ? [
                        'id' => $currentInvoice->id,
                        'status' => $currentInvoice->status,
                        'amount' => (float) $currentInvoice->total_amount,
                        'created_at' => $currentInvoice->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                    'has_overdue' => $overdueInvoices > 0,
                    'features' => $features,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PlanStatusController@status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке статуса тарифа'
            ], 500);
        }
    }
}




