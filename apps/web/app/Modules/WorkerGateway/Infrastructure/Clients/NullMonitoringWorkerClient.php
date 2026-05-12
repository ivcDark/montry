<?php

namespace App\Modules\WorkerGateway\Infrastructure\Clients;

use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;
use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;

final class NullMonitoringWorkerClient implements MonitoringWorkerClientInterface
{
    public function requestManualCheck(WorkerCheckPayload $payload): void
    {
    }
}
