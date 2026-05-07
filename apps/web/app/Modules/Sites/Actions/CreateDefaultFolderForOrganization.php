<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Folder;

final class CreateDefaultFolderForOrganization
{
    public function handle(Organization $organization): Folder
    {
        $existingDefaultFolder = Folder::query()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->first();

        if ($existingDefaultFolder) {
            return $existingDefaultFolder;
        }

        return Folder::query()->create([
            'organization_id' => $organization->id,
            'name' => 'default',
            'color' => '#ffffff',
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }
}
