<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Repositories\ProductGroupRepository;
use Marvel\Database\Repositories\ProductSkuRepository;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\Type;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Marvel\Database\Models\ProductGroup;
use Marvel\Database\Models\ProductSku;
use Illuminate\Http\Request;

class TestProductGroupSkuCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:product-group-sku 
                            {--shop-id=1 : ID магазина}
                            {--type-id=1 : ID типа товара}
                            {--user-id=1 : ID пользователя}
                            {--cleanup : Удалить тестовые данные после завершения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование новой системы ProductGroup + ProductSKU';

    protected $groupRepository;
    protected $skuRepository;
    protected $createdGroupId = null;
    protected $createdSkuIds = [];

    public function __construct(ProductGroupRepository $groupRepository, ProductSkuRepository $skuRepository)
    {
        parent::__construct();
        $this->groupRepository = $groupRepository;
        $this->skuRepository = $skuRepository;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ТЕСТИРОВАНИЕ СИСТЕМЫ ProductGroup + ProductSKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $shopId = (int)$this->option('shop-id');
        $typeId = (int)$this->option('type-id');
        $userId = (int)$this->option('user-id');

        try {
            // ЭТАП 1: Проверка окружения
            $env = $this->checkEnvironment($shopId, $typeId, $userId);
            if (!$env) {
                return 1;
            }

            // ЭТАП 2: Создание ProductGroup
            if (!$this->testCreateProductGroup($env['shopId'], $env['typeId'], $env['userId'])) {
                return 1;
            }

            // ЭТАП 3: Генерация SKU из атрибутов
            if (!$this->testGenerateSkus()) {
                return 1;
            }

            // ЭТАП 4: Обновление SKU
            if (!$this->testUpdateSku()) {
                return 1;
            }

            // ЭТАП 5: Получение группы со всеми SKU
            if (!$this->testGetGroupWithSkus()) {
                return 1;
            }

            // ЭТАП 6: Получение конкретного SKU
            if (!$this->testGetSingleSku()) {
                return 1;
            }

            // ЭТАП 7: Создание отдельного SKU
            if (!$this->testCreateSingleSku()) {
                return 1;
            }

            // ЭТАП 8: Тестирование SEO-URL и редиректов
            if (!$this->testSeoUrls()) {
                return 1;
            }

            // ЭТАП 9: Тестирование изменения slug и истории
            if (!$this->testSlugHistory()) {
                return 1;
            }

            // ЭТАП 10: Удаление SKU
            if (!$this->testDeleteSku()) {
                return 1;
            }

            // Итоговая статистика
            $this->printFinalStatistics();

            // Очистка тестовых данных
            if ($this->option('cleanup')) {
                $this->cleanup();
            }

            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ КРИТИЧЕСКАЯ ОШИБКА: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            Log::error('TestProductGroupSkuCommand - CRITICAL ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    protected function checkEnvironment($shopId, $typeId, $userId)
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 1: Проверка окружения');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Проверка магазина
        $shop = Shop::find($shopId);
        if (!$shop) {
            $this->error("❌ Магазин с ID $shopId не найден!");
            return false;
        }
        $this->info("✅ Магазин найден: {$shop->name} (ID: $shopId)");

        // Проверка типа товара
        $type = Type::find($typeId);
        if (!$type) {
            $this->warn("⚠️  Тип товара с ID $typeId не найден!");
            
            // Пытаемся найти первый доступный тип
            $type = Type::first();
            if (!$type) {
                $this->error("❌ В базе данных нет ни одного типа товара!");
                $this->line("   Создайте хотя бы один тип товара в админ-панели");
                return false;
            }
            
            $this->info("✅ Используем первый доступный тип: {$type->name} (ID: {$type->id})");
        } else {
            $this->info("✅ Тип товара найден: {$type->name} (ID: $typeId)");
        }

        // Проверка пользователя
        $user = \Marvel\Database\Models\User::find($userId);
        if (!$user) {
            $this->error("❌ Пользователь с ID $userId не найден!");
            return false;
        }
        $this->info("✅ Пользователь найден: {$user->name} (ID: $userId)");

        // Проверка атрибутов
        $attributes = Attribute::with('values')->limit(2)->get();
        if ($attributes->count() < 2) {
            $this->warn("⚠️  Недостаточно атрибутов для генерации комбинаций (найдено: {$attributes->count()})");
            $this->info("   Будет создана минимальная конфигурация");
        } else {
            $this->info("✅ Атрибуты найдены: {$attributes->count()}");
            foreach ($attributes as $attr) {
                $this->line("   - {$attr->name}: {$attr->values->count()} значений");
            }
        }

        $this->newLine();
        return [
            'shopId' => $shop->id,
            'typeId' => $type->id,
            'userId' => $user->id,
        ];
    }

    protected function testCreateProductGroup($shopId, $typeId, $userId)
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 2: Создание ProductGroup');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            $timestamp = time();
            $testData = [
                'title' => "Тестовая группа товаров {$timestamp}",
                'description' => 'Описание тестовой группы товаров для проверки новой системы',
                'short_description' => 'Краткое описание',
                'type_id' => $typeId,
                'shop_id' => $shopId,
                'status' => 'publish',
                'language' => 'ru',
                'main_image' => [
                    'original' => 'https://via.placeholder.com/800',
                    'thumbnail' => 'https://via.placeholder.com/150',
                ],
                'gallery' => [
                    [
                        'original' => 'https://via.placeholder.com/800/FF0000',
                        'thumbnail' => 'https://via.placeholder.com/150/FF0000',
                    ],
                    [
                        'original' => 'https://via.placeholder.com/800/00FF00',
                        'thumbnail' => 'https://via.placeholder.com/150/00FF00',
                    ],
                ],
            ];

            $this->line("📝 Данные для создания:");
            $this->line("   - Название: {$testData['title']}");
            $this->line("   - Тип: {$testData['type_id']}");
            $this->line("   - Магазин: {$testData['shop_id']}");
            $this->line("   - Статус: {$testData['status']}");
            $this->newLine();

            // Получаем пользователя
            $user = \Marvel\Database\Models\User::find($userId);
            if (!$user) {
                throw new \Exception("User not found: $userId");
            }

            // Создаем Request и устанавливаем пользователя
            $request = Request::create('/api/product-groups', 'POST', $testData);
            $request->setUserResolver(function() use ($user) {
                return $user;
            });

            // Устанавливаем текущего пользователя в Auth
            \Illuminate\Support\Facades\Auth::setUser($user);

            $this->info("📤 Отправка запроса на создание...");
            $group = $this->groupRepository->storeProductGroup($request);
            $this->createdGroupId = $group->id;

            $this->newLine();
            $this->info("✅ ProductGroup создан успешно!");
            $this->line("   - ID: {$group->id}");
            $this->line("   - Название: {$group->title}");
            $this->line("   - Slug: {$group->slug}");
            $this->line("   - Статус: {$group->status}");
            $this->newLine();

            Log::info('TestProductGroupSkuCommand - Group created', [
                'group_id' => $group->id,
                'slug' => $group->slug,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при создании группы: {$e->getMessage()}");
            $this->error("Trace: " . $e->getTraceAsString());
            Log::error('TestProductGroupSkuCommand - Group creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function testGenerateSkus()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 3: Генерация SKU из атрибутов');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            // Получаем атрибуты с значениями
            $attributes = Attribute::with('values')->limit(2)->get();
            
            if ($attributes->count() < 1) {
                $this->warn("⚠️  Атрибуты не найдены, пропускаем генерацию");
                return true;
            }

            $attributeIds = $attributes->pluck('id')->toArray();
            
            $this->line("📋 Атрибуты для генерации:");
            foreach ($attributes as $attr) {
                $this->line("   - {$attr->name} (ID: {$attr->id}): " . $attr->values->pluck('value')->implode(', '));
            }
            $this->newLine();

            // Рассчитываем ожидаемое количество комбинаций
            $expectedCombinations = 1;
            foreach ($attributes as $attr) {
                $expectedCombinations *= $attr->values->count();
            }
            $this->info("📊 Ожидаемое количество комбинаций: {$expectedCombinations}");
            $this->newLine();

            $this->info("🔄 Генерация SKU...");
            $skus = $this->skuRepository->generateSkusFromAttributes(
                $this->createdGroupId,
                $attributeIds,
                1000 // базовая цена
            );

            $this->createdSkuIds = array_map(fn($sku) => $sku->id, $skus);

            $this->newLine();
            $this->info("✅ SKU сгенерированы успешно!");
            $this->line("   - Создано: " . count($skus));
            $this->newLine();

            // Показываем первые 5 SKU
            $this->line("📦 Первые SKU:");
            foreach (array_slice($skus, 0, 5) as $sku) {
                $this->line("   - {$sku->title} (#{$sku->id})");
                $this->line("     Slug: {$sku->slug}");
                $this->line("     Цена: {$sku->price}, Количество: {$sku->quantity}");
            }

            if (count($skus) > 5) {
                $this->line("   ... и ещё " . (count($skus) - 5) . " SKU");
            }

            $this->newLine();

            Log::info('TestProductGroupSkuCommand - SKUs generated', [
                'group_id' => $this->createdGroupId,
                'count' => count($skus),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при генерации SKU: {$e->getMessage()}");
            $this->error("Trace: " . $e->getTraceAsString());
            Log::error('TestProductGroupSkuCommand - SKU generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function testUpdateSku()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 4: Обновление SKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            if (empty($this->createdSkuIds)) {
                $this->warn("⚠️  Нет созданных SKU для обновления");
                return true;
            }

            // Обновляем первый SKU
            $skuId = $this->createdSkuIds[0];
            $this->line("📝 Обновляем SKU ID: {$skuId}");
            $this->newLine();

            $updateData = [
                'price' => 1500,
                'old_price' => 2000,
                'quantity' => 100,
                'is_active' => true,
            ];

            $this->line("📤 Данные для обновления:");
            $this->line("   - Цена: {$updateData['price']} (старая: {$updateData['old_price']})");
            $this->line("   - Количество: {$updateData['quantity']}");
            $this->line("   - Активен: " . ($updateData['is_active'] ? 'Да' : 'Нет'));
            $this->newLine();

            $request = Request::create("/api/skus/{$skuId}", 'PUT', $updateData);
            $updatedSku = $this->skuRepository->updateProductSku($request, $skuId);

            $this->info("✅ SKU обновлен успешно!");
            $this->line("   - ID: {$updatedSku->id}");
            $this->line("   - Название: {$updatedSku->title}");
            $this->line("   - Цена: {$updatedSku->price}");
            $this->line("   - Старая цена: {$updatedSku->old_price}");
            $this->line("   - Количество: {$updatedSku->quantity}");
            $this->newLine();

            Log::info('TestProductGroupSkuCommand - SKU updated', [
                'sku_id' => $skuId,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при обновлении SKU: {$e->getMessage()}");
            Log::error('TestProductGroupSkuCommand - SKU update failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function testGetGroupWithSkus()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 5: Получение группы со всеми SKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            $group = ProductGroup::with(['activeSkus', 'activeSkus.propertyValues', 'activeSkus.propertyValues.attribute'])
                ->findOrFail($this->createdGroupId);

            $this->info("✅ Группа загружена:");
            $this->line("   - ID: {$group->id}");
            $this->line("   - Название: {$group->title}");
            $this->line("   - Slug: {$group->slug}");
            $this->line("   - Количество SKU: {$group->activeSkus->count()}");
            $this->line("   - Мин. цена: {$group->min_price}");
            $this->line("   - Макс. цена: {$group->max_price}");
            $this->line("   - Общее количество: {$group->total_quantity}");
            $this->newLine();

            // Показываем SKU
            if ($group->activeSkus->count() > 0) {
                $this->line("📦 SKU в группе:");
                foreach ($group->activeSkus->take(3) as $sku) {
                    $this->line("   - {$sku->title}");
                    $this->line("     Цена: {$sku->price}, Кол-во: {$sku->quantity}");
                    
                    if ($sku->propertyValues->count() > 0) {
                        $properties = $sku->propertyValues->map(function($pv) {
                            return "{$pv->attribute->name}: {$pv->value}";
                        })->implode(', ');
                        $this->line("     Свойства: {$properties}");
                    }
                }
                
                if ($group->activeSkus->count() > 3) {
                    $this->line("   ... и ещё " . ($group->activeSkus->count() - 3) . " SKU");
                }
            }

            $this->newLine();

            Log::info('TestProductGroupSkuCommand - Group retrieved', [
                'group_id' => $group->id,
                'skus_count' => $group->activeSkus->count(),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при получении группы: {$e->getMessage()}");
            return false;
        }
    }

    protected function testGetSingleSku()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 6: Получение конкретного SKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            if (empty($this->createdSkuIds)) {
                $this->warn("⚠️  Нет созданных SKU");
                return true;
            }

            $skuId = $this->createdSkuIds[0];
            $sku = ProductSku::with(['group', 'propertyValues', 'propertyValues.attribute'])
                ->findOrFail($skuId);

            $this->info("✅ SKU загружен:");
            $this->line("   - ID: {$sku->id}");
            $this->line("   - Название: {$sku->title}");
            $this->line("   - Slug: {$sku->slug}");
            $this->line("   - SKU код: " . ($sku->sku ?? 'не указан'));
            $this->line("   - Цена: {$sku->price}");
            $this->line("   - Старая цена: " . ($sku->old_price ?? 'не указана'));
            $this->line("   - Количество: {$sku->quantity}");
            $this->line("   - Активен: " . ($sku->is_active ? 'Да' : 'Нет'));
            $this->newLine();

            $this->line("🔗 Группа:");
            $this->line("   - {$sku->group->title} (#{$sku->group->id})");
            $this->newLine();

            if ($sku->propertyValues->count() > 0) {
                $this->line("🏷️  Свойства:");
                foreach ($sku->propertyValues as $pv) {
                    $this->line("   - {$pv->attribute->name}: {$pv->value}");
                }
                $this->newLine();
            }

            Log::info('TestProductGroupSkuCommand - SKU retrieved', [
                'sku_id' => $sku->id,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при получении SKU: {$e->getMessage()}");
            return false;
        }
    }

    protected function testCreateSingleSku()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 7: Создание отдельного SKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            $testData = [
                'title' => 'Кастомный SKU',
                'price' => 2500,
                'quantity' => 50,
                'sku' => 'CUSTOM-SKU-001',
                'is_active' => true,
            ];

            $this->line("📝 Данные для создания:");
            $this->line("   - Название: {$testData['title']}");
            $this->line("   - Цена: {$testData['price']}");
            $this->line("   - Количество: {$testData['quantity']}");
            $this->line("   - SKU код: {$testData['sku']}");
            $this->newLine();

            $request = Request::create("/api/product-groups/{$this->createdGroupId}/skus", 'POST', $testData);
            $sku = $this->skuRepository->storeProductSku($request, $this->createdGroupId);
            $this->createdSkuIds[] = $sku->id;

            $this->info("✅ SKU создан успешно!");
            $this->line("   - ID: {$sku->id}");
            $this->line("   - Название: {$sku->title}");
            $this->line("   - Slug: {$sku->slug}");
            $this->line("   - Цена: {$sku->price}");
            $this->newLine();

            Log::info('TestProductGroupSkuCommand - Single SKU created', [
                'sku_id' => $sku->id,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при создании SKU: {$e->getMessage()}");
            return false;
        }
    }

    protected function testSeoUrls()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 8: Тестирование SEO-URL формата');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            $group = ProductGroup::find($this->createdGroupId);
            if (!$group) {
                $this->error("❌ Группа не найдена");
                return false;
            }

            $this->line("📦 Проверка URL группы:");
            $this->line("   - ID: {$group->id}");
            $this->line("   - Slug: {$group->slug}");
            $this->line("   - URL: {$group->url}");
            $this->line("   - Full URL: {$group->full_url}");
            $this->newLine();

            // Проверка формата URL
            $expectedUrl = "/element/{$group->slug}-{$group->id}";
            if ($group->url === $expectedUrl) {
                $this->info("✅ URL группы соответствует формату /element/{slug}-{id}");
            } else {
                $this->error("❌ URL группы не соответствует ожидаемому формату");
                $this->error("   Ожидалось: {$expectedUrl}");
                $this->error("   Получено: {$group->url}");
                return false;
            }
            $this->newLine();

            // Проверка URL для SKU
            if (!empty($this->createdSkuIds)) {
                $sku = ProductSku::with('group')->find($this->createdSkuIds[0]);
                if ($sku) {
                    $this->line("📦 Проверка URL SKU:");
                    $this->line("   - SKU ID: {$sku->id}");
                    $this->line("   - SKU Slug: {$sku->slug}");
                    $this->line("   - Group Slug: {$sku->group->slug}");
                    $this->line("   - URL: {$sku->url}");
                    $this->line("   - Full URL: {$sku->full_url}");
                    $this->newLine();

                    $expectedSkuUrl = "/element/{$sku->group->slug}/{$sku->slug}-{$sku->id}";
                    if ($sku->url === $expectedSkuUrl) {
                        $this->info("✅ URL SKU соответствует формату /element/{group-slug}/{sku-slug}-{sku-id}");
                    } else {
                        $this->error("❌ URL SKU не соответствует ожидаемому формату");
                        $this->error("   Ожидалось: {$expectedSkuUrl}");
                        $this->error("   Получено: {$sku->url}");
                        return false;
                    }
                    $this->newLine();
                }
            }

            // Тест парсинга slug-id
            $this->line("🔍 Тест парсинга {slug}-{id}:");
            $testCases = [
                'test-product-123' => ['slug' => 'test-product', 'id' => 123],
                'my-awesome-product-456789' => ['slug' => 'my-awesome-product', 'id' => 456789],
                'product-with-many-dashes-999' => ['slug' => 'product-with-many-dashes', 'id' => 999],
            ];

            foreach ($testCases as $input => $expected) {
                $result = ProductGroup::parseSlugId($input);
                if ($result['slug'] === $expected['slug'] && $result['id'] === $expected['id']) {
                    $this->line("   ✓ '{$input}' → slug: '{$result['slug']}', id: {$result['id']}");
                } else {
                    $this->error("   ✗ '{$input}' → ожидалось: {$expected['slug']}-{$expected['id']}, получено: {$result['slug']}-{$result['id']}");
                    return false;
                }
            }

            $this->newLine();
            $this->info("✅ Все проверки SEO-URL пройдены!");
            $this->newLine();

            Log::info('TestProductGroupSkuCommand - SEO URLs tested');

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при тестировании SEO-URL: {$e->getMessage()}");
            return false;
        }
    }

    protected function testSlugHistory()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 9: Тестирование истории изменений slug');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            $group = ProductGroup::find($this->createdGroupId);
            if (!$group) {
                $this->error("❌ Группа не найдена");
                return false;
            }

            $oldSlug = $group->slug;
            $newSlug = $oldSlug . '-updated';

            $this->line("📝 Изменяем slug группы:");
            $this->line("   - Старый slug: {$oldSlug}");
            $this->line("   - Новый slug: {$newSlug}");
            $this->newLine();

            // Используем метод changeSlug для явного сохранения истории
            $group->changeSlug($newSlug);

            $this->info("✅ Slug обновлен");
            $this->newLine();

            // Проверяем, что создалась запись в истории
            $history = \Marvel\Database\Models\ProductGroupSlugHistory::where('product_group_id', $group->id)
                ->where('old_slug', $oldSlug)
                ->first();

            if ($history) {
                $this->info("✅ Запись в истории создана:");
                $this->line("   - ID: {$history->id}");
                $this->line("   - Старый slug: {$history->old_slug}");
                $this->line("   - Дата изменения: {$history->changed_at}");
                $this->newLine();
            } else {
                $this->error("❌ Запись в истории НЕ создана!");
                return false;
            }

            // Тестируем поиск по старому slug
            $this->line("🔍 Тест поиска по старому slug:");
            $foundGroup = ProductGroup::findBySlugOrHistory($oldSlug);

            if ($foundGroup && $foundGroup->id === $group->id) {
                $this->info("✅ Группа найдена по старому slug!");
                $this->line("   - Найденная группа: #{$foundGroup->id}");
                $this->line("   - Текущий slug: {$foundGroup->slug}");
                $this->newLine();
            } else {
                $this->error("❌ Группа НЕ найдена по старому slug!");
                return false;
            }

            // Проверяем новый URL
            $this->line("🔗 Новый URL группы:");
            $this->line("   - {$group->url}");
            $this->newLine();

            // Тест для SKU
            if (!empty($this->createdSkuIds)) {
                $sku = ProductSku::find($this->createdSkuIds[0]);
                if ($sku) {
                    $oldSkuSlug = $sku->slug;
                    $newSkuSlug = $oldSkuSlug . '-v2';

                    $this->line("📝 Изменяем slug SKU:");
                    $this->line("   - Старый slug: {$oldSkuSlug}");
                    $this->line("   - Новый slug: {$newSkuSlug}");
                    $this->newLine();

                    // Используем метод changeSlug для явного сохранения истории
                    $sku->changeSlug($newSkuSlug);

                    $skuHistory = \Marvel\Database\Models\ProductSkuSlugHistory::where('product_sku_id', $sku->id)
                        ->where('old_slug', $oldSkuSlug)
                        ->first();

                    if ($skuHistory) {
                        $this->info("✅ Запись в истории SKU создана");
                        $this->newLine();
                    } else {
                        $this->error("❌ Запись в истории SKU НЕ создана!");
                        return false;
                    }

                    // Проверяем поиск SKU по старому slug
                    $foundSku = ProductSku::findBySlugOrHistory($oldSkuSlug);
                    if ($foundSku && $foundSku->id === $sku->id) {
                        $this->info("✅ SKU найден по старому slug!");
                        $this->newLine();
                    } else {
                        $this->error("❌ SKU НЕ найден по старому slug!");
                        return false;
                    }
                }
            }

            $this->info("✅ Все проверки истории slug пройдены!");
            $this->newLine();

            Log::info('TestProductGroupSkuCommand - Slug history tested');

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при тестировании истории slug: {$e->getMessage()}");
            $this->error("Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    protected function testDeleteSku()
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('ЭТАП 10: Удаление SKU');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        try {
            if (count($this->createdSkuIds) < 2) {
                $this->warn("⚠️  Недостаточно SKU для тестирования удаления");
                return true;
            }

            // Удаляем последний созданный SKU
            $skuId = end($this->createdSkuIds);
            $this->line("🗑️  Удаляем SKU ID: {$skuId}");
            $this->newLine();

            $this->skuRepository->deleteProductSku($skuId);

            $this->info("✅ SKU удален успешно!");
            $this->newLine();

            // Проверяем, что SKU действительно удален (soft delete)
            $deletedSku = ProductSku::withTrashed()->find($skuId);
            if ($deletedSku && $deletedSku->trashed()) {
                $this->line("✓ Подтверждено: SKU помечен как удаленный (soft delete)");
            }

            $this->newLine();

            Log::info('TestProductGroupSkuCommand - SKU deleted', [
                'sku_id' => $skuId,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при удалении SKU: {$e->getMessage()}");
            return false;
        }
    }

    protected function printFinalStatistics()
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ИТОГОВАЯ СТАТИСТИКА');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        if ($this->createdGroupId) {
            $group = ProductGroup::withCount('skus')->find($this->createdGroupId);
            if ($group) {
                $this->line("📦 Созданная группа:");
                $this->line("   - ID: {$group->id}");
                $this->line("   - Slug: {$group->slug}");
                $this->line("   - Всего SKU: {$group->skus_count}");
                $this->newLine();

                $this->line("🔗 Ссылки для проверки:");
                $this->line("   - Группа: /api/product-groups/{$group->slug}");
                $this->line("   - SKU группы: /api/product-groups/{$group->id}/skus");
                $this->newLine();
            }
        }

        $this->info("✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!");
        $this->newLine();
    }

    protected function cleanup()
    {
        $this->newLine();
        $this->info('🧹 Очистка тестовых данных...');
        $this->newLine();

        try {
            if ($this->createdGroupId) {
                $group = ProductGroup::find($this->createdGroupId);
                if ($group) {
                    // Удаляем все SKU
                    $group->skus()->delete();
                    $this->line("✓ SKU удалены");

                    // Удаляем группу
                    $group->delete();
                    $this->line("✓ Группа удалена");
                }
            }

            $this->newLine();
            $this->info("✅ Очистка завершена");

        } catch (\Exception $e) {
            $this->error("❌ Ошибка при очистке: {$e->getMessage()}");
        }
    }
}
