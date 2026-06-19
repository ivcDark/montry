<?php

namespace App\Modules\Projects\Application\Actions;

use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Support\Facades\DB;

final class UpdateProjectAction
{
    /** @param array<int, int> $resourceIds */
    public function handle(Project $project, string $name, ?string $comment, array $resourceIds): Project
    {
        return DB::transaction(function () use ($project, $name, $comment, $resourceIds): Project {
            $project->update(['name' => $name, 'comment' => $comment]);
            $resourceIds = array_values(array_unique(array_map('intval', $resourceIds)));
            $currentResourceIds = $project->monitoredResources()->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $this->moveResources($project->organization_id, $resourceIds, $project->id);

            if (! $project->is_default) {
                $removedIds = array_values(array_diff($currentResourceIds, $resourceIds));
                $defaultProjectId = Project::query()->where('organization_id', $project->organization_id)->where('is_default', true)->value('id');

                if ($defaultProjectId !== null) {
                    $this->moveResources($project->organization_id, $removedIds, (int) $defaultProjectId);
                }
            }

            return $project->refresh();
        });
    }

    /** @param array<int, int> $resourceIds */
    public function moveResources(int $organizationId, array $resourceIds, int $projectId): void
    {
        if ($resourceIds === []) return;

        $ids = MonitoredResource::query()->where('organization_id', $organizationId)->whereIn('id', $resourceIds)->pluck('id');
        MonitoredResource::query()->whereIn('id', $ids)->update(['project_id' => $projectId]);
        Monitor::query()->whereIn('monitored_resource_id', $ids)->update(['project_id' => $projectId]);
        Incident::query()->whereIn('monitored_resource_id', $ids)->update(['project_id' => $projectId]);
    }
}