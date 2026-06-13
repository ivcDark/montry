<?php

namespace App\Modules\Incidents\Application\Queries;

use App\Modules\Incidents\Application\DTO\IncidentAnalyticsFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IncidentAnalyticsQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $organizationId, IncidentAnalyticsFilters $filters): array
    {
        $base = $this->baseQuery($organizationId, $filters);
        $previous = $this->baseQueryForRange($organizationId, $filters, $filters->previousStart(), $filters->previousEnd());

        $projects = $this->projectSummaries(clone $base);
        $selectedProjectId = $this->selectedProjectId($projects, $filters->projectId);
        $selectedProject = $projects->firstWhere('id', $selectedProjectId);

        return [
            'kpi' => $this->kpi(clone $base),
            'comparison' => $this->comparison(clone $base, clone $previous),
            'series' => [
                'incident_counts' => $this->dailyIncidentCounts(clone $base, $filters),
                'downtime_seconds' => $this->dailyDowntime(clone $base, $filters),
            ],
            'type_distribution' => $this->typeDistribution(clone $base),
            'projects' => $projects->values()->all(),
            'selected_project_id' => $selectedProjectId,
            'selected_project' => $selectedProject === null ? null : [
                'id' => $selectedProject['id'],
                'name' => $selectedProject['name'],
            ],
            'sites' => $selectedProjectId === null ? [] : $this->siteSummaries(clone $base, $selectedProjectId)->values()->all(),
            'top_sites' => $this->topSites(clone $base)->values()->all(),
        ];
    }

    private function baseQuery(int $organizationId, IncidentAnalyticsFilters $filters): Builder
    {
        return $this->baseQueryForRange($organizationId, $filters, $filters->start, $filters->end);
    }

    private function baseQueryForRange(int $organizationId, IncidentAnalyticsFilters $filters, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = DB::table('incidents')
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->join('projects', 'projects.id', '=', 'incidents.project_id')
            ->join('monitored_resources', 'monitored_resources.id', '=', 'incidents.monitored_resource_id')
            ->where('incidents.organization_id', $organizationId)
            ->where('incidents.severity', '!=', 'warning')
            ->whereBetween('incidents.started_at', [$start, $end]);

        if ($filters->type !== 'all') {
            $query->where('monitors.type', $filters->type);
        }

        return $query;
    }

    /**
     * @return array{total_incidents:int, active_incidents:int, downtime_seconds:int, mttr_seconds:int}
     */
    private function kpi(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as total_incidents')
            ->selectRaw("COUNT(*) FILTER (WHERE incidents.status = 'open') as active_incidents")
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->selectRaw("COALESCE(AVG(incidents.duration_seconds) FILTER (WHERE incidents.duration_seconds IS NOT NULL), 0) as mttr_seconds")
            ->first();

        return [
            'total_incidents' => (int) ($row->total_incidents ?? 0),
            'active_incidents' => (int) ($row->active_incidents ?? 0),
            'downtime_seconds' => (int) ($row->downtime_seconds ?? 0),
            'mttr_seconds' => (int) round((float) ($row->mttr_seconds ?? 0)),
        ];
    }

    /**
     * @return array{total_incidents_delta:int, downtime_seconds_delta:int, mttr_seconds_delta:int}
     */
    private function comparison(Builder $current, Builder $previous): array
    {
        $currentKpi = $this->kpi($current);
        $previousKpi = $this->kpi($previous);

        return [
            'total_incidents_delta' => $currentKpi['total_incidents'] - $previousKpi['total_incidents'],
            'downtime_seconds_delta' => $currentKpi['downtime_seconds'] - $previousKpi['downtime_seconds'],
            'mttr_seconds_delta' => $currentKpi['mttr_seconds'] - $previousKpi['mttr_seconds'],
        ];
    }

    /**
     * @return array<int, array{date:string, value:int}>
     */
    private function dailyIncidentCounts(Builder $query, IncidentAnalyticsFilters $filters): array
    {
        $rows = $query
            ->selectRaw("DATE(incidents.started_at) as bucket_date")
            ->selectRaw('COUNT(*) as value')
            ->groupBy('bucket_date')
            ->pluck('value', 'bucket_date');

        return $this->dateSeries($filters, $rows);
    }

    /**
     * @return array<int, array{date:string, value:int}>
     */
    private function dailyDowntime(Builder $query, IncidentAnalyticsFilters $filters): array
    {
        $rows = $query
            ->selectRaw("DATE(incidents.started_at) as bucket_date")
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as value')
            ->groupBy('bucket_date')
            ->pluck('value', 'bucket_date');

        return $this->dateSeries($filters, $rows);
    }

    /**
     * @param Collection<string, int|string> $rows
     * @return array<int, array{date:string, value:int}>
     */
    private function dateSeries(IncidentAnalyticsFilters $filters, Collection $rows): array
    {
        $series = [];
        $cursor = $filters->start->startOfDay();
        $end = $filters->end->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'value' => (int) ($rows[$date] ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array<string, int>
     */
    private function typeDistribution(Builder $query): array
    {
        $rows = $query
            ->select('monitors.type')
            ->selectRaw('COUNT(*) as value')
            ->groupBy('monitors.type')
            ->pluck('value', 'monitors.type');

        return $rows
            ->mapWithKeys(fn ($value, string $type): array => [$type => (int) $value])
            ->all();
    }

    /**
     * @return Collection<int, array{id:int, name:string, incident_count:int, downtime_seconds:int, mttr_seconds:int, affected_sites:int}>
     */
    private function projectSummaries(Builder $query): Collection
    {
        return $query
            ->select('projects.id', 'projects.name')
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->selectRaw("COALESCE(AVG(incidents.duration_seconds) FILTER (WHERE incidents.duration_seconds IS NOT NULL), 0) as mttr_seconds")
            ->selectRaw('COUNT(DISTINCT incidents.monitored_resource_id) as affected_sites')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('incident_count')
            ->orderByDesc('downtime_seconds')
            ->orderBy('projects.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'incident_count' => (int) $row->incident_count,
                'downtime_seconds' => (int) $row->downtime_seconds,
                'mttr_seconds' => (int) round((float) $row->mttr_seconds),
                'affected_sites' => (int) $row->affected_sites,
            ]);
    }

    /**
     * @param Collection<int, array{id:int, name:string, incident_count:int, downtime_seconds:int, mttr_seconds:int, affected_sites:int}> $projects
     */
    private function selectedProjectId(Collection $projects, ?int $requestedProjectId): ?int
    {
        if ($requestedProjectId !== null && $projects->contains(fn (array $project): bool => $project['id'] === $requestedProjectId)) {
            return $requestedProjectId;
        }

        $first = $projects->first();

        return $first === null ? null : (int) $first['id'];
    }

    /**
     * @return Collection<int, array{id:int, name:string, incident_count:int, downtime_seconds:int, mttr_seconds:int, last_incident_at:?string}>
     */
    private function siteSummaries(Builder $query, int $projectId): Collection
    {
        return $query
            ->where('incidents.project_id', $projectId)
            ->select('monitored_resources.id')
            ->selectRaw("COALESCE(monitored_resources.host, monitored_resources.name, 'Без сайта') as name")
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->selectRaw("COALESCE(AVG(incidents.duration_seconds) FILTER (WHERE incidents.duration_seconds IS NOT NULL), 0) as mttr_seconds")
            ->selectRaw('MAX(incidents.started_at) as last_incident_at')
            ->groupBy('monitored_resources.id', 'monitored_resources.host', 'monitored_resources.name')
            ->orderByDesc('incident_count')
            ->orderByDesc('downtime_seconds')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'incident_count' => (int) $row->incident_count,
                'downtime_seconds' => (int) $row->downtime_seconds,
                'mttr_seconds' => (int) round((float) $row->mttr_seconds),
                'last_incident_at' => $row->last_incident_at === null ? null : CarbonImmutable::parse($row->last_incident_at)->toISOString(),
            ]);
    }

    /**
     * @return Collection<int, array{id:int, name:string, incident_count:int, downtime_seconds:int}>
     */
    private function topSites(Builder $query): Collection
    {
        return $query
            ->select('monitored_resources.id')
            ->selectRaw("COALESCE(monitored_resources.host, monitored_resources.name, 'Без сайта') as name")
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(COALESCE(incidents.duration_seconds, 0)), 0) as downtime_seconds')
            ->groupBy('monitored_resources.id', 'monitored_resources.host', 'monitored_resources.name')
            ->orderByDesc('incident_count')
            ->orderByDesc('downtime_seconds')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'incident_count' => (int) $row->incident_count,
                'downtime_seconds' => (int) $row->downtime_seconds,
            ]);
    }
}
