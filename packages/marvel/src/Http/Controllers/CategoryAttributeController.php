<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Attribute;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryAttributeController extends CoreController
{
    /**
     * Get attributes for specific category
     */
    public function getCategoryAttributes(Request $request, $categoryId): JsonResponse
    {
        try {
            // Проверяем, есть ли поле is_active в таблице attributes
            $hasIsActiveColumn = Schema::hasColumn('attributes', 'is_active');
            
            // Загружаем категорию с атрибутами и их значениями, сортируя по sort_order
            $category = Category::with([
                'attributes' => function($query) use ($hasIsActiveColumn) {
                    if ($hasIsActiveColumn) {
                        // Если поле есть - фильтруем только активные атрибуты
                        $query->where(function($q) {
                            $q->where('is_active', true)
                              ->orWhere('is_active', 1)
                              ->orWhereNull('is_active'); // Если NULL - считаем активным (по умолчанию)
                        });
                    }
                    
                    $query->orderByPivot('sort_order');
                },
                'attributes.values' => function($query) {
                    // Загружаем значения атрибутов, отсортированные по значению
                    $query->orderBy('value');
                }
            ])->findOrFail($categoryId);

            // Дополнительная фильтрация на уровне коллекции (если поле is_active существует)
            $attributes = $category->attributes;
            
            if ($hasIsActiveColumn) {
                $attributes = $attributes->filter(function($attribute) {
                    // Активен если: true, 1, или NULL (по умолчанию)
                    $isActive = $attribute->is_active;
                    return $isActive === true || $isActive === 1 || $isActive === null || $isActive === '1';
                });
            }

            // Убеждаемся, что values загружены для каждого атрибута
            $attributes->load('values');
            
            // Преобразуем в массив и убеждаемся, что values включены
            $attributesArray = $attributes->map(function($attribute) {
                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'type' => $attribute->type,
                    'input_type' => $attribute->input_type,
                    'display_type' => $attribute->display_type,
                    'is_required' => $attribute->is_required,
                    'is_active' => $attribute->is_active,
                    'description' => $attribute->description,
                    'unit' => $attribute->unit,
                    'min_value' => $attribute->min_value ?? null,
                    'max_value' => $attribute->max_value ?? null,
                    'sort_order' => $attribute->sort_order,
                    'pivot' => $attribute->pivot ? [
                        'is_required' => $attribute->pivot->is_required,
                        'sort_order' => $attribute->pivot->sort_order,
                    ] : null,
                    'values' => $attribute->values ? $attribute->values->map(function($value) {
                        return [
                            'id' => $value->id,
                            'value' => $value->value,
                            'meta' => $value->meta,
                            'attribute_id' => $value->attribute_id,
                        ];
                    })->values() : [],
                ];
            })->values();

            // Получаем required и optional атрибуты
            $requiredAttributes = $category->requiredAttributes();
            $optionalAttributes = $category->optionalAttributes();
            
            if ($hasIsActiveColumn) {
                $requiredAttributes = $requiredAttributes->where(function($q) {
                    $q->where('is_active', true)
                      ->orWhere('is_active', 1)
                      ->orWhereNull('is_active');
                });
                $optionalAttributes = $optionalAttributes->where(function($q) {
                    $q->where('is_active', true)
                      ->orWhere('is_active', 1)
                      ->orWhereNull('is_active');
                });
            }

            return response()->json([
                'success' => true,
                'data' => $attributesArray,
                'required_attributes' => $requiredAttributes->get(),
                'optional_attributes' => $optionalAttributes->get(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Категория не найдена'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении атрибутов категории: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Attach attribute to category
     */
    public function attachAttributeToCategory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category_id' => 'required|exists:categories,id',
                'attribute_id' => 'required|exists:attributes,id',
                'is_required' => 'boolean',
                'sort_order' => 'integer|min:0',
            ]);

            $category = Category::findOrFail($request->category_id);
            $attribute = Attribute::findOrFail($request->attribute_id);

            // Проверяем, не привязан ли уже атрибут к категории
            if ($category->attributes()->where('attribute_id', $request->attribute_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Атрибут уже привязан к этой категории'
                ], 400);
            }

            $category->attributes()->attach($request->attribute_id, [
                'is_required' => $request->is_required ?? false,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Атрибут успешно привязан к категории',
                'data' => $category->attributes()->find($request->attribute_id)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Категория или атрибут не найдены'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при привязке атрибута к категории'
            ], 500);
        }
    }

    /**
     * Update attribute settings for category
     */
    public function updateCategoryAttribute(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category_id' => 'required|exists:categories,id',
                'attribute_id' => 'required|exists:attributes,id',
                'is_required' => 'boolean',
                'sort_order' => 'integer|min:0',
            ]);

            $category = Category::findOrFail($request->category_id);
            
            $category->attributes()->updateExistingPivot($request->attribute_id, [
                'is_required' => $request->is_required ?? false,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Настройки атрибута для категории обновлены'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Категория или атрибут не найдены'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении настроек атрибута'
            ], 500);
        }
    }

    /**
     * Detach attribute from category
     */
    public function detachAttributeFromCategory(Request $request): JsonResponse
    {
        try {
            // Получаем данные из тела запроса или из query параметров
            $categoryId = $request->input('category_id') ?? $request->query('category_id');
            $attributeId = $request->input('attribute_id') ?? $request->query('attribute_id');
            
            // Валидация
            $validator = \Validator::make([
                'category_id' => $categoryId,
                'attribute_id' => $attributeId,
            ], [
                'category_id' => 'required|exists:categories,id',
                'attribute_id' => 'required|exists:attributes,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = Category::findOrFail($categoryId);
            $category->attributes()->detach($attributeId);

            return response()->json([
                'success' => true,
                'message' => 'Атрибут отвязан от категории'
            ]);
        } catch (ModelNotFoundException $e) {
            \Log::error('CategoryAttributeController::detachAttributeFromCategory - Model not found', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Категория или атрибут не найдены'
            ], 404);
        } catch (Exception $e) {
            \Log::error('CategoryAttributeController::detachAttributeFromCategory - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отвязке атрибута от категории: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories for specific attribute
     */
    public function getAttributeCategories(Request $request, $attributeId): JsonResponse
    {
        try {
            $attribute = Attribute::with(['categories' => function($query) {
                $query->orderByPivot('sort_order');
            }])->findOrFail($attributeId);

            return response()->json([
                'success' => true,
                'data' => $attribute->categories
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Атрибут не найден'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении категорий атрибута'
            ], 500);
        }
    }
}







