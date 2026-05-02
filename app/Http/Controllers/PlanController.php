<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    /**
     * Получить все тарифы в JSON
     * GET /api/plans
     */
    public function index()
    {
        try {
            $plans = Plan::all();

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('PlanController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при получении тарифов'
            ], 500);
        }
    }

    /**
     * Получить конкретный тариф
     * GET /api/plans/{id}
     */
    public function show($id)
    {
        try {
            $plan = Plan::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('PlanController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Тариф не найден'
            ], 404);
        }
    }
}




