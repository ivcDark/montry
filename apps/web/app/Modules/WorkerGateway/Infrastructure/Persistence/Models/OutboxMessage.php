<?php

namespace App\Modules\WorkerGateway\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OutboxMessage extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
