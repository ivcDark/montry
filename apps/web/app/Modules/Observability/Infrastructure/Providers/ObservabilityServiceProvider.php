<?php

namespace App\Modules\Observability\Infrastructure\Providers;

use App\Modules\Observability\Application\Context\CorrelationContext;
use App\Modules\Observability\Application\Context\TraceContext;
use App\Modules\Admin\Infrastructure\Http\Middleware\EnsureAdmin;
use App\Modules\Observability\Application\Services\MetricsRecorder;
use App\Modules\Observability\Domain\Contracts\BusinessEventRepositoryInterface;
use App\Modules\Observability\Infrastructure\ClickHouse\ClickHouseClient;
use App\Modules\Observability\Infrastructure\ClickHouse\ClickHouseBusinessEventExporter;
use App\Modules\Observability\Infrastructure\Persistence\EloquentBusinessEventRepository;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetrySpan;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CorrelationContext::class);
        $this->app->singleton(TraceContext::class);
        $this->app->singleton(OpenTelemetryService::class);
        $this->app->singleton(ClickHouseClient::class, fn (): ClickHouseClient => new ClickHouseClient(
            baseUrl: (string) config('observability.clickhouse.url'),
            database: (string) config('observability.clickhouse.database'),
            username: (string) config('observability.clickhouse.username'),
            password: (string) config('observability.clickhouse.password'),
            timeoutSeconds: (float) config('observability.clickhouse.timeout_seconds', 5),
        ));
        $this->app->singleton(ClickHouseBusinessEventExporter::class);
        $this->app->bind(BusinessEventRepositoryInterface::class, EloquentBusinessEventRepository::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('internal')
            ->group(__DIR__ . '/../../Presentation/Routes/internal.php');

        Route::middleware(['web', 'auth', EnsureAdmin::class])
            ->prefix('admin')
            ->name('admin.')
            ->group(__DIR__ . '/../../Presentation/Routes/admin.php');

        $startedAt = [];
        $queueSpans = [];

        Queue::before(function (JobProcessing $event) use (&$startedAt, &$queueSpans): void {
            $key = spl_object_id($event->job);
            $startedAt[$key] = hrtime(true);
            $queueSpans[$key] = app(OpenTelemetryService::class)->startSpan('queue.job', [
                'messaging.system' => 'laravel_queue',
                'messaging.destination.name' => method_exists($event->job, 'getQueue') ? (string) $event->job->getQueue() : 'default',
                'code.function' => $this->jobName($event->job),
            ], OpenTelemetryService::SPAN_KIND_CONSUMER);
        });

        Queue::after(function (JobProcessed $event) use (&$startedAt, &$queueSpans): void {
            $this->recordQueueDuration($event->job, $startedAt, 'processed');
            $this->endQueueSpan($event->job, $queueSpans, 'STATUS_CODE_OK');
        });

        Queue::failing(function (JobFailed $event) use (&$startedAt, &$queueSpans): void {
            $this->recordQueueDuration($event->job, $startedAt, 'failed');
            $this->endQueueSpan($event->job, $queueSpans, 'STATUS_CODE_ERROR');
        });
    }

    /**
     * @param array<int, int> $startedAt
     */
    private function recordQueueDuration(object $job, array &$startedAt, string $status): void
    {
        $key = spl_object_id($job);
        $started = $startedAt[$key] ?? hrtime(true);
        unset($startedAt[$key]);

        app(MetricsRecorder::class)->observeQueueJob([
            'queue' => method_exists($job, 'getQueue') ? (string) $job->getQueue() : 'default',
            'job' => $this->jobName($job),
            'status' => $status,
        ], (hrtime(true) - $started) / 1_000_000_000);
    }

    private function jobName(object $job): string
    {
        if (method_exists($job, 'resolveName')) {
            return class_basename((string) $job->resolveName());
        }

        if (method_exists($job, 'getName')) {
            return class_basename((string) $job->getName());
        }

        return class_basename($job::class);
    }

    /**
     * @param array<int, OpenTelemetrySpan> $queueSpans
     */
    private function endQueueSpan(object $job, array &$queueSpans, string $statusCode): void
    {
        $key = spl_object_id($job);

        if (! isset($queueSpans[$key])) {
            return;
        }

        $queueSpans[$key]->end($statusCode);
        unset($queueSpans[$key]);
    }
}
