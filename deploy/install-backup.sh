#!/bin/bash
# ML-169 — Installe (ou réinstalle) la planification de la sauvegarde de la base.
#
# Idempotent : rejouable autant de fois que nécessaire, et rejoué à chaque
# déploiement par cd.yml. C'est le point du ticket — une planification posée une
# fois à la main en SSH ne survit pas à une réinstallation du serveur, et son
# absence ne produit aucune erreur, seulement du silence.
#
#   sudo /opt/medlink/deploy/install-backup.sh
#
# N'exécute aucune sauvegarde : l'installation ne doit pas produire d'archive
# qu'on confondrait ensuite avec un déclenchement automatique. Pour un essai
# immédiat, voir la fin de la sortie du script.

set -euo pipefail

DEPLOY_PATH="${MEDLINK_DEPLOY_PATH:-/opt/medlink}"
SYSTEMD_DIR="/etc/systemd/system"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

fail() {
  echo "ERREUR : $*" >&2
  exit 1
}

step() {
  echo
  echo "==> $*"
}

# --- Préalables ------------------------------------------------------------
# Tout est vérifié avant la première écriture : une installation à moitié faite
# serait pire que pas d'installation du tout, puisqu'elle aurait l'air installée.

step "Vérification des préalables"

[ "$(id -u)" -eq 0 ] || fail "à lancer en root (sudo $0)"

command -v systemctl >/dev/null 2>&1 || fail "systemctl introuvable — ce serveur n'utilise pas systemd, revoir ML-169 pour une variante cron"
command -v docker >/dev/null 2>&1 || fail "docker introuvable, alors que backup.sh appelle 'docker compose'"

for file in backup.sh systemd/medlink-backup.service systemd/medlink-backup.timer; do
  [ -f "${SOURCE_DIR}/${file}" ] || fail "fichier source manquant : ${SOURCE_DIR}/${file}"
done

# backup.sh porte son propre DEPLOY_PATH en dur. S'il diverge de celui utilisé
# ici, l'unité systemd pointerait vers un script qui, lui, irait chercher son
# .env ailleurs — et échouerait chaque nuit sans que personne ne le voie. Le
# ticket interdit de modifier la logique de backup.sh, donc on constate et on
# refuse d'installer plutôt que de corriger en douce.
declared_path=$(grep -m1 '^DEPLOY_PATH=' "${SOURCE_DIR}/backup.sh" | cut -d'"' -f2)
[ "$declared_path" = "$DEPLOY_PATH" ] || fail \
  "backup.sh déclare DEPLOY_PATH=\"${declared_path}\" alors que l'installation vise \"${DEPLOY_PATH}\". Aligner les deux avant d'installer."

[ -f "${DEPLOY_PATH}/.env" ] || fail \
  "${DEPLOY_PATH}/.env introuvable — backup.sh le source pour POSTGRES_USER/POSTGRES_DB et échouerait dès le premier déclenchement"
[ -f "${DEPLOY_PATH}/docker-compose.prod.yml" ] || fail \
  "${DEPLOY_PATH}/docker-compose.prod.yml introuvable — backup.sh en a besoin pour joindre le conteneur db"

echo "OK : root, systemd, docker, ${DEPLOY_PATH}/.env, docker-compose.prod.yml, sources présentes."

# --- Installation ----------------------------------------------------------

step "Installation du script de sauvegarde"

install -o root -g root -m 0750 "${SOURCE_DIR}/backup.sh" "${DEPLOY_PATH}/backup.sh"
echo "Installé : ${DEPLOY_PATH}/backup.sh (0750 root:root)"

step "Installation des unités systemd"

install -o root -g root -m 0644 "${SOURCE_DIR}/systemd/medlink-backup.service" "${SYSTEMD_DIR}/medlink-backup.service"
install -o root -g root -m 0644 "${SOURCE_DIR}/systemd/medlink-backup.timer" "${SYSTEMD_DIR}/medlink-backup.timer"
echo "Installé : ${SYSTEMD_DIR}/medlink-backup.{service,timer}"

systemctl daemon-reload

step "Contrôle statique des unités"

# Détecte notamment un ExecStart pointant vers un fichier absent ou non
# exécutable — la panne la plus probable, et qui ne se manifesterait sinon
# qu'au premier déclenchement, donc la nuit suivante.
systemd-analyze verify "${SYSTEMD_DIR}/medlink-backup.service" "${SYSTEMD_DIR}/medlink-backup.timer" || \
  echo "AVERTISSEMENT : systemd-analyze a signalé quelque chose ci-dessus, à lire avant de continuer." >&2

# Contrôle explicite plutôt que de s'en remettre aux avertissements ci-dessus,
# dont le code de retour n'est pas fiable selon les versions de systemd.
[ -x "${DEPLOY_PATH}/backup.sh" ] || fail "${DEPLOY_PATH}/backup.sh n'est pas exécutable après installation"

step "Activation du timer"

systemctl enable --now medlink-backup.timer

# --- Constat ---------------------------------------------------------------

step "État après installation"

systemctl is-enabled medlink-backup.timer
systemctl is-active medlink-backup.timer
systemctl list-timers --all medlink-backup.timer

cat <<EOF

--------------------------------------------------------------------------
Installation terminée. Elle ne prouve rien encore.

Une planification enregistrée n'est pas une exécution : c'est précisément la
confusion qui a permis au défaut de ML-169 d'exister. La seule preuve qui
compte est une archive datée du lendemain, apparue sans intervention.

  Essai immédiat (facultatif, produit une archive manuelle) :
    sudo systemctl start medlink-backup.service
    sudo journalctl -u medlink-backup.service -n 30 --no-pager

  Vérification du déclenchement automatique, à lancer demain :
    sudo ${DEPLOY_PATH}/deploy/check-backup.sh
--------------------------------------------------------------------------
EOF
