<?php

namespace Marvel\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Проверка авторизации через middleware
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'commentable_type' => 'required|string|in:product,place',
            'commentable_id' => 'required|integer|min:1',
            'body' => 'required|string|min:3|max:2000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'commentable_type.required' => 'Тип комментируемой сущности обязателен',
            'commentable_type.in' => 'Неподдерживаемый тип сущности',
            'commentable_id.required' => 'ID комментируемой сущности обязателен',
            'commentable_id.integer' => 'ID должен быть числом',
            'body.required' => 'Текст комментария обязателен',
            'body.min' => 'Комментарий должен содержать минимум 3 символа',
            'body.max' => 'Комментарий не может превышать 2000 символов',
            'parent_id.exists' => 'Родительский комментарий не найден',
        ];
    }
}

