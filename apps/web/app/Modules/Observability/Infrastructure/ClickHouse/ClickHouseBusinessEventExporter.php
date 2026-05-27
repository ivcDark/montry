<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\ClickHouse;

use App\Modules\Observability\Infrastructure\Persistence\Models\AnalyticsEventExport;
use App\Modules\Observability\Infrastructure\Persistence\Models\BusinessEvent;
use App\Modules\Observability\Application\Services\DeadLetterRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ClickHouseBusinessEventExporter
{
    public function __construct(
        private ClickHouseClient $clickHouse,
        private DeadLetterRecorder $deadLetters,
    ) {
    }

    /**
     * @return array{selected: int, exported: int, failed: int}
     */
    public function export(?int $batchSize = null): array
    {
        if (! config('observability.clickhouse.enabled', true)) {
            return ['selected' => 0, 'exported' => 0, 'failed' => 0];
        }

        $batchSize ??= (int) config('observability.clickhouse.export_batch_size', 500);
        $maxAttempts = (int) config('observability.clickhouse.max_attempts', 5);
        $events = $this->selectEvents($batchSize, $maxAttempts);

        if ($events->isEmpty()) {
            return ['selected' => 0, 'exported' => 0, 'failed' => 0];
        }

        $exports = $this->ensureExportRows($events);
        $rows = $events
            ->filter(fn (BusinessEvent $event): bool => isset($exports[$event->id]))
            ->map(fn (BusinessEvent $event): array => $this->toClickHouseRow($event))
            ->values()
            ->all();

        try {
            $this->clickHouse->insertJsonEachRow('analytics_events', $rows);
            $this->markExported($exports);

            return ['selected' => $events->count(), 'exported' => count($exports), 'failed' => 0];
        } catch (Throwable $exception) {
            $failed = $this->markFailed($exports, $exception, $maxAttempts);

            return ['selected' => $events->count(), 'exported' => 0, 'failed' => $failed];
        }
    }

    private function selectEvents(int $batchSize, int $maxAttempts)
    {
        return BusinessEvent::query()
            ->leftJoin('analytics_event_exports', 'business_events.id', '=', 'analytics_event_exports.business_event_id')
            ->where(function ($query) use ($maxAttempts): void {
                $query
                    ->whereNull('analytics_event_exports.id')
                    ->orWhere(function ($query) use ($maxAttempts): void {
                        $query
                            ->where('analytics_event_exports.status', AnalyticsEventExport::STATUS_PENDING)
                            ->where('analytics_event_exports.attempts', '<', $maxAttempts);
                    });
            })
            ->orderBy('business_events.id')
            ->limit($batchSize)
            ->select('business_events.*')
            ->get();
    }

    /**
     * @return array<int, AnalyticsEventExport>
     */
    private function ensureExportRows($events): array
    {
        return DB::transaction(function () use ($events): array {
            $exports = [];

            foreach ($events as $event) {
                $export = AnalyticsEventExport::query()->firstOrCreate(
                    ['business_event_id' => $event->id],
                    [
                        'event_id' => $event->event_id,
                        'status' => AnalyticsEventExport::STATUS_PENDING,
                    ],
                );

                if ($export->status === AnalyticsEventExport::STATUS_EXPORTED) {
                    continue;
                }

                $exports[$event->id] = $export;
            }

            return $exports;
        });
    }

    /**
     * @param array<int, AnalyticsEventExport> $exports
     */
    private function markExported(array $exports): void
    {
        $now = Carbon::now();

        AnalyticsEventExport::query()
            ->whereIn('id', array_map(static fn (AnalyticsEventExport $export): int => $export->id, $exports))
            ->update([
                'status' => AnalyticsEventExport::STATUS_EXPORTED,
                'exported_at' => $now,
                'last_attempted_at' => $now,
                'last_error' => null,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param array<int, AnalyticsEventExport> $exports
     */
    private function markFailed(array $exports, Throwable $exception, int $maxAttempts): int
    {
        $now = Carbon::now();
        $failed = 0;

        foreach ($exports as $export) {
            $attempts = $export->attempts + 1;

            $export->forceFill([
                'status' => $attempts >= $maxAttempts ? AnalyticsEventExport::STATUS_FAILED : AnalyticsEventExport::STATUS_PENDING,
                'attempts' => $attempts,
                'last_attempted_at' => $now,
                'last_error' => mb_strimwidth($exception->getMessage(), 0, 4000),
            ])->save();

            $failed++;

            if ($attempts >= $maxAttempts) {
                $this->deadLetters->record(
                    source: 'clickhouse',
                    type: 'business_event_export',
                    exception: $exception,
                    recoverable: true,
                    idempotencyKey: "analytics_event_export:{$export->id}",
                    subjectType: 'analytics_event_export',
                    subjectId: (string) $export->id,
                    payload: [
                        'analytics_event_export_id' => $export->id,
                        'business_event_id' => $export->business_event_id,
                        'event_id' => $export->event_id,
                    ],
                    context: [
                        'table' => 'analytics_events',
                    ],
                    attempts: $attempts,
                    maxAttempts: $maxAttempts,
                );
            }
        }

        return $failed;
    }

    /**
     * @return array<string, mixed>
     */
    private function toClickHouseRow(BusinessEvent $event): array
    {
        return [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'occurred_at' => $event->occurred_at?->utc()->format('Y-m-d H:i:s.v') ?? Carbon::now('UTC')->format('Y-m-d H:i:s.v'),
            'organization_id' => $event->organization_id,
            'user_id' => $event->user_id,
            'plan_code' => $event->plan_code,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'status' => $event->status,
            'source' => $event->source,
            'correlation_id' => $event->correlation_id,
            'payload' => json_encode($event->payload ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
