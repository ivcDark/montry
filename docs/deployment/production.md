# Production Deployment

Дата обновления: 2026-06-04.

Эта инструкция описывает развертывание Montry на одном production-сервере через
`docker-compose.prod.yml`.

Production Compose является самостоятельным файлом и не использует dev bind
mounts, Vite dev server, Mailpit или observability-контейнеры.

## Требования к серверу

- Linux-сервер с публичным IP;
- Git;
- Docker Engine;
- Docker Compose plugin;
- Make;
- открытый внешний порт для Nginx, по умолчанию `80`;
- настроенный внешний TLS reverse proxy либо отдельная настройка HTTPS.

Рекомендуется запускать команды из отдельного системного пользователя, имеющего
доступ к Docker. Production env-файлы и каталог проекта должны быть недоступны
посторонним пользователям сервера.

## Production-контейнеры

| Контейнер | Назначение | Обязательный | Публичные порты | Зависимости | Что произойдет при остановке |
|---|---|---:|---|---|---|
| `nginx` | Принимает HTTP-запросы, отдает frontend assets и передает PHP-запросы в `web` | Да | `${APP_PORT:-80}` | `web` | Сайт и internal API станут недоступны |
| `web` | Выполняет Laravel через PHP-FPM | Да | Нет | `postgres`, `redis` | Сайт, API и обработка результатов poller перестанут работать |
| `postgres` | Хранит пользователей, мониторы, результаты проверок, инциденты, платежи и остальные бизнес-данные | Да | Нет | Нет | Laravel не сможет читать и сохранять бизнес-данные |
| `redis` | Хранит пользовательские сессии, кеш и данные Laravel-очередей | Да | Нет | Нет | Пользовательские сессии и кеш перестанут работать; queued jobs не смогут обрабатываться |
| `poller` | Выполняет HTTP, SSL и domain-проверки и отправляет результаты в Laravel | Да | Нет | healthy `nginx` | Новые проверки выполняться не будут |
| `web-scheduler` | Запускает периодические Laravel-команды: биллинг, дайджесты и другие scheduled jobs | Да | Нет | `postgres`, `redis` | Периодические Laravel-задачи перестанут запускаться |
| `node` | Выполняет `npm ci && npm run build` во время деплоя | Только при деплое | Нет | Нет | Не влияет на уже запущенное приложение |

`node` является одноразовым build-контейнером с Compose profile `build`. После
успешной frontend-сборки он завершается. Отсутствие `node` в выводе
`make prod-ps` является нормальным и ожидаемым состоянием.

Отдельного `web-queue` сейчас нет, потому что текущий код не содержит задач
Laravel `ShouldQueue`. После появления queued jobs необходимо добавить
постоянный worker на том же образе `web`.

## Production-файлы

В production используются три раздельных env-файла:

| Файл | Назначение |
|---|---|
| `.env.production` | Только переменные Docker Compose: порт, UID/GID и учетные данные PostgreSQL для контейнера |
| `apps/web/.env.production` | Настройки Laravel |
| `apps/poller/.env.production` | Настройки Go poller |

Не смешивайте корневой Docker env с Laravel или poller env.

Реальные production env-файлы игнорируются Git. Файлы `*.example` не содержат
рабочих секретов.

## Первый деплой

Клонируйте ветку `master`:

```bash
git clone --branch master <repository-url> montri
cd montri
```

Подготовьте env-файлы:

```bash
cp .env.production.example .env.production
cp apps/web/.env.production.example apps/web/.env.production
cp apps/poller/.env.production.example apps/poller/.env.production
chmod 600 .env.production apps/web/.env.production apps/poller/.env.production
```

Замените все значения `change-me` и `replace-with-*`, настройте домен, почту,
Robokassa и Sentry.

Для проверки Robokassa в тестовом режиме на production-инфраструктуре
настройте именно Laravel env-файл `apps/web/.env.production`:

```dotenv
ROBOKASSA_MODE=test
ROBOKASSA_MERCHANT_LOGIN=<merchant-login>
ROBOKASSA_TEST_PASSWORD1=<test-password-1>
ROBOKASSA_TEST_PASSWORD2=<test-password-2>
ROBOKASSA_HASH_ALGORITHM=md5
ROBOKASSA_PAYMENT_URL=https://auth.robokassa.ru/Merchant/Index.aspx
ROBOKASSA_CULTURE=ru
```

`ROBOKASSA_PASSWORD1` и `ROBOKASSA_PASSWORD2` относятся к боевому режиму и могут
оставаться пустыми, пока тестируется `ROBOKASSA_MODE=test`. После изменения env
выполните `make prod-optimize-clear` и пересоздайте Laravel-контейнеры через
`make prod-up` или `make prod-restart`.

