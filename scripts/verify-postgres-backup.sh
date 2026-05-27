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
metrics_file="${METRICS_DIR}/montry_postgres_backup_verify.prom"
started_at="$(date +%s)"
verify_db="montry_restore_verify_$(date +%s)_$$"

if [[ "${BACKUP_DIR}" != /* ]]; then
    BACKUP_DIR="${ROOT_DIR}/${BACKUP_DIR}"
fi

if [[ "${METRICS_DIR}" != /* ]]; then
    METRICS_DIR="${ROOT_DIR}/${METRICS_DIR}"
fi

compose() {
    # Intentionally unquoted: allows DOCKER_COMPOSE_BIN="docker compose".
    ${COMPOSE_BIN} "$@"
}

latest_backup() {
    find "${BACKUP_DIR}" -type f -name "*.dump" -printf "%T@ %p\n" 2>/dev/null \
        | sort -nr \
        | awk 'NR == 1 { sub(/^[^ ]+ /, ""); print }'
}

write_metrics() {
    local status="$1"
    local finished_at duration tmp_metrics

    finished_at="$(date +%s)"
    duration="$((finished_at - started_at))"
    tmp_metrics="${metrics_file}.tmp"

    {
        echo "# HELP montry_postgres_backup_last_verify_status Last PostgreSQL backup restore verification status, 1 for success and 0 for failure."
        echo "# TYPE montry_postgres_backup_last_verify_status gauge"
        echo "montry_postgres_backup_last_verify_status ${status}"
        echo "# HELP montry_postgres_backup_last_verify_attempt_timestamp_seconds Unix timestamp of the last PostgreSQL backup restore verification attempt."
        echo "# TYPE montry_postgres_backup_last_verify_attempt_timestamp_seconds gauge"
        echo "montry_postgres_backup_last_verify_attempt_timestamp_seconds ${finished_at}"
        echo "# HELP montry_postgres_backup_last_verify_duration_seconds Duration of the last PostgreSQL backup restore verification attempt."
        echo "# TYPE montry_postgres_backup_last_verify_duration_seconds gauge"
        echo "montry_postgres_backup_last_verify_duration_seconds ${duration}"

        if [[ "${status}" == "1" ]]; then
            echo "# HELP montry_postgres_backup_last_verify_success_timestamp_seconds Unix timestamp of the last successful PostgreSQL backup restore verification."
            echo "# TYPE montry_postgres_backup_last_verify_success_timestamp_seconds gauge"
            echo "montry_postgres_backup_last_verify_success_timestamp_seconds ${finished_at}"
        fi
    } > "${tmp_metrics}"

    mv "${tmp_metrics}" "${metrics_file}"
}

cleanup() {
    compose exec -T "${POSTGRES_SERVICE}" dropdb -U "${POSTGRES_USER}" --if-exists "${verify_db}" >/dev/null 2>&1 || true
}

on_error() {
    cleanup
    write_metrics 0
}

trap on_error ERR
trap cleanup EXIT

mkdir -p "${METRICS_DIR}"

backup_file="${1:-$(latest_backup)}"

if [[ -z "${backup_file}" || ! -f "${backup_file}" ]]; then
    echo "No PostgreSQL backup file found in ${BACKUP_DIR}" >&2
    false
fi

if [[ -f "${backup_file}.sha256" ]] && command -v sha256sum >/dev/null 2>&1; then
    sha256sum --check "${backup_file}.sha256"
fi

compose exec -T "${POSTGRES_SERVICE}" createdb -U "${POSTGRES_USER}" "${verify_db}"

compose exec -T "${POSTGRES_SERVICE}" pg_restore \
    -U "${POSTGRES_USER}" \
    -d "${verify_db}" \
    --no-owner \
    --no-privileges < "${backup_file}"

compose exec -T "${POSTGRES_SERVICE}" psql \
    -U "${POSTGRES_USER}" \
    -d "${verify_db}" \
    -v ON_ERROR_STOP=1 \
    -c "select count(*) as migrations_count from migrations;" >/dev/null

write_metrics 1

echo "PostgreSQL backup verified against temporary database ${verify_db}: ${backup_file}"
