COMPOSE = docker compose
PROD_ENV = .env.production
PROD_COMPOSE = docker compose --env-file $(PROD_ENV) -f docker-compose.prod.yml
PROD_SERVICES = nginx web web-scheduler poller postgres redis
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
	observability-up observability-down observability-logs grafana-ui prometheus-ui loki-ui tempo-ui clickhouse-shell \
	backup-postgres verify-postgres-backup \
	prod-check-env prod-build-frontend prod-build prod-up prod-down prod-restart prod-logs prod-ps prod-migrate prod-optimize-clear prod-deploy prod-update \
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

prod-check-env:
	@test -f $(PROD_ENV) || (echo "Missing $(PROD_ENV). Copy .env.production.example and configure it." && exit 1)
	@test -f apps/web/.env.production || (echo "Missing apps/web/.env.production. Copy apps/web/.env.production.example and configure it." && exit 1)
	@test -f apps/poller/.env.production || (echo "Missing apps/poller/.env.production. Copy apps/poller/.env.production.example and configure it." && exit 1)
	@! grep -Eq '=(change-me|replace-with-[^[:space:]]*)$$|^APP_KEY=$$' $(PROD_ENV) apps/web/.env.production apps/poller/.env.production || (echo "Production env files contain placeholder secrets or an empty APP_KEY." && exit 1)

prod-build-frontend: prod-check-env
	$(PROD_COMPOSE) --profile build run --rm node

prod-build: prod-check-env
	$(PROD_COMPOSE) build web nginx poller

prod-up: prod-check-env
	$(PROD_COMPOSE) up -d --remove-orphans $(PROD_SERVICES)

prod-down: prod-check-env
	$(PROD_COMPOSE) down

prod-restart: prod-check-env
	$(PROD_COMPOSE) up -d --force-recreate $(PROD_SERVICES)

prod-logs: prod-check-env
	$(PROD_COMPOSE) logs -f --tail=200 $(PROD_SERVICES)

prod-ps: prod-check-env
	$(PROD_COMPOSE) ps

prod-migrate: prod-check-env
	$(PROD_COMPOSE) run --rm web php artisan migrate --force

prod-optimize-clear: prod-check-env
	$(PROD_COMPOSE) run --rm web php artisan optimize:clear

prod-deploy: prod-check-env
	$(MAKE) prod-build-frontend
	$(MAKE) prod-build
	$(PROD_COMPOSE) up -d postgres redis
	$(MAKE) prod-migrate
	$(MAKE) prod-optimize-clear
	$(MAKE) prod-up
	$(MAKE) prod-ps

prod-update: prod-check-env
	git pull --ff-only origin master
	$(MAKE) prod-deploy

observability-up:
	$(COMPOSE) --profile observability up -d

observability-down:
	$(COMPOSE) --profile observability down

observability-logs:
	$(COMPOSE) --profile observability logs -f --tail=200 grafana prometheus loki tempo otel-collector clickhouse node-exporter cadvisor blackbox-exporter

grafana-ui:
	xdg-open http://localhost:$${GRAFANA_PORT:-3000} || open http://localhost:$${GRAFANA_PORT:-3000} || true

prometheus-ui:
	xdg-open http://localhost:$${PROMETHEUS_PORT:-9090} || open http://localhost:$${PROMETHEUS_PORT:-9090} || true

loki-ui: grafana-ui

tempo-ui: grafana-ui

clickhouse-shell:
	$(COMPOSE) --profile observability exec clickhouse clickhouse-client -u $${CLICKHOUSE_USER:-montry} --password $${CLICKHOUSE_PASSWORD:-montry_secret} -d $${CLICKHOUSE_DB:-montry_analytics}

backup-postgres:
	./scripts/backup-postgres.sh

verify-postgres-backup:
	./scripts/verify-postgres-backup.sh

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
