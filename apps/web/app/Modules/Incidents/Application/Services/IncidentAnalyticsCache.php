<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Incidents\Application\DTO\IncidentAnalyticsFilters;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class IncidentAnalyticsCache
{
    /**
     * @return array<string, mixed>
     */
    public function remember(int $organizationId, string $planCode, IncidentAnalyticsFilters $filters, ?int $projectId, Closure $callback): array
    {
        try {
            return Cache::store($this->store())->remember(
                $this->key($organizationId, $planCode, $filters, $projectId),
                now()->addMinutes(5),
                $callback,
            );
        } catch (Throwable) {
            return $callback();
        }
    }

    public function incrementVersion(int $organizationId): void
    {
        try {
            $store = Cache::store($this->store());
            $store->forever($this->versionKey($organizationId), (int) $store->get($this->versionKey($organizationId), 1) + 1);
        } catch (Throwable) {
        }
    }

    private function version(int $organizationId): int
    {
        try {
            return (int) Cache::store($this->store())->get($this->versionKey($organizationId), 1);
        } catch (Throwable) {
            return 1;
        }
    }

    private function key(int $organizationId, string $planCode, IncidentAnalyticsFilters $filters, ?int $projectId): string
    {
        $payload = [
            'organization_id' => $organizationId,
            'plan_code' => $planCode,
            'start' => $filters->start->toISOString(),
            'end' => $filters->end->toISOString(),
            'type' => $filters->type,
            'project_id' => $projectId,
            'version' => $this->version($organizationId),
        ];

        return 'incident_analytics:'.sha1(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function versionKey(int $organizationId): string
    {
        return "incident_analytics_version:{$organizationId}";
    }

    private function store(): string
    {
        return (string) config('cache.default', 'redis');
    }
}
