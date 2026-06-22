<?php

namespace App\Modules\StatusPages\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class StatusPage extends Model
{
    protected $fillable = [
        'organization_id',
        'created_user_id',
        'name',
        'slug',
        'description',
        'is_published',
        'show_incident_history',
        'accent_color',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_incident_history' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'status_page_monitors')
            ->withPivot(['display_name', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
