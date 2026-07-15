# MedLink — Lien Médical Simplifié

Application web et mobile de coordination du suivi médical entre patients, aidants et professionnels de santé.

## Stack technique

- **Backend** : Symfony 7 + API Platform
- **Front web** : React 18
- **Front mobile** : React Native
- **Base de données** : PostgreSQL 16
- **CI/CD** : GitHub Actions + Docker

## Structure du monorepo
medlink/
├── backend/         ← API Symfony 7
├── frontend-web/    ← React 18
├── frontend-mobile/ ← React Native
└── docker-compose.yml

## Lancer le projet

```bash
cp .env.example .env
docker compose up -d
```

## Hook pre-commit (formatage et lint automatiques)

Un hook Git pre-commit (Husky + lint-staged) formate et lint automatiquement les
fichiers stagés avant chaque commit : `prettier --write` + `oxlint --fix`
(frontend-web) ou `eslint --fix` (frontend-mobile) sur les fichiers JS/JSX/TS/TSX/
CSS/JSON, et `php-cs-fixer fix` sur les fichiers PHP stagés (backend). Le commit est
auto-corrigé quand c'est possible, ou bloqué avec un message d'erreur clair si une
erreur ESLint/oxlint ne peut pas être corrigée automatiquement (le check PHPStan
complet reste réservé à la CI, trop lent en pre-commit).

Installation (une fois le dépôt cloné) :

```bash
npm install
```

`npm install` déclenche automatiquement `husky` via le script `prepare` du
`package.json` racine et active le hook — aucune étape manuelle supplémentaire.
Le hook s'appuie sur les outils déjà installés dans `backend/vendor`,
`frontend-web/node_modules` et `frontend-mobile/node_modules` : pense à avoir
installé les dépendances de ces trois sous-projets au préalable.

## Jeu de données de développement

Un jeu de fixtures Doctrine (`backend/src/DataFixtures/AppFixtures.php`) fournit des comptes
et données réalistes pour les tests manuels : 1 soignant, 4 patients, 2 aidants, avec des
relations Patient↔Aidant et Patient↔Soignant actives/inactives et des entrées de journal de
suivi réparties sur plusieurs dates.

```bash
docker compose exec app php bin/console doctrine:fixtures:load
```

Mot de passe commun à tous les comptes : `MedLink2026!` (identifiants : `patient1@medlink.test`,
`aidant1@medlink.test`, `soignant@medlink.test`, `admin@medlink.test`, etc.).

**⚠️ Ne jamais charger ces fixtures en production** : ce sont des données de santé fictives,
réservées au développement local et aux démonstrations.

## Certification

Projet réalisé dans le cadre de la certification RNCP 39583 — Expert en développement logiciel (YNOV Connect).