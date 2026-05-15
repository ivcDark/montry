<?php

namespace App\Modules\MonitoredResources\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MonitoredResources\Application\Handlers\ListMonitoredResourcesHandler;
use App\Modules\MonitoredResources\Application\Queries\ListMonitoredResourcesQuery;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\MonitoredResources\Presentation\Http\Requests\StoreMonitoredResourceRequest;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MonitoredResourceController extends Controller
{
    public function index(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        ListMonitoredResourcesHandler $listMonitoredResources,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        return Inertia::render('Sites/Index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'sites' => $listMonitoredResources->handle(new ListMonitoredResourcesQuery($organization->id)),
        ]);
    }

    public function create(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        CheckTypeRegistry $checkTypes,
    ): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        return Inertia::render('Sites/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'monitorTypes' => collect($checkTypes->all())
                ->map(fn ($definition) => [
                    'value' => $definition->type(),
                    'label' => $definition->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(
        StoreMonitoredResourceRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CreateDefaultFolderForOrganization $createDefaultProject,
        CreateSiteAction $createMonitoredResource,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());
        $project = $createDefaultProject->handle($organization);

        $siteData = $request->toData(
            organizationId: $organization->id,
            project: $project,
        );

        $createMonitoredResource->handle(
            $siteData,
            $request->monitorPayloads([
                'url' => $siteData->url,
                'host' => $siteData->host,
                'port' => $siteData->port,
            ]),
        );

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site added.');
    }

    public function show(
        Request $request,
        MonitoredResource $site,
        GetCurrentOrganization $getCurrentOrganization,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());
        $site->load([
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
        ]);

        if ($site->organization_id !== $organization->id) {
            throw new NotFoundHttpException();
        }

        return Inertia::render('Sites/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'scheme' => $site->scheme,
                'host' => $site->host,
                'port' => $site->port,
                'path' => $site->path,
                'status' => $this->resourceStatus($site),
                'raw_status' => $site->status,
                'problem_label' => $this->problemLabel($site),
                'created_at' => $site->created_at?->toISOString(),
                'updated_at' => $site->updated_at?->toISOString(),
                'project' => $site->project
                    ? [
                        'id' => $site->project->id,
                        'name' => $site->project->name,
                    ]
                    : null,
                'monitors' => $site->monitors
                    ->sortBy(fn (Monitor $monitor): string => sprintf(
                        '%d-%d-%s',
                        (! $monitor->is_enabled || $monitor->status === 'paused') ? 1 : 0,
                        $this->monitorTypeOrder($monitor->type),
                        $monitor->name,
                    ))
                    ->values()
                    ->map(fn (Monitor $monitor) => [
                    'id' => $monitor->id,
                    'name' => $monitor->name,
                    'type' => $monitor->type,
                    'status' => $monitor->status,
                    'is_enabled' => $monitor->is_enabled,
                    'interval_seconds' => $monitor->interval_seconds,
                    'timeout_ms' => $monitor->timeout_ms,
                    'settings' => $monitor->settings,
                    'expected' => $monitor->expected,
                    'last_check_at' => $monitor->last_check_at?->toISOString(),
                    'next_check_at' => $monitor->next_check_at?->toISOString(),
                    'last_success_at' => $monitor->last_success_at?->toISOString(),
                    'last_failure_at' => $monitor->last_failure_at?->toISOString(),
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
                'recent_checks' => $this->recentChecks($site),
                'incidents' => $this->incidents($site),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        MonitoredResource $site,
        GetCurrentOrganization $getCurrentOrganization,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($site->organization_id !== $organization->id) {
            throw new NotFoundHttpException();
        }

        DB::transaction(function () use ($site): void {
            $site->monitors()->delete();
            $site->delete();
        });

        return to_route('sites.index')
            ->with('success', 'Site deleted.');
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

    private function recentChecks(MonitoredResource $site): array
    {
        return CheckResult::query()
            ->where('organization_id', $site->organization_id)
            ->whereIn('monitor_id', $site->monitors->pluck('id'))
            ->latest('checked_at')
            ->limit(10)
            ->get()
            ->map(fn (CheckResult $result): array => [
                'id' => $result->id,
                'monitor_id' => $result->monitor_id,
                'check_type' => $result->check_type,
                'status' => $result->status,
                'checked_at' => $result->checked_at?->toISOString(),
                'response_time_ms' => $result->response_time_ms,
                'status_code' => $result->status_code,
                'error_code' => $result->error_code,
                'error_message' => $result->error_message,
                'normalized_result' => $result->normalized_result ?? [],
            ])
            ->all();
    }

    private function incidents(MonitoredResource $site): array
    {
        return Incident::query()
            ->where('organization_id', $site->organization_id)
            ->where('monitored_resource_id', $site->id)
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'title' => $incident->title,
                'summary' => $incident->summary,
                'started_at' => $incident->started_at?->toISOString(),
                'resolved_at' => $incident->resolved_at?->toISOString(),
                'duration_seconds' => $incident->duration_seconds,
            ])
            ->all();
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

    private function monitorTypeOrder(string $type): int
    {
        return match ($type) {
            'http' => 0,
            'ssl' => 1,
            'domain' => 2,
            default => 99,
        };
    }
}
