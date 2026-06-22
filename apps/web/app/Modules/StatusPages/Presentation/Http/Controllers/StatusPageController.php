<?php

namespace App\Modules\StatusPages\Presentation\Http\Controllers;

use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\StatusPages\Application\Commands\CreateStatusPage;
use App\Modules\StatusPages\Application\Commands\UpdateStatusPage;
use App\Modules\StatusPages\Application\Handlers\CreateStatusPageHandler;
use App\Modules\StatusPages\Application\Handlers\UpdateStatusPageHandler;
use App\Modules\StatusPages\Application\Queries\StatusPagePublicQuery;
use App\Modules\StatusPages\Infrastructure\Persistence\Models\StatusPage;
use App\Modules\StatusPages\Presentation\Http\Requests\SaveStatusPageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StatusPageController
{
    public function __construct(
        private readonly GetCurrentOrganization $getCurrentOrganization,
        private readonly MonitorTypeCatalog $monitorTypes,
        private readonly StatusPagePublicQuery $publicQuery,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $statusPages = StatusPage::query()
            ->withCount('monitors')
            ->where('organization_id', $organization->id)
            ->latest()
            ->get()
            ->map(fn (StatusPage $statusPage): array => [
                'id' => $statusPage->id,
                'name' => $statusPage->name,
                'slug' => $statusPage->slug,
                'description' => $statusPage->description,
                'is_published' => $statusPage->is_published,
                'accent_color' => $statusPage->accent_color,
                'monitors_count' => $statusPage->monitors_count,
                'updated_at' => $statusPage->updated_at?->toISOString(),
            ]);

        return Inertia::render('StatusPages/Index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'statusPages' => $statusPages,
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        return Inertia::render('StatusPages/Form', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'statusPage' => null,
            'availableMonitors' => $this->availableMonitors((int) $organization->id),
        ]);
    }

    public function store(SaveStatusPageRequest $request, CreateStatusPageHandler $handler): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $statusPage = $handler->handle(new CreateStatusPage($request->toData((int) $organization->id)));

        return to_route('status-pages.edit', $statusPage)->with('success', 'Публичная страница создана.');
    }

    public function edit(Request $request, StatusPage $statusPage): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertBelongsToOrganization($statusPage, (int) $organization->id);
        $statusPage->load('monitors');

        return Inertia::render('StatusPages/Form', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'statusPage' => [
                'id' => $statusPage->id,
                'name' => $statusPage->name,
                'slug' => $statusPage->slug,
                'description' => $statusPage->description,
                'is_published' => $statusPage->is_published,
                'show_incident_history' => $statusPage->show_incident_history,
                'accent_color' => $statusPage->accent_color,
                'monitors' => $statusPage->monitors->map(fn (Monitor $monitor): array => [
                    'monitor_id' => $monitor->id,
                    'display_name' => $monitor->pivot->display_name,
                ])->values(),
            ],
            'availableMonitors' => $this->availableMonitors((int) $organization->id),
        ]);
    }

    public function update(
        SaveStatusPageRequest $request,
        StatusPage $statusPage,
        UpdateStatusPageHandler $handler,
    ): RedirectResponse {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertBelongsToOrganization($statusPage, (int) $organization->id);
        $handler->handle(new UpdateStatusPage($statusPage, $request->toData((int) $organization->id)));

        return to_route('status-pages.edit', $statusPage)->with('success', 'Публичная страница обновлена.');
    }

    public function destroy(Request $request, StatusPage $statusPage): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertBelongsToOrganization($statusPage, (int) $organization->id);
        $statusPage->delete();

        return to_route('status-pages.index')->with('success', 'Публичная страница удалена.');
    }

    public function show(string $slug): Response
    {
        $statusPage = StatusPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('StatusPages/Public', [
            'statusPage' => $this->publicQuery->build($statusPage),
        ]);
    }

    public function preview(Request $request, StatusPage $statusPage): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $this->assertBelongsToOrganization($statusPage, (int) $organization->id);

        return Inertia::render('StatusPages/Public', [
            'statusPage' => $this->publicQuery->build($statusPage),
            'isPreview' => true,
        ]);
    }

    private function availableMonitors(int $organizationId): array
    {
        return Monitor::query()
            ->with('monitoredResource:id,name,target,host')
            ->where('organization_id', $organizationId)
            ->orderBy('project_id')
            ->orderBy('monitored_resource_id')
            ->orderBy('type')
            ->get()
            ->map(fn (Monitor $monitor): array => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'resource_name' => $monitor->monitoredResource?->name ?? $monitor->name,
                'target' => $monitor->monitoredResource?->host ?: $monitor->monitoredResource?->target,
                'type' => $monitor->type,
                'type_label' => $this->monitorTypes->label($monitor->type),
                'enabled' => $monitor->enabled,
                'status' => $monitor->status,
            ])
            ->values()
            ->all();
    }

    private function assertBelongsToOrganization(StatusPage $statusPage, int $organizationId): void
    {
        if ((int) $statusPage->organization_id !== $organizationId) {
            throw new NotFoundHttpException;
        }
    }
}
