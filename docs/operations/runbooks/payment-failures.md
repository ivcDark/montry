# Runbook: Payment Failures

## Alerts

- `MontryPaymentFailureRateHigh`
- `MontryNoSuccessfulPaymentsAfterStarts`

## Symptoms

- Users can start checkout but subscriptions do not activate.
- Payments remain failed, canceled or expired.
- `payment.started` events continue but `payment.succeeded` is absent.

## Checks

```bash
docker compose logs --tail=200 web
make artisan cmd="about"
```

In Prometheus:

```promql
sum(montry_payments_total) by (status)
sum(increase(montry_business_events_total{event_type="payment.started"}[2h]))
sum(increase(montry_business_events_total{event_type="payment.succeeded"}[2h]))
```

In database:

```bash
docker compose exec postgres psql -U ${POSTGRES_USER:-montry} -d ${POSTGRES_DB:-montry}
```

```sql
select status, count(*) from payments group by status order by status;
select status, count(*) from subscriptions group by status order by status;
```

## Likely Causes

- Fake bank/local payment flow has validation or route errors.
- Payment provider credentials are wrong.
- Webhook or return URL is misconfigured.
- Subscription activation failed after payment success.
- User canceled checkout or payment expired normally during testing.

## Remediation

1. Check Laravel logs around `payment.started`, `payment.succeeded`,
   `subscription.activated` and `plan.changed`.
2. Reproduce one checkout with a test account.
3. Confirm payment status changes and subscription activation in the database.
4. If using a real provider later, verify webhook signature, callback URL and
   provider dashboard errors.
5. Keep failed records for debugging; do not delete them unless explicitly
   cleaning local test data.

## Escalation

Escalate if successful provider payments exist but Montry subscriptions are not
activated.

