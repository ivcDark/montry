# Montri Poller

Go service for technical monitoring checks. Laravel remains the source of
business state: users, billing, monitor status, incidents and notifications.

Current state:

- app bootstrap loads config from env and creates the logger;
- `/health` HTTP endpoint is available;
- graceful shutdown handles `SIGINT` and `SIGTERM`;
- scheduler fetches due checks from Laravel;
- worker pool executes generic `CheckJob` values through registered checkers;
- results are published back to Laravel with retry/backoff;
- HTTP/HTTPS checker is implemented;
- SSL checker is implemented;
- Domain expiration checker is implemented.

## Structure

```text
cmd/poller          thin entrypoint
internal/app        config, logger, registry, worker pool, scheduler and lifecycle wiring
internal/config     env config
internal/logger     simple stdout logger
internal/checks     checker interface, registry and result contracts
internal/checks/httpcheck
                    HTTP/HTTPS checker
internal/checks/sslcheck
                    SSL certificate checker
internal/checks/domaincheck
                    Domain expiration checker
internal/jobs       generic job contracts
internal/runner     worker pool, workers, dispatcher and result publisher
internal/scheduler  scheduler placeholders
internal/laravel    Laravel client placeholders
internal/transport  HTTP transport
```

## Worker config

- `POLLER_WORKERS` - fixed worker goroutine count, default `10`.
- `POLLER_CHECK_TIMEOUT_SECONDS` - per-check timeout, default `10`.
- `POLLER_QUEUE_BUFFER` - planned jobs channel buffer size, default `100`.
- `LARAVEL_INTERNAL_API_URL` - Laravel internal API base URL.
- `LARAVEL_INTERNAL_API_TOKEN` - optional Bearer token for Laravel internal API.
- `LARAVEL_INTERNAL_API_TIMEOUT_SECONDS` - Laravel client timeout, default `10`.
- `POLLER_RESULT_RETRY_ATTEMPTS` - result submit attempts, default `3`.
- `POLLER_RESULT_RETRY_DELAY_SECONDS` - linear backoff base delay, default `1`.
- `POLLER_SCHEDULER_INTERVAL_SECONDS` - due checks polling interval, default `30`.
- `POLLER_FETCH_DUE_LIMIT` - max due checks per fetch, default `100`.
- `POLLER_MANUAL_API_TOKEN` - optional Bearer token for Laravel -> poller manual checks.
- `POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS` - manual endpoint request timeout, default `5`.

## Scheduler

The scheduler calls Laravel `GET /internal/monitors/due` on a timer and enqueues
returned `CheckJob` values into the shared worker pool jobs channel. It does not
inspect or branch on check type. If Laravel is unavailable, it logs the error and
continues on the next tick. If the jobs channel is full, it logs a warning and
drops that fetched job; Laravel remains the source of truth and can return it
again on a later due fetch.

## Lifecycle

`cmd/poller/main.go` only creates a signal-aware context and calls
`app.NewFromEnv()` plus `App.Run(ctx)`. The app bootstrap wires:

- env config;
- logger;
- checker registry with `http`, `ssl`, `domain`;
- Laravel HTTP client;
- shared jobs channel;
- result publisher;
- worker pool;
- scheduler;
- HTTP server with `/health` and `/internal/manual-checks`.

## HTTP checker

Type: `http`.

Supported settings:

- `method` - `GET` or `HEAD`, defaults to `GET`.
- `url` - optional URL override; otherwise `CheckJob.Target` is used.
- `follow_redirects` - defaults to `true`.
- `verify_ssl` - defaults to `true`.
- `headers` - optional string map.

Supported expected values:

- `status_codes` - allowed status codes, defaults to `[200]`.
- `max_response_time_ms` - warning threshold.

## SSL checker

Type: `ssl`.

Supported settings:

- `domain` - domain to connect to; otherwise `CheckJob.Target` is used.
- `port` - TLS port, defaults to `443`.
- `warning_days` - expiration warning thresholds, defaults to `[30, 14, 7, 3, 1]`.
- `server_name` - optional SNI/hostname override.
- `verify_ssl` - certificate chain verification flag, defaults to `true`.

Raw result includes certificate validity dates, `days_until_expiry`, issuer,
subject, DNS names and chain length. Go only returns the technical result;
Laravel decides whether to notify users or update incidents.

## Domain checker

Type: `domain`.

Supported settings:

- `domain` - domain name; otherwise `CheckJob.Target` is used.
- `warning_days` - expiration warning thresholds, defaults to `[30, 14, 7, 3, 1]`.

The MVP implementation uses WHOIS over port `43` and parses common expiration
fields for `.ru`, `.рф`/`.xn--p1ai`, `.com`, `.net` and `.org`. Parsing is kept
in separate functions so new TLD-specific rules can be added without changing
the runner or scheduler.

## Manual Checks

MVP manual flow is push-based:

1. Laravel validates user permissions and billing limits.
2. Laravel sends `POST /internal/manual-checks` to the poller.
3. Poller validates the payload and check type.
4. Poller enqueues the payload as `CheckJob` with `SourceManual`.
5. Worker pool executes the checker and publishes the result back to Laravel.

`POLLER_MANUAL_API_TOKEN` should match Laravel `POLLER_TOKEN` when auth is
enabled. Go does not check user permissions, billing limits, incidents or
notifications.

## Commands

Run locally:

```bash
go run ./cmd/poller
```

Run tests:

```bash
go test ./...
```

Run through Docker Compose:

```bash
make poller-run
make poller-logs
make poller-test
```
