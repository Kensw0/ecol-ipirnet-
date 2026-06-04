-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2026 at 03:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gestion_des_stagiaires`
--

-- --------------------------------------------------------

--
-- Table structure for table `absences`
--

CREATE TABLE `absences` (
  `id_absence` int(10) UNSIGNED NOT NULL,
  `date_absence` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `justificatif` varchar(1024) DEFAULT NULL,
  `est_justifiee` tinyint(1) NOT NULL DEFAULT 0,
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `id_module` int(10) UNSIGNED DEFAULT NULL COMMENT 'CDC pointage par cours / module',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `absences`
--

INSERT INTO `absences` (`id_absence`, `date_absence`, `heure_debut`, `heure_fin`, `justificatif`, `est_justifiee`, `id_stagiaire`, `id_module`, `created_at`) VALUES
(4, '2026-05-14', '14:30:00', '16:00:00', NULL, 1, 8, 2, '2026-05-14 20:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id_classe` int(10) UNSIGNED NOT NULL,
  `nom_classe` varchar(128) NOT NULL,
  `annee_scolaire` varchar(16) NOT NULL,
  `id_filiere` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id_classe`, `nom_classe`, `annee_scolaire`, `id_filiere`) VALUES
(1, '1A TSDI', '2025-2026', 2),
(2, '2A TSDI', '2025-2026', 2),
(5, '1A TSGE', '1ère année', 4),
(6, '2A TSGE', '2ème année', 4),
(7, '1A TGI', '1ère année', 3),
(8, '2A TGI', '2ème année', 3),
(9, '1A OPAD', '1ère année', 5),
(10, '2A OPAD', '2ème année', 5),
(113, '1A TSDI', '1ère année', 2),
(114, '2A TSDI', '2ème année', 2),
(115, '1A TSGE', '1ère année', 4),
(116, '2A TSGE', '2ème année', 4),
(117, '1A TGI', '1ère année', 3),
(118, '2A TGI', '2ème année', 3),
(119, '1A OPAD', '1ère année', 5),
(120, '2A OPAD', '2ème année', 5);

-- --------------------------------------------------------

--
-- Table structure for table `demandes_inscription`
--

CREATE TABLE `demandes_inscription` (
  `id_demande` int(10) UNSIGNED NOT NULL,
  `cin` varchar(32) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(32) DEFAULT NULL,
  `telephone_parent` varchar(32) DEFAULT NULL,
  `nom_tuteur` varchar(255) DEFAULT NULL,
  `id_classe` int(10) UNSIGNED NOT NULL,
  `statut` enum('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `date_soumission` datetime NOT NULL DEFAULT current_timestamp(),
  `date_decision` datetime DEFAULT NULL,
  `id_stagiaire_cree` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `demandes_inscription`
--

INSERT INTO `demandes_inscription` (`id_demande`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `id_classe`, `statut`, `date_soumission`, `date_decision`, `id_stagiaire_cree`) VALUES
(1, 'wa23174', 'El Mehdi', 'Bergam', '2001-12-24', '122 RUE PALESTINE', 'mehdibergame@gmail.com', '0650757944', '0642551292', 'amina yassin', 1, 'acceptee', '2026-05-13 16:48:40', '2026-05-13 16:50:15', NULL),
(2, 'wz1514121', 'nadia', 'bergam', '2005-02-24', 'Rue palestine', 'nadiabergame@gmail.com', '06507579', '06507579', 'amina', 113, 'acceptee', '2026-05-14 20:20:57', '2026-05-14 20:27:22', NULL),
(3, 'wa121518', 'el mehdi', 'bergam', '2005-02-14', '122 RUE PALESTINE', 'mehdibergame@gmail.com', '0650757944', '0682427801', 'amina', 113, 'acceptee', '2026-05-14 20:33:39', '2026-05-14 20:33:49', 8),
(4, 'wa123456', 'khawla', 'bergam', '2005-02-22', '1 RUE PALESTINE tissir 2', 'mehdibergame7@gmail.com', '0650757944', '0650757944', 'Amina yassin', 5, 'acceptee', '2026-05-14 22:07:22', '2026-05-14 22:12:44', 11);

-- --------------------------------------------------------

--
-- Table structure for table `documents_generes`
--

CREATE TABLE `documents_generes` (
  `id_gen` int(10) UNSIGNED NOT NULL,
  `type_document` enum('certificat_scolarite','billet_excuse','etat_mensualites','releve_notes','bulletin','attestation_reussite','convention_stage','fiche_inscription','recu_paiement','autre') NOT NULL,
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `genere_le` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents_generes`
--

