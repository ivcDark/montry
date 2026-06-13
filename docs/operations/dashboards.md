# Grafana Dashboards

Дата обновления: 2026-05-27.

Dashboards лежат в:

```text
docker/observability/grafana/dashboards/
```

Grafana автоматически загружает их в folder `Montry` через file provisioning.

## Montry Owner

Файл:

```text
owner.json
```

Назначение: быстрый обзор бизнеса для владельца.

Основные источники:

- ClickHouse `analytics_events`;
- PostgreSQL `business_events`;
- Prometheus product gauges.

Панели:

- регистрации за 30 дней;
- выборы тарифов за 30 дней;
- включенные мониторы;
- открытые инциденты;
- продуктовая активность по дням;
- активность по тарифам.

## Montry Operations

Файл:

```text
operations.json
```

Назначение: техническое состояние локального/production stack.

Основные источники:

- Prometheus;
- Loki.

Панели:

- доступность core scrape targets;
- backlog очередей;
- failed jobs;
- poller workers;
- p95 Laravel HTTP latency;
- последние error logs.

## Montry Monitoring Product

Файл:

```text
monitoring-product.json
```

Назначение: состояние monitoring-функциональности как продукта.

Основные источники:

- Prometheus poller and product metrics;
- ClickHouse business events.

Панели:

- monitored resources;
- enabled monitors;
- open incidents;
- poller job rate;
- jobs by check type and status;
- check/incident business events.

## Montry Billing

Файл:

```text
billing.json
```

Назначение: тарифы, оплаты и billing friction.

Основные источники:

- Prometheus billing metrics;
- ClickHouse billing events;
- PostgreSQL subscriptions.

Панели:

- active subscriptions;
- succeeded payments;
- failed payments;
- billing limit hits;
- billing events over time;
- subscriptions by plan.

## Montry Notifications

Файл:

```text
notifications.json
```

Назначение: контроль доставки email/Telegram и очередей уведомлений.

Основные источники:

- Prometheus business and queue metrics;
- ClickHouse notification events;
- Loki failure logs.

Панели:

- sent notifications;
- failed notifications;
- notification queue depth;
- failed notification jobs;
- notification events by type/status/source;
- notification failure logs.

## Montry Security and Audit

Файл:

```text
security-audit.json
```

Назначение: временный security/audit обзор до полноценного audit log из Epic 12.

Основные источники:

- ClickHouse business events;
- PostgreSQL `audit_logs`;
- Loki structured logs;
- Prometheus risk-related counters.

Панели:

- auth events;
- unauthorized logs;
- signature warnings;
- risk event counter;
- security-relevant product events;
- security logs.
- audit events total;
- failed or blocked audit events;
- audit events by category/action/outcome/source for 24h;
- latest audit events.

После Epic 12 dashboard читает отдельную таблицу `audit_logs` для
security-sensitive и admin операций. Business events остаются источником
product analytics, а audit logs — источником расследования действий админов,
auth failures, internal API auth failures и payment signature failures.

## Проверка

Проверить provisioning:

```bash
make observability-up
```

Затем открыть:

```text
http://localhost:3000/dashboards
```

или проверить через Grafana API:

```bash
docker compose exec grafana wget -qO- \
  --header='Authorization: Basic YWRtaW46YWRtaW4=' \
  http://localhost:3000/api/search?folderIds=1
```
