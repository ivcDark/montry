<?php

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Payment extends Model
{
    protected $fillable = [
        'organization_id',
        'subscription_id',
        'provider',
        'provider_payment_id',
        'status',
        'amount_cents',
        'currency',
        'payload',
        'paid_at',
        'failed_at',
        'failure_code',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }
}
