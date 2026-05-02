<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Marvel\Enums\ProductStatus;

class ProductGroupCreateRequest extends FormRequest
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
            // Обязательные поля
            'title'                        => ['required', 'string', 'max:255'],
            'type_id'                      => ['required', 'exists:Marvel\Database\Models\Type,id'],
            'shop_id'                      => ['required', 'exists:Marvel\Database\Models\Shop,id'],
            'status'                       => ['required', Rule::in($productStatus)],
            
            // Обязательные поля - категория
            'category_id'                  => ['required', 'exists:Marvel\Database\Models\Category,id'],
            
            // Обязательные поля - габариты и вес
            'height'                       => ['required', 'numeric', 'min:1'],
            'length'                       => ['required', 'numeric', 'min:1'],
            'width'                        => ['required', 'numeric', 'min:1'],
            'weight'                       => ['required', 'numeric', 'min:1'],
            
            // Необязательные поля
            'slug'                         => ['nullable', 'string'],
            'description'                  => ['nullable', 'string'],
            'short_description'            => ['nullable', 'string'],
            'language'                     => ['nullable', 'string'],
            
            // Медиа
            'main_image'                   => ['nullable', 'array'],
            'gallery'                      => ['nullable', 'array'],
            'video'                        => ['nullable', 'array'],
            
            // Категории и теги - дополнительные
            'categories'                   => ['nullable', 'array'],
            'tags'                         => ['nullable', 'array'],
            
            // Метаданные
            'meta'                         => ['nullable', 'array'],
            
            // Бренд
            'brand_id'                     => ['nullable', 'integer'],
            'brand_type'                   => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Название группы обязательно для заполнения',
            'type_id.required' => 'Тип товара обязателен для выбора',
            'type_id.exists' => 'Выбранный тип товара не существует',
            'shop_id.required' => 'ID магазина обязательно',
            'shop_id.exists' => 'Магазин не найден',
            'category_id.required' => 'Категория обязательна для выбора',
            'category_id.exists' => 'Выбранная категория не существует',
            'status.required' => 'Статус обязателен для заполнения',
            'height.required' => 'Высота обязательна для заполнения',
            'height.numeric' => 'Высота должна быть числом',
            'height.min' => 'Высота должна быть больше 0',
            'length.required' => 'Длина обязательна для заполнения',
            'length.numeric' => 'Длина должна быть числом',
            'length.min' => 'Длина должна быть больше 0',
            'width.required' => 'Ширина обязательна для заполнения',
            'width.numeric' => 'Ширина должна быть числом',
            'width.min' => 'Ширина должна быть больше 0',
            'weight.required' => 'Вес обязателен для заполнения',
            'weight.numeric' => 'Вес должен быть числом',
            'weight.min' => 'Вес должен быть больше 0',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}


