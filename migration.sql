-- ============================================================
-- migration.sql — Mise à jour base gestion_des_stagiaires
-- Fonctionne peu importe l'état actuel de la base :
--   • base avec ancienne table `evalue`
--   • base avec ancienne table `module_notes` (colonnes fixes)
--   • base sans aucune des deux
--   • base déjà à jour (idempotent)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Étape 1 : Supprimer l'ancienne table module_notes si elle existe
--    (ancienne structure avec colonnes fixes note_controle/theorique/pratique)
DROP TABLE IF EXISTS `module_notes`;

-- ── Étape 2 : Renommer evalue → module_notes si evalue existe
--    Sinon créer module_notes depuis zéro
DROP PROCEDURE IF EXISTS `migration_setup`;
DELIMITER $$
CREATE PROCEDURE `migration_setup`()
BEGIN
  -- Vérifie si la table evalue existe
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'evalue'
  ) THEN
    RENAME TABLE `evalue` TO `module_notes`;
  ELSE
    -- Crée module_notes depuis zéro (nouvelle structure dynamique)
    CREATE TABLE IF NOT EXISTS `module_notes` (
      `id_stagiaire` int(10) UNSIGNED NOT NULL,
      `id_module`    int(10) UNSIGNED NOT NULL,
      `note`         decimal(5,2) DEFAULT NULL,
      `type`         varchar(32)  NOT NULL COMMENT 'controle_1, controle_2, ..., theorique, pratique',
      PRIMARY KEY (`id_stagiaire`,`id_module`,`type`),
      KEY `idx_module_notes_module` (`id_module`),
      CONSTRAINT `chk_module_notes_note` CHECK (`note` IS NULL OR (`note` >= 0 AND `note` <= 20)),
      CONSTRAINT `fk_module_notes_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_module_notes_module`    FOREIGN KEY (`id_module`)    REFERENCES `modules`    (`id_module`)    ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  END IF;
END$$
DELIMITER ;

CALL `migration_setup`();
DROP PROCEDURE IF EXISTS `migration_setup`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Étape 3 : Recréer la vue v_moyennes_par_module ────────────────────────
DROP VIEW IF EXISTS `v_moyennes_par_module`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER
VIEW `v_moyennes_par_module` AS
SELECT
  mn.`id_stagiaire`,
  mn.`id_module`,
  m.`nom_module`,
  m.`coefficient`,
  m.`nb_controles`,
  AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END) AS `note_controle`,
  MAX(CASE WHEN mn.`type` = 'theorique'   THEN mn.`note` END) AS `note_theorique`,
  MAX(CASE WHEN mn.`type` = 'pratique'    THEN mn.`note` END) AS `note_pratique`,
  CASE
    WHEN MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END) IS NOT NULL
     AND MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END) IS NOT NULL
    THEN (MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END)
        + MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END)) / 2
    WHEN MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END) IS NOT NULL
    THEN  MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END)
    WHEN MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END) IS NOT NULL
    THEN  MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END)
    ELSE NULL
  END AS `note_examen`,
  CASE
    WHEN AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END) IS NOT NULL
     AND MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END) IS NOT NULL
     AND MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END) IS NOT NULL
    THEN ROUND(
           AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END) * 0.40
         + MAX(CASE WHEN mn.`type` = 'theorique'    THEN mn.`note` END) * 0.30
         + MAX(CASE WHEN mn.`type` = 'pratique'     THEN mn.`note` END) * 0.30, 2)
    WHEN AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END) IS NOT NULL
     AND (MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END) IS NOT NULL
       OR  MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END) IS NOT NULL)
    THEN ROUND(
           AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END) * 0.40
         + COALESCE(MAX(CASE WHEN mn.`type` = 'theorique' THEN mn.`note` END), 0) * 0.30
         + COALESCE(MAX(CASE WHEN mn.`type` = 'pratique'  THEN mn.`note` END), 0) * 0.30, 2)
    ELSE AVG(CASE WHEN mn.`type` LIKE 'controle%' THEN mn.`note` END)
  END AS `moyenne_module`
FROM `module_notes` mn
JOIN `modules` m ON m.`id_module` = mn.`id_module`
GROUP BY mn.`id_stagiaire`, mn.`id_module`, m.`nom_module`, m.`coefficient`, m.`nb_controles`;

SELECT 'Migration terminée avec succès.' AS statut;


-- ============================================================
-- feat: add etat_paiements_annuel to documents_generes ENUM
-- Run this on the live database after deploying the PHP changes
-- ============================================================

-- Step 1: Expand the ENUM to include the new document type
ALTER TABLE `documents_generes`
MODIFY COLUMN `type_document`
  enum('certificat_scolarite','billet_excuse','etat_mensualites','releve_notes','bulletin',
       'attestation_reussite','convention_stage','fiche_inscription','recu_paiement',
       'fiche_preinscription','liste_stagiaires','etat_paiement','rapport_individuel',
       'etat_paiements_annuel','autre')
  NOT NULL DEFAULT 'autre';

-- Step 2: Fix existing rows that were mislogged as 'autre' because the ENUM
--         didn't accept 'etat_paiements_annuel' yet. These rows have a reference
--         matching a student num_inscri, logged after the feature was introduced.
--         Review before running — adjust the date cutoff if needed.
UPDATE `documents_generes`
SET    `type_document` = 'etat_paiements_annuel'
WHERE  `type_document` = 'autre'
  AND  `reference` IN (SELECT `num_inscri` FROM `stagiaires`)
  AND  `genere_le` >= '2026-06-13';

SELECT CONCAT('Rows updated: ', ROW_COUNT()) AS statut;
