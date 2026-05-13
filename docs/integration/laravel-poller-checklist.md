# Laravel and Go Poller Integration Checklist

Дата обновления: 2026-05-13.

Этот документ фиксирует, что Laravel должен реализовать для полноценной интеграции с Go poller. Go poller выполняет только технические проверки. Laravel остается источником бизнес-логики и состояния.

## 1. Required Endpoints

### Laravel endpoint: `GET /internal/monitors/due`

Назначение: отдать poller список плановых проверок, у которых `next_check_at <= now()`.

Checklist:

- [ ] Endpoint доступен только для internal clients.
- [ ] Поддерживает query `limit`.
- [ ] Возвращает только enabled monitors.
- [ ] Не возвращает monitors, которые paused/deleted/archived.
- [ ] Формирует generic payload без PHP class names.
- [ ] Для каждого задания генерирует стабильный `event_id`.
- [ ] Возвращает `timeout_ms`, `settings`, `expected`.
- [ ] Учитывает lease/idempotency, если несколько poller instances будут запущены позже.

### Laravel endpoint: `POST /internal/check-results`

Назначение: принять результат проверки от Go poller.

Checklist:

- [ ] Валидирует Bearer token или HMAC.
- [ ] Валидирует payload.
- [ ] Ищет monitor по `monitor_id`.
- [ ] Проверяет, что `check_type` соответствует monitor type.
- [ ] Обрабатывает повторную доставку по `event_id`.
- [ ] Сохраняет `check_results`.
- [ ] Обновляет monitor counters/status/last timestamps.
- [ ] Запускает incident resolver.
- [ ] Публикует Laravel domain events для notifications.

### Poller endpoint: `POST {POLLER_BASE_URL}/internal/manual-checks`

MVP-вариант: endpoint находится в Go poller, Laravel вызывает его после user-facing action `POST /monitors/{monitor}/check-now`.

Checklist на Laravel стороне:

- [ ] User-facing endpoint проверяет права пользователя.
- [ ] Billing/Application service проверяет тарифные лимиты.
- [ ] Laravel формирует `WorkerCheckPayload`.
- [ ] Laravel отправляет payload в poller через `MonitoringWorkerClientInterface`.
- [ ] `POLLER_BASE_URL` указывает на `http://poller:8090` внутри Docker.
- [ ] `POLLER_TOKEN` совпадает с `POLLER_MANUAL_API_TOKEN` в poller.
- [ ] При недоступности poller Laravel показывает понятную ошибку или ставит задачу в retry/outbox позже.

## 2. Due Check Payload

Response from Laravel:

```json
{
  "data": [
    {
      "id": "job-uuid",
      "event_id": "event-uuid",
      "event_type": "scheduled_check_due",
      "monitor_id": 1,
      "check_type": "http",
      "target": "https://example.ru",
      "settings": {
        "method": "GET",
        "follow_redirects": true,
        "verify_ssl": true
      },
      "expected": {
        "status_codes": [200],
        "max_response_time_ms": 5000
      },
      "timeout_ms": 5000,
      "requested_at": "2026-05-13T10:00:00+04:00"
    }
  ]
}
```

Required fields:

- `id` - job id, useful for future lease/outbox tracking.
- `event_id` - idempotency key for one check event.
- `event_type` - `scheduled_check_due` or `manual_check_requested`.
- `monitor_id` - Laravel monitor id.
- `check_type` - `http`, `ssl`, `domain`.
- `target` - URL/domain fallback for checker.
- `settings` - checker-specific settings.
- `expected` - checker-specific expectations.
- `timeout_ms` - per-check timeout.
- `requested_at` - RFC3339/ISO-8601 timestamp.

## 3. Check Result Payload

Request from Go poller to Laravel:

```json
{
  "event_id": "event-uuid",
  "monitor_id": "1",
  "check_type": "http",
  "status": "success",
  "checked_at": "2026-05-13T10:00:05Z",
  "duration_ms": 341,
  "result": {
    "status_code": 200,
    "response_time_ms": 341,
    "headers": {
      "server": "nginx"
    }
  },
  "error": null
}
```

Statuses:

- `success` - technical check passed.
- `warning` - target is reachable/valid but near threshold, for example slow HTTP response or expiring SSL/domain.
- `failed` - technical check failed or expected condition was not met.

Error format:

```json
{
  "code": "http_timeout",
  "message": "HTTP check timed out",
  "temporary": true
}
```

Laravel must not treat `temporary` as a business command. It is only technical context.

## 4. Auth

MVP recommendation: Bearer tokens.

Poller to Laravel:

```http
Authorization: Bearer <LARAVEL_INTERNAL_API_TOKEN>
```

Laravel to poller manual checks:

```http
Authorization: Bearer <POLLER_TOKEN>
```

Checklist:

