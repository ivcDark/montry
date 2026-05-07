<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\UpdateMonitorData;
use App\Modules\Sites\Models\SiteMonitor;

final class UpdateMonitorAction
{
    public function handle(SiteMonitor $monitor, UpdateMonitorData $data): SiteMonitor
    {
        $monitor->update([
            'name' => $data->name,
            'is_enabled' => $data->isEnabled,
            'interval_seconds' => $data->intervalSeconds,
            'timeout_ms' => $data->timeoutMs,
            'settings' => $data->settings,
        ]);

        return $monitor->refresh();
    }
}
