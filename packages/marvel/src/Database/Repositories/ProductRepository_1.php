<?php


namespace Marvel\Database\Repositories;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Availability;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Resource;
use Marvel\Database\Models\Tag;
use Marvel\Database\Models\Type;
use Marvel\Database\Models\Variation;
use Illuminate\Support\Str;
use Marvel\Enums\ProductStatus;
use Marvel\Enums\ProductType;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Marvel\Enums\Permission;
use Marvel\Events\ProductReviewApproved;
use Marvel\Events\ProductReviewRejected;
use Marvel\Events\ProductCreated;
use Marvel\Events\ProductUnderReview;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name'        => 'like',
        'shop_id',
        'status',
        'is_rental',
        'product_type',
        'type.slug',
        'dropoff_locations.slug' => 'in',
        'pickup_locations.slug' => 'in',
        'persons.slug' => 'in',
        'deposits.slug' => 'in',
        'features.slug' => 'in',
        'categories.slug' => 'in',
        'tags.slug' => 'in',
        'author.slug',
        'manufacturer.slug' => 'in',
        'min_price' => 'between',
        'max_price' => 'between',
        'price' => 'between',
        'language',
        'metas.key',
        'metas.value',
        'internal_article' => 'like', // Поиск по внутреннему артикулу
        'sku' => 'like', // Поиск по артикулу (для обратной совместимости)
        'group_key', // Фильтр по ключу группы товаров

    ];

    protected $dataArray = [
        'name',
        'slug',
        'price',
        'sale_price',
        'max_price',
        'min_price',
        'type_id',
        'author_id',
        'language',
        'manufacturer_id',
        'product_type',
        'quantity',
        'unit',
        'is_digital',
        'is_external',
        'external_product_url',
        'external_product_button_text',
        'description',
        'sku',
        'preview_url',
        'image',
        'gallery',
        'video',
        'status',
        'height',
        'length',
        'width',
        'weight',
        'in_stock',
        'is_taxable',
        'shop_id',
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Product::class;
    }


    /**
     * storeProduct
     *
     * @param  mixed $request
     * @param  mixed $setting
     * @return void
     */
    public function storeProduct($request, $setting)
    {
        try {
            // FormData уже обработан в ProductCreateRequest::prepareForValidation
            // Расширенное логирование для отладки
            Log::info('=== ProductRepository::storeProduct - START ===');
            Log::info('ProductRepository::storeProduct - request data', [
                'product_type' => $request->input('product_type'),
                'has_product_type' => $request->has('product_type'),
                'name' => $request->input('name'),
                'shop_id' => $request->input('shop_id'),
                'type_id' => $request->input('type_id'),
                'has_variations' => $request->has('variations'),
                'has_variation_options' => $request->has('variation_options'),
                'variations' => $request->input('variations'),
                'variation_options' => $request->input('variation_options'),
                'all_keys' => array_keys($request->all()),
            ]);
            
            $data = $request->only($this->dataArray);
            $data['slug'] = $this->makeSlug($request);
            
            // УПРОЩЕННАЯ ЛОГИКА - как в CategoryRepository
            // Если gallery пришел как JSON строка - декодируем (для FormData)
            if (isset($data['gallery']) && is_string($data['gallery'])) {
                $decoded = json_decode($data['gallery'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data['gallery'] = $decoded;
                }
            }

            if ($setting->options["isProductReview"]) {
                if ($request->status == ProductStatus::DRAFT) {
                    $data['status'] = ProductStatus::DRAFT;
                } elseif ($request->status == ProductStatus::UNDER_REVIEW) {
                    $data['status'] = ProductStatus::UNDER_REVIEW;
                } else {
                    throw new HttpException(406, 'The selected status is invalid.');
                }
            }

            if ($request->product_type == ProductType::SIMPLE) {
                $data['max_price'] = $data['price'];
                $data['min_price'] = $data['price'];
            }
            
            // ВАЖНО: internal_article генерируется ТОЛЬКО автоматически, игнорируем любые значения из запроса
            // Удаляем internal_article из данных, если он был передан (не должен приниматься из API)
            unset($data['internal_article']);
            // Генерируем внутренний артикул автоматически
            $data['internal_article'] = \Marvel\Services\ArticleGeneratorService::generateProductArticle();
            
            $product = $this->create($data);

            if (empty($product->slug) || is_numeric($product->slug)) {
                $product->slug = $this->customSlugify($product->name);
                $product->save(); // Сохраняем сгенерированный slug
            }

            if (isset($request['metas'])) {
                foreach ($request['metas'] as $value) {
                    $metas[$value['key']] = $value['value'];
                    $product->setMeta($metas);
                }
            }

            // Обработка категории: теперь одна категория вместо массива
            try {
                if (isset($request['category_id'])) {
                    // Преобразуем category_id в число (если передан объект, извлекаем id)
                    $categoryId = is_array($request['category_id']) ? ($request['category_id']['id'] ?? $request['category_id'][0] ?? null) : $request['category_id'];
                    $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;
                    
                    if ($categoryId) {
                        $product->categories()->attach([$categoryId]);
                    }
                } elseif (isset($request['categories'])) {
                    // Для обратной совместимости: если передан массив categories
                    $categoryIds = is_array($request['categories']) ? $request['categories'] : [$request['categories']];
                    // Преобразуем все ID в числа
                    $categoryIds = array_filter(array_map(function($catId) {
                        if (is_array($catId)) {
                            return isset($catId['id']) && is_numeric($catId['id']) ? (int)$catId['id'] : null;
                        }
                        return is_numeric($catId) ? (int)$catId : null;
                    }, $categoryIds));
                    
                    if (!empty($categoryIds)) {
                        $product->categories()->attach($categoryIds);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('ProductRepository::storeProduct - Error processing category', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Не прерываем создание товара из-за ошибки категории
            }
            
            // Обработка значений атрибутов товара
            try {
                if (isset($request['attribute_values']) && is_array($request['attribute_values'])) {
                    $attributeValuesData = [];
                    foreach ($request['attribute_values'] as $attributeId => $value) {
                        // Преобразуем attributeId в число
                        $attrId = is_numeric($attributeId) ? (int)$attributeId : null;
                        if (!$attrId) {
                            continue; // Пропускаем невалидные ID
                        }
                        
                        // Пропускаем пустые значения
                        if (empty($value) && $value !== '0' && $value !== 0) {
                            continue;
                        }
                        
                        // Преобразуем значение в строку, если это массив (для multiselect)
                        $finalValue = is_array($value) ? implode(',', $value) : (string)$value;
                        
                        // Если значение - это объект (из SelectInput), извлекаем value
                        if (is_array($value) && isset($value['value'])) {
                            $finalValue = is_array($value['value']) ? implode(',', $value['value']) : (string)$value['value'];
                            $attributeValueId = isset($value['attribute_value_id']) && is_numeric($value['attribute_value_id']) ? (int)$value['attribute_value_id'] : null;
                        } else {
                            $attributeValueId = null;
                        }
                        
                        $attributeValuesData[$attrId] = [
                            'value' => $finalValue,
                            'attribute_value_id' => $attributeValueId,
                        ];
                    }
                    
                    // Используем sync для обновления значений атрибутов
                    if (!empty($attributeValuesData)) {
                        $product->attributes()->sync($attributeValuesData);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('ProductRepository::storeProduct - Error processing attributes', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Не прерываем создание товара из-за ошибки атрибутов
            }
            if (isset($request['dropoff_locations'])) {
                $product->dropoff_locations()->attach($request['dropoff_locations']);
            }
            if (isset($request['pickup_locations'])) {
                $product->pickup_locations()->attach($request['pickup_locations']);
            }
            if (isset($request['persons'])) {
                $product->persons()->attach($request['persons']);
            }
            if (isset($request['features'])) {
                $product->features()->attach($request['features']);
            }
            if (isset($request['deposits'])) {
                $product->deposits()->attach($request['deposits']);
            }
            if (isset($request['tags'])) {
                $tagIds = $this->processTags($request['tags'], $product->type_id ?? 1, $request['language'] ?? DEFAULT_LANGUAGE);
                if (!empty($tagIds)) {
                    $product->tags()->attach($tagIds);
                }
            }
            if (isset($request['variations'])) {
                // variations - это массив ID attribute_value для вариативных товаров
                // Используем sync() для правильной синхронизации связей
                $variationIds = is_array($request['variations']) 
                    ? array_filter(array_map('intval', $request['variations']))
                    : [];
                $product->variations()->sync($variationIds);
            }
            if (isset($request['variation_options']) && isset($request['variation_options']['upsert'])) {
                $upsertOptions = $request['variation_options']['upsert'];
                Log::info('ProductRepository::storeProduct - Processing variation_options', [
                    'count' => is_array($upsertOptions) ? count($upsertOptions) : 0,
                    'upsert' => $upsertOptions,
                ]);
                
                if (is_array($upsertOptions) && count($upsertOptions) > 0) {
                    foreach ($upsertOptions as $variation_option) {
                        // Обрабатываем options - должны быть массивом объектов {name, value}
                        if (isset($variation_option['options'])) {
                            if (is_string($variation_option['options'])) {
                                $variation_option['options'] = json_decode($variation_option['options'], true);
                            }
                            // Убеждаемся, что options - это массив
                            if (!is_array($variation_option['options'])) {
                                $variation_option['options'] = [];
                            }
                        } else {
                            $variation_option['options'] = [];
                        }
                        
                        // Формируем title из options, если не указан
                        if (!isset($variation_option['title']) || empty($variation_option['title'])) {
                            if (is_array($variation_option['options']) && count($variation_option['options']) > 0) {
                                $variation_option['title'] = implode('/', array_map(function($opt) {
                                    return $opt['value'] ?? '';
                                }, $variation_option['options']));
                            } else {
                                $variation_option['title'] = 'Variant';
                            }
                        }
                        
                        // Преобразуем числовые значения в правильные типы
                        if (isset($variation_option['price'])) {
                            $variation_option['price'] = (string)$variation_option['price'];
                        } else {
                            $variation_option['price'] = '0';
                        }
                        if (isset($variation_option['sale_price']) && $variation_option['sale_price'] !== null && $variation_option['sale_price'] !== '') {
                            $variation_option['sale_price'] = (string)$variation_option['sale_price'];
                        } else {
                            $variation_option['sale_price'] = null;
                        }
                        if (isset($variation_option['quantity'])) {
                            $variation_option['quantity'] = (int)$variation_option['quantity'];
                        } else {
                            $variation_option['quantity'] = 0;
                        }
                        
                        // Убеждаемся, что sku установлен
                        if (!isset($variation_option['sku']) || empty($variation_option['sku'])) {
                            $variation_option['sku'] = $product->slug . '-' . uniqid();
                        }
                        
                        // Убеждаемся, что is_disable установлен
                        if (!isset($variation_option['is_disable'])) {
                            $variation_option['is_disable'] = false;
                        }
                        if (!isset($variation_option['is_digital'])) {
                            $variation_option['is_digital'] = false;
                        }
                        
                        // Обрабатываем is_digital и digital_file
                    if (isset($variation_option['is_digital']) && $variation_option['is_digital']) {
                            $file = $variation_option['digital_file'] ?? null;
                        unset($variation_option['digital_file']);
                        } else {
                            $file = null;
                        }
                        
                        Log::info('ProductRepository::storeProduct - Creating variation_option', [
                            'variation_option' => $variation_option,
                        ]);
                        
                        try {
                    $new_variation_option = $product->variation_options()->create($variation_option);
                            
                            if ($file && isset($file['attachment_id']) && isset($file['url'])) {
                        $new_variation_option->digital_file()->create($file);
                            }
                            
                            Log::info('ProductRepository::storeProduct - Variation option created successfully', [
                                'id' => $new_variation_option->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('ProductRepository::storeProduct - Error creating variation_option', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'variation_option' => $variation_option,
                            ]);
                            throw $e;
                        }
                    }
                }
            }
            if (isset($request['is_digital']) && $request['is_digital'] && !empty($request['digital_file'])) {

                $digitalFileArray['attachment_id'] = $request['digital_file']['attachment_id'];
                $digitalFileArray['url'] = $request['digital_file']['url'];
                
                $product->digital_file()->create($digitalFileArray);
            }

               // Обработка загрузки видео
               // Логируем информацию о запросе
               Log::info('ProductRepository::storeProduct - проверка видео', [
                   'hasFile_video' => $request->hasFile('video'),
                   'has_video' => $request->has('video'),
                   'all_files' => array_keys($request->allFiles()),
                   'all_input_keys' => array_keys($request->all()),
                   'content_type' => $request->header('Content-Type'),
                   'request_method' => $request->method(),
               ]);
               
               if ($request->hasFile('video')) {
                   $file = $request->file('video');
                Log::info('ProductRepository::storeProduct - сохраняем видео', [
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
                
                // Проверяем размер файла (40MB максимум)
                $maxSize = 40 * 1024 * 1024; // 40MB
                if ($file->getSize() > $maxSize) {
                    throw new \Exception('Video file size exceeds maximum allowed size of 40MB');
                }
                
                $key = 'products/videos/' . uniqid('', true) . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                Storage::disk('s3')->put($key, file_get_contents($file->getRealPath()), [
                    'visibility' => 'public',
                    'CacheControl' => 'public, max-age=86400',
                    'ContentType' => $file->getMimeType() ?: 'video/mp4',
                ]);
                
                $videoRecord = \Marvel\Database\Models\ProductVideo::create([
                    'product_id' => $product->id,
                    'url' => $key,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
                
                Log::info('ProductRepository::storeProduct - видео сохранено в БД', [
                    'video_id' => $videoRecord->id,
                    'product_id' => $product->id,
                    'video_url' => $videoRecord->url,
                    's3_key' => $key,
                    'video_exists_in_db' => \Marvel\Database\Models\ProductVideo::where('id', $videoRecord->id)->exists(),
                ]);
                
                // Оптимизируем видео в фоне (можно через очередь)
                try {
                    \Marvel\Helpers\VideoOptimizer::optimizeVideo($videoRecord, $file->getRealPath());
                } catch (\Exception $e) {
                    Log::error('ProductRepository::storeProduct - ошибка оптимизации видео', [
                        'video_id' => $videoRecord->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Если установлена галочка "Сделать обложкой", используем превью как первое изображение
                if ($request->has('video_as_cover') && $request->input('video_as_cover')) {
                    // Сохраняем флаг, что нужно использовать превью
                    $product->setMeta('video_as_cover', true);
                    $product->setMeta('cover_video_id', $videoRecord->id);
                } else {
                    // Если галочка не установлена, удаляем флаг
                    $product->removeMeta('video_as_cover');
                    $product->removeMeta('cover_video_id');
                }
            } elseif ($request->has('video_as_cover')) {
                // Если видео не загружается, но есть флаг, обрабатываем его
                if ($request->input('video_as_cover')) {
                    // Находим последнее видео и устанавливаем флаг
                    $lastVideo = $product->videos()->latest()->first();
                    if ($lastVideo) {
                        $product->setMeta('video_as_cover', true);
                        $product->setMeta('cover_video_id', $lastVideo->id);
                    }
                } else {
                    // Если галочка снята, удаляем флаг
                    $product->removeMeta('video_as_cover');
                    $product->removeMeta('cover_video_id');
                }
            }

            $product->save();
            
            // Загружаем videos после сохранения
            try {
                if (class_exists(\Marvel\Database\Models\ProductVideo::class)) {
                    $product->load('videos');
                    Log::info('ProductRepository::storeProduct - videos загружены', [
                        'product_id' => $product->id,
                        'videos_count' => $product->videos ? $product->videos->count() : 0,
                        'videos_in_db' => \Marvel\Database\Models\ProductVideo::where('product_id', $product->id)->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('ProductRepository::storeProduct - не удалось загрузить videos', [
                    'error' => $e->getMessage(),
                    'product_id' => $product->id,
                ]);
            }
            
            // Загружаем связи для финальной проверки
            $product->load('variations', 'variation_options');
            
            // Обновляем slug если он был пустым или числовым (должно было произойти выше, но на всякий случай)
            if (empty($product->slug) || is_numeric($product->slug)) {
                $product->slug = $this->customSlugify($product->name);
                $product->save();
            }
            
            Log::info('ProductRepository::storeProduct - Product created successfully', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_url' => $product->url,
                'product_full_url' => $product->full_url,
                'product_type' => $product->product_type,
                'variations_count' => $product->variations->count(),
                'variation_options_count' => $product->variation_options->count(),
            ]);
            
            // Отправляем событие о создании товара
            event(new ProductCreated($product));
            
            Log::info('=== ProductRepository::storeProduct - END (SUCCESS) ===');
            return $product;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function checkProductForPublish($request, $product)
    {
        $status = '';
        if ($product->shop['owner']['id'] == $request->user()->id) {
            if ($product->status == ProductStatus::DRAFT || $product->status == ProductStatus::UNDER_REVIEW || $product->status == ProductStatus::REJECTED) {
                if ($request->status == ProductStatus::DRAFT) {
                    $status = ProductStatus::DRAFT;
                } elseif ($request->status == ProductStatus::UNDER_REVIEW) {
                    $status = ProductStatus::UNDER_REVIEW;
                    // Отправляем событие о товаре на модерации
                    event(new ProductUnderReview($product));
                } else {
                    $status = ProductStatus::DRAFT;
                }
            } elseif ($product->status == ProductStatus::APPROVED || $product->status == ProductStatus::PUBLISH || $product->status == ProductStatus::UNPUBLISH) {
                if ($request->status == ProductStatus::PUBLISH) {
                    $status = ProductStatus::PUBLISH;
                } elseif ($request->status == ProductStatus::UNPUBLISH) {
                    $status = ProductStatus::UNPUBLISH;
                } else {
                    $status = ProductStatus::UNPUBLISH;
                }
            }
        } elseif ($request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            if ($request->status == ProductStatus::APPROVED) {
                $status = ProductStatus::PUBLISH;
                event(new ProductReviewApproved($product));
            } elseif ($request->status == ProductStatus::REJECTED) {
                $status = ProductStatus::REJECTED;
                event(new ProductReviewRejected($product));
            } elseif ($request->status == ProductStatus::PUBLISH) {
                return ProductStatus::PUBLISH;
            } elseif ($request->status == ProductStatus::UNPUBLISH) {
                $status = ProductStatus::UNPUBLISH;
            } else {
                $status = ProductStatus::REJECTED;
            }
        } else {
            $status = ProductStatus::REJECTED;
        }
        return $status;
    }

    /**
     * updateProduct
     *
     * @param  $request
     * @param  $id
     * @param  $setting
     * @return void
     */
    public function updateProduct($request, $id, $setting)
    {
        try {
            Log::info('=== ProductRepository::updateProduct - START ===');
            // FormData уже обработан в ProductUpdateRequest::prepareForValidation
            // Расширенное логирование для отладки
            Log::info('ProductRepository::updateProduct - request data', [
                'id' => $id,
                'product_type' => $request->input('product_type'),
                'has_product_type' => $request->has('product_type'),
                'name' => $request->input('name'),
                'shop_id' => $request->input('shop_id'),
                'type_id' => $request->input('type_id'),
                'has_variations' => $request->has('variations'),
                'has_variation_options' => $request->has('variation_options'),
                'variations' => $request->input('variations'),
                'variation_options' => $request->input('variation_options'),
                'all_keys' => array_keys($request->all()),
                'all_data' => $request->all(),
                'content_type' => $request->header('Content-Type'),
                'is_json' => $request->isJson(),
                'is_form_data' => $request->hasFile('video') || str_contains($request->header('Content-Type', ''), 'multipart/form-data'),
            ]);
            
            $product = $this->findOrFail($id);

            if (is_array($request['metas'])) {
                foreach ($request['metas'] as $key => $value) {
                    $metas[$value['key']] = $value['value'];
                    $product->setMeta($metas);
                }
            }

            // Обработка категории: теперь одна категория вместо массива
            try {
                if (isset($request['category_id'])) {
                    // Преобразуем category_id в число (если передан объект, извлекаем id)
                    $categoryId = is_array($request['category_id']) ? ($request['category_id']['id'] ?? $request['category_id'][0] ?? null) : $request['category_id'];
                    $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;
                    
                    if ($categoryId) {
                        $product->categories()->sync([$categoryId]);
                    } else {
                        // Если category_id пустой или невалидный, удаляем все категории
                        $product->categories()->detach();
                    }
                } elseif (isset($request['categories'])) {
                // Для обратной совместимости: если передан массив categories
                $categoryIds = is_array($request['categories']) ? $request['categories'] : [$request['categories']];
                // Преобразуем все ID в числа
                $categoryIds = array_filter(array_map(function($catId) {
                    if (is_array($catId)) {
                        return isset($catId['id']) && is_numeric($catId['id']) ? (int)$catId['id'] : null;
                    }
                    return is_numeric($catId) ? (int)$catId : null;
                }, $categoryIds));
                
                // Используем только первую категорию (теперь товар может иметь только одну категорию)
                if (!empty($categoryIds)) {
                    $firstCategoryId = reset($categoryIds);
                    $product->categories()->sync([$firstCategoryId]);
                } else {
                    $product->categories()->detach();
                }
            }
            } catch (\Exception $e) {
                \Log::error('ProductRepository::updateProduct - Error processing category', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Не прерываем обновление товара из-за ошибки категории
            }
            
            // Обработка значений атрибутов товара
            try {
                if (isset($request['attribute_values']) && is_array($request['attribute_values'])) {
                    $attributeValuesData = [];
                    foreach ($request['attribute_values'] as $attributeId => $value) {
                        // Преобразуем attributeId в число
                        $attrId = is_numeric($attributeId) ? (int)$attributeId : null;
                        if (!$attrId) {
                            continue; // Пропускаем невалидные ID
                        }
                        
                        // Пропускаем пустые значения
                        if (empty($value) && $value !== '0' && $value !== 0) {
                            continue;
                        }
                        
                        // Преобразуем значение в строку, если это массив (для multiselect)
                        $finalValue = is_array($value) ? implode(',', $value) : (string)$value;
                        
                        // Если значение - это объект (из SelectInput), извлекаем value
                        if (is_array($value) && isset($value['value'])) {
                            $finalValue = is_array($value['value']) ? implode(',', $value['value']) : (string)$value['value'];
                            $attributeValueId = isset($value['attribute_value_id']) && is_numeric($value['attribute_value_id']) ? (int)$value['attribute_value_id'] : null;
                        } else {
                            $attributeValueId = null;
                        }
                        
                        $attributeValuesData[$attrId] = [
                            'value' => $finalValue,
                            'attribute_value_id' => $attributeValueId,
                        ];
                    }
                    
                    // Используем sync для обновления значений атрибутов
                    if (!empty($attributeValuesData)) {
                        $product->attributes()->sync($attributeValuesData);
                    } else {
                        // Если все значения пустые, удаляем все связи с атрибутами
                        $product->attributes()->detach();
                    }
                }
            } catch (\Exception $e) {
                \Log::error('ProductRepository::updateProduct - Error processing attributes', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Не прерываем обновление товара из-за ошибки атрибутов
            }
            if (isset($request['tags'])) {
                \Log::info('ProductRepository::updateProduct - Processing tags', [
                    'tags' => $request['tags'],
                    'type_id' => $product->type_id ?? 1,
                    'language' => $request['language'] ?? DEFAULT_LANGUAGE,
                ]);
                
                $tagIds = $this->processTags($request['tags'], $product->type_id ?? 1, $request['language'] ?? DEFAULT_LANGUAGE);
                
                \Log::info('ProductRepository::updateProduct - Processed tag IDs', [
                    'tag_ids' => $tagIds,
                ]);
                
                if (!empty($tagIds)) {
                    $product->tags()->sync($tagIds);
                } else {
                    // Если тегов нет, отвязываем все теги
                    $product->tags()->sync([]);
                }
            }
            if (isset($request['dropoff_locations'])) {
                $product->dropoff_locations()->sync($request['dropoff_locations']);
            }
            if (isset($request['pickup_locations'])) {
                $product->pickup_locations()->sync($request['pickup_locations']);
            }
            if (isset($request['variations'])) {
                // variations - это массив ID attribute_value для вариативных товаров
                // Используем sync() для правильной синхронизации связей
                $variationIds = is_array($request['variations']) 
                    ? array_filter(array_map('intval', $request['variations']))
                    : [];
                Log::info('ProductRepository::updateProduct - Syncing variations', [
                    'variation_ids' => $variationIds,
                    'count' => count($variationIds),
                ]);
                $product->variations()->sync($variationIds);
                Log::info('ProductRepository::updateProduct - Variations synced successfully');
            } else {
                Log::warning('ProductRepository::updateProduct - variations not found in request', [
                    'has_variations_key' => $request->has('variations'),
                    'product_type' => $request->input('product_type'),
                    'current_product_type' => $product->product_type,
                ]);
            }
            if (isset($request['persons'])) {
                $product->persons()->sync($request['persons']);
            }
            if (isset($request['features'])) {
                $product->features()->sync($request['features']);
            }
            if (isset($request['deposits'])) {
                $product->deposits()->sync($request['deposits']);
            }
            if (isset($request['digital_file'])) {
                $file = $request['digital_file'];
                if (isset($file['id'])) {
                    $product->digital_file()->where('id', $file['id'])->update($file);
                } else {
                    $product->digital_file()->create($file);
                }
            }
            if (isset($request['variation_options'])) {
                Log::info('ProductRepository::updateProduct - variation_options found', [
                    'has_upsert' => isset($request['variation_options']['upsert']),
                    'has_delete' => isset($request['variation_options']['delete']),
                    'variation_options_type' => gettype($request['variation_options']),
                    'variation_options' => $request['variation_options'],
                ]);
                if (isset($request['variation_options']['upsert'])) {
                    $upsertOptions = $request['variation_options']['upsert'];
                    Log::info('ProductRepository::updateProduct - Processing variation_options', [
                        'count' => is_array($upsertOptions) ? count($upsertOptions) : 0,
                        'upsert' => $upsertOptions,
                    ]);
                    
                    if (is_array($upsertOptions) && count($upsertOptions) > 0) {
                        foreach ($upsertOptions as $key => $variation) {
                            // Обрабатываем options - должны быть массивом объектов {name, value}
                            if (isset($variation['options'])) {
                                if (is_string($variation['options'])) {
                                    $variation['options'] = json_decode($variation['options'], true);
                                }
                                // Убеждаемся, что options - это массив
                                if (!is_array($variation['options'])) {
                                    $variation['options'] = [];
                                }
                            } else {
                                $variation['options'] = [];
                            }
                            
                            // Преобразуем числовые значения в правильные типы
                            if (isset($variation['price'])) {
                                $variation['price'] = (string)$variation['price'];
                            }
                            if (isset($variation['sale_price']) && $variation['sale_price'] !== null && $variation['sale_price'] !== '') {
                                $variation['sale_price'] = (string)$variation['sale_price'];
                            } else {
                                $variation['sale_price'] = null;
                            }
                            if (isset($variation['quantity'])) {
                                $variation['quantity'] = (int)$variation['quantity'];
                            }
                            
                            // Убеждаемся, что title установлен
                            if (!isset($variation['title']) || empty($variation['title'])) {
                                // Генерируем title из options
                                if (is_array($variation['options']) && count($variation['options']) > 0) {
                                    $variation['title'] = implode('/', array_map(function($opt) {
                                        return $opt['value'] ?? '';
                                    }, $variation['options']));
                                } else {
                                    $variation['title'] = 'Variant ' . ($key + 1);
                                }
                            }
                            
                            // Обрабатываем is_digital и digital_file
                        if (isset($variation['is_digital']) && $variation['is_digital']) {
                                $file = $variation['digital_file'] ?? null;
                            unset($variation['digital_file']);
                            } else {
                                $file = null;
                            }
                            
                            Log::info('ProductRepository::updateProduct - Processing variation', [
                                'id' => $variation['id'] ?? 'new',
                                'variation' => $variation,
                            ]);

                            if (isset($variation['is_digital']) && $variation['is_digital'] && $file) {
                                if (isset($variation['id']) && $variation['id']) {
                                $product->variation_options()->where('id', $variation['id'])->update($variation);
                                try {
                                    $updated_variation = Variation::findOrFail($variation['id']);
                                } catch (Exception $e) {
                                    throw new ModelNotFoundException(NOT_FOUND);
                                }
                                if (TRANSLATION_ENABLED) {
                                    Variation::where('sku', $updated_variation->sku)->where('id', '=', $updated_variation->id)->update([
                                        'price' => $updated_variation->price,
                                        'sale_price' => $updated_variation->sale_price,
                                        'quantity' => $updated_variation->quantity,
                                    ]);
                                }
                                if (isset($file['id'])) {
                                    $updated_variation->digital_file()->where('id', $file['id'])->update($file);
                                } else {
                                    $updated_variation->digital_file()->create($file);
                                }
                            } else {
                                $new_variation = $product->variation_options()->create($variation);
                                    if ($file && isset($file['attachment_id']) && isset($file['url'])) {
                                $new_variation->digital_file()->create($file);
                                    }
                            }
                        } else {
                                if (isset($variation['id']) && $variation['id']) {
                                $product->variation_options()->where('id', $variation['id'])->update($variation);
                            } else {
                                $product->variation_options()->create($variation);
                                }
                            }
                        }
                    }
                }
                if (isset($request['variation_options']['delete'])) {
                    foreach ($request['variation_options']['delete'] as $key => $id) {
                        try {
                            $product->variation_options()->where('id', $id)->delete();
                        } catch (Exception $e) {
                            //
                        }
                    }
                }
            } else {
                Log::warning('ProductRepository::updateProduct - variation_options not found in request', [
                    'has_variation_options_key' => $request->has('variation_options'),
                    'product_type' => $request->input('product_type'),
                    'current_product_type' => $product->product_type,
                ]);
            }
            // КРИТИЧНО: Обрабатываем GALLERY ДО only() чтобы гарантировать попадание в $data
            $processedGallery = null;
            if ($request->has('gallery') || $request->has('gallery[]')) {
                $galleryInput = $request->input('gallery') ?? $request->input('gallery[]');
                
                Log::info('ProductRepository::updateProduct - Gallery received BEFORE only()', [
                    'has_gallery' => $request->has('gallery'),
                    'has_gallery_array' => $request->has('gallery[]'),
                    'gallery_type' => gettype($galleryInput),
                    'gallery_is_array' => is_array($galleryInput),
                    'gallery_input' => is_array($galleryInput) ? count($galleryInput) : 'not_array',
                ]);
                
                // Нормализуем gallery
                $normalizedGallery = [];
                
                // Если это JSON строка - декодируем
                if (is_string($galleryInput)) {
                    $decoded = json_decode($galleryInput, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $normalizedGallery = $decoded;
                    } else {
                        Log::warning('ProductRepository::updateProduct - Failed to decode gallery JSON', [
                            'gallery_string' => substr($galleryInput, 0, 200),
                        ]);
                    }
                } 
                // Если это массив - используем как есть
                elseif (is_array($galleryInput)) {
                    $normalizedGallery = $galleryInput;
                }
                // Если это объект - преобразуем в массив
                elseif (is_object($galleryInput)) {
                    $normalizedGallery = [$galleryInput];
                }
                
                // Фильтруем и нормализуем элементы
                $normalizedGallery = array_values(array_filter(array_map(function($item) {
                    if (!is_array($item) && !is_object($item)) {
                        return null;
                    }
                    
                    $item = is_object($item) ? (array)$item : $item;
                    
                    // Проверяем наличие хотя бы одного поля изображения
                    if (empty($item['thumbnail']) && empty($item['original']) && empty($item['url'])) {
                        return null;
                    }
                    
                    return [
                        'id' => isset($item['id']) ? (int)$item['id'] : null,
                        'thumbnail' => $item['thumbnail'] ?? $item['url'] ?? '',
                        'original' => $item['original'] ?? $item['url'] ?? $item['thumbnail'] ?? '',
                        'file_name' => $item['file_name'] ?? null,
                    ];
                }, $normalizedGallery)));
                
                $processedGallery = $normalizedGallery;
                
                Log::info('ProductRepository::updateProduct - Gallery normalized BEFORE only()', [
                    'gallery_count' => count($normalizedGallery),
                ]);
            }
            
            $data = $request->only($this->dataArray);
            $data['sale_price'] = isset($request['sale_price']) ? $request['sale_price'] : null;
            
            // КРИТИЧНО: Принудительно устанавливаем обработанную gallery в $data
            if ($processedGallery !== null) {
                $data['gallery'] = $processedGallery;
                Log::info('ProductRepository::updateProduct - Gallery set in $data after only()', [
                    'gallery_count' => count($processedGallery),
                ]);
            } else {
                // Если gallery не была передана, НЕ трогаем существующую
                unset($data['gallery']);
                Log::info('ProductRepository::updateProduct - Gallery not in request, keeping existing (removed from $data)');
            }
            
            // ВАЖНО: Логируем что попало в $data после обработки
            \Log::info('ProductRepository::updateProduct - Data after processing', [
                'has_gallery_in_data' => isset($data['gallery']),
                'gallery_in_data' => isset($data['gallery']) ? (is_array($data['gallery']) ? count($data['gallery']) : 'not_array') : 'NOT_SET',
                'gallery_type' => isset($data['gallery']) ? gettype($data['gallery']) : 'NOT_SET',
            ]);
            
            // ВАЖНО: type_id должен быть обязательным для всех товаров
            if (!isset($data['type_id']) || empty($data['type_id'])) {
                if ($product->type_id) {
                    $data['type_id'] = $product->type_id;
                    Log::info('ProductRepository::updateProduct - Preserving existing type_id', [
                        'type_id' => $product->type_id,
                    ]);
                } else {
                    Log::warning('ProductRepository::updateProduct - Product has no type_id!', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                    ]);
                }
            }

            if ($setting->options["isProductReview"]) {
                $data['status'] = $this->checkProductForPublish($request, $product);
            }

            // ВАЖНО: Проверяем product_type из запроса, если не передан - берем из существующего товара
            $requestProductType = $request->input('product_type');
            $finalProductType = $requestProductType ?? $product->product_type;
            
            Log::info('ProductRepository::updateProduct - Product type check', [
                'request_product_type' => $requestProductType,
                'current_product_type' => $product->product_type,
                'final_product_type' => $finalProductType,
                'is_variable' => $finalProductType == ProductType::VARIABLE,
                'is_simple' => $finalProductType == ProductType::SIMPLE,
            ]);
            
            if ($finalProductType == ProductType::VARIABLE) {
                $data['price'] = NULL;
                $data['sale_price'] = NULL;
                $data['sku'] = NULL;
                // ВАЖНО: Устанавливаем product_type в данных для обновления
                $data['product_type'] = ProductType::VARIABLE;
            }
            if ($finalProductType == ProductType::SIMPLE) {
                // Проверяем наличие price в $data, если нет - берем из существующего товара или используем 0
                $price = $data['price'] ?? $product->price ?? 0;
                $data['max_price'] = $price;
                $data['min_price'] = $price;
                // Убеждаемся что price тоже установлен
                if (!isset($data['price'])) {
                    $data['price'] = $price;
                }
            }

            if (!empty($request->slug) &&  $request->slug != $product->slug) {
                // Извлекаем существующий 12-значный код из текущего slug товара
                $currentSlugParsed = Product::parseSlugId($product->slug);
                $existingCode = $currentSlugParsed['code'] ?? null;
                
                // Генерируем новый slug из запроса пользователя
                $newSlugFromRequest = $this->makeSlug($request);
                
                // Если у текущего товара есть 12-значный код, сохраняем его
                // Проверяем, что код - это именно 12 цифр (новый формат)
                if ($existingCode && preg_match('/^\d{12}$/', $existingCode)) {
                    // Извлекаем базовую часть нового slug (убираем код, если он был добавлен makeSlug)
                    $newSlugParsed = Product::parseSlugId($newSlugFromRequest);
                    $newSlugBase = $newSlugFromRequest;
                    
                    // Если новый slug уже содержит 12-значный код, убираем его
                    if ($newSlugParsed['code'] && preg_match('/^\d{12}$/', $newSlugParsed['code'])) {
                        // Убираем код из нового slug, чтобы использовать старый код
                        $newSlugBase = preg_replace('/-\d{12}$/', '', $newSlugFromRequest);
                    }
                    
                    // Сохраняем старый код с новым slug
                    $finalSlug = $newSlugBase . '-' . $existingCode;
                    
                    // Проверяем уникальность (исключая текущий товар)
                    $exists = Product::where('slug', $finalSlug)
                        ->where('id', '!=', $product->id)
                        ->exists();
                    
                    if ($exists) {
                        // Если slug с этим кодом уже существует, используем стандартную логику makeSlug
                        // (makeSlug сам добавит уникальный код)
                        $finalSlug = $newSlugFromRequest;
                    }
                } else {
                    // Если кода нет или он не 12-значный, используем стандартную логику
                    $finalSlug = $newSlugFromRequest;
                }
                
                $data['slug'] = $finalSlug;

                if (TRANSLATION_ENABLED) {
                    $this->where('slug', $product->slug)->where('id', '!=', $product->id)->update([
                        'slug' => $finalSlug
                    ]);
                }
            }

            // Обработка загрузки видео при обновлении
            // Логируем информацию о запросе
            Log::info('ProductRepository::updateProduct - проверка видео', [
                'product_id' => $product->id,
                'hasFile_video' => $request->hasFile('video'),
                'has_video' => $request->has('video'),
                'all_files' => array_keys($request->allFiles()),
                'all_input_keys' => array_keys($request->all()),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method(),
            ]);
            
            if ($request->hasFile('video')) {
                try {
                    // Удаляем старые видео
                    $product->videos()->delete();
                    
                    $file = $request->file('video');
                    Log::info('ProductRepository::updateProduct - сохраняем видео', [
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'product_id' => $product->id,
                    ]);
                    
                    // Проверяем размер файла (40MB максимум)
                    $maxSize = 40 * 1024 * 1024; // 40MB
                    if ($file->getSize() > $maxSize) {
                        throw new \Exception('Video file size exceeds maximum allowed size of 40MB');
                    }
                    
                    $key = 'products/videos/' . uniqid('', true) . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    
                    // Загружаем в S3
                    try {
                        $fileContents = file_get_contents($file->getRealPath());
                        $fileSize = strlen($fileContents);
                        
                        Log::info('ProductRepository::updateProduct - загружаем видео в S3', [
                            's3_key' => $key,
                            'file_size' => $fileSize,
                            'file_path' => $file->getRealPath(),
                            'file_exists' => file_exists($file->getRealPath()),
                        ]);
                        
                        $result = Storage::disk('s3')->put($key, $fileContents, [
                            'visibility' => 'public',
                            'CacheControl' => 'public, max-age=86400',
                            'ContentType' => $file->getMimeType() ?: 'video/mp4',
                        ]);
                        
                        // Проверяем, что файл действительно загружен
                        $existsInS3 = Storage::disk('s3')->exists($key);
                        
                        Log::info('ProductRepository::updateProduct - видео загружено в S3', [
                            's3_key' => $key,
                            'upload_result' => $result,
                            'exists_in_s3' => $existsInS3,
                            's3_url' => Storage::disk('s3')->url($key),
                        ]);
                    } catch (\Exception $e) {
                        Log::error('ProductRepository::updateProduct - ошибка загрузки видео в S3', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw new \Exception('Failed to upload video to S3: ' . $e->getMessage());
                    }
                    
                    // Создаем запись в БД
                    try {
                        $videoRecord = \Marvel\Database\Models\ProductVideo::create([
                            'product_id' => $product->id,
                            'url' => $key,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                        ]);
                        
                        Log::info('ProductRepository::updateProduct - видео сохранено в БД', [
                            'video_id' => $videoRecord->id,
                            'product_id' => $product->id,
                            'video_url' => $videoRecord->url,
                            's3_key' => $key,
                            'video_exists_in_db' => \Marvel\Database\Models\ProductVideo::where('id', $videoRecord->id)->exists(),
                            'product_videos_count' => \Marvel\Database\Models\ProductVideo::where('product_id', $product->id)->count(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error('ProductRepository::updateProduct - ошибка создания записи видео в БД', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Пытаемся удалить файл из S3, если запись в БД не создалась
                        try {
                            Storage::disk('s3')->delete($key);
                        } catch (\Exception $deleteException) {
                            Log::error('ProductRepository::updateProduct - ошибка удаления файла из S3', [
                                'error' => $deleteException->getMessage(),
                            ]);
                        }
                        throw new \Exception('Failed to create video record in database: ' . $e->getMessage());
                    }
                    
                    // Обрабатываем флаг video_as_cover (правильно обрабатываем строку '1' как boolean)
                    $videoAsCover = false;
                    if ($request->has('video_as_cover')) {
                        $videoAsCoverValue = $request->input('video_as_cover');
                        // Обрабатываем строку '1', 'true', boolean true и т.д.
                        $videoAsCover = in_array($videoAsCoverValue, ['1', 'true', true, 1], true);
                    }
                    
                    // Устанавливаем мета-данные ДО оптимизации
                    if ($videoAsCover) {
                        $product->setMeta('video_as_cover', true);
                        $product->setMeta('cover_video_id', $videoRecord->id);
                    } else {
                        $product->removeMeta('video_as_cover');
                        $product->removeMeta('cover_video_id');
                    }
                    
                    // Оптимизируем видео (может занять время, но не должно блокировать сохранение)
                    try {
                        $optimizationResult = \Marvel\Helpers\VideoOptimizer::optimizeVideo($videoRecord, $file->getRealPath());
                        
                        // Если установлена галочка "Сделать обложкой" и оптимизация прошла успешно
                        if ($videoAsCover && $optimizationResult) {
                            // Обновляем превью видео после оптимизации
                            $videoRecord->refresh();
                            if ($videoRecord->poster_url) {
                                // Используем постер видео как первое изображение
                                $currentImage = $product->image;
                                $currentGallery = $product->gallery ?? [];
                                
                                // Если image - массив, берем первый элемент
                                if (is_array($currentImage)) {
                                    $firstImage = $currentImage[0] ?? null;
                                } else {
                                    $firstImage = $currentImage;
                                }
                                
                                // Создаем новую структуру изображений с постером видео первым
                                $newImage = [
                                    'thumbnail' => $videoRecord->poster_url,
                                    'original' => $videoRecord->poster_url,
                                    'id' => null,
                                ];
                                
                                // Если есть существующее изображение, добавляем его в gallery
                                if ($firstImage) {
                                    $newGallery = array_merge([$newImage], [$firstImage], $currentGallery);
                                } else {
                                    $newGallery = array_merge([$newImage], $currentGallery);
                                }
                                
                                $data['image'] = $newImage;
                                $data['gallery'] = $newGallery;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('ProductRepository::updateProduct - ошибка оптимизации видео', [
                            'video_id' => $videoRecord->id ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Не прерываем обновление товара из-за ошибки оптимизации видео
                    }
                } catch (\Exception $e) {
                    Log::error('ProductRepository::updateProduct - критическая ошибка при загрузке видео', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'product_id' => $product->id,
                    ]);
                    throw $e; // Пробрасываем исключение дальше
                }
            } elseif ($request->has('existing_video')) {
                // Сохраняем существующее видео
                $product->videos()->delete();
                \Marvel\Database\Models\ProductVideo::create([
                    'product_id' => $product->id,
                    'url' => $request->input('existing_video')
                ]);
            }
            
            // Обрабатываем флаг video_as_cover (только если видео НЕ загружается)
            // Если видео загружается, флаг уже обработан выше
            if (!$request->hasFile('video') && $request->has('video_as_cover')) {
                $videoAsCoverValue = $request->input('video_as_cover');
                $videoAsCover = in_array($videoAsCoverValue, ['1', 'true', true, 1], true);
                
                if ($videoAsCover) {
                    // Если галочка установлена, находим последнее видео и устанавливаем флаг
                    $lastVideo = $product->videos()->latest()->first();
                    if ($lastVideo) {
                        $product->setMeta('video_as_cover', true);
                        $product->setMeta('cover_video_id', $lastVideo->id);
                    }
                } else {
                    // Если галочка снята, удаляем флаг
                    $product->removeMeta('video_as_cover');
                    $product->removeMeta('cover_video_id');
                }
            }
            
            // Защита: internal_article нельзя изменить после создания
            // ВАЖНО: internal_article генерируется ТОЛЬКО автоматически при создании
            // Удаляем internal_article из данных обновления (не принимается из API)
            unset($data['internal_article']);
            
            // Если артикул еще не установлен (для старых записей), генерируем его
            if (empty($product->internal_article)) {
                $data['internal_article'] = \Marvel\Services\ArticleGeneratorService::generateProductArticle();
            }

            // ВАЖНО: Логируем финальные данные перед обновлением
            \Log::info('ProductRepository::updateProduct - Final data before update', [
                'has_gallery' => isset($data['gallery']),
                'gallery' => isset($data['gallery']) ? (is_array($data['gallery']) ? count($data['gallery']) . ' items' : gettype($data['gallery'])) : 'NOT_SET',
                'gallery_count' => isset($data['gallery']) && is_array($data['gallery']) ? count($data['gallery']) : 0,
                'gallery_type' => isset($data['gallery']) ? gettype($data['gallery']) : 'NOT_SET',
                'all_data_keys' => array_keys($data),
            ]);
            
            // КРИТИЧНО: Сохраняем gallery отдельно перед update, чтобы гарантировать сохранение
            if (isset($data['gallery'])) {
                $galleryToSave = $data['gallery'];
                // Временно убираем gallery из $data
                unset($data['gallery']);
                
                // Обновляем товар БЕЗ gallery
                $product->update($data);
                
                // КРИТИЧНО: Принудительно устанавливаем и сохраняем gallery
                $product->gallery = $galleryToSave;
                $product->save();
                
                Log::info('ProductRepository::updateProduct - Gallery saved separately', [
                    'gallery_count' => is_array($galleryToSave) ? count($galleryToSave) : 0,
                ]);
            } else {
                // Если gallery нет в $data, просто обновляем
                $product->update($data);
            }
            
            // ВАЖНО: Проверяем что gallery сохранился
            $product->refresh();
            \Log::info('ProductRepository::updateProduct - After update', [
                'product_id' => $product->id,
                'saved_gallery' => $product->gallery ?? 'NOT_SET',
                'saved_gallery_count' => is_array($product->gallery) ? count($product->gallery) : 0,
                'saved_gallery_type' => gettype($product->gallery),
                'saved_gallery_is_array' => is_array($product->gallery),
            ]);
            
            // Загружаем videos после обновления
            try {
                if (class_exists(\Marvel\Database\Models\ProductVideo::class)) {
                    $product->load('videos');
                    Log::info('ProductRepository::updateProduct - videos загружены после update', [
                        'product_id' => $product->id,
                        'videos_count' => $product->videos ? $product->videos->count() : 0,
                        'videos_in_db' => \Marvel\Database\Models\ProductVideo::where('product_id', $product->id)->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('ProductRepository::updateProduct - не удалось загрузить videos', [
                    'error' => $e->getMessage(),
                    'product_id' => $product->id,
                ]);
            }
            
            // ВАЖНО: Удаляем вариации только если товар действительно стал простым
            // И только если он был вариативным до этого
            if ($finalProductType === ProductType::SIMPLE && $product->product_type === ProductType::VARIABLE) {
                Log::info('ProductRepository::updateProduct - Converting from variable to simple, deleting variations');
                $product->variations()->delete();
                $product->variation_options()->delete();
            }
            
            // КРИТИЧНО: НЕ вызываем $product->save() здесь, так как gallery уже сохранена выше
            // Если нужно сохранить другие изменения - они уже сохранены через update()
            // Дополнительный save() может перезаписать gallery

            // Отладочная информация после обновления
            \Log::info('Product updated successfully:', [
                'product_id' => $id,
                'final_image' => $product->fresh()->image ?? 'not_set',
                'final_gallery' => $product->fresh()->gallery ?? 'not_set',
                'updated_fields' => array_keys($data)
            ]);

            if (TRANSLATION_ENABLED) {
                $this->where('sku', $product->sku)->where('id', '=',  $product->id)->update([
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'max_price' => $product->max_price,
                    'min_price' => $product->min_price,
                    'unit' => $product->unit,
                    'quantity' => $product->quantity,
                ]);
            }
            
            // Обновляем videos перед возвратом (на случай если они были изменены)
            try {
                if (class_exists(\Marvel\Database\Models\ProductVideo::class)) {
                    $product->load('videos');
                }
            } catch (\Exception $e) {
                Log::warning('ProductRepository::updateProduct - не удалось загрузить videos перед возвратом', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Логируем информацию о видео для отладки
            Log::info('ProductRepository::updateProduct - возвращаем товар с videos', [
                'product_id' => $product->id,
                'videos_count' => $product->videos ? $product->videos->count() : 0,
                'has_videos_relation' => $product->relationLoaded('videos'),
                'videos_in_db_count' => \Marvel\Database\Models\ProductVideo::where('product_id', $product->id)->count(),
            ]);
            
            // Преобразуем в массив для проверки
            try {
                $productArray = $product->toArray();
                Log::info('ProductRepository::updateProduct - product toArray videos', [
                    'product_id' => $product->id,
                    'has_videos_in_array' => isset($productArray['videos']),
                    'videos_array_count' => isset($productArray['videos']) && is_array($productArray['videos']) ? count($productArray['videos']) : 0,
                ]);
            } catch (\Exception $e) {
                // Игнорируем ошибки преобразования
            }
            
            // Загружаем связи для финальной проверки
            // ВАЖНО: Загружаем type, чтобы фронтенд получил полную информацию
            $product->load('variations', 'variation_options', 'type', 'shop', 'categories');
            Log::info('ProductRepository::updateProduct - Product updated successfully', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_type' => $product->product_type,
                'type_id' => $product->type_id,
                'type_loaded' => $product->relationLoaded('type'),
                'type_name' => $product->type ? $product->type->name : 'NOT_LOADED',
                'variations_count' => $product->variations->count(),
                'variation_options_count' => $product->variation_options->count(),
            ]);
            
            // ВАЖНО: Проверяем что gallery возвращается в ответе
            $product->refresh(); // Обновляем из БД перед возвратом
            Log::info('ProductRepository::updateProduct - Returning product with gallery', [
                'product_id' => $product->id,
                'gallery' => $product->gallery ?? 'NOT_SET',
                'gallery_type' => gettype($product->gallery),
                'gallery_count' => is_array($product->gallery) ? count($product->gallery) : 0,
                'gallery_is_array' => is_array($product->gallery),
            ]);
            
            Log::info('=== ProductRepository::updateProduct - END (SUCCESS) ===');
            return $product;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * getBestSellingProducts
     *
     * @param $request
     * @return void
     */

    public function getBestSellingProducts($request)
    {
        $limit = $request->limit ? $request->limit : 10;
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $range = !empty($request->range) && $request->range !== 'undefined'  ? $request->range : '';
        $type_id = $request->type_id ? $request->type_id : '';
        if (isset($request->type_slug) && empty($type_id)) {
            try {
                $type = Type::where('slug', $request->type_slug)->where('language', $language)->firstOrFail();
                $type_id = $type->id;
            } catch (ModelNotFoundException $e) {
                throw new MarvelException(NOT_FOUND);
            }
        }

        $products_query = Product::leftJoin('order_product', 'order_product.product_id', 'products.id')
            ->leftJoin('orders', 'order_product.order_id', '=', 'orders.id')
            ->with(['type', 'shop'])
            ->selectRaw('products.*, sum(order_product.order_quantity) total_sales')
            ->where('orders.parent_id', null)
            ->where('orders.order_status', 'order-completed')
            ->where('orders.language', $language)
            ->groupBy('order_product.product_id')
            ->orderBy('total_sales', 'desc');

        if (isset($request->shop_id)) {
            $products_query = $products_query->where('shop_id', "=", $request->shop_id);
        }
        if ($range) {
            $products_query = $products_query->whereDate('created_at', '>', Carbon::now()->subDays($range));
        }
        if ($type_id) {
            $products_query = $products_query->where('type_id', '=', $type_id);
        }
        return $products_query->take($limit)->get();
    }

    public function fetchRelated($slug, $limit = 10, $language = DEFAULT_LANGUAGE)
    {
        try {
            $product    = $this->findOneByFieldOrFail('slug', $slug);
            $categories = $product->categories->pluck('id');

            return $this->where('language', $language)->whereHas('categories', function ($query) use ($categories) {
                $query->whereIn('categories.id', $categories);
            })->with('type')->limit($limit)->get();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getUnavailableProducts($from, $to)
    {
        $_blockedDates = Availability::whereDate('from', '<=', $from)
            ->whereDate('to', '>=', $to)
            ->get()->groupBy('product_id');

        $unavailableProducts = [];

        foreach ($_blockedDates as $productId =>  $date) {
            if (!$this->isProductAvailableAt($from, $to, $productId, $date)) {
                $unavailableProducts[] = $productId;
            }
        }
        return $unavailableProducts;
    }

    public function isProductAvailableAt($from, $to, $productId, $_blockedDates, $requestedQuantity = 1)
    {
        $quantity = 0;
        try {
            $product = Product::findOrFail($productId);
        } catch (\Throwable $th) {
            throw $th;
        }

        foreach ($_blockedDates as $singleDate) {
            $period = Period::make($singleDate['from'], $singleDate['to'], Precision::DAY, Boundaries::EXCLUDE_END);
            $range = Period::make($from, $to, Precision::DAY, Boundaries::EXCLUDE_END);
            if ($period->overlapsWith($range)) {
                $quantity += $singleDate->order_quantity;
            }
        }
        return $product->quantity - $quantity > $requestedQuantity;
    }


    public function fetchBlockedDatesForAProductInRange($from, $to, $productId)
    {
        return  Availability::where('product_id', $productId)->whereDate('from', '>=', $from)->whereDate('to', '<=', $to)->get();
    }

    public function fetchBlockedDatesForAVariationInRange($from, $to, $variation_id)
    {
        return  Availability::where('bookable_id', $variation_id)->where('bookable_type', 'Marvel\Database\Models\Variation')->whereDate('from', '>=', $from)->whereDate('to', '<=', $to)->get();
    }

    public function isVariationAvailableAt($from, $to, $variationId, $_blockedDates, $requestedQuantity)
    {
        $quantity = 0;
        try {
            $variation = Variation::findOrFail($variationId);
        } catch (\Throwable $th) {
            throw $th;
        }

        foreach ($_blockedDates as $singleDate) {
            $period = Period::make($singleDate['from'], $singleDate['to'], Precision::DAY, Boundaries::EXCLUDE_END);
            $range = Period::make($from, $to, Precision::DAY, Boundaries::EXCLUDE_END);
            if ($period->overlapsWith($range)) {
                $quantity += $singleDate->order_quantity;
            }
        }
        return $variation->quantity - $quantity >= $requestedQuantity;
    }


    public function calculatePrice($bookedDay, $product_id, $variation_id, $quantity, $persons, $dropoff_location_id, $pickup_location_id, $deposits, $features)
    {
        $price = 0;
        $person_price = 0;
        $deposit_price = 0;
        $feature_price = 0;
        $dropoff_location_price = 0;
        $pickup_location_price = 0;

        if ($variation_id) {
            $variation_price = $this->calculateVariationPrice($variation_id);
            $price += $variation_price * $bookedDay * $quantity;
        } else {
            $product_price = $this->calculateProductPrice($product_id);
            $price += $product_price * $bookedDay * $quantity;
        }
        if ($dropoff_location_id) {
            $dropoff_location_price = $this->calculateLocationPrice($dropoff_location_id);
        }
        if ($pickup_location_id) {
            $pickup_location_price = $this->calculateLocationPrice($pickup_location_id);
        }
        if ($features) {
            $feature_price = $this->calculateResourcePrice($features);
        }
        if ($persons) {
            $person_price = $this->calculateResourcePrice($persons);
        }
        if ($deposits) {
            $deposit_price = $this->calculateResourcePrice($deposits);
        }

        return [
            'totalPrice' => $price + $person_price + $deposit_price + $feature_price + $dropoff_location_price, $pickup_location_price,
            'personPrice' => $person_price,
            'depositPrice' => $deposit_price,
            'featurePrice' => $feature_price,
            'dropoffLocationPrice' => $dropoff_location_price,
            'pickupLocationPrice' => $pickup_location_price
        ];
    }

    public function calculateProductPrice($product_id)
    {
        try {
            $product = Product::findOrFail($product_id);
        } catch (\Throwable $th) {
            throw $th;
        }
        return $product->sale_price ? $product->sale_price : $product->price;
    }

    public function calculateVariationPrice($variation_id)
    {
        try {
            $variation = Variation::findOrFail($variation_id);
        } catch (\Throwable $th) {
            throw $th;
        }
        return $variation->sale_price ? $variation->sale_price : $variation->price;
    }

    public function calculateLocationPrice($location_id)
    {
        try {
            $location = Resource::findOrFail($location_id);
        } catch (\Throwable $th) {
            throw $th;
        }
        return $location->price;
    }

    public function calculateResourcePrice($resources)
    {
        $price = 0;
        foreach ($resources as $resource_id) {
            try {
                $resource = Resource::findOrFail($resource_id);
            } catch (\Throwable $th) {
                throw $th;
            }
            if ($resource->price) {
                $price += $resource->price;
            }
        }
        return $price;
    }

    public function customSlugify($text, string $divider = '-')
    {
        // Используем globalSlugify для генерации slug с 12-значным кодом
        return globalSlugify($text, Product::class, 'slug', $divider);
    }

    /**
     * Обрабатывает теги: создает новые теги если их нет, возвращает массив ID существующих тегов
     * 
     * @param array $tags Массив тегов (могут быть ID или объекты с name)
     * @param int $typeId ID типа товара
     * @param string $language Язык тега
     * @return array Массив ID тегов
     */
    protected function processTags($tags, $typeId = 1, $language = 'ru')
    {
        if (empty($tags) || !is_array($tags)) {
            Log::info('ProductRepository::processTags - Empty or invalid tags array', [
                'tags' => $tags,
            ]);
            return [];
        }

        $tagIds = [];

        foreach ($tags as $index => $tag) {
            Log::info('ProductRepository::processTags - Processing tag', [
                'index' => $index,
                'tag' => $tag,
                'tag_type' => gettype($tag),
            ]);
            
            // Если тег - это ID (число или строка с числом)
            if (is_numeric($tag)) {
                // Проверяем, существует ли тег с таким ID
                $existingTag = Tag::find($tag);
                if ($existingTag) {
                    $tagIds[] = $existingTag->id;
                    Log::info('ProductRepository::processTags - Found existing tag by ID', [
                        'tag_id' => $existingTag->id,
                        'tag_name' => $existingTag->name,
                    ]);
                } else {
                    Log::warning('ProductRepository::processTags - Tag ID not found', [
                        'tag_id' => $tag,
                    ]);
                }
                continue;
            }

            // Если тег - это объект или массив с полем name
            $tagName = null;
            if (is_array($tag) && isset($tag['name'])) {
                $tagName = trim($tag['name']);
            } elseif (is_object($tag) && isset($tag->name)) {
                $tagName = trim($tag->name);
            } elseif (is_string($tag)) {
                // Если это просто строка - используем как имя тега
                $tagName = trim($tag);
            }

            if (empty($tagName)) {
                Log::warning('ProductRepository::processTags - Empty tag name', [
                    'tag' => $tag,
                ]);
                continue;
            }

            // Пытаемся найти существующий тег по имени и языку
            $existingTag = Tag::where('name', $tagName)
                ->where('language', $language)
                ->first();

            if ($existingTag) {
                // Тег существует, используем его ID
                $tagIds[] = $existingTag->id;
                Log::info('ProductRepository::processTags - Found existing tag by name', [
                    'tag_id' => $existingTag->id,
                    'tag_name' => $tagName,
                    'language' => $language,
                ]);
            } else {
                // Тег не существует, создаем новый
                try {
                    $slug = Str::slug($tagName);
                    
                    // Проверяем уникальность slug для данного языка
                    $slugCount = Tag::where('slug', $slug)
                        ->where('language', $language)
                        ->count();
                    
                    if ($slugCount > 0) {
                        $slug = $slug . '-' . ($slugCount + 1);
                    }

                    $newTag = Tag::create([
                        'name' => $tagName,
                        'slug' => $slug,
                        'language' => $language,
                        'type_id' => $typeId,
                    ]);

                    $tagIds[] = $newTag->id;

                    Log::info('ProductRepository::processTags - Created new tag', [
                        'tag_id' => $newTag->id,
                        'tag_name' => $tagName,
                        'tag_slug' => $slug,
                        'language' => $language,
                        'type_id' => $typeId,
                    ]);
                } catch (\Exception $e) {
                    Log::error('ProductRepository::processTags - Error creating tag', [
                        'tag_name' => $tagName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Продолжаем обработку остальных тегов даже если один не удалось создать
                }
            }
        }

        // Убираем дубликаты и возвращаем массив ID
        $uniqueTagIds = array_unique($tagIds);
        Log::info('ProductRepository::processTags - Final tag IDs', [
            'tag_ids' => $uniqueTagIds,
            'count' => count($uniqueTagIds),
        ]);
        
        return $uniqueTagIds;
    }
}
