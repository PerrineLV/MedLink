# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

### Added
- Titre professionnel du soignant (ex. "Dr", "Pr", saisi à l'inscription depuis ML-57/ML-58) affiché devant son nom partout où un patient ou un aidant le voit : liste des liaisons (`/liaisons`), messagerie (liste de contacts + en-tête de conversation) et agenda/RDV, web et mobile (ML-72). Format centralisé dans une fonction utilitaire unique par plateforme (`formatSoignantName`, `services/roles.js`) qui préfixe le titre sans jamais retirer prénom/nom (`"Dr Jean Martin"`), et ne s'applique jamais à un aidant ni à un soignant regardant son propre profil. Backend : `title` propagé côté API sur les DTOs `LiaisonInvitation`/`MessageContact` (`inviteeTitle`/`title`), `null` inchangé si non renseigné
- Écran d'accueil "landing" avant la connexion (ML-64, web + mobile), nouveau point d'entrée de l'appli pour un visiteur non connecté : logo + accroche "MedLink — Lien Médical Simplifié", titre "Vous êtes nouveaux sur MedLink ?", illustration, 3 points forts ("Centraliser vos échanges médicaux" / "Coordination entre patient, aidant et soignant" / "Suivi sécurisé et accessible à tout moment"), 2 CTA empilés "Inscription"/"Connexion" vers les écrans existants. Web : nouvelle route `/` (`WelcomePage`), le catch-all et les redirections "non connecté" pointent désormais vers `/` au lieu de `/login` ; un utilisateur déjà connecté qui y accède est renvoyé vers son écran d'accueil habituel. Mobile : nouvel écran `Welcome`, devenu le premier écran de la pile non authentifiée (remplace `Login`) — `Login`/`Register` restent atteignables via les CTA et via les liens "retour" existants (mot de passe oublié, etc.), inchangés. Le quota Figma MCP était épuisé pour les deux nœuds de référence du ticket (web `1:436`, mobile `1:206`) : layout, couleurs et logo reconstruits à partir d'une capture de la maquette et du fichier logo fournis directement par Perrine plutôt que du MCP, recalés au pixel près par comparaison avec la capture (position, tailles, couleurs `--color-primary`/`--color-primary-light` déjà en place dans le design system). Logo et illustration ajoutés comme assets (`frontend-web/src/assets`, `frontend-mobile/assets`), recadrés depuis les fichiers fournis
- Ticket ML-108 : le bouton "Connexion" (texte blanc sur `--color-primary-light` #7491F7, repris fidèlement de la maquette ML-64) mesure 2.94:1 de contraste, sous le seuil RGAA 4.5:1 — problème préexistant sur toute la couleur d'action `--color-primary-light`/`COLORS.primaryLight`, déjà utilisée telle quelle dans `LoginPage`, `RegisterPage` et plusieurs écrans mobile. Laissé fidèle à la maquette/à l'existant sur ML-64 (décision explicite) plutôt que de corriger un seul bouton en isolation ; correction globale trackée séparément
- Icône d'app, favicon et nom affiché "MedLink" (ML-79), en remplacement des icônes par défaut du bootstrap de projet (chevron bleu Expo, favicon violet Vite) jamais mises à jour depuis ML-13/ML-14. Toutes les déclinaisons générées depuis le logo MedLink existant (`medlink-logo.png`, silhouettes + cœur/croix), recadré sur fond marine `#2E3862` — le fond choisi après comparaison visuelle de 3 options, car la silhouette blanche du logo devient quasi invisible sur fond blanc/clair. Mobile (Expo managed, pas de dossiers `ios`/`android` prebuild — EAS génère les tailles par plateforme à partir de ces sources uniques) : `icon.png` (1024×1024), `android-icon-foreground/-background/-monochrome.png` (icône adaptative + thématique Android 13+), `favicon.png`, et `expo.name` → "MedLink" dans `app.json` (slug/bundle identifier `fr.medlinkapp.mobile` non touchés, pour ne pas casser la continuité d'installation/mise à jour ML-98). Web : `favicon.ico` multi-résolution, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png`, balises `<link rel="icon">`/`apple-touch-icon` mises à jour dans `index.html` (`<title>` déjà "MedLink", inchangé). Non vérifié en conditions réelles (écran d'accueil iOS/Android après installation, ajout aux favoris/à l'écran d'accueil Safari) — à confirmer manuellement comme demandé par le ticket
- Avertissement "projet de certification" sur l'écran d'inscription, web et mobile (ML-107, étend ML-82 sans le rouvrir) : bandeau visible immédiatement au-dessus du formulaire, sans action requise ("Projet de certification à but pédagogique. Ne pas utiliser avec de vraies données de santé. Hébergement non certifié HDS. L'identité professionnelle des soignants n'est pas vérifiée à l'inscription."), distinct du texte CGU/RGPD existant (ML-82, resté inchangé) qui n'est lu que sur clic. Réutilise le token de couleur `--color-danger`/`--color-danger-bg` (web) et `COLORS.danger`/`COLORS.dangerBg` (mobile) déjà utilisé pour les bannières d'erreur du formulaire — délibérément distinct des couleurs des badges d'état santé orange/vert pour éviter toute confusion, contraste ~4.8:1 (> seuil RGAA 4.5:1). `role="alert"` web, `accessibilityRole="alert"` mobile

### Fixed
- Bandeau de sécurité "Données chiffrées - accès soignants uniquement" (ML-92) incohérent entre écrans : 3 libellés différents coexistaient (Journal web : "Données chiffrées — accessibles uniquement à l'équipe soignante désignée" ; Invitations/Liaisons web : "Données chiffrées - accès soignants uniquement" ; mobile : "Données chiffrées · accès soignants uniquement" avec un point médian) et le bandeau était absent de plusieurs écrans, causant un décalage de layout (contenu qui "remonte") à la navigation. Texte unifié sur le libellé de référence (celui déjà documenté au dossier Bloc 2 et dans `CLAUDE.md`). Plutôt que de traiter sa présence comme une prop optionnelle à ajouter écran par écran (ce qui laissait la porte ouverte au même oubli), le bandeau est désormais rendu de façon inconditionnelle : web, `AppLayout` l'affiche systématiquement (constante unique `SECURITY_BANNER_TEXT` exportée, remplace 3 déclarations dupliquées et la prop `securityBanner` désormais inutile, supprimée) — couvre les 11 pages authentifiées sans exception ; mobile, le bandeau est intégré directement dans le composant `Header` partagé (remplace l'ajout manuel de `<SecurityBanner />` après chaque `<Header />` sur 7 écrans, et corrige au passage `AdminBlockedScreen` qui ne l'avait jamais eu). `ConversationScreen`, qui a son propre en-tête (pas le composant `Header` partagé), reçoit le bandeau explicitement. Les écrans de formulaire mobile sans chrome standard (`NewEntryScreen`, `NewAppointmentScreen` — pas de logo/cadenas, juste un bouton retour) restent hors périmètre : ce sont des écrans de saisie modaux, pas la structure d'écran standard visée par le ticket
- Clavier mobile masquant le champ de saisie en bas d'écran (ML-69, React Native) : sur Android, le `KeyboardAvoidingView` existant n'avait aucun effet (`behavior={undefined}`) — le clavier recouvrait purement et simplement le champ actif. Remplacé sur les 8 écrans formulaire concernés (connexion, inscription, mot de passe oublié/réinitialisation, nouvelle entrée journal, nouveau RDV, mon compte, liaisons) par `KeyboardAwareScrollView` (`react-native-keyboard-aware-scroll-view`, pur JS, aucun module natif — testable dans Expo Go sans dev client), qui scrolle automatiquement le champ actif au-dessus du clavier sur iOS et Android. La modale de suppression de compte, dans un arbre de rendu séparé (`Modal`) donc hors de portée du scroll parent, reçoit son propre `KeyboardAvoidingView`. La messagerie (`ConversationScreen`), dont le composer reste épinglé sous la liste de messages plutôt que scrollé, garde `KeyboardAvoidingView` mais avec `behavior="height"` sur Android au lieu de `undefined`. Remplace une première tentative avec `react-native-keyboard-controller` (lib native, nécessitant un build EAS dev client), abandonnée précisément parce qu'elle cassait la testabilité dans Expo Go
- Responsive web (ML-63), audité sur desktop large / tablette ~768px / mobile ~390px pour Journal, Messagerie, RDV, Export PDF et Profil (patient, aidant, soignant) : la messagerie (`MessagingPage`) passait en layout deux colonnes figé sous ~700px, rendant le fil de conversation illisible (texte compressé sur une colonne d'un mot) — repassée en mobile-first, empilée sous 700px puis remise en colonnes au-delà ; la carte patient (`PatientsPage`, écran d'accueil soignant) ne prévoyait pas de repli pour son badge de statut, qui finissait par chevaucher le texte "Dernière entrée" sous ~480px — carte passée en `flex-wrap` pour que le badge retombe proprement sur sa propre ligne ; le bouton "Se déconnecter" de l'en-tête (`AppLayout`, présent sur tout le site) ne faisait que 35px de haut, sous le minimum RGAA de 44×44 px déjà appliqué côté mobile — corrigé à 44px partout. Vérifié : aucun débordement horizontal résiduel (desktop/tablette/mobile), formulaires (nouvelle entrée journal, export personnalisé, commentaire soignant) et zones tactiles conformes sur les 9 écrans audités
- Highlight d'autofill (gestionnaire de mots de passe Android) décalé et carré au lieu d'épouser la pilule arrondie des champs email/mot de passe sur l'écran de connexion mobile (ML-103, `LoginScreen.js`) : le style `input` n'avait pas de `backgroundColor` propre, laissant transparaître le fond natif rectangulaire par défaut de l'`EditText` Android sous la bordure arrondie RN — ajout de `backgroundColor: COLORS.surface` (le style RN reprend alors entièrement le rendu natif, y compris le highlight d'autofill) et `overflow: 'hidden'` pour garantir que tout contenu peint nativement reste bien clippé à la forme pilule (`borderRadius: 33`). L'autofill lui-même reste actif (hors périmètre du ticket de le désactiver). Le même style `input` (sans `backgroundColor`) est dupliqué tel quel sur d'autres écrans (`RegisterScreen.js` notamment) mais hors périmètre de ce ticket, limité à l'écran de connexion — probable candidat pour un ticket de suivi. Non vérifié en conditions réelles (autofill Android actif sur appareil physique, non-régression iOS) — à confirmer manuellement comme demandé par le ticket
- Contraste insuffisant de `--color-primary-light`/`COLORS.primaryLight` (ML-108, 2.94:1 mesuré sur ML-64, sous le seuil RGAA 4.5:1) : couleur foncée de `#7491f7` à `#3b5bdb` (même famille de bleu, ~5.7:1 avec du texte blanc — marge au-dessus du seuil plutôt qu'un calage pile à 4.5:1). Décision prise avec Perrine parmi 3 pistes possibles (foncer le token existant / passer le texte en navy / introduire une variable dédiée aux CTA) : foncer le token existant, qui corrige tous les usages en un seul changement de valeur sans distinguer cas par cas. Web : un seul endroit (`index.css`, `--color-primary-light`), propagé automatiquement partout via `var()`, y compris l'anneau de focus clavier. Mobile : `COLORS.primaryLight` dupliqué par écran (convention du projet, pas de token partagé) — répercuté dans les 8 fichiers concernés (`LoginScreen`, `RegisterScreen`, `ForgotPasswordScreen`, `ResetPasswordScreen`, `PrivacyPolicyScreen`, `NewEntryScreen`, `NewAppointmentScreen`, `WelcomeScreen`). Le bouton "Connexion" de `WelcomePage`/`WelcomeScreen` (ML-64), laissé fidèle à la maquette en attendant ce correctif global, est donc maintenant conforme lui aussi. Nouveau `grep -rn` sur les deux repos confirmé sans usage supplémentaire introduit entre-temps, comme demandé par le ticket

### Removed
- Page "dashboard" web vide (ML-62), reliquat de l'initialisation du projet : route `/dashboard`, composant `DashboardPage` et entrée "Tableau de bord" de la sidebar soignant supprimés. Le fallback de `getHomeRoute()` (utilisateur authentifié sans rôle reconnu — cas normalement impossible côté backend) pointe désormais vers `/login` plutôt que vers cette page morte
- Entrée "Paramètres" orpheline dans la sidebar soignant web (ML-76), résidu distinct de "Mon compte" (ML-68) qui n'a jamais été spécifié par aucun ticket : entrée sans route (`to: null`) déclenchant seulement l'alerte générique "bientôt disponible". Supprimée de `SOIGNANT_SIDEBAR_ITEMS` (`services/roles.js`) ; aucune autre occurrence côté web ou mobile

### Changed
- Modale "Profil" mobile (ML-61) : remplacement de l'`Alert.alert` natif (non stylable, incohérent avec le reste de l'appli) par une modale maison reprenant l'identité visuelle MedLink (fond navy `#2E3862`, boutons en pilules arrondies, action "Se déconnecter" mise en évidence), avec le même habillage que la modale de confirmation de suppression de compte. Accessibilité : `accessibilityRole="alert"` + `accessibilityViewIsModal` sur la carte, zones tactiles ≥ 44×44 pt, fermeture au bouton retour Android (`onRequestClose`)
- Affichage du numéro de version de l'application (ML-89) : web, en bas de la sidebar (`import.meta.env.VITE_APP_VERSION`, injecté par `vite.config.js` depuis `package.json` à chaque build, dev comme prod) ; mobile, dans l'écran "Mon compte" (`app.json` → `expo.version`, lu via `expo-constants`, même source que le update-checker ML-98). Aucune valeur codée en dur : le numéro affiché suit automatiquement le bump de version, sans modification de code applicatif
- Navigation web sous le breakpoint mobile (<900px, ML-63) : la sidebar, qui passait en ligne d'items avec retour à la ligne sous le header, est remplacée par un menu burger (`AppLayout`) — icône ☰/✕ dans l'en-tête (zone tactile 44×44, `aria-expanded`/`aria-controls`/`aria-label` dynamique), ouvrant un panneau en overlay au-dessus du contenu (au lieu de le pousser) listant les mêmes items et badges de compteur qu'avant. Fermeture au clic sur un item, au clic à l'extérieur et à la touche Échap. En resserrant ce changement, l'en-tête (déjà tout juste à la limite de largeur sur petit écran) a aussi été resserré pour ne pas déborder jusqu'à ~360px
- Bouton "Se déconnecter" moins imposant sur mobile (<900px, ML-63) : icône seule (`lucide-react` `LogOut`, même gabarit 44×44 que le bouton cloche) au lieu de la pilule bordée avec libellé complet, qui prenait une place disproportionnée dans l'en-tête. Libellé toujours visible au clic (`aria-label`) et sur desktop, où le bouton gagne au passage la même icône
- Sélection du patient sur l'écran Export PDF web (ML-95) : la rangée de pilules (une par patient rattaché), illisible au-delà de quelques patients, est remplacée par le composant `PatientAutocomplete` déjà utilisé sur l'agenda (ML-28) — recherche par nom, validation stricte du texte saisi contre le patient réellement sélectionné avant génération ("Sélectionnez un patient dans la liste." sinon). Comportement inchangé pour un soignant avec un seul patient rattaché (champ masqué, sélection implicite) ; le sélecteur de période (7j/30j/personnalisé) n'est pas concerné. `PatientAutocomplete` ne stylise pas son `<input>` lui-même (il compte sur le CSS du parent, comme sur l'agenda) : ajout de `.export-field input` dans `ExportPage.css` (bordure, padding, hauteur 44px), qui rend au passage redondante et supprime la règle équivalente propre aux champs de date personnalisée. `.export-field` limité à `max-width: 480px` (même largeur que l'aperçu du fichier et les champs de date personnalisée) pour éviter que le champ ne s'étire sur toute la largeur de la page
- Sélecteur de rôle ("Vous êtes") sur l'écran d'inscription mobile (ML-99, étend ML-58) : les 3 pilules Patient/Aidant/Soignant, qui passaient sur 2 lignes sur un écran de 390px et repoussaient le bouton "Créer mon compte" hors du viewport initial, sont regroupées en segmented control sur une seule ligne (`RegisterScreen.js`, largeur égale `flex: 1` par option, zone tactile ≥ 44 pt). État sélectionné (fond navy `#2E3862` + texte blanc) déjà présent dans le code, inchangé
- Navigation mobile (ML-101) : `createStackNavigator` (`@react-navigation/stack`, transitions animées en JS) remplacé par `createNativeStackNavigator` (`@react-navigation/native-stack`, délègue les transitions aux contrôleurs natifs — `react-native-screens`/`react-native-safe-area-context` étaient déjà en dépendance mais inexploités). Remplacement direct dans `App.js` : aucun écran n'utilisait d'option spécifique à l'ancienne stack (header custom, `cardStyleInterpolator`…), `screenOptions={{ headerShown: false }}` et le geste de retour natif restent identiques. Dépendance `@react-navigation/stack` désinstallée, devenue inutilisée. Fluidité des transitions non vérifiée en conditions réelles (aucun appareil/émulateur disponible dans l'environnement de dev) — à confirmer manuellement comme demandé par le ticket


