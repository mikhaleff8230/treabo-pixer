<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ProductSkuUpdateRequest extends FormRequest
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
     */
    protected function prepareForValidation()
    {
        $requestData = $this->all();
        foreach ($requestData as $key => $value) {
            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$key => $decoded]);
                }
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
        $skuId = $this->route('id');

        return [
            'product_group_id'             => ['sometimes', 'exists:Marvel\Database\Models\ProductGroup,id'],
            'title'                        => ['sometimes', 'string', 'max:255'],
            'sku'                          => ['sometimes', 'string', 'max:255', Rule::unique('product_skus', 'sku')->ignore($skuId)],
            'price'                        => ['sometimes', 'numeric', 'min:0'],
            'quantity'                     => ['sometimes', 'integer', 'min:0'],
            'slug'                         => ['sometimes', 'string'],
            'sale_price'                   => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'language'                     => ['sometimes', 'string'],
            'is_active'                    => ['sometimes', 'boolean'],
            'main_image'                   => ['nullable', 'array'],
            'gallery'                      => ['nullable', 'array'],
            // Атрибуты (поддерживаем оба формата: property_values и properties)
            'property_values'              => ['nullable', 'array'],
            'property_values.*'            => ['integer', 'exists:Marvel\Database\Models\AttributeValue,id'],
            'properties'                   => ['nullable', 'array'],
            'properties.*.attribute_id'     => ['required_with:properties', 'integer', 'exists:Marvel\Database\Models\Attribute,id'],
            'properties.*.attribute_value_id' => ['required_with:properties', 'integer', 'exists:Marvel\Database\Models\AttributeValue,id'],
            'meta'                         => ['nullable', 'array'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}


