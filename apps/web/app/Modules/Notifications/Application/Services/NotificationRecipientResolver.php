<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationRule;
use Illuminate\Database\Eloquent\Collection;

final class NotificationRecipientResolver
{
    /**
     * @return Collection<int, NotificationChannel>
     */
    public function resolve(int $organizationId, string $eventType): Collection
    {
        $hasEventRules = NotificationRule::query()
            ->where('organization_id', $organizationId)
            ->where('event_type', $eventType)
            ->exists();

        $ruleChannelIds = NotificationRule::query()
            ->where('organization_id', $organizationId)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->pluck('notification_channel_id');

        $query = NotificationChannel::query()
            ->with('user')
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->whereIn('type', ['email', 'telegram']);

        if ($hasEventRules) {
            $query->whereIn('id', $ruleChannelIds);
        }

        return $query->get();
    }
}
