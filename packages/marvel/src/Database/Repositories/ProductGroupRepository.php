<?php

namespace Marvel\Database\Repositories;

use Marvel\Database\Models\ProductGroup;
use Prettus\Repository\Eloquent\BaseRepository;

class ProductGroupRepository extends BaseRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ProductGroup::class;
    }

    /**
     * Создание ProductGroup
     */
    public function storeProductGroup($request)
    {
        $data = $request->all();
        
        // Создаем ProductGroup
        $group = ProductGroup::create([
            'title' => $data['title'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'main_image' => $data['main_image'] ?? null,
            'gallery' => $data['gallery'] ?? [],
            'video' => $data['video'] ?? [],
            'category_id' => $data['category_id'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'shop_id' => $data['shop_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'brand_type' => $data['brand_type'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'language' => $data['language'] ?? 'ru',
            'meta' => $data['meta'] ?? [],
            // Габариты
            'height' => $data['height'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'weight' => $data['weight'] ?? null,
        ]);

        // Синхронизируем теги если переданы
        if (isset($data['tags']) && is_array($data['tags'])) {
            $group->tags()->sync($data['tags']);
        }

        // Загружаем связи
        $group->load(['type', 'category', 'shop', 'tags']);

        return $group;
    }

    /**
     * Обновление ProductGroup
     */
    public function updateProductGroup($request, $id)
    {
        $group = ProductGroup::findOrFail($id);
        $data = $request->all();

        $group->update([
            'title' => $data['title'] ?? $group->title,
            'slug' => $data['slug'] ?? $group->slug,
            'description' => $data['description'] ?? $group->description,
            'short_description' => $data['short_description'] ?? $group->short_description,
            'main_image' => $data['main_image'] ?? $group->main_image,
            'gallery' => $data['gallery'] ?? $group->gallery,
            'video' => $data['video'] ?? $group->video,
            'category_id' => $data['category_id'] ?? $group->category_id,
            'type_id' => $data['type_id'] ?? $group->type_id,
            'shop_id' => $data['shop_id'] ?? $group->shop_id,
            'brand_id' => $data['brand_id'] ?? $group->brand_id,
            'brand_type' => $data['brand_type'] ?? $group->brand_type,
            'status' => $data['status'] ?? $group->status,
            'language' => $data['language'] ?? $group->language,
            'meta' => $data['meta'] ?? $group->meta,
            // Габариты
            'height' => $data['height'] ?? $group->height,
            'length' => $data['length'] ?? $group->length,
            'width' => $data['width'] ?? $group->width,
            'weight' => $data['weight'] ?? $group->weight,
        ]);

        // Синхронизируем теги если переданы
        if (isset($data['tags']) && is_array($data['tags'])) {
            $group->tags()->sync($data['tags']);
        }

        // Обновляем модель из базы данных чтобы получить актуальные значения
        $group->refresh();
        
        // Загружаем связи
        $group->load(['type', 'category', 'shop', 'tags']);

        return $group;
    }

    /**
     * Удаление ProductGroup
     */
    public function deleteProductGroup($id)
    {
        $group = ProductGroup::findOrFail($id);
        
        // Удаляем все связанные SKU
        $group->skus()->delete();
        
        // Удаляем группу
        $group->delete();
        
        return true;
    }

    /**
     * Получить группу со всеми SKU
     */
    public function findBySlugWithSkus($slug)
    {
        return ProductGroup::where('slug', $slug)
            ->with(['activeSkus', 'activeSkus.propertyValues', 'activeSkus.propertyValues.attribute'])
            ->first();
    }
}


