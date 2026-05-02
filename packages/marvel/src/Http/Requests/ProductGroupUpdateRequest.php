<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Marvel\Enums\ProductStatus;

class ProductGroupUpdateRequest extends FormRequest
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
        $productStatus = [
            ProductStatus::PUBLISH,
            ProductStatus::DRAFT,
        ];

        return [
            'title'                        => ['sometimes', 'string', 'max:255'],
            'type_id'                      => ['sometimes', 'exists:Marvel\Database\Models\Type,id'],
            'shop_id'                      => ['sometimes', 'exists:Marvel\Database\Models\Shop,id'],
            'category_id'                  => ['sometimes', 'exists:Marvel\Database\Models\Category,id'],
            'status'                       => ['sometimes', Rule::in($productStatus)],
            
            // Габариты и вес - обязательны при наличии
            'height'                       => ['sometimes', 'numeric', 'min:1'],
            'length'                       => ['sometimes', 'numeric', 'min:1'],
            'width'                        => ['sometimes', 'numeric', 'min:1'],
            'weight'                       => ['sometimes', 'numeric', 'min:1'],
            
            'slug'                         => ['sometimes', 'string'],
            'description'                  => ['nullable', 'string'],
            'short_description'            => ['nullable', 'string'],
            'language'                     => ['sometimes', 'string'],
            'main_image'                   => ['nullable', 'array'],
            'gallery'                      => ['nullable', 'array'],
            'video'                        => ['nullable', 'array'],
            'categories'                   => ['nullable', 'array'],
            'tags'                         => ['nullable', 'array'],
            'meta'                         => ['nullable', 'array'],
            'brand_id'                     => ['nullable', 'integer'],
            'brand_type'                   => ['nullable', 'string'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}


