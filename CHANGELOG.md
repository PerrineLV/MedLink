# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

### Added
- Configuration du build Android release signé pour distribution hors Play Store (sideload) : `android.package` dans `app.json`, `eas.json` (profil `production`, build `apk`, `versionCode` géré et auto-incrémenté par EAS via `appVersionSource: "remote"`, `EXPO_PUBLIC_API_URL` injectée vers l'API prod pour éviter que l'APK pointe vers l'adresse de bouclage de l'émulateur Android), page de téléchargement `frontend-web/public/telecharger-app.html` avec instructions d'installation manuelle, procédure de build/déploiement documentée dans `deploy/android-release.md` (ML-97)
- `frontend/downloads/` (contenant l'APK Android) rendu persistant aux déploiements du frontend web : `cd.yml` recrée un lien symbolique `dist/downloads -> ../downloads` à chaque swap atomique, au lieu de stocker l'APK dans `dist/` qui est intégralement remplacé à chaque déploiement (ML-97)
- Mot de passe oublié : un utilisateur non connecté peut demander un lien de réinitialisation par email et redéfinir son mot de passe (backend `PasswordResetToken` + `POST /api/password-reset/{request,confirm}`, écrans web `/forgot-password` et `/reset-password`, écrans mobile équivalents avec saisie manuelle du code reçu par email en secours). Réponse anti-énumération systématique sur la demande, rate limiting 5/min par IP, token à usage unique valable 1h, invalidation des refresh tokens actifs après reset. Ajoute `symfony/mailer` + Mailpit (service `mailer` dans `docker-compose.yml`, UI sur http://localhost:8025) pour capter les emails en dev (ML-78)
- Deep link mobile pour la réinitialisation de mot de passe : la requête `/api/password-reset/request` accepte un champ `platform` (`web`/`mobile`) pour choisir le bon lien dans l'email (`https://.../reset-password?token=...` ou `medlink://reset-password?token=...`), `app.json` déclare le schéma `medlink` et `ResetPasswordScreen` pré-remplit le token reçu via le lien. Fonctionne uniquement sur un build natif autonome (EAS build / dev-client) — Expo Go ne gère pas les schémas personnalisés (ML-78)

### Fixed
- Un aidant sans patient rattaché pouvait accéder au formulaire de saisie de journal (web + mobile) et déclencher une erreur 500 côté API en le soumettant ; formulaire désormais masqué côté front pour ce cas, et l'endpoint de création d'entrée renvoie une 403 claire en défense en profondeur (ML-85)
- Drift de trigger CI entre `main`/`develop` et les anciennes branches `epicX--` provoquant des runs en double (push + pull_request) sur une même PR ; ajout d'un bloc `concurrency` à `ci.yml` pour absorber ce type de cas à l'avenir (ML-86)
- Warnings CI de dépréciation Node 20 : mise à jour de `actions/checkout` (v4→v7), `actions/setup-node` (v4→v6), `actions/cache` (v4→v6), `docker/build-push-action` (v6→v7) et `docker/login-action` (v3→v4) vers leurs dernières majeures (runtime Node 24) dans `ci.yml`/`cd.yml` (ML-80)
- Warning ESLint `react/only-export-components` cassant le Fast Refresh sur `AuthContext.jsx`, `MessagesBadgeContext.jsx` et `InvitationsBadgeContext.jsx` : extraction des hooks (`useAuth`, `useMessagesBadge`, `useInvitationsBadge`) dans des fichiers dédiés, les fichiers de contexte ne conservant plus que le composant Provider (ML-80)
- Workflow `update-medications.yml` échouait à l'étape de création de PR (`GitHub Actions is not permitted to create or approve pull requests`) : remplacement de `peter-evans/create-pull-request` par un commit + push direct sur la branche dédiée `bot/update-medications`, à relire et merger manuellement — évite d'élargir les permissions Actions à tout le dépôt. Une Issue GitHub (label `automated`) est ouverte automatiquement quand une mise à jour est en attente de revue, réutilisée d'un run à l'autre tant qu'elle n'est pas fermée (ML-96)
- Check `Prettier check` en échec en CI sur `frontend-mobile/app.json` et `frontend-web/public/telecharger-app.html` (fichiers ajoutés hors format Prettier) : reformatage, aucun changement de contenu (ML-97)
<<<<<<< HEAD
- Race condition mobile au premier login : `JournalScreen` affichait systématiquement "Impossible de charger le journal de suivi" avant de fonctionner au refresh suivant. Le header `Authorization` était posé dans un `useEffect` d'`AuthContext` (parent), déclenché après le montage de `JournalScreen` (enfant) dans le même commit React que `setToken()` — le fetch initial partait donc sans header. Remplacé par un intercepteur `axios` sur `httpClient` lisant une valeur mise à jour de façon synchrone par `login()`/`logout()`, sans passer par un effet (ML-100)
=======
- Expo Go affichait systématiquement "Something went wrong" en dev depuis la liaison du projet à EAS (`extra.eas.projectId`/`owner` dans `app.json`, ML-97) : à chaque requête de manifest, le CLI tentait de récupérer un certificat de développement en se connectant à un compte Expo, ce qui échouait en boucle en mode non-interactif dans le conteneur Docker (`CommandError: Input is required, but 'npx expo' is in non-interactive mode`). Ajout de `EXPO_OFFLINE: 1` à l'environnement du service `mobile` dans `docker-compose.yml` pour désactiver ces appels réseau en dev local ; ajout de `ios.bundleIdentifier` (`fr.medlinkapp.mobile`) manquant dans `app.json` en prévision d'un futur build EAS iOS
>>>>>>> 0c0000c (correction expo go)

### Changed
- `pull_request.branches` de `ci.yml` inclut désormais `"epic*"`, pour permettre le workflow "une sous-branche par ticket" (PR `ticket → epicX--` vérifiée individuellement avant la PR finale `epicX-- → develop`) (ML-86)
- Port du bundler Metro (mobile) rendu configurable via `METRO_PORT` (défaut 8083, au lieu de 8081 en dur) : sur la machine de dev, le port 8081 est déjà occupé par un conteneur d'un autre projet, ce qui faisait échouer Expo Go silencieusement avec "Something went wrong" sans lien avec le réseau

## [1.1.0] - 2026-07-12

### Added
- Intégration Sentry et journalisation Monolog des événements de sécurité (login échoué, 403, 5xx) sans donnée personnelle (ML-31)
- Sauvegarde automatisée de la base de données en production (ML-74)
- Déploiement du frontend web en production (ML-75)
- Processus de consignation des anomalies : template GitHub Issues et labels de priorité (ML-39)
- Suivi automatisé des mises à jour de dépendances via Dependabot sur les 3 écosystèmes du monorepo (ML-40)

### Changed
- Mise à jour de dépendances via Dependabot après revue individuelle : backend (api-platform/doctrine-orm, api-platform/symfony, phpstan/phpstan, phpstan/phpdoc-parser, php-cs-fixer) et frontend web (oxlint, vite, prettier) (ML-40)
- Tentative de montée de version de l'écosystème Expo/React Native (SDK 57, puis 56, puis 55) : abandonnée, Expo Go (Play Store) ne supportant encore aucun de ces SDK ; reste sur SDK 54 (ML-90)

### Fixed
- Corrections du pipeline CD (ML-37, ML-38)
- Perte de session au rechargement de page sur le web malgré un JWT valide (ML-39)
- Contrainte de version PHP dans composer.json incohérente avec l'image Docker/CI, bloquant la résolution des mises à jour de dépendances backend par Dependabot (ML-40)
- Tag `environment` Sentry non mappé sur `APP_ENV`, empêchant le filtrage des issues par environnement ; conteneur de production tournant en réalité avec `APP_ENV=dev`/`APP_DEBUG=1` faute de surcharge explicite dans `docker-compose.prod.yml` (ML-88)
- Vulnérabilités de sécurité modérées sur `postcss` (XSS) et `uuid` (dépassement de tampon) via des dépendances transitives d'Expo (`@expo/metro-config`, `xcode`), corrigées par override npm (ML-40, ML-90)

## [1.0.0] - 2026-07-11

Premier déploiement en production.

### Added
- Déploiement en production : configuration Docker et finalisation du pipeline CI/CD (ML-36, ML-37)

## [0.11.0] - 2026-07-11

### Added
- Espace d'administration : endpoints admin, liste des utilisateurs, écran de supervision (tentatives de connexion échouées), version mobile de l'espace admin (ML-53, ML-54, ML-55, ML-73)

## [0.10.0] - 2026-07-11

### Added
- Gestion du compte : endpoints et interface "Mon compte" (ML-67, ML-68)

## [0.9.0] - 2026-07-09

### Added
- Inscription : endpoint et interface d'inscription, avec limitation de débit (ML-57, ML-58)

## [0.8.0] - 2026-07-09

### Added
- Export PDF du suivi : API et interface (ML-29, ML-30)

## [0.7.0] - 2026-07-09

### Added
- Rendez-vous : entité Appointment et endpoints associés, écrans agenda/RDV (ML-27, ML-28)

## [0.6.0] - 2026-07-09

### Added
- Messagerie interne sécurisée : entité Message, endpoints et interface (ML-25, ML-26)

### Changed
- Ajout de checks frontend au pipeline CI (ML-71)

## [0.5.0] - 2026-07-09

### Added
- Gestion des liaisons patient/aidant/soignant : création, acceptation/refus et révocation d'invitations, écrans dédiés (ML-44, ML-45, ML-46, ML-47, ML-48)

## [0.4.0] - 2026-07-08

### Added
- Journal de suivi : version mobile, version web soignant, version web patient/aidant (ML-22, ML-23, ML-24, ML-41)
- Entité Treatment et endpoints de prescription/suivi des traitements, affichage et gestion des traitements du jour (web/mobile), autosuggestion des noms de médicaments (ML-49, ML-50, ML-51)

## [0.3.0] - 2026-07-06

### Added
- Entité User et authentification JWT (configuration, fixtures), Voters Symfony, limitation de débit sur les endpoints d'authentification, interfaces de connexion web et mobile (ML-17, ML-18, ML-19, ML-20, ML-21)

## [0.2.0] - 2026-07-03

### Added
- Pipeline d'intégration continue (CI) et de déploiement continu (CD) (ML-15, ML-16)

## [0.1.0] - 2026-07-02

### Added
- Structure du monorepo et configuration Docker : backend Symfony 7 + API Platform, frontend web React, frontend mobile Expo (ML-11, ML-12, ML-13)
