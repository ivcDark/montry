# Runbook: Dead Letters

## Alerts

- `MontryDeadLettersOpen`
- `MontryRecoverableDeadLettersOpen`

## Symptoms

- Prometheus shows open `montry_dead_letters_total` records.
- Admin page `/admin/dead-letters` lists failed side effects.
- Notifications, ClickHouse exports or poller result processing may have stopped
  for individual records while the core product continues running.

## Checks

```bash
make artisan cmd="observability:retry-dead-letter --all"
```

Admin UI:

```text
http://localhost:8080/admin/dead-letters
```

Prometheus:

```promql
sum(montry_dead_letters_total) by (source, type, status, recoverable)
```

Database:

```sql
select source, type, status, recoverable, count(*)
from dead_letters
group by source, type, status, recoverable
order by source, type, status;
```

## Likely Causes

- Notification provider rejected a delivery after all available attempts.
- ClickHouse export reached `OBSERVABILITY_CLICKHOUSE_EXPORT_MAX_ATTEMPTS`.
- Poller sent a malformed or semantically invalid check result payload.
- A deployment changed payload contracts while old jobs were still running.

## Remediation

1. Open `/admin/dead-letters` and filter by source.
2. For `clickhouse/business_event_export`, fix ClickHouse availability or
   credentials first, then run:

```bash
make artisan cmd="observability:retry-dead-letter --all"
```

3. For `notifications/notification_delivery`, inspect the notification channel
   and provider error. These records are marked non-recoverable until queued
   notification retry workers are introduced.
4. For `poller/check_result_payload_invalid` or
   `poller/check_result_processing_failed`, compare the payload with
   `docs/api/internal-api.md` and the current `StoreCheckResultRequest`.
5. Keep resolved rows for auditability. Do not delete dead letters during an
   incident unless explicitly cleaning local development data.

## Escalation

Escalate if recoverable ClickHouse dead letters return to `open` after retry, or
if poller payload dead letters appear after a contract change.

