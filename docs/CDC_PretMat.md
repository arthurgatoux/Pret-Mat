# Cahier des charges : PretMat

**Application de prêt de matériel**
Auteur : Arthur Gatoux | Version 1.0 | 10 novembre 2026
Contexte : Titre Professionnel Développeur Web et Web Mobile (bloc back-end, RNCP37674BC02)

## 1. Contexte et objectifs

Un petit club prête du matériel (appareils photo, micros, câbles) à ses membres. Le suivi se fait aujourd'hui sur papier ou par messages, ce qui provoque des doubles réservations et des oublis de retour. PretMat remplace ce suivi par une application web minimale : catalogue, emprunt, retour, historique.

**Objectif pédagogique** : démontrer les compétences back-end 5 à 8 (base relationnelle, accès aux données SQL, composants métier, documentation du déploiement) avec une base de données conçue et administrée manuellement, sans service managé de type Neon et sans ORM.

## 2. Principe directeur : simple, rapide, fonctionnel, sécurisé

- **Simple** : 4 tables, 6 pages, aucune fonctionnalité superflue. Tout ce qui n'est pas listé au chapitre 4 est hors périmètre.
- **Rapide à réaliser** : développement en une soirée (environ 4 h) avec assistance d'un outil d'IA. Chaque requête SQL et chaque règle métier doit toutefois être relue et comprise par l'auteur, qui devra les défendre à l'oral.
- **Fonctionnel** : le parcours complet (connexion, emprunt, retour, administration) fonctionne en ligne, sans erreur bloquante.
- **Sécurisé** : authentification Google OAuth 2.0, requêtes préparées, contrôle des rôles, protection CSRF et XSS, secrets hors du dépôt Git (chapitre 6).

## 3. Acteurs

| Acteur | Description | Droits |
|---|---|---|
| Visiteur | Personne non connectée | Voir la page d'accueil et se connecter avec Google |
| Membre | Utilisateur connecté (rôle `member`) | Consulter le catalogue, emprunter, rendre, voir ses prêts |
| Administrateur | Utilisateur connecté (rôle `admin`) | Tout ce que fait un membre, plus gestion du catalogue et vue de tous les prêts |

## 4. Exigences fonctionnelles

| Réf. | Fonctionnalité | Priorité |
|---|---|---|
| F1 | Connexion et déconnexion via Google (OAuth 2.0 / OpenID Connect). Création automatique du compte membre à la première connexion. | Obligatoire |
| F2 | Catalogue : liste du matériel avec catégorie et disponibilité, filtre par catégorie. | Obligatoire |
| F3 | Emprunter un objet disponible (durée de 1 à 14 jours). | Obligatoire |
| F4 | Rendre un objet emprunté. | Obligatoire |
| F5 | Page « Mes prêts » : prêts en cours et historique. | Obligatoire |
| F6 | Admin : créer, modifier, supprimer un objet et une catégorie (CRUD). | Obligatoire |
| F7 | Admin : liste de tous les prêts, avec mise en évidence des retards. | Obligatoire |
| F8 | Journal des actions (connexion, emprunt, retour) dans une collection MongoDB. | Optionnel, si le temps le permet |

### Règles métier (composants côté serveur)

- Un objet ne peut avoir qu'un seul prêt actif à la fois.
- Un membre ne peut avoir plus de 3 prêts actifs.
- Cycle de vie d'un prêt : en cours puis rendu. Un prêt dont la date de fin est dépassée est affiché en retard (calculé, non stocké).
- Un objet ayant un prêt actif ne peut pas être supprimé.
- Seul le propriétaire d'un prêt (ou un admin) peut le clôturer.

## 5. Architecture et stack technique

| Élément | Choix | Justification |
|---|---|---|
| Langage | PHP 8.2 ou plus, **programmation orientée objet**, sans framework | Exigence de l'examinateur (POO) et simplicité de déploiement |
| Base de données | MariaDB/MySQL, schéma écrit à la main en SQL | Exigence de maîtrise des requêtes SQL, sans ORM ni interface de création |
| Accès aux données | PDO, requêtes préparées, classes Repository | Sécurité contre l'injection SQL, compétence 6 |
| Connexion Google | Bibliothèque `league/oauth2-google` (Composer) | Implémentation fiable du flux « authorization code » |
| Front | HTML, CSS (Tailwind CDN ou CSS simple), templates PHP échappés | Le front n'est pas l'enjeu de ce projet |
| Tests | PHPUnit : règles métier et repositories | Compétence 7 |
| Conteneurs (dev) | docker-compose : PHP-Apache et MariaDB | Environnement reproductible, compétence 8 |
| Journal (option) | MongoDB (collection `logs`) | Volet NoSQL de la compétence 6 |

**Structure du dépôt** : `public/` (seul dossier exposé), `src/` (Controller, Service, Repository, Model, Security), `sql/` (schema.sql, seed.sql), `tests/`, `docs/`, `.env.example`, `docker-compose.yml`, `README.md`.

