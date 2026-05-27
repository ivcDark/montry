CREATE TABLE IF NOT EXISTS montry_analytics.analytics_events
(
    event_id UUID,
    event_type LowCardinality(String),
    occurred_at DateTime64(3, 'UTC'),
    organization_id Nullable(UInt64),
    user_id Nullable(UInt64),
    plan_code LowCardinality(Nullable(String)),
    subject_type LowCardinality(Nullable(String)),
    subject_id Nullable(String),
    status LowCardinality(Nullable(String)),
    source LowCardinality(Nullable(String)),
    correlation_id Nullable(String),
    payload String,
    ingested_at DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = ReplacingMergeTree(ingested_at)
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (event_id);
