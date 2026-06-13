<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\DTO\UpdateMonitorData;

final class UpdateMonitorAction
{
    public function handle(Monitor $monitor, UpdateMonitorData $data): Monitor
    {
        $monitor->update([
            'name' => $data->name,
            'enabled' => $data->isEnabled,
            'interval_seconds' => $data->intervalSeconds,
            'timeout_ms' => $data->timeoutMs,
            'settings' => $data->settings,
            'expected' => $data->expected ?: ($monitor->expected ?? []),
        ]);

        return $monitor->refresh();
    }
}
