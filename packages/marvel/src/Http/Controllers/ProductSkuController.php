<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\ProductSku;
use Marvel\Database\Repositories\ProductSkuRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\ProductSkuCreateRequest;
use Marvel\Http\Requests\ProductSkuUpdateRequest;
use Marvel\Http\Requests\GenerateSkusRequest;

class ProductSkuController extends CoreController
{
    public $repository;

    public function __construct(ProductSkuRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of SKUs for a group.
     *
     * @param Request $request
     * @param int|null $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $groupId = null)
    {
        try {
            // groupId может быть из URL или из query параметра
            $groupIdParam = $groupId ?? $request->input('group_id');
            
            $limit = $request->limit ?? 50;
            $page = $request->page ?? 1;
            
            // Создаем query builder с отношениями через модель напрямую
            $query = ProductSku::with(['group', 'propertyValues', 'propertyValues.attribute']);
            
            // Фильтруем по group_id если передан
            if ($groupIdParam) {
                $query = $query->where('group_id', $groupIdParam);
            }
            
            // Применяем сортировку если указана
            $orderBy = $request->input('orderBy', 'created_at');
            $sortedBy = $request->input('sortedBy', 'desc');
            $query = $query->orderBy($orderBy, $sortedBy);
            
            // Пагинация
            $skus = $query->paginate($limit, ['*'], 'page', $page);
            
            return response()->json($skus);
        } catch (Exception $e) {
            Log::error('ProductSkuController::index - ERROR', [
                'groupId' => $groupId ?? $request->input('group_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Store a newly created SKU in storage.
     *
     * @param ProductSkuCreateRequest $request
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ProductSkuCreateRequest $request, $groupId)
    {
        try {
            $sku = $this->repository->storeProductSku($request, $groupId);
            
            // Логируем что возвращаем
            Log::info('ProductSkuController::store - Returning SKU', [
                'sku_id' => $sku->id,
                'has_propertyValues' => $sku->relationLoaded('propertyValues'),
                'propertyValues_count' => $sku->propertyValues ? $sku->propertyValues->count() : 0,
                'propertyValues' => $sku->propertyValues ? $sku->propertyValues->map(function($pv) {
                    return [
                        'id' => $pv->id,
                        'value' => $pv->value,
                        'attribute_id' => $pv->pivot->property_id ?? null,
                        'attribute_value_id' => $pv->pivot->property_value_id ?? null,
                        'attribute' => $pv->attribute ? [
                            'id' => $pv->attribute->id,
                            'name' => $pv->attribute->name,
                        ] : null,
                    ];
                })->toArray() : null,
            ]);
            
            return response()->json($sku, 201);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductSkuController::store - ERROR', [
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Display the specified SKU.
     * Поддерживает формат: {sku-slug}-{sku-id} или {sku-slug} (старый формат)
     *
     * @param string $groupSlug Slug группы товара
     * @param string $skuSlugId Slug или Slug-ID SKU
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function show($groupSlug, $skuSlugId)
    {
        try {
            // Парсим slug и id SKU
            $parsed = ProductSku::parseSlugId($skuSlugId);
            $skuSlug = $parsed['slug'];
            $skuId = $parsed['id'];

            // Если передан ID, ищем по ID
            if ($skuId) {
                $sku = ProductSku::with(['group', 'propertyValues', 'propertyValues.attribute'])
                    ->findOrFail($skuId);

                // Проверяем, совпадает ли slug SKU
                if ($sku->slug !== $skuSlug) {
                    $correctUrl = "/api/element/{$sku->group->slug}/{$sku->slug}-{$sku->id}";
                    Log::info('ProductSku - 301 redirect', [
                        'from' => "{$groupSlug}/{$skuSlugId}",
                        'to' => $correctUrl,
                        'reason' => 'sku_slug_mismatch',
                    ]);
                    return response()->json([
                        'redirect' => $correctUrl,
                        'status' => 301,
                    ], 301);
                }

                // Проверяем, совпадает ли slug группы
                if ($sku->group->slug !== $groupSlug) {
                    $correctUrl = "/api/element/{$sku->group->slug}/{$sku->slug}-{$sku->id}";
                    Log::info('ProductSku - 301 redirect', [
                        'from' => "{$groupSlug}/{$skuSlugId}",
                        'to' => $correctUrl,
                        'reason' => 'group_slug_mismatch',
                    ]);
                    return response()->json([
                        'redirect' => $correctUrl,
                        'status' => 301,
                    ], 301);
                }

                return response()->json($sku);
            }

            // Если ID не передан, ищем по slug (включая историю)
            $sku = ProductSku::findBySlugOrHistory($skuSlug);

            if (!$sku) {
                throw new MarvelException(NOT_FOUND);
            }

            // Загружаем связи
            $sku->load(['group', 'propertyValues', 'propertyValues.attribute']);

            // Если нашли через историю или slug не совпадает, делаем редирект
            if ($sku->slug !== $skuSlug || $sku->group->slug !== $groupSlug) {
                $correctUrl = "/api/element/{$sku->group->slug}/{$sku->slug}-{$sku->id}";
                Log::info('ProductSku - 301 redirect', [
                    'from' => "{$groupSlug}/{$skuSlugId}",
                    'to' => $correctUrl,
                    'reason' => 'old_slug',
                ]);
                return response()->json([
                    'redirect' => $correctUrl,
                    'status' => 301,
                ], 301);
            }

            return response()->json($sku);

        } catch (Exception $e) {
            Log::error('ProductSkuController::show - ERROR', [
                'groupSlug' => $groupSlug,
                'skuSlugId' => $skuSlugId,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Получить SKU по slug (универсальный метод для единого формата URL)
     * Используется в роуте /products/{slug} для унификации
     *
     * @param string $slug Slug SKU
     * @param string|null $language Язык
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug, $language = null)
    {
        try {
            $language = $language ?? DEFAULT_LANGUAGE;
            
            // Ищем SKU по slug
            $sku = ProductSku::where('slug', $slug)
                ->where('language', $language)
                ->with(['group', 'propertyValues', 'propertyValues.attribute', 'properties'])
                ->first();

            if (!$sku) {
                // Пробуем найти через историю slug
                $sku = ProductSku::findBySlugOrHistory($slug);
                if ($sku) {
                    $sku->load(['group', 'propertyValues', 'propertyValues.attribute', 'properties']);
                }
            }

            if (!$sku) {
                throw new MarvelException(NOT_FOUND);
            }

            // Логируем что возвращаем
            Log::info('ProductSkuController::getBySlug - Returning SKU', [
                'sku_id' => $sku->id,
                'slug' => $slug,
                'has_propertyValues' => $sku->relationLoaded('propertyValues'),
                'propertyValues_count' => $sku->propertyValues ? $sku->propertyValues->count() : 0,
                'propertyValues' => $sku->propertyValues ? $sku->propertyValues->map(function($pv) {
                    return [
                        'id' => $pv->id,
                        'value' => $pv->value,
                        'attribute_id' => $pv->pivot->property_id ?? null,
                        'attribute_value_id' => $pv->pivot->property_value_id ?? null,
                        'attribute' => $pv->attribute ? [
                            'id' => $pv->attribute->id,
                            'name' => $pv->attribute->name,
                        ] : null,
                    ];
                })->toArray() : null,
            ]);

            return response()->json($sku);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductSkuController::getBySlug - ERROR', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Update the specified SKU in storage.
     *
     * @param ProductSkuUpdateRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProductSkuUpdateRequest $request, $id)
    {
        try {
            $sku = $this->repository->updateProductSku($request, $id);
            
            // Логируем что возвращаем
            Log::info('ProductSkuController::update - Returning SKU', [
                'sku_id' => $sku->id,
                'has_propertyValues' => $sku->relationLoaded('propertyValues'),
                'propertyValues_count' => $sku->propertyValues ? $sku->propertyValues->count() : 0,
                'propertyValues' => $sku->propertyValues ? $sku->propertyValues->map(function($pv) {
                    return [
                        'id' => $pv->id,
                        'value' => $pv->value,
                        'attribute_id' => $pv->pivot->property_id ?? null,
                        'attribute' => $pv->attribute ? [
                            'id' => $pv->attribute->id,
                            'name' => $pv->attribute->name,
                        ] : null,
                    ];
                })->toArray() : null,
            ]);
            
            return response()->json($sku);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductSkuController::update - ERROR', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Remove the specified SKU from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $this->repository->deleteProductSku($id);
            return response()->json(['message' => 'Product SKU deleted successfully']);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductSkuController::destroy - ERROR', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Get SKU by slug
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSkuBySlug(Request $request)
    {
        try {
            $slug = $request->slug;
            if (!$slug) {
                throw new MarvelException(SOMETHING_WENT_WRONG);
            }

            $sku = $this->repository->findBySlug($slug);
            return response()->json($sku);
        } catch (Exception $e) {
            Log::error('ProductSkuController::getSkuBySlug - ERROR', [
                'slug' => $request->slug ?? 'null',
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Generate all possible SKU combinations from attributes
     *
     * @param GenerateSkusRequest $request
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSkus(GenerateSkusRequest $request, $groupId)
    {
        try {
            $attributeIds = $request->attribute_ids ?? [];
            $basePrice = $request->base_price ?? 0;

            $skus = $this->repository->generateSkusFromAttributes($groupId, $attributeIds, $basePrice);
            
            return response()->json([
                'message' => 'SKUs generated successfully',
                'count' => count($skus),
                'skus' => $skus,
            ], 201);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductSkuController::generateSkus - ERROR', [
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
