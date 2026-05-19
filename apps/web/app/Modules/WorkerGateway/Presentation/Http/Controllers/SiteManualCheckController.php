<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\RequestManualCheckCommand;
use App\Modules\Monitoring\Application\Handlers\RequestManualCheckHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SiteManualCheckController extends Controller
{
    public function store(
        Request $request,
        MonitoredResource $site,
        RequestManualCheckHandler $requestManualCheck,
    ): RedirectResponse {
        $organizationIds = $request->user()
            ->organizations()
            ->pluck('organizations.id');

        abort_unless($organizationIds->contains($site->organization_id), 404);

        $site->load(['monitors' => fn ($query) => $query->where('enabled', true)]);

        foreach ($site->monitors as $monitor) {
            $requestManualCheck->handle(new RequestManualCheckCommand(
                monitorId: $monitor->id,
                requestedByUserId: $request->user()->id,
            ));
        }

        return back()->with('success', 'Site checks requested.');
    }
}
