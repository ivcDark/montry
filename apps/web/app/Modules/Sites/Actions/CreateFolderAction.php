<?php

namespace App\Modules\Sites\Actions;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\Sites\DTO\CreateFolderData;

final readonly class CreateFolderAction
{
    public function __construct(
        private LimitChecker $limits,
    ) {}

    public function handle(CreateFolderData $data): Project
    {
        $this->limits->assertCanCreateProject((int) $data->organizationId);

        return Project::query()->create([
            'organization_id' => $data->organizationId,
            'name' => $data->name,
            'comment' => $data->comment,
            'color' => $data->color,
            'sort_order' => $data->sortOrder,
        ]);
    }
}
