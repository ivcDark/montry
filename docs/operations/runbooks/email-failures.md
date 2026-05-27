# Runbook: Email, Telegram or Registration Delivery Failures

## Alerts

- `MontryNotificationFailureRateHigh`
- `MontryRegistrationFunnelBreakage`

## Symptoms

- Users do not receive registration verification codes.
- Incident, SSL or domain expiration notifications are not delivered.
- `notification.email.failed` or `notification.telegram.failed` business events
  rise.
- Registration email submissions happen, but registrations do not complete.

## Checks

```bash
docker compose ps web redis mailpit
docker compose logs --tail=200 web
```

For local email:

```text
http://localhost:8025
```

In Prometheus:

```promql
sum(increase(montry_business_events_total{event_type=~"notification\\..*\\.failed"}[30m]))
sum(increase(montry_business_events_total{event_type="registration.email_submitted"}[1h]))
sum(increase(montry_business_events_total{event_type="registration.completed"}[1h]))
```

## Likely Causes

- SMTP host, port, credentials or encryption are wrong in `apps/web/.env`.
- Mailpit is down in local development.
- Telegram bot token is invalid or revoked.
- Recipient channel is disabled or has invalid address/chat id.
- Queue workers are not running or queue backlog is high.

## Remediation

1. If queue alerts are firing, follow
   `docs/operations/runbooks/queue-backlog.md` first.
2. Verify mail settings in `apps/web/.env`; do not change root `.env` for
   Laravel mail configuration.
3. In local development, open Mailpit and confirm verification code messages.
4. For Telegram, send a test message with the configured bot token outside the
   app before changing application code.
5. Re-send a registration code or trigger a test notification and confirm a
   `notification.*.sent` business event.

## Escalation

Escalate if provider credentials are valid but Laravel logs show repeated
transport exceptions.

