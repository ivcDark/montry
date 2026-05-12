<?php

namespace App\Modules\Notifications\Infrastructure\Persistence\Models;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationRule extends Model
{
    protected $fillable = [
        'organization_id',
        'notification_channel_id',
        'event_type',
        'enabled',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }
}
