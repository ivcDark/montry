<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Monitoring\Application\Commands\ReceiveCheckResultCommand;
use App\Modules\Monitoring\Application\Services\CheckResultProcessor;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Domain\Events\CheckResultReceived;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;

final readonly class ReceiveCheckResultHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
        private CheckResultProcessor $checkResultProcessor,
    ) {
    }

    public function handle(ReceiveCheckResultCommand $command): CheckResult
    {
        $monitor = $this->monitors->getById($command->monitorId);

        $checkResult = $this->checkResultProcessor->process(
            monitor: $monitor,
            workerResult: $command->workerResult,
            checkedAt: $command->checkedAt,
        );

        event(new CheckResultReceived($checkResult->id));

        return $checkResult;
    }
}
