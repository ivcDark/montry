<?php

namespace App\Modules\Monitoring\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MonitorIndexController extends Controller
{
    public function __invoke(Request $request, GetCurrentOrganization $getCurrentOrganization): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $monitors = Monitor::query()
            ->with([
                'project:id,name',
                'monitoredResource:id,name,target,host,status',
                'latestCheckResult' => fn ($query) => $query->select([
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
            ])
            ->where('organization_id', $organization->id)
            ->orderByRaw('case when status in (?, ?) then 0 when status = ? then 1 when enabled = false then 3 else 2 end', [
                'failure',
                'down',
                'degraded',
            ])
            ->orderByDesc('last_check_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Monitor $monitor) => [
                'id' => $monitor->id,
                'site_id' => $monitor->monitored_resource_id,
                'type' => $monitor->type,
                'name' => $monitor->name,
                'status' => $monitor->status,
                'is_enabled' => $monitor->is_enabled,
                'interval_seconds' => $monitor->interval_seconds,
                'timeout_ms' => $monitor->timeout_ms,
                'last_check_at' => $monitor->last_check_at?->toISOString(),
                'next_check_at' => $monitor->next_check_at?->toISOString(),
                'check_in_progress_until' => $monitor->check_in_progress_until?->toISOString(),
                'is_checking' => $monitor->check_in_progress_until?->isFuture() ?? false,
                'settings' => $monitor->settings ?? [],
                'expected' => $monitor->expected ?? [],
                'project' => $monitor->project
                    ? [
                        'id' => $monitor->project->id,
                        'name' => $monitor->project->name,
                    ]
                    : null,
                'resource' => $monitor->monitoredResource
                    ? [
                        'id' => $monitor->monitoredResource->id,
                        'name' => $monitor->monitoredResource->name,
                        'target' => $monitor->monitoredResource->target,
                        'host' => $monitor->monitoredResource->host,
                        'status' => $monitor->monitoredResource->status,
                    ]
                    : null,
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
            ]);

        return Inertia::render('Monitors/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'monitors' => $monitors,
        ]);
    }
}
