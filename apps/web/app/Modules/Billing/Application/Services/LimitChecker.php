<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
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

    private function limitValue(int $organizationId, string $key): ?int
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

        $limit = $subscription?->plan?->limits->firstWhere('key', $key);

        if ($limit === null) {
            return null;
        }

        $value = $limit->value;

        if (is_array($value)) {
            $value = $value['limit'] ?? $value['seconds'] ?? $value['value'] ?? null;
        }

        return $value === null ? null : (int) $value;
    }
}