- [ ] Store tokens in env only.
- [ ] Compare tokens with constant-time comparison where practical.
- [ ] Return `401` or `403` for invalid token.
- [ ] Do not log full token values.

Future option: HMAC signing with timestamp and nonce if replay protection becomes necessary.

## 5. Idempotency

Required identifiers:

- `event_id` - primary idempotency key for check result delivery.
- `id` or `job_id` - job/lease identifier for due fetch processing.
- `monitor_id` - business entity being checked.

Checklist:

- [ ] `check_results` should not duplicate the same `event_id`.
- [ ] Repeated `POST /internal/check-results` with the same `event_id` should return a safe success response.
- [ ] Laravel should not open duplicate incidents for the same result.
- [ ] Laravel should not send duplicate notifications for repeated delivery.
- [ ] Future multi-poller mode should add lease/claim semantics for due jobs.

## 6. Laravel State Updates

### `check_results`

Laravel should:

- [ ] Save raw technical result from poller.
- [ ] Save normalized result from Laravel `CheckTypeRegistry`.
- [ ] Store `status`, `checked_at`, `duration_ms`, `error`.
- [ ] Keep organization/monitor relations for query performance.

### `monitors`

Laravel should:

- [ ] Update `last_check_at`.
- [ ] Update `last_success_at` or `last_failure_at`.
- [ ] Update `consecutive_successes`.
- [ ] Update `consecutive_failures`.
- [ ] Calculate `next_check_at`.
- [ ] Resolve current monitor status through Laravel rules.

### `incidents`

Laravel should:

- [ ] Open incident only after confirmation rules, for example 2-3 consecutive failures.
- [ ] Close incident only after recovery confirmation.
- [ ] Avoid opening a new incident for every failed check.
- [ ] Store start/resolved timestamps and downtime duration.

### `notifications`

Laravel should:

- [ ] Listen to domain events from Monitoring/Incidents.
- [ ] Send notifications for incident opened/resolved.
- [ ] Send warning notifications for SSL/domain expiration according to rules.
- [ ] Avoid repeated spam for the same open incident or same warning threshold.

## 7. What Go Must Not Do

Go poller must not:

- create incidents;
- close incidents;
- send notifications;
- check user permissions;
- check tariffs or billing limits;
- directly update Laravel database;
- directly change monitor business status;
- depend on Laravel PHP classes;
- know Laravel Eloquent model structure.

Go poller may only:

- fetch `CheckJob` payloads;
- execute technical checks;
- return `CheckResult` payloads;
- expose a manual check intake endpoint for Laravel.

## 8. Laravel Tests Needed

Feature tests:

- [ ] `GET /internal/monitors/due` requires valid internal auth.
- [ ] `GET /internal/monitors/due` returns due enabled monitors.
- [ ] `GET /internal/monitors/due` excludes paused/deleted/not-due monitors.
- [ ] `GET /internal/monitors/due` respects `limit`.
- [ ] `POST /internal/check-results` requires valid internal auth.
- [ ] `POST /internal/check-results` validates payload.
- [ ] `POST /internal/check-results` saves `check_results`.
- [ ] `POST /internal/check-results` is idempotent by `event_id`.
- [ ] `POST /monitors/{monitor}/check-now` checks user access.
- [ ] `POST /monitors/{monitor}/check-now` checks billing/manual-check limits when billing is enabled.
- [ ] `POST /monitors/{monitor}/check-now` sends payload through `MonitoringWorkerClientInterface`.

Unit tests:

- [ ] `CheckTypeRegistry` normalizes HTTP worker results.
- [ ] `CheckTypeRegistry` normalizes SSL worker results.
- [ ] `CheckTypeRegistry` normalizes Domain worker results.
- [ ] Monitor status/counter resolver handles success, warning and failed.
- [ ] Incident resolver opens after configured consecutive failures.
- [ ] Incident resolver closes after configured recovery successes.
- [ ] Notification listeners do not send duplicate notifications.

Contract tests:

- [ ] Laravel `WorkerCheckPayload` matches Go `CheckJob` fields.
- [ ] Laravel `StoreCheckResultRequest` accepts Go `CheckResult` payload.
- [ ] Error codes from Go are accepted and stored.
- [ ] `monitor_id` accepts the agreed type consistently.

## 9. Local Verification

Before Laravel internal API is complete, use the Go mock server:

```bash
make poller-run-mock
```

When Laravel internal API is ready, run:

```bash
make poller-run
make poller-logs
```

Then verify:

- [ ] poller can resolve `http://nginx` in Docker network;
- [ ] scheduler calls `GET /internal/monitors/due`;
- [ ] worker pool executes checks;
- [ ] Laravel receives `POST /internal/check-results`;
- [ ] manual check button reaches poller `/internal/manual-checks`;
- [ ] result is stored and monitor state updates in Laravel.
