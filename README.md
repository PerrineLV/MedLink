# MedLink

Application web et mobile de coordination du suivi médical entre patients, aidants et professionnels de santé.

MedLink a été conçu dans le cadre de la certification **RNCP 39583, Expert en développement logiciel, niveau 7**. Le projet met l'accent sur la sécurité, la gestion fine des accès et la qualité logicielle dans un contexte de données sensibles.

## Objectif

Centraliser les informations utiles au suivi médical et faciliter leur partage entre les personnes autorisées, sans remplacer les outils de diagnostic ni les professionnels de santé.

## Périmètre fonctionnel

- Gestion de comptes selon plusieurs profils : patient, aidant et professionnel de santé
- Gestion des relations entre patients, aidants et soignants
- Journal de suivi partagé avec contrôle des accès
- Authentification sécurisée par JWT et renouvellement des jetons
- Interfaces web et mobile consommant la même API
- Jeu de données fictives pour les démonstrations et les tests manuels

## Stack technique

| Composant | Technologies |
| --- | --- |
| API | PHP 8.3, Symfony 7.4, API Platform 4, Doctrine |
| Web | React 18, JavaScript, Vite |
| Mobile | React Native, TypeScript |
| Données | PostgreSQL 16 |
| Qualité | PHPUnit, PHPStan niveau 6, PHP CS Fixer, ESLint, Prettier |
| Exploitation | Docker, GitHub Actions, Sentry |

## Architecture

Le projet est organisé en monorepo :

```text
medlink/
├── backend/          API Symfony et API Platform
├── frontend-web/     application React
├── frontend-mobile/  application React Native
└── docker-compose.yml
```

Les clients web et mobile s'appuient sur la même API. Les responsabilités sont séparées entre le domaine métier, la persistance, l'authentification et les interfaces clientes.

## Sécurité et fiabilité

- Authentification JWT avec jetons de renouvellement
- Autorisations adaptées aux différents profils utilisateurs
- Validation des données côté API
- Limitation du débit sur les parcours sensibles
- Analyse statique PHPStan
- Tests automatisés avec mesure de couverture
- Images Docker distinctes pour le développement et la production
- Suivi des erreurs avec Sentry

## Intégration continue

La CI GitHub Actions vérifie séparément les trois applications.

### Backend

- Installation reproductible des dépendances Composer
- Vérification du style avec PHP CS Fixer
- Analyse statique avec PHPStan niveau 6
- Tests PHPUnit avec couverture
- Construction de l'image Docker de production

### Applications web et mobile

- Installation depuis les fichiers de verrouillage
- Lint et vérification du formatage
- Audit des dépendances critiques
- Construction des applications

## Lancer le projet

Créer le fichier d'environnement local :

```bash
cp .env.example .env
```

Lancer les services Docker :

```bash
docker compose up -d
```

Installer les dépendances nécessaires aux hooks de qualité :

```bash
npm install
```

## Données de démonstration

Des fixtures Doctrine fournissent des comptes et données fictives pour les tests manuels : patients, aidants, professionnels de santé, relations entre utilisateurs et entrées de journal.

```bash
docker compose exec app php bin/console doctrine:fixtures:load
```

Ces données sont strictement réservées au développement et ne doivent jamais être chargées en production.

## Tests du backend

Afficher la couverture dans le terminal :

```bash
docker compose exec app vendor/bin/phpunit --coverage-text
```

Générer un rapport HTML :

```bash
docker compose exec app vendor/bin/phpunit --coverage-html coverage
```

## Contexte du projet

MedLink illustre la conception d'un produit complet, depuis l'analyse du besoin et la modélisation des données jusqu'à l'API, aux interfaces clientes, aux tests et à l'intégration continue.
