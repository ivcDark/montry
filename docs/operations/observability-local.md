# Local Observability Stack

Дата обновления: 2026-05-25.

## Назначение

Локальный observability stack нужен для контроля самого Montry:

- инфраструктура Docker и хоста;
- доступность Laravel, nginx и Go poller;
- будущие Laravel и poller метрики;
- централизованные логи через Loki;
- распределенные traces через Tempo;
- аналитические события через ClickHouse;
- единый интерфейс в Grafana.

## Запуск

Обычный `make up` не поднимает тяжелый observability stack. Для него есть
отдельный Docker Compose profile.

```bash
make observability-up
```

Остановка:

```bash
make observability-down
```

Логи observability-сервисов:

```bash
make observability-logs
```

## Адреса

- Grafana: `http://localhost:3000`
- Prometheus: `http://localhost:9090`
- Loki API: `http://localhost:3100`
- Tempo API: `http://localhost:3200`
- ClickHouse HTTP: `http://localhost:8123`
- Laravel Prometheus metrics: `http://localhost:8080/internal/metrics`
- Poller Prometheus metrics: `http://localhost:8090/metrics`
- cAdvisor: `http://localhost:8088`
- Node Exporter: `http://localhost:9100`
- Blackbox Exporter: `http://localhost:9115`
- OpenTelemetry Collector metrics: `http://localhost:8888`
- OpenTelemetry Prometheus exporter: `http://localhost:8889`

Логин Grafana по умолчанию:

```text
admin / admin
```

Значения можно поменять в root `.env` через:

```text
GRAFANA_ADMIN_USER
GRAFANA_ADMIN_PASSWORD
```

## Datasources в Grafana

Grafana автоматически получает datasources:

- `Prometheus`
- `Loki`
- `Tempo`
- `PostgreSQL`
- `ClickHouse`

Provisioning лежит в:

```text
docker/observability/grafana/provisioning/
```

Стартовый dashboard:

```text
docker/observability/grafana/dashboards/montry-overview.json
```

## Что уже собирается

Prometheus уже настроен на:

- сам Prometheus;
- OpenTelemetry Collector;
- Laravel `/internal/metrics`;
- Go poller `/metrics`;
- Node Exporter;
- cAdvisor;
- blackbox-проверки из
  `docker/observability/prometheus/blackbox_targets.yml`;
- PostgreSQL backup/restore метрики через node-exporter textfile collector.

## Alerts and Runbooks

Prometheus alert rules лежат в:

```text
docker/observability/prometheus/rules/
```

Основные группы:

- `availability.yml` — Laravel, PostgreSQL, Redis и internal API;
- `poller.yml` — доступность poller, отсутствие результатов и ошибки доставки;
- `business.yml` — registration funnel и check processing;
- `billing.yml` — деградация оплат;
- `notifications.yml` — email/Telegram failures и queue backlog;
- `deadletters.yml` — permanent side-effect failures и retry backlog;
- `montry.yml` — базовые platform/container/disk/blackbox alerts.

Сводка правил и проверочные команды:

```text
docs/operations/alerts.md
```

Runbooks:

```text
docs/operations/runbooks/laravel-down.md
docs/operations/runbooks/poller-no-results.md
docs/operations/runbooks/email-failures.md
docs/operations/runbooks/payment-failures.md
docs/operations/runbooks/queue-backlog.md
```

Backup и self-monitoring procedures:

```text
docs/operations/backups.md
docs/operations/self-monitoring.md
```

## Audit logs

Security-sensitive и admin события пишутся отдельно от product analytics в
таблицу PostgreSQL:

```text
audit_logs
```

Туда попадают:

- admin login, failed admin login, blocked admin login и logout;
- admin блокировка/разблокировка пользователя;
- admin смена тарифа организации;
- internal API auth failures;
- fake-bank/payment signature failures при наличии подписи и секрета;
- rate limit hits на sensitive endpoints, если endpoint вернул HTTP 429.

Audit metadata не хранит raw password, token, authorization header, secret или
signature. IP и User-Agent сохраняются как SHA-256 hashes.

Prometheus получает агрегированную метрику:

```text
montry_audit_logs_total
```

Labels ограничены `category`, `action`, `outcome`, `source`.

## Dead letters

Постоянные ошибки побочных процессов пишутся в PostgreSQL:

```text
dead_letters
```

Текущие источники:

- `notifications/notification_delivery` — доставка уведомления завершилась
  ошибкой после доступных попыток;
- `clickhouse/business_event_export` — export business event в ClickHouse
  исчерпал `OBSERVABILITY_CLICKHOUSE_EXPORT_MAX_ATTEMPTS`;
- `poller/check_result_payload_invalid` — poller отправил payload, который не
  прошел Laravel validation;
- `poller/check_result_processing_failed` — payload прошел validation, но не
  может быть применен к монитору.

Admin UI:

```text
/admin/dead-letters
```

Retry recoverable записей:

```bash
make artisan cmd="observability:retry-dead-letter --all"
```

Prometheus получает агрегированную метрику:

```text
montry_dead_letters_total
```

## Laravel metrics

Laravel endpoint:

```text
GET /internal/metrics
```

В локальном Docker endpoint доступен Prometheus по внутренней сети. Если
`OBSERVABILITY_METRICS_TOKEN` задан в `apps/web/.env`, endpoint принимает
только запросы с одним из заголовков:

