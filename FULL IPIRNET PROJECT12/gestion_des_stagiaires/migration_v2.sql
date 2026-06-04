-- ============================================================
-- Migration V2: 
--   1. Rename stagiaires.matricule → stagiaires.num_inscri
--   2. Disconnect demandes_inscription from stagiaires
-- ============================================================

-- ---- Step 1: Rename matricule → num_inscri in stagiaires ----

-- Drop the old unique key
ALTER TABLE `stagiaires` DROP INDEX `uk_stagiaires_matricule`;

-- Rename the column
ALTER TABLE `stagiaires` CHANGE `matricule` `num_inscri` varchar(32) NOT NULL;

-- Recreate unique key with new name
ALTER TABLE `stagiaires` ADD UNIQUE KEY `uk_stagiaires_num_inscri` (`num_inscri`);


-- ---- Step 2: Update the auto-generation trigger ----

DROP TRIGGER IF EXISTS `tr_stagiaires_bi_matricule`;

DELIMITER $$
CREATE TRIGGER `tr_stagiaires_bi_num_inscri` BEFORE INSERT ON `stagiaires` FOR EACH ROW
BEGIN
  DECLARE y INT;
  DECLARE n INT;
  IF NEW.num_inscri IS NULL OR TRIM(NEW.num_inscri) = '' THEN
    SET y = YEAR(COALESCE(NEW.date_inscription, CURDATE()));
    IF NOT EXISTS (SELECT 1 FROM seq_inscription WHERE annee = y) THEN
      INSERT INTO seq_inscription (annee, last_num) VALUES (y, 0);
    END IF;
    UPDATE seq_inscription SET last_num = last_num + 1 WHERE annee = y;
    SELECT last_num INTO n FROM seq_inscription WHERE annee = y;
    SET NEW.num_inscri = CONCAT('INS-', y, '-', LPAD(n, 5, '0'));
  END IF;
END
$$
DELIMITER ;


-- ---- Step 3: Recreate the view with num_inscri ----

DROP VIEW IF EXISTS `v_stagiaires_detail`;

CREATE VIEW `v_stagiaires_detail` AS
SELECT 
    `s`.`id_stagiaire`,
    `s`.`num_inscri`,
    `s`.`cin`,
    `s`.`nom`,
    `s`.`prenom`,
    `s`.`email`,
    `s`.`telephone`,
    `s`.`telephone_parent`,
    `s`.`nom_tuteur`,
    `s`.`date_inscription`,
    `c`.`id_classe`,
    `c`.`nom_classe`,
    `c`.`annee_scolaire`,
    `f`.`id_filiere`,
    `f`.`nom_filiere`,
    `f`.`niveau` AS `niveau_filiere`
FROM `stagiaires` `s`
JOIN `classes` `c` ON `c`.`id_classe` = `s`.`id_classe`
JOIN `filieres` `f` ON `f`.`id_filiere` = `c`.`id_filiere`;


-- ---- Step 4: Disconnect demandes_inscription from stagiaires ----

-- Drop the foreign key linking demandes to stagiaires
ALTER TABLE `demandes_inscription` DROP FOREIGN KEY `fk_demande_inscription_stag`;

-- Drop the index for that FK
ALTER TABLE `demandes_inscription` DROP INDEX `fk_demande_inscription_stag`;

-- Remove the id_stagiaire_cree column entirely
ALTER TABLE `demandes_inscription` DROP COLUMN `id_stagiaire_cree`;

-- Done! demandes_inscription now only links to classes (for the form dropdown).
