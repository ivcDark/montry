<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\CreateSiteData;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\DB;

final readonly class CreateSiteAction
{
    public function __construct(
        private CreateDefaultHttpMonitorAction $createDefaultHttpMonitor,
    ) {
    }

    public function handle(CreateSiteData $data): Site
    {
        return DB::transaction(function () use ($data) {
            $site = Site::query()->create([
                'organization_id' => $data->organizationId,
                'folder_id' => $data->folderId,
                'created_user_id' => $data->createdUserId,
                'name' => $data->name,
                'url' => $data->url,
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
