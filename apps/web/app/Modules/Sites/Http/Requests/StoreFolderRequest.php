<?php

namespace App\Modules\Sites\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
