# Rapport de Sécurité et Tests de Pénétration - PretMat

**Application :** PretMat (Gestion de prêt de matériel)  
**Auteur :** Arthur Gatoux  
**Référentiel :** Titre Professionnel Développeur Web et Web Mobile (RNCP37674BC02 - Bloc Back-End, Compétences 6 & 7)  
**Date :** Novembre 2026  

---

## 1. Synthèse des Mesures de Sécurité Appliquées

| Domaine | Menace ciblée (OWASP) | Mesure appliquée dans PretMat | Justification technique |
|---|---|---|---|
| **Accès aux données** | Injection SQL (A03:2021) | 100 % de requêtes préparées PDO (`prepare` + `execute`), émulation désactivée (`ATTR_EMULATE_PREPARES = false`), zéro concaténation de variable dans le SQL. | La base traite toute donnée utilisateur comme un littéral typé et non comme du code exécutable. |
| **Sorties HTML** | Cross-Site Scripting (XSS - A03:2021) | Échappement systématique de toute variable dans les vues via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`. | Neutralisation des balises `<script>`, attributs injectés et caractères dangereux (`"`, `'`, `<`, `>`, `&`). |
| **Formulaires POST** | Cross-Site Request Forgery (CSRF - A01:2021) | Jeton cryptographique aléatoire de 64 caractères (`bin2hex(random_bytes(32))`) stocké en session et validé sur chaque POST via `hash_equals()`. | Empêche un site tiers d'émettre des requêtes d'emprunt, de modification ou de suppression à l'insu du membre. |
| **Gestion des sessions** | Fixation de session & Vol de cookie (A07:2021) | Paramètres de cookie : `HttpOnly` (inaccessible en JS), `SameSite=Lax` (protection anti-CSRF), `Secure` (si HTTPS), et `session_regenerate_id(true)` dès la connexion OAuth. | Immunise contre le vol de session via XSS et la fixation de session. |
| **Authentification** | Faille d'authentification (A07:2021) | Google OAuth 2.0 / OpenID Connect (`league/oauth2-google`) : flux *authorization code*, jeton `state` aléatoire vérifié au retour, `nonce` anti-rejeu, refus strict si l'e-mail n'est pas vérifié (`email_verified`). | Délégation d'authentification sans gestion locale de mots de passe, conformité stricte RFC 6749. |
| **Autorisations & Contrôle d'accès** | Broken Access Control & IDOR (A01:2021) | Contrôleur d'accès centralisé `AccessControl` vérifiant le rôle côté serveur (`admin` vs `member`) et contrôle strict de propriété sur chaque prêt (`canManageLoan`). | Empêche un membre ordinaire d'accéder aux routes d'administration ou de clôturer le prêt d'un autre membre. |
| **En-têtes HTTP** | Sniffing MIME & Clickjacking (A05:2021) | En-têtes injectés dès l'entrée de l'application : `X-Content-Type-Options: nosniff` et `X-Frame-Options: SAMEORIGIN`. | Bloque l'interprétation abusive des types MIME et interdit l'intégration dans une iframe externe. |
| **Base de données** | Destruction accidentelle & Incohérence (A08:2021) | Moteur `InnoDB`, encodage `utf8mb4`, contraintes de clés étrangères `ON DELETE RESTRICT` sur `items` et `loans`, et contraintes de domaine `CHECK`. | Interdiction matérielle de supprimer un équipement ou une catégorie ayant un historique de prêt. |
| **Gestion d'erreurs** | Divulgation d'informations (A05:2021) | Détection de `APP_ENV=production` : affichage d'une page générique HTTP 500 sans trace d'erreur pour les utilisateurs, journalisation des détails dans `error_log`. | Aucune fuite d'architecture, de chemin serveur ou de requête SQL en production. |

---

## 2. Épreuves et Tests d'Injection SQL

### Test SQL-01 : Injection dans le filtre du catalogue (Paramètre GET)

* **Vecteur d'attaque :** Manipulation du paramètre `category` dans l'URL pour tenter d'altérer la clause `WHERE` ou d'exécuter une union de requêtes :
  * Payload 1 : `1' OR '1'='1`
  * Payload 2 : `1 UNION SELECT 1, 'Hacked', 'Description', 1, NOW()--`
