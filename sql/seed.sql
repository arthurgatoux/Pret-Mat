-- ==============================================================================
-- Jeu de données d'essai (Seed) pour PretMat
-- 3 catégories, 8 matériels, 2 utilisateurs fictifs, 3 prêts types
-- ==============================================================================

-- 1. Insertion des 3 catégories
INSERT INTO categories (id, name) VALUES
(1, 'Photo & Vidéo'),
(2, 'Son & Audio'),
(3, 'Éclairage & Studio');

-- 2. Insertion des 8 matériels
INSERT INTO items (id, name, description, category_id, created_at) VALUES
(1, 'Appareil photo Sony Alpha 7 III', 'Boîtier hybride plein format 24 Mpx avec sangle et batterie', 1, NOW()),
(2, 'Objectif Sony FE 24-70mm f/2.8 GM', 'Zoom transtandard professionnel avec paresoleil et bouchons', 1, NOW()),
(3, 'Trépied vidéo Manfrotto 504HD', 'Trépied aluminium robuste avec rotule fluide et plateau rapide', 1, NOW()),
(4, 'Micro cravate Rode Wireless GO II', 'Kit HF 2 émetteurs + 1 récepteur double canal sans fil', 2, NOW()),
(5, 'Microphone broadcast Shure SM7B', 'Microphone dynamique cardioïde de référence pour studio/podcast', 2, NOW()),
(6, 'Enregistreur multipiste Zoom H6 Black', 'Enregistreur autonome 6 pistes avec capsule XY et carte SD 64Go', 2, NOW()),
(7, 'Panneau LED Neewer 660 RGB', 'Éclairage bicolore 3200-5600K avec coupe-flux et rotule inclinable', 3, NOW()),
(8, 'Softbox octogonale Godox 80cm', 'Boîte à lumière pliable avec double toile diffusante et monture Bowens', 3, NOW());

-- 3. Insertion des 2 utilisateurs fictifs
INSERT INTO users (id, google_sub, email, name, role, created_at) VALUES
(1, 'fake_sub_admin_001', 'admin@pretmat.local', 'Alice Dupont (Admin)', 'admin', NOW()),
(2, 'fake_sub_member_002', 'membre@pretmat.local', 'Bob Martin (Membre)', 'member', NOW());

-- 4. Insertion des prêts types
-- Prêt 1 : EN RETARD (emprunté il y a 10 jours, date limite dépassée il y a 3 jours, non rendu)
INSERT INTO loans (id, user_id, item_id, borrowed_at, due_at, returned_at) VALUES
(1, 2, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), NULL);

-- Prêt 2 : RENDU / CLÔTURÉ (emprunté il y a 15 jours, rendu il y a 9 jours)
INSERT INTO loans (id, user_id, item_id, borrowed_at, due_at, returned_at) VALUES
(2, 2, 4, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY));

-- Prêt 3 : EN COURS ACTIF (emprunté hier, date limite dans 6 jours, non rendu)
INSERT INTO loans (id, user_id, item_id, borrowed_at, due_at, returned_at) VALUES
(3, 1, 7, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 6 DAY), NULL);
