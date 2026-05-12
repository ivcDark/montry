<?php

namespace App\Modules\MonitoredResources\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonitoredResource extends Model
{
    use SoftDeletes;

    protected $table = 'monitored_resources';

    protected $fillable = [
        'organization_id',
        'project_id',
        'created_user_id',
        'type',
        'name',
        'target',
        'scheme',
        'host',
        'port',
        'path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return (string) $this->target;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }
}
