-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 10:11 PM
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
(120, '2026-06-23', '14:30:00', '16:00:00', 'certif', 1, 139, 40, '2026-06-23 13:54:26'),
(121, '2026-06-23', '14:30:00', '16:00:00', 'certif', 1, 141, 40, '2026-06-23 13:54:26'),
(122, '2026-06-23', '14:30:00', '16:00:00', 'certif', 1, 142, 40, '2026-06-23 13:54:26'),
(123, '2026-06-23', '14:00:00', '16:00:00', 'certif', 1, 89, 36, '2026-06-23 14:31:35'),
(124, '2026-06-23', '14:00:00', '16:00:00', 'certif', 1, 88, 36, '2026-06-23 14:31:35'),
(125, '2026-06-23', '14:00:00', '16:00:00', 'certif', 1, 165, 36, '2026-06-23 14:31:35');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id_classe` int(10) UNSIGNED NOT NULL,
  `nom_classe` varchar(128) NOT NULL,
  `annee_scolaire` varchar(16) NOT NULL,
  `niveau` varchar(32) NOT NULL DEFAULT '',
  `id_filiere` int(10) UNSIGNED NOT NULL,
  `capacite` int(10) UNSIGNED NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id_classe`, `nom_classe`, `annee_scolaire`, `niveau`, `id_filiere`, `capacite`) VALUES
(1, '1A TSDI', '2025/2026', '1ère année', 2, 30),
(2, '2A TSDI', '2025/2026', '2ème année', 2, 30),
(3, '1A TGI', '2025/2026', '1ère année', 3, 30),
(4, '2A TGI', '2025/2026', '2ème année', 3, 30),
(5, '1A TSGE', '2025/2026', '1ère année', 4, 30),
(6, '2A TSGE', '2025/2026', '2ème année', 4, 30),
(9, '1A TSDI', '2024/2025', '1ère année', 2, 30),
(10, '2A TSDI', '2024/2025', '2ème année', 2, 30),
(11, '1A TGI', '2024/2025', '1ère année', 3, 30),
(12, '2A TGI', '2024/2025', '2ème année', 3, 30),
(13, '1A TSGE', '2024/2025', '1ère année', 4, 30),
(14, '2A TSGE', '2024/2025', '2ème année', 4, 30);

-- --------------------------------------------------------

--
-- Table structure for table `documents_generes`
--

