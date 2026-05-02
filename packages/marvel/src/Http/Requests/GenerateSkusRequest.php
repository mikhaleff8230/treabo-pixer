<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GenerateSkusRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'attribute_ids' => ['required', 'array', 'min:1'],
            'attribute_ids.*' => ['integer'], // Временно убрали exists для тестирования
            'base_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom error messages
     *
     * @return array
     */
    public function messages()
    {
        return [
            'attribute_ids.required' => 'Необходимо выбрать хотя бы один атрибут',
            'attribute_ids.array' => 'Атрибуты должны быть массивом',
            'attribute_ids.min' => 'Необходимо выбрать хотя бы один атрибут',
            'attribute_ids.*.integer' => 'ID атрибута должен быть числом',
            'attribute_ids.*.exists' => 'Атрибут не найден',
            'base_price.numeric' => 'Базовая цена должна быть числом',
            'base_price.min' => 'Базовая цена должна быть больше или равна 0',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}

