-- Migration: seed all filière classes and modules
-- Safe to run multiple times (uses INSERT IGNORE)

-- 1. Ensure filieres exist (Removed OPAD)
INSERT IGNORE INTO filieres (nom_filiere) VALUES
    ('Technicien Spécialisé en Développement Informatique'),
    ('Technicien en Informatique de Gestion'),
    ('Technicien Spécialisé en Gestion des Entreprises');

-- 2. Insert classes for each filiere (1ère and 2ème année)
-- TSDI
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TSDI', '1ère année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Développement Informatique';
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TSDI', '2ème année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Développement Informatique';

-- TSGE
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TSGE', '1ère année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TSGE', '2ème année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';

-- TGI
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A TGI', '1ère année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A TGI', '2ème année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';

-- 3. Insert Modules with correct coefficients
-- TSDI (Assuming f.id_filiere for TSDI)
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Métier et Formation', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Développement Informatique';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Algorithmique et Programmation', f.id_filiere, 5 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Développement Informatique';

-- TSGE Modules
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Comptabilité générale', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Concept de base', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Traitement de salaire', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Charge de personnel', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Marketing', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Entreprise', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Statistique', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien Spécialisé en Gestion des Entreprises';

-- TGI Modules
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Algorithm', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Installation d\'un poste', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Bureautique', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Comptabilité générale', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
INSERT IGNORE INTO modules (nom_module, id_filiere, coefficient)
SELECT 'Statistique', f.id_filiere, 1 FROM filieres f WHERE f.nom_filiere = 'Technicien en Informatique de Gestion';
