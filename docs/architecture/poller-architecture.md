# Go Poller Architecture

Дата обновления: 2026-05-22.

## 1. Цель Go poller

Go poller выполняет технические проверки мониторинга для Montry:

- HTTP/HTTPS availability;
- SSL certificate validity and expiration;
- domain expiration;
- будущие DNS, Ping, TCP Port, Keyword/content, Robots.txt, Sitemap, Redirect, RKN, Blacklist, PageSpeed, Malware checks.

Poller не является источником бизнес-состояния. Он получает задание, выполняет техническую проверку с timeout, формирует нормализованный технический результат и отправляет его в Laravel internal API.

Laravel остается источником бизнес-логики: пользователи, организации, тарифы, лимиты, состояние монитора, инциденты, уведомления и отчеты.

## 2. Границы ответственности Laravel и Go

Laravel отвечает за:

- создание и изменение monitors;
- проверку доступа пользователя;
- проверку тарифов и лимитов;
- хранение `monitors`, `check_results`, `incidents`, `notification_logs`;
- расчет статуса monitor;
- открытие и закрытие incidents;
- отправку уведомлений пользователям;
- подготовку payload для manual checks;
- выдачу списка due checks для планового poller;
- прием результатов через internal API.

Go poller отвечает только за:

- получение `CheckJob` из разрешенного источника;
- выбор checker по `type`;
- выполнение технической проверки;
- соблюдение `context.Context`, timeout и cancellation;
- ограничение concurrency через worker pool;
- retry/backoff сетевых операций;
- отправку результата в Laravel;
- graceful shutdown.

Go poller не должен:

- создавать, закрывать или менять incidents;
- отправлять Telegram/email/webhook/SMS уведомления пользователям;
- проверять тарифы;
- напрямую читать или менять БД Laravel;
- зависеть от PHP-классов Laravel;
- менять бизнес-статусы monitor в обход Laravel;
- держать отдельный runner на каждый check type;
- дублировать общий формат результата внутри каждого check package.

## 3. Общая схема работы poller

```text
Laravel due API / manual API / future Redis Streams
        |
        v
scheduler sources
        |
        v
jobs channel
        |
        v
dispatcher
        |
        v
worker pool
        |
        v
checker registry -> checker by type
        |
        v
CheckResult
        |
        v
result publisher
        |
        v
Laravel POST /internal/check-results
```

Scheduler и manual listener работают с generic `CheckJob`. Они не знают, как устроены HTTP, SSL, Domain или будущие проверки.

Worker знает только registry и общий интерфейс `Checker`. Детали проверки живут в отдельных пакетах: `internal/checks/httpcheck`, `internal/checks/sslcheck`, `internal/checks/domaincheck`.

## 4. Поток плановой проверки

MVP-поток через HTTP polling:

1. `scheduler.DueFetcher` по interval обращается в Laravel: `GET /internal/monitors/due`.
2. Laravel выбирает due monitors без active lease, резервирует их через `check_in_progress_until` и возвращает список как `CheckJob` payloads.
3. Scheduler валидирует минимальные технические поля: `event_id`, `monitor_id`, `check_type`, `target`, `settings`, `expected`.
4. Scheduler кладет задания в общий `jobs` channel.
5. Dispatcher передает задания в worker pool.
6. Worker получает checker через registry: `registry.Get(job.Type)`.
7. Worker создает child context с `job.Timeout`.
8. Checker выполняет техническую проверку.
9. Worker формирует `CheckResult` даже при ошибке проверки.
10. Result publisher отправляет результат в Laravel: `POST /internal/check-results`.
11. При временной ошибке отправки publisher делает retry/backoff.
12. Laravel сохраняет результат, очищает lease для совпавшего `event_id` и сам обновляет status/counters/incidents/notifications.

Важно: scheduler не должен вычислять `next_check_at`. Laravel выдает due checks и после приема результата решает, когда монитор должен проверяться дальше.

Laravel ставит короткий lease перед возвратом scheduled jobs:

- `last_check_event_id` хранит `event_id`, который ожидается от poller;
- `check_in_progress_until` блокирует повторную выдачу monitor до завершения проверки или истечения lease;
- длительность lease сейчас равна `timeout_ms + 60 секунд`;
- прием результата очищает lease только если входящий `event_id` совпадает с `last_check_event_id`;
- если poller не вернул результат, lease истечет и monitor снова попадет в `/internal/monitors/due`.

## 5. Поток ручной проверки

