-- Migration: seed all filière classes
-- Run once on your MySQL database
-- Safe to run multiple times (uses INSERT IGNORE)

-- Ensure filieres exist
INSERT IGNORE INTO filieres (nom_filiere) VALUES
    ('Technicien Spécialisé en Développement Informatique'),
    ('Technicien en Informatique de Gestion'),
    ('Technicien Spécialisé en Gestion des Entreprises'),
    ('Opérateur Administratif');

-- Insert classes for each filiere (1ère and 2ème année)
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

-- OPAD
INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '1A OPAD', '1ère année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Opérateur Administratif';

INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere)
SELECT '2A OPAD', '2ème année', f.id_filiere FROM filieres f WHERE f.nom_filiere = 'Opérateur Administratif';
