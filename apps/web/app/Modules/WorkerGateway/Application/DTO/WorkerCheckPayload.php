<?php

namespace App\Modules\WorkerGateway\Application\DTO;

use DateTimeInterface;

final readonly class WorkerCheckPayload
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $monitorId,
        public string $checkType,
        public string $target,
        public array $settings,
        public array $expected,
        public DateTimeInterface $requestedAt,
        public ?string $correlationId = null,
        public ?string $traceparent = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'monitor_id' => $this->monitorId,
            'check_type' => $this->checkType,
            'target' => $this->target,
            'settings' => $this->settings,
            'expected' => $this->expected,
            'requested_at' => $this->requestedAt->format(DateTimeInterface::ATOM),
            'correlation_id' => $this->correlationId,
            'traceparent' => $this->traceparent,
        ];
    }
}
