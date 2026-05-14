-- ============================================================
-- CLEANUP: Remove duplicate filieres and classes
-- Run this ONCE on your database to fix the duplicates.
-- ============================================================

-- Step 1: For each filiere name, keep only the LOWEST id, delete the rest.
-- This also cascades to classes if you have ON DELETE CASCADE,
-- otherwise we handle classes manually below.

-- First, reassign classes from duplicate filieres to the canonical (lowest id) filiere
UPDATE classes c
JOIN filieres f ON f.id_filiere = c.id_filiere
JOIN (
    SELECT nom_filiere, MIN(id_filiere) AS keep_id
    FROM filieres
    GROUP BY nom_filiere
) canon ON canon.nom_filiere = f.nom_filiere
SET c.id_filiere = canon.keep_id
WHERE c.id_filiere != canon.keep_id;

-- Step 2: Delete duplicate filieres (keep only the one with lowest id per name)
DELETE f FROM filieres f
JOIN (
    SELECT nom_filiere, MIN(id_filiere) AS keep_id
    FROM filieres
    GROUP BY nom_filiere
) canon ON canon.nom_filiere = f.nom_filiere
WHERE f.id_filiere != canon.keep_id;

-- Step 3: Remove duplicate classes (same nom_classe + id_filiere, keep lowest id)
DELETE c FROM classes c
JOIN (
    SELECT nom_classe, id_filiere, MIN(id_classe) AS keep_id
    FROM classes
    GROUP BY nom_classe, id_filiere
) canon ON canon.nom_classe = c.nom_classe AND canon.id_filiere = c.id_filiere
WHERE c.id_classe != canon.keep_id;

-- Step 4: Insert any still-missing classes (safe now that duplicates are gone)
-- TSDI
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TSDI', '1ère année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien Spécialisé en Développement Informatique'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TSDI', '2ème année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien Spécialisé en Développement Informatique'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

-- TSGE
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TSGE', '1ère année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TSGE', '2ème année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

-- TGI
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TGI', '1ère année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien en Informatique de Gestion'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TGI', '2ème année', id_filiere FROM filieres WHERE nom_filiere = 'Technicien en Informatique de Gestion'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

-- OPAD
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A OPAD', '1ère année', id_filiere FROM filieres WHERE nom_filiere = 'Opérateur Administratif'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A OPAD', '2ème année', id_filiere FROM filieres WHERE nom_filiere = 'Opérateur Administratif'
ON DUPLICATE KEY UPDATE nom_classe = nom_classe;

-- Done. You should now have exactly 1 entry per filiere and 2 classes per filiere.
