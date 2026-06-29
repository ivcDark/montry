<?php

namespace App\Modules\MonitoredResources\Application\Handlers;

use App\Modules\MonitoredResources\Application\Queries\ListMonitoredResourcesQuery;
use App\Modules\MonitoredResources\Application\Services\SiteNotificationChannels;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use Illuminate\Support\Collection;

final class ListMonitoredResourcesHandler
{
    public function __construct(
        private readonly SiteNotificationChannels $siteNotificationChannels,
    ) {
    }

    public function handle(ListMonitoredResourcesQuery $query): Collection
    {
        $connectedNotificationTypes = $this->siteNotificationChannels->connectedTypes($query->organizationId);

        return MonitoredResource::query()
            ->with([
                'project:id,name',
                'monitors.latestCheckResult' => fn ($query) => $query->select([
                    'check_results.id',
                    'check_results.monitor_id',
                    'check_results.status',
                    'check_results.checked_at',
                    'check_results.response_time_ms',
                    'check_results.status_code',
                    'check_results.error_code',
                    'check_results.error_message',
                    'check_results.normalized_result',
                ]),
            ])
            ->where('organization_id', $query->organizationId)
            ->latest()
            ->get()
            ->map(fn (MonitoredResource $resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'url' => $resource->url,
                'host' => $resource->host,
                'status' => $this->resourceStatus($resource),
                'raw_status' => $resource->status,
                'problem_label' => $this->problemLabel($resource),
                'monitors_count' => $resource->monitors->count(),
                'enabled_monitors_count' => $resource->monitors
                    ->filter(fn (Monitor $monitor): bool => $monitor->is_enabled)
                    ->count(),
                'last_checked_at' => $resource->monitors
                    ->pluck('last_check_at')
                    ->filter()
                    ->sortDesc()
                    ->first()
                    ?->toISOString(),
                'notification_channels' => $this->siteNotificationChannels->payload($resource, $connectedNotificationTypes),
                'project' => $resource->project
                    ? [
                        'id' => $resource->project->id,
                        'name' => $resource->project->name,
                    ]
                    : null,
                'monitors' => $resource->monitors
                    ->sortBy(fn (Monitor $monitor): string => "{$monitor->type}-{$monitor->name}")
                    ->values()
                    ->map(fn (Monitor $monitor): array => [
                        'id' => $monitor->id,
                        'type' => $monitor->type,
                        'name' => $monitor->name,
                        'status' => $monitor->status,
                        'is_enabled' => $monitor->is_enabled,
                        'interval_seconds' => $monitor->interval_seconds,
                        'last_check_at' => $monitor->last_check_at?->toISOString(),
                        'next_check_at' => $monitor->next_check_at?->toISOString(),
                        'latest_result' => $monitor->latestCheckResult
                            ? [
                                'status' => $monitor->latestCheckResult->status,
                                'checked_at' => $monitor->latestCheckResult->checked_at?->toISOString(),
                                'response_time_ms' => $monitor->latestCheckResult->response_time_ms,
                                'status_code' => $monitor->latestCheckResult->status_code,
                                'error_code' => $monitor->latestCheckResult->error_code,
                                'error_message' => $monitor->latestCheckResult->error_message,
                                'normalized_result' => $monitor->latestCheckResult->normalized_result ?? [],
                            ]
                            : null,
                    ]),
            ]);
    }

    private function resourceStatus(MonitoredResource $resource): string
    {
        $monitors = $resource->monitors;
        $enabledMonitors = $monitors->filter(fn (Monitor $monitor): bool => $monitor->is_enabled);

        if ($enabledMonitors->contains(fn (Monitor $monitor): bool => $this->isDown($monitor))) {
            return 'down';
        }

        if ($enabledMonitors->contains(fn (Monitor $monitor): bool => $this->isWarning($monitor))) {
            return 'warning';
        }

        if ($monitors->isNotEmpty() && $enabledMonitors->isEmpty()) {
            return 'paused';
        }

        if ($enabledMonitors->isNotEmpty()) {
            return 'ok';
        }

        return 'empty';
    }

    private function problemLabel(MonitoredResource $resource): string
    {
        $enabledMonitors = $resource->monitors->filter(fn (Monitor $monitor): bool => $monitor->is_enabled);
        $downCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isDown($monitor))->count();
        $warningCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isWarning($monitor))->count();

        if ($downCount > 0) {
            return $this->plural($downCount, 'монитор упал', 'монитора упали', 'мониторов упали');
        }

        if ($warningCount > 0) {
            return $this->plural($warningCount, 'warning', 'warning', 'warning');
        }

        if ($resource->monitors->isNotEmpty() && $enabledMonitors->isEmpty()) {
            return 'Мониторинг на паузе';
        }

        if ($resource->monitors->isEmpty()) {
            return 'Нет мониторингов';
        }

        return 'Нет';
    }

    private function isDown(Monitor $monitor): bool
    {
        return in_array($monitor->status, ['failure', 'down'], true);
    }

    private function isWarning(Monitor $monitor): bool
    {
        return in_array($monitor->status, ['degraded', 'warning'], true)
            || $monitor->latestCheckResult?->status === 'warning';
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        $word = $many;

        if ($mod10 === 1 && $mod100 !== 11) {
            $word = $one;
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            $word = $few;
        }

        return "{$count} {$word}";
    }
}
