<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final class DeleteMonitorAction
{
    public function handle(Monitor $siteMonitor): void
    {
        $siteMonitor->delete();
    }
}
