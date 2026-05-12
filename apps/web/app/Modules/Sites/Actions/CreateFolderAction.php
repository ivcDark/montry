<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\CreateFolderData;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;

final class CreateFolderAction
{
    public function handle(CreateFolderData $data): Project
    {
        return Project::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'color' => $data->color,
            'sort_order' => $data->sortOrder,
        ]);
    }
}
