<?php

namespace App\Modules\WorkerGateway\Infrastructure\Clients;

use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;
use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class HttpMonitoringWorkerClient implements MonitoringWorkerClientInterface
{
    public function __construct(
        private string $baseUrl,
        private OpenTelemetryService $tracer,
        private ?string $token = null,
    ) {
    }

    public function requestManualCheck(WorkerCheckPayload $payload): void
    {
        $span = $this->tracer->startSpan('poller.manual_check.request', [
            'rpc.system' => 'http',
            'http.request.method' => 'POST',
            'url.path' => '/internal/manual-checks',
            'check.type' => $payload->checkType,
        ], OpenTelemetryService::SPAN_KIND_CLIENT);

        $request = Http::acceptJson()
            ->timeout(10);

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        if ($payload->correlationId !== null && $payload->correlationId !== '') {
            $request = $request->withHeader('X-Correlation-ID', $payload->correlationId);
        }

        $traceparent = $payload->traceparent ?? $span->traceparent();
        $request = $request->withHeader('traceparent', $traceparent);

        try {
            $request
                ->post(rtrim($this->baseUrl, '/') . '/internal/manual-checks', [
                    ...$payload->toArray(),
                    'traceparent' => $traceparent,
                ])
                ->throw();

            $span->end();
        } catch (Throwable $exception) {
            $span->end('STATUS_CODE_ERROR');

            throw $exception;
        }
    }
}
