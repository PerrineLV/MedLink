#!/bin/bash
# ML-169 — Vérifie que la sauvegarde quotidienne se déclenche RÉELLEMENT.
#
#   sudo /opt/medlink/deploy/check-backup.sh
#
# Rejoue à la demande les tests manuels du ticket. Strictement en lecture : ne
# crée, ne supprime et ne restaure rien.
#
# Ce script reste un contrôle À LA DEMANDE : il faut le lancer pour savoir.
# La surveillance automatique est assurée depuis ML-143 par les check-ins
# Sentry Crons émis par `backup.sh` — c'est elle qui alerte sans qu'on demande
# rien, y compris quand la sauvegarde ne se déclenche pas du tout. Ce script
# garde son utilité pour un diagnostic ponctuel, et parce qu'il regarde ce que
# Sentry ne voit pas : l'état du répertoire, la rétention, le journal systemd.
#
# Code de retour : 0 si tout passe, 1 si au moins un contrôle échoue.

set -uo pipefail

DEPLOY_PATH="${MEDLINK_DEPLOY_PATH:-/opt/medlink}"
BACKUP_DIR="${MEDLINK_BACKUP_DIR:-/var/backups/medlink}"
RETENTION_DAYS=7

# ML-143 : les contrôles de conformité sont partagés avec `backup.sh`, qui les
# applique à l'archive qu'il vient de produire. Les écrire deux fois les
# aurait condamnés à diverger. On préfère la copie installée ; à défaut, celle
# du dépôt, pour que le script reste exécutable depuis un clone.
if [ -f "${DEPLOY_PATH}/lib/archive-checks.sh" ]; then
  # shellcheck source=lib/archive-checks.sh
  source "${DEPLOY_PATH}/lib/archive-checks.sh"
elif [ -f "$(dirname "${BASH_SOURCE[0]}")/lib/archive-checks.sh" ]; then
  # shellcheck source=lib/archive-checks.sh
  source "$(dirname "${BASH_SOURCE[0]}")/lib/archive-checks.sh"
else
  echo "ERREUR : lib/archive-checks.sh introuvable — relancer deploy/install-backup.sh" >&2
  exit 1
fi
# Le timer tourne à 04h17 : au-delà de 26 h sans archive, un déclenchement a été
# manqué. La marge de 2 h absorbe un rattrapage Persistent=true après un
# redémarrage, sans laisser passer une journée entière.
MAX_AGE_HOURS=26

failures=0
warnings=0

section() {
  echo
  echo "=== $* "
}

ok() { echo "  [OK]    $*"; }
ko() { echo "  [ÉCHEC] $*"; failures=$((failures + 1)); }
warn() { echo "  [ALERTE] $*"; warnings=$((warnings + 1)); }

# --- 1. La planification est-elle en place ? -------------------------------

section "Planification"

if ! command -v systemctl >/dev/null 2>&1; then
  ko "systemctl introuvable — la planification systemd ne peut pas être vérifiée"
else
  if [ "$(systemctl is-enabled medlink-backup.timer 2>/dev/null)" = "enabled" ]; then
    ok "medlink-backup.timer est activé (survivra à un redémarrage)"
  else
    ko "medlink-backup.timer n'est pas activé — lancer deploy/install-backup.sh"
  fi

  if [ "$(systemctl is-active medlink-backup.timer 2>/dev/null)" = "active" ]; then
    ok "medlink-backup.timer est actif"
  else
    ko "medlink-backup.timer n'est pas actif"
  fi

  echo
  systemctl list-timers --all medlink-backup.timer 2>/dev/null | sed 's/^/  /'
fi

# --- 2. Le dernier déclenchement s'est-il bien passé ? ---------------------
#
# Une archive présente sans trace d'exécution laisserait un doute sur son
# origine : c'est le journal qui distingue un déclenchement automatique d'une
# archive posée à la main.

section "Dernière exécution"

if command -v systemctl >/dev/null 2>&1; then
  result=$(systemctl show medlink-backup.service -p Result --value 2>/dev/null)
  exit_status=$(systemctl show medlink-backup.service -p ExecMainStatus --value 2>/dev/null)
  last_run=$(systemctl show medlink-backup.service -p ExecMainExitTimestamp --value 2>/dev/null)

  if [ -z "$last_run" ]; then
    ko "aucune exécution enregistrée pour medlink-backup.service"
  else
    echo "  Dernière fin d'exécution : ${last_run}"
    if [ "$result" = "success" ] && [ "$exit_status" = "0" ]; then
      ok "terminée avec succès (Result=${result}, code ${exit_status})"
    else
      ko "terminée en erreur (Result=${result}, code ${exit_status})"
    fi
  fi

  echo
  echo "  Dernières lignes du journal :"
  journalctl -u medlink-backup.service -n 10 --no-pager 2>/dev/null | sed 's/^/    /' \
    || echo "    (journal illisible — relancer avec sudo)"
