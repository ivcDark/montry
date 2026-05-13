# Local Deployment

Дата обновления: 2026-05-13.

## Docker Compose services

Local development uses `docker-compose.yml`.

Core services:

- `nginx` - Laravel HTTP entrypoint, available on `${APP_PORT:-8080}`.
- `web` - Laravel PHP application.
- `vite` - frontend dev server.
- `postgres` - PostgreSQL.
- `redis` - Redis.
- `poller` - Go poller service.

The Go poller is a single local service for MVP. Older specialized poller
services such as `poller-http`, `poller-ssl` and `poller-domain` are not needed
for the current architecture.

## Poller networking

The poller and Laravel containers share the `montri-net` Docker network.

Inside Docker, the poller reaches Laravel through:

```text
http://nginx
```

This value is configured through:

```env
LARAVEL_INTERNAL_API_URL=http://nginx
```

Manual checks from Laravel should use:

```env
POLLER_BASE_URL=http://poller:8090
POLLER_TOKEN=<same value as POLLER_MANUAL_API_TOKEN>
POLLER_MOCK=false
```

Go does not check user permissions, billing limits, incidents or notifications.
Laravel must do that before calling the poller.

## Poller env

Root `.env.example` contains the local Compose values. `apps/poller/.env.example`
contains the poller-only subset.

Important variables:

- `POLLER_WORKERS` - worker goroutine count.
- `POLLER_CHECK_TIMEOUT_SECONDS` - default per-check timeout.
- `POLLER_SCHEDULER_INTERVAL_SECONDS` - due checks polling interval.
- `POLLER_FETCH_DUE_LIMIT` - max due jobs fetched per request.
- `LARAVEL_INTERNAL_API_URL` - Laravel internal API base URL from poller.
- `LARAVEL_INTERNAL_API_TOKEN` - Bearer token for poller -> Laravel.
- `POLLER_MANUAL_API_TOKEN` - Bearer token for Laravel -> poller manual checks.

## Commands

Build the poller image:

```bash
make poller-build
```

Run the poller:

```bash
make poller-run
```

Run tests through Docker:

```bash
make poller-test
```

Open a shell:

```bash
make poller-shell
```

Show logs:

```bash
make poller-logs
```

Health check:

```bash
docker compose exec -T poller wget -qO- http://127.0.0.1:8090/health
```

Expected response:

```json
{"status":"ok"}
```

## Poller without Laravel internal API

When Laravel internal API endpoints are not ready, run the dev-only mock Laravel
server from the poller codebase:

```bash
make poller-run-mock
```

This starts:

- `mock-laravel` on `http://mock-laravel:8081` inside Docker;
- `poller-mock` with `LARAVEL_INTERNAL_API_URL=http://mock-laravel:8081`.

Optional root `.env` tuning:

```env
POLLER_SCHEDULER_INTERVAL_SECONDS=5
```

The mock server implements:

- `GET /internal/monitors/due` with three jobs: HTTP, SSL and Domain.
- `POST /internal/check-results`, logging received results to container logs.
- `GET /health`.

It is only for local development and is not part of the production flow.
