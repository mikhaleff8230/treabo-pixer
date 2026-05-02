<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductGroup;
use Marvel\Database\Models\ProductSku;
use Marvel\Database\Models\Variation;
use Marvel\Enums\ProductType;

class MigrateProductVariationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:product-variations 
                            {--dry-run : Выполнить без сохранения в БД}
                            {--limit= : Ограничить количество товаров для миграции}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Миграция товаров из старой структуры (products + variation_options) в новую (product_groups + product_skus)';

    protected $dryRun = false;
    protected $migrated = 0;
    protected $skipped = 0;
    protected $errors = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Миграция товаров в новую структуру ProductGroup + ProductSku');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('⚠️  РЕЖИМ ТЕСТИРОВАНИЯ: изменения не будут сохранены в БД');
            $this->newLine();
        }

        // Получаем все вариативные товары
        $query = Product::where('product_type', ProductType::VARIABLE)
            ->with(['variation_options', 'categories', 'tags', 'shop', 'type']);

        if ($limit) {
            $query->limit($limit);
        }

        $products = $query->get();
        $total = $products->count();

        $this->info("Найдено вариативных товаров: {$total}");
        $this->newLine();

        if ($total === 0) {
            $this->warn('Нет товаров для миграции');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($products as $product) {
                try {
                    $this->migrateProduct($product);
                    $this->migrated++;
                } catch (\Exception $e) {
                    $this->errors++;
                    Log::error('Ошибка миграции товара', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("\n❌ Ошибка миграции товара #{$product->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }

            if ($this->dryRun) {
                DB::rollBack();
                $this->newLine(2);
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('РЕЖИМ ТЕСТИРОВАНИЯ: транзакция отменена');
            } else {
                DB::commit();
                $this->newLine(2);
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('✅ Миграция завершена успешно!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error('❌ Критическая ошибка: ' . $e->getMessage());
            Log::error('Критическая ошибка миграции', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        // Статистика
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Статистика миграции:');
        $this->line("   ✅ Успешно мигрировано: {$this->migrated}");
        $this->line("   ⏭️  Пропущено: {$this->skipped}");
        $this->line("   ❌ Ошибок: {$this->errors}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return 0;
    }

    /**
     * Миграция одного товара
     */
    protected function migrateProduct(Product $product)
    {
        // Проверяем, есть ли варианты
        if ($product->variation_options->isEmpty()) {
            $this->skipped++;
            return;
        }

        // Создаем ProductGroup
        $group = new ProductGroup();
        $group->title = $product->name;
        $group->slug = $product->slug . '-group'; // Добавляем суффикс для уникальности
        $group->description = $product->description;
        $group->main_image = $product->image;
        $group->gallery = $product->gallery;
        $group->video = $product->video;
        $group->category_id = $product->categories->first()?->id;
        $group->type_id = $product->type_id;
        $group->shop_id = $product->shop_id;
        
        // Определяем brand_id и brand_type
        if ($product->manufacturer_id) {
            $group->brand_id = $product->manufacturer_id;
            $group->brand_type = 'manufacturer';
        } elseif ($product->author_id) {
            $group->brand_id = $product->author_id;
            $group->brand_type = 'author';
        }
        
        $group->status = $product->status;
        $group->language = $product->language ?? 'ru';

        if (!$this->dryRun) {
            $group->save();

            // Связываем категории
            if ($product->categories->isNotEmpty()) {
                $group->categories()->sync($product->categories->pluck('id'));
            }

            // Связываем теги
            if ($product->tags->isNotEmpty()) {
                $group->tags()->sync($product->tags->pluck('id'));
            }
        }

        // Мигрируем варианты в SKU
        foreach ($product->variation_options as $variationOption) {
            $this->migrateVariationOption($variationOption, $group, $this->dryRun);
        }

        if (!$this->dryRun) {
            // Обновляем slug группы, если он был изменен из-за конфликта
            $group->save();
        }
    }

    /**
     * Миграция варианта в SKU
     */
    protected function migrateVariationOption(Variation $variationOption, ProductGroup $group, bool $dryRun)
    {
        $sku = new ProductSku();
        $sku->group_id = $group->id;
        $sku->sku = $variationOption->sku;
        $sku->slug = $this->generateSkuSlug($group, $variationOption);
        $sku->price = (float)$variationOption->price;
        $sku->old_price = $variationOption->sale_price ? (float)$variationOption->sale_price : null;
        $sku->quantity = (int)$variationOption->quantity;
        $sku->image = $variationOption->image;
        $sku->title = $variationOption->title;
        $sku->is_digital = $variationOption->is_digital ?? false;
        $sku->is_disable = $variationOption->is_disable ?? false;
        $sku->is_active = !$variationOption->is_disable;
        $sku->language = $group->language;

        if (!$dryRun) {
            $sku->save();

            // Связываем свойства (атрибуты) из options
            if (!empty($variationOption->options)) {
                $options = is_string($variationOption->options) 
                    ? json_decode($variationOption->options, true) 
                    : $variationOption->options;

                if (is_array($options)) {
                    $syncData = [];
                    foreach ($options as $option) {
                        if (isset($option['attribute_id']) && isset($option['attribute_value_id'])) {
                            $syncData[$option['attribute_value_id']] = [
                                'property_id' => $option['attribute_id'],
                            ];
                        } elseif (isset($option['name']) && isset($option['value'])) {
                            // Ищем по названиям
                            $attribute = \Marvel\Database\Models\Attribute::where('name', $option['name'])->first();
                            if ($attribute) {
                                $attributeValue = \Marvel\Database\Models\AttributeValue::where('attribute_id', $attribute->id)
                                    ->where('value', $option['value'])
                                    ->first();
                                
                                if ($attributeValue) {
                                    $syncData[$attributeValue->id] = [
                                        'property_id' => $attribute->id,
                                    ];
                                }
                            }
                        }
                    }
                    $sku->propertyValues()->sync($syncData);
                }
            }
        }
    }

    /**
     * Генерация slug для SKU
     */
    protected function generateSkuSlug(ProductGroup $group, Variation $variationOption): string
    {
        $baseSlug = $group->slug;
        
        // Формируем slug из options
        $options = is_string($variationOption->options) 
            ? json_decode($variationOption->options, true) 
            : $variationOption->options;

        if (is_array($options) && !empty($options)) {
            $values = array_map(function($opt) {
                return isset($opt['value']) ? \Illuminate\Support\Str::slug($opt['value']) : '';
            }, $options);
            $values = array_filter($values);
            
            if (!empty($values)) {
                $baseSlug .= '-' . implode('-', $values);
            }
        }

        // Проверяем уникальность
        $slug = $baseSlug;
        $counter = 1;
        while (ProductSku::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

