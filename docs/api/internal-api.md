# Internal API

Дата обновления: 2026-05-12.

Internal API используется только для связи Laravel с Go poller. Go выполняет технические проверки и возвращает raw result. Laravel сохраняет результат, обновляет состояние monitor и управляет incidents/notifications/billing.

Go poller не должен:

- открывать или закрывать incidents;
- отправлять уведомления пользователям;
- проверять тарифы и лимиты;
- обращаться напрямую к базе Laravel.

## Authentication

Для локального MVP `POLLER_INTERNAL_TOKEN` может быть пустым. Если token задан, Go должен отправлять:

```http
Authorization: Bearer <POLLER_INTERNAL_TOKEN>
```

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
5. Update monitor status/counters.
6. Emit Laravel event.
7. Incidents module opens/closes incidents according to Laravel rules.

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
