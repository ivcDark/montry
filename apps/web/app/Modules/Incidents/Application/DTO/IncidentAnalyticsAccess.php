<?php

namespace App\Modules\Incidents\Application\DTO;

final readonly class IncidentAnalyticsAccess
{
    public function __construct(
        public bool $enabled,
        public string $planCode,
        public int $retentionDays,
    ) {
    }
}
