# Montry Poller

Poller - это Go-сервис, который выполняет технические проверки мониторинга и
отправляет результаты обратно в Laravel.

Laravel остается источником бизнес-состояния: пользователи, тарифы, статусы
мониторов, инциденты, уведомления и лимиты живут в Laravel. Poller только
получает задания, выполняет проверки и возвращает технический результат.

## Текущее состояние

- сервис загружает конфигурацию из env-переменных;
- есть HTTP endpoint `/health`;
- есть graceful shutdown по `SIGINT` и `SIGTERM`;
- scheduler забирает из Laravel мониторы, которым пора выполниться;
- worker pool выполняет проверки через зарегистрированные checker-ы;
- результаты отправляются обратно в Laravel с retry/backoff;
- реализованы HTTP/HTTPS проверки;
- реализованы SSL проверки;
- реализованы проверки срока действия домена;
- реализованы ручные проверки через `POST /internal/manual-checks`.

## Структура

```text
cmd/poller          точка входа
internal/app        сборка приложения, lifecycle, registry, scheduler, worker pool
internal/config     конфигурация из env
internal/logger     простой stdout logger
internal/checks     интерфейсы checker-ов и общие контракты результата
internal/checks/httpcheck
                    HTTP/HTTPS checker
internal/checks/sslcheck
                    SSL checker
internal/checks/domaincheck
                    Domain expiration checker
internal/jobs       общие контракты заданий
internal/runner     worker pool, workers, dispatcher, публикация результатов
internal/scheduler  получение due-мониторов из Laravel
internal/laravel    HTTP client для Laravel internal API
internal/transport  HTTP transport poller-а
```

## Как работает scheduler

Scheduler по таймеру вызывает Laravel endpoint:

```text
GET /internal/monitors/due
```

Laravel возвращает включенные мониторы, у которых `next_check_at` пустой или уже
наступил. Poller преобразует ответ в `CheckJob` и кладет задания в общий worker
pool.

Scheduler не знает деталей HTTP, SSL или domain проверок. Он работает только с
универсальным заданием. Конкретный checker выбирается через registry по типу
монитора.

Если Laravel временно недоступен, poller логирует ошибку и повторяет попытку на
следующем тике. Если очередь заданий заполнена, задание пропускается; Laravel
остается источником правды и сможет вернуть этот монитор повторно при следующем
запросе.

## Как сохраняются результаты

После выполнения проверки poller отправляет результат в Laravel:

```text
POST /internal/check-results
```

Laravel:

- сохраняет запись в `check_results`;
- обновляет статус монитора;
- обновляет `last_check_at` и `next_check_at`;
- обновляет счетчики успешных и неуспешных проверок;
- решает, нужно ли открывать или закрывать инциденты;
- решает, нужно ли отправлять уведомления.

Poller не открывает инциденты, не отправляет уведомления и не проверяет тарифные
ограничения.

## Ручные проверки

Ручная проверка работает в обратную сторону:

1. Пользователь нажимает кнопку проверки в интерфейсе.
2. Laravel проверяет права пользователя и тарифные ограничения.
3. Laravel отправляет задание в poller:

```text
POST /internal/manual-checks
```

4. Poller проверяет payload и тип проверки.
5. Poller кладет задание в worker pool как `SourceManual`.
6. Worker выполняет проверку.
7. Poller отправляет результат обратно в Laravel через `/internal/check-results`.

`POLLER_MANUAL_API_TOKEN` в poller должен совпадать с `POLLER_TOKEN` в Laravel,
если авторизация ручных проверок включена.

## Конфигурация poller

Основные переменные окружения:

