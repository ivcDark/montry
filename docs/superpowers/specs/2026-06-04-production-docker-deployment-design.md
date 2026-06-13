# Production Docker Deployment Design

## Цель

Подготовить изолированный production Docker Compose и Makefile-команды для
первого развертывания и последующих обновлений Montry на одном сервере.

Production-конфигурация не должна наследовать dev bind mounts, Vite dev server,
открытые инфраструктурные порты или observability-сервисы из
`docker-compose.yml`.

## Production-сервисы

Постоянно работающий базовый набор:

- `nginx` — единственная публичная HTTP-точка входа;
- `web` — Laravel через PHP-FPM;
- `postgres` — основная база данных;
- `redis` — сессии, кеш и Laravel-очереди;
- `poller` — Go-сервис выполнения проверок;
- `web-scheduler` — Laravel `php artisan schedule:work`.

`web-scheduler` использует тот же production-образ, что и `web`. Он необходим
для периодических задач биллинга, дайджестов и других команд Laravel.

`node` определяется в production Compose как одноразовый build-сервис. Он
запускается только командами развертывания и обновления, выполняет
`npm ci && npm run build`, записывает результат в `apps/web/public/build` и
завершается. Отсутствие работающего контейнера `node` после успешного деплоя
является нормальным состоянием.

Отдельный Laravel queue worker пока не входит в production-набор: в текущем
коде нет задач, реализующих `ShouldQueue`. При появлении таких задач необходимо
добавить отдельный сервис `web-queue` на том же образе `web`.

## Файлы

- `docker-compose.prod.yml` — самостоятельная production-конфигурация;
- `docker/php/Dockerfile` — production-образ Laravel;
- `docker/nginx/Dockerfile` — production-образ Nginx со статическими файлами;
- `docker/nginx/prod.conf` — production-конфигурация Nginx;
- `.env.production.example` — только переменные Docker Compose;
- `apps/web/.env.production.example` — Laravel production env;
- `apps/poller/.env.production.example` — Go poller production env;
- `Makefile` — команды `prod-*`;
- `docs/deployment/production.md` — инструкция оператора.

Production Compose запускается явно:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ...
```

Корневой `.env.production` используется только Docker Compose. Laravel и poller
получают настройки из собственных env-файлов, чтобы не смешивать инфраструктуру
и настройки приложений.

## Образы и данные

Laravel production-образ:

- копирует `apps/web`;
- устанавливает Composer-зависимости с `--no-dev`;
- содержит собранный `public/build`;
- запускает PHP-FPM;
- не использует bind mount исходного кода.

Nginx production-образ:

- использует production-конфигурацию Nginx;
- содержит `apps/web/public`, включая собранные frontend assets;
- передает PHP-запросы сервису `web:9000`.

Poller production-образ использует существующий target `prod` и запускает
скомпилированный Go-бинарник.

Постоянные данные хранятся в named volumes PostgreSQL и Redis. Порты PostgreSQL,
Redis и poller не публикуются наружу. Наружу публикуется только порт Nginx.

## Первый деплой

Оператор:

1. Клонирует репозиторий и создает три production env-файла из примеров.
2. Задает надежные пароли, `APP_KEY`, внутренние токены poller и внешние
   интеграции.
3. Запускает `make prod-deploy`.

`prod-deploy`:

1. Проверяет наличие production env-файлов.
2. Запускает одноразовый `node` и собирает frontend.
3. Собирает production-образы `web`, `nginx` и `poller`.
4. Запускает PostgreSQL и Redis и ожидает их health checks.
5. Выполняет `php artisan migrate --force` через одноразовый контейнер `web`.
6. Выполняет `php artisan optimize:clear`.
7. Запускает постоянные production-сервисы.
8. Показывает состояние контейнеров.

Команда не генерирует `APP_KEY` автоматически, чтобы случайный повторный запуск
не сделал существующие cookies и зашифрованные данные нечитаемыми.

## Обновление

`make prod-update` выполняет:

1. `git pull origin master`;
2. одноразовую production-сборку frontend;
3. пересборку образов `web`, `nginx` и `poller`;
4. запуск PostgreSQL и Redis;
5. `php artisan migrate --force`;
6. `php artisan optimize:clear`;
7. пересоздание и запуск постоянных production-сервисов;
8. вывод состояния контейнеров.

Миграции должны оставаться обратно совместимыми с текущей версией приложения,
поскольку обновление выполняется без сложной blue-green схемы.

## Эксплуатационные команды

Makefile предоставляет:

- `prod-deploy` — первый запуск без `git pull`;
- `prod-update` — обновление ветки `master` и повторный деплой;
- `prod-build-frontend` — ручная одноразовая сборка frontend;
- `prod-build` — сборка production-образов;
- `prod-up` — запуск постоянных сервисов;
- `prod-down` — остановка production Compose без удаления volumes;
- `prod-restart` — пересоздание постоянных сервисов;
- `prod-logs` — логи production-сервисов;
- `prod-ps` — их состояние;
- `prod-migrate` — миграции с `--force`;
- `prod-optimize-clear` — очистка Laravel-кешей.

## Надежность и безопасность

- Production-сервисы используют `restart: unless-stopped`.
- `web`, `poller` и `web-scheduler` зависят от healthy PostgreSQL и Redis там,
  где зависимость действительно нужна.
- Nginx публикует только HTTP-порт; TLS может завершаться внешним reverse proxy
  или добавляться отдельной задачей.
- Production env-файлы не добавляются в Git.
- Примеры env-файлов не содержат рабочих секретов.
- `docker compose down` не вызывается в процессе обновления, чтобы не
  останавливать PostgreSQL и Redis без необходимости.

## Документация

`docs/deployment/production.md` должна явно описывать:

- требования к серверу;
- таблицу production-контейнеров с назначением каждого сервиса, обязательностью,
  зависимостями, публикуемыми портами и последствиями его остановки;
- почему `node` не работает постоянно;
- подготовку всех production env-файлов;
- первый деплой;
- регулярное обновление;
- проверку health/status/logs;
- резервное копирование PostgreSQL;
- восстановление после неуспешного обновления;
- будущую необходимость `web-queue` при появлении queued jobs.

В таблицу контейнеров входят:

| Сервис | Назначение | Режим работы |
|---|---|---|
| `nginx` | Принимает публичные HTTP-запросы, отдает frontend assets и передает PHP-запросы в `web` | Постоянно |
| `web` | Выполняет Laravel через PHP-FPM | Постоянно |
| `postgres` | Хранит бизнес-данные Laravel | Постоянно |
| `redis` | Хранит сессии, кеш и данные Laravel-очередей | Постоянно |
| `poller` | Выполняет HTTP, SSL и domain-проверки и отправляет результаты в Laravel | Постоянно |
| `web-scheduler` | Запускает периодические Laravel-команды | Постоянно |
| `node` | Собирает production frontend assets во время деплоя | Одноразово |

## Проверка

Перед завершением необходимо проверить:

- `docker compose --env-file .env.production.example -f docker-compose.prod.yml config`;
- успешную production-сборку frontend;
- успешную сборку production-образов;
- запуск базовых сервисов;
- выполнение миграций и `optimize:clear`;
- доступность Laravel health endpoint через Nginx;
- доступность poller health endpoint внутри Docker-сети;
- отсутствие постоянно работающего `node` после деплоя.
