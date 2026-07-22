#!/bin/bash
# Build & déploiement de l'APK Android release (ML-97). Reprend les étapes
# documentées dans deploy/android-release.md — voir ce fichier pour les
# prérequis one-shot (eas login / eas init / eas credentials) et pour le
# détail de chaque étape.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MOBILE_DIR="$REPO_ROOT/frontend-mobile"
ENV_FILE="$SCRIPT_DIR/.env.deploy"

if [ ! -f "$ENV_FILE" ]; then
  echo "Fichier $ENV_FILE introuvable. Copiez deploy/.env.deploy.example vers deploy/.env.deploy et remplissez-le." >&2
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

for var in SERVER_HOST SERVER_USER DEPLOY_PATH; do
  if [ -z "${!var:-}" ]; then
    echo "Variable $var manquante dans $ENV_FILE" >&2
    exit 1
  fi
done
SERVER_PORT="${SERVER_PORT:-22}"

if ! command -v jq >/dev/null; then
  echo "jq est requis (apt install jq / brew install jq)." >&2
  exit 1
fi

echo "==> Build EAS (production, Android)…"
cd "$MOBILE_DIR"
BUILD_JSON=$(npx eas-cli build --platform android --profile production --non-interactive --wait --json)

BUILD_ID=$(echo "$BUILD_JSON" | jq -r '.[0].id')
BUILD_STATUS=$(echo "$BUILD_JSON" | jq -r '.[0].status')

if [ "${BUILD_STATUS^^}" != "FINISHED" ]; then
  echo "Le build EAS a échoué (status: $BUILD_STATUS). Voir https://expo.dev pour les logs." >&2
  exit 1
fi

echo "==> Build terminé (id: $BUILD_ID). Téléchargement…"
# `eas-cli build:download` télécharge désormais dans son propre cache
# (eas-build-run-cache), plus dans le dossier courant : on récupère donc
# l'URL de l'artefact via build:view et on la télécharge nous-mêmes.
ARCHIVE_URL=$(npx eas-cli build:view --json "$BUILD_ID" | jq -r '.artifacts.applicationArchiveUrl // empty')
if [ -z "$ARCHIVE_URL" ]; then
  echo "Impossible de récupérer l'URL de l'APK (artifacts.applicationArchiveUrl) pour le build $BUILD_ID." >&2
  exit 1
fi

APK_PATH="$MOBILE_DIR/medlink-latest.apk"
curl -fsSL -o "$APK_PATH" "$ARCHIVE_URL"

echo "==> APK prêt : $APK_PATH"
echo
read -r -p "Vérifié sur un appareil Android physique (pas seulement l'émulateur) ? Déployer sur $SERVER_HOST maintenant ? [y/N] " CONFIRM
if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
  echo "Déploiement annulé. L'APK reste disponible ici : $APK_PATH"
  exit 0
fi

echo "==> Déploiement vers $SERVER_HOST…"
scp -P "$SERVER_PORT" "$APK_PATH" \
  "$SERVER_USER@$SERVER_HOST:$DEPLOY_PATH/frontend/downloads/medlink-latest.apk"

echo "==> Déployé. Vérifiez https://medlink-app.fr/telecharger-app.html"
