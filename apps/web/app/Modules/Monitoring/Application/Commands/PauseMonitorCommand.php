<?php

namespace App\Modules\Monitoring\Application\Commands;

final readonly class PauseMonitorCommand
{
    public function __construct(
        public int $monitorId,
    ) {
    }
}
