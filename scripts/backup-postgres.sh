#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

if [[ -f "${ROOT_DIR}/.env" ]]; then
    set -a
    # shellcheck disable=SC1090
    source <(sed 's/\r$//' "${ROOT_DIR}/.env" | sed '/^UID=/d')
    set +a
fi

COMPOSE_BIN="${DOCKER_COMPOSE_BIN:-docker compose}"
POSTGRES_SERVICE="${POSTGRES_SERVICE:-postgres}"
POSTGRES_DB="${POSTGRES_DB:-montry}"
POSTGRES_USER="${POSTGRES_USER:-montry}"
BACKUP_DIR="${POSTGRES_BACKUP_DIR:-${ROOT_DIR}/backups/postgres}"
METRICS_DIR="${BACKUP_METRICS_DIR:-${ROOT_DIR}/docker/observability/node-exporter/textfile_collector}"
RETENTION_DAYS="${POSTGRES_BACKUP_RETENTION_DAYS:-14}"

if [[ "${BACKUP_DIR}" != /* ]]; then
    BACKUP_DIR="${ROOT_DIR}/${BACKUP_DIR}"
fi

if [[ "${METRICS_DIR}" != /* ]]; then
    METRICS_DIR="${ROOT_DIR}/${METRICS_DIR}"
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="${BACKUP_DIR}/${POSTGRES_DB}-${timestamp}.dump"
tmp_file="${backup_file}.tmp"
metrics_file="${METRICS_DIR}/montry_postgres_backup.prom"
started_at="$(date +%s)"

compose() {
    # Intentionally unquoted: allows DOCKER_COMPOSE_BIN="docker compose".
    ${COMPOSE_BIN} "$@"
}

write_metrics() {
    local status="$1"
    local finished_at duration size tmp_metrics

    finished_at="$(date +%s)"
    duration="$((finished_at - started_at))"
    size="0"

    if [[ -f "${backup_file}" ]]; then
        size="$(wc -c < "${backup_file}" | tr -d ' ')"
    fi

    tmp_metrics="${metrics_file}.tmp"

    {
        echo "# HELP montry_postgres_backup_last_status Last PostgreSQL backup status, 1 for success and 0 for failure."
        echo "# TYPE montry_postgres_backup_last_status gauge"
        echo "montry_postgres_backup_last_status ${status}"
        echo "# HELP montry_postgres_backup_last_attempt_timestamp_seconds Unix timestamp of the last PostgreSQL backup attempt."
        echo "# TYPE montry_postgres_backup_last_attempt_timestamp_seconds gauge"
        echo "montry_postgres_backup_last_attempt_timestamp_seconds ${finished_at}"
        echo "# HELP montry_postgres_backup_last_duration_seconds Duration of the last PostgreSQL backup attempt."
        echo "# TYPE montry_postgres_backup_last_duration_seconds gauge"
        echo "montry_postgres_backup_last_duration_seconds ${duration}"
        echo "# HELP montry_postgres_backup_last_size_bytes Size of the last successful PostgreSQL backup file."
        echo "# TYPE montry_postgres_backup_last_size_bytes gauge"
        echo "montry_postgres_backup_last_size_bytes ${size}"

        if [[ "${status}" == "1" ]]; then
            echo "# HELP montry_postgres_backup_last_success_timestamp_seconds Unix timestamp of the last successful PostgreSQL backup."
            echo "# TYPE montry_postgres_backup_last_success_timestamp_seconds gauge"
            echo "montry_postgres_backup_last_success_timestamp_seconds ${finished_at}"
        fi
    } > "${tmp_metrics}"

    mv "${tmp_metrics}" "${metrics_file}"
}

on_error() {
    rm -f "${tmp_file}"
    write_metrics 0
}

trap on_error ERR

mkdir -p "${BACKUP_DIR}" "${METRICS_DIR}"

compose exec -T "${POSTGRES_SERVICE}" pg_dump \
    -U "${POSTGRES_USER}" \
    -d "${POSTGRES_DB}" \
    -Fc \
    --no-owner \
    --no-privileges > "${tmp_file}"

mv "${tmp_file}" "${backup_file}"

if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "${backup_file}" > "${backup_file}.sha256"
fi

find "${BACKUP_DIR}" -type f \( -name "*.dump" -o -name "*.dump.sha256" \) -mtime "+${RETENTION_DAYS}" -delete

write_metrics 1

echo "PostgreSQL backup created: ${backup_file}"