- `APP_ENV` - окружение приложения, например `local` или `production`.
- `POLLER_HTTP_ADDR` - адрес HTTP-сервера poller-а, по умолчанию `:8090`.
- `POLLER_WORKERS` - количество worker goroutine, по умолчанию `10`.
- `POLLER_CHECK_TIMEOUT_SECONDS` - timeout одной проверки, по умолчанию `10`.
- `POLLER_QUEUE_BUFFER` - размер буфера очереди заданий, по умолчанию `100`.
- `LARAVEL_INTERNAL_API_URL` - base URL Laravel internal API.
- `LARAVEL_INTERNAL_API_TOKEN` - Bearer token для запросов poller -> Laravel.
- `LARAVEL_INTERNAL_API_TIMEOUT_SECONDS` - timeout HTTP-клиента Laravel, по умолчанию `10`.
- `POLLER_RESULT_RETRY_ATTEMPTS` - количество попыток отправки результата, по умолчанию `3`.
- `POLLER_RESULT_RETRY_DELAY_SECONDS` - базовая задержка retry, по умолчанию `1`.
- `POLLER_SCHEDULER_INTERVAL_SECONDS` - интервал запроса due-мониторов, по умолчанию `30`.
- `POLLER_FETCH_DUE_LIMIT` - максимум due-мониторов за один запрос, по умолчанию `100`.
- `POLLER_MANUAL_API_TOKEN` - Bearer token для запросов Laravel -> poller.
- `POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS` - timeout ручного endpoint-а, по умолчанию `5`.
- `POLLER_SHUTDOWN_TIMEOUT` - timeout graceful shutdown, по умолчанию `10s`.

Для production `LARAVEL_INTERNAL_API_URL`, `LARAVEL_INTERNAL_API_TOKEN` и
`POLLER_MANUAL_API_TOKEN` должны быть заданы явно.

## Связанные переменные Laravel

В `apps/web/.env` должны быть настроены переменные для связи Laravel с poller:

```env
POLLER_BASE_URL=http://127.0.0.1:8090
POLLER_TOKEN=change-me-manual-token
POLLER_INTERNAL_TOKEN=change-me-internal-token
POLLER_MOCK=false
```

Соответствие токенов:

```text
Laravel POLLER_TOKEN           = Poller POLLER_MANUAL_API_TOKEN
Laravel POLLER_INTERNAL_TOKEN  = Poller LARAVEL_INTERNAL_API_TOKEN
```

`POLLER_BASE_URL` нужен Laravel для ручных проверок. Если Laravel и poller
работают на одном сервере, лучше держать poller на `127.0.0.1:8090` и не
открывать этот порт наружу.

## HTTP checker

Тип: `http`.

Поддерживаемые settings:

- `method` - `GET` или `HEAD`, по умолчанию `GET`.
- `url` - опциональный URL; если не задан, используется `CheckJob.Target`.
- `follow_redirects` - следовать редиректам, по умолчанию `true`.
- `verify_ssl` - проверять SSL-сертификат, по умолчанию `true`.
- `headers` - опциональная string map с HTTP-заголовками.

Поддерживаемые expected:

- `status_codes` - допустимые HTTP-статусы, по умолчанию `[200]`.
- `max_response_time_ms` - порог времени ответа для warning.

Raw result:

- `status_code`
- `response_time_ms`
- `ip`
- `headers`

## SSL checker

Тип: `ssl`.

Поддерживаемые settings:

- `domain` - домен для подключения; если не задан, используется `CheckJob.Target`.
- `port` - TLS-порт, по умолчанию `443`.
- `warning_days` - пороги предупреждения об истечении сертификата, по умолчанию `[30, 14, 7, 3, 1]`.
- `server_name` - опциональный SNI/hostname override.
- `verify_ssl` - проверять цепочку сертификата, по умолчанию `true`.

Raw result:

- `valid`
- `issued_at`
- `expires_at`
- `days_until_expiration`
- `issuer`
- `subject`
- `serial_number`
- `dns_names`
- `chain_length`

Go возвращает только технический результат. Laravel решает, является ли это
проблемой, нужно ли менять статус монитора и отправлять уведомления.

## Domain checker

Тип: `domain`.

Поддерживаемые settings:

- `domain` - доменное имя; если не задано, используется `CheckJob.Target`.
- `warning_days` - пороги предупреждения об истечении домена, по умолчанию `[30, 14, 7, 3, 1]`.

MVP-реализация использует WHOIS через порт `43` и парсит распространенные поля
даты истечения для `.ru`, `.рф`/`.xn--p1ai`, `.com`, `.net` и `.org`.

Raw result:

- `registered`
- `domain`
- `expires_at`
- `days_until_expiration`
- `registrar`

Для работы domain checker-а на сервере должен быть разрешен исходящий доступ к
WHOIS-серверам по порту `43`.

