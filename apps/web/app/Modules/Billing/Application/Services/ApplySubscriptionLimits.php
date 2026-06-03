<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final class ApplySubscriptionLimits
{
    public function handle(int $organizationId, Plan $plan): void
    {
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

        $maxMonitors = $this->maxMonitors($plan);

        if ($maxMonitors === null) {
            return;
        }

        $enabledAllowedMonitorIds = Monitor::query()
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->when($allowedResourceIds !== null, fn ($query) => $query->whereIn('monitored_resource_id', $allowedResourceIds))
            ->when($allowedTypes !== null, fn ($query) => $query->whereIn('type', $allowedTypes))
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $monitorIdsToPause = array_slice($enabledAllowedMonitorIds, $maxMonitors);

        if ($monitorIdsToPause === []) {
            return;
        }

        Monitor::query()
            ->whereIn('id', $monitorIdsToPause)
            ->update([
                'enabled' => false,
                'status' => 'paused',
            ]);
    }

    /**
     * @return list<int>|null
     */
    private function allowedResourceIds(int $organizationId, Plan $plan): ?array
    {
        $maxSites = $this->maxSites($plan);

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

    private function maxMonitors(Plan $plan): ?int
    {
        $limit = $plan->limits->firstWhere('key', 'max_monitors');
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
}