```text
Authorization: Bearer <token>
X-Montry-Metrics-Token: <token>
```

Если токен не задан, доступ разрешен только с private/internal IP диапазонов из
`OBSERVABILITY_METRICS_ALLOWED_IPS`.

Основные метрики:

- `montry_business_events_total` — счетчики бизнес-событий по `event_type`,
  `status`, `source`, `plan_code`;
- `montry_monitored_resources_total` — текущие сайты/ресурсы по типу и статусу;
- `montry_monitors_total` — текущие мониторы по типу, статусу и включенности;
- `montry_open_incidents_total` — открытые инциденты по severity;
- `montry_subscriptions_total` — подписки по тарифу и статусу;
- `montry_payments_total` — оплаты по провайдеру, статусу и валюте;
- `montry_queue_jobs_total` и `montry_failed_jobs_total` — очереди и failed jobs;
- `montry_http_request_duration_seconds` — histogram длительности Laravel
  запросов;
- `montry_internal_api_duration_seconds` — histogram внутренних API запросов;
- `montry_queue_job_duration_seconds` — histogram длительности queue jobs.

В labels нельзя добавлять `user_id`, `organization_id`, `monitor_id`, `site_id`,
email, domain или URL. Метрики должны оставаться агрегированными.

## Poller metrics

Poller endpoint:

```text
GET /metrics
```

Основные метрики:

- `montry_poller_build_info` — статичная информация о сервисе;
- `montry_poller_jobs_total` — jobs по `check_type`, `source`, `status`;
- `montry_poller_check_duration_seconds` — histogram длительности checks по
  `check_type` и `status`;
- `montry_poller_result_delivery_total` — попытки доставки результата в Laravel
  по `check_type` и `status`;
- `montry_poller_queue_buffer_used` — занятая часть in-memory очереди;
- `montry_poller_queue_buffer_capacity` — размер буфера очереди;
- `montry_poller_workers` — количество worker goroutines.

Poller metrics также не должны содержать IDs, targets, domains или URLs.

## ClickHouse

ClickHouse поднимается с базой `montry_analytics` и таблицей
`analytics_events`.

Подключение через CLI:

```bash
make clickhouse-shell
```

Таблица создана для будущего экспорта business events из PostgreSQL:

```sql
SELECT event_type, count()
FROM analytics_events
GROUP BY event_type
ORDER BY count() DESC;
```

Экспорт business events запускается Laravel-командой:

```bash
make artisan cmd="observability:export-business-events"
```

Команда выбирает еще не экспортированные записи из PostgreSQL `business_events`,
пишет их в ClickHouse `analytics_events` через `JSONEachRow` и отмечает прогресс
в PostgreSQL `analytics_event_exports`. Повторный запуск не выбирает уже
успешно экспортированные события. Временные ошибки ClickHouse увеличивают
`attempts`; после лимита запись получает статус `failed` и сохраняет
`last_error`.

Основные настройки в `apps/web/.env`:

```text
OBSERVABILITY_CLICKHOUSE_ENABLED=true
OBSERVABILITY_CLICKHOUSE_EXPORT_BATCH_SIZE=500
OBSERVABILITY_CLICKHOUSE_EXPORT_MAX_ATTEMPTS=5
CLICKHOUSE_URL=http://clickhouse:8123
CLICKHOUSE_DB=montry_analytics
CLICKHOUSE_USER=montry
CLICKHOUSE_PASSWORD=montry_secret
```

Laravel scheduler запускает export каждую минуту.

## Логи

Laravel пишет структурированные JSON-логи в `storage/logs/laravel.log`.
OpenTelemetry Collector читает этот файл через `filelog/laravel` receiver и
отправляет записи в Loki.

Go poller пишет JSON-логи в stdout. Для локального Docker Compose у `poller`
включен fluent logging driver с async-доставкой в `otel-collector:8006`, поэтому
poller logs также попадают в Loki.

Проверочный Loki query:

```text
{exporter="OTLP"} |= "observability"
```

Логи содержат стабильные поля `service`, `component`, `level`, `message` и
`correlation_id`, где он доступен. Чувствительные поля вроде `token`,
`authorization`, `password`, `secret` редактируются до `[redacted]`.

## Traces

Laravel и Go poller отправляют traces в OpenTelemetry Collector через OTLP/HTTP:

```text
http://otel-collector:4318/v1/traces
```

Collector экспортирует traces в Tempo. В Grafana можно открыть datasource
`Tempo` и искать traces по сервисам:

```text
service.name = montry-web
service.name = montry-poller
```

Что трассируется:

- Laravel HTTP requests;
- Laravel queue jobs;
- business events вроде `registration.*`, `plan.*`, `check.*`,
  `notification.*`, `payment.*`;
- Laravel -> poller manual check requests;
- poller -> Laravel due checks fetch;
- poller check execution;
- poller -> Laravel check result delivery;
- Laravel check result receive/process flow.

Trace context передается через W3C `traceparent` header и поле `traceparent` в
payload между Laravel и poller. `correlation_id` остается отдельным полем для
логов и бизнес-событий.

## Правило эксплуатации

Observability stack не должен быть обязательным для работы продукта. Если
Grafana, Loki, Tempo, Prometheus или ClickHouse временно недоступны, Laravel и
Go poller должны продолжать выполнять основную бизнес-логику.
