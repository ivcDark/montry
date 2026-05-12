<?php

namespace App\Modules\MonitoredResources\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MonitoredResources\Application\Handlers\ListMonitoredResourcesHandler;
use App\Modules\MonitoredResources\Application\Queries\ListMonitoredResourcesQuery;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\MonitoredResources\Presentation\Http\Requests\StoreMonitoredResourceRequest;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): Response
    {
        return Inertia::render('Sites/Create');
    }

    public function store(
        StoreMonitoredResourceRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CreateDefaultFolderForOrganization $createDefaultProject,
        CreateSiteAction $createMonitoredResource,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());
        $project = $createDefaultProject->handle($organization);

        $createMonitoredResource->handle(
            $request->toData(
                organizationId: $organization->id,
                project: $project,
            ),
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
        $site->load('monitors');

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
                'status' => $site->status,
                'created_at' => $site->created_at?->toISOString(),
                'monitors' => $site->monitors->map(fn ($monitor) => [
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
                ]),
            ],
        ]);
    }
}
