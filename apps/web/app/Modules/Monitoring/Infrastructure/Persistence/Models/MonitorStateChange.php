<?php

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorStateChange extends Model
{
    protected $fillable = [
        'monitor_id',
        'organization_id',
        'check_result_id',
        'from_status',
        'to_status',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
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

    public function checkResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class);
    }
}