## [1.2.0] - 2026-07-15

### Added
- Update-checker mobile (ML-98) : au démarrage, l'app appelle `GET /api/app-version` (endpoint public, backend) et compare le numéro de version reçu à celui installé (`app.json` → `expo.version`, lu via `expo-constants`). En cas de version distante plus récente, une bannière non bloquante s'affiche en haut de l'écran (que l'utilisateur soit connecté ou non) avec un lien de téléchargement vers l'APK à jour ; l'utilisateur peut l'ignorer et continuer à utiliser l'app. Aucune mise à jour automatique/silencieuse (hors périmètre). L'appel réseau a un timeout court (2s) et échoue silencieusement pour ne jamais impacter le démarrage perçu
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
- Race condition mobile au premier login : `JournalScreen` affichait systématiquement "Impossible de charger le journal de suivi" avant de fonctionner au refresh suivant. Le header `Authorization` était posé dans un `useEffect` d'`AuthContext` (parent), déclenché après le montage de `JournalScreen` (enfant) dans le même commit React que `setToken()` — le fetch initial partait donc sans header. Remplacé par un intercepteur `axios` sur `httpClient` lisant une valeur mise à jour de façon synchrone par `login()`/`logout()`, sans passer par un effet (ML-100)
- Expo Go affichait systématiquement "Something went wrong" en dev depuis la liaison du projet à EAS (`extra.eas.projectId`/`owner` dans `app.json`, ML-97) : à chaque requête de manifest, le CLI tentait de récupérer un certificat de développement en se connectant à un compte Expo, ce qui échouait en boucle en mode non-interactif dans le conteneur Docker (`CommandError: Input is required, but 'npx expo' is in non-interactive mode`). Ajout de `EXPO_OFFLINE: 1` à l'environnement du service `mobile` dans `docker-compose.yml` pour désactiver ces appels réseau en dev local ; ajout de `ios.bundleIdentifier` (`fr.medlinkapp.mobile`) manquant dans `app.json` en prévision d'un futur build EAS iOS
- Fichiers mal formatés (Prettier/ESLint/oxlint côté web et mobile, php-cs-fixer côté backend) détectés seulement après push et faisant échouer la CI : ajout d'un hook pre-commit Husky + lint-staged qui auto-corrige les fichiers stagés ou bloque le commit avec un message clair si une erreur ne peut pas être corrigée automatiquement (ML-102)
- Healthcheck du service `app` dans `docker-compose.yml` (dev) ciblait `http://localhost/healthz`, route inexistante côté `HealthController` (seule `/health` est exposée) : le conteneur pouvait être marqué `unhealthy` en dev alors que l'API répondait normalement. Corrigé pour cibler `/health`, comme `docker-compose.prod.yml` et le `Dockerfile` le faisaient déjà (ML-105)

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
