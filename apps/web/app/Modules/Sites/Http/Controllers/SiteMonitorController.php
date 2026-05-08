<?php

namespace App\Modules\Sites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Actions\CreateMonitorAction;
use App\Modules\Sites\Actions\DeleteMonitorAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\Actions\ToggleMonitorAction;
use App\Modules\Sites\Actions\UpdateMonitorAction;
use App\Modules\Sites\DTO\CreateMonitorData;
use App\Modules\Sites\DTO\UpdateMonitorData;
use App\Modules\Sites\Enums\MonitorType;
use App\Modules\Sites\Http\Requests\SaveMonitorRequest;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Models\SiteMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SiteMonitorController extends Controller
{
    public function __construct(
        private readonly CreateMonitorAction $createMonitorAction,
        private readonly UpdateMonitorAction $updateMonitorAction,
        private readonly ToggleMonitorAction $toggleMonitorAction,
        private readonly DeleteMonitorAction $deleteMonitorAction,
        private readonly GetCurrentOrganization $getCurrentOrganization,
    ) {
    }

    public function create(
        Request $request,
        Site $site
    ): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless(
            $site->organization_id === $organization->id,
            404,
        );

        return Inertia::render('Sites/Monitors/Create', [
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
            ],
            'monitorTypes' => collect(MonitorType::cases())
                ->map(fn (MonitorType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values(),
        ]);
    }

    public function store(
        SaveMonitorRequest     $request,
        Site                   $site
    ): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless($site->organization_id === $organization->id, 404);

        $validated = $request->validated();

        $this->createMonitorAction->handle(
            site: $site,
            data: new CreateMonitorData(
                name: $validated['name'],
                type: MonitorType::from($validated['type']),
                isEnabled: $validated['is_enabled'],
                intervalSeconds: $validated['interval_seconds'],
                timeoutMs: $validated['timeout_ms'],
                settings: $validated['settings'],
            ),
        );

        return redirect()->route('sites.show', $site);
    }

    public function edit(
        Request $request,
        Site $site,
        SiteMonitor $siteMonitor
    ): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->site_id === $site->id, 404);

        return Inertia::render('Sites/Monitors/Edit', [
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
            ],
            'monitor' => [
                'id' => $siteMonitor->id,
                'type' => $siteMonitor->type instanceof MonitorType
                    ? $siteMonitor->type->value
                    : $siteMonitor->type,
                'name' => $siteMonitor->name,
                'is_enabled' => $siteMonitor->is_enabled,
                'interval_seconds' => $siteMonitor->interval_seconds,
                'timeout_ms' => $siteMonitor->timeout_ms,
                'settings' => $siteMonitor->settings ?? [],
            ],
            'monitorTypes' => $this->monitorTypes(),
        ]);
    }

    public function update(
        SaveMonitorRequest $request,
        Site $site,
        SiteMonitor $siteMonitor
    ): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->site_id === $site->id, 404);

        $validated = $request->validated();

        $currentType = $siteMonitor->type instanceof MonitorType
            ? $siteMonitor->type->value
            : $siteMonitor->type;

        /**
         * MVP decision:
         * Monitor type cannot be changed after creation.
         */
        abort_unless($validated['type'] === $currentType, 422);

        $this->updateMonitorAction->handle(
            monitor: $siteMonitor,
            data: new UpdateMonitorData(
                name: $validated['name'],
                isEnabled: $validated['is_enabled'],
                intervalSeconds: $validated['interval_seconds'],
                timeoutMs: $validated['timeout_ms'],
                settings: $validated['settings'],
            ),
        );

        return to_route('sites.show', $site);
    }

    public function toggle(Request $request, Site $site, SiteMonitor $siteMonitor): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->site_id === $site->id, 404);

        $this->toggleMonitorAction->handle($siteMonitor);

        return to_route('sites.show', $site);
    }

    public function destroy(Request $request, Site $site, SiteMonitor $siteMonitor): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->site_id === $site->id, 404);

        $this->deleteMonitorAction->handle($siteMonitor);

        return to_route('sites.show', $site);
    }

    private function monitorTypes(): array
    {
        return collect(MonitorType::cases())
            ->map(fn (MonitorType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->all();
    }
}
