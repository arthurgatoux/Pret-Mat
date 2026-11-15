# Procédure de Sauvegarde et Restauration - PretMat

Ce guide détaille la procédure opérationnelle pour exporter et restaurer la base de données relationnelle MariaDB de **PretMat** à l'aide de l'utilitaire standard `mysqldump`. Il couvre l'environnement de développement Docker ainsi que l'hébergement en production (Alwaysdata).

---

## 1. Principes et options recommandées

Pour garantir une sauvegarde cohérente, sans verrouillage excessif des tables et avec préservation parfaite des caractères accentués (UTF-8) :

* `--single-transaction` : garantit la cohérence des données pour les tables InnoDB sans bloquer les lectures/écritures.
* `--default-character-set=utf8mb4` : préserve l'intégrité de l'encodage complet Unicode.
* `--quick` : lit les tables ligne par ligne plutôt que de tout charger en mémoire (recommandé pour limiter la consommation RAM).
* `--routines --triggers` : sauvegarde d'éventuels déclencheurs ou fonctions SQL.

---

## 2. Environnement local (Docker)

### A. Sauvegarder la base de données (Export)

Depuis la racine du projet, exécutez la commande suivante pour générer un fichier `.sql` horodaté dans un dossier `backups/` :

```bash
# Création du dossier de sauvegarde si nécessaire
mkdir -p backups

# Export complet des données et de la structure
docker compose exec -T db mysqldump -u root -p"$DB_ROOT_PASSWORD" \
    --default-character-set=utf8mb4 \
    --single-transaction \
    --quick \
    "$DB_NAME" > backups/pretmat_backup_$(date +%Y%m%d_%H%M%S).sql
```

*Sous PowerShell (Windows) :*
```powershell
New-Item -ItemType Directory -Force -Path backups
$date = Get-Date -Format "yyyyMMdd_HHmmss"
docker compose exec -T db mysqldump -u root -p"$env:DB_ROOT_PASSWORD" --default-character-set=utf8mb4 --single-transaction --quick "$env:DB_NAME" | Out-File -Encoding utf8 "backups/pretmat_backup_$date.sql"
```

### B. Restaurer la base de données (Import)

Pour réinjecter une sauvegarde existante dans le conteneur MariaDB :

```bash
# Exemple de restauration d'un dump
docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" < backups/pretmat_backup_YYYYMMDD_HHMMSS.sql
```

*Sous PowerShell (Windows) :*
```powershell
Get-Content backups/pretmat_backup_YYYYMMDD_HHMMSS.sql | docker compose exec -T db mariadb -u root -p"$env:DB_ROOT_PASSWORD" "$env:DB_NAME"
```

---

## 3. Déploiement en production (Alwaysdata)

Alwaysdata fournit un accès en ligne de commande via SSH et une console phpMyAdmin.

### A. Export depuis le serveur Alwaysdata (SSH)

Connectez-vous en SSH à votre compte Alwaysdata :
```bash
ssh <compte>@ssh-<compte>.alwaysdata.net
```

Lancez la sauvegarde de votre base hébergée :
```bash
mysqldump -h mysql-<compte>.alwaysdata.net \
    -u <compte>_app \
    -p \
    --default-character-set=utf8mb4 \
    --single-transaction \
    --quick \
    <compte>_pretmat > backup_prod_$(date +%Y%m%d).sql
```

### B. Restauration sur le serveur Alwaysdata (SSH)

Pour restaurer le schéma initial `schema.sql` ou un dump complet :
```bash
mariadb -h mysql-<compte>.alwaysdata.net \
    -u <compte>_app \
    -p \
    --default-character-set=utf8mb4 \
    <compte>_pretmat < sql/schema.sql
```

### C. Alternative via l'interface Web Alwaysdata (phpMyAdmin)

1. Connectez-vous sur le panneau d'administration Alwaysdata $\rightarrow$ **Bases de données** $\rightarrow$ **MySQL/MariaDB**.
2. Cliquez sur **phpMyAdmin**.
3. **Pour exporter** : sélectionnez la base `pretmat`, onglet **Exporter**, méthode *Personnalisée*, format *SQL*.
4. **Pour importer** : sélectionnez la base, onglet **Importer**, sélectionnez le fichier `schema.sql` ou `seed.sql`, puis cliquez sur **Exécuter**.

---

## 4. Mesures de sécurité

1. **Secrets et fichiers `.sql`** :
   * Les dumps de données réelles contiennent des informations d'utilisateurs et doivent être exclus de Git (déjà renseigné dans `.gitignore` : `backups/`, `*.dump`).
2. **Droits d'accès** :
   * En production, l'utilisateur applicatif (`DB_USER`) ne possède que les privilèges stricts `SELECT, INSERT, UPDATE, DELETE` sur sa base, sans droit d'administration globale.
