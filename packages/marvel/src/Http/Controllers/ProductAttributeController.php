<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductAttributeController extends CoreController
{
    /**
     * Get attributes for specific product
     */
    public function getProductAttributes(Request $request, $productId): JsonResponse
    {
        try {
            // Находим товар без связей сначала
            $product = Product::findOrFail($productId);
            
            // Загружаем атрибуты товара безопасно
            try {
                $product->load([
                    'attributes' => function($query) {
                        $query->orderBy('id', 'ASC');
                    }
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading product attributes in getProductAttributes', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                // Продолжаем без атрибутов
            }
            
            // Загружаем категории с их атрибутами безопасно
            try {
                $product->load([
                    'categories' => function($query) {
                        $query->with(['attributes' => function($attrQuery) {
                            $attrQuery->orderBy('category_attribute.sort_order', 'ASC')
                                      ->orderBy('attributes.id', 'ASC');
                        }]);
                    }
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading categories with attributes in getProductAttributes', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                // Продолжаем без категорий
            }

            // Получаем атрибуты категории товара
            $categoryAttributes = collect();
            if ($product->categories) {
                foreach ($product->categories as $category) {
                    if ($category->attributes) {
                        $categoryAttributes = $categoryAttributes->merge($category->attributes);
                    }
                }
            }
            $categoryAttributes = $categoryAttributes->unique('id');

            // Получаем значения атрибутов безопасно
            $attributeValues = [];
            try {
                $attributeValues = $product->getAttributeValuesArray();
            } catch (\Exception $e) {
                \Log::error('Error getting attribute values array in getProductAttributes', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
            }

            // Исправляем значения атрибутов: заменяем "NaN" на null
            $productAttributes = collect($product->attributes ?? [])->map(function ($attribute) {
                $pivotValue = $attribute->pivot->value ?? null;
                
                // Если значение "NaN" или пустое - заменяем на null
                if ($pivotValue === 'NaN' || $pivotValue === '' || (is_string($pivotValue) && strtolower($pivotValue) === 'nan')) {
                    $attribute->pivot->value = null;
                }
                
                return $attribute;
            })->values();

            // Логируем для отладки
            \Log::info('ProductAttributeController::getProductAttributes response', [
                'product_id' => $productId,
                'attributes_count' => $productAttributes->count(),
                'attribute_values_count' => count($attributeValues),
                'sample_attribute' => $productAttributes->first() ? [
                    'id' => $productAttributes->first()->id,
                    'name' => $productAttributes->first()->name,
                    'pivot_value' => $productAttributes->first()->pivot->value ?? 'NULL',
                ] : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'product_attributes' => $productAttributes->toArray(),
                    'category_attributes' => $categoryAttributes->toArray(),
                    'attribute_values' => $attributeValues,
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден'
            ], 404);
        } catch (Exception $e) {
            \Log::error('Error in getProductAttributes', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении атрибутов товара'
            ], 500);
        }
    }

    /**
     * Set attribute value for product
     */
    public function setProductAttributeValue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'attribute_id' => 'required|exists:attributes,id',
                'value' => 'required|string|max:1000',
                'attribute_value_id' => 'nullable|exists:attribute_values,id',
            ]);

            $product = Product::findOrFail($request->product_id);
            $attribute = Attribute::findOrFail($request->attribute_id);

            // Проверяем, что атрибут разрешен для категории товара
            $isAllowed = false;
            foreach ($product->categories as $category) {
                if ($category->attributes()->where('attribute_id', $request->attribute_id)->exists()) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Атрибут не разрешен для категории этого товара'
                ], 400);
            }

            // Валидация значения в зависимости от типа атрибута
            $this->validateAttributeValue($attribute, $request->value);

            $product->setAttributeValue(
                $request->attribute_id,
                $request->value,
                $request->attribute_value_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Значение атрибута установлено',
                'data' => [
                    'attribute_id' => $request->attribute_id,
                    'value' => $request->value,
                    'attribute_value_id' => $request->attribute_value_id,
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Товар или атрибут не найдены'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при установке значения атрибута: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update multiple attribute values for product
     */
    public function updateProductAttributes(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'attributes' => 'required|array',
                'attributes.*.attribute_id' => 'required|exists:attributes,id',
                'attributes.*.value' => 'required|string|max:1000',
                'attributes.*.attribute_value_id' => 'nullable|exists:attribute_values,id',
            ]);

            $product = Product::findOrFail($request->product_id);
            $attributesData = [];

            foreach ($request->attributes as $attrData) {
                $attribute = Attribute::findOrFail($attrData['attribute_id']);
                
                // Проверяем, что атрибут разрешен для категории товара
                $isAllowed = false;
                foreach ($product->categories as $category) {
                    if ($category->attributes()->where('attribute_id', $attrData['attribute_id'])->exists()) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    continue; // Пропускаем неразрешенные атрибуты
                }

                // Валидация значения
                $this->validateAttributeValue($attribute, $attrData['value']);

                $attributesData[$attrData['attribute_id']] = [
                    'value' => $attrData['value'],
                    'attribute_value_id' => $attrData['attribute_value_id'] ?? null,
                ];
            }

            $product->attributes()->sync($attributesData);

            return response()->json([
                'success' => true,
                'message' => 'Атрибуты товара обновлены',
                'data' => $attributesData
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении атрибутов товара: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove attribute value from product
     */
    public function removeProductAttribute(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'attribute_id' => 'required|exists:attributes,id',
            ]);

            $product = Product::findOrFail($request->product_id);
            $product->attributes()->detach($request->attribute_id);

            return response()->json([
                'success' => true,
                'message' => 'Атрибут удален из товара'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Товар или атрибут не найдены'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении атрибута из товара'
            ], 500);
        }
    }

    /**
     * Filter products by attribute values
     */
    public function filterProductsByAttributes(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'attributes' => 'required|array',
                'attributes.*.attribute_id' => 'required|exists:attributes,id',
                'attributes.*.value' => 'required|string',
                'category_id' => 'nullable|exists:categories,id',
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
            ]);

            $query = Product::query();

            // Фильтр по категории
            if ($request->category_id) {
                $query->whereHas('categories', function($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                });
            }

            // Фильтр по атрибутам
            foreach ($request->attributes as $attrFilter) {
                $query->whereHas('attributes', function($q) use ($attrFilter) {
                    $q->where('attribute_id', $attrFilter['attribute_id'])
                      ->where('value', 'like', '%' . $attrFilter['value'] . '%');
                });
            }

            $page = $request->page ?? 1;
            $perPage = $request->per_page ?? 20;
            
            $products = $query->with(['attributes', 'categories'])
                             ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при фильтрации товаров: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate attribute value based on attribute type
     */
    private function validateAttributeValue(Attribute $attribute, string $value): void
    {
        switch ($attribute->input_type) {
            case 'number':
                if (!is_numeric($value)) {
                    throw new Exception('Значение должно быть числом');
                }
                if ($attribute->min_value && $value < $attribute->min_value) {
                    throw new Exception('Значение не может быть меньше ' . $attribute->min_value);
                }
                if ($attribute->max_value && $value > $attribute->max_value) {
                    throw new Exception('Значение не может быть больше ' . $attribute->max_value);
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Некорректный email адрес');
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new Exception('Некорректный URL');
                }
                break;
                
            case 'boolean':
                if (!in_array(strtolower($value), ['true', 'false', '1', '0', 'да', 'нет'])) {
                    throw new Exception('Значение должно быть true/false');
                }
                break;
        }

        // Проверка регулярного выражения
        if ($attribute->validation_regex && !preg_match($attribute->validation_regex, $value)) {
            throw new Exception('Значение не соответствует требованиям');
        }
    }
}















