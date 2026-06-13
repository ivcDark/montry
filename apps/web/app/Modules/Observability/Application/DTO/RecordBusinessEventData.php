<?php

namespace App\Modules\Observability\Application\DTO;

use DateTimeInterface;

final readonly class RecordBusinessEventData
{
    public function __construct(
        public string $eventType,
        public array $payload = [],
        public ?DateTimeInterface $occurredAt = null,
        public ?int $organizationId = null,
        public ?int $userId = null,
        public ?string $planCode = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $status = null,
        public ?string $source = null,
        public ?string $correlationId = null,
        public ?string $eventId = null,
    ) {
    }
}

