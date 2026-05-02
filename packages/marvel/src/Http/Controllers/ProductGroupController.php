<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\ProductGroup;
use Marvel\Database\Repositories\ProductGroupRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\ProductGroupCreateRequest;
use Marvel\Http\Requests\ProductGroupUpdateRequest;

class ProductGroupController extends CoreController
{
    public $repository;

    public function __construct(ProductGroupRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->limit ?? 15;
            $groups = $this->repository->with(['category', 'type', 'shop', 'activeSkus'])->paginate($limit);
            
            return response()->json($groups);
        } catch (Exception $e) {
            Log::error('ProductGroupController::index - ERROR', [
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProductGroupCreateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ProductGroupCreateRequest $request)
    {
        try {
            $group = $this->repository->storeProductGroup($request);
            return response()->json($group, 201);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductGroupController::store - ERROR', [
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Display the specified resource.
     * Поддерживает формат: /element/{slug}-{id} или /element/{slug} (старый формат)
     *
     * @param string $slugId
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function show($slugId)
    {
        try {
            // Парсим slug и id
            $parsed = ProductGroup::parseSlugId($slugId);
            $slug = $parsed['slug'];
            $id = $parsed['id'];

            // Если передан ID, ищем по ID
            if ($id) {
                $group = ProductGroup::with(['category', 'type', 'shop', 'activeSkus', 'activeSkus.propertyValues'])
                    ->findOrFail($id);

                // Проверяем, совпадает ли slug
                if ($group->slug !== $slug) {
                    // 301 редирект на правильный URL
                    $correctUrl = "/api/element/{$group->slug}-{$group->id}";
                    Log::info('ProductGroup - 301 redirect', [
                        'from' => $slugId,
                        'to' => $correctUrl,
                        'reason' => 'slug_mismatch',
                    ]);
                    return response()->json([
                        'redirect' => $correctUrl,
                        'status' => 301,
                    ], 301);
                }

                return response()->json($group);
            }

            // Если ID не передан, ищем по slug (включая историю)
            $group = ProductGroup::findBySlugOrHistory($slug);

            if (!$group) {
                throw new MarvelException(NOT_FOUND);
            }

            // Загружаем связи
            $group->load(['category', 'type', 'shop', 'activeSkus', 'activeSkus.propertyValues']);

            // Если нашли через историю или slug не совпадает, делаем редирект
            if ($group->slug !== $slug) {
                $correctUrl = "/api/element/{$group->slug}-{$group->id}";
                Log::info('ProductGroup - 301 redirect', [
                    'from' => $slugId,
                    'to' => $correctUrl,
                    'reason' => 'old_slug',
                ]);
                return response()->json([
                    'redirect' => $correctUrl,
                    'status' => 301,
                ], 301);
            }

            return response()->json($group);

        } catch (Exception $e) {
            Log::error('ProductGroupController::show - ERROR', [
                'slugId' => $slugId,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProductGroupUpdateRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProductGroupUpdateRequest $request, $id)
    {
        try {
            $group = $this->repository->updateProductGroup($request, $id);
            return response()->json($group);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductGroupController::update - ERROR', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $this->repository->deleteProductGroup($id);
            return response()->json(['message' => 'Product group deleted successfully']);
        } catch (MarvelException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('ProductGroupController::destroy - ERROR', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Get product group by slug (legacy method)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductGroupBySlug(Request $request)
    {
        try {
            $slugId = $request->slug;
            if (!$slugId) {
                throw new MarvelException(SOMETHING_WENT_WRONG);
            }

            // Используем тот же метод show
            return $this->show($slugId);
        } catch (Exception $e) {
            Log::error('ProductGroupController::getProductGroupBySlug - ERROR', [
                'slug' => $request->slug ?? 'null',
                'error' => $e->getMessage(),
            ]);
            throw new MarvelException(NOT_FOUND);
        }
    }
}

