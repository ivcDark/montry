<?php

namespace App\Modules\Incidents\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateWeeklyDigestPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'send_time' => ['required', 'date_format:H:i'],
        ];
    }
}
