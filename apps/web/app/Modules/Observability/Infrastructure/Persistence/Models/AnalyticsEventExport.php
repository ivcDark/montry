<?php

namespace App\Modules\Observability\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AnalyticsEventExport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPORTED = 'exported';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_event_id',
        'event_id',
        'status',
        'attempts',
        'exported_at',
        'last_attempted_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'business_event_id' => 'integer',
            'attempts' => 'integer',
            'exported_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }
}
