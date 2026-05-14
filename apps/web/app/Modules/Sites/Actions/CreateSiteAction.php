<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use Illuminate\Support\Facades\DB;

final readonly class CreateSiteAction
{
    public function __construct(
        private CreateDefaultHttpMonitorAction $createDefaultHttpMonitor,
    ) {
    }

    public function handle(CreateSiteData $data): MonitoredResource
    {
        return DB::transaction(function () use ($data) {
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

            $this->createDefaultHttpMonitor->execute($site);

            return $site;
        });
    }
}
