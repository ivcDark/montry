# Montry

**Montry** — B2B SaaS-сервис для мониторинга клиентских сайтов, доменов, SSL-сертификатов, DNS, `robots.txt` и платных дополнительных проверок вроде `sitemap.xml`, TCP-портов и API endpoint.

Проект ориентирован на веб-студии, SEO-агентства и команды поддержки сайтов.

## Что делает Montry

Montry помогает отслеживать:

- актуальность SSL-сертификатов;
- срок действия доменов;
- доступность важных web-страниц;
- время ответа страниц;
- доступность и базовые ошибки в `robots.txt`;
- платную доступность и валидность `sitemap.xml`;
- платные TCP-порты и API endpoint checks;
- инциденты и восстановление после ошибок;
- ручные проверки по кнопке “Проверить сейчас”;
- уведомления в Telegram, email и личном кабинете.

## Структура проекта

```text
.
├── apps
│   ├── poller              # Go-приложение для выполнения проверок
│   └── web                 # Laravel-приложение Montry
├── docker
│   ├── go                  # Dockerfile для Go poller
│   ├── nginx               # Конфигурация Nginx
│   ├── php                 # Dockerfile и настройки PHP-FPM
│   ├── postgres            # Init-скрипты PostgreSQL
│   ├── rabbitmq            # Конфиги RabbitMQ и definitions.json
│   └── redis               # Конфиг Redis
├── docs                    # Документация проекта
├── scripts                 # Вспомогательные скрипты / CI/CD
├── .env.example
├── .gitignore
├── docker-compose.yml
├── Makefile
└── README.md
```

## Требования
Для запуска проекта нужны:
- Docker
- Docker Compose
- Make

## Production

Production использует отдельный `docker-compose.prod.yml`. Инструкция по подготовке сервера, назначению контейнеров, первому деплою и обновлениям:

- [docs/deployment/production.md](docs/deployment/production.md)

## Product docs

- `docs/product/redesign.md` - направление редизайна, компактный UI, toast-сообщения и UX настройки мониторингов.
- `docs/product/tariffs.md` - актуальные тарифы Free/Pro/Plus, базовые мониторинги и платные add-ons.

## Быстрый старт
**Первый запуск проекта**
```
make init
```
Команда ```make init```:

Создаёт .env из .env.example, если файла ещё нет.  
Собирает Docker-образы.  
Запускает инфраструктурные сервисы:
- PostgreSQL;
- Redis;
- RabbitMQ;
- Mailpit.

Запускает Laravel-сервисы:
- web;
- nginx.

Устанавливает Composer-зависимости.  
Исправляет права на директории Laravel:
storage;
bootstrap/cache.  
Генерирует APP_KEY.  
Запускает миграции.  
Поднимает все остальные сервисы Montry:
- web-scheduler;
- web-queue;
- web-result-consumer;
- poller-manual;
- poller-http;
- poller-seo;
- poller-ssl;
- poller-domain.

После запуска приложение будет доступно по адресу:  
http://localhost:8080


**Запускает все контейнеры в фоне**
```
make up
```

**Останавливает и удаляет контейнеры**
```
make down
```

**Собирает Docker-образы**
```
make build
```

**Пересобирает Docker-образы без кеша**
```
make rebuild
```

**Перезапускает весь проект**
```
make restart
```

**Показывает список контейнеров**
```
make ps
```

**Показывает логи всех сервисов Montry**
```
make logs
```

**Устанавливает Composer-зависимости внутри контейнера web**
```
make composer-install
```

**Обновляет Composer-зависимости внутри контейнера web**
```
make composer-update
```

**Запускает произвольную Artisan-команду**
```
make artisan

Примеры:
make artisan cmd="config:clear"
make artisan cmd="cache:clear"
make artisan cmd="queue:failed"
```

**Генерирует Laravel APP_KEY**
```
make key
```

**Запускает миграции базы данных**
```
make migrate
```


