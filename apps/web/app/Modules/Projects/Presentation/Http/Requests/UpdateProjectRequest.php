<?php

namespace App\Modules\Projects\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'resource_ids' => ['sometimes', 'array'],
            'resource_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('monitored_resources', 'id'),
            ],
        ];
    }
}