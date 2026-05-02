<?php


namespace Marvel\Database\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Marvel\Database\Models\Attribute;
use Marvel\Database\Models\AttributeValue;
use Marvel\Exceptions\MarvelException;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttributeRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name'        => 'like',
        'shop_id',
        'language',
        'is_common',
    ];

    protected $dataArray = [
        'name',
        'slug',
        'shop_id',
        'language',
        'type',
        'input_type',
        'is_required',
        'description',
        'unit',
        'min_value',
        'max_value',
        'validation_regex',
        'sort_order',
        'is_active',
        'display_type',
        'is_common',
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
        return Attribute::class;
    }

    public function storeAttribute($request)
    {
        try {
            // Получаем данные из запроса
            $data = is_array($request) ? $request : $request->all();
            
            // Генерируем slug через makeSlug (как для товаров)
            if (is_array($request)) {
                $tempRequest = new \Illuminate\Http\Request($data);
                $data['slug'] = $this->makeSlug($tempRequest);
            } else {
                $data['slug'] = $this->makeSlug($request);
            }
            
            // Собираем данные для создания атрибута
            $attributeData = [];
            foreach ($this->dataArray as $field) {
                // Для boolean и enum полей разрешаем 0, false, и пустые строки
                $isBooleanOrEnum = in_array($field, ['is_required', 'is_active', 'display_type', 'input_type', 'type']);
                
                if (isset($data[$field])) {
                    if ($isBooleanOrEnum) {
                        // Для boolean и enum полей сохраняем значение даже если оно пустое или 0
                        // Но для display_type и type устанавливаем дефолтные значения если пусто
                        if ($field === 'display_type' && (empty($data[$field]) || $data[$field] === '')) {
                            $attributeData[$field] = 'input';
                        } elseif ($field === 'type' && (empty($data[$field]) || $data[$field] === '')) {
                            $attributeData[$field] = 'text';
                        } else {
                            $attributeData[$field] = $data[$field];
                        }
                    } elseif ($data[$field] !== null && $data[$field] !== '') {
                        // Для остальных полей проверяем на пустоту
                        $attributeData[$field] = $data[$field];
                    }
                }
            }
            
            // Убеждаемся что обязательные поля есть
            if (empty($attributeData['name']) && empty($data['name'])) {
                throw new \Exception('Name is required');
            }
            
            // Если name не попал в attributeData, берем из data
            if (empty($attributeData['name']) && !empty($data['name'])) {
                $attributeData['name'] = $data['name'];
            }
            
            // shop_id должен быть в валидированных данных от AttributeRequest
            if (empty($attributeData['shop_id']) && !empty($data['shop_id'])) {
                $attributeData['shop_id'] = $data['shop_id'];
            }
            
            // Убеждаемся что display_type есть (если передан, но не попал в attributeData)
            if (isset($data['display_type'])) {
                if (!isset($attributeData['display_type']) || empty($attributeData['display_type'])) {
                    $attributeData['display_type'] = !empty($data['display_type']) ? $data['display_type'] : 'input';
                }
            } elseif (!isset($attributeData['display_type'])) {
                // Если display_type не передан, устанавливаем дефолтное значение
                $attributeData['display_type'] = 'input';
            }
            
            // Убеждаемся что type есть (если передан, но не попал в attributeData)
            if (isset($data['type'])) {
                if (!isset($attributeData['type']) || empty($attributeData['type'])) {
                    $attributeData['type'] = !empty($data['type']) ? $data['type'] : 'text';
                }
            } elseif (!isset($attributeData['type'])) {
                // Если type не передан, устанавливаем дефолтное значение
                $attributeData['type'] = 'text';
            }
            
            // Проверяем обязательные поля перед созданием
            if (empty($attributeData['name'])) {
                throw new \Exception('Name is required');
            }
            
            // Создаем атрибут
            $attribute = $this->create($attributeData);
            
            // Если slug пустой или числовой, используем customSlugify (как для товаров)
            if (empty($attribute->slug) || is_numeric($attribute->slug)) {
                if (is_array($request)) {
                    $tempRequest = new \Illuminate\Http\Request(['name' => $attribute->name]);
                    $attribute->slug = $this->makeSlug($tempRequest);
                } else {
                    $request->merge(['name' => $attribute->name]);
                    $attribute->slug = $this->makeSlug($request);
                }
                $attribute->save();
            }
            
            // Создаем значения атрибутов если они есть
            if (isset($data['values']) && is_array($data['values']) && count($data['values'])) {
                foreach ($data['values'] as $valueData) {
                    if (!empty($valueData['value'])) {
                        $attribute->values()->create([
                            'value' => $valueData['value'],
                            'meta' => $valueData['meta'] ?? null,
                            'language' => $valueData['language'] ?? $attributeData['language'] ?? 'en',
                        ]);
                    }
                }
            }
            
            // Возвращаем атрибут с загруженными значениями для текущего языка
            $valuesLanguage = $attributeData['language'] ?? $attribute->language ?? DEFAULT_LANGUAGE;
            return $attribute->load(['values' => function($query) use ($valuesLanguage) {
                $query->where('language', $valuesLanguage);
            }]);
        } catch (\Throwable $th) {
            Log::error('Error storing attribute: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'request' => is_array($request) ? $request : $request->all(),
            ]);
            throw new HttpException(400, COULD_NOT_CREATE_THE_RESOURCE . ': ' . $th->getMessage());
        }
    }

    public function updateAttribute($request, $attribute)
    {
        try {
            // Получаем данные из запроса
            $data = is_array($request) ? $request : $request->all();
            
            // Обновляем slug если он был изменен (как для товаров)
            if (!empty($data['slug']) && $data['slug'] != $attribute->slug) {
                if (is_array($request)) {
                    $tempRequest = new \Illuminate\Http\Request($data);
                    $data['slug'] = $this->makeSlug($tempRequest);
                } else {
                    $data['slug'] = $this->makeSlug($request);
                }
            }
            
            // Обрабатываем значения атрибутов если они есть
            // Определяем язык для значений атрибута
            $valuesLanguage = $data['language'] ?? $attribute->language ?? DEFAULT_LANGUAGE;
            
            if (isset($data['values']) && is_array($data['values'])) {
                $existingValueIds = [];
                $newValueIds = [];
                
                // Обновляем или создаем значения
                foreach ($data['values'] as $value) {
                    // Пропускаем пустые значения (без value)
                    if (empty($value['value']) && $value['value'] !== '0') {
                        // Если это существующее значение и оно стало пустым - удаляем его
                        if (isset($value['id']) && $value['id']) {
                            // Не добавляем в existingValueIds, чтобы оно было удалено
                            continue;
                        }
                        // Если новое значение пустое - просто пропускаем
                        continue;
                    }
                    
                    $valueLanguage = $value['language'] ?? $valuesLanguage;
                    
                    if (isset($value['id']) && $value['id']) {
                        // Обновляем существующее значение
                        $existingValueIds[] = $value['id'];
                        $updated = AttributeValue::where('id', $value['id'])
                            ->where('attribute_id', $attribute->id)
                            ->update([
                                'value' => $value['value'] ?? '',
                                'meta' => $value['meta'] ?? null,
                                'language' => $valueLanguage,
                            ]);
                    } else {
                        // Создаем новое значение (только если value не пустое)
                        if (!empty($value['value']) || $value['value'] === '0') {
                            $newValue = $attribute->values()->create([
                                'value' => $value['value'] ?? '',
                                'meta' => $value['meta'] ?? null,
                                'language' => $valueLanguage,
                            ]);
                            if ($newValue) {
                                $newValueIds[] = $newValue->id;
                            }
                        }
                    }
                }
                
                // Удаляем значения для текущего языка, которых нет в запросе
                // Получаем все существующие ID значений атрибута для текущего языка
                $allExistingIdsForLanguage = AttributeValue::where('attribute_id', $attribute->id)
                    ->where('language', $valuesLanguage)
                    ->pluck('id')
                    ->toArray();
                
                // Объединяем существующие и новые ID
                $allKeptIds = array_merge($existingValueIds, $newValueIds);
                
                // Удаляем только те значения текущего языка, которых нет в списке обновленных
                $idsToDelete = array_diff($allExistingIdsForLanguage, $allKeptIds);
                if (!empty($idsToDelete)) {
                    AttributeValue::where('attribute_id', $attribute->id)
                        ->whereIn('id', $idsToDelete)
                        ->delete();
                }
            } else {
                // Если values не переданы или пустой массив - удаляем только значения для текущего языка
                AttributeValue::where('attribute_id', $attribute->id)
                    ->where('language', $valuesLanguage)
                    ->delete();
            }
            
            // Проверяем какие колонки существуют в таблице
            $tableColumns = Schema::getColumnListing('attributes');
            
            // Собираем данные для обновления атрибута (только существующие колонки)
            $attributeData = [];
            foreach ($this->dataArray as $field) {
                // Пропускаем поля, которых нет в таблице
                if (!in_array($field, $tableColumns)) {
                    continue;
                }
                
                // Для boolean и enum полей разрешаем 0, false, и пустые строки
                $isBooleanOrEnum = in_array($field, ['is_required', 'is_active', 'display_type', 'input_type', 'type']);
                
                if (isset($data[$field])) {
                    if ($isBooleanOrEnum) {
                        // Для boolean и enum полей сохраняем значение даже если оно пустое или 0
                        // Но для display_type и type устанавливаем дефолтные значения если пусто
                        if ($field === 'display_type' && (empty($data[$field]) || $data[$field] === '')) {
                            $attributeData[$field] = 'input';
                        } elseif ($field === 'type' && (empty($data[$field]) || $data[$field] === '')) {
                            $attributeData[$field] = 'text';
                    } else {
                            $attributeData[$field] = $data[$field];
                        }
                    } elseif ($data[$field] !== null && $data[$field] !== '') {
                        // Для остальных полей проверяем на пустоту
                        $attributeData[$field] = $data[$field];
                    }
                }
            }
            
            // Убеждаемся что обязательные поля есть
            if (empty($attributeData['name']) && empty($data['name'])) {
                throw new \Exception('Name is required');
            }
            
            // Если name не попал в attributeData, берем из data
            if (empty($attributeData['name']) && !empty($data['name'])) {
                $attributeData['name'] = $data['name'];
            }
            
            if (empty($attributeData['shop_id']) && empty($attribute->shop_id)) {
                // shop_id может быть не передан при обновлении, оставляем текущий
                if (!empty($data['shop_id'])) {
                    $attributeData['shop_id'] = $data['shop_id'];
                }
            }
            
            // Убеждаемся что display_type обновляется если передан и колонка существует
            if (in_array('display_type', $tableColumns)) {
                if (isset($data['display_type'])) {
                    // Если display_type передан, используем его значение
                    if (!isset($attributeData['display_type']) || empty($attributeData['display_type'])) {
                        $attributeData['display_type'] = !empty($data['display_type']) ? $data['display_type'] : 'input';
                    }
                } elseif (!isset($attributeData['display_type'])) {
                    // Если display_type не передан, оставляем текущее значение или устанавливаем дефолт
                    $attributeData['display_type'] = $attribute->display_type ?? 'input';
                }
            }
            
            // Убеждаемся что type обновляется если передан и колонка существует
            if (in_array('type', $tableColumns)) {
                if (isset($data['type'])) {
                    // Если type передан, используем его значение
                    if (!isset($attributeData['type']) || empty($attributeData['type'])) {
                        $attributeData['type'] = !empty($data['type']) ? $data['type'] : 'text';
                    }
                } elseif (!isset($attributeData['type'])) {
                    // Если type не передан, оставляем текущее значение или устанавливаем дефолт
                    $attributeData['type'] = $attribute->type ?? 'text';
                }
            }
            
            // Обновляем атрибут только если есть данные для обновления
            if (!empty($attributeData)) {
                $attribute->update($attributeData);
            }
            
            // Возвращаем атрибут с загруженными значениями для текущего языка
            $valuesLanguage = $data['language'] ?? $attribute->language ?? DEFAULT_LANGUAGE;
            return $this->with(['values' => function($query) use ($valuesLanguage) {
                $query->where('language', $valuesLanguage);
            }])->findOrFail($attribute->id);
        } catch (\Throwable $th) {
            Log::error('Error updating attribute: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'request' => is_array($request) ? $request : $request->all(),
                'attribute_id' => $attribute->id,
            ]);
            throw new HttpException(400, COULD_NOT_UPDATE_THE_RESOURCE . ': ' . $th->getMessage());
        }
    }
}