Текущий Laravel-код уже содержит `HttpMonitoringWorkerClient`, который отправляет manual payload в poller:

```http
POST {POLLER_BASE_URL}/internal/manual-checks
Authorization: Bearer <POLLER_TOKEN>
```

Payload совпадает с Laravel DTO `WorkerCheckPayload`:

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
  "requested_at": "2026-05-12T12:00:00+03:00"
}
```

Poller manual listener:

1. принимает `POST /internal/manual-checks`;
2. проверяет internal token, если он задан;
3. преобразует payload в `CheckJob` с `SourceManual`;
4. кладет job в общий `jobs` channel;
5. возвращает `202 Accepted`, если job принят в очередь poller;
6. выполнение дальше идет через тот же worker pool, что и плановые проверки.

Manual checks не должны иметь отдельную реализацию runner/checker. Отличается только источник задания и возможный приоритет очереди.

Laravel также ставит `check_in_progress_until` для manual checks до отправки payload в poller. Это дает UI состояние `checking` и не позволяет scheduled polling параллельно выдать тот же monitor, пока ручная проверка в работе.

## 6. Архитектура goroutines

В одном poller process запускаются ограниченные долгоживущие goroutines:

- main goroutine управляет lifecycle и signal handling;
- scheduler goroutine периодически получает due checks;
- manual HTTP server goroutine принимает ручные задачи;
- dispatcher goroutine читает общий jobs channel;
- N worker goroutines выполняют проверки;
- result publisher goroutine или ограниченная группа publisher workers отправляет результаты в Laravel;
- health server goroutine, если нужен простой `/health`.

Требования:

- все goroutines получают общий root `context.Context`;
- `SIGTERM` и `SIGINT` отменяют root context;
- новые jobs перестают приниматься при shutdown;
- worker pool завершает уже взятые jobs в пределах grace period;
- per-check timeout защищает от зависших DNS/TLS/HTTP/WHOIS операций;
- количество goroutines не растет на каждый incoming job без лимита;
- каналы имеют bounded capacity из config.

## 7. Архитектура worker pool

Worker pool содержит фиксированное количество workers:

- `POLLER_WORKERS` или `Config.Workers`;
- `POLLER_CHECK_TIMEOUT_SECONDS` или `Config.CheckTimeout`;
- `POLLER_QUEUE_BUFFER` или `Config.QueueBuffer`;
- отдельные лимиты для scheduled/manual возможны позже, но MVP использует один общий pool;
- backpressure достигается bounded `jobs` channel;
- если канал переполнен, manual endpoint возвращает `503` или `429`, а scheduler оставляет due checks для следующего fetch;
- worker не знает источник задания, кроме `job.Source` для логов и метрик.

Пример потока:

```text
due fetcher ---> jobs channel
manual API  ---> jobs channel
                    |
                    v
               dispatcher
                    |
                    v
          worker 1..N with registry
                    |
                    v
              results channel
                    |
                    v
          Laravel result publisher
