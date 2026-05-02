<?php

namespace Marvel\Database\Repositories;

use Marvel\Database\Models\ProductSku;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Prettus\Repository\Eloquent\BaseRepository;

class ProductSkuRepository extends BaseRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ProductSku::class;
    }

    /**
     * Создание ProductSku
     */
    public function storeProductSku($request, $groupId)
    {
        $data = $request->all();
        
        // Логирование для отладки
        \Log::info('ProductSkuRepository::storeProductSku - Input data', [
            'groupId' => $groupId,
            'properties' => $data['properties'] ?? null,
            'properties_count' => isset($data['properties']) && is_array($data['properties']) ? count($data['properties']) : 0,
        ]);
        
        // Загружаем группу для генерации slug
        $group = \Marvel\Database\Models\ProductGroup::findOrFail($groupId);
        
        // Создаем SKU
        // ВАЖНО: internal_article генерируется ТОЛЬКО автоматически, игнорируем любые значения из запроса
        // Удаляем internal_article из данных, если он был передан (не должен приниматься из API)
        unset($data['internal_article']);
        // Генерируем внутренний артикул автоматически
        $data['internal_article'] = \Marvel\Services\ArticleGeneratorService::generateSkuArticle();
        
        $sku = new ProductSku([
            'group_id' => $groupId,
            'title' => $data['title'] ?? null,
            'slug' => $data['slug'] ?? null, // Если передан, будет использован, иначе сгенерируется автоматически
            'sku' => $data['sku'] ?? null,
            'internal_article' => $data['internal_article'],
            'price' => $data['price'] ?? 0,
            'old_price' => $data['old_price'] ?? null,
            'quantity' => $data['quantity'] ?? 0,
            'barcode' => $data['barcode'] ?? null,
            'image' => $data['image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'description' => $data['description'] ?? null,
            'is_digital' => $data['is_digital'] ?? false,
            'is_disable' => $data['is_disable'] ?? false,
            'language' => $data['language'] ?? 'ru',
            'meta' => $data['meta'] ?? [],
        ]);
        
        // Устанавливаем связь с группой для генерации slug
        $sku->setRelation('group', $group);
        
        // Сохраняем SKU (slug сгенерируется автоматически через Sluggable)
        $sku->save();

        // Если переданы свойства (properties), связываем их
        if (isset($data['properties']) && is_array($data['properties']) && count($data['properties']) > 0) {
            \Log::info('ProductSkuRepository::storeProductSku - Attaching properties', [
                'sku_id' => $sku->id,
                'properties' => $data['properties'],
            ]);
            $this->attachProperties($sku, $data['properties']);
        } else {
            \Log::warning('ProductSkuRepository::storeProductSku - No properties to attach', [
                'sku_id' => $sku->id,
                'has_properties' => isset($data['properties']),
                'properties_type' => isset($data['properties']) ? gettype($data['properties']) : 'not set',
            ]);
        }

        // Перезагружаем SKU со всеми связями
        $sku = $sku->fresh(['group', 'propertyValues', 'propertyValues.attribute']);
        
        \Log::info('ProductSkuRepository::storeProductSku - Returning SKU', [
            'sku_id' => $sku->id,
            'propertyValues_count' => $sku->propertyValues->count(),
            'propertyValues' => $sku->propertyValues->map(function($pv) {
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
            })->toArray(),
        ]);
        
        return $sku;
    }

    /**
     * Обновление ProductSku
     */
    public function updateProductSku($request, $id)
    {
        $sku = ProductSku::findOrFail($id);
        $data = $request->all();

        // Логирование для отладки
        \Log::info('ProductSkuRepository::updateProductSku - Input data', [
            'sku_id' => $id,
            'properties' => $data['properties'] ?? null,
            'has_properties_key' => array_key_exists('properties', $data),
            'properties_count' => isset($data['properties']) && is_array($data['properties']) ? count($data['properties']) : 0,
        ]);

        $updateData = [
            'title' => $data['title'] ?? $sku->title,
            'slug' => $data['slug'] ?? $sku->slug,
            'sku' => $data['sku'] ?? $sku->sku,
            'price' => $data['price'] ?? $sku->price,
            'old_price' => $data['old_price'] ?? $sku->old_price,
            'quantity' => $data['quantity'] ?? $sku->quantity,
            'barcode' => $data['barcode'] ?? $sku->barcode,
            'is_active' => $data['is_active'] ?? $sku->is_active,
            'description' => $data['description'] ?? $sku->description,
            'is_digital' => $data['is_digital'] ?? $sku->is_digital,
            'is_disable' => $data['is_disable'] ?? $sku->is_disable,
            'language' => $data['language'] ?? $sku->language,
            'meta' => $data['meta'] ?? $sku->meta,
        ];
        
        // Защита: internal_article нельзя изменить после создания
        // ВАЖНО: internal_article генерируется ТОЛЬКО автоматически при создании
        // Удаляем internal_article из данных обновления (не принимается из API)
        unset($updateData['internal_article']);
        
        // Если артикул еще не установлен (для старых записей), генерируем его
        if (empty($sku->internal_article)) {
            $updateData['internal_article'] = \Marvel\Services\ArticleGeneratorService::generateSkuArticle();
        }

        // Обработка изображения: если передано, обновляем; если null - очищаем
        if (isset($data['image'])) {
            $updateData['image'] = $data['image'];
        }

        $sku->update($updateData);

        // Обновляем свойства если переданы
        // Если передан пустой массив - очищаем свойства
        // Если не передан вообще - не трогаем свойства
        if (array_key_exists('properties', $data)) {
            if (is_array($data['properties']) && count($data['properties']) > 0) {
                \Log::info('ProductSkuRepository::updateProductSku - Attaching properties', [
                    'sku_id' => $id,
                    'properties' => $data['properties'],
                ]);
                $this->attachProperties($sku, $data['properties']);
            } else {
                // Очищаем все свойства
                \Log::info('ProductSkuRepository::updateProductSku - Clearing properties', [
                    'sku_id' => $id,
                ]);
                $sku->propertyValues()->detach();
            }
        } else {
            \Log::warning('ProductSkuRepository::updateProductSku - Properties key not found in data', [
                'sku_id' => $id,
                'data_keys' => array_keys($data),
            ]);
        }

        // Перезагружаем SKU со всеми связями для возврата
        $sku = $sku->fresh(['group', 'propertyValues', 'propertyValues.attribute']);
        
        \Log::info('ProductSkuRepository::updateProductSku - Returning SKU', [
            'sku_id' => $sku->id,
            'propertyValues_count' => $sku->propertyValues->count(),
            'propertyValues' => $sku->propertyValues->map(function($pv) {
                return [
                    'id' => $pv->id,
                    'attribute_id' => $pv->pivot->property_id ?? null,
                    'attribute_value_id' => $pv->pivot->property_value_id ?? null,
                    'attribute' => $pv->attribute ? [
                        'id' => $pv->attribute->id,
                        'name' => $pv->attribute->name,
                    ] : null,
                ];
            })->toArray(),
        ]);
        
        return $sku;
    }

    /**
     * Удаление ProductSku
     */
    public function deleteProductSku($id)
    {
        $sku = ProductSku::findOrFail($id);
        $sku->delete();
        return true;
    }

    /**
     * Получить SKU по slug
     */
    public function findBySlug($slug)
    {
        return ProductSku::where('slug', $slug)
            ->with(['group', 'propertyValues', 'propertyValues.attribute'])
            ->first();
    }

    /**
     * Генерация SKU из атрибутов (создание всех комбинаций)
     */
    public function generateSkusFromAttributes($groupId, $attributeIds, $basePrice = 0)
    {
        // Получаем атрибуты со значениями
        $attributes = Attribute::with('values')
            ->whereIn('id', $attributeIds)
            ->get();

        if ($attributes->isEmpty()) {
            return [];
        }

        // Генерируем все комбинации значений атрибутов
        $combinations = $this->generateCombinations($attributes);

        $skus = [];
        foreach ($combinations as $combination) {
            // Создаем название SKU из значений атрибутов
            $title = implode(' ', array_column($combination, 'value'));
            
            // Создаем SKU
            $sku = ProductSku::create([
                'group_id' => $groupId,
                'title' => $title,
                'price' => $basePrice,
                'quantity' => 0,
                'is_active' => true,
                'language' => 'ru',
            ]);

            // Привязываем свойства к SKU
            \Log::info('ProductSkuRepository::generateSkusFromAttributes - Before attachProperties', [
                'sku_id' => $sku->id,
                'combination' => $combination,
            ]);
            
            $this->attachProperties($sku, $combination);
            
            // Проверяем что атрибуты сохранились
            $sku = $sku->fresh(['propertyValues', 'propertyValues.attribute']);
            
            \Log::info('ProductSkuRepository::generateSkusFromAttributes - After attachProperties', [
                'sku_id' => $sku->id,
                'propertyValues_count' => $sku->propertyValues->count(),
                'propertyValues' => $sku->propertyValues->map(function($pv) {
                    return [
                        'id' => $pv->id,
                        'value' => $pv->value,
                        'attribute_id' => $pv->pivot->property_id ?? null,
                        'attribute' => $pv->attribute ? [
                            'id' => $pv->attribute->id,
                            'name' => $pv->attribute->name,
                        ] : null,
                    ];
                })->toArray(),
            ]);

            $skus[] = $sku;
        }

        return $skus;
    }

    /**
     * Генерация всех комбинаций атрибутов
     */
    private function generateCombinations($attributes)
    {
        $result = [[]];
        
        foreach ($attributes as $attribute) {
            $temp = [];
            foreach ($result as $combination) {
                foreach ($attribute->values as $value) {
                    $temp[] = array_merge($combination, [[
                        'attribute_id' => $attribute->id,
                        'attribute_value_id' => $value->id,
                        'value' => $value->value,
                    ]]);
                }
            }
            $result = $temp;
        }
        
        return $result;
    }

    /**
     * Привязка свойств (атрибутов) к SKU
     */
    private function attachProperties($sku, $properties)
    {
        $syncData = [];
        
        \Log::info('ProductSkuRepository::attachProperties - Input', [
            'sku_id' => $sku->id,
            'properties' => $properties,
        ]);
        
        foreach ($properties as $property) {
            if (isset($property['attribute_id']) && isset($property['attribute_value_id'])) {
                $syncData[$property['attribute_value_id']] = [
                    'property_id' => $property['attribute_id'],
                ];
            } else {
                \Log::warning('ProductSkuRepository::attachProperties - Invalid property format', [
                    'property' => $property,
                ]);
            }
        }
        
        \Log::info('ProductSkuRepository::attachProperties - Sync data', [
            'sku_id' => $sku->id,
            'syncData' => $syncData,
            'syncData_count' => count($syncData),
        ]);
        
        $result = $sku->propertyValues()->sync($syncData);
        
        \Log::info('ProductSkuRepository::attachProperties - Sync result', [
            'sku_id' => $sku->id,
            'result' => $result,
            'attached' => $result['attached'] ?? [],
            'detached' => $result['detached'] ?? [],
            'updated' => $result['updated'] ?? [],
        ]);
        
        // Проверяем, что данные действительно сохранились
        $sku->refresh();
        $loadedPropertyValues = $sku->propertyValues()->with('attribute')->get();
        
        \Log::info('ProductSkuRepository::attachProperties - Verification after sync', [
            'sku_id' => $sku->id,
            'loaded_count' => $loadedPropertyValues->count(),
            'loaded_propertyValues' => $loadedPropertyValues->map(function($pv) {
                return [
                    'id' => $pv->id,
                    'value' => $pv->value,
                    'pivot_property_id' => $pv->pivot->property_id ?? null,
                    'pivot_property_value_id' => $pv->pivot->property_value_id ?? null,
                    'attribute' => $pv->attribute ? [
                        'id' => $pv->attribute->id,
                        'name' => $pv->attribute->name,
                    ] : null,
                ];
            })->toArray(),
        ]);
        
        return $result;
    }
}


