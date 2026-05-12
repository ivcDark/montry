<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCheckResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = config('services.poller.internal_token');

        if ($token === null || $token === '') {
            return true;
        }

        return hash_equals((string) $token, (string) $this->bearerToken());
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'string'],
            'monitor_id' => ['required', 'integer', 'exists:monitors,id'],
            'check_type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'checked_at' => ['required', 'date'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'array'],
        ];
    }
}
