<?php

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Projects\Presentation\Http\Requests\StoreProjectRequest;
use App\Modules\Sites\Actions\CreateFolderAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\DTO\CreateFolderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly CreateFolderAction $createProject,
        private readonly GetCurrentOrganization $getCurrentOrganization,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        $projects = Project::query()
            ->with([
                'monitoredResources:id,project_id,name,target,host,status',
                'monitors.latestCheckResult' => fn ($query) => $query->select([
                    'check_results.id',
                    'check_results.monitor_id',
                    'check_results.status',
                    'check_results.checked_at',
                    'check_results.normalized_result',
                ]),
            ])
            ->withCount([
                'monitoredResources as resources_count',
                'monitors as monitors_count',
            ])
            ->where('organization_id', $organization->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => $this->projectPayload($project));

        return Inertia::render('Projects/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'projects' => $projects,
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        return Inertia::render('Sites/Folders/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $validated = $request->validated();

        $this->createProject->handle(
            new CreateFolderData(
                organizationId: $organization->id,
                name: $validated['name'],
                color: $validated['color'] ?? '#ffffff',
                sortOrder: $validated['sort_order'] ?? 0,
            ),
        );

        return to_route('projects.index');
    }

    private function projectPayload(Project $project): array
    {
        $monitors = $project->monitors;
        $enabledMonitors = $monitors->filter(fn (Monitor $monitor): bool => $monitor->is_enabled);
        $downCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isDown($monitor))->count();
        $warningCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isWarning($monitor))->count();
        $pausedCount = $monitors->filter(fn (Monitor $monitor): bool => ! $monitor->is_enabled || $monitor->status === 'paused')->count();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color,
            'is_default' => $project->is_default,
            'resources_count' => $project->resources_count,
            'monitors_count' => $project->monitors_count,
            'status' => $this->projectStatus($project, $downCount, $warningCount, $pausedCount),
            'problem_label' => $this->problemLabel($downCount, $warningCount, $pausedCount),
            'ssl_days' => $this->minDaysForType($project, 'ssl'),
            'domain_days' => $this->minDaysForType($project, 'domain'),
            'last_incident_at' => $monitors
                ->pluck('last_failure_at')
                ->filter()
                ->sortDesc()
                ->first()
                ?->toISOString(),
            'resources' => $project->monitoredResources->map(fn ($resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'target' => $resource->target,
                'host' => $resource->host,
                'status' => $resource->status,
            ])->values(),
        ];
    }

    private function projectStatus(Project $project, int $downCount, int $warningCount, int $pausedCount): string
    {
        if ($downCount > 0) {
            return 'down';
        }

        if ($warningCount > 0) {
            return 'warning';
        }

        if ($project->monitors_count > 0 && $pausedCount === $project->monitors_count) {
            return 'paused';
        }

        if ($project->monitors_count > 0) {
            return 'ok';
        }

        return 'empty';
    }

    private function problemLabel(int $downCount, int $warningCount, int $pausedCount): string
    {
        if ($downCount > 0) {
            return $this->plural($downCount, 'монитор упал', 'монитора упали', 'мониторов упали');
        }

        if ($warningCount > 0) {
            return $this->plural($warningCount, 'warning', 'warning', 'warning');
        }

        if ($pausedCount > 0) {
            return 'Мониторинг на паузе';
        }

        return 'Нет';
    }

    private function minDaysForType(Project $project, string $type): ?int
    {
        return $project->monitors
            ->where('type', $type)
            ->map(fn (Monitor $monitor): mixed => $monitor->latestCheckResult?->normalized_result['days_until_expiration'] ?? null)
            ->filter(fn (mixed $days): bool => is_numeric($days))
            ->map(fn (mixed $days): int => (int) $days)
            ->min();
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
