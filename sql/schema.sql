-- ==============================================================================
-- Schéma relationnel de la base de données PretMat (MariaDB / MySQL)
-- Projet de formation : TP Développeur Web et Web Mobile
-- Règles : SQL écrit à la main, sans ORM, moteur InnoDB, encodage utf8mb4
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- Table : users
-- Utilisateurs authentifiés via Google OAuth 2.0
-- ------------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_sub VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Contrainte d'unicité de l'identifiant OpenID stable Google sub pour éviter les comptes multiples
    CONSTRAINT uq_users_google_sub UNIQUE (google_sub),
    -- Contrainte d'unicité de l'e-mail pour garantir une adresse unique par profil
    CONSTRAINT uq_users_email UNIQUE (email),
    -- Contrainte de domaine restreignant les valeurs de rôles autorisées aux seuls profils member et admin
    CONSTRAINT chk_users_role CHECK (role IN ('member', 'admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table : categories
-- Catégories d'équipements gérées par le club
-- ------------------------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    -- Contrainte d'unicité sur le nom de la catégorie pour proscrire les doublons taxonomiques
    CONSTRAINT uq_categories_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table : items
-- Matériel disponible au prêt au sein du club
-- ------------------------------------------------------------------------------
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    category_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Clé étrangère interdisant la suppression d'une catégorie tant qu'elle référence des matériels existants
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table : loans
-- Historique et suivi en direct des transactions de prêts
-- ------------------------------------------------------------------------------
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    borrowed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_at DATETIME NOT NULL,
    returned_at DATETIME NULL DEFAULT NULL,
    -- Clé étrangère liant le prêt à l'emprunteur et empêchant la suppression d'un utilisateur ayant des prêts
    CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    -- Clé étrangère liant le prêt au matériel et interdisant la suppression d'un équipement ayant un historique de prêt
    CONSTRAINT fk_loans_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE RESTRICT,
    -- Contrainte de validation temporelle imposant une date d'échéance strictement postérieure à la date d'emprunt
    CONSTRAINT chk_loans_due_after_borrowed CHECK (due_at > borrowed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index composite optimisant la détection des prêts en cours pour un matériel donné
CREATE INDEX idx_loans_item_returned ON loans (item_id, returned_at);

-- Index composite optimisant le calcul des prêts actifs et de l'historique par utilisateur
CREATE INDEX idx_loans_user_returned ON loans (user_id, returned_at);
