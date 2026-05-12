<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Database\QueryException;

final class CreateDefaultFolderForOrganization
{
    public function handle(Organization $organization): Project
    {
        try {
            return Project::query()->firstOrCreate(
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
            return Project::query()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->firstOrFail();
        }
    }
}
