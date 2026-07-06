-- ============================================================
--  Migration v2 — Aligner la BD au nouveau MCD (IPIRNET)
--  À appliquer sur la base : gestion_des_stagiaires
--
--  Ordre d'exécution :
--    1. Supprimer l'association "cree" (pre_inscription ↔ stagiaires)
--    2. Ajouter sexe dans stagiaires
--    3. Rendre absences.id_module NOT NULL
-- ============================================================

USE gestion_des_stagiaires;

-- ────────────────────────────────────────────────────────────
--  CHANGEMENT 1 : Supprimer l'association "cree"
--  (pre_inscription.id_stagiaire_cree → stagiaires)
-- ────────────────────────────────────────────────────────────

-- 1a. Supprimer la contrainte de clé étrangère
ALTER TABLE pre_inscription
    DROP FOREIGN KEY fk_demande_inscription_stag;

-- 1b. Supprimer la colonne
ALTER TABLE pre_inscription
    DROP COLUMN id_stagiaire_cree;


-- ────────────────────────────────────────────────────────────
--  CHANGEMENT 2 : Ajouter sexe dans stagiaires
--  Le DEFAULT 'M' s'applique aux stagiaires déjà inscrits.
--  Révisez manuellement si nécessaire.
-- ────────────────────────────────────────────────────────────

ALTER TABLE stagiaires
    ADD COLUMN sexe VARCHAR(1) NOT NULL DEFAULT 'M'
    COMMENT 'M = Masculin, F = Féminin'
    AFTER nom_tuteur;


-- ────────────────────────────────────────────────────────────
--  CHANGEMENT 3 : absences.id_module NOT NULL
--  Le MCD impose (1,1) côté absences : chaque absence appartient
--  à exactement un module.
-- ────────────────────────────────────────────────────────────

-- 3a. Sécurité : supprimer les éventuelles absences sans module
--     (Dans les données actuelles, toutes les absences ont un id_module,
--      mais on nettoie par précaution.)
DELETE FROM absences WHERE id_module IS NULL;

-- 3b. Supprimer l'ancienne FK (elle avait ON DELETE SET NULL, incompatible
--     avec NOT NULL)
ALTER TABLE absences
    DROP FOREIGN KEY fk_absences_module;

-- 3c. Rendre la colonne NOT NULL
ALTER TABLE absences
    MODIFY COLUMN id_module INT(10) UNSIGNED NOT NULL
    COMMENT 'CDC pointage par cours / module';

-- 3d. Recréer la FK avec ON DELETE RESTRICT (empêche la suppression d'un
--     module ayant des absences — plus sûr que CASCADE)
ALTER TABLE absences
    ADD CONSTRAINT fk_absences_module
        FOREIGN KEY (id_module) REFERENCES modules(id_module)
        ON DELETE RESTRICT ON UPDATE CASCADE;
