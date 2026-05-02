<?php

namespace Marvel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class AttributeRequest extends FormRequest
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
            'name'            => ['required', 'string'],
            'slug'            => ['nullable', 'string'],
            'shop_id'         => ['required', 'exists:Marvel\Database\Models\Shop,id'],
            'values'          => ['nullable', 'array'],
            'values.*.value'  => ['nullable', 'string'],
            'values.*.meta'   => ['nullable', 'string'],
            'values.*.language' => ['nullable', 'string'],
            'language'        => ['nullable', 'string'],
            'type'            => ['nullable', 'string'],
            'input_type'      => ['nullable', 'string', 'in:text,number,boolean,select,multiselect,textarea,date,url,email'],
            'is_required'    => ['nullable', 'boolean'],
            'description'     => ['nullable', 'string'],
            'unit'            => ['nullable', 'string'],
            'min_value'       => ['nullable', 'string'],
            'max_value'       => ['nullable', 'string'],
            'validation_regex' => ['nullable', 'string'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'      => ['nullable', 'boolean'],
            'display_type'    => ['nullable', 'string', 'in:input,dropdown,radio,checkbox,color_swatch,image_swatch,toggle,range'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
