<?php

namespace App\Modules\Incidents\Application\Listeners;

use App\Modules\Incidents\Application\Services\IncidentResolver;
use App\Modules\Monitoring\Domain\Events\CheckResultReceived;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;

final readonly class UpdateIncidentStateForCheckResult
{
    public function __construct(
        private IncidentResolver $incidentResolver,
    ) {
    }

    public function handle(CheckResultReceived $event): void
    {
        $checkResult = CheckResult::query()->find($event->checkResultId);

        if ($checkResult === null) {
            return;
        }

        $this->incidentResolver->resolve($checkResult);
    }
}
