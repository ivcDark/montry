<?php

return [
    'service_name' => env('OTEL_SERVICE_NAME', 'montry-web'),
    'environment' => env('APP_ENV', 'local'),

    'tracing' => [
        'enabled' => env('OTEL_TRACES_ENABLED', true),
        'endpoint' => rtrim(env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'), '/'),
        'timeout_seconds' => (float) env('OTEL_EXPORTER_OTLP_TIMEOUT_SECONDS', 2),
        'sample_ratio' => (float) env('OTEL_TRACES_SAMPLE_RATIO', 1.0),
    ],

    'clickhouse' => [
        'enabled' => env('OBSERVABILITY_CLICKHOUSE_ENABLED', true),
        'url' => rtrim(env('CLICKHOUSE_URL', 'http://clickhouse:8123'), '/'),
        'database' => env('CLICKHOUSE_DB', 'montry_analytics'),
        'username' => env('CLICKHOUSE_USER', 'montry'),
        'password' => env('CLICKHOUSE_PASSWORD', 'montry_secret'),
        'timeout_seconds' => (float) env('CLICKHOUSE_TIMEOUT_SECONDS', 5),
        'export_batch_size' => (int) env('OBSERVABILITY_CLICKHOUSE_EXPORT_BATCH_SIZE', 500),
        'max_attempts' => (int) env('OBSERVABILITY_CLICKHOUSE_EXPORT_MAX_ATTEMPTS', 5),
    ],

    'metrics' => [
        'enabled' => env('OBSERVABILITY_METRICS_ENABLED', true),
        'token' => env('OBSERVABILITY_METRICS_TOKEN'),
        'allowed_ips' => array_filter(array_map(
            'trim',
            explode(',', env('OBSERVABILITY_METRICS_ALLOWED_IPS', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))
        )),
        'default_queues' => array_filter(array_map(
            'trim',
            explode(',', env('OBSERVABILITY_METRICS_QUEUES', 'default,notifications,reports'))
        )),
        'histogram_buckets' => [
            'http_request_duration_seconds' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
            'internal_api_duration_seconds' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
            'queue_job_duration_seconds' => [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30, 60, 120],
        ],
    ],
];
