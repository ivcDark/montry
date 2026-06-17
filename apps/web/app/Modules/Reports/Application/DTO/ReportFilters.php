<?php

namespace App\Modules\Reports\Application\DTO;

use Carbon\CarbonImmutable;

final readonly class ReportFilters
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $period,
        public string $type,
        public ?int $projectId,
        public int $retentionDays,
        public int $requestedDays,
        public bool $wasLimitedByPlan,
    ) {
    }

    public function periodDays(): int
    {
        return max(1, (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1);
    }
}