## Локальный запуск

Из директории `apps/poller`:

```bash
go run ./cmd/poller
```

Тесты:

```bash
go test ./...
```

Через Docker Compose из корня репозитория:

```bash
make poller-run
make poller-logs
make poller-test
```

Локально через Docker poller обычно использует Laravel URL внутри compose-сети:

```env
LARAVEL_INTERNAL_API_URL=http://nginx
```

## Запуск на сервере без Docker

Для production без Docker poller лучше запускать как обычный systemd-сервис.

Пример сборки на сервере:

```bash
cd /var/www/montry/apps/poller
go test ./...
go build -trimpath -ldflags="-s -w" -o /opt/montry/bin/montry-poller ./cmd/poller
```

Пример env-файла `/etc/montry/poller.env`:

```env
APP_ENV=production
POLLER_HTTP_ADDR=127.0.0.1:8090

LARAVEL_INTERNAL_API_URL=https://example.ru
LARAVEL_INTERNAL_API_TOKEN=change-me-internal-token
LARAVEL_INTERNAL_API_TIMEOUT_SECONDS=10

POLLER_MANUAL_API_TOKEN=change-me-manual-token
POLLER_WORKERS=10
POLLER_CHECK_TIMEOUT_SECONDS=10
POLLER_QUEUE_BUFFER=100
POLLER_RESULT_RETRY_ATTEMPTS=3
POLLER_RESULT_RETRY_DELAY_SECONDS=1
POLLER_SCHEDULER_INTERVAL_SECONDS=30
POLLER_FETCH_DUE_LIMIT=100
POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS=5
POLLER_SHUTDOWN_TIMEOUT=10s
```

Пример systemd unit `/etc/systemd/system/montry-poller.service`:

```ini
[Unit]
Description=Montry Poller
After=network-online.target
Wants=network-online.target

[Service]
User=montry
Group=montry
WorkingDirectory=/var/www/montry/apps/poller
EnvironmentFile=/etc/montry/poller.env
ExecStart=/opt/montry/bin/montry-poller
Restart=always
RestartSec=5
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
```

Команды обслуживания:

```bash
sudo systemctl daemon-reload
sudo systemctl enable montry-poller
sudo systemctl start montry-poller
sudo systemctl status montry-poller
sudo journalctl -u montry-poller -f
```

После `git pull` и новой сборки бинарника:

```bash
sudo systemctl restart montry-poller
```

## Production checklist

- Go установлен на сервере или бинарник собирается в CI/CD.
- Laravel `.env` содержит `POLLER_BASE_URL`, `POLLER_TOKEN`, `POLLER_INTERNAL_TOKEN`, `POLLER_MOCK=false`.
- Poller env содержит `LARAVEL_INTERNAL_API_URL`, `LARAVEL_INTERNAL_API_TOKEN`, `POLLER_MANUAL_API_TOKEN`.
- `POLLER_TOKEN` совпадает с `POLLER_MANUAL_API_TOKEN`.
- `POLLER_INTERNAL_TOKEN` совпадает с `LARAVEL_INTERNAL_API_TOKEN`.
- Poller слушает `127.0.0.1:8090`, если находится на том же сервере, что и Laravel.
- Порт poller-а не открыт публично без необходимости.
- Сервер имеет исходящий доступ к HTTP/HTTPS целям мониторинга.
- Сервер имеет исходящий доступ к WHOIS по порту `43`, если включены domain checks.
- В Laravel запущены необходимые фоновые процессы для очередей и планировщика, если уведомления или другие обработчики работают асинхронно.

## Ограничения текущего MVP

- Endpoint `/internal/monitors/due` сейчас отдает due-мониторы без отдельной
  операции резервирования. Для одного poller-процесса это приемлемо на MVP, но
  перед запуском нескольких poller-ов нужно добавить leasing/reservation, чтобы
  один и тот же монитор не выполнялся параллельно несколько раз.
- Domain checker использует простой WHOIS-парсер. Для новых TLD могут
  потребоваться отдельные правила парсинга.
- Poller не хранит очередь заданий между перезапусками. Если процесс
  перезапустился, Laravel снова вернет due-мониторы при следующем запросе.
