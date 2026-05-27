# Runbook: Laravel Down or Internal API 5xx

## Alerts

- `MontryLaravelUnavailable`
- `MontryPostgresUnavailable`
- `MontryInternalApi5xx`

## Symptoms

- Dashboard, registration, billing or monitor pages do not load.
- Prometheus cannot scrape `job="laravel"`.
- Blackbox probe for `http://nginx` fails.
- Poller result delivery may also fail because Laravel internal API is down.

## Checks

```bash
docker compose ps nginx web postgres redis
docker compose logs --tail=200 nginx web postgres
curl -i http://localhost:8080/internal/metrics
docker compose exec postgres pg_isready -U ${POSTGRES_USER:-montry} -d ${POSTGRES_DB:-montry}
```

In Prometheus:

```promql
up{job="laravel"}
probe_success{job="blackbox-montry",instance="http://nginx"}
rate(montry_internal_api_duration_seconds_count{status_class="5xx"}[5m])
```

## Likely Causes

- PHP-FPM container crashed or cannot boot Laravel.
- `.env` or `apps/web/.env` is misconfigured.
- Database or Redis is unavailable.
- Migration drift after deploy.
- Internal metrics token or allowed IP settings block Prometheus.

## Remediation

1. Check `web` and `nginx` logs for the first exception.
2. If Laravel cannot connect to PostgreSQL or Redis, recover that dependency
   before restarting Laravel.
3. Run migrations only if the deployment expected schema changes:

```bash
make migrate
```

4. Restart only the unhealthy service:

```bash
docker compose restart web nginx
```

5. Confirm recovery in Prometheus and by opening the dashboard.

## Escalation

Escalate if Laravel still returns 5xx after dependency recovery and a service
restart, or if migrations fail.

