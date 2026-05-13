COMPOSE = docker compose
WEB = $(COMPOSE) exec web
WEB_RUN = $(COMPOSE) run --rm web
POLLER_HTTP = $(COMPOSE) exec poller-http
POLLER = $(COMPOSE) exec poller
POLLER_RUN = $(COMPOSE) run --rm poller
POSTGRES = $(COMPOSE) exec postgres
REDIS = $(COMPOSE) exec redis

.PHONY: init up down build rebuild restart logs ps \
	composer-install composer-update fix-permissions artisan key generate-app-key \
	migrate fresh seed test shell status \
	web-logs scheduler-logs queue-logs result-consumer-logs \
	poller-build poller-logs poller-test poller-run poller-run-mock poller-manual-logs poller-http-logs poller-seo-logs poller-ssl-logs poller-domain-logs \
	poller-shell postgres-shell redis-cli rabbitmq-ui mailpit-ui \
	scale-http scale-seo

prepare-permissions:
	mkdir -p apps/web/vendor apps/web/storage apps/web/bootstrap/cache
	sudo chown -R $$(id -u):$$(id -g) apps/web/vendor apps/web/storage apps/web/bootstrap/cache || true

init:
	cp -n .env.example .env || true
	$(COMPOSE) up -d --build postgres redis rabbitmq mailpit
	$(COMPOSE) up -d --build web nginx
	$(WEB_RUN) composer install
	$(WEB) chmod -R ug+rwX storage bootstrap/cache
	$(WEB) php artisan key:generate
	$(WEB) php artisan migrate
	$(COMPOSE) up -d

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

rebuild:
	$(COMPOSE) build --no-cache

restart:
	$(COMPOSE) down
	$(COMPOSE) up -d

logs:
	$(COMPOSE) logs -f --tail=200

ps:
	$(COMPOSE) ps

status: ps

web-logs:
	$(COMPOSE) logs -f --tail=200 web nginx

scheduler-logs:
	$(COMPOSE) logs -f --tail=200 web-scheduler

queue-logs:
	$(COMPOSE) logs -f --tail=200 web-queue

result-consumer-logs:
	$(COMPOSE) logs -f --tail=200 web-result-consumer

poller-logs:
	$(COMPOSE) logs -f --tail=200 poller

poller-build:
	$(COMPOSE) build poller

poller-manual-logs:
	$(COMPOSE) logs -f --tail=200 poller-manual

poller-http-logs:
	$(COMPOSE) logs -f --tail=200 poller-http

poller-seo-logs:
	$(COMPOSE) logs -f --tail=200 poller-seo

poller-ssl-logs:
	$(COMPOSE) logs -f --tail=200 poller-ssl

poller-domain-logs:
	$(COMPOSE) logs -f --tail=200 poller-domain

composer-install:
	$(WEB_RUN) composer install

composer-update:
	$(WEB_RUN) composer update

fix-permissions:
	$(WEB) chmod -R 777 storage bootstrap/cache

artisan:
	$(WEB) php artisan $(cmd)

key:
	$(WEB) php artisan key:generate

generate-app-key: key

migrate:
	$(WEB) php artisan migrate

fresh:
	$(WEB) php artisan migrate:fresh --seed

seed:
	$(WEB) php artisan db:seed

test:
	$(WEB) php artisan test

shell:
	$(WEB) bash

poller-shell:
	$(POLLER) sh

poller-test:
	$(POLLER_RUN) go test ./...

poller-run:
	$(COMPOSE) up poller

poller-run-mock:
	$(COMPOSE) --profile mock up mock-laravel poller-mock

postgres-shell:
	$(POSTGRES) psql -U monitoring -d monitoring

redis-cli:
	$(REDIS) redis-cli

rabbitmq-ui:
	open http://localhost:15672 || true

mailpit-ui:
	open http://localhost:8025 || true

scale-http:
	$(COMPOSE) up -d --scale poller-http=$(n)

scale-seo:
	$(COMPOSE) up -d --scale poller-seo=$(n)
