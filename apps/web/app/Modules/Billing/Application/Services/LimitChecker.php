<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use Illuminate\Auth\Access\AuthorizationException;

final class LimitChecker
{
    /**
     * @throws AuthorizationException
     */
    public function assertCanCreateMonitor(int $organizationId): void
    {
        $limit = $this->limitValue($organizationId, 'max_monitors');

        if ($limit === null) {
            return;
        }

        $monitorCount = Monitor::query()
            ->where('organization_id', $organizationId)
            ->count();

        if ($monitorCount >= $limit) {
            throw new AuthorizationException('Monitor limit reached for the current plan.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanCreateSite(int $organizationId): void
    {
        $limit = $this->limitValue($organizationId, 'max_sites');

        if ($limit === null) {
            return;
        }

        $siteCount = MonitoredResource::query()
            ->where('organization_id', $organizationId)
            ->where('type', 'website')
            ->count();

        if ($siteCount >= $limit) {
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

        throw new AuthorizationException('Separate projects are not available for the current plan.');
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseMonitorType(int $organizationId, string $type): void
    {
        $allowedTypes = $this->allowedMonitorTypes($organizationId);

        if ($allowedTypes === null || in_array($type, $allowedTypes, true)) {
            return;
        }

        throw new AuthorizationException('Monitor type is not available for the current plan.');
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseInterval(int $organizationId, int $intervalSeconds): void
    {
        $minimumInterval = $this->limitValue($organizationId, 'minimum_check_interval_seconds');

        if ($minimumInterval !== null && $intervalSeconds < $minimumInterval) {
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
            throw new AuthorizationException('Manual checks are not available for the current plan.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanUseNotificationChannel(int $organizationId, string $channel): void
    {
        $allowedChannels = $this->listLimit($organizationId, 'notification_channels', 'channels');

        if ($allowedChannels === null || in_array($channel, $allowedChannels, true)) {
            return;
        }

        throw new AuthorizationException('Notification channel is not available for the current plan.');
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
            ->with('plan.limits')
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
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->whereHas('plan', fn ($query) => $query->where('code', 'free'))
            ->latest('starts_at')
            ->first();
    }
}
