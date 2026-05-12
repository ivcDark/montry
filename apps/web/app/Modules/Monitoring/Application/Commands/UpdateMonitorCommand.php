<?php

namespace App\Modules\Monitoring\Application\Commands;

final readonly class UpdateMonitorCommand
{
    public function __construct(
        public int $monitorId,
        public string $name,
        public bool $enabled,
        public int $intervalSeconds,
        public int $timeoutMs,
        public array $settings = [],
        public array $expected = [],
    ) {
    }
}