```

Ошибки отправки результатов:

- retry только для временных сетевых ошибок, 429 и 5xx;
- не retry для 400/401/403/404, кроме явно согласованных случаев;
- backoff между попытками;
- максимум попыток задается config;
- `event_id` используется для идемпотентности на стороне Laravel;
- если все retry исчерпаны, результат логируется как delivery failure и должен быть доступен для будущего outbox/dead-letter механизма.

MVP implementation:

- `runner.LaravelResultPublisher` вызывает `LaravelClient.SubmitCheckResult`;
- retry attempts задаются через `POLLER_RESULT_RETRY_ATTEMPTS`;
- базовая задержка задается через `POLLER_RESULT_RETRY_DELAY_SECONDS`;
- backoff сейчас линейный: `delay * attempt`;
- disk persistence не используется.

Future reliability step:

- добавить lightweight outbox/local queue для результатов, которые не удалось отправить после всех retry;
- затем повторно отправлять такие результаты отдельным publisher loop;
- Laravel должен сохранять идемпотентность по `event_id`, чтобы повторная доставка не создавала дубликаты.

## 8. Архитектура scheduler

Scheduler состоит из generic компонентов:

- `DueFetcher` получает due jobs из Laravel или будущего stream source;
- `ManualListener` принимает manual jobs;
- `Scheduler` запускает источники и передает jobs в runner;
- scheduler не импортирует `httpcheck`, `sslcheck`, `domaincheck`;
- scheduler не содержит switch по check type.

MVP scheduler config:

- `POLLER_SCHEDULER_INTERVAL_SECONDS` - interval for `GET /internal/monitors/due`;
- `POLLER_FETCH_DUE_LIMIT` - max jobs per fetch;
- jobs are sent into the shared bounded jobs channel;
- if Laravel API fails, the scheduler logs the error and continues on the next tick;
- if the jobs channel is full, the scheduler logs a warning and does not spawn extra goroutines.

Для MVP рекомендуется HTTP polling, потому что:

- Laravel уже является источником состояния и может безопасно выбрать due monitors;
- проще отлаживать в Docker Compose;
- не нужно вводить надежный broker contract раньше MVP;
- текущий Laravel WorkerGateway уже использует HTTP для manual checks и result callback.

Redis Streams стоит добавить позже, когда потребуется более надежная доставка и горизонтальное масштабирование:

- Laravel публикует manual/scheduled events в stream;
- poller читает consumer group;
- ack делается после успешной отправки результата в Laravel или после сохранения delivery failure;
- contracts `CheckJob` и `CheckResult` остаются теми же.

## 9. Архитектура checker registry

Registry хранит соответствие `check_type -> Checker`.

Правила:

- каждый checker регистрируется при сборке приложения в `internal/app`;
- дублирование type запрещено;
- неизвестный type возвращает structured error;
- scheduler и worker pool зависят от interface, а не от конкретных packages;
- добавление нового типа не требует изменения runner/scheduler.

Пример:

```go
registry := checks.NewRegistry()
registry.Register(httpcheck.New())
registry.Register(sslcheck.New())
registry.Register(domaincheck.New())
```

## 10. Интерфейс Checker

```go
type Checker interface {
    Type() string
    Check(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error)
}
```

Контракт:

- `Type()` возвращает стабильный код: `http`, `ssl`, `domain`;
- `Check()` уважает `ctx.Done()`;
- checker не отправляет результат в Laravel;
- checker не открывает incidents;
- checker не отправляет notifications;
- checker возвращает технические raw/normalized поля;
- checker возвращает structured error для технической ошибки проверки.

## 11. Общий формат CheckJob

```go
type CheckJob struct {
    ID          string
    EventID     string
    MonitorID   string
    Type        string
    Target      string
    Settings    map[string]any
    Expected    map[string]any
    Timeout     time.Duration
    RequestedAt time.Time
    Source      JobSource
}
```

`ID` - внутренний идентификатор job внутри poller. `EventID` - внешний идемпотентный идентификатор от Laravel или generated event id для scheduled checks.

`Source`:

```go
type JobSource string

