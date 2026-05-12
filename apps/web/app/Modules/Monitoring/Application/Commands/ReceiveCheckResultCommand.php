<?php

namespace App\Modules\Monitoring\Application\Commands;

use DateTimeInterface;

final readonly class ReceiveCheckResultCommand
{
    public function __construct(
        public int $monitorId,
        public array $workerResult,
        public ?DateTimeInterface $checkedAt = null,
    ) {
    }
}
