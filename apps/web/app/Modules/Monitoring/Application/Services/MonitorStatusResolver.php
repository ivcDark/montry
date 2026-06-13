<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class MonitorStatusResolver
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
    ) {
    }

    public function resolve(Monitor $monitor, array $normalizedResult): string
    {
        return $this->checkTypeRegistry
            ->get($monitor->type)
            ->resolveStatus($normalizedResult, $monitor->expected ?? []);
    }
}
