<?php

namespace App\Modules\WorkerGateway\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\WorkerGateway\Presentation\Http\Requests\ListDueMonitorsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DueMonitorController extends Controller
{
    public function index(ListDueMonitorsRequest $request): JsonResponse
    {
        $limit = (int) ($request->validated('limit') ?? 100);
        $now = Carbon::now();

        $monitors = Monitor::query()
            ->with('monitoredResource')
            ->where('enabled', true)
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->orderByRaw('next_check_at is null')
            ->orderBy('next_check_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $monitors->map(fn (Monitor $monitor): array => [
                'id' => (string) Str::uuid(),
                'event_id' => (string) Str::uuid(),
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
            ])->values(),
        ]);
    }
}
