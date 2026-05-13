# Internal API

Дата обновления: 2026-05-12.

Internal API используется только для связи Laravel с Go poller. Go выполняет технические проверки и возвращает raw result. Laravel сохраняет результат, обновляет состояние monitor и управляет incidents/notifications/billing.

Go poller не должен:

- открывать или закрывать incidents;
- отправлять уведомления пользователям;
- проверять тарифы и лимиты;
- обращаться напрямую к базе Laravel.

## Authentication

Для локального MVP internal token может быть пустым. Если token задан, Go должен отправлять:

```http
Authorization: Bearer <LARAVEL_INTERNAL_API_TOKEN>
```

В Laravel это значение может соответствовать `POLLER_INTERNAL_TOKEN`, пока названия env не унифицированы.

## GET `/internal/monitors/due`

MVP endpoint для получения плановых проверок.

Request:

```http
GET /internal/monitors/due?limit=100
Authorization: Bearer <LARAVEL_INTERNAL_API_TOKEN>
Accept: application/json
```

Response:

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

Laravel flow:

1. Select enabled monitors where `next_check_at <= now()`.
2. Build stable worker payloads.
3. Return generic check jobs without exposing PHP classes.
4. Keep ownership of monitor scheduling and state transitions inside Laravel.

## POST poller `/internal/manual-checks`

MVP endpoint owned by the Go poller. Laravel uses it after the user clicks
“Check now”. Laravel must validate user permissions and billing limits before
calling this endpoint.

Request:

```http
POST {POLLER_BASE_URL}/internal/manual-checks
Authorization: Bearer <POLLER_TOKEN>
Content-Type: application/json
Accept: application/json
```

Payload:

```json
{
  "event_id": "uuid",
  "event_type": "manual_check_requested",
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
```

Response:

```json
{
  "accepted": true,
  "event_id": "uuid"
}
```

Statuses:

- `202` - job accepted into the poller queue;
- `400` - invalid JSON or missing required fields;
- `401` - invalid or missing Bearer token when `POLLER_MANUAL_API_TOKEN` is set;
- `422` - unknown `check_type`;
- `503` - manual jobs queue is full or unavailable;
- `408` - request context timed out before enqueue.

Poller flow:

1. Validate Bearer token if `POLLER_MANUAL_API_TOKEN` is set.
2. Validate generic payload fields.
3. Check `check_type` through the checker registry.
4. Convert payload to `CheckJob` with `source = manual`.
5. Enqueue into the shared bounded jobs channel.
6. Worker pool executes the check and posts result to Laravel `/internal/check-results`.

Go poller does not check tariffs, user permissions, incidents or notifications.

## POST `/internal/check-results`

Принимает результат выполненной проверки от Go poller.

Payload:

```json
{
  "event_id": "uuid",
  "monitor_id": 1,
  "check_type": "http",
  "status": "success",
  "checked_at": "2026-05-12T12:00:05+03:00",
  "duration_ms": 341,
  "result": {
    "status_code": 200,
    "response_time_ms": 341,
    "ip": "1.2.3.4",
    "headers": {
      "server": "nginx"
    }
  },
  "error": null
}
```

Response:

```json
{
  "id": 10,
  "status": "success"
}
```

Laravel flow:

1. Validate internal payload.
2. Resolve monitor by `monitor_id`.
3. Normalize worker result through `CheckTypeRegistry`.
4. Save `check_results`.
5. If `event_id` was already processed, return the existing saved result without creating a duplicate.
6. Update monitor status/counters and `next_check_at`.
7. Emit Laravel event.
8. Incidents module opens/closes incidents according to Laravel rules.

## POST `/monitors/{monitor}/check-now`

User-facing endpoint for manual checks from the dashboard.

Laravel flow:

1. Check authenticated user access to monitor organization.
2. Billing limits should be checked in Billing application service when billing is implemented.
3. Build `WorkerCheckPayload`.
4. Send payload through `MonitoringWorkerClientInterface`.
5. Go poller later posts result to `/internal/check-results`.

Current MVP note:

- `HttpMonitoringWorkerClient` is implemented for future Go integration.
- Default local binding uses `NullMonitoringWorkerClient` while `POLLER_MOCK=true` or `POLLER_BASE_URL` is empty.
