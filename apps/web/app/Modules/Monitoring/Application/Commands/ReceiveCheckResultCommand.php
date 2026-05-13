<?php

namespace App\Modules\Monitoring\Application\Commands;

use DateTimeInterface;

final readonly class ReceiveCheckResultCommand
{
    public function __construct(
        public ?string $eventId,
        public int $monitorId,
        public string $checkType,
        public array $workerResult,
        public ?DateTimeInterface $checkedAt = null,
    ) {}
}
