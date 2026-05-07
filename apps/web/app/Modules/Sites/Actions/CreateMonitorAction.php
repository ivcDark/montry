<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\CreateMonitorData;
use App\Modules\Sites\Models\SiteMonitor;
use App\Modules\Sites\Models\Site;

final class CreateMonitorAction
{
    public function execute(Site $site, CreateMonitorData $data): SiteMonitor
    {
        return $site->monitors()->create([
            'name' => $data->name,
            'type' => $data->type,
            'is_enabled' => $data->isEnabled,
            'interval_seconds' => $data->intervalSeconds,
            'timeout_ms' => $data->timeoutMs,
            'settings' => $data->settings,
        ]);
    }
}
