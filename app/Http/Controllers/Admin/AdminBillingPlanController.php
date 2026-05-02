<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BillingPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminBillingPlanController extends Controller
{
    /**
     * Получить список всех тарифных планов
     */
    public function index(Request $request)
    {
        try {
            $query = BillingPlan::query();

            // Фильтр по активности
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 15);
            $plans = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении тарифных планов'
            ], 500);
        }
    }

    /**
     * Получить все активные тарифные планы
     */
    public function active()
    {
        try {
            $plans = BillingPlan::getActivePlans();

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@active: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении активных тарифных планов'
            ], 500);
        }
    }

    /**
     * Получить тарифный план по ID
     */
    public function show($id)
    {
        try {
            $plan = BillingPlan::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Тарифный план не найден'
            ], 404);
        }
    }

    /**
     * Создать новый тарифный план
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:billing_plans,name',
                'display_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'monthly_price' => 'required|numeric|min:0',
                'product_limit' => 'required|integer|min:0',
                'place_limit' => 'required|integer|min:0',
                'extra_product_price' => 'required|numeric|min:0',
                'extra_place_price' => 'required|numeric|min:0',
                'photos_per_product' => 'required|integer|min:1',
                'has_shop' => 'boolean',
                'has_extended_shop' => 'boolean',
                'has_ozon_wb_link' => 'boolean',
                'has_utm_tags' => 'boolean',
                'analytics_level' => 'required|in:none,basic,advanced',
                'search_priority' => 'required|in:none,low,high',
                'featured_in_collections' => 'boolean',
                'support_level' => 'required|in:basic,standard,24/7',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plan = BillingPlan::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Тарифный план создан успешно',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании тарифного плана'
            ], 500);
        }
    }

    /**
     * Обновить тарифный план
     */
    public function update(Request $request, $id)
    {
        try {
            $plan = BillingPlan::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|unique:billing_plans,name,' . $id,
                'display_name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'monthly_price' => 'sometimes|numeric|min:0',
                'product_limit' => 'sometimes|integer|min:0',
                'place_limit' => 'sometimes|integer|min:0',
                'extra_product_price' => 'sometimes|numeric|min:0',
                'extra_place_price' => 'sometimes|numeric|min:0',
                'photos_per_product' => 'sometimes|integer|min:1',
                'has_shop' => 'boolean',
                'has_extended_shop' => 'boolean',
                'has_ozon_wb_link' => 'boolean',
                'has_utm_tags' => 'boolean',
                'analytics_level' => 'sometimes|in:none,basic,advanced',
                'search_priority' => 'sometimes|in:none,low,high',
                'featured_in_collections' => 'boolean',
                'support_level' => 'sometimes|in:basic,standard,24/7',
                'is_active' => 'boolean',
                'sort_order' => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plan->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Тарифный план обновлен успешно',
                'data' => $plan
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении тарифного плана'
            ], 500);
        }
    }

    /**
     * Удалить тарифный план
     */
    public function destroy($id)
    {
        try {
            $plan = BillingPlan::findOrFail($id);

            // Проверяем, используется ли тариф
            $usersCount = $plan->users()->count();
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Невозможно удалить тарифный план. Он используется {$usersCount} продавцами."
                ], 400);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Тарифный план удален успешно'
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBillingPlanController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении тарифного плана'
            ], 500);
        }
    }
}

