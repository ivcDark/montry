<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Application\Commands\ReceiveCheckResultCommand;
use App\Modules\Monitoring\Application\Handlers\ReceiveCheckResultHandler;
use App\Modules\WorkerGateway\Application\DTO\WorkerCheckResultPayload;
use App\Modules\WorkerGateway\Presentation\Http\Requests\StoreCheckResultRequest;
use Illuminate\Http\JsonResponse;

final class InternalCheckResultController extends Controller
{
    public function store(
        StoreCheckResultRequest $request,
        ReceiveCheckResultHandler $receiveCheckResult,
    ): JsonResponse {
        $payload = WorkerCheckResultPayload::fromArray($request->validated());

        $checkResult = $receiveCheckResult->handle(new ReceiveCheckResultCommand(
            monitorId: $payload->monitorId,
            workerResult: $payload->toWorkerResult(),
            checkedAt: $payload->checkedAt,
        ));

        return response()->json([
            'id' => $checkResult->id,
            'status' => $checkResult->status,
        ], 201);
    }
}
