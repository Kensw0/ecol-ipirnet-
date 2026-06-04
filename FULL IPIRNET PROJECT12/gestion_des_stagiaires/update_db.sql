ALTER TABLE gestion_des_stagiaires.modules ADD COLUMN coefficient INT DEFAULT 1;

UPDATE gestion_des_stagiaires.modules SET coefficient = 5 WHERE nom_module LIKE '%Développement%' OR nom_module LIKE '%Programmation%';
UPDATE gestion_des_stagiaires.modules SET coefficient = 3 WHERE nom_module LIKE '%Système%';

DROP VIEW IF EXISTS gestion_des_stagiaires.v_moyennes_par_module;
DROP TABLE IF EXISTS gestion_des_stagiaires.evaluer;

CREATE TABLE gestion_des_stagiaires.module_notes (
    id_stagiaire INT(10) UNSIGNED NOT NULL,
    id_module INT(10) UNSIGNED NOT NULL,
    note_controle DECIMAL(5,2) DEFAULT NULL,
    note_theorique DECIMAL(5,2) DEFAULT NULL,
    note_pratique DECIMAL(5,2) DEFAULT NULL,
    PRIMARY KEY(id_stagiaire, id_module),
    FOREIGN KEY(id_stagiaire) REFERENCES stagiaires(id_stagiaire) ON DELETE CASCADE,
    FOREIGN KEY(id_module) REFERENCES modules(id_module) ON DELETE CASCADE
);

CREATE VIEW gestion_des_stagiaires.v_moyennes_par_module AS
SELECT 
    mn.id_stagiaire, 
    mn.id_module, 
    m.nom_module, 
    m.coefficient,
    mn.note_controle, 
    mn.note_theorique, 
    mn.note_pratique,
    IF(mn.note_theorique IS NOT NULL AND mn.note_pratique IS NOT NULL, (mn.note_theorique + mn.note_pratique)/2, 
       IF(mn.note_theorique IS NOT NULL, mn.note_theorique, 
          IF(mn.note_pratique IS NOT NULL, mn.note_pratique, NULL)
       )
    ) AS note_examen,
    IF(mn.note_controle IS NOT NULL,
       IF((mn.note_theorique IS NOT NULL OR mn.note_pratique IS NOT NULL),
           (mn.note_controle + IF(mn.note_theorique IS NOT NULL AND mn.note_pratique IS NOT NULL, (mn.note_theorique + mn.note_pratique)/2, IF(mn.note_theorique IS NOT NULL, mn.note_theorique, mn.note_pratique))) / 2,
           mn.note_controle
       ),
       IF((mn.note_theorique IS NOT NULL OR mn.note_pratique IS NOT NULL),
           IF(mn.note_theorique IS NOT NULL AND mn.note_pratique IS NOT NULL, (mn.note_theorique + mn.note_pratique)/2, IF(mn.note_theorique IS NOT NULL, mn.note_theorique, mn.note_pratique)),
           NULL
       )
    ) AS moyenne_module
FROM gestion_des_stagiaires.module_notes mn
JOIN gestion_des_stagiaires.modules m ON mn.id_module = m.id_module;
