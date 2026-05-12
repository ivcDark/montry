<?php

namespace App\Modules\MonitoredResources\Application\Handlers;

use App\Modules\MonitoredResources\Application\Queries\ListMonitoredResourcesQuery;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use Illuminate\Support\Collection;

final class ListMonitoredResourcesHandler
{
    public function handle(ListMonitoredResourcesQuery $query): Collection
    {
        return MonitoredResource::query()
            ->where('organization_id', $query->organizationId)
            ->latest()
            ->get()
            ->map(fn (MonitoredResource $resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'url' => $resource->url,
                'host' => $resource->host,
                'status' => $resource->status,
            ]);
    }
}