fi

# --- 3. Les archives sont-elles là ? ---------------------------------------

section "Archives dans ${BACKUP_DIR}"

if [ ! -d "$BACKUP_DIR" ]; then
  ko "${BACKUP_DIR} n'existe pas — aucune sauvegarde n'a jamais abouti"
  echo
  echo "Bilan : ${failures} échec(s), ${warnings} alerte(s)."
  exit 1
fi

mapfile -t archives < <(find "$BACKUP_DIR" -maxdepth 1 -name 'medlink_*.sql.gz' -printf '%T@ %p\n' 2>/dev/null | sort -rn | cut -d' ' -f2-)
count=${#archives[@]}

if [ "$count" -eq 0 ]; then
  ko "aucune archive dans ${BACKUP_DIR}"
  echo
  echo "Bilan : ${failures} échec(s), ${warnings} alerte(s)."
  exit 1
fi

ls -lt "$BACKUP_DIR" | sed 's/^/  /'
echo
ok "${count} archive(s) présente(s)"

# Une seule archive est le symptôme exact du constat de ML-169 : un script
# lancé à la main une fois, et rien derrière. Avec une rétention de 7 jours et
# un déclenchement quotidien, le répertoire doit se stabiliser autour de 7 ou 8.
if [ "$count" -eq 1 ]; then
  warn "une seule archive : soit l'installation vient d'avoir lieu, soit rien ne déclenche le script (le symptôme d'origine de ML-169)"
fi

# --- 4. La plus récente est-elle fraîche ? ---------------------------------

newest="${archives[0]}"
age_seconds=$(( $(date +%s) - $(stat -c %Y "$newest") ))
age_hours=$(( age_seconds / 3600 ))

section "Fraîcheur"

echo "  Plus récente : $(basename "$newest") (il y a ${age_hours} h)"
if [ "$age_hours" -le "$MAX_AGE_HOURS" ]; then
  ok "moins de ${MAX_AGE_HOURS} h — un déclenchement quotidien a bien eu lieu"
else
  ko "plus de ${MAX_AGE_HOURS} h — au moins un déclenchement a été manqué"
fi

# --- 5. L'archive est-elle exploitable ? -----------------------------------
#
# Un fichier produit n'est pas un fichier restaurable. backup.sh redirige la
# sortie avant de savoir si pg_dump réussit : un échec laisse derrière lui une
# archive tronquée, qui a l'air d'une sauvegarde. Le contrôle d'intégrité au
# sein même du script relève de ML-143 ; ici on se contente de le constater.

section "Intégrité de la plus récente"

# Contrôles délégués à la bibliothèque partagée avec `backup.sh` : taille,
# intégrité gzip, marqueur de fin de pg_dump, présence de tables. Le détail de
# ce que chacun attrape — et notamment pourquoi `gzip -t` ne suffit pas — est
# documenté dans lib/archive-checks.sh.
if archive_check "$newest"; then
  ok "archive conforme"
else
  ko "archive non conforme (motifs ci-dessus)"
fi

# --- 6. La rétention fait-elle son travail ? -------------------------------

section "Rétention (${RETENTION_DAYS} jours)"

stale=$(find "$BACKUP_DIR" -maxdepth 1 -name 'medlink_*.sql.gz' -mtime "+$((RETENTION_DAYS + 1))" | wc -l)
if [ "$stale" -eq 0 ]; then
  ok "aucune archive de plus de $((RETENTION_DAYS + 1)) jours"
else
  ko "${stale} archive(s) au-delà de la rétention — le 'find -delete' de backup.sh ne passe pas"
fi

# --- Bilan -----------------------------------------------------------------

echo
echo "=========================================================="
echo "Bilan : ${failures} échec(s), ${warnings} alerte(s)."
echo
echo "Ces contrôles prouvent que des fichiers sont produits, pas qu'ils sont"
echo "restaurables. La restauration d'essai dans une base jetable reste la"
echo "seule validation complète — procédure dans deploy/backup.md."
echo "=========================================================="

[ "$failures" -eq 0 ]
