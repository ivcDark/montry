<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class LimitChecker
{
    public function __construct(
        private BusinessEventRecorder $events,
        private BillingAddonCatalog $addons,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function assertCanCreateMonitor(int $organizationId, bool $enabled = true): void
    {
        if ($enabled) {
            $this->assertCanEnableMonitor($organizationId);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanEnableMonitor(int $organizationId): void
    {
        $limit = $this->activeMonitorLimit($organizationId);

        if ($limit === null) {
            return;
        }

        $usage = $this->activeMonitorUsage($organizationId);

        if ($usage >= $limit) {
            $this->recordLimitHit($organizationId, 'max_monitors', [
                'current_count' => $usage,
                'limit' => $limit,
            ]);

            throw new AuthorizationException('Monitor limit reached for the current plan.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanCreateSite(int $organizationId): void
    {
        $limit = $this->effectiveSiteLimit($organizationId);

        if ($limit === null) {
            return;
        }

        $siteCount = $this->siteUsage($organizationId);

        if ($siteCount >= $limit) {
            $this->recordLimitHit($organizationId, 'max_sites', [
                'current_count' => $siteCount,
                'effective_limit' => $limit,
                'extra_site_packs' => $this->addonQuantity($organizationId, BillingAddonCatalog::EXTRA_SITES_PACK),
            ]);

            throw new AuthorizationException('Site limit reached for the current plan.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanCreateProject(int $organizationId): void
    {
        if ($this->booleanLimit($organizationId, 'can_create_projects', true)) {
            return;
        }

        $this->recordLimitHit($organizationId, 'can_create_projects');

        throw new AuthorizationException('Separate projects are not available for the current plan.');
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseMonitorType(int $organizationId, string $type): void
    {
        $allowedTypes = $this->allowedMonitorTypes($organizationId);

        if ($allowedTypes !== null && ! in_array($type, $allowedTypes, true)) {
            $this->recordLimitHit($organizationId, 'allowed_monitor_types', [
                'requested_type' => $type,
                'allowed_types' => $allowedTypes,
            ]);

            throw new AuthorizationException('Monitor type is not available for the current plan.');
        }

    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanCreatePaidCheck(int $organizationId, string $type, int $additionalQuantity = 1): void
    {
        $this->assertCanUseMonitorType($organizationId, $type);
    }

    /**
     * @param  list<string>  $types
     *
     * @throws AuthorizationException
     */
    public function assertCanCreatePaidChecks(int $organizationId, array $types): void
    {
        $requested = [];

        foreach ($types as $type) {
            if (! $this->addons->isPaidMonitorType($type)) {
                continue;
            }

            $requested[$type] = ($requested[$type] ?? 0) + 1;
        }

        foreach ($requested as $type => $quantity) {
            $this->assertCanCreatePaidCheck($organizationId, $type, $quantity);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanEnablePaidMonitor(Monitor $monitor): void
    {
        $this->assertCanUseMonitorType($monitor->organization_id, $monitor->type);
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseInterval(int $organizationId, int $intervalSeconds): void
    {
        $minimumInterval = $this->limitValue($organizationId, 'minimum_check_interval_seconds');

        if ($minimumInterval !== null && $intervalSeconds < $minimumInterval) {
            $this->recordLimitHit($organizationId, 'minimum_check_interval_seconds', [
                'requested_interval_seconds' => $intervalSeconds,
                'minimum_interval_seconds' => $minimumInterval,
            ]);

            throw new AuthorizationException('Check interval is below the current plan limit.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanRequestManualCheck(int $organizationId): void
    {
        $limit = $this->limitValue($organizationId, 'manual_checks_per_day');

        if ($limit === 0) {
            $this->recordLimitHit($organizationId, 'manual_checks_per_day', [
                'limit' => $limit,
            ]);

            throw new AuthorizationException('Manual checks are not available for the current plan.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseNotificationChannel(int $organizationId, string $channel): void
    {
        if ($this->canUseNotificationChannel($organizationId, $channel)) {
            return;
        }

        $allowedChannels = $this->listLimit($organizationId, 'notification_channels', 'channels');

        $this->recordLimitHit($organizationId, 'notification_channels', [
            'requested_channel' => $channel,
            'allowed_channels' => $allowedChannels,
        ]);

        throw new AuthorizationException('Notification channel is not available for the current plan.');
    }

    public function canUseNotificationChannel(int $organizationId, string $channel): bool
    {
        $allowedChannels = $this->listLimit($organizationId, 'notification_channels', 'channels');

        return $allowedChannels === null || in_array($channel, $allowedChannels, true);
    }

    public function minimumCheckIntervalSeconds(int $organizationId): ?int
    {
        return $this->limitValue($organizationId, 'minimum_check_interval_seconds');
    }

    public function historyRetentionDays(int $organizationId): ?int
    {
        return $this->limitValue($organizationId, 'history_retention_days');
    }

    /**
     * @return list<string>|null
     */
    public function allowedMonitorTypes(int $organizationId): ?array
    {
        return $this->listLimit($organizationId, 'allowed_monitor_types', 'types');
    }

    public function isMonitorTypeAvailable(int $organizationId, string $type): bool
    {
        $allowedTypes = $this->allowedMonitorTypes($organizationId);

        if ($allowedTypes !== null && ! in_array($type, $allowedTypes, true)) {
            return false;
        }

        return true;
    }

    public function effectiveSiteLimit(int $organizationId): ?int
    {
        $baseLimit = $this->limitValue($organizationId, 'max_sites');

        if ($baseLimit === null) {
            return null;
        }

        return $baseLimit + ($this->addonQuantity($organizationId, BillingAddonCatalog::EXTRA_SITES_PACK) * 5);
    }

    public function siteUsage(int $organizationId): int
    {
        return MonitoredResource::query()
            ->where('organization_id', $organizationId)
            ->where('type', 'website')
            ->count();
    }

    public function activeMonitorLimit(int $organizationId): ?int
    {
        return $this->limitValue($organizationId, 'max_monitors');
    }

    public function activeMonitorUsage(int $organizationId): int
    {
        return Monitor::query()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->count();
    }

    public function paidCheckLimit(int $organizationId, string $type): int
    {
        if (! $this->addons->isPaidMonitorType($type)) {
            return 0;
        }

        return $this->addonQuantity($organizationId, $type);
    }

    public function paidCheckUsage(int $organizationId, string $type): int
    {
        if (! $this->addons->isPaidMonitorType($type)) {
            return 0;
        }

        return Monitor::query()
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->count();
    }

    public function addonQuantity(int $organizationId, string $code): int
    {
        $subscription = $this->currentSubscription($organizationId);
        $item = $subscription?->items?->firstWhere('code', $code);

        return (int) ($item?->quantity ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(int $organizationId): array
    {
        $siteLimit = $this->effectiveSiteLimit($organizationId);

        return [
            'monitors' => [
                'current' => $this->activeMonitorUsage($organizationId),
                'limit' => $this->activeMonitorLimit($organizationId),
            ],
            'sites' => [
                'current' => $this->siteUsage($organizationId),
                'limit' => $siteLimit,
                'extra_packs' => $this->addonQuantity($organizationId, BillingAddonCatalog::EXTRA_SITES_PACK),
            ],
            'paid_checks' => collect($this->addons->paidMonitorTypes())
                ->mapWithKeys(fn (string $type): array => [$type => [
                    'used' => $this->paidCheckUsage($organizationId, $type),
                    'limit' => $this->paidCheckLimit($organizationId, $type),
                ]])
                ->all(),
            'telegram_available' => $this->canUseNotificationChannel($organizationId, 'telegram'),
            'minimum_check_interval_seconds' => $this->minimumCheckIntervalSeconds($organizationId),
            'history_retention_days' => $this->historyRetentionDays($organizationId),
        ];
    }

    private function limitValue(int $organizationId, string $key): ?int
    {
        $subscription = $this->currentSubscription($organizationId);

        $limit = $subscription?->plan?->limits->firstWhere('key', $key);

        if ($limit === null) {
            return null;
        }

        $value = $limit->value;

        if (is_array($value)) {
            $value = $value['limit'] ?? $value['seconds'] ?? $value['days'] ?? $value['value'] ?? null;
        }

        return $value === null ? null : (int) $value;
    }

    /**
     * @return list<string>|null
     */
    private function listLimit(int $organizationId, string $key, string $valueKey): ?array
    {
        $subscription = $this->currentSubscription($organizationId);
        $limit = $subscription?->plan?->limits->firstWhere('key', $key);

        if ($limit === null) {
            return null;
        }

        $value = $limit->value;

        if (is_array($value) && isset($value[$valueKey]) && is_array($value[$valueKey])) {
            $items = array_values(array_map('strval', $value[$valueKey]));

            return in_array('*', $items, true) ? null : $items;
        }

        return null;
    }

    private function booleanLimit(int $organizationId, string $key, bool $default): bool
    {
        $subscription = $this->currentSubscription($organizationId);
        $limit = $subscription?->plan?->limits->firstWhere('key', $key);

        if ($limit === null) {
            return $default;
        }

        $value = $limit->value;

        if (is_array($value)) {
            return (bool) ($value['enabled'] ?? $value['value'] ?? $default);
        }

        return $default;
    }

    private function currentSubscription(int $organizationId): ?Subscription
    {
        $subscription = Subscription::query()
            ->with(['plan.limits', 'items'])
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        if ($subscription !== null) {
            return $subscription;
        }

        return Subscription::query()
            ->with(['plan.limits', 'items'])
            ->where('organization_id', $organizationId)
            ->whereHas('plan', fn ($query) => $query->where('code', 'free'))
            ->latest('starts_at')
            ->first();
    }

    private function recordLimitHit(int $organizationId, string $limitKey, array $payload = []): void
    {
        $subscription = $this->currentSubscription($organizationId);

        $this->events->record(new RecordBusinessEventData(
            eventType: 'billing.limit_hit',
            organizationId: $organizationId,
            planCode: $subscription?->plan?->code,
            subjectType: 'billing_limit',
            subjectId: $limitKey,
            status: 'blocked',
            source: 'billing',
            payload: [
                'limit_key' => $limitKey,
                'subscription_id' => $subscription?->id,
                ...$payload,
            ],
        ));
    }
}