const (
    SourceScheduled JobSource = "scheduled"
    SourceManual    JobSource = "manual"
)
```

## 12. Общий формат CheckResult

```go
type CheckResult struct {
    EventID   string
    MonitorID string
    Type      string
    Status    string
    CheckedAt time.Time
    Duration  time.Duration
    Raw       map[string]any
    Error     *CheckError
}
```

Статусы poller должны описывать технический результат:

- `success` - проверка технически выполнена и соответствует базовой проверке checker;
- `failure` - техническая проверка выполнена, но цель недоступна, сертификат невалиден, домен не получен и т.п.;
- `error` - poller не смог корректно выполнить проверку из-за timeout, DNS/client/internal error.

Laravel может дополнительно нормализовать result через свой `CheckTypeRegistry` и решать, как это влияет на monitor state.

Payload в Laravel:

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

Для HTTP `result` должен использовать контракт:

```json
{
  "status_code": 200,
  "response_time_ms": 341,
  "ip": "1.2.3.4",
  "headers": {
    "server": "nginx"
  }
}
```

Для SSL `result` должен использовать контракт, который Laravel нормализует без
переименований:

```json
{
  "valid": true,
  "issued_at": "2026-01-01T00:00:00Z",
  "expires_at": "2026-06-01T00:00:00Z",
  "days_until_expiration": 19,
  "issuer": "CN=Test Issuer",
  "subject": "CN=example.com",
  "serial_number": "123",
  "dns_names": ["example.com", "www.example.com"],
  "chain_length": 2
}
```

Для Domain `result` должен использовать контракт:

```json
{
  "registered": true,
  "domain": "example.com",
  "expires_at": "2026-08-13T04:00:00Z",
  "days_until_expiration": 92,
  "registrar": "Example Registrar, Inc."
}
```

## 13. Общий формат ошибок проверки

```go
type CheckError struct {
    Code      string
    Message   string
    Temporary bool
    Details   map[string]any
}
```

Примеры `Code`:

- `timeout`;
- `dns_error`;
- `connection_refused`;
- `tls_error`;
- `http_error`;
- `whois_error`;
- `invalid_settings`;
- `unsupported_check_type`;
- `internal_error`.

`Temporary` помогает решить, стоит ли retry на уровне технической операции. Laravel не должен использовать это поле как прямую бизнес-команду для incidents.

## 14. HTTP-контракты с Laravel

### Laravel -> poller: ручная проверка

MVP endpoint poller:

```http
POST /internal/manual-checks
Authorization: Bearer <POLLER_TOKEN>
Content-Type: application/json
```

Response:

```json
{
  "accepted": true,
  "event_id": "uuid"
}
```

HTTP statuses:

- `202` - job принят;
- `400` - invalid payload;
- `401` или `403` - invalid token;
- `429` или `503` - queue is full / poller shutting down.

### poller -> Laravel: результат проверки

Существующий Laravel endpoint:

```http
POST /internal/check-results
Authorization: Bearer <POLLER_INTERNAL_TOKEN>
Content-Type: application/json
```

Laravel response:

```json
{
  "id": 10,
  "status": "success"
}
```

### poller -> Laravel: плановые due checks

Рекомендуемый MVP endpoint:

```http
GET /internal/monitors/due?limit=100
Authorization: Bearer <POLLER_INTERNAL_TOKEN>
Accept: application/json
```

Response:

```json
{
  "data": [
    {
      "event_id": "uuid",
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

Laravel должен обеспечивать идемпотентность по `event_id` или по отдельному lease/claim механизму, когда появится горизонтальное масштабирование poller.

## 15. Как добавлять новый тип мониторинга

1. Добавить пакет `apps/poller/internal/checks/<type>check`.
2. Реализовать `checks.Checker`.
3. Добавить unit tests для checker.
4. Зарегистрировать checker в `internal/app`.
5. Добавить Laravel `CheckTypeDefinition` в `apps/web/app/Modules/CheckTypes`, если типа еще нет.
6. Обновить Laravel validation/settings/expected normalization.
7. Убедиться, что result payload остается совместимым с `/internal/check-results`.
8. Добавить документацию в `docs/api/internal-api.md`, если меняется contract.

Runner, scheduler и worker pool не должны меняться при добавлении нового типа, кроме регистрации checker.

## 16. Целевая структура apps/poller

```text
apps/poller/
├── cmd/
│   └── poller/
│       └── main.go
├── internal/
│   ├── app/
│   │   ├── app.go
│   │   └── lifecycle.go
│   ├── config/
│   │   └── config.go
│   ├── logger/
│   │   └── logger.go
│   ├── checks/
│   │   ├── checker.go
│   │   ├── registry.go
│   │   ├── result.go
│   │   ├── errors.go
│   │   ├── httpcheck/
│   │   ├── sslcheck/
│   │   └── domaincheck/
│   ├── jobs/
│   │   ├── job.go
│   │   ├── source.go
│   │   └── result.go
│   ├── scheduler/
│   │   ├── scheduler.go
│   │   ├── due_fetcher.go
│   │   └── manual_listener.go
│   ├── runner/
│   │   ├── dispatcher.go
│   │   ├── worker_pool.go
│   │   └── worker.go
│   ├── laravel/
│   │   ├── client.go
│   │   ├── dto.go
│   │   └── signer.go
│   ├── transport/
│   │   ├── http/
│   │   └── redis/
│   └── observability/
│       ├── metrics.go
│       └── health.go
├── pkg/
├── tests/
├── Dockerfile
├── go.mod
└── go.sum
```

### Назначение пакетов

- `cmd/poller` - entrypoint, signal handling, запуск app lifecycle.
- `internal/app` - сборка зависимостей: env config, logger, registry, Laravel client, result publisher, shared jobs channel, scheduler, worker pool and HTTP transport.
- `internal/config` - env parsing, defaults, validation.
- `internal/logger` - thin wrapper над стандартным logger или structured logger.
- `internal/checks` - общие contracts и registry.
- `internal/checks/httpcheck` - HTTP/HTTPS checker: `GET`/`HEAD`, redirects, TLS verify flag, response time, status code and basic headers.
- `internal/checks/sslcheck` - SSL checker: TLS dial, certificate validity, expiry, hostname match, issuer/subject/DNS names and chain length.
- `internal/checks/domaincheck` - domain expiration checker: WHOIS lookup, TLD-specific expiration parsing, expiry status and warning thresholds.
- `internal/jobs` - общий формат job/source/result для scheduler/runner.
- `internal/scheduler` - источники задач и polling loop.
- `internal/runner` - dispatcher, worker pool, worker.
- `internal/laravel` - HTTP client к Laravel internal API и DTO.
- `internal/transport/http` - HTTP server для manual checks и health endpoints.
- `internal/transport/redis` - будущий Redis Streams adapter.
- `internal/observability` - lightweight health/metrics без сложной инфраструктуры.

## 17. Текущий код и mapping на целевую архитектуру

На 2026-05-13 в `apps/poller` добавлен базовый runnable каркас:

- `cmd/poller/main.go` - entrypoint;
- `internal/config` - env config;
- `internal/logger` - простой stdout logger;
- `internal/app` - lifecycle и graceful shutdown;
- `internal/transport/http` - минимальный `/health`;
- `internal/checks` и `internal/jobs` - общие contracts;
- `internal/runner`, `internal/scheduler`, `internal/laravel` - placeholders под следующие этапы.

Реальные HTTP/SSL/Domain проверки пока не реализованы.

Mapping текущих файлов:

| Текущий файл | Целевая роль |
| --- | --- |
| `apps/poller/AGENTS.md` | Оставить как локальные инструкции |
| `apps/poller/README.md` | Краткое описание текущего runnable каркаса |
| `apps/poller/Dockerfile` | Dockerfile для dev/prod сборки poller |
| `docker/go/Dockerfile` | Старый общий Go Dockerfile; не используется новым `poller` service |
| `docker-compose.yml` service `poller` | Минимальный сервис для запуска базового poller |
| Закомментированные legacy poller services в `docker-compose.yml` | Пока не используются; будущий app может поддержать mode/config без отдельного runner per type |

Поэтапный рефакторинг:

1. Добавить contracts: `checks.Checker`, `checks.Registry`, `jobs.CheckJob`, `checks.CheckResult`.
2. Добавить lifecycle/config/logger skeleton.
3. Добавить worker pool с tests.
4. Добавить manual HTTP endpoint `/internal/manual-checks`.
5. Добавить Laravel client для `POST /internal/check-results`.
6. Добавить HTTP due fetcher для `GET /internal/monitors/due`.
7. Реализовать `httpcheck`.
8. Реализовать `sslcheck`.
9. Реализовать `domaincheck`.
10. После стабилизации MVP рассмотреть Redis Streams adapter.

## 18. Какие тесты нужны

Unit tests:

- registry registers checker by type;
- registry rejects duplicate type;
- registry returns error for unknown type;
- worker applies per-check timeout;
- worker maps checker errors to `CheckResult.Error`;
- worker pool respects concurrency;
- HTTP checker handles success, non-2xx, redirects, timeout, DNS error;
- SSL checker handles valid cert, expiring cert, invalid cert, timeout;
- domain checker handles expiration date, missing WHOIS data, timeout;
- Laravel client sends expected payload and auth header;
- retry/backoff retries temporary network errors and stops on permanent 4xx.

Integration tests:

- manual endpoint accepts valid payload and enqueues job;
- scheduler fetches due checks and enqueues jobs;
- worker executes checker and publisher posts result to fake Laravel server;
- graceful shutdown stops sources and drains in-flight jobs within grace period.

Contract tests:

- Go DTO for manual check matches Laravel `WorkerCheckPayload`;
- Go result payload matches Laravel `StoreCheckResultRequest`;
- `event_id`, `monitor_id`, `check_type`, `status`, `checked_at`, `duration_ms`, `result`, `error` stay compatible.

## 19. Что нельзя делать в poller

- Нельзя напрямую менять таблицы Laravel.
- Нельзя открывать или закрывать incidents.
- Нельзя отправлять уведомления пользователям.
- Нельзя проверять тарифы и лимиты.
- Нельзя импортировать или генерировать зависимости от PHP-классов.
- Нельзя делать scheduler с `switch check_type`.
- Нельзя создавать отдельный runner для каждого типа проверки.
- Нельзя запускать goroutine на каждую проверку без общего лимита.
- Нельзя выполнять сетевую проверку без `context.Context` и timeout.
- Нельзя дублировать common result format в каждом checker package.
- Нельзя добавлять Kubernetes, Prometheus или сложную observability-инфраструктуру для MVP.
- Нельзя менять Docker-конфиги в рамках этого архитектурного этапа.
