# Sauvegarde de la base de production

Référence : ML-74 (script), ML-169 (planification), ML-143 (validation de l'archive et surveillance Sentry).

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
| **Quand** | tous les jours à **04h17 UTC**, soit 06h17 à Paris en été et 05h17 en hiver — le VPS est réglé sur UTC (constaté le 14/09/2026) |
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
| `deploy/backup.sh` | le script de sauvegarde (ML-74), sa validation d'archive et ses check-ins Sentry (ML-143) |
| `deploy/lib/archive-checks.sh` | contrôles de conformité d'une archive, partagés par les deux scripts (ML-143) |
| `deploy/systemd/medlink-backup.service` | l'unité qui exécute le script |
| `deploy/systemd/medlink-backup.timer` | le déclenchement quotidien |
| `deploy/install-backup.sh` | installe ou réinstalle le tout, idempotent |
| `deploy/check-backup.sh` | vérifie que le déclenchement a réellement lieu |

Sur le serveur, après installation :

| Emplacement | Contenu |
|---|---|
| `/opt/medlink/deploy/` | les fichiers ci-dessus, tels que déposés par la CD |
| `/opt/medlink/backup.sh` | la copie **installée**, celle que systemd exécute (0750 root:root) |
| `/opt/medlink/lib/archive-checks.sh` | la bibliothèque installée, sourcée par `backup.sh` et `check-backup.sh` |
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

La règle n'autorise qu'une seule commande, ce qui a une conséquence
contre-intuitive : **`sudo -n true` échoue quand même**, puisque `true` n'est
couvert par aucune règle NOPASSWD. Ce n'est donc pas un test valide de la
présence de la règle — la première version du job `schedule-backup` s'en
servait et échouait systématiquement, y compris avec une règle correcte. Pour
vérifier la règle, exécuter la commande réellement autorisée :

```bash
sudo -n /bin/bash /opt/medlink/deploy/install-backup.sh
```

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
gzip, **présence du marqueur de fin de `pg_dump`**, présence de tables, et
application de la rétention. Code de retour 0 si tout passe, 1 sinon.

Le marqueur de fin est le contrôle qui compte, et il n'est pas redondant avec
`gzip -t`. Voir « Une archive publiée est une archive valide » plus bas : une
archive peut être un gzip parfaitement valide *et* un dump amputé.

**Ce script n'alerte personne** : il faut le lancer pour savoir. C'est
volontaire, et ce n'est plus un manque depuis ML-143 — la surveillance
automatique est assurée par les check-ins Sentry émis par `backup.sh`, qui
alertent sans qu'on demande rien, y compris quand la sauvegarde ne se déclenche
pas du tout. Ce script garde son utilité pour un diagnostic ponctuel, et parce
qu'il regarde ce que Sentry ne voit pas : l'état du répertoire, la rétention,
le journal systemd.

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
ARCHIVE=/var/backups/medlink/medlink_2026-09-18_04h17.sql.gz

# Même image que la prod, pour que le test prouve quelque chose
docker run --rm -d --name medlink_restore_test \
  -e POSTGRES_USER=medlink -e POSTGRES_DB=medlink -e POSTGRES_PASSWORD=test \
  postgres:16-alpine

# NE PAS attendre avec `pg_isready` : l'image officielle démarre un serveur
# TEMPORAIRE pour initdb, puis l'arrête et relance le vrai. `pg_isready` répond
# « accepting connections » sur le serveur temporaire, la restauration part
# aussitôt et tombe sur « FATAL: the database system is shutting down ».
# Constaté le 18/09/2026 — c'est exactement l'erreur que cette procédure
# contenait au départ.
#
# On attend donc le marqueur que l'entrypoint n'écrit qu'après avoir relancé le
# vrai serveur, puis on teste une vraie connexion plutôt qu'un indicateur
# approchant. Boucle bornée à 60 s pour ne pas tourner indéfiniment.
until docker logs medlink_restore_test 2>&1 | grep -q "init process complete"; do sleep 1; done
for i in $(seq 1 60); do
  docker exec medlink_restore_test psql -U medlink -d medlink -c 'SELECT 1' >/dev/null 2>&1 && break
  sleep 1
done

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

Une restauration réussie affiche les `CREATE TABLE`, les `COPY <n>` par table, les
`CREATE INDEX`, les `ALTER TABLE` de clés étrangères **et une série de `setval`** —
ces derniers comptent : sans eux les séquences repartiraient de zéro et les
prochaines insertions entreraient en collision avec les identifiants restaurés.

**Dernière restauration d'essai réussie : le 18/09/2026**, depuis
`medlink_2026-09-18_04h17.sql.gz`, une archive produite par le timer et non à la
main. Résultat : 14 tables, 20 comptes, 15 entrées de journal, 9 rendez-vous.

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

## Surveillance : Sentry Crons (ML-143)

`backup.sh` émet un **check-in** à Sentry au démarrage (`in_progress`), puis à
la fin (`ok` ou `error`). Sentry alerte dans deux situations, et c'est
délibérément deux et pas une :

| Situation | Ce qui la révèle |
| --- | --- |
| La sauvegarde a échoué | un check-in `error` arrive |
| La sauvegarde **ne s'est pas déclenchée** | **aucun check-in n'arrive** dans la fenêtre attendue |

Le second cas est celui qu'une alerte sur échec ne peut pas couvrir : un travail
qui ne démarre pas ne produit aucune erreur à notifier. C'est exactement la
panne de ML-169, restée invisible pendant des mois, et la raison pour laquelle
un simple `OnFailure=` systemd n'aurait pas suffi.

Le monitor est **créé et reconfiguré par le script lui-même**, via
`monitor_config` dans le premier check-in : sa planification (04h17 UTC, marge
de 30 min, durée maximale de 30 min) vit dans `backup.sh`, versionnée, plutôt
que d'être cliquée dans l'interface Sentry — où elle disparaîtrait à la
première réinstallation sans que personne ne sache ce qu'elle contenait.

### Configurer `SENTRY_CRONS_URL`

L'URL de check-in se dérive du DSN Sentry du projet, déjà présent sur le
serveur. À exécuter **sur le serveur**, pour que le DSN n'en sorte pas :

```bash
DSN=$(grep '^SENTRY_DSN=' /opt/medlink/backend/.env | cut -d= -f2- | tr -d '"')
KEY=$(echo "$DSN"  | sed -E 's#^https://([^@]+)@.*#\1#')
HOST=$(echo "$DSN" | sed -E 's#^https://[^@]+@([^/]+)/.*#\1#')
PROJ=$(echo "$DSN" | sed -E 's#.*/([0-9]+)$#\1#')

echo "SENTRY_CRONS_URL=https://${HOST}/api/${PROJ}/cron/medlink-backup/${KEY}/" \
  | sudo tee -a /opt/medlink/.env
```

`install-backup.sh` **refuse d'installer** si cette variable est absente ou
vide. C'est voulu : à l'exécution, `backup.sh` tolère un Sentry injoignable —
un incident de surveillance ne doit pas faire échouer une sauvegarde saine —
mais à l'installation, la même tolérance produirait une sauvegarde qui tourne
sans que personne ne surveille rien, en donnant l'impression du contraire.

### Vérifier que la surveillance fonctionne

Après installation, une exécution manuelle doit faire apparaître le monitor
dans Sentry :

```bash
sudo systemctl start medlink-backup.service
```

Puis, dans Sentry, **Crons → `medlink-backup`** : le monitor doit exister et
afficher un check-in réussi. S'il n'apparaît pas, le journal porte
`AVERTISSEMENT : check-in Sentry ... non transmis` — l'URL est alors erronée,
et la sauvegarde tourne sans surveillance.

## Une archive publiée est une archive valide

Depuis ML-143, `backup.sh` produit dans un fichier temporaire `.partial`, le
valide, et ne le renomme à son nom définitif **qu'ensuite**. En cas d'échec, le
fichier partiel est supprimé et rien n'est publié.

Avant ça, la redirection shell créait et tronquait le fichier d'archive *avant*
que `pg_dump` ne s'exécute : un échec laissait derrière lui une archive
incomplète, que la rétention conservait sept jours.

Et cette archive était plus trompeuse qu'il n'y paraît. Le script fait
`pg_dump | gzip` : si `pg_dump` meurt en cours de route, `gzip` reçoit
simplement EOF et referme proprement son flux. **Le fichier obtenu est un gzip
parfaitement valide contenant un dump amputé.** Vérifié : un `pg_dump` simulé
qui émet 30 tables puis sort en erreur produit un fichier que `gzip -t` accepte
et où le comptage des `CREATE TABLE` en trouve bien 30.

Le seul discriminant fiable est le marqueur `-- PostgreSQL database dump
complete`, que `pg_dump` n'écrit qu'après avoir tout produit. C'est lui qui
porte le verdict ; `gzip -t`, le comptage de tables et le plancher de taille ne
couvrent que les cas grossiers.

Ces contrôles vivent dans **`deploy/lib/archive-checks.sh`**, sourcé aussi bien
par `backup.sh` — qui valide l'archive qu'il vient de produire — que par
`check-backup.sh` — qui contrôle la plus récente du répertoire. Les écrire deux
fois les aurait condamnés à diverger.

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
