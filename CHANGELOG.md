# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

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
