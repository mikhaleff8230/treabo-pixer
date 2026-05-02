<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillingPlan;
use Marvel\Database\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingPlanController extends Controller
{
    /**
     * Получить все активные тарифные планы (публичный endpoint)
     */
    public function index()
    {
        try {
            $plans = BillingPlan::getActivePlans();

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('BillingPlanController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении тарифных планов'
            ], 500);
        }
    }

    /**
     * Получить текущий тарифный план продавца
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

            $plan = $user->billingPlan ?? BillingPlan::getDefault();

            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('BillingPlanController@current: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении текущего тарифа'
            ], 500);
        }
    }

    /**
     * Выбрать тарифный план для продавца
     */
    public function select(Request $request)
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
                'plan_id' => 'required|exists:billing_plans,id'
            ]);

            $plan = BillingPlan::findOrFail($request->plan_id);

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Выбранный тарифный план неактивен'
                ], 400);
            }

            $user->billing_plan_id = $plan->id;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Тарифный план успешно выбран',
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('BillingPlanController@select: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выборе тарифного плана'
            ], 500);
        }
    }
}

