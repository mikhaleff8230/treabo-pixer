<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductSkuCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Обрабатываем FormData ДО валидации
     */
    protected function prepareForValidation()
    {
        // Обрабатываем FormData - парсим JSON строки
        $requestData = $this->all();
        foreach ($requestData as $key => $value) {
            // Парсим JSON строки из FormData
            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
        
        // Если product_group_id не передан в теле запроса, но есть в route параметрах
        // (это происходит когда используется URL: product-groups/{groupId}/skus)
        if (!$this->has('product_group_id')) {
            $route = $this->route();
            if ($route && $route->hasParameter('groupId')) {
                $groupId = $route->parameter('groupId');
                $this->merge(['product_group_id' => $groupId]);
            }
        }
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // Обязательные поля
            'product_group_id'             => ['required', 'exists:Marvel\Database\Models\ProductGroup,id'],
            'title'                        => ['required', 'string', 'max:255'],
            'sku'                          => ['required', 'string', 'max:255', 'unique:Marvel\Database\Models\ProductSku,sku'],
            'price'                        => ['required', 'numeric', 'min:0'],
            'quantity'                     => ['required', 'integer', 'min:0'],
            
            // Необязательные поля
            'slug'                         => ['nullable', 'string'],
            'sale_price'                   => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'language'                     => ['nullable', 'string'],
            'is_active'                    => ['boolean'],
            
            // Медиа
            'main_image'                   => ['nullable', 'array'],
            'gallery'                      => ['nullable', 'array'],
            
            // Атрибуты (поддерживаем оба формата: property_values и properties)
            'property_values'              => ['nullable', 'array'],
            'property_values.*'            => ['integer', 'exists:Marvel\Database\Models\AttributeValue,id'],
            'properties'                   => ['nullable', 'array'],
            'properties.*.attribute_id'     => ['required_with:properties', 'integer', 'exists:Marvel\Database\Models\Attribute,id'],
            'properties.*.attribute_value_id' => ['required_with:properties', 'integer', 'exists:Marvel\Database\Models\AttributeValue,id'],
            
            // Метаданные
            'meta'                         => ['nullable', 'array'],
        ];
    }

    public function messages()
    {
        return [
            'product_group_id.required' => 'ID группы товара обязательно',
            'product_group_id.exists' => 'Группа товара не найдена',
            'title.required' => 'Название SKU обязательно',
            'sku.required' => 'Артикул обязателен для заполнения',
            'sku.unique' => 'Такой артикул уже существует',
            'price.required' => 'Цена обязательна для заполнения',
            'price.numeric' => 'Цена должна быть числом',
            'price.min' => 'Цена должна быть больше или равна 0',
            'quantity.required' => 'Количество обязательно для заполнения',
            'quantity.integer' => 'Количество должно быть целым числом',
            'quantity.min' => 'Количество должно быть больше или равно 0',
            'sale_price.numeric' => 'Цена со скидкой должна быть числом',
            'sale_price.lte' => 'Цена со скидкой должна быть меньше или равна обычной цене',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}


