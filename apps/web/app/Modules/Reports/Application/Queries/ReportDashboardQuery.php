<?php

namespace App\Modules\Reports\Application\Queries;

use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationLog;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Reports\Application\DTO\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReportDashboardQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $organizationId, ReportFilters $filters): array
    {
        $checks = $this->checksQuery($organizationId, $filters);
        $incidents = $this->incidentsQuery($organizationId, $filters);

        return [
            'kpi' => $this->kpi(clone $checks, clone $incidents, $organizationId, $filters),
            'series' => [
                'uptime' => $this->dailyUptime(clone $checks, $filters),
                'response_time' => $this->dailyResponseTime(clone $checks, $filters),
                'incidents' => $this->dailyIncidents(clone $incidents, $filters),
            ],
            'status_distribution' => $this->statusDistribution(clone $checks),
            'resources' => $this->resourceSummaries($organizationId, $filters),
            'projects' => $this->projectOptions($organizationId),
            'incident_history' => $this->incidentHistory($organizationId, $filters),
            'expiration_risks' => $this->expirationRisks($organizationId, $filters),
            'notification_logs' => $this->notificationLogs($organizationId, $filters),
        ];
    }

    private function checksQuery(int $organizationId, ReportFilters $filters): Builder
    {
        $query = DB::table('check_results')
            ->join('monitors', 'monitors.id', '=', 'check_results.monitor_id')
            ->join('projects', 'projects.id', '=', 'monitors.project_id')
            ->join('monitored_resources', 'monitored_resources.id', '=', 'monitors.monitored_resource_id')
            ->where('check_results.organization_id', $organizationId)
            ->whereBetween('check_results.checked_at', [$filters->start, $filters->end]);

        if ($filters->type !== 'all') {
            $query->where('check_results.check_type', $filters->type);
        }

        if ($filters->projectId !== null) {
            $query->where('monitors.project_id', $filters->projectId);
        }

        return $query;
    }

    private function incidentsQuery(int $organizationId, ReportFilters $filters): Builder
    {
        $query = DB::table('incidents')
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->join('projects', 'projects.id', '=', 'incidents.project_id')
            ->join('monitored_resources', 'monitored_resources.id', '=', 'incidents.monitored_resource_id')
            ->where('incidents.organization_id', $organizationId)
            ->whereBetween('incidents.started_at', [$filters->start, $filters->end]);

        if ($filters->type !== 'all') {
            $query->where('monitors.type', $filters->type);
        }

        if ($filters->projectId !== null) {
            $query->where('incidents.project_id', $filters->projectId);
        }

        return $query;
    }

    /**
     * @return array<string, int|float>
     */
    private function kpi(Builder $checks, Builder $incidents, int $organizationId, ReportFilters $filters): array
    {
        $checkRow = $checks
            ->selectRaw('COUNT(*) as total_checks')
            ->selectRaw("COUNT(*) FILTER (WHERE check_results.status = 'success') as successful_checks")
            ->selectRaw('COALESCE(AVG(check_results.response_time_ms) FILTER (WHERE check_results.response_time_ms IS NOT NULL), 0) as avg_response_time_ms')
            ->first();

        $incidentRow = $incidents
            ->where('incidents.severity', '!=', 'warning')
            ->selectRaw('COUNT(*) as total_incidents')
            ->selectRaw("COUNT(*) FILTER (WHERE incidents.status = 'open') as open_incidents")
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->first();

        $totalChecks = (int) ($checkRow->total_checks ?? 0);
        $successfulChecks = (int) ($checkRow->successful_checks ?? 0);

        return [
            'uptime_percent' => $totalChecks === 0 ? 100.0 : round(($successfulChecks / $totalChecks) * 100, 2),
            'total_checks' => $totalChecks,
            'avg_response_time_ms' => (int) round((float) ($checkRow->avg_response_time_ms ?? 0)),
            'total_incidents' => (int) ($incidentRow->total_incidents ?? 0),
            'open_incidents' => (int) ($incidentRow->open_incidents ?? 0),
            'downtime_seconds' => (int) ($incidentRow->downtime_seconds ?? 0),
            'monitors_total' => $this->monitorsQuery($organizationId, $filters)->count(),
            'warnings_total' => $this->incidentsQuery($organizationId, $filters)
                ->where('incidents.severity', 'warning')
                ->where('incidents.status', 'open')
                ->count(),
        ];
    }

    /**
     * @return array<int, array{date:string, value:float}>
     */
    private function dailyUptime(Builder $query, ReportFilters $filters): array
    {
        $rows = $query
            ->selectRaw('DATE(check_results.checked_at) as bucket_date')
            ->selectRaw('COUNT(*) as total_checks')
            ->selectRaw("COUNT(*) FILTER (WHERE check_results.status = 'success') as successful_checks")
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');

        return $this->dateSeries($filters, $rows, function (?object $row): float {
            if ($row === null || (int) $row->total_checks === 0) {
                return 100.0;
            }

            return round(((int) $row->successful_checks / (int) $row->total_checks) * 100, 2);
        });
    }

    /**
     * @return array<int, array{date:string, value:int}>
     */
    private function dailyResponseTime(Builder $query, ReportFilters $filters): array
    {
        $rows = $query
            ->selectRaw('DATE(check_results.checked_at) as bucket_date')
            ->selectRaw('COALESCE(AVG(check_results.response_time_ms) FILTER (WHERE check_results.response_time_ms IS NOT NULL), 0) as value')
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');

        return $this->dateSeries($filters, $rows, fn (?object $row): int => (int) round((float) ($row->value ?? 0)));
    }

    /**
     * @return array<int, array{date:string, value:int}>
     */
    private function dailyIncidents(Builder $query, ReportFilters $filters): array
    {
        $rows = $query
            ->where('incidents.severity', '!=', 'warning')
            ->selectRaw('DATE(incidents.started_at) as bucket_date')
            ->selectRaw('COUNT(*) as value')
            ->groupBy('bucket_date')
            ->get()
            ->keyBy('bucket_date');

        return $this->dateSeries($filters, $rows, fn (?object $row): int => (int) ($row->value ?? 0));
    }

    /**
     * @return array<string, int>
     */
    private function statusDistribution(Builder $query): array
    {
        return $query
            ->select('check_results.status')
            ->selectRaw('COUNT(*) as value')
            ->groupBy('check_results.status')
            ->pluck('value', 'check_results.status')
            ->mapWithKeys(fn ($value, string $status): array => [$status => (int) $value])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resourceSummaries(int $organizationId, ReportFilters $filters): array
    {
        $rows = $this->checksQuery($organizationId, $filters)
            ->select('monitored_resources.id', 'projects.name as project_name')
            ->selectRaw("COALESCE(monitored_resources.host, monitored_resources.name, 'Без сайта') as resource_name")
            ->selectRaw('COUNT(*) as total_checks')
            ->selectRaw("COUNT(*) FILTER (WHERE check_results.status = 'success') as successful_checks")
            ->selectRaw('COALESCE(AVG(check_results.response_time_ms) FILTER (WHERE check_results.response_time_ms IS NOT NULL), 0) as avg_response_time_ms')
            ->selectRaw('MAX(check_results.checked_at) as last_check_at')
            ->selectRaw('MAX(check_results.status) as last_status')
            ->groupBy('monitored_resources.id', 'monitored_resources.host', 'monitored_resources.name', 'projects.name')
            ->orderBy('projects.name')
            ->orderBy('resource_name')
            ->limit(40)
            ->get();

        $incidentRows = $this->incidentsQuery($organizationId, $filters)
            ->where('incidents.severity', '!=', 'warning')
            ->select('incidents.monitored_resource_id')
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->groupBy('incidents.monitored_resource_id')
            ->get()
            ->keyBy('monitored_resource_id');

        return $rows
            ->map(function (object $row) use ($incidentRows): array {
                $totalChecks = (int) $row->total_checks;
                $successfulChecks = (int) $row->successful_checks;
                $incident = $incidentRows->get($row->id);

                return [
                    'id' => (int) $row->id,
                    'project' => (string) $row->project_name,
                    'name' => (string) $row->resource_name,
                    'uptime_percent' => $totalChecks === 0 ? 100.0 : round(($successfulChecks / $totalChecks) * 100, 2),
                    'total_checks' => $totalChecks,
                    'incident_count' => (int) ($incident->incident_count ?? 0),
                    'downtime_seconds' => (int) ($incident->downtime_seconds ?? 0),
                    'avg_response_time_ms' => (int) round((float) $row->avg_response_time_ms),
                    'last_status' => (string) $row->last_status,
                    'last_check_at' => $row->last_check_at === null ? null : CarbonImmutable::parse($row->last_check_at)->toISOString(),
                ];
            })
            ->sortByDesc('incident_count')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    private function projectOptions(int $organizationId): array
    {
        return Project::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function incidentHistory(int $organizationId, ReportFilters $filters): array
    {
        $query = Incident::query()
            ->with(['project:id,name', 'monitoredResource:id,name,host,target', 'monitor:id,type,name'])
            ->where('organization_id', $organizationId)
            ->where('severity', '!=', 'warning')
            ->whereBetween('started_at', [$filters->start, $filters->end]);

        if ($filters->type !== 'all') {
            $query->whereHas('monitor', fn ($query) => $query->where('type', $filters->type));
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }

        return $query
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'resource' => $incident->monitoredResource?->host ?? $incident->monitoredResource?->name ?? 'Без сайта',
                'project' => $incident->project?->name ?? 'Без проекта',
                'type' => $incident->monitor?->type ?? 'unknown',
                'status' => $incident->status,
                'title' => $incident->title,
                'started_at' => $incident->started_at?->toISOString(),
                'resolved_at' => $incident->resolved_at?->toISOString(),
                'duration_seconds' => $incident->duration_seconds,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expirationRisks(int $organizationId, ReportFilters $filters): array
    {
        $query = $this->monitorsQuery($organizationId, $filters)
            ->with(['project:id,name', 'monitoredResource:id,name,host,target', 'latestCheckResult'])
            ->whereIn('type', ['ssl', 'domain'])
            ->latest('last_check_at');

        return $query
            ->limit(30)
            ->get()
            ->map(fn (Monitor $monitor): array => $this->expirationRiskPayload($monitor))
            ->filter(fn (array $row): bool => $row['days_left'] === null || $row['days_left'] <= 30)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationLogs(int $organizationId, ReportFilters $filters): array
    {
        $query = NotificationLog::query()
            ->with(['notificationChannel:id,type,name', 'incident:id,monitored_resource_id,title'])
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$filters->start, $filters->end]);

        return $query
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'channel' => $log->notificationChannel?->type ?? 'unknown',
                'channel_name' => $log->notificationChannel?->name,
                'status' => $log->status,
                'sent_at' => $log->sent_at?->toISOString(),
                'created_at' => $log->created_at?->toISOString(),
            ])
            ->all();
    }

    private function monitorsQuery(int $organizationId, ReportFilters $filters)
    {
        $query = Monitor::query()
            ->where('organization_id', $organizationId);

        if ($filters->type !== 'all') {
            $query->where('type', $filters->type);
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }

        return $query;
    }

    /**
     * @template TValue of int|float
     * @param Collection<string, object> $rows
     * @param callable(?object): TValue $value
     * @return array<int, array{date:string, value:TValue}>
     */
    private function dateSeries(ReportFilters $filters, Collection $rows, callable $value): array
    {
        $series = [];
        $cursor = $filters->start->startOfDay();
        $end = $filters->end->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'value' => $value($rows->get($date)),
            ];
            $cursor = $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    private function expirationRiskPayload(Monitor $monitor): array
    {
        $normalized = $monitor->latestCheckResult?->normalized_result ?? [];
        $raw = $monitor->latestCheckResult?->raw_result ?? [];
        $result = array_merge(is_array($raw) ? $raw : [], is_array($normalized) ? $normalized : []);
        $expiresAt = $result['expires_at']
            ?? $result['valid_to']
            ?? $result['not_after']
            ?? $result['domain_expires_at']
            ?? $result['expiration_date']
            ?? null;
        $daysLeft = $result['days_left']
            ?? $result['days_remaining']
            ?? null;

        if ($daysLeft === null && $expiresAt !== null) {
            $daysLeft = CarbonImmutable::parse((string) $expiresAt)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay(), false) * -1;
        }

        return [
            'id' => $monitor->id,
            'type' => $monitor->type,
            'resource' => $monitor->monitoredResource?->host ?? $monitor->monitoredResource?->name ?? $monitor->name,
            'project' => $monitor->project?->name ?? 'Без проекта',
            'expires_at' => $expiresAt === null ? null : CarbonImmutable::parse((string) $expiresAt)->toISOString(),
            'days_left' => $daysLeft === null ? null : (int) $daysLeft,
            'status' => $monitor->status,
            'last_check_at' => $monitor->last_check_at?->toISOString(),
        ];
    }
}
