<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class ApplySubscriptionLimits
{
    public function __construct(
        private BillingAddonCatalog $addons,
    ) {}

    public function handle(int $organizationId, Plan $plan): void
    {
        $plan->loadMissing('limits');

        $allowedResourceIds = $this->allowedResourceIds($organizationId, $plan);
        $allowedTypes = $this->allowedMonitorTypes($plan);

        if ($allowedResourceIds !== null) {
            Monitor::query()
                ->where('organization_id', $organizationId)
                ->where('enabled', true)
                ->whereNotIn('monitored_resource_id', $allowedResourceIds)
                ->update([
                    'enabled' => false,
                    'status' => 'paused',
                ]);

            MonitoredResource::query()
                ->where('organization_id', $organizationId)
                ->where('type', 'website')
                ->whereNotIn('id', $allowedResourceIds)
                ->update(['status' => 'paused']);
        }

        if ($allowedTypes !== null) {
            Monitor::query()
                ->where('organization_id', $organizationId)
                ->where('enabled', true)
                ->when($allowedResourceIds !== null, fn ($query) => $query->whereIn('monitored_resource_id', $allowedResourceIds))
                ->whereNotIn('type', $allowedTypes)
                ->update([
                    'enabled' => false,
                    'status' => 'paused',
                ]);
        }

        foreach (BillingAddonCatalog::PAID_MONITOR_TYPES as $type) {
            $this->applyPaidCheckLimit($organizationId, $type, $allowedResourceIds);
        }
    }

    /**
     * @return list<int>|null
     */
    private function allowedResourceIds(int $organizationId, Plan $plan): ?array
    {
        $maxSites = $this->effectiveMaxSites($organizationId, $plan);

        if ($maxSites === null) {
            return null;
        }

        return MonitoredResource::query()
            ->where('organization_id', $organizationId)
            ->where('type', 'website')
            ->latest('created_at')
            ->latest('id')
            ->limit($maxSites)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function effectiveMaxSites(int $organizationId, Plan $plan): ?int
    {
        $base = $this->maxSites($plan);

        if ($base === null) {
            return null;
        }

        return $base + ($this->addonQuantity($organizationId, BillingAddonCatalog::EXTRA_SITES_PACK) * 5);
    }

    private function maxSites(Plan $plan): ?int
    {
        $limit = $plan->limits->firstWhere('key', 'max_sites');
        $value = $limit?->value;

        if (! is_array($value)) {
            return null;
        }

        return array_key_exists('limit', $value) && $value['limit'] !== null
            ? (int) $value['limit']
            : null;
    }

    /**
     * @return list<string>|null
     */
    private function allowedMonitorTypes(Plan $plan): ?array
    {
        $limit = $plan->limits->firstWhere('key', 'allowed_monitor_types');
        $value = $limit?->value;

        if (! is_array($value) || ! isset($value['types']) || ! is_array($value['types'])) {
            return null;
        }

        $types = array_values(array_map('strval', $value['types']));

        return in_array('*', $types, true) ? null : $types;
    }

    /**
     * @param list<int>|null $allowedResourceIds
     */
    private function applyPaidCheckLimit(int $organizationId, string $type, ?array $allowedResourceIds): void
    {
        $limit = $this->addonQuantity($organizationId, $type);

        $allowedMonitorIds = $limit > 0
            ? Monitor::query()
                ->where('organization_id', $organizationId)
                ->where('type', $type)
                ->when($allowedResourceIds !== null, fn ($query) => $query->whereIn('monitored_resource_id', $allowedResourceIds))
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        Monitor::query()
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('enabled', true)
            ->when($allowedMonitorIds !== [], fn ($query) => $query->whereNotIn('id', $allowedMonitorIds))
            ->when($allowedMonitorIds === [], fn ($query) => $query)
            ->update([
                'enabled' => false,
                'status' => 'paused',
            ]);
    }

    private function addonQuantity(int $organizationId, string $code): int
    {
        $subscription = Subscription::query()
            ->with('items')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        $item = $subscription?->items?->firstWhere('code', $code);

        return (int) ($item?->quantity ?? 0);
    }
}
