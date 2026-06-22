<?php

namespace App\Modules\StatusPages\Application\Queries;

use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\StatusPages\Infrastructure\Persistence\Models\StatusPage;

final class StatusPagePublicQuery
{
    public function __construct(private readonly MonitorTypeCatalog $monitorTypes) {}

    public function build(StatusPage $statusPage): array
    {
        $statusPage->load([
            'monitors.monitoredResource:id,name,target,host',
            'monitors.latestCheckResult',
        ]);

        $monitors = $statusPage->monitors
            ->map(fn (Monitor $monitor): array => $this->monitorPayload($monitor))
            ->values();

        $monitorIds = $statusPage->monitors->pluck('id');
        $incidents = $statusPage->show_incident_history
            ? Incident::query()
                ->with('monitoredResource:id,name')
                ->whereIn('monitor_id', $monitorIds)
                ->where(function ($query): void {
                    $query->where('status', 'open')
                        ->orWhere('started_at', '>=', now()->subDays(90));
                })
                ->latest('started_at')
                ->limit(10)
                ->get()
                ->map(fn (Incident $incident): array => [
                    'id' => $incident->id,
                    'title' => $incident->title,
                    'summary' => $incident->summary,
                    'resource' => $incident->monitoredResource?->name,
                    'status' => $incident->status,
                    'severity' => $incident->severity,
                    'started_at' => $incident->started_at?->toISOString(),
                    'resolved_at' => $incident->resolved_at?->toISOString(),
                ])
                ->values()
            : collect();

        return [
            'id' => $statusPage->id,
            'name' => $statusPage->name,
            'slug' => $statusPage->slug,
            'description' => $statusPage->description,
            'accent_color' => $statusPage->accent_color,
            'overall_status' => $this->overallStatus($monitors->pluck('status')->all()),
            'updated_at' => $statusPage->monitors->max('last_check_at')?->toISOString(),
            'monitors' => $monitors,
            'incidents' => $incidents,
        ];
    }

    private function monitorPayload(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->pivot->display_name ?: $monitor->monitoredResource?->name ?: $monitor->name,
            'target' => $monitor->monitoredResource?->host ?: $monitor->monitoredResource?->target,
            'type' => $monitor->type,
            'type_label' => $this->monitorTypes->label($monitor->type),
            'status' => $monitor->enabled ? $this->normalizeStatus($monitor->status) : 'paused',
            'last_check_at' => $monitor->last_check_at?->toISOString(),
            'response_time_ms' => $monitor->latestCheckResult?->response_time_ms,
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function overallStatus(array $statuses): string
    {
        if (in_array('outage', $statuses, true)) {
            return 'outage';
        }

        if (in_array('degraded', $statuses, true)) {
            return 'degraded';
        }

        if ($statuses !== [] && count(array_filter($statuses, fn (string $status): bool => $status === 'operational')) === count($statuses)) {
            return 'operational';
        }

        return 'unknown';
    }

    private function normalizeStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'success', 'up', 'healthy', 'operational' => 'operational',
            'warning', 'degraded' => 'degraded',
            'failure', 'failed', 'down', 'critical', 'outage' => 'outage',
            default => 'unknown',
        };
    }
}
