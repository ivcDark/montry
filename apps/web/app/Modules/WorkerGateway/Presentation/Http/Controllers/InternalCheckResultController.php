<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Application\Commands\ReceiveCheckResultCommand;
use App\Modules\Monitoring\Application\Handlers\ReceiveCheckResultHandler;
use App\Modules\Observability\Application\Services\DeadLetterRecorder;
use App\Modules\WorkerGateway\Application\DTO\WorkerCheckResultPayload;
use App\Modules\WorkerGateway\Presentation\Http\Requests\StoreCheckResultRequest;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final class InternalCheckResultController extends Controller
{
    public function store(
        StoreCheckResultRequest $request,
        ReceiveCheckResultHandler $receiveCheckResult,
        OpenTelemetryService $tracer,
        DeadLetterRecorder $deadLetters,
    ): JsonResponse {
        $payload = WorkerCheckResultPayload::fromArray($request->validated());
        $traceparent = $payload->traceparent ?? $request->headers->get('traceparent');

        $span = $tracer->startSpan('poller.check_result.receive', [
            'check.type' => $payload->checkType,
            'check.status' => $payload->status,
        ], OpenTelemetryService::SPAN_KIND_SERVER, $traceparent);

        try {
            $checkResult = $receiveCheckResult->handle(new ReceiveCheckResultCommand(
                eventId: $payload->eventId,
                monitorId: $payload->monitorId,
                checkType: $payload->checkType,
                workerResult: $payload->toWorkerResult(),
                checkedAt: $payload->checkedAt,
            ));

            $span->end();

            return response()->json([
                'id' => $checkResult->id,
                'status' => $checkResult->status,
            ], $checkResult->wasRecentlyCreated ? 201 : 200);
        } catch (Throwable $exception) {
            $span->end('STATUS_CODE_ERROR');

            if ($exception instanceof UnprocessableEntityHttpException) {
                $deadLetters->record(
                    source: 'poller',
                    type: 'check_result_processing_failed',
                    exception: $exception,
                    recoverable: false,
                    idempotencyKey: $payload->eventId !== null ? "poller_result_processing:{$payload->eventId}" : null,
                    organizationId: null,
                    subjectType: 'monitor',
                    subjectId: (string) $payload->monitorId,
                    payload: [
                        'event_id' => $payload->eventId,
                        'monitor_id' => $payload->monitorId,
                        'check_type' => $payload->checkType,
                        'status' => $payload->status,
                    ],
                    context: [
                        'reason' => 'unprocessable_check_result',
                    ],
                );
            }

            throw $exception;
        }
    }
}
