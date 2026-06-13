# Runbook: Queue Backlog or Failed Jobs

## Alerts

- `MontryNotificationQueueBacklog`
- `MontryFailedJobsPresent`
- `MontryRedisUnavailable`

## Symptoms

- Email, Telegram, reports or background jobs are delayed.
- `montry_queue_jobs_total` grows and does not drain.
- `failed_jobs` contains records.
- Registration or notification delivery may appear broken.

## Checks

```bash
docker compose ps redis web
docker compose logs --tail=200 web redis
make artisan cmd="queue:failed"
```

In Prometheus:

```promql
sum(montry_queue_jobs_total) by (queue)
sum(montry_failed_jobs_total) by (queue)
```

Redis:

```bash
docker compose exec redis redis-cli ping
```

## Likely Causes

- Redis is unavailable or misconfigured.
- Queue worker is not running.
- A job repeatedly fails because an external provider is unavailable.
- Queue volume exceeds current worker capacity.
- A deployment changed job payloads while old jobs remained in the queue.

## Remediation

1. Restore Redis first if `MontryRedisUnavailable` is firing.
2. Start or restart queue worker service. In the current local compose file the
   queue worker is still commented, so run it manually when needed:

```bash
docker compose exec web php artisan queue:work redis --queue=notifications,reports,default --sleep=1 --tries=3 --timeout=120
```

3. Inspect failed jobs:

```bash
make artisan cmd="queue:failed"
```

4. Retry only after fixing the underlying error:

```bash
make artisan cmd="queue:retry all"
```

5. If backlog is expected load, increase worker count before lowering alert
   thresholds.

## Escalation

Escalate if failed jobs continue after Redis and provider dependencies are
healthy.

