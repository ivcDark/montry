<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Application\Commands\RequestManualCheckCommand;
use App\Modules\Monitoring\Application\Handlers\RequestManualCheckHandler;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ManualCheckController extends Controller
{
    public function store(
        Request $request,
        Monitor $monitor,
        RequestManualCheckHandler $requestManualCheck,
    ): RedirectResponse {
        $organizationIds = $request->user()
            ->organizations()
            ->pluck('organizations.id');

        abort_unless($organizationIds->contains($monitor->organization_id), 404);

        $requestManualCheck->handle(new RequestManualCheckCommand(
            monitorId: $monitor->id,
            requestedByUserId: $request->user()->id,
        ));

        return back()->with('success', 'Check requested.');
    }
}
