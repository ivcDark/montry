<?php

namespace App\Modules\Incidents\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Incident extends Model
{
    protected $fillable = [
        'organization_id',
        'project_id',
        'monitored_resource_id',
        'monitor_id',
        'status',
        'severity',
        'title',
        'summary',
        'started_at',
        'resolved_at',
        'duration_seconds',
        'opened_by_check_result_id',
        'resolved_by_check_result_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
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

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function openedByCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'opened_by_check_result_id');
    }

    public function resolvedByCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'resolved_by_check_result_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class);
    }
}
