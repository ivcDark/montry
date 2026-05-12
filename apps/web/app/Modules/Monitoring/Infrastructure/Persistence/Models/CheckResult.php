<?php

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResult extends Model
{
    protected $fillable = [
        'monitor_id',
        'organization_id',
        'check_type',
        'status',
        'checked_at',
        'response_time_ms',
        'status_code',
        'error_code',
        'error_message',
        'raw_result',
        'normalized_result',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'response_time_ms' => 'integer',
            'status_code' => 'integer',
            'raw_result' => 'array',
            'normalized_result' => 'array',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
