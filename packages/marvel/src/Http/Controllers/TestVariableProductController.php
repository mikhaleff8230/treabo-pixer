<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Repositories\ProductRepository;
use Marvel\Http\Requests\ProductCreateRequest;

/**
 * Тестовый контроллер для отладки создания вариативных товаров
 * Используется для пошаговой проверки каждого этапа
 */
class TestVariableProductController extends CoreController
{
    public $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Тестовый endpoint для отладки создания вариативного товара
     * POST /api/test/variable-product
     */
    public function testCreateVariableProduct(Request $request)
    {
        try {
            Log::info('=== TEST: Начало создания вариативного товара ===');
            Log::info('TEST: Request method', ['method' => $request->method()]);
            Log::info('TEST: Request headers', ['headers' => $request->headers->all()]);
            Log::info('TEST: Content-Type', ['content_type' => $request->header('Content-Type')]);
            
            // Шаг 1: Проверка входных данных
            Log::info('=== ШАГ 1: Проверка входных данных ===');
            $allData = $request->all();
            Log::info('TEST: Все данные запроса', [
                'keys' => array_keys($allData),
                'product_type' => $request->input('product_type'),
                'has_variations' => $request->has('variations'),
                'has_variation_options' => $request->has('variation_options'),
                'variations' => $request->input('variations'),
                'variation_options' => $request->input('variation_options'),
            ]);

            // Шаг 2: Проверка FormData
            Log::info('=== ШАГ 2: Проверка FormData ===');
            if ($request->header('Content-Type') && str_contains($request->header('Content-Type'), 'multipart/form-data')) {
                Log::info('TEST: Это FormData запрос');
                // Проверяем, что JSON строки распарсены
                foreach ($allData as $key => $value) {
                    if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                        Log::info("TEST: Найдена JSON строка для ключа: {$key}", [
                            'value' => substr($value, 0, 200), // Первые 200 символов
                            'is_json' => json_decode($value) !== null,
                        ]);
                    }
                }
            }

            // Шаг 3: Создание ProductCreateRequest
            Log::info('=== ШАГ 3: Создание ProductCreateRequest ===');
            $createRequest = new ProductCreateRequest();
            $createRequest->merge($allData);
            
            // Вызываем prepareForValidation вручную
            $reflection = new \ReflectionClass($createRequest);
            $method = $reflection->getMethod('prepareForValidation');
            $method->setAccessible(true);
            $method->invoke($createRequest);
            
            Log::info('TEST: Данные после prepareForValidation', [
                'product_type' => $createRequest->input('product_type'),
                'variations' => $createRequest->input('variations'),
                'variation_options' => $createRequest->input('variation_options'),
            ]);

            // Шаг 4: Валидация
            Log::info('=== ШАГ 4: Валидация ===');
            try {
                $createRequest->validateResolved();
                Log::info('TEST: Валидация прошла успешно');
            } catch (\Illuminate\Validation\ValidationException $e) {
                Log::error('TEST: Ошибка валидации', [
                    'errors' => $e->errors(),
                ]);
                return response()->json([
                    'success' => false,
                    'step' => 'validation',
                    'errors' => $e->errors(),
                ], 422);
            }

            // Шаг 5: Проверка прав доступа
            Log::info('=== ШАГ 5: Проверка прав доступа ===');
            $user = $request->user();
            $shopId = $createRequest->input('shop_id');
            Log::info('TEST: Проверка прав', [
                'user_id' => $user?->id,
                'shop_id' => $shopId,
                'has_permission' => $this->repository->hasPermission($user, $shopId),
            ]);

            if (!$this->repository->hasPermission($user, $shopId)) {
                Log::error('TEST: Нет прав доступа');
                return response()->json([
                    'success' => false,
                    'step' => 'permission',
                    'message' => 'Нет прав доступа',
                ], 403);
            }

            // Шаг 6: Получение настроек
            Log::info('=== ШАГ 6: Получение настроек ===');
            $settings = app(\Marvel\Database\Repositories\SettingsRepository::class)->first();
            Log::info('TEST: Настройки получены', ['settings_id' => $settings?->id]);

            // Шаг 7: Вызов storeProduct
            Log::info('=== ШАГ 7: Вызов storeProduct ===');
            try {
                $product = $this->repository->storeProduct($createRequest, $settings);
                Log::info('TEST: Товар создан успешно', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_type' => $product->product_type,
                ]);

                // Шаг 8: Проверка вариаций
                Log::info('=== ШАГ 8: Проверка вариаций ===');
                $product->load('variations', 'variation_options');
                Log::info('TEST: Вариации товара', [
                    'variations_count' => $product->variations->count(),
                    'variation_options_count' => $product->variation_options->count(),
                    'variations' => $product->variations->map(function($v) {
                        return [
                            'id' => $v->id,
                            'value' => $v->value,
                            'attribute' => $v->attribute?->name,
                        ];
                    }),
                    'variation_options' => $product->variation_options->map(function($vo) {
                        return [
                            'id' => $vo->id,
                            'title' => $vo->title,
                            'price' => $vo->price,
                            'quantity' => $vo->quantity,
                            'sku' => $vo->sku,
                            'options' => $vo->options,
                        ];
                    }),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Вариативный товар создан успешно',
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_type' => $product->product_type,
                        'variations_count' => $product->variations->count(),
                        'variation_options_count' => $product->variation_options->count(),
                    ],
                ], 201);

            } catch (\Exception $e) {
                Log::error('TEST: Ошибка при создании товара', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'success' => false,
                    'step' => 'storeProduct',
                    'error' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('TEST: Критическая ошибка', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'step' => 'critical',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Тестовый endpoint для проверки данных перед отправкой
     * GET /api/test/variable-product/check
     */
    public function checkData(Request $request)
    {
        Log::info('=== TEST: Проверка данных ===');
        return response()->json([
            'message' => 'Используйте POST /api/test/variable-product для тестирования',
            'example' => [
                'name' => 'Test Product',
                'product_type' => 'variable',
                'shop_id' => 1,
                'type_id' => 1,
                'variations' => [1, 2, 3],
                'variation_options' => [
                    'upsert' => [
                        [
                            'price' => '100',
                            'quantity' => 10,
                            'sku' => 'TEST-001',
                            'options' => '[{"name":"Color","value":"Red"}]',
                        ],
                    ],
                ],
            ],
        ]);
    }
}

