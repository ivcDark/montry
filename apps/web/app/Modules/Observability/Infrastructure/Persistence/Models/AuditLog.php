<?php

namespace App\Modules\Observability\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    protected $fillable = [
        'event_id',
        'occurred_at',
        'category',
        'action',
        'outcome',
        'source',
        'actor_user_id',
        'organization_id',
        'target_type',
        'target_id',
        'route_name',
        'request_method',
        'request_path',
        'ip_hash',
        'user_agent_hash',
        'correlation_id',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];
}

