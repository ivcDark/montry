# Sentry Exception Tracking

Дата обновления: 2026-05-27.

## Назначение

Sentry используется только для exception tracking. Product analytics, business
events, audit logs и dead letters остаются в PostgreSQL, ClickHouse, Prometheus
и Grafana.

## Установленные SDK

Laravel:

```text
sentry/sentry-laravel
```

Go poller:

```text
github.com/getsentry/sentry-go
```

Для poller закреплена версия, совместимая с Go 1.22.

## Laravel

Конфигурация:

```text
apps/web/config/sentry.php
```

Основные переменные в `apps/web/.env`:

```text
SENTRY_DSN=
SENTRY_LARAVEL_DSN=
SENTRY_ENVIRONMENT=local
SENTRY_RELEASE=
SENTRY_SAMPLE_RATE=1.0
SENTRY_TRACES_SAMPLE_RATE=
SENTRY_ENABLE_LOGS=false
```

Laravel подключает официальный handler:

```php
Sentry\Laravel\Integration::handles($exceptions)
```

`SentryContextMiddleware` добавляет safe context:

- `service=laravel`;
- `correlation_id`;
- route name/path;
- safe user id.

`send_default_pii=false`, SQL bindings, request bodies and high-cardinality
payloads are not enabled.

Проверка:

```bash
make artisan cmd="observability:test-sentry"
```

Официальная команда пакета также доступна:

```bash
docker compose exec web php artisan sentry:test
```

## Go poller

Переменные в root `.env`:

```text
SENTRY_ENABLED=false
SENTRY_DSN=
SENTRY_POLLER_DSN=
SENTRY_ENVIRONMENT=local
SENTRY_RELEASE=
SENTRY_FLUSH_TIMEOUT=2s
```

Если `SENTRY_POLLER_DSN` задан, poller использует его. Иначе используется общий
`SENTRY_DSN`. При пустом DSN reporter становится no-op.

Poller отправляет:

- top-level service errors;
- due-fetch errors;
- checker errors;
- checker panics.

Safe tags:

- `service=poller`;
- `event`;
- `check_type`;
- `job_source`;
- `correlation_id`.

Extra fields ограничены техническими ids (`event_id`, `monitor_id`,
`worker_id`) и не содержат target URL/domain, tokens, authorization headers,
request payloads или secrets.

## Release Naming

Рекомендуемый формат:

```text
montry@<git-sha>
```

Для production deploy задавать одинаковый `SENTRY_RELEASE` в Laravel и poller,
чтобы ошибки связывались с одной версией релиза.

## Environments

Рекомендуемые значения:

- `local`
- `staging`
- `production`

`SENTRY_ENVIRONMENT` должен совпадать между Laravel и poller в одном окружении.