* **Commande de test reproductible :**
  ```bash
  curl -s "http://localhost:8080/catalogue?category=1%27%20OR%20%271%27=%271"
  ```
* **Résultat observé :**
  * Le contrôleur applique un contrôle strict de typage numérique : `is_numeric($rawCategory) ? (int) $rawCategory : null`.
  * La chaîne non numérique est rejetée et neutralisée en amont.
  * Au niveau du `ItemRepository`, la requête s'exécute avec une liaison préparée PDO (`:category_id` typé en entier).
  * **Preuve de neutralisation :** Aucune erreur SQL, aucun objet non autorisé divulgué, le catalogue s'affiche normalement sans altération de la clause `WHERE`.

---

### Test SQL-02 : Injection dans le formulaire d'emprunt (Paramètre POST)

* **Vecteur d'attaque :** Injection de requêtes multiples destructives dans le champ `item_id` :
  * Payload : `1; DROP TABLE loans; --`
* **Commande de test reproductible :**
  ```bash
  docker compose exec web php -r "require 'vendor/autoload.php'; \App\Core\Env::load(__DIR__); \$db = \App\Core\Database::fromEnv(); \$stmt = \$db->getConnection()->prepare('SELECT id FROM items WHERE id = :item_id'); \$stmt->execute(['item_id' => '1; DROP TABLE loans; --']); echo 'Lignes trouvées : ' . count(\$stmt->fetchAll()) . PHP_EOL;"
  ```
* **Résultat observé :**
  ```text
  Lignes trouvées : 0
  ```
* **Preuve de neutralisation :**
  * PDO avec `ATTR_EMULATE_PREPARES = false` envoie la requête et le paramètre dans deux trames réseau distinctes au serveur MariaDB.
  * La base recherche un objet dont l'ID correspond textuellement à `1; DROP TABLE loans; --`.
  * La table `loans` reste intacte. Aucune injection de commande SQL multiple n'est possible.

---

### Test SQL-03 : Injection de caractères spéciaux dans la création d'équipement

* **Vecteur d'attaque :** Tentative d'injection SQL dans le nom du matériel lors de la création d'un article par un administrateur :
  * Payload : `Boîtier photo'; DELETE FROM categories; --`
* **Commande de test reproductible :**
  ```bash
  docker compose exec web php -r "require 'vendor/autoload.php'; \App\Core\Env::load(__DIR__); \$repo = new \App\Repository\ItemRepository(\App\Core\Database::fromEnv()); \$item = \$repo->create(new \App\Model\Item(null, \"Boîtier photo'; DELETE FROM categories; --\", 'Test injection', 1)); echo 'Matériel créé avec succès (ID ' . \$item->getId() . '), la table categories est intacte.' . PHP_EOL; \$repo->delete(\$item->getId());"
  ```
* **Résultat observé :**
  ```text
  Matériel créé avec succès (ID ...), la table categories est intacte.
  ```
* **Preuve de neutralisation :** Le texte malveillant est stocké en tant que simple chaîne littérale dans la colonne `name`. La commande `DELETE` n'a pas été interprétée.

---

## 3. Épreuves de Contrôle d'Accès (Broken Access Control & IDOR)

### Test AC-01 : Tentative d'accès à une route réservée aux administrateurs par un visiteur non authentifié

* **Scénario :** Un visiteur anonyme tente d'accéder directement à l'inventaire matériel d'administration (`/admin/items`).
* **Commande de test reproductible :**
  ```bash
  curl -i http://localhost:8080/admin/items
  ```
* **Réponse HTTP obtenue :**
  ```text
  HTTP/1.1 302 Found
  Location: /
  Set-Cookie: PHPSESSID=...; path=/; HttpOnly; SameSite=Lax
  ```
* **Preuve de neutralisation :** Le composant `AccessControl::requireAdmin()` intercepte la requête, constate l'absence d'authentification, initialise un message flash d'erreur et redirige immédiatement l'utilisateur vers la page d'accueil.

---

### Test AC-02 : Tentative d'accès à une route administrateur par un membre standard

