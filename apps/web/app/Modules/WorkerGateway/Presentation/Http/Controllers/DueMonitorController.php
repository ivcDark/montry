<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\Context\CorrelationContext;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use App\Modules\WorkerGateway\Presentation\Http\Requests\ListDueMonitorsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DueMonitorController extends Controller
{
    public function index(
        ListDueMonitorsRequest $request,
        CorrelationContext $correlationContext,
        BusinessEventRecorder $events,
        OpenTelemetryService $tracer,
    ): JsonResponse {
        $limit = (int) ($request->validated('limit') ?? 100);
        $now = Carbon::now();

        $monitors = DB::transaction(function () use ($limit, $now) {
            $query = Monitor::query()
                ->with('monitoredResource')
                ->where('enabled', true)
                ->where(function ($query) use ($now): void {
                    $query
                        ->whereNull('next_check_at')
                        ->orWhere('next_check_at', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query
                        ->whereNull('check_in_progress_until')
                        ->orWhere('check_in_progress_until', '<=', $now);
                })
                ->orderByRaw('next_check_at is null')
                ->orderBy('next_check_at')
                ->limit($limit);

            if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)) {
                $query->lock('FOR UPDATE SKIP LOCKED');
            }

            $monitors = $query->get();

            $monitors->each(function (Monitor $monitor) use ($now): void {
                $monitor->last_check_event_id = (string) Str::uuid();
                $monitor->check_in_progress_until = $now
                    ->copy()
                    ->addMilliseconds((int) $monitor->timeout_ms)
                    ->addSeconds(60);
                $monitor->save();
            });

            return $monitors;
        });

        $monitors->each(function (Monitor $monitor) use ($events): void {
            $events->record(new RecordBusinessEventData(
                eventType: 'check.scheduled',
                organizationId: $monitor->organization_id,
                subjectType: 'monitor',
                subjectId: (string) $monitor->id,
                status: 'scheduled',
                source: 'poller_due_api',
                payload: [
                    'event_id' => $monitor->last_check_event_id,
                    'check_type' => $monitor->type,
                    'monitored_resource_id' => $monitor->monitored_resource_id,
                    'timeout_ms' => $monitor->timeout_ms,
                ],
            ));
        });

        return response()->json([
            'data' => $monitors->map(fn (Monitor $monitor): array => [
                'id' => (string) Str::uuid(),
                'event_id' => $monitor->last_check_event_id,
                'event_type' => 'scheduled_check_due',
                'monitor_id' => $monitor->id,
                'check_type' => $monitor->type,
                'target' => $monitor->monitoredResource?->target
                    ?? $monitor->settings['url']
                    ?? $monitor->settings['domain']
                    ?? '',
                'settings' => $monitor->settings ?? [],
                'expected' => $monitor->expected ?? [],
                'timeout_ms' => $monitor->timeout_ms,
                'requested_at' => $now->toAtomString(),
                'correlation_id' => $correlationContext->id(),
                'traceparent' => $tracer->currentTraceparent(),
            ])->values(),
        ]);
    }
}
