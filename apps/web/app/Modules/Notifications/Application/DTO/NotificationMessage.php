<?php

namespace App\Modules\Notifications\Application\DTO;

final readonly class NotificationMessage
{
    public function __construct(
        public string $eventType,
        public string $subject,
        public string $body,
        public array $payload = [],
        public ?int $organizationId = null,
        public ?int $incidentId = null,
    ) {
    }
}
