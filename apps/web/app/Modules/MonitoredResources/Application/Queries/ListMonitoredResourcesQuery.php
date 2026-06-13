<?php

namespace App\Modules\MonitoredResources\Application\Queries;

final readonly class ListMonitoredResourcesQuery
{
    public function __construct(
        public int $organizationId,
    ) {
    }
}
