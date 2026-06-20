-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2026 at 09:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS=0;


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
(30, '2026-06-13', '09:00:00', '10:30:00', 'Certificat médical', 1, 68, 2, '2026-06-13 08:56:11'),
(31, '2026-06-13', '14:30:00', '16:50:00', NULL, 0, 71, 32, '2026-06-13 13:53:26');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id_classe` int(10) UNSIGNED NOT NULL,
  `nom_classe` varchar(128) NOT NULL,
  `annee_scolaire` varchar(16) NOT NULL,
  `niveau` varchar(32) NOT NULL DEFAULT '',
  `id_filiere` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id_classe`, `nom_classe`, `annee_scolaire`, `niveau`, `id_filiere`) VALUES
(1, '1A TSDI', '2025/2026', '1ère année', 2),
(2, '2A TSDI', '2025/2026', '2ème année', 2),
(3, '1A TGI', '2025/2026', '1ère année', 3),
(4, '2A TGI', '2025/2026', '2ème année', 3),
(5, '1A TSGE', '2025/2026', '1ère année', 4),
(6, '2A TSGE', '2025/2026', '2ème année', 4),
(9, '1A TSDI', '2024/2025', '1ère année', 2),
(10, '2A TSDI', '2024/2025', '2ème année', 2),
(11, '1A TGI', '2024/2025', '1ère année', 3),
(12, '2A TGI', '2024/2025', '2ème année', 3),
(13, '1A TSGE', '2024/2025', '1ère année', 4),
(14, '2A TSGE', '2024/2025', '2ème année', 4);

-- --------------------------------------------------------

--
-- Table structure for table `documents_generes`
--

