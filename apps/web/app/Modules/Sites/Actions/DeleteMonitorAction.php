<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\Models\SiteMonitor;

final class DeleteMonitorAction
{
    public function handle(SiteMonitor $siteMonitor): void
    {
        $siteMonitor->delete();
    }
}
