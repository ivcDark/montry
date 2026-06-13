<?php

namespace App\Modules\WorkerGateway\Domain\Contracts;

use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;

interface MonitoringWorkerClientInterface
{
    public function requestManualCheck(WorkerCheckPayload $payload): void;
}
