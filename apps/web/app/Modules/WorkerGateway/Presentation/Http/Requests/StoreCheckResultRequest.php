<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Requests;

use App\Modules\Observability\Application\Services\AuditLogger;
use App\Modules\Observability\Application\Services\DeadLetterRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

final class StoreCheckResultRequest extends FormRequest
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
                    'endpoint' => 'check-results',
                ],
            );
        }

        return $authorized;
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
            'correlation_id' => ['nullable', 'string', 'max:128'],
            'traceparent' => ['nullable', 'string', 'max:128'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        app(DeadLetterRecorder::class)->record(
            source: 'poller',
            type: 'check_result_payload_invalid',
            errorMessage: 'Poller check result payload failed validation.',
            recoverable: false,
            idempotencyKey: $this->input('event_id') ? 'poller_result_validation:' . $this->input('event_id') : null,
            subjectType: 'monitor',
            subjectId: is_scalar($this->input('monitor_id')) ? (string) $this->input('monitor_id') : null,
            payload: [
                'input' => $this->except(['authorization', 'token', 'signature']),
            ],
            context: [
                'errors' => $validator->errors()->toArray(),
            ],
        );

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
