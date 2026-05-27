<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Requests;

use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Foundation\Http\FormRequest;

final class ListDueMonitorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = config('services.poller.internal_token');

        if ($token === null || $token === '') {
            return true;
        }

        $authorized = hash_equals((string) $token, (string) $this->bearerToken());

        if (! $authorized) {
            app(AuditLogger::class)->record(
                category: 'security',
                action: 'internal_api.auth_failed',
                outcome: 'failed',
                request: $this,
                source: 'internal_api',
                metadata: [
                    'endpoint' => 'monitors.due',
                ],
            );
        }

        return $authorized;
    }

    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }
}
