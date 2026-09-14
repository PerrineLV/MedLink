#!/bin/bash
set -euo pipefail

DEPLOY_PATH="/opt/medlink"
BACKUP_DIR="/var/backups/medlink"
RETENTION_DAYS=7

set -a
source "${DEPLOY_PATH}/.env"
set +a

TIMESTAMP=$(date +"%Y-%m-%d_%Hh%M")
BACKUP_FILE="${BACKUP_DIR}/medlink_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

docker compose -f "${DEPLOY_PATH}/docker-compose.prod.yml" exec -T db \
  pg_dump -U "${POSTGRES_USER:-medlink}" "${POSTGRES_DB:-medlink}" | gzip > "$BACKUP_FILE"

find "$BACKUP_DIR" -name "medlink_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

echo "Sauvegarde créée : ${BACKUP_FILE}"
