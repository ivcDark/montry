<?php

namespace App\Modules\Incidents\Application\DTO;

use Carbon\CarbonImmutable;

final readonly class IncidentAnalyticsFilters
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $type,
        public ?int $projectId,
        public string $search,
    ) {
    }

    public function previousStart(): CarbonImmutable
    {
        return $this->start->subSeconds($this->periodSeconds());
    }

    public function previousEnd(): CarbonImmutable
    {
        return $this->start->subSecond();
    }

    public function periodSeconds(): int
    {
        return max(1, (int) $this->start->diffInSeconds($this->end) + 1);
    }
}
