<?php

namespace App\Modules\Feedback\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', 'in:bug,feature,improvement,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Короткое название идеи обязательно.',
            'title.max' => 'Название идеи должно быть не длиннее 255 символов.',
            'description.required' => 'Текст идеи обязателен.',
            'description.max' => 'Текст идеи должен быть не длиннее 5000 символов.',
            'type.required' => 'Выберите тип идеи.',
            'type.in' => 'Выберите корректный тип идеи.',
        ];
    }
}
