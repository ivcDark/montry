# Observability Alerts

Дата обновления: 2026-05-27.

Alert rules лежат в:

```text
docker/observability/prometheus/rules/
```

Prometheus загружает все `*.yml` из этого каталога через `rule_files` в
`docker/observability/prometheus/prometheus.yml`.

## Группы правил

### availability.yml

- `MontryLaravelUnavailable` — Laravel `/internal/metrics` или blackbox probe
  nginx недоступны больше 2 минут.
- `MontryPostgresUnavailable` — Blackbox TCP probe не может подключиться к
  PostgreSQL.
- `MontryRedisUnavailable` — Blackbox TCP probe не может подключиться к Redis.
- `MontryInternalApi5xx` — internal API возвращает 5xx с устойчивой частотой.
- `MontryPublicEndpointDown` — Blackbox Exporter не может открыть публичный или
  public-local endpoint.
- `MontryPostgresBackupFailed` — последняя попытка PostgreSQL backup
  завершилась ошибкой.
- `MontryPostgresBackupStale` — нет успешного PostgreSQL backup дольше 25 часов.
- `MontryPostgresBackupVerificationFailed` — последний restore test backup
  завершился ошибкой.

Runbook: `docs/operations/runbooks/laravel-down.md` или
`docs/operations/backups.md`.

### poller.yml

- `MontryPollerUnavailable` — `/metrics` или `/health` poller недоступны больше
  2 минут.
- `MontryNoCheckResults` — есть включенные мониторы, но нет успешных poller jobs
  за 30 минут.
- `MontryPollerResultDeliveryFailures` — poller не может доставить результаты в
  Laravel.
- `MontryPollerQueueBufferHigh` — in-memory очередь poller заполнена больше чем
  на 80%.

Runbook: `docs/operations/runbooks/poller-no-results.md`.

### business.yml

- `MontryRegistrationFunnelBreakage` — пользователи отправляют email на
  регистрации, но регистрации не завершаются.
- `MontryCheckProcessingFailures` — Laravel пишет много `check.failed`
  business events.

Runbooks:

- `docs/operations/runbooks/email-failures.md`;
- `docs/operations/runbooks/poller-no-results.md`.

### billing.yml

- `MontryPaymentFailureRateHigh` — доля failed/canceled/expired payments больше
  30% среди сохраненных payment records.
- `MontryNoSuccessfulPaymentsAfterStarts` — платежи стартуют, но успешных
  оплат нет.

Runbook: `docs/operations/runbooks/payment-failures.md`.

### notifications.yml

- `MontryNotificationFailureRateHigh` — больше 20% email/Telegram доставок
  завершились ошибкой.
- `MontryNotificationQueueBacklog` — очередь `notifications` или `default`
  держится выше 100 jobs.
- `MontryFailedJobsPresent` — в Laravel `failed_jobs` есть записи.

Runbooks:

- `docs/operations/runbooks/email-failures.md`;
- `docs/operations/runbooks/queue-backlog.md`.

### deadletters.yml

- `MontryDeadLettersOpen` — есть хотя бы одна открытая dead-letter запись.
- `MontryRecoverableDeadLettersOpen` — recoverable dead-letter записи ждут retry
  больше 15 минут.

Runbook: `docs/operations/runbooks/dead-letters.md`.

## Проверка правил

Локальная проверка Prometheus config:

```bash
docker run --rm \
  --entrypoint promtool \
  -v "$PWD/docker/observability/prometheus:/etc/prometheus:ro" \
  prom/prometheus:v2.55.1 \
  check config /etc/prometheus/prometheus.yml
```

Проверка после запуска stack:

```bash
make observability-up
```

Открыть:

```text
http://localhost:9090/alerts
http://localhost:3000/alerting/list
```

## Ограничения MVP

- Alertmanager и реальные contact points пока не provisioned. В Epic 11 правила
  создаются для Prometheus/Grafana visibility и будущей маршрутизации.
- PostgreSQL и Redis проверяются через Blackbox TCP probe. Отдельные
  postgres-exporter и redis-exporter еще не добавлены.
- Business alerts используют агрегированные metrics и business events без
  `user_id`, `organization_id`, `monitor_id`, URL или доменов.
- Backup alerts используют node-exporter textfile collector. До первого запуска
  `make backup-postgres` будет активен stale alert, это ожидаемо.
