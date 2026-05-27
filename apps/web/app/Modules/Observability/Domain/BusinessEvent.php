<?php

namespace App\Modules\Observability\Domain;

use DateTimeInterface;

final readonly class BusinessEvent
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public DateTimeInterface $occurredAt,
        public ?int $organizationId,
        public ?int $userId,
        public ?string $planCode,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $status,
        public ?string $source,
        public ?string $correlationId,
        public array $payload,
    ) {
    }
}

