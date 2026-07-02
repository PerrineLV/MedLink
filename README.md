# MedLink — Lien Médical Simplifié

Application web et mobile de coordination du suivi médical entre patients, aidants et professionnels de santé.

## Stack technique

- **Backend** : Symfony 7 + API Platform
- **Front web** : React 18 (professionnels de santé)
- **Front mobile** : React Native (patients et aidants)
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

## Certification

Projet réalisé dans le cadre de la certification RNCP 39583 — Expert en développement logiciel (YNOV Connect).