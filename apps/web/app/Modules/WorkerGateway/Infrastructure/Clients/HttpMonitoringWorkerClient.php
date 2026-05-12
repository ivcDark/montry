<?php

namespace App\Modules\WorkerGateway\Infrastructure\Clients;

use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;
use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;
use Illuminate\Support\Facades\Http;

final readonly class HttpMonitoringWorkerClient implements MonitoringWorkerClientInterface
{
    public function __construct(
        private string $baseUrl,
        private ?string $token = null,
    ) {
    }

    public function requestManualCheck(WorkerCheckPayload $payload): void
    {
        $request = Http::acceptJson()
            ->timeout(10);

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        $request
            ->post(rtrim($this->baseUrl, '/') . '/internal/manual-checks', $payload->toArray())
            ->throw();
    }
}
