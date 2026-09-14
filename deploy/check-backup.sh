#!/bin/bash
# ML-169 — Vérifie que la sauvegarde quotidienne se déclenche RÉELLEMENT.
#
#   sudo /opt/medlink/deploy/check-backup.sh
#
# Rejoue à la demande les tests manuels du ticket. Strictement en lecture : ne
# crée, ne supprime et ne restaure rien.
#
# Ce script n'alerte personne. Il faut le lancer pour savoir. La notification
# automatique en cas d'échec relève de ML-143, à traiter après ce ticket —
# sa prémisse d'origine (« le script tourne, seul son échec passe inaperçu »)
# était fausse tant que rien ne le déclenchait.
#
# Code de retour : 0 si tout passe, 1 si au moins un contrôle échoue.

set -uo pipefail

BACKUP_DIR="${MEDLINK_BACKUP_DIR:-/var/backups/medlink}"
RETENTION_DAYS=7
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

size=$(stat -c %s "$newest")
echo "  Taille : ${size} octets"

if gzip -t "$newest" 2>/dev/null; then
  ok "gzip -t : fichier gzip non corrompu"

  # ATTENTION : gzip -t ci-dessus ne prouve presque rien sur le dump lui-même.
  # backup.sh fait `pg_dump | gzip > archive` : si pg_dump meurt en cours de
  # route, gzip reçoit simplement EOF et referme proprement son flux. Le
  # résultat est une archive gzip parfaitement valide contenant un dump amputé.
  # Vérifié : un pg_dump simulé qui émet 30 tables puis sort en erreur produit
  # un fichier que `gzip -t` accepte et où le comptage ci-dessous trouve bien
  # 30 tables. Aucun des deux contrôles ne le distingue d'une sauvegarde saine.
  #
  # Le seul discriminant fiable est le marqueur de fin que pg_dump n'écrit
  # qu'après avoir tout produit. C'est donc lui qui porte le verdict.
  if zcat "$newest" 2>/dev/null | tail -5 | grep -q 'PostgreSQL database dump complete'; then
    ok "marqueur de fin de pg_dump présent — le dump est allé à son terme"
  else
    ko "marqueur de fin de pg_dump absent — dump interrompu, l'archive est incomplète malgré un gzip valide"
  fi

  tables=$(zcat "$newest" 2>/dev/null | grep -c '^CREATE TABLE' || true)
  echo "  Instructions CREATE TABLE : ${tables}"
  if [ "$tables" -eq 0 ]; then
    ko "dump vide — aucune table, l'archive ne restaurerait rien"
  elif [ "$tables" -lt 5 ]; then
    warn "seulement ${tables} table(s), le schéma MedLink en compte davantage : dump probablement partiel"
  else
    ok "le dump contient bien des tables"
  fi

  # Repère mesuré : la sauvegarde manuelle du 13/09/2026 pesait 20 091 octets
  # sur la base de production. Un pg_dump qui échoue d'emblée produit, lui, un
  # gzip vide de 20 octets — valide, et que seul ce plancher attrape.
  [ "$size" -gt 1024 ] || ko "archive suspecte (moins de 1 ko, alors qu'un dump réel de cette base en pèse une vingtaine de fois plus)"
else
  ko "gzip -t : fichier gzip corrompu ou tronqué"
  # Sur un .gz réellement tronqué, zcat restitue quand même le préfixe déjà
  # décodé : le comptage y trouverait assez de CREATE TABLE pour conclure
  # « schéma présent » juste après un échec d'intégrité. Deux verdicts
  # contradictoires dans le même rapport valent moins qu'un seul.
  echo "  (contrôle du contenu ignoré : il n'a pas de sens sur un fichier corrompu)"
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
