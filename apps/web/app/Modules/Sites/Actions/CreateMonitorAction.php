<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\DTO\CreateMonitorData;

final class CreateMonitorAction
{
    public function handle(MonitoredResource $site, CreateMonitorData $data): Monitor
    {
        return $site->monitors()->create([
            'organization_id' => $site->organization_id,
            'project_id' => $site->project_id,
            'name' => $data->name,
            'type' => $data->type->value,
            'enabled' => $data->isEnabled,
            'status' => 'unknown',
            'interval_seconds' => $data->intervalSeconds,
            'timeout_ms' => $data->timeoutMs,
            'settings' => $data->settings,
            'expected' => $data->expected,
        ]);
    }
}
