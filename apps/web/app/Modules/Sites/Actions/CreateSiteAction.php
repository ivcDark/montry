<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Handlers\CreateMonitorHandler;
use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Support\Facades\DB;

final readonly class CreateSiteAction
{
    public function __construct(
        private CreateMonitorHandler $createMonitor,
    ) {
    }

    public function handle(CreateSiteData $data, array $monitors = []): MonitoredResource
    {
        return DB::transaction(function () use ($data, $monitors) {
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
                    settings: $monitor['settings'],
                    expected: $monitor['expected'] ?? [],
                ));
            }

            return $site;
        });
    }
}
