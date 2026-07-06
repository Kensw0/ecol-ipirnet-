-- Migration v3 – Add sexe to v_stagiaires_detail view
-- Run this on your live MariaDB to fix the sexe dropdown in the hub edit modal.
-- Safe to run multiple times (CREATE OR REPLACE).

CREATE OR REPLACE ALGORITHM=UNDEFINED
  DEFINER=`root`@`localhost`
  SQL SECURITY DEFINER
  VIEW `v_stagiaires_detail` AS
SELECT
  `s`.`id_stagiaire`       AS `id_stagiaire`,
  `s`.`num_inscri`         AS `num_inscri`,
  `s`.`cin`                AS `cin`,
  `s`.`nom`                AS `nom`,
  `s`.`prenom`             AS `prenom`,
  `s`.`email`              AS `email`,
  `s`.`telephone`          AS `telephone`,
  `s`.`telephone_parent`   AS `telephone_parent`,
  `s`.`nom_tuteur`         AS `nom_tuteur`,
  `s`.`date_inscription`   AS `date_inscription`,
  `s`.`date_naissance`     AS `date_naissance`,
  `s`.`sexe`               AS `sexe`,
  `s`.`id_classe`          AS `id_classe`,
  `c`.`nom_classe`         AS `nom_classe`,
  `c`.`annee_scolaire`     AS `annee_scolaire`,
  `c`.`niveau`             AS `niveau_classe`,
  `f`.`id_filiere`         AS `id_filiere`,
  `f`.`nom_filiere`        AS `nom_filiere`,
  `f`.`niveau`             AS `niveau_filiere`
FROM
  `stagiaires` `s`
  JOIN `classes`  `c` ON `c`.`id_classe`  = `s`.`id_classe`
  JOIN `filieres` `f` ON `f`.`id_filiere` = `c`.`id_filiere`;
