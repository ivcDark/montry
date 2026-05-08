<?php

namespace App\Modules\Sites\Models;

use App\Modules\Sites\Enums\MonitorType;
use Illuminate\Database\Eloquent\Model;
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
class SiteMonitor extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'type',
        'is_enabled',
        'interval_seconds',
        'timeout_ms',
        'settings',
        'last_checked_at',
    ];

    protected $casts = [
        'type' => MonitorType::class,
        'is_enabled' => 'boolean',
        'interval_seconds' => 'integer',
        'timeout_ms' => 'integer',
        'settings' => 'array',
        'last_checked_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

}
