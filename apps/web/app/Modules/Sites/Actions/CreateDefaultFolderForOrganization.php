<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Folder;
use Illuminate\Database\QueryException;

final class CreateDefaultFolderForOrganization
{
    public function handle(Organization $organization): Folder
    {
        try {
            return Folder::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'is_default' => true,
                ],
                [
                    'name' => 'default',
                    'color' => '#ffffff',
                    'sort_order' => 0,
                ],
            );
        } catch (QueryException) {
            return Folder::query()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->firstOrFail();
        }
    }
}
