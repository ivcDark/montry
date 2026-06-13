# PostgreSQL Backups

Дата обновления: 2026-05-27.

## Назначение

PostgreSQL остается operational source of truth для Montry. Backup-процедура
делает сжатый custom-format dump через `pg_dump`, проверяет restore во временную
базу и отдает Prometheus-метрики через node-exporter textfile collector.

## Команды

Создать backup:

```bash
make backup-postgres
```

Проверить последний backup restore-ом во временную базу:

```bash
make verify-postgres-backup
```

Проверить конкретный файл:

```bash
./scripts/verify-postgres-backup.sh backups/postgres/monitoring-20260527T120000Z.dump
```

## Конфигурация

Переменные задаются в root `.env`:

```text
POSTGRES_SERVICE=postgres
POSTGRES_DB=monitoring
POSTGRES_USER=monitoring
POSTGRES_BACKUP_DIR=backups/postgres
POSTGRES_BACKUP_RETENTION_DAYS=14
BACKUP_METRICS_DIR=docker/observability/node-exporter/textfile_collector
```

`POSTGRES_BACKUP_DIR` и `BACKUP_METRICS_DIR` могут быть абсолютными или
относительными к корню репозитория. Для production лучше указывать каталог на
отдельном диске или примонтированном backup volume.

## Метрики

Скрипты пишут `.prom` файлы в:

```text
docker/observability/node-exporter/textfile_collector/
```

Node Exporter читает этот каталог и публикует метрики:

- `montry_postgres_backup_last_status`
- `montry_postgres_backup_last_attempt_timestamp_seconds`
- `montry_postgres_backup_last_success_timestamp_seconds`
- `montry_postgres_backup_last_duration_seconds`
- `montry_postgres_backup_last_size_bytes`
- `montry_postgres_backup_last_verify_status`
- `montry_postgres_backup_last_verify_attempt_timestamp_seconds`
- `montry_postgres_backup_last_verify_success_timestamp_seconds`
- `montry_postgres_backup_last_verify_duration_seconds`

## Alerts

Правила лежат в:

```text
docker/observability/prometheus/rules/availability.yml
```

Активные backup alerts:

- `MontryPostgresBackupFailed` — последняя попытка backup завершилась ошибкой.
- `MontryPostgresBackupStale` — нет успешного backup дольше 25 часов.
- `MontryPostgresBackupVerificationFailed` — последний restore test завершился
  ошибкой.

## Restore Procedure

1. Остановить Laravel write traffic или перевести приложение в maintenance mode:

```bash
make artisan cmd="down"
```

2. Выбрать backup файл:

```bash
ls -lh backups/postgres/*.dump
```

3. Проверить backup перед restore:

```bash
./scripts/verify-postgres-backup.sh backups/postgres/<file>.dump
```

4. Создать отдельную восстановленную базу для ручной проверки:

```bash
docker compose exec -T postgres createdb -U ${POSTGRES_USER:-monitoring} montry_restore
docker compose exec -T postgres pg_restore -U ${POSTGRES_USER:-monitoring} -d montry_restore --no-owner --no-privileges < backups/postgres/<file>.dump
```

5. Если нужно заменить рабочую базу, делать это только после snapshot текущего
   состояния и подтверждения владельца проекта:

```bash
docker compose exec -T postgres dropdb -U ${POSTGRES_USER:-monitoring} ${POSTGRES_DB:-monitoring}
docker compose exec -T postgres createdb -U ${POSTGRES_USER:-monitoring} ${POSTGRES_DB:-monitoring}
docker compose exec -T postgres pg_restore -U ${POSTGRES_USER:-monitoring} -d ${POSTGRES_DB:-monitoring} --no-owner --no-privileges < backups/postgres/<file>.dump
```

6. Вернуть приложение:

```bash
make artisan cmd="up"
make migrate
```

7. Проверить dashboard, регистрацию, internal API и Prometheus alerts.

## Production Notes

- Запускать `backup-postgres` минимум раз в сутки.
- Запускать `verify-postgres-backup` после каждого backup или хотя бы ежедневно.
- Хранить копию backup вне сервера Montry.
- Не логировать dump file contents.
- Не хранить backup в публичных Docker volumes или web-accessible каталогах.