CREATE TABLE `documents_generes` (
  `id_gen` int(10) UNSIGNED NOT NULL,
  `type_document` enum('certificat_scolarite','billet_excuse','etat_mensualites','releve_notes','bulletin','attestation_reussite','convention_stage','fiche_inscription','recu_paiement','fiche_preinscription','liste_stagiaires','etat_paiement','rapport_individuel','autre') NOT NULL DEFAULT 'autre',
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `genere_le` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents_generes`
--

INSERT INTO `documents_generes` (`id_gen`, `type_document`, `id_stagiaire`, `reference`, `genere_le`) VALUES
(279, 'certificat_scolarite', 68, 'INS-2026-00001', '2026-06-13 10:15:10'),
(280, 'recu_paiement', 69, 'INS-2026-00002-2026-06', '2026-06-13 10:45:41'),
(281, 'releve_notes', 69, 'INS-2026-00002', '2026-06-13 13:00:12'),
(282, 'releve_notes', 69, 'INS-2026-00002', '2026-06-13 13:00:14'),
(283, 'releve_notes', 69, 'INS-2026-00002', '2026-06-13 13:00:16'),
(284, 'autre', 68, 'INS-2026-00001', '2026-06-13 14:50:55'),
(285, 'autre', 68, 'INS-2026-00001', '2026-06-13 14:52:46'),
(286, 'autre', 71, 'INS-2026-00004', '2026-06-13 14:53:55'),
(287, 'autre', 69, 'INS-2026-00002', '2026-06-13 14:54:04'),
(288, 'releve_notes', 68, 'INS-2026-00001', '2026-06-13 14:55:32'),
(289, 'autre', 68, 'INS-2026-00001', '2026-06-13 15:06:16');

-- --------------------------------------------------------

--
-- Table structure for table `filieres`
--

CREATE TABLE `filieres` (
  `id_filiere` int(10) UNSIGNED NOT NULL,
  `nom_filiere` varchar(255) NOT NULL,
  `niveau` varchar(128) DEFAULT NULL,
  `capacite` int(11) NOT NULL DEFAULT 30 COMMENT 'Capacité maximale d''accueil de la filière'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `filieres`
--

INSERT INTO `filieres` (`id_filiere`, `nom_filiere`, `niveau`, `capacite`) VALUES
(2, 'TSDI', NULL, 30),
(3, 'TGI', NULL, 30),
(4, 'TSGE', NULL, 30);

-- --------------------------------------------------------

--
-- Table structure for table `mensualites`
--

CREATE TABLE `mensualites` (
  `id_mensualite` int(10) UNSIGNED NOT NULL,
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `mois_ref` char(7) NOT NULL COMMENT 'YYYY-MM',
  `est_paye` tinyint(1) NOT NULL DEFAULT 0,
  `montant_total` decimal(10,2) DEFAULT NULL COMMENT 'Montant total de la mensualité',
  `montant_paye` decimal(10,2) DEFAULT NULL COMMENT 'Montant déjà payé',
  `montant_restant` decimal(10,2) DEFAULT NULL COMMENT 'Montant restant à payer',
  `cumul_restant` decimal(10,2) DEFAULT NULL COMMENT 'Cumul des impayés',
  `statut_paiement` varchar(32) DEFAULT NULL COMMENT 'payé / partiel / impayé',
  `date_paiement` datetime DEFAULT NULL COMMENT 'Date du paiement effectif',
  `marque_le` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mensualites`
--

INSERT INTO `mensualites` (`id_mensualite`, `id_stagiaire`, `mois_ref`, `est_paye`, `montant_total`, `montant_paye`, `montant_restant`, `cumul_restant`, `statut_paiement`, `date_paiement`, `marque_le`) VALUES
(103, 68, '2026-06', 1, 700.00, 700.00, 0.00, 0.00, 'payé', '2026-06-13 00:00:00', '2026-06-13 11:30:39'),
(104, 71, '2026-06', 1, 700.00, 700.00, 0.00, 0.00, 'payé', '2026-06-13 00:00:00', '2026-06-13 11:30:39');

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
  `coefficient` int(11) DEFAULT 1,
  `nb_controles` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nombre de contrôles continus pour ce module'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id_module`, `nom_module`, `masse_horaire`, `semestre`, `id_filiere`, `coefficient`, `nb_controles`) VALUES
(2, 'Algorithmique et Programmation', 120, 1, 2, 5, 1),
(3, 'Bases de données', 90, 2, 2, 1, 1),
(4, 'Développement Web', 100, 2, 2, 1, 1),
(19, 'Métier et Formation', NULL, NULL, 2, 1, 1),
(20, 'L\'entreprise et son environnement', NULL, NULL, 2, 1, 1),
(21, 'Notion de mathématique appliquée', NULL, NULL, 2, 1, 1),
(22, 'Gestion du temps', NULL, NULL, 2, 1, 1),
(23, 'Veille technologique', NULL, NULL, 2, 1, 1),
(24, 'Logiciel d\'application', NULL, NULL, 2, 1, 1),
(25, 'Programmation événementielle', NULL, NULL, 2, 5, 1),
(26, 'Technique de programmation structurée', NULL, NULL, 2, 5, 1),
(27, 'Langage de programmation structurée', NULL, NULL, 2, 5, 1),
(28, 'Programmation orientée objet', NULL, NULL, 2, 5, 1),
(29, 'Concept et modélisation d\'un système d\'information', NULL, NULL, 2, 1, 1),
(30, 'Installation d\'un poste informatique', NULL, NULL, 2, 1, 1),
(31, 'Communication en Anglais', NULL, NULL, 2, 1, 1),
(32, 'Assistant technique à la clientèle', NULL, NULL, 2, 1, 1),
(33, 'Comptabilité générale', NULL, NULL, 4, 1, 1),
(34, 'Concept de base', NULL, NULL, 4, 1, 1),
(35, 'Traitement de salaire', NULL, NULL, 4, 1, 1),
(36, 'Charge de personnel', NULL, NULL, 4, 1, 1),
(37, 'Marketing', NULL, NULL, 4, 1, 1),
(38, 'Entreprise', NULL, NULL, 4, 1, 1),
(39, 'Statistique', NULL, NULL, 4, 1, 1),
(40, 'Algorithm', NULL, NULL, 3, 1, 1),
(41, 'Installation d\'un poste', NULL, NULL, 3, 1, 1),
(42, 'Bureautique', NULL, NULL, 3, 1, 1),
(43, 'Comptabilité générale', NULL, NULL, 3, 1, 1),
(44, 'Statistique', NULL, NULL, 3, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `module_notes`
-- Stocke les évaluations individuelles (controle_1..N, theorique, pratique)
-- Structure dynamique : une ligne par (stagiaire, module, type d'évaluation)
--

CREATE TABLE `module_notes` (
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `id_module` int(10) UNSIGNED NOT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  `type` varchar(32) NOT NULL COMMENT 'controle_1, controle_2, ..., theorique, pratique',
  PRIMARY KEY (`id_stagiaire`,`id_module`,`type`),
  KEY `idx_module_notes_module` (`id_module`),
  CONSTRAINT `chk_module_notes_note` CHECK (`note` IS NULL OR (`note` >= 0 AND `note` <= 20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `module_notes`
--

INSERT INTO `module_notes` (`id_stagiaire`, `id_module`, `note`, `type`) VALUES
(68, 2, 10.00, 'controle_1'),
(68, 2, 12.00, 'theorique'),
(68, 2, 16.00, 'pratique'),
(71, 2, 12.00, 'controle_1'),
(71, 2, 15.00, 'theorique'),
(71, 2, 18.00, 'pratique');

-- --------------------------------------------------------

--
-- Table structure for table `pre_inscription`
--

CREATE TABLE `pre_inscription` (
  `id_demande` int(10) UNSIGNED NOT NULL,
  `cin` varchar(8) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `telephone_parent` varchar(15) DEFAULT NULL,
  `nom_tuteur` varchar(255) DEFAULT NULL,
  `id_filiere` int(10) UNSIGNED NOT NULL,
  `annee_scolaire_visee` varchar(9) DEFAULT NULL,
  `statut` enum('en_attente','converti','abandonne') NOT NULL DEFAULT 'en_attente',
  `date_soumission` datetime NOT NULL DEFAULT current_timestamp(),
  `date_decision` datetime DEFAULT NULL,
  `id_stagiaire_cree` int(10) UNSIGNED DEFAULT NULL,
  `sexe` varchar(1) DEFAULT NULL COMMENT 'M ou F',
  `niveaux` varchar(512) DEFAULT NULL COMMENT 'JSON array des niveaux cochés',
  `diplomes` varchar(512) DEFAULT NULL COMMENT 'JSON array des filières/diplômes cochés (id_filiere)',
  `formations` varchar(512) DEFAULT NULL COMMENT 'JSON array formations continues cochées',
  `autre_formation` varchar(255) DEFAULT NULL,
  `sources` varchar(512) DEFAULT NULL COMMENT 'JSON array comment connu',
  `source_autre` varchar(255) DEFAULT NULL,
  `licences` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pre_inscription`
--

INSERT INTO `pre_inscription` (`id_demande`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `id_filiere`, `annee_scolaire_visee`, `statut`, `date_soumission`, `date_decision`, `id_stagiaire_cree`, `sexe`, `niveaux`, `diplomes`, `formations`, `autre_formation`, `sources`, `source_autre`, `licences`) VALUES
(33, 'AA123451', 'Test', 'Alpha', '2001-12-12', 'testsstestss', 'alpha@test.com', '0612345678', NULL, 'test', 2, '2025/2026', 'converti', '2026-06-13 09:23:47', '2026-06-13 09:39:51', 68, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Programmation\",\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', 'test', '[\"Publicit\\u00e9\",\"Relation\"]', 'test', '[\"Management et Ressource Humaine\",\"Finance et Comptabilit\\u00e9\",\"Logistique Internationale\",\"Informatique\"]'),
(34, 'CD678901', 'Deux', 'Bêta', '2000-12-12', 'DeuxDeux', 'beta2@test.com', '0698765432', '0698765432', 'Deux', 2, '2026/2027', 'converti', '2026-06-13 09:40:33', '2026-06-13 09:40:38', 69, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"R\\u00e9seau\"]', NULL, '[\"Publicit\\u00e9\"]', NULL, '[\"Logistique Internationale\"]'),
(35, 'SV121122', 'tres', 'tres', '2003-12-12', 'trestres', 'tres@gmail.com', '0650757944', '0650757944', 'tres', 2, '2024/2025', 'converti', '2026-06-13 09:41:29', '2026-06-13 09:41:56', 70, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', NULL, '[\"Relation\"]', NULL, '[\"Finance et Comptabilit\\u00e9\"]'),
(36, 'WA000000', 'test', 'test', '2001-12-12', 'testtest', 'testtest@gmail.com', '0650757944', '0682427801', 'test', 2, '2025/2026', 'en_attente', '2026-06-13 10:34:41', NULL, NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Comptabilit\\u00e9\"]', NULL, '[\"Relation\"]', NULL, '[\"Logistique Internationale\",\"Informatique\"]'),
(37, 'WA000031', 'testt', 'testt', '2001-12-12', 'testttestt', 'testttestt@gmail.com', '0650757944', '0682427801', 'testt', 3, '2025/2026', 'en_attente', '2026-06-13 10:38:20', NULL, NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Comptabilit\\u00e9\"]', NULL, NULL, NULL, '[\"Management et Ressource Humaine\"]'),
(38, 'WA122466', 'testtt', 'testtt', '2001-12-12', 'testtttesttt', 'testtttesttt@gmail.com', '0650757944', '0682427801', 'testtt', 4, '2025/2026', 'en_attente', '2026-06-13 10:39:00', NULL, NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', NULL, NULL, NULL, NULL, '[\"Finance et Comptabilit\\u00e9\",\"Informatique\"]');

-- --------------------------------------------------------

--
-- Table structure for table `seq_inscription`
--

CREATE TABLE `seq_inscription` (
  `annee` int(11) NOT NULL,
  `last_num` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `stagiaires`
--

CREATE TABLE `stagiaires` (
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `num_inscri` varchar(32) NOT NULL,
  `cin` varchar(8) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` varchar(512) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(15) DEFAULT NULL,
  `telephone_parent` varchar(15) DEFAULT NULL COMMENT 'CDC fiche inscription',
  `nom_tuteur` varchar(255) DEFAULT NULL COMMENT 'Père ou tuteur',
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

INSERT INTO `stagiaires` (`id_stagiaire`, `num_inscri`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `mot_de_passe`, `photo`, `date_inscription`, `id_classe`, `created_at`, `updated_at`) VALUES
(68, 'INS-2026-00001', 'AA123451', 'Alpha', 'Modifié', '2001-12-12', 'testsstestss', 'alpha@test.com', '0612345678', NULL, 'test', '$2y$10$.ewcADq7Abu823649RzTruWlg7wRQQg9VbioVuYIY4x1vQqhwvCOO', NULL, '2026-06-13', 1, '2026-06-13 08:39:51', '2026-06-13 10:22:13'),
(69, 'INS-2026-00002', 'CD678901', 'Deux', 'Bêta', '2000-12-12', 'DeuxDeux', 'beta2@test.com', '0698765432', '0698765432', 'Deux', '$2y$10$w0baTALNvS1vnniO1QDRpOIqztZKgzUV8rMBR7XL1piEta5C1JS0O', NULL, '2026-06-13', 16, '2026-06-13 08:40:38', '2026-06-13 11:56:55'),
(70, 'INS-2026-00003', 'SV121122', 'tres', 'tres', '2003-12-12', 'trestres', 'tres@gmail.com', '0650757944', '0650757944', 'tres', '$2y$10$u4PYAv2HlyHqMJJwYnHRa.s024FU.VI.zGeBHbPPXrXOwboBp.7Jq', NULL, '2026-06-13', 9, '2026-06-13 08:41:56', NULL),
(71, 'INS-2026-00004', 'BB678901', 'Beta', 'Charlie', '2003-11-11', '12 rue des Tests, Casablanca', 'charlie@test.com', '0611223344', '0611223344', 'test', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2026-06-13', 1, '2026-06-13 08:49:12', NULL);

--
-- Triggers `stagiaires`
--
DELIMITER $$
CREATE TRIGGER `tr_stagiaires_bi_num_inscri` BEFORE INSERT ON `stagiaires` FOR EACH ROW BEGIN
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

-- --------------------------------------------------------

--
-- Table structure for table `stagiaire_historique`
--

CREATE TABLE `stagiaire_historique` (
  `id` int(11) NOT NULL,
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `champ` varchar(60) NOT NULL,
  `ancien` varchar(255) DEFAULT NULL,
  `nouveau` varchar(255) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `change_le` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stagiaire_historique`
--

INSERT INTO `stagiaire_historique` (`id`, `id_stagiaire`, `champ`, `ancien`, `nouveau`, `note`, `change_le`) VALUES
(12, 68, 'classe', '1A TSDI', '2A TSDI', 'class switching', '2026-06-13 10:03:31'),
(13, 68, 'classe', '2A TSDI', '1A TSDI', 'aa', '2026-06-13 11:22:13'),
(14, 69, 'classe', '1A TSDI', '2A TSDI', 'aaa', '2026-06-13 12:56:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('directeur','secretaire') NOT NULL DEFAULT 'secretaire',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'secretaire1', '$2b$10$eWcKuuvv.3mqEwndErPuGuqMqjj3pfkOX0J1ZTdq9g2Y/iRF2o4dG', 'secretaire', '2026-06-12 19:43:34');

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
,`moyenne_module` decimal(9,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stagiaires_detail`
-- (See below for the actual view)
--
CREATE TABLE `v_stagiaires_detail` (
`id_stagiaire` int(10) unsigned
,`num_inscri` varchar(32)
,`cin` varchar(8)
,`nom` varchar(100)
,`prenom` varchar(100)
,`email` varchar(255)
,`telephone` varchar(15)
,`telephone_parent` varchar(15)
,`nom_tuteur` varchar(255)
,`date_inscription` date
,`date_naissance` date
,`id_classe` int(10) unsigned
,`nom_classe` varchar(128)
,`annee_scolaire` varchar(16)
,`niveau_classe` varchar(32)
,`id_filiere` int(10) unsigned
,`nom_filiere` varchar(255)
,`niveau_filiere` varchar(128)
);

-- --------------------------------------------------------

--
-- Structure for view `v_moyennes_par_module`
--
DROP TABLE IF EXISTS `v_moyennes_par_module`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_moyennes_par_module` AS
SELECT
  ev.`id_stagiaire`,
  ev.`id_module`,
  m.`nom_module`,
  m.`coefficient`,
  m.`nb_controles`,
  AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END) AS `note_controle`,
  MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) AS `note_theorique`,
  MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) AS `note_pratique`,
  CASE
    WHEN MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) IS NOT NULL
     AND MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) IS NOT NULL
    THEN (MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) + MAX(CASE WHEN ev.`type` = 'pratique' THEN ev.`note` END)) / 2
    WHEN MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) IS NOT NULL
    THEN MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END)
    WHEN MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) IS NOT NULL
    THEN MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END)
    ELSE NULL
  END AS `note_examen`,
  CASE
    WHEN AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END) IS NOT NULL
     AND MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) IS NOT NULL
     AND MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) IS NOT NULL
    THEN ROUND(
      AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END) * 0.40 +
      MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) * 0.30 +
      MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) * 0.30, 2)
    WHEN AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END) IS NOT NULL
     AND (MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END) IS NOT NULL
       OR MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END) IS NOT NULL)
    THEN ROUND(
      AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END) * 0.40 +
      COALESCE(MAX(CASE WHEN ev.`type` = 'theorique' THEN ev.`note` END), 0) * 0.30 +
      COALESCE(MAX(CASE WHEN ev.`type` = 'pratique'  THEN ev.`note` END), 0) * 0.30, 2)
    ELSE AVG(CASE WHEN ev.`type` LIKE 'controle%' THEN ev.`note` END)
  END AS `moyenne_module`
FROM `module_notes` ev
JOIN `modules` m ON m.`id_module` = ev.`id_module`
GROUP BY ev.`id_stagiaire`, ev.`id_module`, m.`nom_module`, m.`coefficient`, m.`nb_controles`;

-- --------------------------------------------------------

--
-- Structure for view `v_stagiaires_detail`
--
DROP TABLE IF EXISTS `v_stagiaires_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stagiaires_detail`  AS SELECT `s`.`id_stagiaire` AS `id_stagiaire`, `s`.`num_inscri` AS `num_inscri`, `s`.`cin` AS `cin`, `s`.`nom` AS `nom`, `s`.`prenom` AS `prenom`, `s`.`email` AS `email`, `s`.`telephone` AS `telephone`, `s`.`telephone_parent` AS `telephone_parent`, `s`.`nom_tuteur` AS `nom_tuteur`, `s`.`date_inscription` AS `date_inscription`, `s`.`date_naissance` AS `date_naissance`, `s`.`id_classe` AS `id_classe`, `c`.`nom_classe` AS `nom_classe`, `c`.`annee_scolaire` AS `annee_scolaire`, `c`.`niveau` AS `niveau_classe`, `f`.`id_filiere` AS `id_filiere`, `f`.`nom_filiere` AS `nom_filiere`, `f`.`niveau` AS `niveau_filiere` FROM ((`stagiaires` `s` join `classes` `c` on(`c`.`id_classe` = `s`.`id_classe`)) join `filieres` `f` on(`f`.`id_filiere` = `c`.`id_filiere`)) ;

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
-- Indexes for table `pre_inscription`
--
ALTER TABLE `pre_inscription`
  ADD PRIMARY KEY (`id_demande`),
  ADD KEY `fk_dem_filiere` (`id_filiere`),
  ADD KEY `fk_demande_inscription_stag` (`id_stagiaire_cree`),
  ADD KEY `idx_demandes_statut` (`statut`,`date_soumission`),
  ADD KEY `idx_demandes_cin` (`cin`);

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
  ADD UNIQUE KEY `uk_stagiaires_num_inscri` (`num_inscri`),
  ADD UNIQUE KEY `uk_stagiaires_email` (`email`),
  ADD UNIQUE KEY `uk_stagiaires_cin` (`cin`),
  ADD KEY `idx_stagiaires_classe` (`id_classe`);

--
-- Indexes for table `stagiaire_historique`
--
ALTER TABLE `stagiaire_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stagiaire` (`id_stagiaire`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absences`
--
ALTER TABLE `absences`
  MODIFY `id_absence` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id_classe` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `documents_generes`
--
ALTER TABLE `documents_generes`
  MODIFY `id_gen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=290;

--
-- AUTO_INCREMENT for table `filieres`
--
ALTER TABLE `filieres`
  MODIFY `id_filiere` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `mensualites`
--
ALTER TABLE `mensualites`
  MODIFY `id_mensualite` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id_module` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `pre_inscription`
--
ALTER TABLE `pre_inscription`
  MODIFY `id_demande` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id_stage` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stagiaires`
--
ALTER TABLE `stagiaires`
  MODIFY `id_stagiaire` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `stagiaire_historique`
--
ALTER TABLE `stagiaire_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  ADD CONSTRAINT `fk_module_notes_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_module_notes_module`    FOREIGN KEY (`id_module`)    REFERENCES `modules`    (`id_module`)    ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pre_inscription`
--
ALTER TABLE `pre_inscription`
  ADD CONSTRAINT `fk_dem_filiere` FOREIGN KEY (`id_filiere`) REFERENCES `filieres` (`id_filiere`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_inscription_stag` FOREIGN KEY (`id_stagiaire_cree`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE SET NULL ON UPDATE CASCADE;

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

--
-- Constraints for table `stagiaire_historique`
--
ALTER TABLE `stagiaire_historique`
  ADD CONSTRAINT `fk_hist_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