## 6. Sécurité

### 6.1 Authentification Google (OAuth 2.0 / OpenID Connect)

- Flux « authorization code » côté serveur : génération d'un jeton `state` aléatoire stocké en session, vérifié au retour de Google (protection CSRF du flux) ; ajout d'un `nonce` contre le rejeu.
- Scopes limités à `openid email profile`. Identifiant utilisateur stocké : le champ `sub` de Google (stable), et non l'e-mail seul.
- Le client secret et l'identifiant client sont dans des variables d'environnement, jamais dans Git. L'URI de redirection est exacte et en HTTPS en production.
- Refus de la connexion si l'e-mail n'est pas vérifié (`email_verified`).
- Prérequis Google Cloud : projet, écran de consentement, identifiants OAuth « application Web », URI de redirection locale et de production. Tant que l'écran est en mode test, seuls les comptes testeurs déclarés peuvent se connecter.

### 6.2 Mesures applicatives

- Injection SQL : uniquement des requêtes préparées PDO, aucune concaténation.
- XSS : échappement de toute sortie avec `htmlspecialchars`.
- CSRF : jeton par session sur tous les formulaires POST.
- Sessions : cookie HttpOnly, Secure, SameSite=Lax ; régénération de l'identifiant de session après connexion.
- Autorisation : contrôle du rôle côté serveur à chaque route admin, et contrôle de propriété sur chaque prêt (prévention des IDOR).
- Base : utilisateur SQL dédié aux droits limités (pas de compte root), contraintes d'intégrité en base.
- Divers : en-têtes de sécurité de base (X-Content-Type-Options, X-Frame-Options), messages d'erreur génériques, erreurs détaillées désactivées en production.

## 7. Modèle de données (à écrire en SQL à la main)

| Table | Colonnes principales | Contraintes |
|---|---|---|
| users | id, google_sub, email, name, role (member/admin), created_at | google_sub UNIQUE ; email UNIQUE ; CHECK sur role |
| categories | id, name | name UNIQUE |
| items | id, name, description, category_id, created_at | FK vers categories (ON DELETE RESTRICT) |
| loans | id, user_id, item_id, borrowed_at, due_at, returned_at (NULL si en cours) | FK vers users et items ; CHECK due_at > borrowed_at ; règle « un seul prêt actif par objet » garantie par le service dans une transaction |

Livrables associés : MCD et MLD (schéma), `schema.sql`, `seed.sql` avec jeu d'essai, et procédure de sauvegarde/restauration (mysqldump).

## 8. Déploiement gratuit et simple

- **Hébergeur cible** : alwaysdata, offre gratuite (usage personnel, sans carte bancaire, 1 Go de disque), qui fournit PHP et MariaDB gérée. Cela convient à un projet de formation.
- **Procédure** : création du compte, de la base et de l'utilisateur SQL depuis l'interface alwaysdata ; import de `schema.sql` ; envoi du code par SFTP ou Git ; document root pointant sur `public/` ; variables d'environnement du site ; URI de redirection de production ajoutée dans Google Cloud ; HTTPS actif.
- **Plan B** : si l'accès SSH pose problème, utiliser SFTP seul, qui suffit pour ce projet.
- **En local** : `docker compose up` lance l'application et la base.

## 9. Livrables

- Code source sur GitHub (commits réguliers, branche principale propre).
- Application en ligne accessible par URL HTTPS.
- Documentation : README (installation, variables d'environnement, déploiement pas à pas), MCD/MLD, liste des mesures de sécurité.
- Tests PHPUnit (5 à 8 minimum) et captures d'écran du fonctionnement.
- Rapport de test d'injection SQL et de contrôle d'accès (preuves).

## 10. Planning prévisionnel (une soirée)

| Étape | Contenu | Durée |
|---|---|---|
| 1 | MCD, schema.sql, seed.sql, test d'import | 30 min |
| 2 | Classes Database et Repository (PDO) | 45 min |
| 3 | Connexion Google, sessions, rôles, CSRF | 60 min |
| 4 | Services métier (emprunt, retour, règles) et pages | 60 min |
| 5 | Tests PHPUnit | 30 min |
| 6 | Déploiement alwaysdata, README, captures | 45 min |

## 11. Critères de validation et lien avec le référentiel

| Compétence | Preuve attendue |
|---|---|
| 5. Mettre en place une base de données relationnelle | MCD/MLD, schema.sql écrit à la main, contraintes, jeu d'essai, sauvegarde |
| 6. Développer des composants d'accès aux données SQL et NoSQL | Repositories PDO préparés, jointures, test anti-injection, journal MongoDB (option) |
| 7. Développer des composants métier côté serveur | Services POO, règles d'emprunt, authentification Google, rôles, tests PHPUnit |
| 8. Documenter le déploiement | README, docker-compose, procédure alwaysdata, variables d'environnement |

**Hors périmètre** : notifications e-mail, paiement, API publique, application mobile, interface d'administration avancée, mot de passe local.
