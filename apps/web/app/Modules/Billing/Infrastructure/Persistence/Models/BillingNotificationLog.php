<?php

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingNotificationLog extends Model
{
    protected $fillable = [
        'organization_id',
        'subscription_id',
        'event_type',
        'event_date',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
