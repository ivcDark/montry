<?php

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'organization_id',
        'provider',
        'level',
        'event',
        'message',
        'request_method',
        'request_path',
        'ip_hash',
        'payload',
        'context',
        'exception_class',
        'exception_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
