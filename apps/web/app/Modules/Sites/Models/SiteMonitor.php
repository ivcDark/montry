<?php

namespace App\Modules\Sites\Models;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * @property integer site_id
 * @property string name
 * @property string type
 * @property bool is_enabled
 * @property integer internal_seconds
 * @property integer timeout_ms
 * @property string settings
 * @property string last_checked_at
 */
class SiteMonitor extends Monitor
{
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'monitored_resource_id');
    }
}
