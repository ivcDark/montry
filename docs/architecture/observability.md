# Observability and Business Monitoring

Дата обновления: 2026-05-25.

## Цель

Observability в Montry является частью ядра продукта, а не внешней
надстройкой. Администратор и владелец сервиса должен видеть:

- работает ли само приложение;
- выполняются ли клиентские проверки;
- доставляются ли уведомления;
- проходят ли оплаты;
- где пользователи упираются в лимиты тарифов;
- как меняются регистрации, тарифы, сайты, мониторы и инциденты во времени;
- какие предупреждения и ошибки требуют реакции.

Система должна давать техническую картину, бизнес-картину и возможность
быстро расследовать проблему по конкретной операции.

## Целевая схема

```text
Laravel web app
Go poller
Queue workers
Scheduler
Payment integration
Notification providers
        |
        |-- Metrics --------> Prometheus
        |-- Logs -----------> Loki
        |-- Traces ---------> Tempo
        |-- Exceptions -----> Sentry
        |-- Business events -> PostgreSQL -> ClickHouse
        |
      Grafana
        |
   Dashboards + Alerts
```

## Разделение данных

Observability-данные делятся на пять типов.

### 1. Business events

Business events фиксируют действия и изменения бизнес-состояния. Это основной
источник продуктовой статистики в любых разрезах по времени.

Примеры событий:

- `registration.form_opened`
- `registration.email_submitted`
- `registration.code_sent`
- `registration.code_verified`
- `registration.completed`
- `plan.selected`
- `plan.changed`
- `billing.limit_hit`
- `site.created`
- `site.deleted`
- `site.paused`
- `site.resumed`
- `monitor.created`
- `monitor.enabled`
- `monitor.disabled`
- `monitor.deleted`
- `check.scheduled`
- `check.started`
- `check.finished`
- `check.failed`
- `incident.opened`
- `incident.resolved`
- `notification.email.sent`
- `notification.email.failed`
- `notification.telegram.sent`
- `notification.telegram.failed`
- `payment.started`
- `payment.succeeded`
- `payment.failed`
- `subscription.activated`
- `subscription.expired`

Минимальная структура события:

```text
event_id
event_type
occurred_at
user_id
organization_id
plan_code
subject_type
subject_id
status
source
correlation_id
payload
created_at
```

PostgreSQL остается операционным источником истины. ClickHouse используется для
быстрых аналитических запросов и долгого хранения событий. Запись в ClickHouse
идет через outbox/worker, чтобы временная недоступность аналитики не ломала
основной продукт.

### 2. Product metrics

Product metrics нужны для быстрых графиков и алертов в Prometheus.

Примеры:

```text
montry_registration_form_opened_total
montry_registrations_total
montry_plan_selected_total{plan="free|solo|studio"}
montry_sites_total
montry_sites_active_total
montry_monitors_total{type="http|ssl|domain"}
montry_monitors_enabled_total{type="http|ssl|domain"}
montry_incidents_open_total
montry_incidents_resolved_total
montry_notifications_total{channel="email|telegram",status="sent|failed"}
montry_payments_total{status="succeeded|failed"}
montry_limit_hits_total{plan="free|solo|studio",limit="monitors|manual_checks|channels"}
```

В Prometheus нельзя использовать high-cardinality labels: `user_id`,
`organization_id`, `site_id`, `monitor_id`, `email`, `domain`, `payment_id`.
Такие разрезы должны идти через business events в PostgreSQL/ClickHouse.

### 3. Technical metrics

Technical metrics показывают работоспособность приложения и инфраструктуры.

Примеры:

```text
montry_http_requests_total
montry_http_request_duration_seconds
montry_http_errors_total
montry_queue_jobs_pending
montry_queue_jobs_failed_total
montry_queue_job_duration_seconds
montry_poller_jobs_total{type="http|ssl|domain",source="scheduled|manual",status="success|failed"}
montry_poller_check_duration_seconds{type="http|ssl|domain"}
montry_poller_result_delivery_failures_total
montry_internal_api_requests_total
montry_internal_api_errors_total
montry_db_query_duration_seconds
montry_redis_errors_total
montry_mail_provider_errors_total
```

Инфраструктурные метрики собираются через exporters:

- CPU, RAM, disk, network;
- Docker/container restarts;
- PostgreSQL connections and query health;
- Redis memory and availability;
- queue depth;
- external blackbox checks for public Montry endpoints.

### 4. Logs

Все сервисы пишут структурированные JSON-логи. Loki является центральным
хранилищем логов для предупреждений, ошибок и расследований.

Пример записи:

