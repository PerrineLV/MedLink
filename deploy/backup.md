# Sauvegarde de la base de production

Référence : ML-74 (script), ML-169 (planification), ML-143 (notification d'échec, à venir).

## Pourquoi ce document existe

Le script de sauvegarde existait depuis ML-74 et fonctionnait. Il n'était
déclenché par rien. Découvert le 13/09/2026 : `sudo crontab -l` répondait `no
crontab for root`, et `/var/backups/medlink/` ne contenait qu'une seule archive
— celle créée à la main quelques minutes plus tôt pendant l'investigation.

Personne ne s'en était aperçu parce qu'**un dispositif de sauvegarde qui ne
tourne pas ne produit aucune erreur. Il produit du silence, qui ressemble
exactement à du succès.**

D'où deux principes qui gouvernent tout ce qui suit :

1. La planification est **versionnée dans le dépôt** et réappliquée à chaque
   déploiement. Une commande tapée une fois en SSH ne survit pas ; c'est très
   probablement ce qui s'est passé.
2. **Une planification enregistrée n'est pas une exécution.** La seule preuve
   qui compte est une archive datée du lendemain, apparue sans intervention.

## Ce qui tourne, et quand

| | |
|---|---|
| **Quoi** | `pg_dump` de la base de production, compressé en gzip |
| **Quand** | tous les jours à **04h17**, heure locale du serveur |
| **Où** | `/var/backups/medlink/medlink_AAAA-MM-JJ_HHhMM.sql.gz` |
| **Sous quelle identité** | `root` (écrit dans `/var/backups`, pilote Docker) |
| **Rétention** | 7 jours, par `find -mtime +7 -delete` à la fin du script |
| **Rattrapage** | oui — `Persistent=true`, une sauvegarde manquée pour cause de serveur éteint est reprise au démarrage suivant |

L'heure n'est pas arbitraire. Le seul travail planifié concurrent du projet,
`update-medications.yml`, s'exécute intégralement sur un runner GitHub et ne
touche jamais ce serveur : il n'entre pas en compte. Le vrai concurrent est le
déploiement CD, déclenché par un merge sur `main`, donc en journée — d'où une
heure de nuit. Et `:17` plutôt que `:00` parce que les tâches système d'une
distribution se groupent sur les heures rondes.

## Les fichiers

Dans le dépôt :

| Fichier | Rôle |
|---|---|
| `deploy/backup.sh` | le script de sauvegarde (ML-74) |
| `deploy/systemd/medlink-backup.service` | l'unité qui exécute le script |
| `deploy/systemd/medlink-backup.timer` | le déclenchement quotidien |
| `deploy/install-backup.sh` | installe ou réinstalle le tout, idempotent |
| `deploy/check-backup.sh` | vérifie que le déclenchement a réellement lieu |

Sur le serveur, après installation :

| Emplacement | Contenu |
|---|---|
| `/opt/medlink/deploy/` | les fichiers ci-dessus, tels que déposés par la CD |
| `/opt/medlink/backup.sh` | la copie **installée**, celle que systemd exécute (0750 root:root) |
| `/etc/systemd/system/medlink-backup.{service,timer}` | les unités installées |
| `/var/backups/medlink/` | les archives |

La distinction entre `/opt/medlink/deploy/backup.sh` (la source déposée) et
`/opt/medlink/backup.sh` (la copie installée) est voulue : l'unité systemd
pointe vers un chemin fixe qui ne dépend pas de l'endroit d'où l'installation a
été lancée.

## Installation

### Prérequis : le sudo de l'utilisateur de déploiement

La CD réapplique la planification à chaque déploiement, ce qui suppose que
l'utilisateur SSH puisse devenir root **sans mot de passe**, sur ce seul script.
Créer `/etc/sudoers.d/medlink-backup` (avec `sudo visudo -f`, jamais avec un
éditeur direct — une erreur de syntaxe dans sudoers peut verrouiller l'accès
root) :

```
<utilisateur-de-déploiement> ALL=(root) NOPASSWD: /bin/bash /opt/medlink/deploy/install-backup.sh
```

> **À savoir avant de poser cette règle.** Le script visé est réécrit par la CD
> à chaque déploiement. Autoriser son exécution en root sans mot de passe
> revient donc, en pratique, à donner root sur ce serveur à quiconque peut
> pousser sur `main`. Sur ce projet — un seul développeur, un dépôt privé, une
> branche `main` protégée — c'est un compromis acceptable. Il cesserait de
> l'être dès qu'une deuxième personne obtiendrait le droit de push.
>
> Pour s'en passer : ne pas poser la règle, laisser le job `schedule-backup` de
> la CD échouer visiblement, et lancer l'installation à la main (ci-dessous)
> après chaque réinstallation du serveur. On échange une élévation de privilège
> automatique contre une étape manuelle à ne pas oublier — c'est exactement
> l'oubli qui a créé ML-169.

### Installation manuelle

Depuis un clone du dépôt, ou depuis `/opt/medlink/deploy` après un déploiement :

```bash
sudo /opt/medlink/deploy/install-backup.sh
```

Le script est idempotent et refuse d'installer quoi que ce soit si un prérequis
manque, plutôt que de laisser une installation à moitié faite — qui aurait
l'air installée. Il vérifie notamment que le `DEPLOY_PATH` codé en dur dans
`backup.sh` correspond bien à celui visé par l'installation : s'ils divergent,
l'unité pointerait vers un script qui irait chercher son `.env` ailleurs, et
échouerait chaque nuit sans que personne ne le voie.

Il **ne lance aucune sauvegarde** : une archive créée pendant l'installation se
confondrait ensuite avec un déclenchement automatique.

### Réinstallation automatique

Le job `schedule-backup` de `.github/workflows/cd.yml` dépose les fichiers et
relance `install-backup.sh` après chaque déploiement réussi sur `main`. C'est un
job séparé du job `deploy` : une planification qui échoue à s'installer doit se
voir (job rouge), pas déclencher le rollback d'un déploiement par ailleurs sain.

## Vérifier que ça tourne vraiment

```bash
sudo /opt/medlink/deploy/check-backup.sh
```

Contrôle, en lecture seule : timer activé et actif, prochaine échéance, résultat
de la dernière exécution, journal, présence et fraîcheur des archives, intégrité
gzip, présence du schéma dans le dump, et application de la rétention. Code de
retour 0 si tout passe, 1 sinon.

**Ce script n'alerte personne.** Il faut le lancer pour savoir. La notification
automatique en cas d'échec est le périmètre de ML-143 — dont la prémisse
d'origine (« le script tourne quotidiennement, seul son échec passe inaperçu »)
était fausse tant que rien ne le déclenchait, et reste à relire à cette
lumière.

Contrôles à la main, si besoin :

```bash
systemctl list-timers --all medlink-backup.timer   # prochaine échéance, dernier passage
journalctl -u medlink-backup.service -n 50         # trace d'exécution
ls -lt /var/backups/medlink/                       # les archives
```

Une archive présente **sans trace dans le journal** laisse un doute sur son
origine : c'est le journal qui distingue un déclenchement automatique d'une
archive posée à la main.

## Restaurer

C'est la seule validation qui compte. Produire des fichiers n'est pas les rendre
restaurables, et c'est précisément cette confusion qui a permis au défaut
d'exister.

La restauration d'essai se fait dans un **conteneur PostgreSQL jetable**, jamais
dans l'instance de production — même en visant une base de test, on ajouterait
de la charge et du volume à la base réelle.

```bash
ARCHIVE=/var/backups/medlink/medlink_2026-09-15_04h17.sql.gz

# Même image que la prod, pour que le test prouve quelque chose
docker run --rm -d --name medlink_restore_test \
  -e POSTGRES_USER=medlink -e POSTGRES_DB=medlink -e POSTGRES_PASSWORD=test \
  postgres:16-alpine

until docker exec medlink_restore_test pg_isready -U medlink -d medlink; do sleep 1; done

# Le dump est du SQL brut (pg_dump sans -F) : c'est psql qui restaure, pas pg_restore
zcat "$ARCHIVE" | docker exec -i medlink_restore_test psql -U medlink -d medlink

# Les données attendues sont-elles là ?
docker exec medlink_restore_test psql -U medlink -d medlink -c '\dt'
docker exec medlink_restore_test psql -U medlink -d medlink \
  -c 'SELECT count(*) AS comptes FROM "user";' \
  -c 'SELECT count(*) AS entrees_journal FROM journal_entry;' \
  -c 'SELECT count(*) AS rendez_vous FROM appointment;'

docker stop medlink_restore_test
```

Restaurer une archive **produite automatiquement**, pas une archive créée à la
main pour l'occasion : c'est le résultat du déclenchement réel qu'on valide.

### Restauration réelle en production

Même principe, mais vers la base de production, application arrêtée :

```bash
cd /opt/medlink
docker compose -f docker-compose.prod.yml stop app
zcat "$ARCHIVE" | docker compose -f docker-compose.prod.yml exec -T db \
  psql -U medlink -d medlink
docker compose -f docker-compose.prod.yml start app
```

À n'exécuter qu'en connaissance de cause : le dump ne contient pas de `DROP
DATABASE`, la restauration se superpose donc au schéma existant. Pour repartir
propre, supprimer et recréer la base avant de restaurer.

## Limite connue

`backup.sh` redirige sa sortie vers le fichier d'archive **avant** de savoir si
`pg_dump` a réussi. Un échec laisse donc derrière lui une archive tronquée, qui
a l'air d'une sauvegarde et que la rétention conservera sept jours.
`check-backup.sh` le détecte après coup (`gzip -t`, comptage des `CREATE
TABLE`), mais le contrôle au sein même du script, et l'alerte qui va avec,
relèvent de ML-143. Le périmètre de ML-169 était le déclenchement, pas la
robustesse du script — volontairement, pour ne pas mélanger les deux.

## Ce qui doit figurer en section 5.1 du dossier (ML-84)

La procédure décrite au dossier repose sur des archives qui n'existaient pas.
À mettre à jour avec, au minimum :

- l'emplacement réel des archives : `/var/backups/medlink/`
- le mode de planification : timer systemd `medlink-backup.timer`, quotidien à
  04h17, versionné dans `deploy/systemd/` et réinstallé à chaque déploiement
- la rétention effective : 7 jours glissants, soit 7 à 8 archives en régime
  établi
- la procédure de restauration ci-dessus, avec la mention qu'elle a été
  **réellement essayée** depuis une archive produite automatiquement, et à
  quelle date
- la limite connue et son renvoi à ML-143
