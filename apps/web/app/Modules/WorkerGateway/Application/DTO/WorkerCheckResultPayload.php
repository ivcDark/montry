<?php

namespace App\Modules\WorkerGateway\Application\DTO;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class WorkerCheckResultPayload
{
    public function __construct(
        public ?string $eventId,
        public int $monitorId,
        public string $checkType,
        public string $status,
        public DateTimeInterface $checkedAt,
        public ?int $durationMs,
        public array $result,
        public ?array $error,
        public ?string $traceparent = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            eventId: $payload['event_id'] ?? null,
            monitorId: (int) $payload['monitor_id'],
            checkType: (string) $payload['check_type'],
            status: (string) $payload['status'],
            checkedAt: new DateTimeImmutable((string) $payload['checked_at']),
            durationMs: isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null,
            result: is_array($payload['result'] ?? null) ? $payload['result'] : [],
            error: is_array($payload['error'] ?? null) ? $payload['error'] : null,
            traceparent: $payload['traceparent'] ?? null,
        );
    }

    public function toWorkerResult(): array
    {
        return [
            ...$this->result,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
            'traceparent' => $this->traceparent,
        ];
    }
}