INSERT INTO `documents_generes` (`id_gen`, `type_document`, `id_stagiaire`, `reference`, `genere_le`) VALUES
(59, 'fiche_inscription', 8, 'INS-2026-00001', '2026-05-14 20:33:56'),
(60, 'certificat_scolarite', 8, 'INS-2026-00001', '2026-05-14 20:34:02'),
(61, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 20:34:07'),
(62, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 21:02:34'),
(63, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 21:04:47'),
(64, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 21:05:04'),
(65, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 21:09:49'),
(66, 'billet_excuse', 8, 'ABS-4', '2026-05-14 21:55:01'),
(67, 'fiche_inscription', 11, 'INS-2026-00003', '2026-05-14 22:22:07'),
(68, 'fiche_inscription', 11, 'INS-2026-00003', '2026-05-14 22:22:19'),
(69, 'certificat_scolarite', 11, 'INS-2026-00003', '2026-05-14 22:22:24'),
(70, 'releve_notes', 11, 'INS-2026-00003', '2026-05-14 22:22:35'),
(71, 'attestation_reussite', 11, 'INS-2026-00003', '2026-05-14 22:22:41'),
(72, 'recu_paiement', 8, 'INS-2026-00001-2026-05', '2026-05-14 22:22:50'),
(73, 'etat_mensualites', 8, 'INS-2026-00001', '2026-05-14 22:22:57'),
(74, 'attestation_reussite', 8, 'INS-2026-00001', '2026-05-14 22:26:12'),
(75, 'convention_stage', 8, 'ST-4', '2026-05-14 22:27:58'),
(76, 'billet_excuse', 8, 'ABS-4', '2026-05-14 22:28:16'),
(77, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 22:29:30'),
(78, 'fiche_inscription', 8, 'INS-2026-00001', '2026-05-14 22:37:16'),
(79, 'certificat_scolarite', 8, 'INS-2026-00001', '2026-05-14 22:37:33'),
(80, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 22:37:47'),
(81, 'attestation_reussite', 8, 'INS-2026-00001', '2026-05-14 22:38:08'),
(82, 'etat_mensualites', 8, 'INS-2026-00001', '2026-05-14 22:38:16'),
(83, 'recu_paiement', 8, 'INS-2026-00001-2026-05', '2026-05-14 22:38:21'),
(84, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:34:15'),
(85, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:34:29'),
(86, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:34:33'),
(87, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:34:37'),
(88, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:36:03'),
(89, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:36:14'),
(90, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:36:48'),
(91, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:37:08'),
(92, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:37:12'),
(93, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:37:17'),
(94, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:37:23'),
(95, 'releve_notes', 8, 'INS-2026-00001', '2026-05-14 23:37:30'),
(96, 'billet_excuse', 8, 'ABS-4', '2026-05-14 23:37:40'),
(97, 'convention_stage', 8, 'ST-4', '2026-05-14 23:37:47');

-- --------------------------------------------------------

--
-- Table structure for table `filieres`
--

CREATE TABLE `filieres` (
  `id_filiere` int(10) UNSIGNED NOT NULL,
  `nom_filiere` varchar(255) NOT NULL,
  `niveau` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `filieres`
--

INSERT INTO `filieres` (`id_filiere`, `nom_filiere`, `niveau`) VALUES
(2, 'Technicien Spécialisé en Développement Informatique', NULL),
(3, 'Technicien en Informatique de Gestion', NULL),
(4, 'Technicien Spécialisé en Gestion des Entreprises', NULL),
(5, 'Opérateur Administratif', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mensualites`
--

CREATE TABLE `mensualites` (
  `id_mensualite` int(10) UNSIGNED NOT NULL,
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `mois_ref` char(7) NOT NULL COMMENT 'YYYY-MM',
  `est_paye` tinyint(1) NOT NULL DEFAULT 0,
  `marque_le` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mensualites`
--

INSERT INTO `mensualites` (`id_mensualite`, `id_stagiaire`, `mois_ref`, `est_paye`, `marque_le`) VALUES
(20, 8, '2026-05', 1, '2026-05-14 20:57:02'),
(21, 10, '2026-05', 1, '2026-05-14 20:57:02'),
(28, 11, '2026-05', 1, '2026-05-14 21:16:18');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id_module` int(10) UNSIGNED NOT NULL,
  `nom_module` varchar(255) NOT NULL,
  `masse_horaire` smallint(5) UNSIGNED DEFAULT NULL,
  `semestre` tinyint(3) UNSIGNED DEFAULT NULL,
  `id_filiere` int(10) UNSIGNED NOT NULL,
  `coefficient` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id_module`, `nom_module`, `masse_horaire`, `semestre`, `id_filiere`, `coefficient`) VALUES
(1, 'Metier et Formation', 30, 1, 2, 1),
(2, 'Algorithmique et Programmation', 120, 1, 2, 5),
(3, 'Bases de donn?es', 90, 2, 2, 1),
(4, 'Developpement Web', 100, 2, 2, 1),
(19, 'Métier et formation', NULL, NULL, 2, 1),
(20, 'L’entreprise et son environnement', NULL, NULL, 2, 1),
(21, 'Notion de mathématique appliquée', NULL, NULL, 2, 1),
(22, 'Gestion du temps', NULL, NULL, 2, 1),
(23, 'Veille technologique', NULL, NULL, 2, 1),
(24, 'Logiciel d’application', NULL, NULL, 2, 1),
(25, 'Programmation événementielle', NULL, NULL, 2, 5),
(26, 'Technique de programmation structurée', NULL, NULL, 2, 5),
(27, 'Langage de programmation structurée', NULL, NULL, 2, 5),
(28, 'Programmation orienté objet', NULL, NULL, 2, 5),
(29, 'Concept et mod d’un system d’information', NULL, NULL, 2, 1),
(30, 'Installation d’un poste informatique', NULL, NULL, 2, 1),
(31, 'Communication en Anglais', NULL, NULL, 2, 1),
(32, 'Assistant technique à la clientèle', NULL, NULL, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `module_notes`
--

CREATE TABLE `module_notes` (
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `id_module` int(10) UNSIGNED NOT NULL,
  `note_controle` decimal(5,2) DEFAULT NULL,
  `note_theorique` decimal(5,2) DEFAULT NULL,
  `note_pratique` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `module_notes`
--

INSERT INTO `module_notes` (`id_stagiaire`, `id_module`, `note_controle`, `note_theorique`, `note_pratique`) VALUES
(8, 2, 18.00, 15.00, 13.00),
(8, 3, 18.00, NULL, NULL),
(8, 4, 16.00, NULL, NULL),
(8, 22, 15.00, NULL, NULL),
(8, 29, 15.50, NULL, NULL),
(8, 31, 19.00, NULL, NULL),
(8, 32, 15.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `seq_inscription`
--

CREATE TABLE `seq_inscription` (
  `annee` int(11) NOT NULL,
  `last_num` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seq_inscription`
--

INSERT INTO `seq_inscription` (`annee`, `last_num`) VALUES
(2025, 2),
(2026, 1);

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `id_stage` int(10) UNSIGNED NOT NULL,
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
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stages`
--

INSERT INTO `stages` (`id_stage`, `type_stage`, `sujet`, `entreprise`, `date_debut`, `date_fin`, `note_stage`, `convention_url`, `rapport_url`, `evaluation_entreprise`, `date_soutenance`, `jury`, `id_stagiaire`, `created_at`) VALUES
(4, 'stage_entreprise', 'creation d\'une base de donnees', 'IPIRNET', '2025-04-01', '2025-04-15', 16.00, NULL, NULL, 'Très bonne ponctualité', '2025-07-03', 'Mr abdoussi', 8, '2026-05-14 21:27:16');

-- --------------------------------------------------------

--
-- Table structure for table `stagiaires`
--

CREATE TABLE `stagiaires` (
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `matricule` varchar(32) NOT NULL,
  `cin` varchar(32) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(32) DEFAULT NULL,
  `telephone_parent` varchar(32) DEFAULT NULL COMMENT 'CDC fiche inscription',
  `nom_tuteur` varchar(255) DEFAULT NULL COMMENT 'P?re ou tuteur',
  `mot_de_passe` varchar(255) NOT NULL,
  `photo` varchar(512) DEFAULT NULL,
  `date_inscription` date NOT NULL,
  `id_classe` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stagiaires`
--

INSERT INTO `stagiaires` (`id_stagiaire`, `matricule`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `mot_de_passe`, `photo`, `date_inscription`, `id_classe`, `created_at`, `updated_at`) VALUES
(8, 'INS-2026-00001', 'wa121518', 'el mehdi', 'bergam', '2005-02-14', '122 RUE PALESTINE', 'mehdibergame@gmail.com', '0650757944', '0682427801', 'amina', '$2y$10$HuhG7uL60Wba9/mLIWhs6eAbaUMxlnLu6l9CFtI2QIS7urLRFwaPi', NULL, '2026-05-14', 113, '2026-05-14 19:33:49', NULL),
(10, 'INS-2026-00002', 'wa123456', 'amina', 'qebaj', '2002-02-24', 'sqdqs', 'qsdqsdq@gmail.com', '0650757944', '0650757944', 'qsdqs', '$2y$10$R6APVYmJ7d902d0Nx5rfduJ7qtULnukw3A9q/rpPd19e4H2bqQfVW', NULL, '2026-05-14', 119, '2026-05-14 19:41:43', '2026-05-14 20:56:56'),
(11, 'INS-2026-00003', 'wa123456', 'khawla', 'bergam', '2005-02-22', '1 RUE PALESTINE tissir 2', 'mehdibergame7@gmail.com', '0650757944', '0650757944', 'Amina yassin', '$2y$10$qZKg00wJU6kAhefutgrNQOUSJeNZbpJcZ1fV36UnlEk8/p8ca2nFC', NULL, '2026-05-14', 5, '2026-05-14 21:12:44', NULL);

--
-- Triggers `stagiaires`
--
DELIMITER $$
CREATE TRIGGER `tr_stagiaires_bi_matricule` BEFORE INSERT ON `stagiaires` FOR EACH ROW BEGIN
  DECLARE y INT;
  DECLARE n INT;
  IF NEW.matricule IS NULL OR TRIM(NEW.matricule) = '' THEN
    SET y = YEAR(COALESCE(NEW.date_inscription, CURDATE()));
    IF NOT EXISTS (SELECT 1 FROM seq_inscription WHERE annee = y) THEN
      INSERT INTO seq_inscription (annee, last_num) VALUES (y, 0);
    END IF;
    UPDATE seq_inscription SET last_num = last_num + 1 WHERE annee = y;
    SELECT last_num INTO n FROM seq_inscription WHERE annee = y;
    SET NEW.matricule = CONCAT('INS-', y, '-', LPAD(n, 5, '0'));
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_moyennes_par_module`
-- (See below for the actual view)
--
CREATE TABLE `v_moyennes_par_module` (
`id_stagiaire` int(10) unsigned
,`id_module` int(10) unsigned
,`nom_module` varchar(255)
,`coefficient` int(11)
,`note_controle` decimal(5,2)
,`note_theorique` decimal(5,2)
,`note_pratique` decimal(5,2)
,`note_examen` decimal(10,6)
,`moyenne_module` decimal(15,10)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stagiaires_detail`
-- (See below for the actual view)
--
CREATE TABLE `v_stagiaires_detail` (
`id_stagiaire` int(10) unsigned
,`matricule` varchar(32)
,`cin` varchar(32)
,`nom` varchar(100)
,`prenom` varchar(100)
,`email` varchar(255)
,`telephone` varchar(32)
,`telephone_parent` varchar(32)
,`nom_tuteur` varchar(255)
,`date_inscription` date
,`id_classe` int(10) unsigned
,`nom_classe` varchar(128)
,`annee_scolaire` varchar(16)
,`id_filiere` int(10) unsigned
,`nom_filiere` varchar(255)
,`niveau_filiere` varchar(128)
);

-- --------------------------------------------------------

--
-- Structure for view `v_moyennes_par_module`
--
DROP TABLE IF EXISTS `v_moyennes_par_module`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_moyennes_par_module`  AS SELECT `mn`.`id_stagiaire` AS `id_stagiaire`, `mn`.`id_module` AS `id_module`, `m`.`nom_module` AS `nom_module`, `m`.`coefficient` AS `coefficient`, `mn`.`note_controle` AS `note_controle`, `mn`.`note_theorique` AS `note_theorique`, `mn`.`note_pratique` AS `note_pratique`, if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,if(`mn`.`note_pratique` is not null,`mn`.`note_pratique`,NULL))) AS `note_examen`, if(`mn`.`note_controle` is not null,if(`mn`.`note_theorique` is not null or `mn`.`note_pratique` is not null,(`mn`.`note_controle` + if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,`mn`.`note_pratique`))) / 2,`mn`.`note_controle`),if(`mn`.`note_theorique` is not null or `mn`.`note_pratique` is not null,if(`mn`.`note_theorique` is not null and `mn`.`note_pratique` is not null,(`mn`.`note_theorique` + `mn`.`note_pratique`) / 2,if(`mn`.`note_theorique` is not null,`mn`.`note_theorique`,`mn`.`note_pratique`)),NULL)) AS `moyenne_module` FROM (`module_notes` `mn` join `modules` `m` on(`mn`.`id_module` = `m`.`id_module`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_stagiaires_detail`
--
DROP TABLE IF EXISTS `v_stagiaires_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stagiaires_detail`  AS SELECT `s`.`id_stagiaire` AS `id_stagiaire`, `s`.`matricule` AS `matricule`, `s`.`cin` AS `cin`, `s`.`nom` AS `nom`, `s`.`prenom` AS `prenom`, `s`.`email` AS `email`, `s`.`telephone` AS `telephone`, `s`.`telephone_parent` AS `telephone_parent`, `s`.`nom_tuteur` AS `nom_tuteur`, `s`.`date_inscription` AS `date_inscription`, `c`.`id_classe` AS `id_classe`, `c`.`nom_classe` AS `nom_classe`, `c`.`annee_scolaire` AS `annee_scolaire`, `f`.`id_filiere` AS `id_filiere`, `f`.`nom_filiere` AS `nom_filiere`, `f`.`niveau` AS `niveau_filiere` FROM ((`stagiaires` `s` join `classes` `c` on(`c`.`id_classe` = `s`.`id_classe`)) join `filieres` `f` on(`f`.`id_filiere` = `c`.`id_filiere`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id_absence`),
  ADD KEY `fk_absences_stagiaire` (`id_stagiaire`),
  ADD KEY `fk_absences_module` (`id_module`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id_classe`),
  ADD KEY `idx_classes_filiere_annee` (`id_filiere`,`annee_scolaire`);

--
-- Indexes for table `demandes_inscription`
--
ALTER TABLE `demandes_inscription`
  ADD PRIMARY KEY (`id_demande`),
  ADD KEY `fk_demande_inscription_classe` (`id_classe`),
  ADD KEY `fk_demande_inscription_stag` (`id_stagiaire_cree`),
  ADD KEY `idx_demandes_statut` (`statut`,`date_soumission`);

--
-- Indexes for table `documents_generes`
--
ALTER TABLE `documents_generes`
  ADD PRIMARY KEY (`id_gen`),
  ADD KEY `fk_docgen_stagiaire` (`id_stagiaire`);

--
-- Indexes for table `filieres`
--
ALTER TABLE `filieres`
  ADD PRIMARY KEY (`id_filiere`);

--
-- Indexes for table `mensualites`
--
ALTER TABLE `mensualites`
  ADD PRIMARY KEY (`id_mensualite`),
  ADD UNIQUE KEY `uk_mensualite_stag_mois` (`id_stagiaire`,`mois_ref`),
  ADD KEY `idx_mensualites_mois` (`mois_ref`,`est_paye`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id_module`),
  ADD KEY `idx_modules_filiere` (`id_filiere`);

--
-- Indexes for table `module_notes`
--
ALTER TABLE `module_notes`
  ADD PRIMARY KEY (`id_stagiaire`,`id_module`),
  ADD KEY `id_module` (`id_module`);

--
-- Indexes for table `seq_inscription`
--
ALTER TABLE `seq_inscription`
  ADD PRIMARY KEY (`annee`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id_stage`),
  ADD KEY `fk_stages_stagiaire` (`id_stagiaire`);

--
-- Indexes for table `stagiaires`
--
ALTER TABLE `stagiaires`
  ADD PRIMARY KEY (`id_stagiaire`),
  ADD UNIQUE KEY `uk_stagiaires_matricule` (`matricule`),
  ADD UNIQUE KEY `uk_stagiaires_email` (`email`),
  ADD KEY `idx_stagiaires_classe` (`id_classe`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absences`
--
ALTER TABLE `absences`
  MODIFY `id_absence` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id_classe` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `demandes_inscription`
--
ALTER TABLE `demandes_inscription`
  MODIFY `id_demande` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `documents_generes`
--
ALTER TABLE `documents_generes`
  MODIFY `id_gen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `filieres`
--
ALTER TABLE `filieres`
  MODIFY `id_filiere` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `mensualites`
--
ALTER TABLE `mensualites`
  MODIFY `id_mensualite` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id_module` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id_stage` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stagiaires`
--
ALTER TABLE `stagiaires`
  MODIFY `id_stagiaire` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `fk_absences_module` FOREIGN KEY (`id_module`) REFERENCES `modules` (`id_module`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absences_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filieres` (`id_filiere`) ON UPDATE CASCADE;

--
-- Constraints for table `demandes_inscription`
--
ALTER TABLE `demandes_inscription`
  ADD CONSTRAINT `fk_demande_inscription_classe` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_inscription_stag` FOREIGN KEY (`id_stagiaire_cree`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `documents_generes`
--
ALTER TABLE `documents_generes`
  ADD CONSTRAINT `fk_docgen_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mensualites`
--
ALTER TABLE `mensualites`
  ADD CONSTRAINT `fk_mensualites_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `fk_modules_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filieres` (`id_filiere`) ON UPDATE CASCADE;

--
-- Constraints for table `module_notes`
--
ALTER TABLE `module_notes`
  ADD CONSTRAINT `module_notes_ibfk_1` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_notes_ibfk_2` FOREIGN KEY (`id_module`) REFERENCES `modules` (`id_module`) ON DELETE CASCADE;

--
-- Constraints for table `stages`
--
ALTER TABLE `stages`
  ADD CONSTRAINT `fk_stages_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stagiaires`
--
ALTER TABLE `stagiaires`
  ADD CONSTRAINT `fk_stagiaires_classe` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
