<?php

namespace App\Modules\Monitoring\Application\Commands;

final readonly class RequestManualCheckCommand
{
    public function __construct(
        public int $monitorId,
        public ?int $requestedByUserId = null,
    ) {
    }
}
