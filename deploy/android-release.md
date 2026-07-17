# Build & distribution de l'APK Android (ML-97)

> Les étapes "Build release" à "Déploiement sur le VPS" ci-dessous sont
> automatisées par `deploy/build-mobile-release.sh` (voir prérequis dans ce
> script et son `deploy/.env.deploy.example`). Ce document reste la
> référence détaillée de chaque étape et couvre les prérequis one-shot que
> le script ne fait pas.

MedLink n'est pas publiée sur le Play Store. L'app mobile est distribuée en
sideload : un APK release signé, téléchargeable depuis
`https://medlink-app.fr/telecharger-app.html`.

Le build est géré par **EAS Build** (cloud Expo) — le projet est en workflow
managé (pas de dossier `android/` commité), et EAS gère le keystore de
signature à notre place (option la plus sûre : pas de risque de perte locale
d'un keystore critique).

## Prérequis (à faire une seule fois)

1. Créer un compte Expo (gratuit) sur https://expo.dev si besoin.
2. `npm install -g eas-cli` (ou `npx eas-cli`)
3. Depuis `frontend-mobile/` : `eas login`
4. `eas init` — lie ce projet à un projet EAS, ajoute `extra.eas.projectId`
   dans `app.json` automatiquement.
5. `eas credentials` → laisser EAS générer et stocker le keystore Android au
   premier build (répondre "Generate new keystore" quand demandé). Le
   keystore reste custody EAS ; il peut être exporté via
   `eas credentials` → Android → Download credentials si une sauvegarde
   locale est aussi voulue.

## Build release

```bash
cd frontend-mobile
eas build --platform android --profile production
```

Le profil `production` (`eas.json`) :

- produit un `.apk` (pas un `.aab`, puisqu'il n'y a pas de soumission Play
  Store) ;
- utilise `appVersionSource: "remote"` + `autoIncrement: true` : le
  `versionCode` est géré et incrémenté automatiquement par EAS à chaque
  build, pas de bump manuel à retenir. Le `versionName` reste celui
  d'`app.json` (`expo.version`).
- injecte `EXPO_PUBLIC_API_URL=https://medlink-app.fr/api` (`eas.json` →
  `build.production.env`). **Important** : sans cette variable,
  `frontend-mobile/config.js` retombe sur `http://10.0.2.2:8080/api` pour
  Android — une adresse qui ne fonctionne que dans l'émulateur Android
  (alias vers le `localhost` de la machine de build), jamais sur un
  téléphone physique. Ne jamais lancer `eas build --profile production`
  sans que ce champ `env` soit renseigné dans `eas.json`.

Le build tourne sur les serveurs Expo (quelques minutes). Une fois terminé,
télécharger l'artefact :

```bash
eas build:list -p android --limit 1
```

Récupérer l'ID du build affiché, puis :

```bash
eas build:download --build-id <id-affiché>
```

Renommer ensuite le fichier téléchargé :

```bash
mv <fichier-téléchargé>.apk medlink-latest.apk
```

## Déploiement sur le VPS

Même serveur que le backend/frontend-web (secrets `SERVER_HOST`,
`SERVER_USER`, `SSH_PRIVATE_KEY`, `DEPLOY_PATH` déjà utilisés par `cd.yml`).

```bash
scp -P "$SERVER_PORT" medlink-latest.apk \
  "$SERVER_USER@$SERVER_HOST:$DEPLOY_PATH/frontend/downloads/medlink-latest.apk"
```

Le fichier atterrit dans `frontend/downloads/`, **à côté** du dossier `dist/`
swappé à chaque déploiement de `frontend-web` (pas dedans). L'étape
"Atomically swap" de `cd.yml` recrée à chaque déploiement un lien symbolique
`dist/downloads -> ../downloads`, donc le fichier reste accessible à la même
URL (`/downloads/medlink-latest.apk`) sans jamais être écrasé par un
déploiement du frontend web — aucune config nginx supplémentaire nécessaire
(suppose que le nginx du VPS suit les liens symboliques, comportement par
défaut).

⚠️ **Migration unique** (une seule fois, avant/juste après le premier
déploiement de ce mécanisme) : si un ancien APK a été déposé manuellement
dans `dist/downloads/` avant ce correctif, le déplacer vers l'emplacement
persistant avant qu'un déploiement frontend-web n'écrase `dist/` :

```bash
ssh "$SERVER_USER@$SERVER_HOST" \
  "mkdir -p $DEPLOY_PATH/frontend/downloads && \
   mv $DEPLOY_PATH/frontend/dist/downloads/medlink-latest.apk $DEPLOY_PATH/frontend/downloads/medlink-latest.apk"
```

⚠️ Le téléversement d'un nouvel APK écrase le précédent. Le déployer
seulement après avoir vérifié le build (étape suivante).

## Vérification avant/après déploiement

- Installation manuelle sur un appareil Android physique (pas seulement un
  émulateur) : télécharger depuis `/telecharger-app.html`, activer
  « Installer des applications inconnues », installer.
- Numéro de version cohérent : `versionName` de l'APK installé (visible dans
  Android → Paramètres → Applications → MedLink → informations sur
  l'app) doit correspondre à `frontend-mobile/app.json` → `expo.version`.
  L'affichage du numéro de version **dans l'app elle-même** (écran Mon
  compte) est couvert par ML-89, pas encore fait — à recroiser une fois ce
  ticket livré.

## Hors périmètre (voir ML-97)

- Publication Play Store / App Store.
- Mise à jour automatique / OTA.
- Build iOS.