Следующие значения должны совпадать:

| Laravel | Poller |
|---|---|
| `POLLER_TOKEN` | `POLLER_MANUAL_API_TOKEN` |
| `POLLER_INTERNAL_TOKEN` | `LARAVEL_INTERNAL_API_TOKEN` |

Значения подключения Laravel к PostgreSQL также должны совпадать с
`POSTGRES_DB`, `POSTGRES_USER` и `POSTGRES_PASSWORD` из `.env.production`.

Сгенерируйте Laravel `APP_KEY` локально или через одноразовый Docker-контейнер:

```bash
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Запишите результат в `APP_KEY` файла `apps/web/.env.production`. Никогда не
меняйте ключ на уже работающем production без отдельного плана миграции.

Запустите первый деплой:

```bash
make prod-deploy
```

Команда:

1. проверяет наличие трех production env-файлов и отсутствие шаблонных секретов;
2. запускает одноразовый Node-контейнер и собирает frontend;
3. собирает production-образы Laravel, Nginx и poller;
4. запускает PostgreSQL и Redis;
5. выполняет `php artisan migrate --force`;
6. выполняет `php artisan optimize:clear`;
7. запускает постоянные production-контейнеры;
8. показывает их состояние.

## Регулярное обновление

Убедитесь, что на сервере нет незакоммиченных изменений, затем выполните:

```bash
make prod-update
```

Команда выполняет:

```text
git pull --ff-only origin master
frontend build
Docker image build
php artisan migrate --force
php artisan optimize:clear
production services up
```

`--ff-only` не позволяет случайно создать merge-коммит на production-сервере.
Если обновление требует merge, сначала исправьте состояние Git вручную.

Во время обычного обновления PostgreSQL и Redis не останавливаются. Nginx,
Laravel и poller пересоздаются после сборки новых образов.

## Операционные команды

```bash
make prod-ps                 # состояние контейнеров
make prod-logs               # логи постоянных сервисов
make prod-up                 # запустить production-сервисы
make prod-down               # остановить сервисы без удаления volumes
make prod-restart            # пересоздать постоянные сервисы
make prod-build-frontend     # только frontend build через одноразовый node
make prod-build              # собрать web, nginx и poller
make prod-migrate            # выполнить миграции с --force
make prod-optimize-clear     # очистить Laravel-кеши
```

Не выполняйте `docker compose down -v`: параметр `-v` удалит данные PostgreSQL
и Redis.

## Проверка после деплоя

Проверьте состояние контейнеров:

```bash
make prod-ps
```

Ожидается, что постоянно работают:

```text
nginx
web
web-scheduler
postgres
redis
poller
```

`node` в этом списке быть не должно.

Проверьте Laravel через Nginx:

```bash
curl -fsS http://127.0.0.1:<APP_PORT>/up
```

Замените `<APP_PORT>` значением `APP_PORT` из `.env.production`.

Проверьте poller внутри Docker-сети:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml \
  exec poller wget -qO- http://127.0.0.1:8090/health
```

Проверьте Laravel-логи:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml \
  logs --tail=200 web web-scheduler nginx poller
```

## Резервное копирование

Перед обновлениями с важными миграциями создавайте резервную копию PostgreSQL.

Пример:

```bash
mkdir -p backups/postgres
docker compose --env-file .env.production -f docker-compose.prod.yml \
  exec -T postgres pg_dump -U <POSTGRES_USER> -d <POSTGRES_DB> -Fc \
  > backups/postgres/montry-$(date +%Y%m%d-%H%M%S).dump
```

Храните копии вне production-сервера. Подробные правила резервного копирования
описаны в `docs/operations/backups.md`.

## Неуспешное обновление

Если сборка завершилась ошибкой до запуска новых контейнеров, исправьте причину
и повторите `make prod-update`. Уже работающие контейнеры продолжат использовать
предыдущие образы.

Если новая версия запустилась, но работает некорректно:

1. изучите `make prod-logs`;
2. верните репозиторий на заранее выбранный рабочий commit без удаления данных;
3. выполните `make prod-deploy`;
4. восстанавливайте PostgreSQL из backup только если миграция необратимо
   изменила данные.

Не применяйте `git reset --hard` к production-каталогу без проверки env-файлов
и локального состояния.

## Дополнительные сервисы

Базовый production-набор намеренно не включает Grafana, Prometheus, Loki,
Tempo, ClickHouse, Mailpit и RabbitMQ.

Observability можно развернуть отдельно, когда она понадобится. До этого в
Laravel и poller production env должны оставаться:

```env
OTEL_TRACES_ENABLED=false
OBSERVABILITY_METRICS_ENABLED=false
OBSERVABILITY_CLICKHOUSE_ENABLED=false
```
