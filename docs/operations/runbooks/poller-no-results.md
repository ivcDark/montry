# Runbook: Poller No Results or Delivery Failures

## Alerts

- `MontryPollerUnavailable`
- `MontryNoCheckResults`
- `MontryPollerResultDeliveryFailures`
- `MontryPollerQueueBufferHigh`
- `MontryCheckProcessingFailures`

## Symptoms

- Monitor statuses stop changing.
- Manual checks remain pending or fail.
- Incidents are not opened or resolved from new checks.
- Prometheus shows enabled monitors but no successful poller jobs.

## Checks

```bash
docker compose ps poller nginx web
docker compose logs --tail=200 poller
curl -i http://localhost:8090/health
curl -i http://localhost:8090/metrics
```

In Prometheus:

```promql
up{job="poller"}
probe_success{job="blackbox-montry",instance="http://poller:8090/health"}
sum(increase(montry_poller_jobs_total[30m])) by (status)
sum(rate(montry_poller_result_delivery_total[10m])) by (status)
montry_poller_queue_buffer_used / montry_poller_queue_buffer_capacity
```

## Likely Causes

- Poller process crashed or is stuck during startup.
- `LARAVEL_INTERNAL_API_URL` points to the wrong endpoint.
- `LARAVEL_INTERNAL_API_TOKEN` does not match Laravel internal API settings.
- Laravel internal API is returning 5xx.
- Queue buffer is too small for the current check burst.
- External DNS/network problems make checks slow or time out.

## Remediation

1. Fix Laravel availability first if internal API alerts are also firing.
2. Verify poller environment variables in root `.env`.
3. Restart poller:

```bash
docker compose restart poller
```

4. If the queue buffer stays above 80%, increase `POLLER_QUEUE_BUFFER` and
   consider increasing `POLLER_WORKERS` cautiously.
5. Trigger a manual check from the dashboard and confirm a new
   `montry_poller_jobs_total{status="success"}` increment.

## Escalation

Escalate if delivery failures continue after Laravel is healthy and tokens/URLs
are confirmed.

