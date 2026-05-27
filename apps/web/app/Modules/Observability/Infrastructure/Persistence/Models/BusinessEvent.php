<?php

namespace App\Modules\Observability\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'occurred_at',
        'organization_id',
        'user_id',
        'plan_code',
        'subject_type',
        'subject_id',
        'status',
        'source',
        'correlation_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'organization_id' => 'integer',
            'user_id' => 'integer',
            'payload' => 'array',
        ];
    }
}

