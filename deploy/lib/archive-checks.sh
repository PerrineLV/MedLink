#!/bin/bash
# ML-143 — Contrôles de conformité d'une archive de sauvegarde.
#
# Bibliothèque sourcée par `backup.sh` (qui valide l'archive qu'il vient de
# produire, avant de la publier) et par `check-backup.sh` (qui contrôle la plus
# récente du répertoire). Les deux posaient sinon les mêmes contrôles,
# écrits deux fois et voués à diverger.
#
# Aucune des fonctions n'écrit sur stdout autre chose que son diagnostic, et
# aucune ne sort du script appelant : elles renvoient un code de retour, c'est
# à l'appelant de décider.

# Plancher de taille. Repère mesuré : la sauvegarde manuelle du 13/09/2026
# pesait 20 091 octets. Un pg_dump qui échoue d'emblée produit, lui, un gzip
# vide de 20 octets — valide, et que seul ce plancher attrape.
ARCHIVE_MIN_BYTES=1024

# Nombre de tables attendu dans un dump sain. En deçà, le dump est partiel.
# Mesuré : 14 `CREATE TABLE` sur le schéma de production au 18/09/2026.
ARCHIVE_MIN_TABLES=5

# Marqueur que pg_dump n'écrit qu'APRÈS avoir tout produit.
#
# C'est le seul contrôle qui distingue un dump complet d'un dump interrompu, et
# il n'est pas redondant avec `gzip -t`. `backup.sh` fait `pg_dump | gzip` : si
# pg_dump meurt en cours de route, gzip reçoit simplement EOF et referme
# proprement son flux. Le fichier obtenu est un **gzip parfaitement valide
# contenant un dump amputé**. Vérifié le 18/09/2026 — un pg_dump simulé qui
# émet 30 tables puis sort en erreur produit un fichier que `gzip -t` accepte
# et où le comptage des tables en trouve bien 30.
ARCHIVE_END_MARKER='PostgreSQL database dump complete'

# Vérifie une archive. Renvoie 0 si elle est exploitable, 1 sinon, et décrit
# sur stdout ce qui ne va pas.
#
#   archive_check <chemin>
archive_check() {
  local file="$1"
  local failures=0
  local size tables

  if [ ! -f "$file" ]; then
    echo "  [ÉCHEC] fichier absent : ${file}"
    return 1
  fi

  size=$(stat -c %s "$file")
  echo "  Taille : ${size} octets"

  if ! gzip -t "$file" 2>/dev/null; then
    echo "  [ÉCHEC] gzip -t : fichier gzip corrompu ou tronqué"
    # Sur un .gz réellement tronqué, zcat restitue quand même le préfixe déjà
    # décodé : les contrôles de contenu y trouveraient de quoi conclure
    # « schéma présent » juste après un échec d'intégrité. Deux verdicts
    # contradictoires dans le même rapport valent moins qu'un seul.
    echo "  (contrôles de contenu ignorés : sans objet sur un fichier corrompu)"
    return 1
  fi
  echo "  [OK]    gzip -t : fichier gzip non corrompu"

  if zcat "$file" 2>/dev/null | tail -5 | grep -q "$ARCHIVE_END_MARKER"; then
    echo "  [OK]    marqueur de fin de pg_dump présent — le dump est allé à son terme"
  else
    echo "  [ÉCHEC] marqueur de fin de pg_dump absent — dump interrompu, archive incomplète malgré un gzip valide"
    failures=$((failures + 1))
  fi

  tables=$(zcat "$file" 2>/dev/null | grep -c '^CREATE TABLE' || true)
  echo "  Instructions CREATE TABLE : ${tables}"
  if [ "$tables" -eq 0 ]; then
    echo "  [ÉCHEC] dump vide — aucune table, l'archive ne restaurerait rien"
    failures=$((failures + 1))
  elif [ "$tables" -lt "$ARCHIVE_MIN_TABLES" ]; then
    echo "  [ÉCHEC] seulement ${tables} table(s), le schéma en compte davantage : dump partiel"
    failures=$((failures + 1))
  else
    echo "  [OK]    le dump contient bien des tables"
  fi

  if [ "$size" -le "$ARCHIVE_MIN_BYTES" ]; then
    echo "  [ÉCHEC] archive suspecte (moins de ${ARCHIVE_MIN_BYTES} octets, alors qu'un dump réel de cette base en pèse une vingtaine de fois plus)"
    failures=$((failures + 1))
  fi

  [ "$failures" -eq 0 ]
}
