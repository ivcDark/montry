<?php

namespace App\Modules\Observability\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class DeadLetter extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'event_id',
        'source',
        'type',
        'status',
        'recoverable',
        'idempotency_key',
        'organization_id',
        'subject_type',
        'subject_id',
        'error_class',
        'error_message',
        'payload',
        'context',
        'attempts',
        'max_attempts',
        'failed_at',
        'last_retry_at',
        'resolved_at',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'recoverable' => 'boolean',
            'payload' => 'array',
            'context' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'failed_at' => 'datetime',
            'last_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}

