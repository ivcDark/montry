# Self-Monitoring

Дата обновления: 2026-05-27.

## Назначение

Self-monitoring отвечает на два вопроса:

- Montry доступен пользователям?
- Montry сохраняет данные так, что их можно восстановить?

В MVP это покрыто Blackbox Exporter, Prometheus alerts и backup metrics через
node-exporter textfile collector.

## Blackbox Targets

Targets лежат отдельно от основного Prometheus config:

```text
docker/observability/prometheus/blackbox_targets.yml
```

Локально проверяются:

- `http://nginx/`
- `http://nginx/login`
- `http://nginx/register`
- `http://poller:8090/health`

Отдельный TCP probe проверяет:

- `postgres:5432`
- `redis:6379`

Для production нужно заменить или дополнить targets публичными URL, например:

```yaml
- labels:
    scope: public
    service: laravel
  targets:
    - https://montry.example.com/
    - https://montry.example.com/login
    - https://montry.example.com/register
```

Не добавляйте URL с токенами, email, organization IDs, monitor IDs или другими
пользовательскими идентификаторами.

## Alerts

Self-monitoring alerts находятся в:

```text
docker/observability/prometheus/rules/availability.yml
```

Ключевые правила:

- `MontryLaravelUnavailable`
- `MontryPublicEndpointDown`
- `MontryPollerUnavailable`
- `MontryPostgresUnavailable`
- `MontryRedisUnavailable`
- `MontryPostgresBackupFailed`
- `MontryPostgresBackupStale`
- `MontryPostgresBackupVerificationFailed`

## Prometheus Queries

Проверка публичных endpoints:

```promql
probe_success{job="blackbox-montry",scope=~"public|public-local"}
```

Проверка backup freshness:

```promql
time() - montry_postgres_backup_last_success_timestamp_seconds
```

Проверка restore verification:

```promql
montry_postgres_backup_last_verify_status
```

## Local Verification

1. Поднять приложение и observability stack:

```bash
make up
make observability-up
```

2. Проверить blackbox targets в Prometheus:

```text
http://localhost:9090/targets?search=blackbox-montry
```

3. Создать и проверить backup:

```bash
make backup-postgres
make verify-postgres-backup
```

4. Проверить node-exporter metrics:

```bash
curl -s http://localhost:9100/metrics | grep montry_postgres_backup
```

5. Проверить alerts:

```text
http://localhost:9090/alerts
```

## Incident Response

Если сработал `MontryPublicEndpointDown`:

1. Проверить `docker compose ps nginx web`.
2. Проверить `docker compose logs --tail=200 nginx web`.
3. Проверить `probe_http_status_code` для конкретного `instance`.
4. Если падает только `/login` или `/register`, смотреть Laravel logs и
   последние деплои.
5. Если падают все endpoints, использовать runbook
   `docs/operations/runbooks/laravel-down.md`.

Если сработал backup alert:

1. Запустить `make backup-postgres` вручную.
2. Если backup создан, запустить `make verify-postgres-backup`.
3. Если restore verification падает, считать backup непригодным до выяснения
   причины.
4. Проверить свободное место, доступность контейнера PostgreSQL и права на
   backup directory.
