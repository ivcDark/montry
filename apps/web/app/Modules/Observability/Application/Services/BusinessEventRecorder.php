<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Context\CorrelationContext;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Domain\BusinessEvent;
use App\Modules\Observability\Domain\Contracts\BusinessEventRepositoryInterface;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class BusinessEventRecorder
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'api_key',
        'access_token',
        'refresh_token',
        'token',
        'secret',
        'password',
        'password_confirmation',
        'verification_code',
        'code',
        'card_number',
        'cvv',
    ];

    public function __construct(
        private BusinessEventRepositoryInterface $events,
        private CorrelationContext $correlationContext,
        private OpenTelemetryService $tracer,
    ) {
    }

    public function record(RecordBusinessEventData $data): void
    {
        $eventType = trim($data->eventType);

        if ($eventType === '') {
            throw new InvalidArgumentException('Business event type cannot be empty.');
        }

        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $eventType)) {
            throw new InvalidArgumentException("Invalid business event type: {$eventType}");
        }

        $span = $this->tracer->startSpan('business_event.'.$eventType, [
            'event.type' => $eventType,
            'event.status' => $data->status,
            'event.source' => $data->source,
            'organization.present' => $data->organizationId !== null,
            'user.present' => $data->userId !== null,
        ]);

        try {
            $this->events->add(new BusinessEvent(
                eventId: $data->eventId ?? (string) Str::uuid(),
                eventType: $eventType,
                occurredAt: $data->occurredAt ?? Carbon::now(),
                organizationId: $data->organizationId,
                userId: $data->userId,
                planCode: $data->planCode,
                subjectType: $data->subjectType,
                subjectId: $data->subjectId,
                status: $data->status,
                source: $data->source,
                correlationId: $data->correlationId ?? $this->correlationContext->id(),
                payload: $this->redactPayload($data->payload),
            ));

            $span->end();
        } catch (\Throwable $exception) {
            $span->end('STATUS_CODE_ERROR');

            throw $exception;
        }
    }

    private function redactPayload(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactPayload($value) : $value;
        }

        return $redacted;
    }
}
