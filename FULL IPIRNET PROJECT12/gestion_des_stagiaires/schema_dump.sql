-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gestion_des_stagiaires
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `absences`
--

DROP TABLE IF EXISTS `absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `absences` (
  `id_absence` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_absence` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `justificatif` varchar(1024) DEFAULT NULL,
  `est_justifiee` tinyint(1) NOT NULL DEFAULT 0,
  `id_stagiaire` int(10) unsigned NOT NULL,
  `id_module` int(10) unsigned DEFAULT NULL COMMENT 'CDC pointage par cours / module',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_absence`),
  KEY `fk_absences_stagiaire` (`id_stagiaire`),
  KEY `fk_absences_module` (`id_module`),
  CONSTRAINT `fk_absences_module` FOREIGN KEY (`id_module`) REFERENCES `modules` (`id_module`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_absences_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id_classe` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nom_classe` varchar(128) NOT NULL,
  `annee_scolaire` varchar(16) NOT NULL,
  `id_filiere` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_classe`),
  KEY `idx_classes_filiere_annee` (`id_filiere`,`annee_scolaire`),
  CONSTRAINT `fk_classes_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filieres` (`id_filiere`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `demandes_inscription`
--

DROP TABLE IF EXISTS `demandes_inscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demandes_inscription` (
  `id_demande` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cin` varchar(32) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(32) DEFAULT NULL,
  `telephone_parent` varchar(32) DEFAULT NULL,
  `nom_tuteur` varchar(255) DEFAULT NULL,
  `id_classe` int(10) unsigned NOT NULL,
  `statut` enum('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `date_soumission` datetime NOT NULL DEFAULT current_timestamp(),
  `date_decision` datetime DEFAULT NULL,
  PRIMARY KEY (`id_demande`),
  KEY `fk_demande_inscription_classe` (`id_classe`),
  KEY `idx_demandes_statut` (`statut`,`date_soumission`),
  CONSTRAINT `fk_demande_inscription_classe` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents_generes`
--

DROP TABLE IF EXISTS `documents_generes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents_generes` (
  `id_gen` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_document` enum('certificat_scolarite','billet_excuse','etat_mensualites','releve_notes','bulletin','attestation_reussite','convention_stage','autre') NOT NULL,
  `id_stagiaire` int(10) unsigned NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `genere_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_gen`),
  KEY `fk_docgen_stagiaire` (`id_stagiaire`),
  CONSTRAINT `fk_docgen_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `filieres`
--

DROP TABLE IF EXISTS `filieres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `filieres` (
  `id_filiere` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nom_filiere` varchar(255) NOT NULL,
  `niveau` varchar(128) DEFAULT NULL,
  `capacite` int(11) NOT NULL DEFAULT 30,
  PRIMARY KEY (`id_filiere`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mensualites`
--

DROP TABLE IF EXISTS `mensualites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensualites` (
  `id_mensualite` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_stagiaire` int(10) unsigned NOT NULL,
  `mois_ref` char(7) NOT NULL COMMENT 'YYYY-MM',
  `est_paye` tinyint(1) NOT NULL DEFAULT 0,
  `montant_total` float DEFAULT NULL,
  `montant_paye` float DEFAULT NULL,
  `montant_restant` float DEFAULT NULL,
  `cumul_restant` float DEFAULT NULL,
  `statut_paiement` varchar(32) DEFAULT NULL,
  `date_paiement` datetime DEFAULT NULL,
  `marque_le` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_mensualite`),
  UNIQUE KEY `uk_mensualite_stag_mois` (`id_stagiaire`,`mois_ref`),
  KEY `idx_mensualites_mois` (`mois_ref`,`est_paye`),
  CONSTRAINT `fk_mensualites_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_notes`
--

DROP TABLE IF EXISTS `module_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `module_notes` (
  `id_stagiaire` int(10) unsigned NOT NULL,
  `id_module` int(10) unsigned NOT NULL,
  `note_controle` decimal(5,2) DEFAULT NULL,
  `note_theorique` decimal(5,2) DEFAULT NULL,
  `note_pratique` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_stagiaire`,`id_module`),
  KEY `id_module` (`id_module`),
  CONSTRAINT `module_notes_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE,
  CONSTRAINT `module_notes_ibfk_2` FOREIGN KEY (`id_module`) REFERENCES `modules` (`id_module`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id_module` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nom_module` varchar(255) NOT NULL,
  `masse_horaire` smallint(5) unsigned DEFAULT NULL,
  `semestre` tinyint(3) unsigned DEFAULT NULL,
  `id_filiere` int(10) unsigned NOT NULL,
  `coefficient` int(11) DEFAULT 1,
  PRIMARY KEY (`id_module`),
  KEY `idx_modules_filiere` (`id_filiere`),
  CONSTRAINT `fk_modules_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filieres` (`id_filiere`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `seq_inscription`
--

DROP TABLE IF EXISTS `seq_inscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seq_inscription` (
  `annee` int(11) NOT NULL,
  `last_num` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stages`
--

DROP TABLE IF EXISTS `stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stages` (
  `id_stage` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_stage` enum('stage_entreprise','pfe') NOT NULL DEFAULT 'stage_entreprise',
  `sujet` varchar(512) DEFAULT NULL,
  `entreprise` varchar(255) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `note_stage` decimal(5,2) DEFAULT NULL,
  `convention_url` varchar(1024) DEFAULT NULL,
  `rapport_url` varchar(1024) DEFAULT NULL,
  `evaluation_entreprise` varchar(512) DEFAULT NULL,
  `date_soutenance` date DEFAULT NULL COMMENT 'CDC PFE : soutenances',
  `jury` text DEFAULT NULL COMMENT 'Membres jury / modalit?s passage',
  `id_stagiaire` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_stage`),
  KEY `fk_stages_stagiaire` (`id_stagiaire`),
  CONSTRAINT `fk_stages_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stagiaires`
--

DROP TABLE IF EXISTS `stagiaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stagiaires` (
  `id_stagiaire` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `num_inscri` varchar(32) NOT NULL,
  `cin` varchar(32) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(32) DEFAULT NULL,
  `telephone_parent` varchar(32) DEFAULT NULL COMMENT 'CDC fiche inscription',
  `nom_tuteur` varchar(255) DEFAULT NULL COMMENT 'Père ou tuteur',
  `mot_de_passe` varchar(255) NOT NULL,
  `photo` varchar(512) DEFAULT NULL,
  `date_inscription` date NOT NULL,
  `id_classe` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_stagiaire`),
  UNIQUE KEY `uk_stagiaires_num_inscri` (`num_inscri`),
  UNIQUE KEY `uk_stagiaires_email` (`email`),
  KEY `idx_stagiaires_classe` (`id_classe`),
  CONSTRAINT `fk_stagiaires_classe` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_stagiaires_bi_num_inscri
BEFORE INSERT ON stagiaires
FOR EACH ROW
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Temporary table structure for view `v_moyennes_par_module`
--

DROP TABLE IF EXISTS `v_moyennes_par_module`;
/*!50001 DROP VIEW IF EXISTS `v_moyennes_par_module`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_moyennes_par_module` AS SELECT
 1 AS `id_stagiaire`,
  1 AS `id_module`,
  1 AS `nom_module`,
  1 AS `coefficient`,
  1 AS `note_controle`,
  1 AS `note_theorique`,
  1 AS `note_pratique`,
  1 AS `note_examen`,
  1 AS `moyenne_module` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_stagiaires_detail`
--

DROP TABLE IF EXISTS `v_stagiaires_detail`;
/*!50001 DROP VIEW IF EXISTS `v_stagiaires_detail`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_stagiaires_detail` AS SELECT
 1 AS `id_stagiaire`,
  1 AS `num_inscri`,
  1 AS `cin`,
  1 AS `nom`,
  1 AS `prenom`,
  1 AS `email`,
  1 AS `telephone`,
  1 AS `telephone_parent`,
  1 AS `nom_tuteur`,
  1 AS `date_inscription`,
  1 AS `id_classe`,
  1 AS `nom_classe`,
  1 AS `annee_scolaire`,
  1 AS `id_filiere`,
  1 AS `nom_filiere`,
  1 AS `niveau_filiere` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_moyennes_par_module`
--

/*!50001 DROP VIEW IF EXISTS `v_moyennes_par_module`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_moyennes_par_module` AS select `mn`.`id_stagiaire` AS `id_stagiaire`,`mn`.`id_module` AS `id_module`,`m`.`nom_module` AS `nom_module`,`m`.`coefficient` AS `coefficient`,`mn`.`note_controle` AS `note_controle`,`mn`.`note_theorique` AS `note_theorique`,`mn`.`note_pratique` AS `note_pratique`,if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,if(`mn`.`note_pratique` is not null,`mn`.`note_pratique`,NULL))) AS `note_examen`,if(`mn`.`note_controle` is not null,if(`mn`.`note_theorique` is not null or `mn`.`note_pratique` is not null,(`mn`.`note_controle` + if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,`mn`.`note_pratique`))) / 2,`mn`.`note_controle`),if(`mn`.`note_theorique` is not null or `mn`.`note_pratique` is not null,if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,`mn`.`note_pratique`)),NULL)) AS `moyenne_module` from (`module_notes` `mn` join `modules` `m` on(`mn`.`id_module` = `m`.`id_module`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_stagiaires_detail`
--

/*!50001 DROP VIEW IF EXISTS `v_stagiaires_detail`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_stagiaires_detail` AS select `s`.`id_stagiaire` AS `id_stagiaire`,`s`.`num_inscri` AS `num_inscri`,`s`.`cin` AS `cin`,`s`.`nom` AS `nom`,`s`.`prenom` AS `prenom`,`s`.`email` AS `email`,`s`.`telephone` AS `telephone`,`s`.`telephone_parent` AS `telephone_parent`,`s`.`nom_tuteur` AS `nom_tuteur`,`s`.`date_inscription` AS `date_inscription`,`c`.`id_classe` AS `id_classe`,`c`.`nom_classe` AS `nom_classe`,`c`.`annee_scolaire` AS `annee_scolaire`,`f`.`id_filiere` AS `id_filiere`,`f`.`nom_filiere` AS `nom_filiere`,`f`.`niveau` AS `niveau_filiere` from ((`stagiaires` `s` join `classes` `c` on(`c`.`id_classe` = `s`.`id_classe`)) join `filieres` `f` on(`f`.`id_filiere` = `c`.`id_filiere`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-14 23:38:41
