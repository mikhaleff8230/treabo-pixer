<?php

namespace Marvel\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Проверка прав доступа через Policy
        return $this->user()->can('update', $this->route('comment'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string|min:3|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Текст комментария обязателен',
            'body.min' => 'Комментарий должен содержать минимум 3 символа',
            'body.max' => 'Комментарий не может превышать 2000 символов',
        ];
    }
}

