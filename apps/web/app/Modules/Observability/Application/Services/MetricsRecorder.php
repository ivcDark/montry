<?php

namespace App\Modules\Observability\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MetricsRecorder
{
    private const SERIES_INDEX_KEY = 'observability:metrics:histogram_series';

    /**
     * @param array<string, string|int|bool|null> $labels
     */
    public function observeHttpRequest(array $labels, float $durationSeconds): void
    {
        $this->observeHistogram('http_request_duration_seconds', $labels, $durationSeconds);
    }

    /**
     * @param array<string, string|int|bool|null> $labels
     */
    public function observeInternalApiRequest(array $labels, float $durationSeconds): void
    {
        $this->observeHistogram('internal_api_duration_seconds', $labels, $durationSeconds);
    }

    /**
     * @param array<string, string|int|bool|null> $labels
     */
    public function observeQueueJob(array $labels, float $durationSeconds): void
    {
        $this->observeHistogram('queue_job_duration_seconds', $labels, $durationSeconds);
    }

    public function render(): string
    {
        $lines = [
            '# HELP montry_build_info Static application build information.',
            '# TYPE montry_build_info gauge',
            $this->sample('montry_build_info', [
                'service' => 'laravel',
                'app' => (string) config('app.name', 'Montry'),
                'environment' => (string) config('app.env', 'local'),
            ], 1),
            '',
        ];

        $lines = array_merge(
            $lines,
            $this->businessEventCounters(),
            $this->auditLogCounters(),
            $this->deadLetterGauges(),
            $this->monitoredResourceGauges(),
            $this->monitorGauges(),
            $this->incidentGauges(),
            $this->subscriptionGauges(),
            $this->paymentGauges(),
            $this->queueGauges(),
            $this->storedHistograms(),
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, string|int|bool|null> $labels
     */
    private function observeHistogram(string $metric, array $labels, float $durationSeconds): void
    {
        $durationSeconds = max(0.0, $durationSeconds);
        $labels = $this->normalizeLabels($labels);
        $hash = sha1($metric . '|' . json_encode($labels, JSON_THROW_ON_ERROR));

        $this->rememberSeries($hash, $metric, $labels);

        foreach ($this->buckets($metric) as $bucketIndex => $bucket) {
            if ($durationSeconds <= $bucket) {
                Cache::increment($this->histogramKey($hash, 'bucket_' . $bucketIndex));
            }
        }

        Cache::increment($this->histogramKey($hash, 'count'));
        Cache::increment($this->histogramKey($hash, 'sum_ms'), (int) round($durationSeconds * 1000));
    }

    /**
     * @param array<string, string> $labels
     */
    private function rememberSeries(string $hash, string $metric, array $labels): void
    {
        $series = Cache::get(self::SERIES_INDEX_KEY, []);

        if (! is_array($series)) {
            $series = [];
        }

        if (! isset($series[$hash])) {
            $series[$hash] = [
                'metric' => $metric,
                'labels' => $labels,
            ];

            Cache::forever(self::SERIES_INDEX_KEY, $series);
        }
    }

    /**
     * @return array<int, string>
     */
    private function deadLetterGauges(): array
    {
        $lines = [
            '# HELP montry_dead_letters_total Current dead-letter records by source, type, status and recoverable flag.',
            '# TYPE montry_dead_letters_total gauge',
        ];

        $rows = DB::table('dead_letters')
            ->select([
                'source',
                'type',
                'status',
                'recoverable',
                DB::raw('COUNT(*) as value'),
            ])
            ->groupBy('source', 'type', 'status', 'recoverable')
            ->orderBy('source')
            ->orderBy('type')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_dead_letters_total', [
                'source' => $row->source,
                'type' => $row->type,
                'status' => $row->status,
                'recoverable' => $row->recoverable ? 'true' : 'false',
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function auditLogCounters(): array
    {
        $lines = [
            '# HELP montry_audit_logs_total Security and admin audit events recorded by Laravel.',
            '# TYPE montry_audit_logs_total counter',
        ];

        $rows = DB::table('audit_logs')
            ->select([
                'category',
                'action',
                'outcome',
                'source',
                DB::raw('COUNT(*) as value'),
            ])
            ->groupBy('category', 'action', 'outcome', 'source')
            ->orderBy('action')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_audit_logs_total', [
                'category' => $row->category,
                'action' => $row->action,
                'outcome' => $row->outcome,
                'source' => $row->source,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function businessEventCounters(): array
    {
        $lines = [
            '# HELP montry_business_events_total Business events recorded by Laravel.',
            '# TYPE montry_business_events_total counter',
        ];

        $rows = DB::table('business_events')
            ->select([
                'event_type',
                DB::raw("COALESCE(status, 'none') as status"),
                DB::raw("COALESCE(source, 'app') as source"),
                DB::raw("COALESCE(plan_code, 'none') as plan_code"),
                DB::raw('COUNT(*) as value'),
            ])
            ->groupBy('event_type', 'status', 'source', 'plan_code')
            ->orderBy('event_type')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_business_events_total', [
                'event_type' => $row->event_type,
                'status' => $row->status,
                'source' => $row->source,
                'plan_code' => $row->plan_code,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function monitoredResourceGauges(): array
    {
        $lines = [
            '# HELP montry_monitored_resources_total Current monitored resources by type and status.',
            '# TYPE montry_monitored_resources_total gauge',
        ];

        $rows = DB::table('monitored_resources')
            ->whereNull('deleted_at')
            ->select(['type', 'status', DB::raw('COUNT(*) as value')])
            ->groupBy('type', 'status')
            ->orderBy('type')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_monitored_resources_total', [
                'type' => $row->type,
                'status' => $row->status,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function monitorGauges(): array
    {
        $lines = [
            '# HELP montry_monitors_total Current monitors by type, status and enabled state.',
            '# TYPE montry_monitors_total gauge',
        ];

        $rows = DB::table('monitors')
            ->whereNull('deleted_at')
            ->select(['type', 'status', 'enabled', DB::raw('COUNT(*) as value')])
            ->groupBy('type', 'status', 'enabled')
            ->orderBy('type')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_monitors_total', [
                'type' => $row->type,
                'status' => $row->status,
                'enabled' => $row->enabled ? 'true' : 'false',
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function incidentGauges(): array
    {
        $lines = [
            '# HELP montry_open_incidents_total Current open incidents by severity.',
            '# TYPE montry_open_incidents_total gauge',
        ];

        $rows = DB::table('incidents')
            ->where('status', 'open')
            ->select(['severity', DB::raw('COUNT(*) as value')])
            ->groupBy('severity')
            ->orderBy('severity')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_open_incidents_total', [
                'severity' => $row->severity,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function subscriptionGauges(): array
    {
        $lines = [
            '# HELP montry_subscriptions_total Current subscriptions by plan and status.',
            '# TYPE montry_subscriptions_total gauge',
        ];

        $rows = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select(['plans.code as plan_code', 'subscriptions.status', DB::raw('COUNT(*) as value')])
            ->groupBy('plans.code', 'subscriptions.status')
            ->orderBy('plans.code')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_subscriptions_total', [
                'plan_code' => $row->plan_code,
                'status' => $row->status,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function paymentGauges(): array
    {
        $lines = [
            '# HELP montry_payments_total Current payments by provider, status and currency.',
            '# TYPE montry_payments_total gauge',
        ];

        $rows = DB::table('payments')
            ->select([
                DB::raw("COALESCE(provider, 'unknown') as provider"),
                'status',
                'currency',
                DB::raw('COUNT(*) as value'),
            ])
            ->groupBy('provider', 'status', 'currency')
            ->orderBy('status')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_payments_total', [
                'provider' => $row->provider,
                'status' => $row->status,
                'currency' => $row->currency,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function queueGauges(): array
    {
        $lines = [
            '# HELP montry_queue_jobs_total Current queued jobs by queue name.',
            '# TYPE montry_queue_jobs_total gauge',
        ];

        $queues = config('observability.metrics.default_queues', ['default']);

        foreach ($queues as $queue) {
            $lines[] = $this->sample('montry_queue_jobs_total', [
                'queue' => (string) $queue,
            ], $this->queueSize((string) $queue));
        }

        $lines[] = '';
        $lines[] = '# HELP montry_failed_jobs_total Stored failed jobs by queue name.';
        $lines[] = '# TYPE montry_failed_jobs_total gauge';

        $rows = DB::table('failed_jobs')
            ->select(['queue', DB::raw('COUNT(*) as value')])
            ->groupBy('queue')
            ->orderBy('queue')
            ->get();

        foreach ($rows as $row) {
            $lines[] = $this->sample('montry_failed_jobs_total', [
                'queue' => $row->queue,
            ], (int) $row->value);
        }

        $lines[] = '';

        return $lines;
    }

    private function queueSize(string $queue): int
    {
        try {
            return app('queue')->size($queue);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    private function storedHistograms(): array
    {
        $series = Cache::get(self::SERIES_INDEX_KEY, []);

        if (! is_array($series) || $series === []) {
            return [];
        }

        $lines = [];
        $seen = [];

        foreach ($series as $hash => $definition) {
            if (! is_array($definition) || ! isset($definition['metric'], $definition['labels'])) {
                continue;
            }

            $metric = (string) $definition['metric'];
            $labels = is_array($definition['labels']) ? $definition['labels'] : [];

            if (! isset($seen[$metric])) {
                $seen[$metric] = true;
                $lines[] = '# HELP montry_' . $metric . ' Observed Laravel runtime duration.';
                $lines[] = '# TYPE montry_' . $metric . ' histogram';
            }

            foreach ($this->buckets($metric) as $bucketIndex => $bucket) {
                $lines[] = $this->sample('montry_' . $metric . '_bucket', $labels + [
                    'le' => $this->formatBucket($bucket),
                ], (int) Cache::get($this->histogramKey((string) $hash, 'bucket_' . $bucketIndex), 0));
            }

            $count = (int) Cache::get($this->histogramKey((string) $hash, 'count'), 0);
            $sumMs = (int) Cache::get($this->histogramKey((string) $hash, 'sum_ms'), 0);

            $lines[] = $this->sample('montry_' . $metric . '_bucket', $labels + [
                'le' => '+Inf',
            ], $count);
            $lines[] = $this->sample('montry_' . $metric . '_sum', $labels, $sumMs / 1000);
            $lines[] = $this->sample('montry_' . $metric . '_count', $labels, $count);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param array<string, string|int|bool|null> $labels
     * @return array<string, string>
     */
    private function normalizeLabels(array $labels): array
    {
        $normalized = [];

        foreach ($labels as $name => $value) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $name)) {
                continue;
            }

            $normalized[(string) $name] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => 'none',
                default => (string) $value,
            };
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<int, float>
     */
    private function buckets(string $metric): array
    {
        $buckets = config('observability.metrics.histogram_buckets.' . $metric, []);

        return array_values(array_map('floatval', is_array($buckets) ? $buckets : []));
    }

    private function histogramKey(string $hash, string $suffix): string
    {
        return 'observability:metrics:histogram:' . $hash . ':' . $suffix;
    }

    /**
     * @param array<string, string|int|bool|null> $labels
     */
    private function sample(string $metric, array $labels, int|float $value): string
    {
        $labels = $this->normalizeLabels($labels);

        if ($labels === []) {
            return sprintf('%s %s', $metric, $this->formatValue($value));
        }

        $pairs = [];

        foreach ($labels as $name => $labelValue) {
            $pairs[] = sprintf('%s="%s"', $name, $this->escapeLabelValue($labelValue));
        }

        return sprintf('%s{%s} %s', $metric, implode(',', $pairs), $this->formatValue($value));
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\"'], $value);
    }

    private function formatValue(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private function formatBucket(float $bucket): string
    {
        return $this->formatValue($bucket);
    }
}