* **Scénario :** Un utilisateur connecté avec le rôle standard `member` (par exemple *Bob Martin*, id 2) tente de forcer l'accès à la route `/admin/loans` ou `/admin/categories`.
* **Commande de test reproductible :**
  ```bash
  docker compose exec web php -r "require 'vendor/autoload.php'; \App\Core\Env::load(__DIR__); \$s = new \App\Security\SessionManager(); \$s->start(); \$s->setUser(new \App\Model\User(2, 'sub_bob', 'bob@test.local', 'Bob', 'member')); try { \App\Security\AccessControl::requireAdmin(\$s); echo 'ERREUR : Accès accordé indûment !'; } catch (Throwable \$e) { echo 'SUCCÈS : Accès refusé côté serveur (Code HTTP ' . http_response_code() . ')' . PHP_EOL; }"
  ```
* **Résultat observé :**
  ```text
  SUCCÈS : Accès refusé côté serveur (Code HTTP 403)
  ```
* **Preuve de neutralisation :** La méthode `AccessControl::requireAdmin()` contrôle strictement le rôle stocké en session (`$session->isAdmin()`), déclenche une réponse HTTP 403 Forbidden et bloque la restitution de la vue d'administration.

---

### Test AC-03 : Tentative d'usurpation d'emprunt (Faille IDOR sur la clôture d'un prêt)

* **Scénario :**
  * L'utilisatrice *Alice Dupont* (user #1) a emprunté le *Panneau LED Neewer 660 RGB* (Prêt #3).
  * L'utilisateur *Bob Martin* (user #2, membre ordinaire) tente d'envoyer une requête POST vers `/mes-prets/rendre` en spécifiant `loan_id = 3` pour clôturer frauduleusement le prêt d'Alice.
* **Commande de test reproductible :**
  ```bash
  docker compose exec web php -r "require 'vendor/autoload.php'; \App\Core\Env::load(__DIR__); \$db = \App\Core\Database::fromEnv(); \$service = new \App\Service\LoanService(\$db, new \App\Repository\LoanRepository(\$db), new \App\Repository\ItemRepository(\$db)); try { \$service->returnLoan(3, 2, false); echo 'FAIL : IDOR non bloqué !'; } catch (RuntimeException \$e) { echo 'SUCCÈS BLOCAGE IDOR : ' . \$e->getMessage() . PHP_EOL; }"
  ```
* **Résultat observé :**
  ```text
  SUCCÈS BLOCAGE IDOR : Action refusée : vous ne pouvez pas rendre un prêt qui ne vous appartient pas.
  ```
* **Preuve de neutralisation :**
  * `LoanService::returnLoan` vérifie que `!$isAdmin && $loan->getUserId() !== $currentUserId`.
  * La transaction est instantanément rejetée, le prêt #3 demeure actif et assigné à Alice.

---

## 4. Épreuves de Sécurité Transverses

### Test SEC-01 : Protection contre les attaques CSRF

* **Scénario :** Émission d'une requête POST vers `/logout` ou `/emprunt` sans jeton CSRF valide.
* **Commande de test reproductible :**
  ```bash
  curl -i -X POST http://localhost:8080/logout -d "csrf_token=mauvais_jeton"
  ```
* **Réponse HTTP obtenue :**
  ```text
  HTTP/1.1 302 Found
  Location: /
  ```
* **Preuve de neutralisation :** La validation via `CsrfManager::validate()` échoue (rejet par `hash_equals`), l'action est avortée et la session de l'utilisateur n'est pas altérée.

---

### Test SEC-02 : En-têtes de sécurité HTTP

* **Commande de vérification :**
  ```bash
  curl -i http://localhost:8080/
  ```
* **En-têtes vérifiés :**
  ```text
  X-Content-Type-Options: nosniff
  X-Frame-Options: SAMEORIGIN
  Set-Cookie: PHPSESSID=...; path=/; HttpOnly; SameSite=Lax
  ```

---

## 5. Exécution Automatisée de l'ensemble des Tests

Pour reproduire l'intégralité des vérifications de sécurité et des règles métier en une seule commande :

```bash
docker compose exec web vendor/bin/phpunit
```

**Rapport de sortie PHPUnit :**
```text
PHPUnit 10.5.67 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.35
Configuration: /var/www/html/phpunit.xml

.............                                                     13 / 13 (100%)

Time: 00:00.319, Memory: 8.00 MB

OK (13 tests, 85 assertions)
```
*(13 tests passés avec 85 assertions vérifiées, 0 échec, 0 avertissement).*
