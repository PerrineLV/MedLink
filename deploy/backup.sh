#!/bin/bash
set -euo pipefail

# Sauvegarde quotidienne de la base de production (ML-74), déclenchée par
# medlink-backup.timer (ML-169), surveillée par Sentry Crons (ML-143).

DEPLOY_PATH="/opt/medlink"
BACKUP_DIR="/var/backups/medlink"
RETENTION_DAYS=7

# shellcheck source=lib/archive-checks.sh
source "${DEPLOY_PATH}/lib/archive-checks.sh"

set -a
source "${DEPLOY_PATH}/.env"
set +a

TIMESTAMP=$(date +"%Y-%m-%d_%Hh%M")
BACKUP_FILE="${BACKUP_DIR}/medlink_${TIMESTAMP}.sql.gz"

# ML-143 : production dans un fichier temporaire.
#
# L'ancienne version écrivait directement dans BACKUP_FILE. Or la redirection
# shell crée et tronque le fichier AVANT que pg_dump ne s'exécute : un échec
# laissait derrière lui une archive incomplète, que la rétention conservait
# sept jours et qui avait toutes les apparences d'une sauvegarde. Le fichier
# n'est désormais renommé à son nom définitif qu'une fois validé — une archive
# présente dans BACKUP_DIR est une archive en laquelle on peut avoir confiance.
TMP_FILE="${BACKUP_FILE}.partial"

# ------------------------------------------------------------------ Sentry ---
#
# ML-143. SENTRY_CRONS_URL vient de ${DEPLOY_PATH}/.env et porte l'URL de
# check-in complète (organisation, projet, slug du monitor, clé publique).
# install-backup.sh refuse d'installer si elle manque : une surveillance
# silencieusement désactivée serait pire que pas de surveillance, puisqu'on la
# croirait active.
#
# Le monitor est créé et reconfiguré par le premier check-in de chaque
# exécution, via `monitor_config`. Sa planification vit donc ici, versionnée,
# plutôt que d'être cliquée dans l'interface Sentry — où elle disparaîtrait à
# la première réinstallation sans que personne ne sache ce qu'elle contenait.
# C'est précisément ce qui avait produit ML-169.
sentry_checkin() {
  local status="$1"
  local payload

  [ -n "${SENTRY_CRONS_URL:-}" ] || return 0

  if [ "$status" = "in_progress" ]; then
    # Aligné sur medlink-backup.timer : 04h17, et le VPS est en UTC.
    # checkin_margin : minutes de retard tolérées avant de déclarer un raté.
    # max_runtime    : au-delà, l'exécution est considérée comme bloquée.
    payload=$(cat <<EOF
{"status":"in_progress","monitor_config":{
  "schedule":{"type":"crontab","value":"17 4 * * *"},
  "timezone":"UTC","checkin_margin":30,"max_runtime":30,
  "failure_issue_threshold":1,"recovery_threshold":1}}
EOF
)
  else
    payload="{\"status\":\"${status}\"}"
  fi

  # Un incident Sentry ne doit jamais faire échouer une sauvegarde par
  # ailleurs saine : l'appel est borné en temps et son échec ignoré. Le coût
  # est nul — un check-in manquant fait de toute façon alerter Sentry.
  curl --silent --show-error --max-time 10 \
       --request POST "${SENTRY_CRONS_URL}" \
       --header 'Content-Type: application/json' \
       --data-raw "$payload" >/dev/null 2>&1 \
    || echo "AVERTISSEMENT : check-in Sentry '${status}' non transmis." >&2
}

# Tout chemin d'échec — y compris une erreur inattendue attrapée par `set -e`
# — passe par ici : Sentry est prévenu et le fichier partiel ne survit pas.
on_failure() {
  rm -f "$TMP_FILE"
  sentry_checkin error
  echo "ÉCHEC : aucune archive publiée pour ${TIMESTAMP}." >&2
}
trap on_failure ERR

# -------------------------------------------------------------------- Dump ---

mkdir -p "$BACKUP_DIR"

sentry_checkin in_progress

docker compose -f "${DEPLOY_PATH}/docker-compose.prod.yml" exec -T db \
  pg_dump -U "${POSTGRES_USER:-medlink}" "${POSTGRES_DB:-medlink}" | gzip > "$TMP_FILE"

# --------------------------------------------------------------- Validation ---

echo "Contrôle de l'archive produite :"
if ! archive_check "$TMP_FILE"; then
  # `trap ERR` ne se déclenche pas sur un `if` qui échoue : on sort
  # explicitement, ce qui l'active.
  false
fi

mv "$TMP_FILE" "$BACKUP_FILE"

# La sauvegarde est acquise dès le renommage : on l'annonce ici, avant la
# rétention. Si la purge échouait, elle ne doit pas faire déclarer à Sentry que
# la sauvegarde a échoué — l'archive est produite, validée et publiée. C'est un
# problème d'entretien du répertoire, que `check-backup.sh` surveille de son
# côté, et le script sortira quand même en erreur pour que le journal systemd
# le porte.
trap - ERR
sentry_checkin ok

echo "Sauvegarde créée et validée : ${BACKUP_FILE}"

find "$BACKUP_DIR" -name "medlink_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete
