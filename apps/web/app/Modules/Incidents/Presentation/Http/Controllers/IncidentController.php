<?php

namespace App\Modules\Incidents\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentController extends Controller
{
    public function index(Request $request, GetCurrentOrganization $getCurrentOrganization): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());
        $periodDays = $this->periodDays($request->string('period', '30')->toString());
        $periodStart = now()->subDays($periodDays);
        $search = trim($request->string('search')->toString());
        $type = $request->string('type', 'all')->toString();

        $baseQuery = Incident::query()
            ->with([
                'project:id,name',
                'monitoredResource:id,name,target,host',
                'monitor:id,type,name',
            ])
            ->where('organization_id', $organization->id);

        $filteredQuery = $this->applyFilters(clone $baseQuery, $search, $type);
        $periodFilteredQuery = (clone $filteredQuery)->where('started_at', '>=', $periodStart);

        $activeIncidents = (clone $filteredQuery)
            ->where('status', 'open')
            ->where('severity', '!=', 'warning')
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn (Incident $incident): array => $this->incidentPayload($incident))
            ->all();

        $resolvedIncidents = (clone $periodFilteredQuery)
            ->where('status', 'resolved')
            ->where('severity', '!=', 'warning')
            ->latest('resolved_at')
            ->latest('started_at')
            ->limit(30)
            ->get()
            ->map(fn (Incident $incident): array => $this->incidentPayload($incident))
            ->all();

        $warnings = (clone $filteredQuery)
            ->where('severity', 'warning')
            ->where('status', 'open')
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn (Incident $incident): array => $this->incidentPayload($incident))
            ->all();

        return Inertia::render('Incidents/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'filters' => [
                'search' => $search,
                'period' => (string) $periodDays,
                'type' => $type,
            ],
            'summary' => [
                'open_incidents' => (clone $baseQuery)
                    ->where('status', 'open')
                    ->where('severity', '!=', 'warning')
                    ->count(),
                'resolved_last_24_hours' => Incident::query()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'resolved')
                    ->where('severity', '!=', 'warning')
                    ->where('resolved_at', '>=', now()->subDay())
                    ->count(),
                'downtime_30_days_seconds' => (int) Incident::query()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'resolved')
                    ->where('severity', '!=', 'warning')
                    ->where('started_at', '>=', now()->subDays(30))
                    ->sum('duration_seconds'),
                'warnings' => (clone $baseQuery)
                    ->where('severity', 'warning')
                    ->where('status', 'open')
                    ->count(),
            ],
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'warnings' => $warnings,
        ]);
    }

    private function applyFilters(Builder $query, string $search, string $type): Builder
    {
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('summary', 'ilike', "%{$search}%")
                    ->orWhereHas('monitoredResource', function (Builder $query) use ($search): void {
                        $query
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('host', 'ilike', "%{$search}%")
                            ->orWhere('target', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('project', function (Builder $query) use ($search): void {
                        $query->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        if (in_array($type, ['http', 'ssl', 'domain'], true)) {
            $query->whereHas('monitor', function (Builder $query) use ($type): void {
                $query->where('type', $type);
            });
        }

        return $query;
    }

    private function incidentPayload(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'site_id' => $incident->monitored_resource_id,
            'monitor_id' => $incident->monitor_id,
            'site' => $incident->monitoredResource?->host
                ?? $incident->monitoredResource?->name
                ?? 'Без сайта',
            'target' => $incident->monitoredResource?->target,
            'project' => $incident->project?->name ?? 'Без проекта',
            'type' => $incident->monitor?->type ?? 'unknown',
            'status' => $incident->status,
            'severity' => $incident->severity,
            'title' => $incident->title,
            'summary' => $incident->summary,
            'started_at' => $incident->started_at?->toISOString(),
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'duration_seconds' => $incident->duration_seconds,
            'current_duration_seconds' => $incident->status === 'open'
                ? max(0, (int) $incident->started_at?->diffInSeconds(now()))
                : null,
        ];
    }

    private function periodDays(string $period): int
    {
        return match ($period) {
            '1', '24' => 1,
            '7' => 7,
            default => 30,
        };
    }
}
