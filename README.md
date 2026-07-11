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