<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\Models\SiteMonitor;

final class ToggleMonitorAction
{
    public function handle(SiteMonitor $siteMonitor): SiteMonitor
    {
        $siteMonitor->update([
            'is_enabled' => ! $siteMonitor->is_enabled,
        ]);

        return $siteMonitor;
    }
}
