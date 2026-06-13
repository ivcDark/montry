# Observability Verification

Дата проверки: 2026-05-27.

## Scope

Epic 16 проверяет локальный end-to-end observability stack Montry:

- Docker Compose application services;
- Prometheus metrics and alert rules;
- Blackbox HTTP/TCP probes;
- Loki logs;
- Tempo traces;
- ClickHouse analytics events;
- Grafana dashboards;
- PostgreSQL business/audit/dead-letter data;
- backup and restore verification;
- Sentry command wiring.

## Stack Startup

```bash
make up
make observability-up
docker compose ps
```

Подтверждено: `nginx`, `web`, `poller`, `postgres`, `redis`, `mailpit`,
`grafana`, `prometheus`, `loki`, `tempo`, `otel-collector`, `clickhouse`,
`node-exporter`, `cadvisor`, `blackbox-exporter` запущены.

## Product Verification Fixture

В Laravel container был создан verification dataset:

- test user `e2e+20260527124554@example.test`;
- organization `5`;
- site `1`;
- HTTP/SSL/domain monitors `1`, `2`, `3`;
- selected plans: `free`, `pro`, `plus`;
- paid payments and one failed payment;
- manual check request;
- check results for failure and recovery;
- incident opened and resolved;
- notification success and notification failure;
- explicit verification log event;
- explicit verification trace span.

Итоговые PostgreSQL counts:

```json
{
  "business_events": 88,
  "verification_events": 8,
  "check_results": 7,
  "incidents_opened": 1,
  "incidents_resolved": 1,
  "notification_sent": 5,
  "notification_failed": 5,
  "payments_paid": 6,
  "payments_failed": 1,
  "monitors": 3
}
```

## ClickHouse

Export command:

```bash
docker compose exec -T web php artisan observability:export-business-events --batch=500
```

Result:

```text
Selected 27 business events, exported 27, failed 0.
```

ClickHouse verification:

```bash
docker compose --profile observability exec -T clickhouse \
  clickhouse-client \
  -u ${CLICKHOUSE_USER:-montry} \
  --password ${CLICKHOUSE_PASSWORD:-montry_secret} \
  -d ${CLICKHOUSE_DB:-montry_analytics} \
  --query "SELECT count() AS total, countIf(source = 'verification') AS verification_events FROM analytics_events"
```

Result:

```text
113  8
```

## Prometheus

Config check:

```bash
docker run --rm \
  --entrypoint promtool \
  -v "$PWD/docker/observability/prometheus:/etc/prometheus:ro" \
  prom/prometheus:v2.55.1 \
  check config /etc/prometheus/prometheus.yml
```

Result:

```text
SUCCESS: /etc/prometheus/prometheus.yml is valid prometheus config file syntax
availability.yml: 8 rules found
```

Important Prometheus queries:

```promql
up
probe_success{job="blackbox-montry"}
probe_success{job="blackbox-montry-tcp"}
montry_poller_jobs_total
montry_poller_result_delivery_total
montry_postgres_backup_last_status
montry_postgres_backup_last_verify_status
ALERTS
```

Confirmed samples:

```text
up{job="laravel",instance="nginx:80"} => 1
up{job="poller",instance="poller:8090"} => 1
up{job="node-exporter",instance="node-exporter:9100"} => 1
up{job="cadvisor",instance="cadvisor:8080"} => 1
probe_success{job="blackbox-montry",instance="http://nginx/"} => 1
probe_success{job="blackbox-montry",instance="http://nginx/login"} => 1
probe_success{job="blackbox-montry",instance="http://nginx/register"} => 1
probe_success{job="blackbox-montry",instance="http://poller:8090/health"} => 1
probe_success{job="blackbox-montry-tcp",instance="postgres:5432"} => 1
probe_success{job="blackbox-montry-tcp",instance="redis:6379"} => 1
montry_poller_jobs_total{check_type="http",source="manual",status="queued"} => 2
montry_poller_jobs_total{check_type="http",source="manual",status="success"} => 2
```

Note: `MontryDeadLettersOpen` can be pending after this verification because the
fixture intentionally creates a Telegram notification failure to verify
dead-letter and notification-failure paths.

## Loki

Loki labels:

```bash
docker compose exec -T web php -r 'echo file_get_contents("http://loki:3100/loki/api/v1/labels"), PHP_EOL;'
```

Result includes:

```json
["exporter"]
```

Recent logs query:

```bash
docker compose exec -T web php -r '$query=urlencode("{exporter=\"OTLP\"}"); echo file_get_contents("http://loki:3100/loki/api/v1/query_range?limit=20&query={$query}"), PHP_EOL;'
```

Confirmed: Loki returns recent Laravel container logs with `service.namespace`
set to `montry`.

## Tempo

Tempo search:

```bash
docker compose exec -T web php -r 'echo file_get_contents("http://tempo:3200/api/search?limit=20"), PHP_EOL;'
```

Confirmed traces:

- `montry-web` root trace `http.request`;
- `montry-poller` root trace `poller.manual_check.receive`;
- `montry-poller` root trace `laravel.fetch_checks`.

## Grafana

Health:

```bash
docker compose exec -T web php -r 'echo file_get_contents("http://grafana:3000/api/health"), PHP_EOL;'
```

Result:

```json
{
  "database": "ok",
  "version": "11.4.0"
}
```

Provisioned dashboards confirmed through API:

- `Montry Overview`
- `Montry Monitoring Product`
- `Montry Operations`
- `Montry Owner`
- `Montry Notifications`
- `Montry Billing`
- `Montry Security and Audit`

## Sentry

Command wiring check:

```bash
docker compose exec -T web php artisan observability:test-sentry
```

Result:

```text
Sentry is disabled. Set SENTRY_DSN or SENTRY_LARAVEL_DSN.
```

Live Sentry visibility was not verified because no Sentry DSN is configured in
the current environment. After setting `SENTRY_LARAVEL_DSN` and
`SENTRY_POLLER_DSN`, rerun:

```bash
docker compose exec -T web php artisan observability:test-sentry
```

Then confirm the event in the Sentry project for the configured environment and
release.

## Backups

Verified commands:

```bash
./scripts/backup-postgres.sh
./scripts/verify-postgres-backup.sh
```

Results:

```text
PostgreSQL backup created: backups/postgres/montry-20260527T123315Z.dump
PostgreSQL backup verified against temporary database montry_restore_verify_1779885208_32852
```

## Current Gaps

- Live Sentry event visibility requires real DSNs.
- Payment success/failure was verified through the local fake/manual payment
  path, not a real external payment provider.
- Alertmanager contact points are not provisioned yet; Prometheus/Grafana rule
  loading and alert state are verified.
