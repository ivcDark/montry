<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\MonitoredResources\Application\Services\SiteNotificationChannels;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationRule;
use Illuminate\Database\Eloquent\Collection;

final readonly class NotificationRecipientResolver
{
    public function __construct(
        private LimitChecker $limits,
        private SyncEmailNotificationChannels $syncEmailChannels,
        private SiteNotificationChannels $siteNotificationChannels,
    ) {}

    /**
     * @return Collection<int, NotificationChannel>
     */
    public function resolve(int $organizationId, string $eventType, array $payload = []): Collection
    {
        $this->syncEmailChannels->handleOrganization($organizationId);
        $siteChannelTypes = $this->siteChannelTypes($organizationId, $payload);

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
            ->whereIn('type', ['email', 'telegram', 'max']);

        if ($siteChannelTypes !== null) {
            $query->whereIn('type', $siteChannelTypes);
        }

        if ($hasEventRules) {
            $query->where(function ($query) use ($ruleChannelIds, $eventType): void {
                $query
                    ->whereIn('id', $ruleChannelIds)
                    ->orWhereJsonContains('settings->event_types', $eventType);
            });
        } else {
            $query->where(function ($query) use ($eventType): void {
                $query
                    ->whereNull('settings->event_types')
                    ->orWhereJsonContains('settings->event_types', $eventType);
            });
        }

        return $query
            ->get()
            ->reject(fn (NotificationChannel $channel): bool => ! $this->limits->canUseNotificationChannel(
                (int) $channel->organization_id,
                (string) $channel->type,
            ))
            ->values();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>|null
     */
    private function siteChannelTypes(int $organizationId, array $payload): ?array
    {
        $resourceId = $payload['monitored_resource_id'] ?? null;

        if (! is_int($resourceId) && ! is_string($resourceId)) {
            return null;
        }

        $site = MonitoredResource::query()
            ->where('organization_id', $organizationId)
            ->find((int) $resourceId);

        if ($site === null) {
            return null;
        }

        return $this->siteNotificationChannels->enabledForSite($site);
    }}

