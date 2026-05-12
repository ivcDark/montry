<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final class ToggleMonitorAction
{
    public function handle(Monitor $siteMonitor): Monitor
    {
        $siteMonitor->update([
            'enabled' => ! $siteMonitor->enabled,
        ]);

        return $siteMonitor;
    }
}
