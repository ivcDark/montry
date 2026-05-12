<?php

namespace App\Modules\Incidents\Domain\Events;

final readonly class IncidentResolved
{
    public function __construct(
        public int $incidentId,
    ) {
    }
}
