<?php

namespace App\Modules\Monitoring\Application\Commands;

final readonly class ResumeMonitorCommand
{
    public function __construct(
        public int $monitorId,
    ) {
    }
}