CREATE TABLE `documents_generes` (
  `id_gen` int(10) UNSIGNED NOT NULL,
  `type_document` enum('certificat_scolarite','billet_excuse','etat_mensualites','releve_notes','bulletin','attestation_reussite','convention_stage','fiche_inscription','recu_paiement','fiche_preinscription','liste_stagiaires','etat_paiement','rapport_individuel','etat_paiements_annuel','autre') NOT NULL DEFAULT 'autre',
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `genere_le` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents_generes`
--

INSERT INTO `documents_generes` (`id_gen`, `type_document`, `id_stagiaire`, `reference`, `genere_le`) VALUES
(290, 'rapport_individuel', 111, 'INS-2024-00017', '2026-06-19 18:18:38'),
(291, 'fiche_inscription', 111, 'INS-2024-00017', '2026-06-19 18:18:54'),
(292, 'releve_notes', 111, 'INS-2024-00017', '2026-06-19 18:19:00'),
(293, 'releve_notes', 111, 'INS-2024-00017', '2026-06-20 16:07:19'),
(294, 'releve_notes', 111, 'INS-2024-00017', '2026-06-20 16:07:25'),
(295, 'releve_notes', 111, 'INS-2024-00017', '2026-06-20 16:07:32'),
(296, 'releve_notes', 105, 'INS-2024-00011', '2026-06-20 16:07:59'),
(297, 'releve_notes', 105, 'INS-2024-00011', '2026-06-20 16:09:56'),
(298, 'certificat_scolarite', 200, 'INS-2024-00046', '2026-06-20 16:34:40'),
(299, 'releve_notes', 105, 'INS-2024-00011', '2026-06-20 17:03:28'),
(300, 'certificat_scolarite', 105, 'INS-2024-00011', '2026-06-20 17:03:39'),
(301, 'rapport_individuel', 105, 'INS-2024-00011', '2026-06-20 17:03:48'),
(302, 'fiche_inscription', 105, 'INS-2024-00011', '2026-06-20 18:11:22'),
(303, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:31:40'),
(304, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:13'),
(305, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:13'),
(306, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:14'),
(307, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:24'),
(308, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:32'),
(309, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:33:35'),
(310, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:48:32'),
(311, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:48:42'),
(312, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:48:46'),
(313, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:55:26'),
(314, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:57:10'),
(315, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:57:35'),
(316, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:57:39'),
(317, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:57:46'),
(318, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:57:58'),
(319, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 20:58:42'),
(320, 'releve_notes', 81, 'INS-2025-00010', '2026-06-20 21:03:22'),
(321, 'recu_paiement', 81, 'INS-2025-00010-2026-06', '2026-06-20 21:29:22'),
(322, 'recu_paiement', 81, 'INS-2025-00010-2025-09', '2026-06-20 21:29:38'),
(323, 'recu_paiement', 81, 'INS-2025-00010-2026-06', '2026-06-20 22:15:05'),
(324, 'recu_paiement', 81, 'INS-2025-00010-2026-06', '2026-06-20 22:18:37'),
(325, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-20 22:35:35'),
(326, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 09:22:08'),
(327, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 09:26:53'),
(328, 'etat_paiements_annuel', 147, 'INS-2025-00053', '2026-06-21 09:28:09'),
(329, 'rapport_individuel', 147, 'INS-2025-00053', '2026-06-21 09:29:08'),
(330, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 09:29:44'),
(331, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 09:30:58'),
(332, 'convention_stage', 84, 'ST-14', '2026-06-21 09:36:57'),
(333, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 09:40:23'),
(334, 'etat_paiements_annuel', 73, 'INS-2025-00002', '2026-06-21 14:22:44'),
(335, 'recu_paiement', 73, 'INS-2025-00002-2026-06', '2026-06-21 14:23:29'),
(336, 'etat_paiements_annuel', 73, 'INS-2025-00002', '2026-06-21 14:35:46'),
(337, 'billet_excuse', 81, 'ABS-42', '2026-06-21 15:27:07'),
(338, 'billet_excuse', 81, 'ABS-115', '2026-06-21 16:52:57'),
(339, 'billet_excuse', 81, 'STAG-81', '2026-06-21 17:27:37'),
(340, 'billet_excuse', 147, 'STAG-147', '2026-06-21 17:27:56'),
(341, 'billet_excuse', 81, 'STAG-81', '2026-06-21 17:28:13'),
(342, 'billet_excuse', 147, 'STAG-147', '2026-06-21 17:30:33'),
(343, 'billet_excuse', 80, 'ABS-MODAL-118', '2026-06-21 17:43:38'),
(344, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 19:36:08'),
(345, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 19:36:11'),
(346, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 19:36:14'),
(347, 'etat_paiements_annuel', 82, 'INS-2025-00011', '2026-06-21 19:48:41'),
(348, 'convention_stage', 81, 'ST-78', '2026-06-21 19:58:08'),
(349, 'releve_notes', 73, 'INS-2025-00002', '2026-06-21 20:09:21'),
(350, 'releve_notes', 73, 'INS-2025-00002', '2026-06-21 20:09:22'),
(351, 'releve_notes', 73, 'INS-2025-00002', '2026-06-21 20:09:24'),
(352, 'fiche_inscription', 81, 'INS-2025-00010', '2026-06-21 21:15:02'),
(353, 'certificat_scolarite', 81, 'INS-2025-00010', '2026-06-21 21:15:06'),
(354, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 21:15:09'),
(355, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 21:15:11'),
(356, 'releve_notes', 81, 'INS-2025-00010', '2026-06-21 21:15:14'),
(357, 'attestation_reussite', 81, 'INS-2025-00010', '2026-06-21 21:15:17'),
(358, 'recu_paiement', 81, 'INS-2025-00010-2026-06', '2026-06-21 21:15:19'),
(359, 'etat_mensualites', 81, 'INS-2025-00010', '2026-06-21 21:15:23'),
(360, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-21 21:15:30'),
(361, 'rapport_individuel', 81, 'INS-2025-00010', '2026-06-21 21:15:35'),
(362, 'convention_stage', 81, 'ST-78', '2026-06-21 21:15:41'),
(363, 'releve_notes', 244, 'INS-2026-00001', '2026-06-23 14:38:25'),
(364, 'releve_notes', 244, 'INS-2026-00001', '2026-06-23 14:38:27'),
(365, 'releve_notes', 244, 'INS-2026-00001', '2026-06-23 14:38:29'),
(366, 'billet_excuse', 89, 'ABS-123', '2026-06-23 15:31:57'),
(367, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-06-23 15:33:45'),
(368, 'convention_stage', 81, 'ST-78', '2026-06-24 06:29:37'),
(369, 'recu_paiement', 81, 'INS-2025-00010-2026-06', '2026-06-24 06:30:17'),
(370, 'fiche_inscription', 80, 'INS-2025-00009', '2026-07-02 09:39:26'),
(371, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-07-02 09:40:44'),
(372, 'releve_notes', 81, 'INS-2025-00010', '2026-07-02 16:09:57'),
(373, 'recu_paiement', 81, 'INS-2025-00010-2026-07', '2026-07-02 18:06:34'),
(374, 'recu_paiement', 81, 'INS-2025-00010-2026-07', '2026-07-02 18:07:10'),
(375, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-07-02 18:07:27'),
(376, 'etat_paiements_annuel', 147, 'INS-2025-00053', '2026-07-02 18:10:29'),
(377, 'recu_paiement', 81, 'INS-2025-00010-2026-07', '2026-07-02 18:17:23'),
(378, 'etat_paiements_annuel', 81, 'INS-2025-00010', '2026-07-02 18:18:56');

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
  `marque_le` timestamp NULL DEFAULT NULL,
  `remise` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mensualites`
--

INSERT INTO `mensualites` (`id_mensualite`, `id_stagiaire`, `mois_ref`, `est_paye`, `montant_total`, `montant_paye`, `montant_restant`, `cumul_restant`, `statut_paiement`, `date_paiement`, `marque_le`, `remise`) VALUES
(2436, 81, '2026-07', 1, 600.00, 400.00, 0.00, 0.00, 'payé', '2026-07-02 09:40:21', '2026-07-02 08:40:21', 200.00),
(2437, 81, '2026-06', 1, 600.00, 400.00, 0.00, 0.00, 'payé', '2026-07-02 00:00:00', '2026-07-02 17:11:20', 200.00);

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
(2, 'Algorithmique et Programmation', 120, 1, 2, 5, 2),
(3, 'Bases de données', 90, 0, 2, 1, 1),
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
(40, 'Algorithm', 50, 1, 3, 5, 2),
(41, 'Installation d\'un poste', 30, 2, 3, 4, 2),
(42, 'Bureautique', 40, 2, 3, 3, 2),
(43, 'Comptabilité générale', 50, 1, 3, 7, 3),
(44, 'Statistique', 35, 2, 3, 6, 3),
(59, 'UML', 50, 2, 2, 5, 3);

-- --------------------------------------------------------

--
-- Table structure for table `module_notes`
--

CREATE TABLE `module_notes` (
  `id_stagiaire` int(10) UNSIGNED NOT NULL,
  `id_module` int(10) UNSIGNED NOT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  `type` varchar(32) NOT NULL COMMENT 'controle_1, controle_2, ..., theorique, pratique'
) ;

--
-- Dumping data for table `module_notes`
--

INSERT INTO `module_notes` (`id_stagiaire`, `id_module`, `note`, `type`) VALUES
(81, 40, 11.00, 'controle_1'),
(81, 40, 19.00, 'controle_2'),
(81, 40, 17.00, 'controle_3'),
(81, 40, 17.00, 'pratique'),
(81, 40, 17.00, 'theorique'),
(244, 2, 15.00, 'controle_1'),
(244, 2, 16.00, 'pratique'),
(244, 2, 18.00, 'theorique');

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
(33, 'AA123451', 'Test', 'Alpha', '2001-12-12', 'testsstestss', 'alpha@test.com', '0612345678', NULL, 'test', 2, '2025/2026', 'converti', '2026-06-13 09:23:47', '2026-06-13 09:39:51', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Programmation\",\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', 'test', '[\"Publicit\\u00e9\",\"Relation\"]', 'test', '[\"Management et Ressource Humaine\",\"Finance et Comptabilit\\u00e9\",\"Logistique Internationale\",\"Informatique\"]'),
(34, 'CD678901', 'Deux', 'Bêta', '2000-12-12', 'DeuxDeux', 'beta2@test.com', '0698765432', '0698765432', 'Deux', 2, '2026/2027', 'converti', '2026-06-13 09:40:33', '2026-06-13 09:40:38', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"R\\u00e9seau\"]', NULL, '[\"Publicit\\u00e9\"]', NULL, '[\"Logistique Internationale\"]'),
(35, 'SV121122', 'tres', 'tres', '2003-12-12', 'trestres', 'tres@gmail.com', '0650757944', '0650757944', 'tres', 2, '2024/2025', 'converti', '2026-06-13 09:41:29', '2026-06-13 09:41:56', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', NULL, '[\"Relation\"]', NULL, '[\"Finance et Comptabilit\\u00e9\"]'),
(36, 'WA000000', 'TSDI', 'TSDI', '2001-12-12', 'testtest', 'testtest@gmail.com', '0650757944', '0682427801', 'test', 2, '2025/2026', 'converti', '2026-06-13 10:34:41', '2026-06-20 19:46:00', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Comptabilit\\u00e9\"]', NULL, '[\"Relation\"]', NULL, '[\"Logistique Internationale\",\"Informatique\"]'),
(37, 'WA000031', 'TGI', 'TGI', '2001-12-12', 'testttestt', 'testttestt@gmail.com', '0650757944', '0682427801', 'testt', 3, '2025/2026', 'converti', '2026-06-13 10:38:20', '2026-06-20 19:46:00', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Comptabilit\\u00e9\"]', NULL, NULL, NULL, '[\"Management et Ressource Humaine\"]'),
(38, 'WA122466', 'TSGE', 'TSGE', '2001-12-12', 'testtttesttt', 'testtttesttt@gmail.com', '0650757944', '0682427801', 'testtt', 4, '2025/2026', 'converti', '2026-06-13 10:39:00', '2026-06-20 19:46:00', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', NULL, NULL, NULL, NULL, '[\"Finance et Comptabilit\\u00e9\",\"Informatique\"]'),
(39, 'ZZ999999', 'Harkouss', 'Test', '2002-05-15', 'Casablanca', 'test.harkouss@ipirnet.ma', '0612000001', NULL, 'Parent Test', 2, '2025/2026', 'converti', '2026-06-21 11:11:22', '2026-06-21 11:45:39', NULL, 'M', '[\"2ème Bac\"]', '[\"2ème Bac\"]', '[\"Programmation\",\"Réseau\"]', NULL, '[\"Publicité\"]', NULL, NULL),
(40, 'WA124575', 'bergam', 'El Mehdi', '2001-12-12', '122 RUE PALESTINE', 'mehdibergame@gmail.com', '0650757944', '0682427801', 'amina', 2, '2025/2026', 'converti', '2026-06-21 13:57:22', '2026-06-21 14:11:49', NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Comptabilit\\u00e9\"]', NULL, NULL, NULL, '[\"Management et Ressource Humaine\",\"Finance et Comptabilit\\u00e9\",\"Logistique Internationale\",\"Informatique\"]'),
(41, 'WA124575', 'test', 'test', '2001-12-12', 'testtest', 'mehdibergame@gmail.com', '0650757944', '0682427801', 'testtest', 2, '2025/2026', 'converti', '2026-06-23 13:46:48', '2026-06-23 14:37:07', 244, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"R\\u00e9seau\"]', 'aaa', '[\"Publicit\\u00e9\"]', 'aaa', '[\"Logistique Internationale\"]'),
(42, 'WA123456', 'testt', 'testt', '2001-12-12', 'testttestt', 'testttestt@gmail.com', '0650757944', '0682427801', 'testt', 3, '2025/2026', 'converti', '2026-06-23 13:48:42', '2026-06-23 14:37:07', 245, 'F', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Programmation\",\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', 'aaa', '[\"Publicit\\u00e9\"]', 'aaa', '[\"Management et Ressource Humaine\",\"Finance et Comptabilit\\u00e9\",\"Logistique Internationale\",\"Informatique\"]'),
(43, 'WA012114', 'testtt', 'testtt', '2001-02-12', '122 RUE PALESTINE', 'testtttesttt@gmail.com', '0650757944', '0682427801', 'amina', 4, '2025/2026', 'converti', '2026-06-23 13:50:30', '2026-06-23 14:37:07', 246, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Bureautique\",\"Programmation\",\"Comptabilit\\u00e9\",\"R\\u00e9seau\"]', 'aa', '[\"Publicit\\u00e9\"]', 'aa', '[\"Management et Ressource Humaine\",\"Finance et Comptabilit\\u00e9\",\"Logistique Internationale\",\"Informatique\"]'),
(44, 'WA123452', 'ahmad', 'bergam', '2001-12-12', 'testtest', 'mehdibergame44@gmail.com', '0650757944', '0682427801', 'amina', 2, '2025/2026', 'en_attente', '2026-07-02 15:24:09', NULL, NULL, 'M', '[\"2\\u00e8me Bac\"]', '[\"2\\u00e8me Bac\"]', '[\"Comptabilit\\u00e9\"]', NULL, NULL, NULL, '[\"Management et Ressource Humaine\"]');

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
(2024, 83),
(2025, 83),
(2026, 27);

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `id_stage` int(10) UNSIGNED NOT NULL,
  `type_stage` enum('stage_entreprise','pfe') NOT NULL DEFAULT 'stage_entreprise',
  `annee_scolaire` varchar(9) DEFAULT NULL COMMENT 'Ex: 2024/2025',
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

INSERT INTO `stages` (`id_stage`, `type_stage`, `annee_scolaire`, `sujet`, `entreprise`, `date_debut`, `date_fin`, `note_stage`, `convention_url`, `rapport_url`, `evaluation_entreprise`, `date_soutenance`, `jury`, `id_stagiaire`, `created_at`) VALUES
(13, 'pfe', '2025/2026', 'Mise en place d\'une infrastructure réseau d\'entreprise', 'NetPro Maroc', '2026-06-01', '2026-07-31', 15.00, NULL, NULL, 'Bon travail', NULL, NULL, 83, '2026-06-19 17:14:30'),
(16, 'stage_entreprise', '2025/2026', 'Administration réseau Windows Server', 'WinSol', '2026-06-01', '2026-07-31', NULL, NULL, NULL, NULL, NULL, NULL, 85, '2026-06-19 17:14:30'),
(54, 'stage_entreprise', '2025/2026', 'Développement d\'une application web de gestion des stocks', 'TechMaroc SARL', '2026-06-01', '2026-07-31', 15.00, NULL, NULL, 'Très bonne maîtrise des outils web', NULL, NULL, 75, '2026-06-19 19:23:32'),
(55, 'pfe', '2025/2026', 'Conception d\'un système de gestion documentaire avec Laravel', 'TechMaroc SARL', '2026-06-01', '2026-07-31', 16.00, NULL, NULL, 'Excellent travail, livrable de qualité', NULL, NULL, 75, '2026-06-19 19:23:32'),
(56, 'stage_entreprise', '2025/2026', 'Mise en place d\'un réseau local sécurisé', 'Infocom Maroc', '2026-06-01', '2026-07-31', 14.00, NULL, NULL, 'Bonne initiative et autonomie', NULL, NULL, 76, '2026-06-19 19:23:32'),
(57, 'pfe', '2025/2026', 'Développement d\'une API REST pour un système de pointage', 'Infocom Maroc', '2026-06-01', '2026-07-31', 17.00, NULL, NULL, 'Travail remarquable', NULL, NULL, 76, '2026-06-19 19:23:32'),
(58, 'stage_entreprise', '2025/2026', 'Maintenance et administration de bases de données', 'DataSys', '2026-06-01', '2026-07-31', NULL, NULL, NULL, NULL, NULL, NULL, 77, '2026-06-19 19:23:32'),
(59, 'stage_entreprise', '2025/2026', 'Développement mobile Android', 'Mobilink', '2026-06-01', '2026-07-31', 13.00, NULL, NULL, 'Apprentissage rapide', NULL, NULL, 78, '2026-06-19 19:23:32'),
(60, 'pfe', '2025/2026', 'Application de gestion des ressources humaines', 'Mobilink', '2026-06-01', '2026-07-31', 12.00, NULL, NULL, 'Résultat satisfaisant', NULL, NULL, 78, '2026-06-19 19:23:32'),
(61, 'stage_entreprise', '2025/2026', 'Installation et configuration de serveurs', 'NetPro Maroc', '2026-06-01', '2026-07-31', 14.00, NULL, NULL, 'Très sérieux', NULL, NULL, 83, '2026-06-19 19:23:32'),
(63, 'stage_entreprise', '2025/2026', 'Support technique et maintenance informatique', 'Assist IT', '2026-06-01', '2026-07-31', 16.00, NULL, NULL, 'Excellent technicien', NULL, NULL, 84, '2026-06-19 19:23:32'),
(64, 'pfe', '2025/2026', 'Virtualisation et cloud computing', 'Assist IT', '2026-06-01', '2026-07-31', 18.00, NULL, NULL, 'Travail exceptionnel', NULL, NULL, 84, '2026-06-19 19:23:32'),
(66, 'stage_entreprise', '2025/2026', 'Gestion de la comptabilité fournisseur', 'Cabinet Benali & Associés', '2026-06-01', '2026-07-31', 15.00, NULL, NULL, 'Rigueur et précision', NULL, NULL, 91, '2026-06-19 19:23:32'),
(67, 'pfe', '2025/2026', 'Mise en place d\'un tableau de bord financier', 'Cabinet Benali & Associés', '2026-06-01', '2026-07-31', 14.00, NULL, NULL, 'Bon résultat', NULL, NULL, 91, '2026-06-19 19:23:32'),
(68, 'stage_entreprise', '2025/2026', 'Marketing digital et gestion réseaux sociaux', 'AgencePlus', '2026-06-01', '2026-07-31', 17.00, NULL, NULL, 'Créativité et professionnalisme', NULL, NULL, 92, '2026-06-19 19:23:32'),
(69, 'pfe', '2025/2026', 'Stratégie de communication pour PME', 'AgencePlus', '2026-06-01', '2026-07-31', 16.00, NULL, NULL, 'Très bonne analyse', NULL, NULL, 92, '2026-06-19 19:23:32'),
(70, 'stage_entreprise', '2025/2026', 'Traitement de la paie et déclarations sociales', 'RH Conseil', '2026-06-01', '2026-07-31', NULL, NULL, NULL, NULL, NULL, NULL, 93, '2026-06-19 19:23:32'),
(71, 'stage_entreprise', '2024/2025', 'Développement d\'un ERP modulaire', 'CodeFactory', '2025-06-01', '2025-07-31', 16.00, NULL, NULL, 'Excellent développeur', NULL, NULL, 98, '2026-06-19 19:23:32'),
(72, 'pfe', '2024/2025', 'Intégration d\'un module de reporting avancé', 'CodeFactory', '2025-06-01', '2025-07-31', 17.00, NULL, NULL, 'Livrable impeccable', NULL, NULL, 98, '2026-06-19 19:23:32'),
(73, 'stage_entreprise', '2024/2025', 'Audit et sécurité des systèmes d\'information', 'SecureNet', '2025-06-01', '2025-07-31', 15.00, NULL, NULL, 'Très bon', NULL, NULL, 99, '2026-06-19 19:23:32'),
(74, 'stage_entreprise', '2024/2025', 'Déploiement infrastructure VMware', 'VirtTech', '2025-06-01', '2025-07-31', 14.00, NULL, NULL, 'Bonne maîtrise', NULL, NULL, 106, '2026-06-19 19:23:32'),
(75, 'pfe', '2024/2025', 'Automatisation des sauvegardes réseau', 'VirtTech', '2025-06-01', '2025-07-31', 15.00, NULL, NULL, 'Résultat satisfaisant', NULL, NULL, 106, '2026-06-19 19:23:32'),
(76, 'stage_entreprise', '2024/2025', 'Contrôle de gestion et budgétisation', 'Finance+', '2025-06-01', '2025-07-31', 16.00, NULL, NULL, 'Très rigoureux', NULL, NULL, 114, '2026-06-19 19:23:32'),
(77, 'pfe', '2024/2025', 'Modèle financier prévisionnel sur Excel', 'Finance+', '2025-06-01', '2025-07-31', 15.00, NULL, NULL, 'Bon travail', NULL, NULL, 114, '2026-06-19 19:23:32'),
(78, 'stage_entreprise', '2025/2026', 'creation d\'une base de donnees', 'IPIRNET', '2026-04-01', '2026-04-18', NULL, NULL, NULL, NULL, '0000-00-00', 'Mr abdoussi', 81, '2026-06-21 10:02:13');

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `remise_mensuelle` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stagiaires`
--

INSERT INTO `stagiaires` (`id_stagiaire`, `num_inscri`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `mot_de_passe`, `photo`, `date_inscription`, `id_classe`, `created_at`, `updated_at`, `remise_mensuelle`) VALUES
(72, 'INS-2025-00001', 'AB123456', 'Benali', 'Youssef', '2003-03-15', '12 rue Al Massira, Casablanca', 'youssef.benali@gmail.com', '0661234501', '0661234501', 'Hassan Benali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 17:14:30', '2026-07-02 19:27:23', 100.00),
(73, 'INS-2025-00002', 'CD234567', 'Alaoui', 'Sara', '2003-07-22', '45 bd Zerktouni, Casablanca', 'sara.alaoui@gmail.com', '0661234502', '0661234502', 'Kamal Alaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 17:14:30', NULL, 0.00),
(74, 'INS-2025-00003', 'EF345678', 'Fassi', 'Hamza', '2002-11-08', '78 av Hassan II, Berrechid', 'hamza.fassi@gmail.com', '0661234503', '0661234503', 'Rachid Fassi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 17:14:30', NULL, 0.00),
(75, 'INS-2025-00004', 'GH456789', 'Tazi', 'Amine', '2002-05-30', '23 rue Moulay Ismail, Casablanca', 'amine.tazi@gmail.com', '0661234504', '0661234504', 'Tarik Tazi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 17:14:30', NULL, 0.00),
(76, 'INS-2025-00005', 'IJ567890', 'Cherkaoui', 'Fatima', '2001-09-14', '56 av Mohammed V, Casablanca', 'fatima.cherkaoui@gmail.com', '0661234505', '0661234505', 'Aziz Cherkaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 17:14:30', NULL, 0.00),
(77, 'INS-2025-00006', 'KL678901', 'Berrada', 'Karim', '2001-12-03', '90 rue Anfa, Casablanca', 'karim.berrada@gmail.com', '0661234506', '0661234506', 'Said Berrada', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 17:14:30', NULL, 0.00),
(78, 'INS-2025-00007', 'MN789012', 'Bennis', 'Nour', '2002-02-18', '34 bd Al Massira, Berrechid', 'nour.bennis@gmail.com', '0661234507', '0661234507', 'Omar Bennis', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 17:14:30', NULL, 0.00),
(79, 'INS-2025-00008', 'OP890123', 'Tahiri', 'Ibrahim', '2003-06-25', '15 rue Ibn Battouta, Casablanca', 'ibrahim.tahiri@gmail.com', '0661234508', '0661234508', 'Mustapha Tahiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 17:14:30', NULL, 0.00),
(80, 'INS-2025-00009', 'QR901234', 'Lahrech', 'Imane', '2003-01-11', '67 av Fal Ould Oumeir, Casablanca', 'imane.lahrech@gmail.com', '0661234509', '0661234509', 'Khalid Lahrech', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 17:14:30', NULL, 0.00),
(81, 'INS-2025-00010', 'ST012345', 'Belhaj', 'Omar', '2002-08-19', '89 rue Lalla Yacout, Casablanca', 'omar.belhaj@gmail.com', '0661234510', '0661234510', 'Abdellah Belhaj', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 17:14:30', '2026-06-20 21:17:22', 200.00),
(82, 'INS-2025-00011', 'UV123456', 'Mernissi', 'Zineb', '2003-04-27', '12 bd Emile Zola, Casablanca', 'zineb.mernissi@gmail.com', '0661234511', '0661234511', 'Hicham Mernissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 17:14:30', NULL, 0.00),
(83, 'INS-2025-00012', 'WX234567', 'Kettani', 'Soufiane', '2001-10-05', '45 rue Sebou, Casablanca', 'soufiane.kettani@gmail.com', '0661234512', '0661234512', 'Younes Kettani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 17:14:30', NULL, 0.00),
(84, 'INS-2025-00013', 'YZ345678', 'Skalli', 'Amina', '2001-03-16', '78 av Lalla Meryem, Casablanca', 'amina.skalli@gmail.com', '0661234513', '0661234513', 'Nabil Skalli', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 17:14:30', NULL, 0.00),
(85, 'INS-2025-00014', 'AB456789', 'Rahmani', 'Ayoub', '2002-07-08', '56 rue Abou Inane, Berrechid', 'ayoub.rahmani@gmail.com', '0661234514', '0661234514', 'Rachid Rahmani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 17:14:30', NULL, 0.00),
(86, 'INS-2025-00015', 'CD567890', 'Ouali', 'Khadija', '2001-11-23', '23 bd Yacoub El Mansour, Casablanca', 'khadija.ouali@gmail.com', '0661234515', '0661234515', 'Samir Ouali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 17:14:30', NULL, 0.00),
(87, 'INS-2025-00016', 'EF678901', 'Bennani', 'Mehdi', '2003-02-14', '90 av des FAR, Casablanca', 'mehdi.bennani@gmail.com', '0661234516', '0661234516', 'Ahmed Bennani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 17:14:30', NULL, 0.00),
(88, 'INS-2025-00017', 'GH789012', 'Chakroun', 'Salma', '2003-09-30', '34 rue Panorama, Casablanca', 'salma.chakroun@gmail.com', '0661234517', '0661234517', 'Fouad Chakroun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 17:14:30', NULL, 0.00),
(89, 'INS-2025-00018', 'IJ890123', 'Bensouda', 'Bilal', '2002-12-07', '15 rue Oued Ziz, Berrechid', 'bilal.bensouda@gmail.com', '0661234518', '0661234518', 'Mohammed Bensouda', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 17:14:30', NULL, 0.00),
(90, 'INS-2025-00019', 'KL901234', 'Filali', 'Rim', '2003-05-21', '67 bd Moulay Abd Aziz, Casablanca', 'rim.filali@gmail.com', '0661234519', '0661234519', 'Brahim Filali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 17:14:30', NULL, 0.00),
(91, 'INS-2025-00020', 'MN012345', 'Lahlou', 'Rachid', '2001-08-09', '89 av Al Aqaba, Casablanca', 'rachid.lahlou@gmail.com', '0661234520', '0661234520', 'Driss Lahlou', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 17:14:30', NULL, 0.00),
(92, 'INS-2025-00021', 'OP123456', 'Tazi', 'Yasmine', '2001-04-28', '12 rue Agadir, Casablanca', 'yasmine.tazi2@gmail.com', '0661234521', '0661234521', 'Jamal Tazi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 17:14:30', NULL, 0.00),
(93, 'INS-2025-00022', 'QR234567', 'Alaoui', 'Zakaria', '2002-01-15', '45 rue Tiznit, Casablanca', 'zakaria.alaoui2@gmail.com', '0661234522', '0661234522', 'Hamid Alaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 17:14:30', NULL, 0.00),
(94, 'INS-2025-00023', 'ST345678', 'Fassi', 'Laila', '2001-06-03', '78 av Moulay Youssef, Berrechid', 'laila.fassi2@gmail.com', '0661234523', '0661234523', 'Aziz Fassi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 17:14:30', NULL, 0.00),
(95, 'INS-2024-00001', 'UV456789', 'Berrada', 'Khalid', '2002-04-12', '23 rue Al Massira, Casablanca', 'khalid.berrada2@gmail.com', '0661234524', '0661234524', 'Said Berrada', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 17:14:30', '2026-06-20 21:21:45', 300.00),
(96, 'INS-2024-00002', 'WX567890', 'Bennis', 'Houda', '2002-10-25', '56 bd Zerktouni, Casablanca', 'houda.bennis@gmail.com', '0661234525', '0661234525', 'Omar Bennis', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 17:14:30', NULL, 0.00),
(97, 'INS-2024-00003', 'YZ678901', 'Tahiri', 'Nassim', '2003-01-18', '90 av Hassan II, Berrechid', 'nassim.tahiri@gmail.com', '0661234526', '0661234526', 'Mustapha Tahiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 17:14:30', NULL, 0.00),
(98, 'INS-2024-00004', 'AB789012', 'Lahrech', 'Abdellah', '2001-07-06', '34 rue Ibn Battouta, Casablanca', 'abdellah.lahrech@gmail.com', '0661234527', '0661234527', 'Khalid Lahrech', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 17:14:30', NULL, 0.00),
(99, 'INS-2024-00005', 'CD890123', 'Belhaj', 'Brahim', '2001-02-14', '67 av Fal Ould Oumeir, Casablanca', 'brahim.belhaj@gmail.com', '0661234528', '0661234528', 'Abdellah Belhaj', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 17:14:30', NULL, 0.00),
(100, 'INS-2024-00006', 'EF901234', 'Mernissi', 'Hicham', '2001-11-29', '89 rue Lalla Yacout, Casablanca', 'hicham.mernissi2@gmail.com', '0661234529', '0661234529', 'Karim Mernissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 17:14:30', NULL, 0.00),
(101, 'INS-2024-00007', 'GH012345', 'Kettani', 'Nabil', '2001-09-17', '12 rue Sebou, Casablanca', 'nabil.kettani@gmail.com', '0661234530', '0661234530', 'Younes Kettani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 17:14:30', NULL, 0.00),
(102, 'INS-2024-00008', 'IJ123456', 'Skalli', 'Tarek', '2002-05-08', '45 av Lalla Meryem, Casablanca', 'tarek.skalli@gmail.com', '0661234531', '0661234531', 'Nabil Skalli', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 17:14:30', NULL, 0.00),
(103, 'INS-2024-00009', 'KL234567', 'Rahmani', 'Mohammed', '2003-08-21', '78 rue Abou Inane, Berrechid', 'mohammed.rahmani@gmail.com', '0661234532', '0661234532', 'Rachid Rahmani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 17:14:30', NULL, 0.00),
(104, 'INS-2024-00010', 'MN345678', 'Ouali', 'Aicha', '2002-03-15', '56 bd Yacoub El Mansour, Casablanca', 'aicha.ouali@gmail.com', '0661234533', '0661234533', 'Samir Ouali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 17:14:30', NULL, 0.00),
(105, 'INS-2024-00011', 'OP456789', 'Bennani', 'Youssef', '2002-12-04', '23 av des FAR, Casablanca', 'youssef.bennani@gmail.com', '0661234534', '0661234534', 'Ahmed Bennani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 17:14:30', '2026-06-20 17:14:15', 0.00),
(106, 'INS-2024-00012', 'QR567890', 'Chakroun', 'Sara', '2001-06-18', '34 rue Panorama, Casablanca', 'sara.chakroun@gmail.com', '0661234535', '0661234535', 'Fouad Chakroun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 17:14:30', NULL, 0.00),
(107, 'INS-2024-00013', 'ST678901', 'Bensouda', 'Hamza', '2001-10-09', '15 rue Oued Ziz, Berrechid', 'hamza.bensouda@gmail.com', '0661234536', '0661234536', 'Mohammed Bensouda', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 17:14:30', NULL, 0.00),
(108, 'INS-2024-00014', 'UV789012', 'Filali', 'Amine', '2001-04-26', '67 bd Moulay Abd Aziz, Casablanca', 'amine.filali@gmail.com', '0661234537', '0661234537', 'Brahim Filali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 17:14:30', NULL, 0.00),
(109, 'INS-2024-00015', 'WX890123', 'Lahlou', 'Fatima', '2001-01-13', '89 av Al Aqaba, Casablanca', 'fatima.lahlou@gmail.com', '0661234538', '0661234538', 'Driss Lahlou', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 17:14:30', NULL, 0.00),
(110, 'INS-2024-00016', 'YZ901234', 'Tazi', 'Karim', '2002-09-02', '12 rue Agadir, Casablanca', 'karim.tazi2@gmail.com', '0661234539', '0661234539', 'Jamal Tazi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 17:14:30', NULL, 0.00),
(111, 'INS-2024-00017', 'AB012345', 'Alaoui', 'Nour', '2002-06-24', '45 rue Tiznit, Casablanca', 'nour.alaoui@gmail.com', '0661234540', '0661234540', 'Hamid Alaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 17:14:30', NULL, 0.00),
(112, 'INS-2024-00018', 'CD123456', 'Fassi', 'Ibrahim', '2002-02-11', '78 av Moulay Youssef, Berrechid', 'ibrahim.fassi@gmail.com', '0661234541', '0661234541', 'Rachid Fassi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 17:14:30', NULL, 0.00),
(113, 'INS-2024-00019', 'EF234567', 'Berrada', 'Imane', '2002-11-30', '56 bd Hassan II, Casablanca', 'imane.berrada@gmail.com', '0661234542', '0661234542', 'Said Berrada', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 17:14:30', NULL, 0.00),
(114, 'INS-2024-00020', 'GH345678', 'Bennis', 'Omar', '2001-07-17', '23 rue Al Massira, Casablanca', 'omar.bennis2@gmail.com', '0661234543', '0661234543', 'Driss Bennis', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 17:14:30', NULL, 0.00),
(115, 'INS-2024-00021', 'IJ456789', 'Tahiri', 'Zineb', '2001-03-05', '90 bd Zerktouni, Casablanca', 'zineb.tahiri@gmail.com', '0661234544', '0661234544', 'Mustapha Tahiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 17:14:30', NULL, 0.00),
(116, 'INS-2024-00022', 'KL567890', 'Lahrech', 'Soufiane', '2001-12-22', '34 av Hassan II, Berrechid', 'soufiane.lahrech@gmail.com', '0661234545', '0661234545', 'Khalid Lahrech', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 17:14:30', NULL, 0.00),
(117, 'INS-2024-00023', 'MN678901', 'Belhaj', 'Amina', '2001-08-10', '15 rue Ibn Battouta, Casablanca', 'amina.belhaj@gmail.com', '0661234546', '0661234546', 'Abdellah Belhaj', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 17:14:30', NULL, 0.00),
(118, 'INS-2025-00024', 'PA700000', 'Amrani', 'Achraf', '2003-11-07', '38 rue Anfa, Benslimane', 'achraf.amrani118@gmail.com', '0661234118', '0671234118', 'Chadi Amrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(119, 'INS-2025-00025', 'PB700001', 'Benkiran', 'Anas', '2003-12-08', '39 av Yacoub El Mansour, Casablanca', 'anas.benkiran119@gmail.com', '0661234119', '0671234119', 'Ilyass Benkiran', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(120, 'INS-2025-00026', 'PC700002', 'Bouazza', 'Chaima', '2002-01-09', '40 rue Lalla Yacout, Berrechid', 'chaima.bouazza120@gmail.com', '0661234120', '0671234120', 'Chadi Bouazza', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(121, 'INS-2025-00027', 'PD700003', 'Guennoun', 'Chakib', '2003-02-10', '41 bd Al Massira, Mohammedia', 'chakib.guennoun121@gmail.com', '0661234121', '0671234121', 'Anas Guennoun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(122, 'INS-2025-00028', 'PE700004', 'Hajji', 'Diyaa', '2003-03-11', '42 av Moulay Youssef, El Jadida', 'diyaa.hajji122@gmail.com', '0661234122', '0671234122', 'Badr Hajji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(123, 'INS-2025-00029', 'PF700005', 'Idrissi', 'Fatine', '2002-04-12', '43 rue Oued Ziz, Settat', 'fatine.idrissi123@gmail.com', '0661234123', '0671234123', 'Anas Idrissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(124, 'INS-2025-00030', 'PG700006', 'Moussaoui', 'Faisal', '2003-05-13', '44 rue Sebou, Khouribga', 'faisal.moussaoui124@gmail.com', '0661234124', '0671234124', 'Diyaa Moussaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(125, 'INS-2025-00031', 'PH700007', 'Oufkir', 'Ghali', '2003-06-14', '45 bd Moulay Abd Aziz, Benslimane', 'ghali.oufkir125@gmail.com', '0661234125', '0671234125', 'Elias Oufkir', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(126, 'INS-2025-00032', 'PI700008', 'Rhazali', 'Ikram', '2002-07-15', '46 av Al Aqaba, Casablanca', 'ikram.rhazali126@gmail.com', '0661234126', '0671234126', 'Diyaa Rhazali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(127, 'INS-2025-00033', 'PJ700009', 'Soussi', 'Ismail', '2003-08-16', '47 rue Agadir, Berrechid', 'ismail.soussi127@gmail.com', '0661234127', '0671234127', 'Ghali Soussi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 1, '2026-06-19 19:23:10', NULL, 0.00),
(128, 'INS-2025-00034', 'PK700010', 'Ziani', 'Achraf', '2003-09-17', '48 rue Al Massira, Mohammedia', 'achraf.ziani128@gmail.com', '0661234128', '0671234128', 'Hatim Ziani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(129, 'INS-2025-00035', 'PL700011', 'Mansouri', 'Basma', '2002-10-18', '49 bd Zerktouni, El Jadida', 'basma.mansouri129@gmail.com', '0661234129', '0671234129', 'Ghali Mansouri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(130, 'INS-2025-00036', 'QA700012', 'Chraibi', 'Badr', '2003-11-19', '50 av Hassan II, Settat', 'badr.chraibi130@gmail.com', '0661234130', '0671234130', 'Jawad Chraibi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(131, 'INS-2025-00037', 'QB700013', 'Doukkali', 'Chakib', '2003-12-20', '51 rue Ibn Battouta, Khouribga', 'chakib.doukkali131@gmail.com', '0661234131', '0671234131', 'Kamal Doukkali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(132, 'INS-2025-00038', 'QC700014', 'Ennaji', 'Emna', '2002-01-21', '52 av des FAR, Benslimane', 'emna.ennaji132@gmail.com', '0661234132', '0671234132', 'Jawad Ennaji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(133, 'INS-2025-00039', 'QD700015', 'Hammouda', 'Elias', '2003-02-22', '53 bd Mohammed V, Casablanca', 'elias.hammouda133@gmail.com', '0661234133', '0671234133', 'Mounir Hammouda', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(134, 'INS-2025-00040', 'QE700016', 'Jabrane', 'Faisal', '2003-03-23', '54 rue Anfa, Berrechid', 'faisal.jabrane134@gmail.com', '0661234134', '0671234134', 'Nassim Jabrane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(135, 'INS-2025-00041', 'QF700017', 'Kabiri', 'Hafsa', '2002-04-24', '55 av Yacoub El Mansour, Mohammedia', 'hafsa.kabiri135@gmail.com', '0661234135', '0671234135', 'Mounir Kabiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(136, 'INS-2025-00042', 'QG700018', 'Lamrani', 'Hatim', '2003-05-25', '56 rue Lalla Yacout, El Jadida', 'hatim.lamrani136@gmail.com', '0661234136', '0671234136', 'Ramzi Lamrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(137, 'INS-2025-00043', 'QH700019', 'Mdaghri', 'Ismail', '2003-06-26', '57 bd Al Massira, Settat', 'ismail.mdaghri137@gmail.com', '0661234137', '0671234137', 'Sami Mdaghri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 2, '2026-06-19 19:23:10', NULL, 0.00),
(138, 'INS-2025-00044', 'QI700020', 'Naciri', 'Amal', '2002-07-27', '58 av Moulay Youssef, Khouribga', 'amal.naciri138@gmail.com', '0661234138', '0671234138', 'Ramzi Naciri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(139, 'INS-2025-00045', 'QJ700021', 'Ouazzani', 'Anas', '2003-08-28', '59 rue Oued Ziz, Benslimane', 'anas.ouazzani139@gmail.com', '0661234139', '0671234139', 'Walid Ouazzani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(140, 'INS-2025-00046', 'QK700022', 'Qacimi', 'Badr', '2003-09-01', '60 rue Sebou, Casablanca', 'badr.qacimi140@gmail.com', '0661234140', '0671234140', 'Yahya Qacimi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(141, 'INS-2025-00047', 'QL700023', 'Raji', 'Doha', '2002-10-02', '61 bd Moulay Abd Aziz, Berrechid', 'doha.raji141@gmail.com', '0661234141', '0671234141', 'Walid Raji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(142, 'INS-2025-00048', 'RA700024', 'Sabri', 'Diyaa', '2003-11-03', '62 av Al Aqaba, Mohammedia', 'diyaa.sabri142@gmail.com', '0661234142', '0671234142', 'Aymane Sabri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(143, 'INS-2025-00049', 'RB700025', 'Tobji', 'Elias', '2003-12-04', '63 rue Agadir, El Jadida', 'elias.tobji143@gmail.com', '0661234143', '0671234143', 'Chadi Tobji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(144, 'INS-2025-00050', 'RC700026', 'Wahbi', 'Ghita', '2002-01-05', '64 rue Al Massira, Settat', 'ghita.wahbi144@gmail.com', '0661234144', '0671234144', 'Aymane Wahbi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(145, 'INS-2025-00051', 'RD700027', 'Yacoubi', 'Ghali', '2003-02-06', '65 bd Zerktouni, Khouribga', 'ghali.yacoubi145@gmail.com', '0661234145', '0671234145', 'Achraf Yacoubi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(146, 'INS-2025-00052', 'RE700028', 'Zenati', 'Hatim', '2003-03-07', '66 av Hassan II, Benslimane', 'hatim.zenati146@gmail.com', '0661234146', '0671234146', 'Anas Zenati', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(147, 'INS-2025-00053', 'RF700029', 'Boucetta', 'Jihane', '2002-04-08', '67 rue Ibn Battouta, Casablanca', 'jihane.boucetta147@gmail.com', '0661234147', '0671234147', 'Achraf Boucetta', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 3, '2026-06-19 19:23:10', NULL, 0.00),
(148, 'INS-2025-00054', 'RG700030', 'Sefrioui', 'Achraf', '2003-05-09', '68 av des FAR, Berrechid', 'achraf.sefrioui148@gmail.com', '0661234148', '0671234148', 'Chakib Sefrioui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(149, 'INS-2025-00055', 'RH700031', 'Hamdouni', 'Anas', '2003-06-10', '69 bd Mohammed V, Mohammedia', 'anas.hamdouni149@gmail.com', '0661234149', '0671234149', 'Diyaa Hamdouni', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(150, 'INS-2025-00056', 'RI700032', 'Rouchdi', 'Chaima', '2002-07-11', '70 rue Anfa, El Jadida', 'chaima.rouchdi150@gmail.com', '0661234150', '0671234150', 'Chakib Rouchdi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(151, 'INS-2025-00057', 'RJ700033', 'Squalli', 'Chakib', '2003-08-12', '71 av Yacoub El Mansour, Settat', 'chakib.squalli151@gmail.com', '0661234151', '0671234151', 'Faisal Squalli', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(152, 'INS-2025-00058', 'RK700034', 'Benkirane', 'Diyaa', '2003-09-13', '72 rue Lalla Yacout, Khouribga', 'diyaa.benkirane152@gmail.com', '0661234152', '0671234152', 'Ghali Benkirane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(153, 'INS-2025-00059', 'RL700035', 'Amrani', 'Fatine', '2002-10-14', '73 bd Al Massira, Benslimane', 'fatine.amrani153@gmail.com', '0661234153', '0671234153', 'Faisal Amrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(154, 'INS-2025-00060', 'SA700036', 'Benkiran', 'Faisal', '2003-11-15', '74 av Moulay Youssef, Casablanca', 'faisal.benkiran154@gmail.com', '0661234154', '0671234154', 'Ismail Benkiran', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(155, 'INS-2025-00061', 'SB700037', 'Bouazza', 'Ghali', '2003-12-16', '75 rue Oued Ziz, Berrechid', 'ghali.bouazza155@gmail.com', '0661234155', '0671234155', 'Jawad Bouazza', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(156, 'INS-2025-00062', 'SC700038', 'Guennoun', 'Ikram', '2002-01-17', '76 rue Sebou, Mohammedia', 'ikram.guennoun156@gmail.com', '0661234156', '0671234156', 'Ismail Guennoun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(157, 'INS-2025-00063', 'SD700039', 'Hajji', 'Ismail', '2003-02-18', '77 bd Moulay Abd Aziz, El Jadida', 'ismail.hajji157@gmail.com', '0661234157', '0671234157', 'Lotfi Hajji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 4, '2026-06-19 19:23:10', NULL, 0.00),
(158, 'INS-2025-00064', 'SE700040', 'Idrissi', 'Achraf', '2003-03-19', '78 av Al Aqaba, Settat', 'achraf.idrissi158@gmail.com', '0661234158', '0671234158', 'Mounir Idrissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(159, 'INS-2025-00065', 'SF700041', 'Moussaoui', 'Basma', '2002-04-20', '79 rue Agadir, Khouribga', 'basma.moussaoui159@gmail.com', '0661234159', '0671234159', 'Lotfi Moussaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(160, 'INS-2025-00066', 'SG700042', 'Oufkir', 'Badr', '2003-05-21', '80 rue Al Massira, Benslimane', 'badr.oufkir160@gmail.com', '0661234160', '0671234160', 'Othmane Oufkir', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(161, 'INS-2025-00067', 'SH700043', 'Rhazali', 'Chakib', '2003-06-22', '81 bd Zerktouni, Casablanca', 'chakib.rhazali161@gmail.com', '0661234161', '0671234161', 'Ramzi Rhazali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(162, 'INS-2025-00068', 'SI700044', 'Soussi', 'Emna', '2002-07-23', '82 av Hassan II, Berrechid', 'emna.soussi162@gmail.com', '0661234162', '0671234162', 'Othmane Soussi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(163, 'INS-2025-00069', 'SJ700045', 'Ziani', 'Elias', '2003-08-24', '83 rue Ibn Battouta, Mohammedia', 'elias.ziani163@gmail.com', '0661234163', '0671234163', 'Taha Ziani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(164, 'INS-2025-00070', 'SK700046', 'Mansouri', 'Faisal', '2003-09-25', '84 av des FAR, El Jadida', 'faisal.mansouri164@gmail.com', '0661234164', '0671234164', 'Walid Mansouri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(165, 'INS-2025-00071', 'SL700047', 'Chraibi', 'Hafsa', '2002-10-26', '85 bd Mohammed V, Settat', 'hafsa.chraibi165@gmail.com', '0661234165', '0671234165', 'Taha Chraibi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(166, 'INS-2025-00072', 'TA700048', 'Doukkali', 'Hatim', '2003-11-27', '86 rue Anfa, Khouribga', 'hatim.doukkali166@gmail.com', '0661234166', '0671234166', 'Adil Doukkali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(167, 'INS-2025-00073', 'TB700049', 'Ennaji', 'Ismail', '2003-12-28', '87 av Yacoub El Mansour, Benslimane', 'ismail.ennaji167@gmail.com', '0661234167', '0671234167', 'Aymane Ennaji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 5, '2026-06-19 19:23:10', NULL, 0.00),
(168, 'INS-2025-00074', 'TC700050', 'Hammouda', 'Amal', '2002-01-01', '88 rue Lalla Yacout, Casablanca', 'amal.hammouda168@gmail.com', '0661234168', '0671234168', 'Adil Hammouda', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(169, 'INS-2025-00075', 'TD700051', 'Jabrane', 'Anas', '2003-02-02', '89 bd Al Massira, Berrechid', 'anas.jabrane169@gmail.com', '0661234169', '0671234169', 'Ilyass Jabrane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(170, 'INS-2025-00076', 'TE700052', 'Kabiri', 'Badr', '2003-03-03', '90 av Moulay Youssef, Mohammedia', 'badr.kabiri170@gmail.com', '0661234170', '0671234170', 'Achraf Kabiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(171, 'INS-2025-00077', 'TF700053', 'Lamrani', 'Doha', '2002-04-04', '91 rue Oued Ziz, El Jadida', 'doha.lamrani171@gmail.com', '0661234171', '0671234171', 'Ilyass Lamrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(172, 'INS-2025-00078', 'TG700054', 'Mdaghri', 'Diyaa', '2003-05-05', '92 rue Sebou, Settat', 'diyaa.mdaghri172@gmail.com', '0661234172', '0671234172', 'Badr Mdaghri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(173, 'INS-2025-00079', 'TH700055', 'Naciri', 'Elias', '2003-06-06', '93 bd Moulay Abd Aziz, Khouribga', 'elias.naciri173@gmail.com', '0661234173', '0671234173', 'Chakib Naciri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(174, 'INS-2025-00080', 'TI700056', 'Ouazzani', 'Ghita', '2002-07-07', '94 av Al Aqaba, Benslimane', 'ghita.ouazzani174@gmail.com', '0661234174', '0671234174', 'Badr Ouazzani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(175, 'INS-2025-00081', 'TJ700057', 'Qacimi', 'Ghali', '2003-08-08', '95 rue Agadir, Casablanca', 'ghali.qacimi175@gmail.com', '0661234175', '0671234175', 'Elias Qacimi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(176, 'INS-2025-00082', 'TK700058', 'Raji', 'Hatim', '2003-09-09', '96 rue Al Massira, Berrechid', 'hatim.raji176@gmail.com', '0661234176', '0671234176', 'Faisal Raji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(177, 'INS-2025-00083', 'TL700059', 'Sabri', 'Jihane', '2002-10-10', '97 bd Zerktouni, Mohammedia', 'jihane.sabri177@gmail.com', '0661234177', '0671234177', 'Elias Sabri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2025-09-15', 6, '2026-06-19 19:23:10', NULL, 0.00),
(178, 'INS-2024-00024', 'UA700060', 'Tobji', 'Achraf', '2002-11-11', '98 av Hassan II, El Jadida', 'achraf.tobji178@gmail.com', '0661234178', '0671234178', 'Hatim Tobji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(179, 'INS-2024-00025', 'UB700061', 'Wahbi', 'Anas', '2002-12-12', '99 rue Ibn Battouta, Settat', 'anas.wahbi179@gmail.com', '0661234179', '0671234179', 'Ismail Wahbi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(180, 'INS-2024-00026', 'UC700062', 'Yacoubi', 'Chaima', '2002-01-13', '10 av des FAR, Khouribga', 'chaima.yacoubi180@gmail.com', '0661234180', '0671234180', 'Hatim Yacoubi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(181, 'INS-2024-00027', 'UD700063', 'Zenati', 'Chakib', '2002-02-14', '11 bd Mohammed V, Benslimane', 'chakib.zenati181@gmail.com', '0661234181', '0671234181', 'Kamal Zenati', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(182, 'INS-2024-00028', 'UE700064', 'Boucetta', 'Diyaa', '2002-03-15', '12 rue Anfa, Casablanca', 'diyaa.boucetta182@gmail.com', '0661234182', '0671234182', 'Lotfi Boucetta', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(183, 'INS-2024-00029', 'UF700065', 'Sefrioui', 'Fatine', '2002-04-16', '13 av Yacoub El Mansour, Berrechid', 'fatine.sefrioui183@gmail.com', '0661234183', '0671234183', 'Kamal Sefrioui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(184, 'INS-2024-00030', 'UG700066', 'Hamdouni', 'Faisal', '2002-05-17', '14 rue Lalla Yacout, Mohammedia', 'faisal.hamdouni184@gmail.com', '0661234184', '0671234184', 'Nassim Hamdouni', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(185, 'INS-2024-00031', 'UH700067', 'Rouchdi', 'Ghali', '2002-06-18', '15 bd Al Massira, El Jadida', 'ghali.rouchdi185@gmail.com', '0661234185', '0671234185', 'Othmane Rouchdi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(186, 'INS-2024-00032', 'UI700068', 'Squalli', 'Ikram', '2002-07-19', '16 av Moulay Youssef, Settat', 'ikram.squalli186@gmail.com', '0661234186', '0671234186', 'Nassim Squalli', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(187, 'INS-2024-00033', 'UJ700069', 'Benkirane', 'Ismail', '2002-08-20', '17 rue Oued Ziz, Khouribga', 'ismail.benkirane187@gmail.com', '0661234187', '0671234187', 'Sami Benkirane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 9, '2026-06-19 19:23:10', NULL, 0.00),
(188, 'INS-2024-00034', 'UK700070', 'Amrani', 'Achraf', '2002-09-21', '18 rue Sebou, Benslimane', 'achraf.amrani188@gmail.com', '0661234188', '0671234188', 'Taha Amrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(189, 'INS-2024-00035', 'UL700071', 'Benkiran', 'Basma', '2002-10-22', '19 bd Moulay Abd Aziz, Casablanca', 'basma.benkiran189@gmail.com', '0661234189', '0671234189', 'Sami Benkiran', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(190, 'INS-2024-00036', 'VA700072', 'Bouazza', 'Badr', '2002-11-23', '20 av Al Aqaba, Berrechid', 'badr.bouazza190@gmail.com', '0661234190', '0671234190', 'Yahya Bouazza', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(191, 'INS-2024-00037', 'VB700073', 'Guennoun', 'Chakib', '2002-12-24', '21 rue Agadir, Mohammedia', 'chakib.guennoun191@gmail.com', '0661234191', '0671234191', 'Adil Guennoun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(192, 'INS-2024-00038', 'VC700074', 'Hajji', 'Emna', '2002-01-25', '22 rue Al Massira, El Jadida', 'emna.hajji192@gmail.com', '0661234192', '0671234192', 'Yahya Hajji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(193, 'INS-2024-00039', 'VD700075', 'Idrissi', 'Elias', '2002-02-26', '23 bd Zerktouni, Settat', 'elias.idrissi193@gmail.com', '0661234193', '0671234193', 'Chadi Idrissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(194, 'INS-2024-00040', 'VE700076', 'Moussaoui', 'Faisal', '2002-03-27', '24 av Hassan II, Khouribga', 'faisal.moussaoui194@gmail.com', '0661234194', '0671234194', 'Ilyass Moussaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(195, 'INS-2024-00041', 'VF700077', 'Oufkir', 'Hafsa', '2002-04-28', '25 rue Ibn Battouta, Benslimane', 'hafsa.oufkir195@gmail.com', '0661234195', '0671234195', 'Chadi Oufkir', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(196, 'INS-2024-00042', 'VG700078', 'Rhazali', 'Hatim', '2002-05-01', '26 av des FAR, Casablanca', 'hatim.rhazali196@gmail.com', '0661234196', '0671234196', 'Anas Rhazali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(197, 'INS-2024-00043', 'VH700079', 'Soussi', 'Ismail', '2002-06-02', '27 bd Mohammed V, Berrechid', 'ismail.soussi197@gmail.com', '0661234197', '0671234197', 'Badr Soussi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 10, '2026-06-19 19:23:10', NULL, 0.00),
(198, 'INS-2024-00044', 'VI700080', 'Ziani', 'Amal', '2002-07-03', '28 rue Anfa, Mohammedia', 'amal.ziani198@gmail.com', '0661234198', '0671234198', 'Anas Ziani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(199, 'INS-2024-00045', 'VJ700081', 'Mansouri', 'Anas', '2002-08-04', '29 av Yacoub El Mansour, El Jadida', 'anas.mansouri199@gmail.com', '0661234199', '0671234199', 'Diyaa Mansouri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(200, 'INS-2024-00046', 'VK700082', 'Chraibi', 'Badr', '2002-09-05', '30 rue Lalla Yacout, Settat', 'badr.chraibi200@gmail.com', '0661234200', '0671234200', 'Elias Chraibi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(201, 'INS-2024-00047', 'VL700083', 'Doukkali', 'Doha', '2002-10-06', '31 bd Al Massira, Khouribga', 'doha.doukkali201@gmail.com', '0661234201', '0671234201', 'Diyaa Doukkali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(202, 'INS-2024-00048', 'WA700084', 'Ennaji', 'Diyaa', '2002-11-07', '32 av Moulay Youssef, Benslimane', 'diyaa.ennaji202@gmail.com', '0661234202', '0671234202', 'Ghali Ennaji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(203, 'INS-2024-00049', 'WB700085', 'Hammouda', 'Elias', '2002-12-08', '33 rue Oued Ziz, Casablanca', 'elias.hammouda203@gmail.com', '0661234203', '0671234203', 'Hatim Hammouda', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(204, 'INS-2024-00050', 'WC700086', 'Jabrane', 'Ghita', '2002-01-09', '34 rue Sebou, Berrechid', 'ghita.jabrane204@gmail.com', '0661234204', '0671234204', 'Ghali Jabrane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(205, 'INS-2024-00051', 'WD700087', 'Kabiri', 'Ghali', '2002-02-10', '35 bd Moulay Abd Aziz, Mohammedia', 'ghali.kabiri205@gmail.com', '0661234205', '0671234205', 'Jawad Kabiri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(206, 'INS-2024-00052', 'WE700088', 'Lamrani', 'Hatim', '2002-03-11', '36 av Al Aqaba, El Jadida', 'hatim.lamrani206@gmail.com', '0661234206', '0671234206', 'Kamal Lamrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(207, 'INS-2024-00053', 'WF700089', 'Mdaghri', 'Jihane', '2002-04-12', '37 rue Agadir, Settat', 'jihane.mdaghri207@gmail.com', '0661234207', '0671234207', 'Jawad Mdaghri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 11, '2026-06-19 19:23:10', NULL, 0.00),
(208, 'INS-2024-00054', 'WG700090', 'Naciri', 'Achraf', '2002-05-13', '38 rue Al Massira, Khouribga', 'achraf.naciri208@gmail.com', '0661234208', '0671234208', 'Mounir Naciri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(209, 'INS-2024-00055', 'WH700091', 'Ouazzani', 'Anas', '2002-06-14', '39 bd Zerktouni, Benslimane', 'anas.ouazzani209@gmail.com', '0661234209', '0671234209', 'Nassim Ouazzani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(210, 'INS-2024-00056', 'WI700092', 'Qacimi', 'Chaima', '2002-07-15', '40 av Hassan II, Casablanca', 'chaima.qacimi210@gmail.com', '0661234210', '0671234210', 'Mounir Qacimi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(211, 'INS-2024-00057', 'WJ700093', 'Raji', 'Chakib', '2002-08-16', '41 rue Ibn Battouta, Berrechid', 'chakib.raji211@gmail.com', '0661234211', '0671234211', 'Ramzi Raji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(212, 'INS-2024-00058', 'WK700094', 'Sabri', 'Diyaa', '2002-09-17', '42 av des FAR, Mohammedia', 'diyaa.sabri212@gmail.com', '0661234212', '0671234212', 'Sami Sabri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(213, 'INS-2024-00059', 'WL700095', 'Tobji', 'Fatine', '2002-10-18', '43 bd Mohammed V, El Jadida', 'fatine.tobji213@gmail.com', '0661234213', '0671234213', 'Ramzi Tobji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(214, 'INS-2024-00060', 'XA700096', 'Wahbi', 'Faisal', '2002-11-19', '44 rue Anfa, Settat', 'faisal.wahbi214@gmail.com', '0661234214', '0671234214', 'Walid Wahbi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(215, 'INS-2024-00061', 'XB700097', 'Yacoubi', 'Ghali', '2002-12-20', '45 av Yacoub El Mansour, Khouribga', 'ghali.yacoubi215@gmail.com', '0661234215', '0671234215', 'Yahya Yacoubi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(216, 'INS-2024-00062', 'XC700098', 'Zenati', 'Ikram', '2002-01-21', '46 rue Lalla Yacout, Benslimane', 'ikram.zenati216@gmail.com', '0661234216', '0671234216', 'Walid Zenati', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(217, 'INS-2024-00063', 'XD700099', 'Boucetta', 'Ismail', '2002-02-22', '47 bd Al Massira, Casablanca', 'ismail.boucetta217@gmail.com', '0661234217', '0671234217', 'Aymane Boucetta', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 12, '2026-06-19 19:23:10', NULL, 0.00),
(218, 'INS-2024-00064', 'XE700100', 'Sefrioui', 'Achraf', '2002-03-23', '48 av Moulay Youssef, Berrechid', 'achraf.sefrioui218@gmail.com', '0661234218', '0671234218', 'Chadi Sefrioui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(219, 'INS-2024-00065', 'XF700101', 'Hamdouni', 'Basma', '2002-04-24', '49 rue Oued Ziz, Mohammedia', 'basma.hamdouni219@gmail.com', '0661234219', '0671234219', 'Aymane Hamdouni', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(220, 'INS-2024-00066', 'XG700102', 'Rouchdi', 'Badr', '2002-05-25', '50 rue Sebou, El Jadida', 'badr.rouchdi220@gmail.com', '0661234220', '0671234220', 'Achraf Rouchdi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(221, 'INS-2024-00067', 'XH700103', 'Squalli', 'Chakib', '2002-06-26', '51 bd Moulay Abd Aziz, Settat', 'chakib.squalli221@gmail.com', '0661234221', '0671234221', 'Anas Squalli', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(222, 'INS-2024-00068', 'XI700104', 'Benkirane', 'Emna', '2002-07-27', '52 av Al Aqaba, Khouribga', 'emna.benkirane222@gmail.com', '0661234222', '0671234222', 'Achraf Benkirane', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(223, 'INS-2024-00069', 'XJ700105', 'Amrani', 'Elias', '2002-08-28', '53 rue Agadir, Benslimane', 'elias.amrani223@gmail.com', '0661234223', '0671234223', 'Chakib Amrani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(224, 'INS-2024-00070', 'XK700106', 'Benkiran', 'Faisal', '2002-09-01', '54 rue Al Massira, Casablanca', 'faisal.benkiran224@gmail.com', '0661234224', '0671234224', 'Diyaa Benkiran', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(225, 'INS-2024-00071', 'XL700107', 'Bouazza', 'Hafsa', '2002-10-02', '55 bd Zerktouni, Berrechid', 'hafsa.bouazza225@gmail.com', '0661234225', '0671234225', 'Chakib Bouazza', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(226, 'INS-2024-00072', 'YA700108', 'Guennoun', 'Hatim', '2002-11-03', '56 av Hassan II, Mohammedia', 'hatim.guennoun226@gmail.com', '0661234226', '0671234226', 'Faisal Guennoun', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(227, 'INS-2024-00073', 'YB700109', 'Hajji', 'Ismail', '2002-12-04', '57 rue Ibn Battouta, El Jadida', 'ismail.hajji227@gmail.com', '0661234227', '0671234227', 'Ghali Hajji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 13, '2026-06-19 19:23:10', NULL, 0.00),
(228, 'INS-2024-00074', 'YC700110', 'Idrissi', 'Amal', '2002-01-05', '58 av des FAR, Settat', 'amal.idrissi228@gmail.com', '0661234228', '0671234228', 'Faisal Idrissi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(229, 'INS-2024-00075', 'YD700111', 'Moussaoui', 'Anas', '2002-02-06', '59 bd Mohammed V, Khouribga', 'anas.moussaoui229@gmail.com', '0661234229', '0671234229', 'Ismail Moussaoui', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(230, 'INS-2024-00076', 'YE700112', 'Oufkir', 'Badr', '2002-03-07', '60 rue Anfa, Benslimane', 'badr.oufkir230@gmail.com', '0661234230', '0671234230', 'Jawad Oufkir', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(231, 'INS-2024-00077', 'YF700113', 'Rhazali', 'Doha', '2002-04-08', '61 av Yacoub El Mansour, Casablanca', 'doha.rhazali231@gmail.com', '0661234231', '0671234231', 'Ismail Rhazali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(232, 'INS-2024-00078', 'YG700114', 'Soussi', 'Diyaa', '2002-05-09', '62 rue Lalla Yacout, Berrechid', 'diyaa.soussi232@gmail.com', '0661234232', '0671234232', 'Lotfi Soussi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(233, 'INS-2024-00079', 'YH700115', 'Ziani', 'Elias', '2002-06-10', '63 bd Al Massira, Mohammedia', 'elias.ziani233@gmail.com', '0661234233', '0671234233', 'Mounir Ziani', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(234, 'INS-2024-00080', 'YI700116', 'Mansouri', 'Ghita', '2002-07-11', '64 av Moulay Youssef, El Jadida', 'ghita.mansouri234@gmail.com', '0661234234', '0671234234', 'Lotfi Mansouri', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(235, 'INS-2024-00081', 'YJ700117', 'Chraibi', 'Ghali', '2002-08-12', '65 rue Oued Ziz, Settat', 'ghali.chraibi235@gmail.com', '0661234235', '0671234235', 'Othmane Chraibi', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(236, 'INS-2024-00082', 'YK700118', 'Doukkali', 'Hatim', '2002-09-13', '66 rue Sebou, Khouribga', 'hatim.doukkali236@gmail.com', '0661234236', '0671234236', 'Ramzi Doukkali', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(237, 'INS-2024-00083', 'YL700119', 'Ennaji', 'Jihane', '2002-10-14', '67 bd Moulay Abd Aziz, Benslimane', 'jihane.ennaji237@gmail.com', '0661234237', '0671234237', 'Othmane Ennaji', '$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6', NULL, '2024-09-15', 14, '2026-06-19 19:23:10', NULL, 0.00),
(244, 'INS-2026-00001', 'WA124575', 'test', 'test', '2001-12-12', 'testtest', 'mehdibergame@gmail.com', '0650757944', '0682427801', 'testtest', '$2y$10$s.aK6qWoqOjjkYLxg4FBXOr0l2BC4ufxW0PRMtFLvZl9w4X2oC/Ae', NULL, '2026-06-23', 2, '2026-06-23 13:37:07', NULL, 0.00);
INSERT INTO `stagiaires` (`id_stagiaire`, `num_inscri`, `cin`, `nom`, `prenom`, `date_naissance`, `adresse`, `email`, `telephone`, `telephone_parent`, `nom_tuteur`, `mot_de_passe`, `photo`, `date_inscription`, `id_classe`, `created_at`, `updated_at`, `remise_mensuelle`) VALUES
(245, 'INS-2026-00002', 'WA123456', 'testt', 'testt', '2001-12-12', 'testttestt', 'testttestt@gmail.com', '0650757944', '0682427801', 'testt', '$2y$10$s.aK6qWoqOjjkYLxg4FBXOr0l2BC4ufxW0PRMtFLvZl9w4X2oC/Ae', NULL, '2026-06-23', 3, '2026-06-23 13:37:07', NULL, 0.00),
(246, 'INS-2026-00003', 'WA012114', 'testtt', 'testtt', '2001-02-12', '122 RUE PALESTINE', 'testtttesttt@gmail.com', '0650757944', '0682427801', 'amina', '$2y$10$s.aK6qWoqOjjkYLxg4FBXOr0l2BC4ufxW0PRMtFLvZl9w4X2oC/Ae', NULL, '2026-06-23', 6, '2026-06-23 13:37:07', NULL, 0.00);

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
(15, 105, 'classe', '1A TGI', '1A TSDI', 'aaa', '2026-06-20 18:14:15'),
(16, 105, 'filiere', 'TGI', 'TSDI', 'aaadqsd', '2026-06-20 18:14:15');

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
,`nb_controles` tinyint(3) unsigned
,`note_controle` decimal(9,6)
,`note_theorique` decimal(5,2)
,`note_pratique` decimal(5,2)
,`note_examen` decimal(10,6)
,`moyenne_module` decimal(13,6)
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
,`sexe` varchar(1)
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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_moyennes_par_module`  AS SELECT `ev`.`id_stagiaire` AS `id_stagiaire`, `ev`.`id_module` AS `id_module`, `m`.`nom_module` AS `nom_module`, `m`.`coefficient` AS `coefficient`, `m`.`nb_controles` AS `nb_controles`, avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) AS `note_controle`, max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) AS `note_theorique`, max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) AS `note_pratique`, CASE WHEN max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) is not null AND max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) is not null THEN (max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) + max(case when `ev`.`type` = 'pratique' then `ev`.`note` end)) / 2 WHEN max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) is not null THEN max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) WHEN max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) is not null THEN max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) ELSE NULL END AS `note_examen`, CASE WHEN avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) is not null AND max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) is not null AND max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) is not null THEN round(avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) * 0.40 + max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) * 0.30 + max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) * 0.30,2) WHEN avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) is not null AND (max(case when `ev`.`type` = 'theorique' then `ev`.`note` end) is not null OR max(case when `ev`.`type` = 'pratique' then `ev`.`note` end) is not null) THEN round(avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) * 0.40 + coalesce(max(case when `ev`.`type` = 'theorique' then `ev`.`note` end),0) * 0.30 + coalesce(max(case when `ev`.`type` = 'pratique' then `ev`.`note` end),0) * 0.30,2) ELSE avg(case when `ev`.`type` like 'controle%' then `ev`.`note` end) END AS `moyenne_module` FROM (`module_notes` `ev` join `modules` `m` on(`m`.`id_module` = `ev`.`id_module`)) GROUP BY `ev`.`id_stagiaire`, `ev`.`id_module`, `m`.`nom_module`, `m`.`coefficient`, `m`.`nb_controles` ;

