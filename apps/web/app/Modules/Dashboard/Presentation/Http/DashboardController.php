<?php

namespace App\Modules\Dashboard\Presentation\Http;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function index(Request $request, GetCurrentOrganization $getCurrentOrganization): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $monitors = Monitor::query()
            ->with([
                'monitoredResource:id,name,target,host,status',
                'project:id,name',
                'latestCheckResult' => fn ($query) => $query->select([
                    'check_results.id',
                    'check_results.monitor_id',
                    'check_results.status',
                    'check_results.checked_at',
                    'check_results.response_time_ms',
                    'check_results.status_code',
                    'check_results.error_message',
                    'check_results.normalized_result',
                ]),
            ])
            ->where('organization_id', $organization->id)
            ->get();

        $resourcesCount = MonitoredResource::query()
            ->where('organization_id', $organization->id)
            ->count();

        $projectsCount = Project::query()
            ->where('organization_id', $organization->id)
            ->count();

        $downMonitors = $monitors->filter(fn (Monitor $monitor): bool => $this->statusKey($monitor) === 'down');
        $warningMonitors = $monitors->filter(fn (Monitor $monitor): bool => $this->statusKey($monitor) === 'warning');
        $okMonitors = $monitors->filter(fn (Monitor $monitor): bool => $this->statusKey($monitor) === 'ok');

        $latestChecks = CheckResult::query()
            ->with('monitor.monitoredResource:id,name,target,host,status')
            ->where('organization_id', $organization->id)
            ->latest('checked_at')
            ->limit(5)
            ->get()
            ->map(fn (CheckResult $result): array => [
                'id' => $result->id,
                'site' => $result->monitor?->monitoredResource?->host
                    ?? $result->monitor?->monitoredResource?->name
                    ?? 'Без сайта',
                'type' => strtoupper($result->check_type),
                'result' => $this->checkResultLabel($result),
                'response' => $result->response_time_ms ? "{$result->response_time_ms} мс" : '—',
                'checked_at' => $result->checked_at?->toISOString(),
                'status' => $result->status,
            ]);

        $incidents = Incident::query()
            ->with('monitoredResource:id,name,target,host,status')
            ->where('organization_id', $organization->id)
            ->latest('started_at')
            ->limit(4)
            ->get()
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'site' => $incident->monitoredResource?->host
                    ?? $incident->monitoredResource?->name
                    ?? 'Без сайта',
                'reason' => $incident->title,
                'duration' => $incident->duration_seconds
                    ? $this->formatDuration($incident->duration_seconds)
                    : '—',
                'status' => $incident->status,
                'started_at' => $incident->started_at?->toISOString(),
            ]);

        return Inertia::render('Dashboard/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'summary' => [
                'total_resources' => $resourcesCount,
                'total_projects' => $projectsCount,
                'total_monitors' => $monitors->count(),
                'ok_monitors' => $okMonitors->count(),
                'down_monitors' => $downMonitors->count(),
                'warning_monitors' => $warningMonitors->count(),
                'ssl_expiring' => $this->expiringCount($monitors, 'ssl', 14),
                'domain_expiring' => $this->expiringCount($monitors, 'domain', 30),
                'latest_check_at' => $latestChecks->first()['checked_at'] ?? null,
            ],
            'problems' => $downMonitors
                ->merge($warningMonitors)
                ->take(6)
                ->values()
                ->map(fn (Monitor $monitor): array => [
                    'id' => $monitor->id,
                    'site_id' => $monitor->monitored_resource_id,
                    'site' => $monitor->monitoredResource?->host
                        ?? $monitor->monitoredResource?->name
                        ?? $monitor->name,
                    'problem' => $this->problemLabel($monitor),
                    'status' => $this->statusKey($monitor),
                    'last_check_at' => $monitor->last_check_at?->toISOString(),
                    'action' => $this->statusKey($monitor) === 'down' ? 'Открыть' : 'Проверить',
                ]),
            'incidents' => $incidents,
            'latest_checks' => $latestChecks,
        ]);
    }

    private function statusKey(Monitor $monitor): string
    {
        if (! $monitor->is_enabled || $monitor->status === 'paused') {
            return 'paused';
        }

        if (in_array($monitor->status, ['success', 'up'], true)) {
            return 'ok';
        }

        if (in_array($monitor->status, ['failure', 'down'], true)) {
            return 'down';
        }

        if (in_array($monitor->status, ['degraded', 'warning'], true)
            || $monitor->latestCheckResult?->status === 'warning') {
            return 'warning';
        }

        return 'unknown';
    }

    private function problemLabel(Monitor $monitor): string
    {
        if ($this->statusKey($monitor) === 'down') {
            return $monitor->latestCheckResult?->status_code
                ? "Ответ {$monitor->latestCheckResult->status_code}"
                : 'Сайт недоступен';
        }

        if ($monitor->type === 'ssl') {
            return 'SSL истекает';
        }

        if ($monitor->type === 'domain') {
            return 'Домен истекает';
        }

        return 'Требует внимания';
    }

    private function checkResultLabel(CheckResult $result): string
    {
        if ($result->status_code) {
            return $result->status_code >= 200 && $result->status_code < 300
                ? "{$result->status_code} OK"
                : (string) $result->status_code;
        }

        $days = $result->normalized_result['days_until_expiration'] ?? null;

        if (is_numeric($days)) {
            return "{$days} дней";
        }

        return $result->status === 'success' ? 'OK' : ucfirst($result->status);
    }

    private function expiringCount($monitors, string $type, int $thresholdDays): int
    {
        return $monitors
            ->where('type', $type)
            ->filter(function (Monitor $monitor) use ($thresholdDays): bool {
                $days = $monitor->latestCheckResult?->normalized_result['days_until_expiration'] ?? null;

                return is_numeric($days) && (int) $days <= $thresholdDays;
            })
            ->count();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, (int) ceil($seconds / 60)).' мин';
        }

        return max(1, (int) ceil($seconds / 3600)).' ч';
    }
}
