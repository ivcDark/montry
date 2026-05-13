<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Monitoring\Application\Commands\ReceiveCheckResultCommand;
use App\Modules\Monitoring\Application\Services\CheckResultProcessor;
use App\Modules\Monitoring\Domain\Contracts\CheckResultRepositoryInterface;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Domain\Events\CheckResultReceived;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ReceiveCheckResultHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
        private CheckResultRepositoryInterface $checkResults,
        private CheckResultProcessor $checkResultProcessor,
    ) {}

    public function handle(ReceiveCheckResultCommand $command): CheckResult
    {
        if ($command->eventId !== null && $command->eventId !== '') {
            $existingResult = $this->checkResults->findByEventId($command->eventId);

            if ($existingResult !== null) {
                return $existingResult;
            }
        }

        $monitor = $this->monitors->getById($command->monitorId);

        if ($monitor->type !== $command->checkType) {
            throw new UnprocessableEntityHttpException('Check result type does not match monitor type.');
        }

        $checkResult = $this->checkResultProcessor->process(
            monitor: $monitor,
            workerResult: $command->workerResult,
            checkedAt: $command->checkedAt,
            eventId: $command->eventId,
        );

        event(new CheckResultReceived($checkResult->id));

        return $checkResult;
    }
}
