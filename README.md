# PretMat

Application web de prêt de matériel pour un petit club : catalogue, emprunt, retour, historique. Projet de formation (Titre Professionnel Développeur Web et Web Mobile, bloc back-end).

Le cahier des charges complet fait foi : [docs/CDC_PretMat.md](docs/CDC_PretMat.md) (version PDF : `docs/CDC_PretMat.pdf`).

## Stack

PHP 8.2+ en POO (sans framework), PDO avec requêtes préparées, MariaDB (SQL écrit à la main, sans ORM), `league/oauth2-google`, PHPUnit, docker-compose.

## Règles pour tout contributeur ou agent IA

- Respecter strictement le périmètre du CDC. Tout ce qui n'est pas listé au chapitre 4 est hors périmètre.
- Aucun framework, aucun ORM, aucun service de base managé (Neon, Supabase...).
- Uniquement des requêtes préparées PDO, aucune concaténation SQL.
- Échapper toute sortie avec `htmlspecialchars`, jeton CSRF sur chaque formulaire POST.
- Aucun secret dans Git : tout passe par `.env` (modèle dans `.env.example`).
- Avancer par étapes (planning du chapitre 10) et attendre la validation de l'auteur avant de passer à la suivante.
- Code et requêtes simples et commentés : l'auteur doit pouvoir les défendre à l'oral.

## Structure prévue

```
public/   seul dossier exposé
src/      Controller, Service, Repository, Model, Security
sql/      schema.sql, seed.sql
tests/
docs/
.env.example
docker-compose.yml
```

## Installation locale

À compléter en fin de projet (étape 6) : prérequis, variables d'environnement, `docker compose up`, import SQL.

## Déploiement

À compléter en fin de projet (étape 6) : procédure alwaysdata pas à pas.

## Sécurité

À compléter en fin de projet : liste des mesures appliquées et rapport de tests.
