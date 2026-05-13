<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListDueMonitorsRequest extends FormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }
}