-- --------------------------------------------------------

--
-- Structure for view `v_stagiaires_detail`
--
DROP TABLE IF EXISTS `v_stagiaires_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stagiaires_detail`  AS SELECT `s`.`id_stagiaire` AS `id_stagiaire`, `s`.`num_inscri` AS `num_inscri`, `s`.`cin` AS `cin`, `s`.`nom` AS `nom`, `s`.`prenom` AS `prenom`, `s`.`email` AS `email`, `s`.`telephone` AS `telephone`, `s`.`telephone_parent` AS `telephone_parent`, `s`.`nom_tuteur` AS `nom_tuteur`, `s`.`date_inscription` AS `date_inscription`, `s`.`date_naissance` AS `date_naissance`, `s`.`sexe` AS `sexe`, `s`.`id_classe` AS `id_classe`, `c`.`nom_classe` AS `nom_classe`, `c`.`annee_scolaire` AS `annee_scolaire`, `c`.`niveau` AS `niveau_classe`, `f`.`id_filiere` AS `id_filiere`, `f`.`nom_filiere` AS `nom_filiere`, `f`.`niveau` AS `niveau_filiere` FROM ((`stagiaires` `s` join `classes` `c` on(`c`.`id_classe` = `s`.`id_classe`)) join `filieres` `f` on(`f`.`id_filiere` = `c`.`id_filiere`)) ;

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
  ADD KEY `idx_mensualites_mois` (`mois_ref`,`est_paye`),
  ADD KEY `idx_mens_stagiaire` (`id_stagiaire`);

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
  ADD PRIMARY KEY (`id_stagiaire`,`id_module`,`type`),
  ADD KEY `idx_module_notes_module` (`id_module`);

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
  ADD UNIQUE KEY `uq_stage_per_year` (`id_stagiaire`,`type_stage`,`annee_scolaire`),
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
  MODIFY `id_absence` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id_classe` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `documents_generes`
--
ALTER TABLE `documents_generes`
  MODIFY `id_gen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=379;

--
-- AUTO_INCREMENT for table `filieres`
--
ALTER TABLE `filieres`
  MODIFY `id_filiere` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `mensualites`
--
ALTER TABLE `mensualites`
  MODIFY `id_mensualite` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2438;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id_module` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `pre_inscription`
--
ALTER TABLE `pre_inscription`
  MODIFY `id_demande` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id_stage` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `stagiaires`
--
ALTER TABLE `stagiaires`
  MODIFY `id_stagiaire` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=247;

--
-- AUTO_INCREMENT for table `stagiaire_historique`
--
ALTER TABLE `stagiaire_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  ADD CONSTRAINT `fk_module_notes_module` FOREIGN KEY (`id_module`) REFERENCES `modules` (`id_module`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_module_notes_stagiaire` FOREIGN KEY (`id_stagiaire`) REFERENCES `stagiaires` (`id_stagiaire`) ON DELETE CASCADE ON UPDATE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
