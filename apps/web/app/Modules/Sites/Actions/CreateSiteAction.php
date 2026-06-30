<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Handlers\CreateMonitorHandler;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Support\Facades\DB;

final readonly class CreateSiteAction
{
    public function __construct(
        private CreateMonitorHandler $createMonitor,
        private LimitChecker $limits,
        private BusinessEventRecorder $events,
    ) {}

    public function handle(CreateSiteData $data, array $monitors = []): MonitoredResource
    {
        return DB::transaction(function () use ($data, $monitors) {
            $this->limits->assertCanCreateSite((int) $data->organizationId);

            $site = MonitoredResource::query()->create([
                'organization_id' => $data->organizationId,
                'project_id' => $data->folderId,
                'created_user_id' => $data->createdUserId,
                'type' => 'website',
                'name' => $data->name,
                'target' => $data->url,
                'scheme' => $data->scheme,
                'host' => $data->host,
                'port' => $data->port,
                'path' => $data->path,
                'status' => SiteStatus::Unknown->value,
                'notes' => $data->notes,
            ]);

            $this->events->record(new RecordBusinessEventData(
                eventType: 'site.created',
                organizationId: (int) $data->organizationId,
                userId: (int) $data->createdUserId,
                subjectType: 'monitored_resource',
                subjectId: (string) $site->id,
                status: $site->status,
                source: 'web',
                payload: [
                    'project_id' => $site->project_id,
                    'type' => $site->type,
                    'host' => $site->host,
                    'scheme' => $site->scheme,
                    'port' => $site->port,
                    'initial_monitors_count' => count($monitors),
                ],
            ));

            foreach ($monitors as $monitor) {
                $this->createMonitor->handle(new CreateMonitorCommand(
                    organizationId: (int) $data->organizationId,
                    projectId: (int) $data->folderId,
                    monitoredResourceId: (int) $site->id,
                    type: $monitor['type'],
                    name: $monitor['name'],
                    enabled: (bool) $monitor['is_enabled'],
                    intervalSeconds: (int) $monitor['interval_seconds'],
                    timeoutMs: (int) $monitor['timeout_ms'],
                    failureThreshold: isset($monitor['failure_threshold']) ? (int) $monitor['failure_threshold'] : null,
                    settings: $monitor['settings'],
                    expected: $monitor['expected'] ?? [],
                ));
            }

            return $site;
        });
    }
}
