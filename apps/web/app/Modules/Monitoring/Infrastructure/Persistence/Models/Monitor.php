<?php

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Monitor extends Model
{
    use SoftDeletes;

    protected $table = 'monitors';

    protected $fillable = [
        'organization_id',
        'project_id',
        'monitored_resource_id',
        'type',
        'name',
        'enabled',
        'status',
        'interval_seconds',
        'failure_threshold',
        'timeout_ms',
        'settings',
        'expected',
        'last_check_at',
        'next_check_at',
        'check_in_progress_until',
        'last_check_event_id',
        'last_success_at',
        'last_failure_at',
        'consecutive_successes',
        'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interval_seconds' => 'integer',
            'failure_threshold' => 'integer',
            'timeout_ms' => 'integer',
            'settings' => 'array',
            'expected' => 'array',
            'last_check_at' => 'datetime',
            'next_check_at' => 'datetime',
            'check_in_progress_until' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'consecutive_successes' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    public function getIsEnabledAttribute(): bool
    {
        return (bool) $this->enabled;
    }

    public function getSiteIdAttribute(): ?int
    {
        return $this->monitored_resource_id;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function monitoredResource(): BelongsTo
    {
        return $this->belongsTo(MonitoredResource::class);
    }

    public function latestCheckResult(): HasOne
    {
        return $this->hasOne(CheckResult::class)->latestOfMany('checked_at');
    }
}
