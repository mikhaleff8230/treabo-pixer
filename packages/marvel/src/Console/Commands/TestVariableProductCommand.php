<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Repositories\ProductRepository;
use Marvel\Database\Repositories\SettingsRepository;
use Marvel\Http\Requests\ProductCreateRequest;
use Illuminate\Http\Request;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Type;
use Marvel\Database\Models\AttributeValue;

class TestVariableProductCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:variable-product 
                            {--shop-id=1 : ID магазина}
                            {--type-id=1 : ID типа товара}
                            {--user-id=1 : ID пользователя}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование создания вариативного товара напрямую через репозиторий';

    protected $repository;
    protected $settings;

    public function __construct(ProductRepository $repository, SettingsRepository $settings)
    {
        parent::__construct();
        $this->repository = $repository;
        $this->settings = $settings;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('========================================');
        $this->info('Тестирование создания вариативного товара');
        $this->info('========================================');
        $this->newLine();

        $shopId = (int)$this->option('shop-id');
        $typeId = (int)$this->option('type-id');
        $userId = (int)$this->option('user-id');

        // ЭТАП 1: Проверка существования магазина и типа
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 1: Проверка существования магазина и типа');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $shop = Shop::find($shopId);
        if (!$shop) {
            $this->error("❌ Магазин с ID $shopId не найден!");
            return 1;
        }
        $this->info("✅ Магазин найден: {$shop->name} (ID: $shopId)");

        $type = Type::find($typeId);
        if (!$type) {
            $this->warn("⚠️  Тип товара с ID $typeId не найден!");
            
            // Показываем доступные типы
            $availableTypes = Type::limit(10)->get();
            if ($availableTypes->isEmpty()) {
                $this->error("❌ В базе нет ни одного типа товара!");
                return 1;
            }
            
            $this->info("📋 Доступные типы товара:");
            foreach ($availableTypes as $availableType) {
                $this->line("   - ID: {$availableType->id}, Название: {$availableType->name}, Slug: {$availableType->slug}");
            }
            
            // Берем первый доступный тип
            $type = $availableTypes->first();
            $typeId = $type->id;
            $this->info("✅ Используем первый доступный тип: {$type->name} (ID: $typeId)");
        } else {
            $this->info("✅ Тип товара найден: {$type->name} (ID: $typeId)");
        }

        // ЭТАП 2: Получение доступных атрибутов и их значений
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 2: Получение доступных атрибутов');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Ищем атрибуты для магазина или общие атрибуты
        $attributeValues = AttributeValue::with('attribute')
            ->whereHas('attribute', function($q) use ($shopId) {
                $q->where(function($query) use ($shopId) {
                    $query->where('shop_id', $shopId)
                          ->orWhere('is_common', true);
                });
            })
            ->limit(20)
            ->get();

        if ($attributeValues->isEmpty()) {
            $this->warn("⚠️  Не найдено значений атрибутов для магазина $shopId");
            $this->info("Создадим тестовый товар без вариаций для проверки базовой функциональности");
        } else {
            $this->info("✅ Найдено значений атрибутов: " . $attributeValues->count());
            foreach ($attributeValues->groupBy('attribute_id') as $attrId => $values) {
                $attribute = $values->first()->attribute;
                $this->info("   - {$attribute->name}: " . $values->pluck('value')->implode(', '));
            }
        }

        // ЭТАП 3: Подготовка тестовых данных
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 3: Подготовка тестовых данных');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $timestamp = time();
        $testData = [
            'name' => "Тестовый вариативный товар $timestamp",
            'product_type' => 'variable',
            'shop_id' => $shopId,
            'type_id' => $typeId,
            'description' => 'Тестовое описание вариативного товара',
            'unit' => 'шт.',
            'status' => 'draft',
            'language' => 'ru',
        ];

        // Если есть атрибуты, используем их
        if ($attributeValues->isNotEmpty()) {
            // Берем первые 2-3 значения атрибутов для вариаций
            $selectedValues = $attributeValues->take(3);
            $testData['variations'] = $selectedValues->pluck('id')->toArray();

            // Создаем варианты на основе выбранных значений
            $variationOptions = [];
            $index = 1;
            foreach ($selectedValues->groupBy('attribute_id') as $attrId => $values) {
                $attribute = $values->first()->attribute;
                foreach ($values as $value) {
                    $variationOptions[] = [
                        'price' => (string)(100 * $index),
                        'quantity' => 10 * $index,
                        'sku' => "TEST-{$timestamp}-{$index}",
                        'title' => $value->value,
                        // ВАЖНО: options должен быть массивом, а не JSON строкой
                        'options' => [
                            [
                                'name' => $attribute->name,
                                'value' => $value->value,
                            ]
                        ],
                    ];
                    $index++;
                }
            }
            $testData['variation_options'] = ['upsert' => $variationOptions];
        } else {
            // Создаем без вариаций для проверки базовой функциональности
            $this->warn("⚠️  Создаем товар без вариаций (нет доступных атрибутов)");
        }

        $this->info("✅ Тестовые данные подготовлены:");
        $this->line("   - Название: {$testData['name']}");
        $this->line("   - Тип: {$testData['product_type']}");
        if (isset($testData['variations'])) {
            $this->line("   - Вариаций: " . count($testData['variations']));
        }
        if (isset($testData['variation_options']['upsert'])) {
            $this->line("   - Вариантов: " . count($testData['variation_options']['upsert']));
        }

        // ЭТАП 4: Создание Request объекта и валидация
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 4: Создание Request объекта и валидация');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $request = Request::create('/api/products', 'POST', $testData);
        $request->setUserResolver(function() use ($userId) {
            return \Marvel\Database\Models\User::find($userId);
        });

        $createRequest = ProductCreateRequest::createFrom($request);
        $createRequest->setContainer(app());

        // Валидация вручную для консольного контекста
        try {
            $validator = \Validator::make($testData, $createRequest->rules(), $createRequest->messages());
            
            if ($validator->fails()) {
                $this->error("❌ Ошибка валидации:");
                foreach ($validator->errors()->all() as $error) {
                    $this->error("   - $error");
                }
                $this->newLine();
                $this->warn("Детали ошибок валидации:");
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->line("   $field: $message");
                    }
                }
                return 1;
            }
            
            // Если валидация прошла, применяем данные к request
            $createRequest->merge($validator->validated());
            $this->info("✅ Request объект создан и валидирован");
        } catch (\Exception $e) {
            $this->error("❌ Ошибка при валидации:");
            $this->error("   " . $e->getMessage());
            return 1;
        }

        // ЭТАП 5: Вызов storeProduct
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 5: Вызов storeProduct');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $settings = $this->settings->first();
            if (!$settings) {
                $this->error("❌ Настройки не найдены!");
                return 1;
            }

            $this->info("📝 Начинаем создание товара...");
            Log::info('=== TEST COMMAND: Начало создания вариативного товара ===', [
                'shop_id' => $shopId,
                'type_id' => $typeId,
                'test_data' => $testData,
            ]);

            $product = $this->repository->storeProduct($createRequest, $settings);

            $this->info("✅ Товар создан успешно!");
            $this->newLine();

            // ЭТАП 6: Проверка результата
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('ЭТАП 6: Проверка результата');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $product->load('variations', 'variation_options');

            $this->info("📦 Информация о товаре:");
            $this->line("   - ID: {$product->id}");
            $this->line("   - Название: {$product->name}");
            $this->line("   - Тип: {$product->product_type}");
            $this->line("   - Slug: {$product->slug}");
            $this->line("   - Статус: {$product->status}");

            $this->newLine();
            $this->info("🔗 Вариации:");
            if ($product->variations->isEmpty()) {
                $this->warn("   ⚠️  Вариации не созданы");
            } else {
                $this->info("   ✅ Создано вариаций: " . $product->variations->count());
                foreach ($product->variations as $variation) {
                    $this->line("      - {$variation->value} (ID: {$variation->id}, Атрибут: {$variation->attribute->name})");
                }
            }

            $this->newLine();
            $this->info("📋 Варианты товара:");
            if ($product->variation_options->isEmpty()) {
                $this->warn("   ⚠️  Варианты не созданы");
            } else {
                $this->info("   ✅ Создано вариантов: " . $product->variation_options->count());
                foreach ($product->variation_options as $option) {
                    $this->line("      - {$option->title} (ID: {$option->id})");
                    $this->line("        Цена: {$option->price}, Количество: {$option->quantity}, SKU: {$option->sku}");
                    if ($option->options) {
                        $options = is_string($option->options) ? json_decode($option->options, true) : $option->options;
                        if (is_array($options)) {
                            $this->line("        Опции: " . json_encode($options, JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            }

            $this->newLine();
            $this->info('========================================');
            $this->info('✅ Тестирование завершено успешно!');
            $this->info('========================================');

            Log::info('=== TEST COMMAND: Товар создан успешно ===', [
                'product_id' => $product->id,
                'variations_count' => $product->variations->count(),
                'variation_options_count' => $product->variation_options->count(),
            ]);

            return 0;

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error("❌ Ошибка валидации:");
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error("   - $field: $message");
                }
            }
            return 1;
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Это исключение выбрасывается из failedValidation, но в консоли нам нужны ошибки валидации
            $this->error("❌ Ошибка валидации (HttpResponseException):");
            $response = $e->getResponse();
            if ($response) {
                $data = json_decode($response->getContent(), true);
                if (isset($data['errors'])) {
                    foreach ($data['errors'] as $field => $messages) {
                        foreach ($messages as $message) {
                            $this->error("   - $field: $message");
                        }
                    }
                } else {
                    $this->error("   " . $response->getContent());
                }
            } else {
                $this->error("   " . $e->getMessage());
            }
            return 1;
        } catch (\Exception $e) {
            $this->error("❌ Ошибка при создании товара:");
            $this->error("   " . $e->getMessage());
            $this->error("   Файл: {$e->getFile()}:{$e->getLine()}");
            $this->newLine();
            $this->warn("Полный trace:");
            $this->line($e->getTraceAsString());

            Log::error('=== TEST COMMAND: Ошибка при создании товара ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return 1;
        }
    }
}

