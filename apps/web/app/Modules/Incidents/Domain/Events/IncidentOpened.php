<?php

namespace App\Modules\Incidents\Domain\Events;

final readonly class IncidentOpened
{
    public function __construct(
        public int $incidentId,
    ) {
    }
}
