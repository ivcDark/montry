<?php

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Projects\Application\Actions\UpdateProjectAction;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Projects\Presentation\Http\Requests\StoreProjectRequest;
use App\Modules\Projects\Presentation\Http\Requests\UpdateProjectRequest;
use App\Modules\Sites\Actions\CreateFolderAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\DTO\CreateFolderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectController
{
    public function __construct(
        private readonly CreateFolderAction $createProject,
        private readonly LimitChecker $limits,
        private readonly UpdateProjectAction $updateProject,
        private readonly GetCurrentOrganization $getCurrentOrganization,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $incidentCounts = Incident::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'open')
            ->whereNotNull('project_id')
            ->selectRaw("project_id, count(*) filter (where severity != 'warning') as incidents_count, count(*) filter (where severity = 'warning') as warnings_count")
            ->groupBy('project_id')->get()->keyBy(fn (Incident $incident): string => (string) $incident->project_id);

        $projects = Project::query()
            ->with('monitoredResources:id,project_id,name,target,host')
            ->withCount('monitoredResources as resources_count')
            ->where('organization_id', $organization->id)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Project $project): array => $this->projectPayload($project, $incidentCounts->get((string) $project->id)));

        return Inertia::render('Projects/Index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'projects' => $projects,
            'projectAccess' => $this->projectAccess((int) $organization->id),
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->limits->assertCanCreateProject((int) $organization->id);

        return Inertia::render('Projects/Form', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'project' => null,
            'resources' => $this->resourcesPayload((int) $organization->id),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $validated = $request->validated();
        DB::transaction(function () use ($organization, $validated): void {
            $project = $this->createProject->handle(new CreateFolderData(
                organizationId: $organization->id,
                name: $validated['name'],
                color: '#ffffff',
                sortOrder: 0,
                comment: $validated['comment'] ?? null,
            ));
            $this->updateProject->moveResources((int) $organization->id, $validated['resource_ids'] ?? [], (int) $project->id);
        });

        return to_route('projects.index')->with('success', 'Проект создан.');
    }

    public function edit(Request $request, Project $project): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertProjectBelongsToOrganization($project, (int) $organization->id);

        return Inertia::render('Projects/Form', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'comment' => $project->comment,
                'is_default' => $project->is_default,
                'resource_ids' => $project->monitoredResources()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ],
            'resources' => $this->resourcesPayload((int) $organization->id),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertProjectBelongsToOrganization($project, (int) $organization->id);
        $validated = $request->validated();
        $this->updateProject->handle($project, $validated['name'], $validated['comment'] ?? null, $validated['resource_ids'] ?? []);

        return to_route('projects.index')->with('success', 'Проект обновлён.');
    }

    private function projectAccess(int $organizationId): array
    {
        return [
            'current' => $this->limits->projectUsage($organizationId),
            'limit' => $this->limits->projectLimit($organizationId),
            'can_create' => $this->limits->canCreateProject($organizationId),
        ];
    }

    private function resourcesPayload(int $organizationId): array
    {
        return MonitoredResource::query()
            ->with('project:id,name')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'target', 'host'])
            ->map(fn (MonitoredResource $resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'target' => $resource->target,
                'host' => $resource->host,
                'project_id' => $resource->project_id,
                'project_name' => $resource->project?->name,
            ])->all();
    }

    private function assertProjectBelongsToOrganization(Project $project, int $organizationId): void
    {
        if ((int) $project->organization_id !== $organizationId) throw new NotFoundHttpException;
    }

    private function projectPayload(Project $project, ?Incident $incidentCounts = null): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'is_default' => $project->is_default,
            'resources_count' => $project->resources_count,
            'incidents_count' => (int) ($incidentCounts?->getAttribute('incidents_count') ?? 0),
            'warnings_count' => (int) ($incidentCounts?->getAttribute('warnings_count') ?? 0),
            'resources' => $project->monitoredResources->map(fn ($resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'target' => $resource->target,
                'host' => $resource->host,
            ])->values(),
        ];
    }
}