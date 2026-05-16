#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BASE_DIR}/backups"
ENV_FILE="${BASE_DIR}/.env"

if [ -f "${ENV_FILE}" ]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

DB_NAME="${DB_NAME:-ferragens_souza}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-localhost}"

mkdir -p "${BACKUP_DIR}"

STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="${BACKUP_DIR}/${DB_NAME}_${STAMP}.sql.gz"

# A-03: Usar arquivo temporário de credenciais para não expor senha
# em 'ps aux' ou /proc durante a execução do mysqldump
if [ -n "${DB_PASS}" ]; then
  MYCNF="$(mktemp)"
  chmod 600 "${MYCNF}"
  printf '[client]\npassword=%s\n' "${DB_PASS}" > "${MYCNF}"
  mysqldump --defaults-extra-file="${MYCNF}" -h "${DB_HOST}" -u "${DB_USER}" "${DB_NAME}" | gzip > "${OUT_FILE}"
  rm -f "${MYCNF}"
else
  mysqldump -h "${DB_HOST}" -u "${DB_USER}" "${DB_NAME}" | gzip > "${OUT_FILE}"
fi

find "${BACKUP_DIR}" -name "*.sql.gz" -type f -mtime +30 -delete

echo "Backup criado: ${OUT_FILE}"
