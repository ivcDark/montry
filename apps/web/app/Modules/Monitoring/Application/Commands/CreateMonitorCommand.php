<?php

namespace App\Modules\Monitoring\Application\Commands;

final readonly class CreateMonitorCommand
{
    public function __construct(
        public int $organizationId,
        public int $projectId,
        public int $monitoredResourceId,
        public string $type,
        public string $name,
        public bool $enabled,
        public int $intervalSeconds,
        public int $timeoutMs,
        public ?int $failureThreshold = null,
        public array $settings = [],
        public array $expected = [],
    ) {
    }
}