```json
{
  "level": "warning",
  "service": "web",
  "component": "billing",
  "event": "billing.limit_hit",
  "message": "Organization reached monitor limit",
  "organization_id": 42,
  "user_id": 7,
  "plan": "free",
  "limit": "monitors",
  "correlation_id": "uuid",
  "timestamp": "2026-05-25T12:00:00+04:00"
}
```

Рекомендуемые Loki labels:

```text
service=web|poller|queue|scheduler
env=local|staging|production
level=debug|info|warning|error|critical
component=auth|billing|monitoring|notifications|payments|poller|worker_gateway
```

Не использовать как Loki labels:

```text
user_id
organization_id
email
domain
monitor_id
payment_id
event_id
correlation_id
```

Эти поля должны оставаться внутри JSON-сообщения.

### 5. Traces

OpenTelemetry и Tempo используются для трассировки цепочек операций.

Ключевые операции для трассировки:

- регистрация пользователя;
- выбор тарифа;
- успешная и неуспешная оплата;
- ручная проверка `Check now`;
- плановая проверка monitor;
- прием результата от poller;
- открытие и закрытие incident;
- отправка email/Telegram уведомления.

Trace должен связывать Laravel request, queue jobs, вызовы Go poller,
internal API и внешние интеграции через `correlation_id` и trace context.

## Dashboards

### Owner Dashboard

- registration funnel;
- registration conversion rate;
- selected plans by day;
- active organizations;
- active users;
- active sites;
- active monitors;
- monitors by type;
- payments succeeded/failed;
- revenue/MRR when billing is fully connected;
- limit hits by plan;
- incidents opened/resolved;
- notification delivery rate.

### Operations Dashboard

- Laravel uptime;
- poller uptime;
- queue health;
- DB/Redis health;
- request latency;
- HTTP 5xx rate;
- failed jobs;
- poller result delivery failures;
- email provider failures;
- Telegram provider failures;
- disk, memory, CPU.

### Monitoring Product Dashboard

- scheduled checks per minute;
- manual checks per minute;
- checks by type;
- check success/failure rate;
- average check duration;
- timeout rate;
- DNS/TLS/HTTP/WHOIS errors;
- open incidents;
- incidents by monitor type;
- noisy monitors.

### Billing Dashboard

- plan selections;
- payment attempts;
- successful payments;
- failed payments;
- subscription activations;
- expired subscriptions;
- limit hits;
- users blocked by limit;
- conversion Free -> Solo -> Studio.

### Notifications Dashboard

- email sent;
- email failed;
- Telegram sent;
- Telegram failed;
- delivery latency;
- provider error codes;
- retries;
- dead-letter notifications.

### Security and Audit Dashboard

- failed logins;
- suspicious registration attempts;
- admin actions;
- internal API auth failures;
- payment callback signature failures;
- rate limit hits;
- unusual manual check volume.

## Alerts

Критичные технические алерты:

- Laravel недоступен;
- Go poller недоступен;
- PostgreSQL недоступен;
- Redis недоступен;
- queue backlog растет;
- queue worker не обрабатывает задачи;
- scheduled checks не выполняются;
- poller не присылает check results заданное время;
- `poller_result_delivery_failures_total` растет;
- internal API 5xx rate выше порога;
- email failure rate выше порога;
- payment failure rate резко вырос;
- disk usage выше порога;
- backup не выполнен.

Бизнес-алерты:

- форму регистрации открывают, но регистрацию не завершают;
- нет регистраций дольше ожидаемого периода при наличии трафика;
- резко выросли failed payments;
- резко выросли `billing.limit_hit`;
- резко выросли `incident.opened`;
- резко упала доля успешных checks;
- массово не доставляются email/Telegram уведомления.

## Отказоустойчивость

Для важных операций обязательны:

- `event_id`;
- `correlation_id`;
- idempotency keys;
- outbox pattern;
- retry/backoff;
- dead-letter storage;
- structured error codes;
- health/readiness endpoints;
- graceful shutdown для Go poller;
- bounded queues;
- audit log для admin-действий;
- backup PostgreSQL;
- проверка восстановления backup;
- внешний blackbox monitoring самого Montry.

## Правила внедрения

- Observability не должна ломать пользовательский сценарий при временной
  недоступности Grafana, Loki, Prometheus, Tempo или ClickHouse.
- Business events пишутся синхронно в PostgreSQL только там, где событие
  является частью бизнес-операции. Доставка в ClickHouse выполняется
  асинхронно.
- Metrics не должны содержать персональные данные и high-cardinality labels.
- Logs не должны содержать plaintext passwords, tokens, verification codes,
  payment secrets или полные персональные данные.
- Важные события должны быть доступны и в бизнес-журнале, и в логах, но цели
  разные: бизнес-журнал для аналитики, логи для расследования.
- Grafana является единым интерфейсом для владельца и администратора.

