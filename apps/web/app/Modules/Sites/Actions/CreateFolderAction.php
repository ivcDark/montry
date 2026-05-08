<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Sites\DTO\CreateFolderData;
use App\Modules\Sites\Models\Folder;

final class CreateFolderAction
{
    public function handle(CreateFolderData $data): Folder
    {
        return Folder::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'color' => $data->color,
            'sort_order' => $data->sortOrder,
        ]);
    }
}
