<?php

namespace App\Modules\Incidents\Application\Listeners;

use App\Modules\Incidents\Application\Services\IncidentAnalyticsCache;
use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;

final readonly class InvalidateIncidentAnalyticsCache
{
    public function __construct(
        private IncidentAnalyticsCache $cache,
    ) {
    }

    public function handle(IncidentOpened|IncidentResolved $event): void
    {
        $incident = Incident::query()->find($event->incidentId);

        if ($incident !== null) {
            $this->cache->incrementVersion($incident->organization_id);
        }
    }
}
