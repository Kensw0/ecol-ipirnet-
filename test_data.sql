-- ============================================================
-- test_data.sql — Données de test complètes
-- À exécuter APRÈS migration.sql (ou sur la base gestion_des_stagiaires.sql propre).
-- Couvre : stagiaires, notes, absences, mensualités, stages, pré-inscriptions
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ─────────────────────────────────────────────────────────────
-- 1. seq_inscription
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `seq_inscription` (`annee`, `last_num`) VALUES
(2024, 23),
(2025, 23),
(2026, 27);

-- ─────────────────────────────────────────────────────────────
-- 2. STAGIAIRES (IDs 72-140)
-- Mot de passe par défaut : ipirnet123
-- Hash bcrypt(ipirnet123) = $2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6
-- ─────────────────────────────────────────────────────────────

-- ── 2025/2026 ────────────────────────────────────────────────
-- Classe 1 : 1A TSDI 2025/2026
INSERT INTO `stagiaires` (`id_stagiaire`,`num_inscri`,`cin`,`nom`,`prenom`,`date_naissance`,`adresse`,`email`,`telephone`,`telephone_parent`,`nom_tuteur`,`mot_de_passe`,`date_inscription`,`id_classe`) VALUES
(72,'INS-2025-00001','AB123456','Benali','Youssef','2003-03-15','12 rue Al Massira, Casablanca','youssef.benali@gmail.com','0661234501','0661234501','Hassan Benali','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',1),
(73,'INS-2025-00002','CD234567','Alaoui','Sara','2003-07-22','45 bd Zerktouni, Casablanca','sara.alaoui@gmail.com','0661234502','0661234502','Kamal Alaoui','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',1),
(74,'INS-2025-00003','EF345678','Fassi','Hamza','2002-11-08','78 av Hassan II, Berrechid','hamza.fassi@gmail.com','0661234503','0661234503','Rachid Fassi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',1),
-- Classe 2 : 2A TSDI 2025/2026
(75,'INS-2025-00004','GH456789','Tazi','Amine','2002-05-30','23 rue Moulay Ismail, Casablanca','amine.tazi@gmail.com','0661234504','0661234504','Tarik Tazi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',2),
(76,'INS-2025-00005','IJ567890','Cherkaoui','Fatima','2001-09-14','56 av Mohammed V, Casablanca','fatima.cherkaoui@gmail.com','0661234505','0661234505','Aziz Cherkaoui','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',2),
(77,'INS-2025-00006','KL678901','Berrada','Karim','2001-12-03','90 rue Anfa, Casablanca','karim.berrada@gmail.com','0661234506','0661234506','Said Berrada','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',2),
(78,'INS-2025-00007','MN789012','Bennis','Nour','2002-02-18','34 bd Al Massira, Berrechid','nour.bennis@gmail.com','0661234507','0661234507','Omar Bennis','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',2),
-- Classe 3 : 1A TGI 2025/2026
(79,'INS-2025-00008','OP890123','Tahiri','Ibrahim','2003-06-25','15 rue Ibn Battouta, Casablanca','ibrahim.tahiri@gmail.com','0661234508','0661234508','Mustapha Tahiri','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',3),
(80,'INS-2025-00009','QR901234','Lahrech','Imane','2003-01-11','67 av Fal Ould Oumeir, Casablanca','imane.lahrech@gmail.com','0661234509','0661234509','Khalid Lahrech','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',3),
(81,'INS-2025-00010','ST012345','Belhaj','Omar','2002-08-19','89 rue Lalla Yacout, Casablanca','omar.belhaj@gmail.com','0661234510','0661234510','Abdellah Belhaj','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',3),
(82,'INS-2025-00011','UV123456','Mernissi','Zineb','2003-04-27','12 bd Emile Zola, Casablanca','zineb.mernissi@gmail.com','0661234511','0661234511','Hicham Mernissi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',3),
-- Classe 4 : 2A TGI 2025/2026
(83,'INS-2025-00012','WX234567','Kettani','Soufiane','2001-10-05','45 rue Sebou, Casablanca','soufiane.kettani@gmail.com','0661234512','0661234512','Younes Kettani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',4),
(84,'INS-2025-00013','YZ345678','Skalli','Amina','2001-03-16','78 av Lalla Meryem, Casablanca','amina.skalli@gmail.com','0661234513','0661234513','Nabil Skalli','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',4),
(85,'INS-2025-00014','AB456789','Rahmani','Ayoub','2002-07-08','56 rue Abou Inane, Berrechid','ayoub.rahmani@gmail.com','0661234514','0661234514','Rachid Rahmani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',4),
(86,'INS-2025-00015','CD567890','Ouali','Khadija','2001-11-23','23 bd Yacoub El Mansour, Casablanca','khadija.ouali@gmail.com','0661234515','0661234515','Samir Ouali','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',4),
-- Classe 5 : 1A TSGE 2025/2026
(87,'INS-2025-00016','EF678901','Bennani','Mehdi','2003-02-14','90 av des FAR, Casablanca','mehdi.bennani@gmail.com','0661234516','0661234516','Ahmed Bennani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',5),
(88,'INS-2025-00017','GH789012','Chakroun','Salma','2003-09-30','34 rue Panorama, Casablanca','salma.chakroun@gmail.com','0661234517','0661234517','Fouad Chakroun','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',5),
(89,'INS-2025-00018','IJ890123','Bensouda','Bilal','2002-12-07','15 rue Oued Ziz, Berrechid','bilal.bensouda@gmail.com','0661234518','0661234518','Mohammed Bensouda','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',5),
(90,'INS-2025-00019','KL901234','Filali','Rim','2003-05-21','67 bd Moulay Abd Aziz, Casablanca','rim.filali@gmail.com','0661234519','0661234519','Brahim Filali','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',5),
-- Classe 6 : 2A TSGE 2025/2026
(91,'INS-2025-00020','MN012345','Lahlou','Rachid','2001-08-09','89 av Al Aqaba, Casablanca','rachid.lahlou@gmail.com','0661234520','0661234520','Driss Lahlou','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',6),
(92,'INS-2025-00021','OP123456','Tazi','Yasmine','2001-04-28','12 rue Agadir, Casablanca','yasmine.tazi2@gmail.com','0661234521','0661234521','Jamal Tazi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',6),
(93,'INS-2025-00022','QR234567','Alaoui','Zakaria','2002-01-15','45 rue Tiznit, Casablanca','zakaria.alaoui2@gmail.com','0661234522','0661234522','Hamid Alaoui','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',6),
(94,'INS-2025-00023','ST345678','Fassi','Laila','2001-06-03','78 av Moulay Youssef, Berrechid','laila.fassi2@gmail.com','0661234523','0661234523','Aziz Fassi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2025-09-15',6);

-- ── 2024/2025 ────────────────────────────────────────────────
INSERT INTO `stagiaires` (`id_stagiaire`,`num_inscri`,`cin`,`nom`,`prenom`,`date_naissance`,`adresse`,`email`,`telephone`,`telephone_parent`,`nom_tuteur`,`mot_de_passe`,`date_inscription`,`id_classe`) VALUES
-- Classe 9 : 1A TSDI 2024/2025
(95,'INS-2024-00001','UV456789','Berrada','Khalid','2002-04-12','23 rue Al Massira, Casablanca','khalid.berrada2@gmail.com','0661234524','0661234524','Said Berrada','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',9),
(96,'INS-2024-00002','WX567890','Bennis','Houda','2002-10-25','56 bd Zerktouni, Casablanca','houda.bennis@gmail.com','0661234525','0661234525','Omar Bennis','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',9),
(97,'INS-2024-00003','YZ678901','Tahiri','Nassim','2003-01-18','90 av Hassan II, Berrechid','nassim.tahiri@gmail.com','0661234526','0661234526','Mustapha Tahiri','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',9),
-- Classe 10 : 2A TSDI 2024/2025
(98,'INS-2024-00004','AB789012','Lahrech','Abdellah','2001-07-06','34 rue Ibn Battouta, Casablanca','abdellah.lahrech@gmail.com','0661234527','0661234527','Khalid Lahrech','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',10),
(99,'INS-2024-00005','CD890123','Belhaj','Brahim','2001-02-14','67 av Fal Ould Oumeir, Casablanca','brahim.belhaj@gmail.com','0661234528','0661234528','Abdellah Belhaj','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',10),
(100,'INS-2024-00006','EF901234','Mernissi','Hicham','2001-11-29','89 rue Lalla Yacout, Casablanca','hicham.mernissi2@gmail.com','0661234529','0661234529','Karim Mernissi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',10),
(101,'INS-2024-00007','GH012345','Kettani','Nabil','2001-09-17','12 rue Sebou, Casablanca','nabil.kettani@gmail.com','0661234530','0661234530','Younes Kettani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',10),
-- Classe 11 : 1A TGI 2024/2025
(102,'INS-2024-00008','IJ123456','Skalli','Tarek','2002-05-08','45 av Lalla Meryem, Casablanca','tarek.skalli@gmail.com','0661234531','0661234531','Nabil Skalli','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',11),
(103,'INS-2024-00009','KL234567','Rahmani','Mohammed','2003-08-21','78 rue Abou Inane, Berrechid','mohammed.rahmani@gmail.com','0661234532','0661234532','Rachid Rahmani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',11),
(104,'INS-2024-00010','MN345678','Ouali','Aicha','2002-03-15','56 bd Yacoub El Mansour, Casablanca','aicha.ouali@gmail.com','0661234533','0661234533','Samir Ouali','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',11),
(105,'INS-2024-00011','OP456789','Bennani','Youssef','2002-12-04','23 av des FAR, Casablanca','youssef.bennani@gmail.com','0661234534','0661234534','Ahmed Bennani','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',11),
-- Classe 12 : 2A TGI 2024/2025
(106,'INS-2024-00012','QR567890','Chakroun','Sara','2001-06-18','34 rue Panorama, Casablanca','sara.chakroun@gmail.com','0661234535','0661234535','Fouad Chakroun','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',12),
(107,'INS-2024-00013','ST678901','Bensouda','Hamza','2001-10-09','15 rue Oued Ziz, Berrechid','hamza.bensouda@gmail.com','0661234536','0661234536','Mohammed Bensouda','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',12),
(108,'INS-2024-00014','UV789012','Filali','Amine','2001-04-26','67 bd Moulay Abd Aziz, Casablanca','amine.filali@gmail.com','0661234537','0661234537','Brahim Filali','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',12),
(109,'INS-2024-00015','WX890123','Lahlou','Fatima','2001-01-13','89 av Al Aqaba, Casablanca','fatima.lahlou@gmail.com','0661234538','0661234538','Driss Lahlou','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',12),
-- Classe 13 : 1A TSGE 2024/2025
(110,'INS-2024-00016','YZ901234','Tazi','Karim','2002-09-02','12 rue Agadir, Casablanca','karim.tazi2@gmail.com','0661234539','0661234539','Jamal Tazi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',13),
(111,'INS-2024-00017','AB012345','Alaoui','Nour','2002-06-24','45 rue Tiznit, Casablanca','nour.alaoui@gmail.com','0661234540','0661234540','Hamid Alaoui','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',13),
(112,'INS-2024-00018','CD123456','Fassi','Ibrahim','2002-02-11','78 av Moulay Youssef, Berrechid','ibrahim.fassi@gmail.com','0661234541','0661234541','Rachid Fassi','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',13),
(113,'INS-2024-00019','EF234567','Berrada','Imane','2002-11-30','56 bd Hassan II, Casablanca','imane.berrada@gmail.com','0661234542','0661234542','Said Berrada','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',13),
-- Classe 14 : 2A TSGE 2024/2025
(114,'INS-2024-00020','GH345678','Bennis','Omar','2001-07-17','23 rue Al Massira, Casablanca','omar.bennis2@gmail.com','0661234543','0661234543','Driss Bennis','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',14),
(115,'INS-2024-00021','IJ456789','Tahiri','Zineb','2001-03-05','90 bd Zerktouni, Casablanca','zineb.tahiri@gmail.com','0661234544','0661234544','Mustapha Tahiri','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',14),
(116,'INS-2024-00022','KL567890','Lahrech','Soufiane','2001-12-22','34 av Hassan II, Berrechid','soufiane.lahrech@gmail.com','0661234545','0661234545','Khalid Lahrech','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',14),
(117,'INS-2024-00023','MN678901','Belhaj','Amina','2001-08-10','15 rue Ibn Battouta, Casablanca','amina.belhaj@gmail.com','0661234546','0661234546','Abdellah Belhaj','$2y$10$4k1u.fa/fOJXJ1QmmOzBaOS3Rhv9kKqeiRuPS.0APmUSmBG5p52W6','2024-09-15',14);


-- ─────────────────────────────────────────────────────────────
-- 3. NOTES (module_notes — nouvelle structure)
-- Structure : (id_stagiaire, id_module, note, type)
-- Types : controle_1, theorique, pratique
-- ─────────────────────────────────────────────────────────────

-- Existing students: add more modules for 68 and 71 (class 1 - 1A TSDI)
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(68,3,13.00,'controle_1'),(68,3,11.00,'theorique'),(68,3,14.00,'pratique'),
(68,4,15.00,'controle_1'),(68,4,13.00,'theorique'),(68,4,17.00,'pratique'),
(71,3,14.00,'controle_1'),(71,3,16.00,'theorique'),(71,3,12.00,'pratique'),
(71,4,13.00,'controle_1'),(71,4,15.00,'theorique'),(71,4,16.00,'pratique');

-- ── Classe 1 (1A TSDI 2025/2026) — modules TSDI 1A: 2,3,4 ────────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(72,2,15.00,'controle_1'),(72,2,14.00,'theorique'),(72,2,16.00,'pratique'),
(72,3,12.00,'controle_1'),(72,3,13.00,'theorique'),(72,3,11.00,'pratique'),
(72,4,17.00,'controle_1'),(72,4,15.00,'theorique'),(72,4,18.00,'pratique'),
(73,2,11.00,'controle_1'),(73,2,10.00,'theorique'),(73,2,12.00,'pratique'),
(73,3,14.00,'controle_1'),(73,3,15.00,'theorique'),(73,3,13.00,'pratique'),
(73,4,10.00,'controle_1'),(73,4,11.00,'theorique'),(73,4, 9.00,'pratique'),
(74,2,18.00,'controle_1'),(74,2,17.00,'theorique'),(74,2,19.00,'pratique'),
(74,3,16.00,'controle_1'),(74,3,15.00,'theorique'),(74,3,17.00,'pratique'),
(74,4,14.00,'controle_1'),(74,4,16.00,'theorique'),(74,4,15.00,'pratique');

-- ── Classe 2 (2A TSDI 2025/2026) — modules TSDI 2A: 25,26,27,28 ──────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(75,25,14.00,'controle_1'),(75,25,13.00,'theorique'),(75,25,15.00,'pratique'),
(75,26,16.00,'controle_1'),(75,26,14.00,'theorique'),(75,26,17.00,'pratique'),
(75,27,12.00,'controle_1'),(75,27,13.00,'theorique'),(75,27,11.00,'pratique'),
(75,28,15.00,'controle_1'),(75,28,16.00,'theorique'),(75,28,14.00,'pratique'),
(76,25,17.00,'controle_1'),(76,25,16.00,'theorique'),(76,25,18.00,'pratique'),
(76,26,15.00,'controle_1'),(76,26,17.00,'theorique'),(76,26,16.00,'pratique'),
(76,27,18.00,'controle_1'),(76,27,17.00,'theorique'),(76,27,19.00,'pratique'),
(76,28,16.00,'controle_1'),(76,28,15.00,'theorique'),(76,28,17.00,'pratique'),
(77,25, 9.00,'controle_1'),(77,25,10.00,'theorique'),(77,25, 8.00,'pratique'),
(77,26,11.00,'controle_1'),(77,26,10.00,'theorique'),(77,26,12.00,'pratique'),
(77,27,10.00,'controle_1'),(77,27, 9.00,'theorique'),(77,27,11.00,'pratique'),
(77,28, 8.00,'controle_1'),(77,28,10.00,'theorique'),(77,28, 9.00,'pratique'),
(78,25,13.00,'controle_1'),(78,25,12.00,'theorique'),(78,25,14.00,'pratique'),
(78,26,14.00,'controle_1'),(78,26,13.00,'theorique'),(78,26,15.00,'pratique'),
(78,27,11.00,'controle_1'),(78,27,12.00,'theorique'),(78,27,10.00,'pratique'),
(78,28,12.00,'controle_1'),(78,28,11.00,'theorique'),(78,28,13.00,'pratique');

-- ── Classe 3 (1A TGI 2025/2026) — modules TGI: 40,41,42,43,44 ────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(79,40,13.00,'controle_1'),(79,40,12.00,'theorique'),(79,40,14.00,'pratique'),
(79,41,15.00,'controle_1'),(79,41,14.00,'theorique'),(79,41,16.00,'pratique'),
(79,42,11.00,'controle_1'),(79,42,12.00,'theorique'),(79,42,10.00,'pratique'),
(79,43,14.00,'controle_1'),(79,43,13.00,'theorique'),(79,43,15.00,'pratique'),
(79,44,12.00,'controle_1'),(79,44,11.00,'theorique'),(79,44,13.00,'pratique'),
(80,40,16.00,'controle_1'),(80,40,15.00,'theorique'),(80,40,17.00,'pratique'),
(80,41,14.00,'controle_1'),(80,41,15.00,'theorique'),(80,41,13.00,'pratique'),
(80,42,18.00,'controle_1'),(80,42,17.00,'theorique'),(80,42,19.00,'pratique'),
(80,43,15.00,'controle_1'),(80,43,16.00,'theorique'),(80,43,14.00,'pratique'),
(80,44,17.00,'controle_1'),(80,44,16.00,'theorique'),(80,44,18.00,'pratique'),
(81,40, 9.00,'controle_1'),(81,40,10.00,'theorique'),(81,40, 8.00,'pratique'),
(81,41,11.00,'controle_1'),(81,41,10.00,'theorique'),(81,41,12.00,'pratique'),
(81,42,10.00,'controle_1'),(81,42, 9.00,'theorique'),(81,42,11.00,'pratique'),
(81,43, 8.00,'controle_1'),(81,43, 9.00,'theorique'),(81,43, 7.00,'pratique'),
(81,44,10.00,'controle_1'),(81,44,11.00,'theorique'),(81,44, 9.00,'pratique'),
(82,40,14.00,'controle_1'),(82,40,13.00,'theorique'),(82,40,15.00,'pratique'),
(82,41,12.00,'controle_1'),(82,41,13.00,'theorique'),(82,41,11.00,'pratique'),
(82,42,16.00,'controle_1'),(82,42,15.00,'theorique'),(82,42,17.00,'pratique'),
(82,43,13.00,'controle_1'),(82,43,14.00,'theorique'),(82,43,12.00,'pratique'),
(82,44,15.00,'controle_1'),(82,44,14.00,'theorique'),(82,44,16.00,'pratique');

-- ── Classe 4 (2A TGI 2025/2026) — modules TGI: 40,41,42,43,44 ────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(83,40,15.00,'controle_1'),(83,40,14.00,'theorique'),(83,40,16.00,'pratique'),
(83,41,13.00,'controle_1'),(83,41,14.00,'theorique'),(83,41,12.00,'pratique'),
(83,42,17.00,'controle_1'),(83,42,16.00,'theorique'),(83,42,18.00,'pratique'),
(83,43,14.00,'controle_1'),(83,43,15.00,'theorique'),(83,43,13.00,'pratique'),
(83,44,16.00,'controle_1'),(83,44,15.00,'theorique'),(83,44,17.00,'pratique'),
(84,40,18.00,'controle_1'),(84,40,17.00,'theorique'),(84,40,19.00,'pratique'),
(84,41,16.00,'controle_1'),(84,41,17.00,'theorique'),(84,41,15.00,'pratique'),
(84,42,15.00,'controle_1'),(84,42,16.00,'theorique'),(84,42,14.00,'pratique'),
(84,43,17.00,'controle_1'),(84,43,16.00,'theorique'),(84,43,18.00,'pratique'),
(84,44,14.00,'controle_1'),(84,44,15.00,'theorique'),(84,44,13.00,'pratique'),
(85,40,10.00,'controle_1'),(85,40,11.00,'theorique'),(85,40, 9.00,'pratique'),
(85,41,12.00,'controle_1'),(85,41,11.00,'theorique'),(85,41,13.00,'pratique'),
(85,42, 9.00,'controle_1'),(85,42,10.00,'theorique'),(85,42, 8.00,'pratique'),
(85,43,11.00,'controle_1'),(85,43,10.00,'theorique'),(85,43,12.00,'pratique'),
(85,44,10.00,'controle_1'),(85,44, 9.00,'theorique'),(85,44,11.00,'pratique'),
(86,40,13.00,'controle_1'),(86,40,12.00,'theorique'),(86,40,14.00,'pratique'),
(86,41,15.00,'controle_1'),(86,41,14.00,'theorique'),(86,41,16.00,'pratique'),
(86,42,12.00,'controle_1'),(86,42,13.00,'theorique'),(86,42,11.00,'pratique'),
(86,43,14.00,'controle_1'),(86,43,13.00,'theorique'),(86,43,15.00,'pratique'),
(86,44,11.00,'controle_1'),(86,44,12.00,'theorique'),(86,44,10.00,'pratique');

-- ── Classe 5 (1A TSGE 2025/2026) — modules TSGE: 33,34,35,36,37 ──────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(87,33,14.00,'controle_1'),(87,33,13.00,'theorique'),(87,33,15.00,'pratique'),
(87,34,12.00,'controle_1'),(87,34,13.00,'theorique'),(87,34,11.00,'pratique'),
(87,35,16.00,'controle_1'),(87,35,15.00,'theorique'),(87,35,17.00,'pratique'),
(87,36,13.00,'controle_1'),(87,36,14.00,'theorique'),(87,36,12.00,'pratique'),
(87,37,15.00,'controle_1'),(87,37,14.00,'theorique'),(87,37,16.00,'pratique'),
(88,33,17.00,'controle_1'),(88,33,16.00,'theorique'),(88,33,18.00,'pratique'),
(88,34,15.00,'controle_1'),(88,34,16.00,'theorique'),(88,34,14.00,'pratique'),
(88,35,18.00,'controle_1'),(88,35,17.00,'theorique'),(88,35,19.00,'pratique'),
(88,36,16.00,'controle_1'),(88,36,15.00,'theorique'),(88,36,17.00,'pratique'),
(88,37,14.00,'controle_1'),(88,37,15.00,'theorique'),(88,37,13.00,'pratique'),
(89,33, 9.00,'controle_1'),(89,33,10.00,'theorique'),(89,33, 8.00,'pratique'),
(89,34,11.00,'controle_1'),(89,34,10.00,'theorique'),(89,34,12.00,'pratique'),
(89,35,10.00,'controle_1'),(89,35, 9.00,'theorique'),(89,35,11.00,'pratique'),
(89,36, 8.00,'controle_1'),(89,36, 9.00,'theorique'),(89,36, 7.00,'pratique'),
(89,37,10.00,'controle_1'),(89,37,11.00,'theorique'),(89,37, 9.00,'pratique'),
(90,33,13.00,'controle_1'),(90,33,12.00,'theorique'),(90,33,14.00,'pratique'),
(90,34,15.00,'controle_1'),(90,34,14.00,'theorique'),(90,34,16.00,'pratique'),
(90,35,12.00,'controle_1'),(90,35,13.00,'theorique'),(90,35,11.00,'pratique'),
(90,36,14.00,'controle_1'),(90,36,13.00,'theorique'),(90,36,15.00,'pratique'),
(90,37,11.00,'controle_1'),(90,37,12.00,'theorique'),(90,37,10.00,'pratique');

-- ── Classe 6 (2A TSGE 2025/2026) — modules TSGE: 33,34,35,36,37,38,39 ────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(91,33,15.00,'controle_1'),(91,33,14.00,'theorique'),(91,33,16.00,'pratique'),
(91,34,13.00,'controle_1'),(91,34,14.00,'theorique'),(91,34,12.00,'pratique'),
(91,35,17.00,'controle_1'),(91,35,16.00,'theorique'),(91,35,18.00,'pratique'),
(91,36,14.00,'controle_1'),(91,36,15.00,'theorique'),(91,36,13.00,'pratique'),
(91,37,16.00,'controle_1'),(91,37,15.00,'theorique'),(91,37,17.00,'pratique'),
(92,33,18.00,'controle_1'),(92,33,17.00,'theorique'),(92,33,19.00,'pratique'),
(92,34,16.00,'controle_1'),(92,34,17.00,'theorique'),(92,34,15.00,'pratique'),
(92,35,15.00,'controle_1'),(92,35,16.00,'theorique'),(92,35,14.00,'pratique'),
(92,36,17.00,'controle_1'),(92,36,16.00,'theorique'),(92,36,18.00,'pratique'),
(92,37,14.00,'controle_1'),(92,37,15.00,'theorique'),(92,37,13.00,'pratique'),
(93,33,11.00,'controle_1'),(93,33,10.00,'theorique'),(93,33,12.00,'pratique'),
(93,34,13.00,'controle_1'),(93,34,12.00,'theorique'),(93,34,14.00,'pratique'),
(93,35,10.00,'controle_1'),(93,35,11.00,'theorique'),(93,35, 9.00,'pratique'),
(93,36,12.00,'controle_1'),(93,36,11.00,'theorique'),(93,36,13.00,'pratique'),
(93,37,14.00,'controle_1'),(93,37,13.00,'theorique'),(93,37,15.00,'pratique'),
(94,33,14.00,'controle_1'),(94,33,13.00,'theorique'),(94,33,15.00,'pratique'),
(94,34,12.00,'controle_1'),(94,34,13.00,'theorique'),(94,34,11.00,'pratique'),
(94,35,16.00,'controle_1'),(94,35,15.00,'theorique'),(94,35,17.00,'pratique'),
(94,36,13.00,'controle_1'),(94,36,14.00,'theorique'),(94,36,12.00,'pratique'),
(94,37,15.00,'controle_1'),(94,37,14.00,'theorique'),(94,37,16.00,'pratique');

-- ── Classe 9 (1A TSDI 2024/2025) — modules TSDI 1A: 2,3,4 ────────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(95,2,16.00,'controle_1'),(95,2,15.00,'theorique'),(95,2,17.00,'pratique'),
(95,3,14.00,'controle_1'),(95,3,13.00,'theorique'),(95,3,15.00,'pratique'),
(95,4,12.00,'controle_1'),(95,4,13.00,'theorique'),(95,4,11.00,'pratique'),
(96,2,10.00,'controle_1'),(96,2,11.00,'theorique'),(96,2, 9.00,'pratique'),
(96,3,13.00,'controle_1'),(96,3,12.00,'theorique'),(96,3,14.00,'pratique'),
(96,4,11.00,'controle_1'),(96,4,10.00,'theorique'),(96,4,12.00,'pratique'),
(97,2,18.00,'controle_1'),(97,2,17.00,'theorique'),(97,2,19.00,'pratique'),
(97,3,16.00,'controle_1'),(97,3,15.00,'theorique'),(97,3,17.00,'pratique'),
(97,4,15.00,'controle_1'),(97,4,16.00,'theorique'),(97,4,14.00,'pratique');

-- ── Classe 10 (2A TSDI 2024/2025) — modules 25,26,27,28 ─────────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(98,25,15.00,'controle_1'),(98,25,14.00,'theorique'),(98,25,16.00,'pratique'),
(98,26,13.00,'controle_1'),(98,26,14.00,'theorique'),(98,26,12.00,'pratique'),
(98,27,17.00,'controle_1'),(98,27,16.00,'theorique'),(98,27,18.00,'pratique'),
(98,28,14.00,'controle_1'),(98,28,15.00,'theorique'),(98,28,13.00,'pratique'),
(99,25,11.00,'controle_1'),(99,25,10.00,'theorique'),(99,25,12.00,'pratique'),
(99,26, 9.00,'controle_1'),(99,26,10.00,'theorique'),(99,26, 8.00,'pratique'),
(99,27,12.00,'controle_1'),(99,27,11.00,'theorique'),(99,27,13.00,'pratique'),
(99,28,10.00,'controle_1'),(99,28,11.00,'theorique'),(99,28, 9.00,'pratique'),
(100,25,16.00,'controle_1'),(100,25,15.00,'theorique'),(100,25,17.00,'pratique'),
(100,26,14.00,'controle_1'),(100,26,15.00,'theorique'),(100,26,13.00,'pratique'),
(100,27,18.00,'controle_1'),(100,27,17.00,'theorique'),(100,27,19.00,'pratique'),
(100,28,15.00,'controle_1'),(100,28,16.00,'theorique'),(100,28,14.00,'pratique'),
(101,25,13.00,'controle_1'),(101,25,12.00,'theorique'),(101,25,14.00,'pratique'),
(101,26,15.00,'controle_1'),(101,26,14.00,'theorique'),(101,26,16.00,'pratique'),
(101,27,11.00,'controle_1'),(101,27,12.00,'theorique'),(101,27,10.00,'pratique'),
(101,28,14.00,'controle_1'),(101,28,13.00,'theorique'),(101,28,15.00,'pratique');

-- ── Classe 11 (1A TGI 2024/2025) — modules 40,41,42,43,44 ───────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(102,40,14.00,'controle_1'),(102,40,13.00,'theorique'),(102,40,15.00,'pratique'),
(102,41,16.00,'controle_1'),(102,41,15.00,'theorique'),(102,41,17.00,'pratique'),
(102,42,12.00,'controle_1'),(102,42,13.00,'theorique'),(102,42,11.00,'pratique'),
(102,43,15.00,'controle_1'),(102,43,14.00,'theorique'),(102,43,16.00,'pratique'),
(102,44,13.00,'controle_1'),(102,44,12.00,'theorique'),(102,44,14.00,'pratique'),
(103,40,17.00,'controle_1'),(103,40,16.00,'theorique'),(103,40,18.00,'pratique'),
(103,41,15.00,'controle_1'),(103,41,16.00,'theorique'),(103,41,14.00,'pratique'),
(103,42,19.00,'controle_1'),(103,42,18.00,'theorique'),(103,42,20.00,'pratique'),
(103,43,16.00,'controle_1'),(103,43,17.00,'theorique'),(103,43,15.00,'pratique'),
(103,44,18.00,'controle_1'),(103,44,17.00,'theorique'),(103,44,19.00,'pratique'),
(104,40, 8.00,'controle_1'),(104,40, 9.00,'theorique'),(104,40, 7.00,'pratique'),
(104,41,10.00,'controle_1'),(104,41, 9.00,'theorique'),(104,41,11.00,'pratique'),
(104,42, 9.00,'controle_1'),(104,42, 8.00,'theorique'),(104,42,10.00,'pratique'),
(104,43, 7.00,'controle_1'),(104,43, 8.00,'theorique'),(104,43, 6.00,'pratique'),
(104,44, 9.00,'controle_1'),(104,44,10.00,'theorique'),(104,44, 8.00,'pratique'),
(105,40,13.00,'controle_1'),(105,40,12.00,'theorique'),(105,40,14.00,'pratique'),
(105,41,11.00,'controle_1'),(105,41,12.00,'theorique'),(105,41,10.00,'pratique'),
(105,42,15.00,'controle_1'),(105,42,14.00,'theorique'),(105,42,16.00,'pratique'),
(105,43,12.00,'controle_1'),(105,43,13.00,'theorique'),(105,43,11.00,'pratique'),
(105,44,14.00,'controle_1'),(105,44,13.00,'theorique'),(105,44,15.00,'pratique');

-- ── Classe 12 (2A TGI 2024/2025) — modules 40,41,42,43,44 ───────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(106,40,15.00,'controle_1'),(106,40,14.00,'theorique'),(106,40,16.00,'pratique'),
(106,41,13.00,'controle_1'),(106,41,14.00,'theorique'),(106,41,12.00,'pratique'),
(106,42,17.00,'controle_1'),(106,42,16.00,'theorique'),(106,42,18.00,'pratique'),
(106,43,14.00,'controle_1'),(106,43,15.00,'theorique'),(106,43,13.00,'pratique'),
(106,44,16.00,'controle_1'),(106,44,15.00,'theorique'),(106,44,17.00,'pratique'),
(107,40,12.00,'controle_1'),(107,40,11.00,'theorique'),(107,40,13.00,'pratique'),
(107,41,10.00,'controle_1'),(107,41,11.00,'theorique'),(107,41, 9.00,'pratique'),
(107,42,14.00,'controle_1'),(107,42,13.00,'theorique'),(107,42,15.00,'pratique'),
(107,43,11.00,'controle_1'),(107,43,12.00,'theorique'),(107,43,10.00,'pratique'),
(107,44,13.00,'controle_1'),(107,44,12.00,'theorique'),(107,44,14.00,'pratique'),
(108,40,18.00,'controle_1'),(108,40,17.00,'theorique'),(108,40,19.00,'pratique'),
(108,41,16.00,'controle_1'),(108,41,17.00,'theorique'),(108,41,15.00,'pratique'),
(108,42,15.00,'controle_1'),(108,42,16.00,'theorique'),(108,42,14.00,'pratique'),
(108,43,17.00,'controle_1'),(108,43,16.00,'theorique'),(108,43,18.00,'pratique'),
(108,44,14.00,'controle_1'),(108,44,15.00,'theorique'),(108,44,13.00,'pratique'),
(109,40, 9.00,'controle_1'),(109,40,10.00,'theorique'),(109,40, 8.00,'pratique'),
(109,41,11.00,'controle_1'),(109,41,10.00,'theorique'),(109,41,12.00,'pratique'),
(109,42,10.00,'controle_1'),(109,42, 9.00,'theorique'),(109,42,11.00,'pratique'),
(109,43, 8.00,'controle_1'),(109,43, 9.00,'theorique'),(109,43, 7.00,'pratique'),
(109,44,10.00,'controle_1'),(109,44,11.00,'theorique'),(109,44, 9.00,'pratique');

-- ── Classes 13,14 (TSGE 2024/2025) — modules 33,34,35,36,37 ────────────
INSERT IGNORE INTO `module_notes` (`id_stagiaire`,`id_module`,`note`,`type`) VALUES
(110,33,13.00,'controle_1'),(110,33,12.00,'theorique'),(110,33,14.00,'pratique'),
(110,34,15.00,'controle_1'),(110,34,14.00,'theorique'),(110,34,16.00,'pratique'),
(110,35,11.00,'controle_1'),(110,35,12.00,'theorique'),(110,35,10.00,'pratique'),
(111,33,16.00,'controle_1'),(111,33,15.00,'theorique'),(111,33,17.00,'pratique'),
(111,34,14.00,'controle_1'),(111,34,15.00,'theorique'),(111,34,13.00,'pratique'),
(111,35,18.00,'controle_1'),(111,35,17.00,'theorique'),(111,35,19.00,'pratique'),
(112,33, 9.00,'controle_1'),(112,33,10.00,'theorique'),(112,33, 8.00,'pratique'),
(112,34,11.00,'controle_1'),(112,34,10.00,'theorique'),(112,34,12.00,'pratique'),
(112,35,10.00,'controle_1'),(112,35, 9.00,'theorique'),(112,35,11.00,'pratique'),
(113,33,14.00,'controle_1'),(113,33,13.00,'theorique'),(113,33,15.00,'pratique'),
(113,34,12.00,'controle_1'),(113,34,13.00,'theorique'),(113,34,11.00,'pratique'),
(113,35,16.00,'controle_1'),(113,35,15.00,'theorique'),(113,35,17.00,'pratique'),
(114,33,15.00,'controle_1'),(114,33,14.00,'theorique'),(114,33,16.00,'pratique'),
(114,34,17.00,'controle_1'),(114,34,16.00,'theorique'),(114,34,18.00,'pratique'),
(114,35,13.00,'controle_1'),(114,35,14.00,'theorique'),(114,35,12.00,'pratique'),
(115,33,12.00,'controle_1'),(115,33,11.00,'theorique'),(115,33,13.00,'pratique'),
(115,34,14.00,'controle_1'),(115,34,13.00,'theorique'),(115,34,15.00,'pratique'),
(115,35,10.00,'controle_1'),(115,35,11.00,'theorique'),(115,35, 9.00,'pratique'),
(116,33,18.00,'controle_1'),(116,33,17.00,'theorique'),(116,33,19.00,'pratique'),
(116,34,16.00,'controle_1'),(116,34,17.00,'theorique'),(116,34,15.00,'pratique'),
(116,35,15.00,'controle_1'),(116,35,16.00,'theorique'),(116,35,14.00,'pratique'),
(117,33, 9.00,'controle_1'),(117,33,10.00,'theorique'),(117,33, 8.00,'pratique'),
(117,34,11.00,'controle_1'),(117,34,10.00,'theorique'),(117,34,12.00,'pratique'),
(117,35,10.00,'controle_1'),(117,35, 9.00,'theorique'),(117,35,11.00,'pratique');


-- ─────────────────────────────────────────────────────────────
-- 4. ABSENCES (classes 2025/2026 principalement)
-- ─────────────────────────────────────────────────────────────
INSERT INTO `absences` (`date_absence`,`heure_debut`,`heure_fin`,`justificatif`,`est_justifiee`,`id_stagiaire`,`id_module`) VALUES
('2025-10-05','08:00:00','10:00:00','Certificat médical',1,72,2),
('2025-11-12','14:00:00','16:00:00',NULL,0,72,4),
('2025-10-08','08:00:00','10:00:00','Transport annulé',1,73,3),
('2025-12-03','10:00:00','12:00:00',NULL,0,74,2),
('2025-11-17','08:00:00','12:00:00','Urgence familiale',1,75,25),
('2025-10-22','14:00:00','16:00:00',NULL,0,75,26),
('2025-12-10','08:00:00','10:00:00','Certificat médical',1,76,27),
('2026-01-14','10:00:00','12:00:00',NULL,0,77,28),
('2025-10-15','08:00:00','10:00:00',NULL,0,79,40),
('2025-11-20','14:00:00','16:00:00','Rendez-vous médical',1,80,42),
('2025-12-08','08:00:00','12:00:00',NULL,0,81,41),
('2026-01-19','10:00:00','12:00:00','Certificat médical',1,82,43),
('2025-10-28','08:00:00','10:00:00',NULL,0,83,40),
('2025-11-25','14:00:00','16:00:00','Transport annulé',1,84,42),
('2025-10-09','08:00:00','10:00:00',NULL,0,87,33),
('2025-11-13','14:00:00','16:00:00','Urgence familiale',1,88,35),
('2025-12-17','08:00:00','12:00:00',NULL,0,89,34),
('2026-01-21','10:00:00','12:00:00','Certificat médical',1,90,37),
('2025-10-30','08:00:00','10:00:00',NULL,0,91,33),
('2025-11-27','14:00:00','16:00:00',NULL,0,92,35),
('2025-10-16','08:00:00','10:00:00','Certificat médical',1,68,2),
('2025-11-23','14:00:00','16:00:00',NULL,0,71,4);

-- ─────────────────────────────────────────────────────────────
-- 5. MENSUALITÉS (2025/2026 — Sep 2025 à Jun 2026, 700 MAD/mois)
-- Quelques partiels pour réalisme
-- ─────────────────────────────────────────────────────────────
INSERT INTO `mensualites` (`id_stagiaire`,`mois_ref`,`est_paye`,`montant_total`,`montant_paye`,`montant_restant`,`cumul_restant`,`statut_paiement`,`date_paiement`) VALUES
-- Youssef Benali (72) — tout payé
(72,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-20 00:00:00'),
(72,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-18 00:00:00'),
(72,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-15 00:00:00'),
(72,'2025-12',1,700.00,700.00,0.00,0.00,'payé','2025-12-12 00:00:00'),
(72,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-16 00:00:00'),
(72,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-14 00:00:00'),
(72,'2026-03',1,700.00,700.00,0.00,0.00,'payé','2026-03-20 00:00:00'),
(72,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-17 00:00:00'),
(72,'2026-05',1,700.00,700.00,0.00,0.00,'payé','2026-05-15 00:00:00'),
(72,'2026-06',1,700.00,700.00,0.00,0.00,'payé','2026-06-12 00:00:00'),
-- Sara Alaoui (73) — partiel en décembre, impayé en janvier
(73,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-22 00:00:00'),
(73,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-20 00:00:00'),
(73,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-18 00:00:00'),
(73,'2025-12',0,700.00,400.00,300.00,300.00,'partiel','2025-12-20 00:00:00'),
(73,'2026-01',0,700.00,0.00,700.00,1000.00,'impayé',NULL),
(73,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-18 00:00:00'),
(73,'2026-03',1,700.00,700.00,0.00,0.00,'payé','2026-03-22 00:00:00'),
(73,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-19 00:00:00'),
(73,'2026-05',1,700.00,700.00,0.00,0.00,'payé','2026-05-18 00:00:00'),
(73,'2026-06',1,700.00,700.00,0.00,0.00,'payé','2026-06-14 00:00:00'),
-- Hamza Fassi (74) — tout payé
(74,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-18 00:00:00'),
(74,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-16 00:00:00'),
(74,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-13 00:00:00'),
(74,'2025-12',1,700.00,700.00,0.00,0.00,'payé','2025-12-11 00:00:00'),
(74,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-14 00:00:00'),
(74,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-12 00:00:00'),
(74,'2026-03',1,700.00,700.00,0.00,0.00,'payé','2026-03-18 00:00:00'),
(74,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-15 00:00:00'),
(74,'2026-05',1,700.00,700.00,0.00,0.00,'payé','2026-05-14 00:00:00'),
(74,'2026-06',1,700.00,700.00,0.00,0.00,'payé','2026-06-11 00:00:00'),
-- Amine Tazi (75) — 2A TSDI
(75,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-21 00:00:00'),
(75,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-19 00:00:00'),
(75,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-16 00:00:00'),
(75,'2025-12',1,700.00,700.00,0.00,0.00,'payé','2025-12-14 00:00:00'),
(75,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-18 00:00:00'),
(75,'2026-02',0,700.00,350.00,350.00,350.00,'partiel','2026-02-16 00:00:00'),
(75,'2026-03',0,700.00,0.00,700.00,1050.00,'impayé',NULL),
(75,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-21 00:00:00'),
(75,'2026-05',1,700.00,700.00,0.00,0.00,'payé','2026-05-16 00:00:00'),
(75,'2026-06',1,700.00,700.00,0.00,0.00,'payé','2026-06-13 00:00:00'),
-- Mehdi Bennani (87) — 1A TSGE
(87,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-23 00:00:00'),
(87,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-21 00:00:00'),
(87,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-19 00:00:00'),
(87,'2025-12',1,700.00,700.00,0.00,0.00,'payé','2025-12-17 00:00:00'),
(87,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-20 00:00:00'),
(87,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-17 00:00:00'),
(87,'2026-03',1,700.00,700.00,0.00,0.00,'payé','2026-03-19 00:00:00'),
(87,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-16 00:00:00'),
(87,'2026-05',0,700.00,0.00,700.00,700.00,'impayé',NULL),
(87,'2026-06',0,700.00,0.00,700.00,1400.00,'impayé',NULL),
-- Existing: extra months for 68 and 71
(68,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-19 00:00:00'),
(68,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-17 00:00:00'),
(68,'2025-11',1,700.00,700.00,0.00,0.00,'payé','2025-11-14 00:00:00'),
(68,'2025-12',1,700.00,700.00,0.00,0.00,'payé','2025-12-12 00:00:00'),
(68,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-15 00:00:00'),
(68,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-13 00:00:00'),
(68,'2026-03',1,700.00,700.00,0.00,0.00,'payé','2026-03-17 00:00:00'),
(68,'2026-04',1,700.00,700.00,0.00,0.00,'payé','2026-04-14 00:00:00'),
(68,'2026-05',1,700.00,700.00,0.00,0.00,'payé','2026-05-12 00:00:00'),
(71,'2025-09',1,700.00,700.00,0.00,0.00,'payé','2025-09-20 00:00:00'),
(71,'2025-10',1,700.00,700.00,0.00,0.00,'payé','2025-10-18 00:00:00'),
(71,'2025-11',0,700.00,500.00,200.00,200.00,'partiel','2025-11-17 00:00:00'),
(71,'2025-12',0,700.00,0.00,700.00,900.00,'impayé',NULL),
(71,'2026-01',1,700.00,700.00,0.00,0.00,'payé','2026-01-17 00:00:00'),
(71,'2026-02',1,700.00,700.00,0.00,0.00,'payé','2026-02-15 00:00:00');

-- ─────────────────────────────────────────────────────────────
-- 6. STAGES
-- 1ère année: 1 stage_entreprise
-- 2ème année: 1 stage_entreprise + 1 PFE
-- ─────────────────────────────────────────────────────────────
INSERT INTO `stages` (`type_stage`,`sujet`,`entreprise`,`date_debut`,`date_fin`,`note_stage`,`evaluation_entreprise`,`id_stagiaire`) VALUES
-- 2A TSDI 2025/2026
('stage_entreprise','Développement d\'une application web de gestion des stocks','TechMaroc SARL','2026-06-01','2026-07-31',15.00,'Très bonne maîtrise des outils web',75),
('pfe','Conception d\'un système de gestion documentaire avec Laravel','TechMaroc SARL','2026-06-01','2026-07-31',16.00,'Excellent travail, livrable de qualité',75),
('stage_entreprise','Mise en place d\'un réseau local sécurisé','Infocom Maroc','2026-06-01','2026-07-31',14.00,'Bonne initiative et autonomie',76),
('pfe','Développement d\'une API REST pour un système de pointage','Infocom Maroc','2026-06-01','2026-07-31',17.00,'Travail remarquable',76),
('stage_entreprise','Maintenance et administration de bases de données','DataSys','2026-06-01','2026-07-31',NULL,NULL,77),
('stage_entreprise','Développement mobile Android','Mobilink','2026-06-01','2026-07-31',13.00,'Apprentissage rapide',78),
('pfe','Application de gestion des ressources humaines','Mobilink','2026-06-01','2026-07-31',12.00,'Résultat satisfaisant',78),
-- 2A TGI 2025/2026
('stage_entreprise','Installation et configuration de serveurs','NetPro Maroc','2026-06-01','2026-07-31',14.00,'Très sérieux',83),
('pfe','Mise en place d\'une infrastructure réseau d\'entreprise','NetPro Maroc','2026-06-01','2026-07-31',15.00,'Bon travail',83),
('stage_entreprise','Support technique et maintenance informatique','Assist IT','2026-06-01','2026-07-31',16.00,'Excellent technicien',84),
('pfe','Virtualisation et cloud computing','Assist IT','2026-06-01','2026-07-31',18.00,'Travail exceptionnel',84),
('stage_entreprise','Administration réseau Windows Server','WinSol','2026-06-01','2026-07-31',NULL,NULL,85),
-- 2A TSGE 2025/2026
('stage_entreprise','Gestion de la comptabilité fournisseur','Cabinet Benali & Associés','2026-06-01','2026-07-31',15.00,'Rigueur et précision',91),
('pfe','Mise en place d\'un tableau de bord financier','Cabinet Benali & Associés','2026-06-01','2026-07-31',14.00,'Bon résultat',91),
('stage_entreprise','Marketing digital et gestion réseaux sociaux','AgencePlus','2026-06-01','2026-07-31',17.00,'Créativité et professionnalisme',92),
('pfe','Stratégie de communication pour PME','AgencePlus','2026-06-01','2026-07-31',16.00,'Très bonne analyse',92),
('stage_entreprise','Traitement de la paie et déclarations sociales','RH Conseil','2026-06-01','2026-07-31',NULL,NULL,93),
-- 2A TSDI 2024/2025 (stages terminés)
('stage_entreprise','Développement d\'un ERP modulaire','CodeFactory','2025-06-01','2025-07-31',16.00,'Excellent développeur',98),
('pfe','Intégration d\'un module de reporting avancé','CodeFactory','2025-06-01','2025-07-31',17.00,'Livrable impeccable',98),
('stage_entreprise','Audit et sécurité des systèmes d\'information','SecureNet','2025-06-01','2025-07-31',15.00,'Très bon',99),
-- 2A TGI 2024/2025
('stage_entreprise','Déploiement infrastructure VMware','VirtTech','2025-06-01','2025-07-31',14.00,'Bonne maîtrise',106),
('pfe','Automatisation des sauvegardes réseau','VirtTech','2025-06-01','2025-07-31',15.00,'Résultat satisfaisant',106),
-- 2A TSGE 2024/2025
('stage_entreprise','Contrôle de gestion et budgétisation','Finance+','2025-06-01','2025-07-31',16.00,'Très rigoureux',114),
('pfe','Modèle financier prévisionnel sur Excel','Finance+','2025-06-01','2025-07-31',15.00,'Bon travail',114);

-- ─────────────────────────────────────────────────────────────
-- 7. Mise à jour AUTO_INCREMENT
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `stagiaires`        MODIFY `id_stagiaire`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;
ALTER TABLE `absences`          MODIFY `id_absence`    int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;
ALTER TABLE `mensualites`       MODIFY `id_mensualite` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;
ALTER TABLE `stages`            MODIFY `id_stage`      int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
ALTER TABLE `pre_inscription`   MODIFY `id_demande`    int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

SET FOREIGN_KEY_CHECKS = 1;

SELECT CONCAT('Données de test insérées : ', COUNT(*), ' stagiaires au total') AS résultat FROM stagiaires;
SELECT CONCAT('Notes : ', COUNT(*), ' enregistrements') AS résultat FROM module_notes;
SELECT CONCAT('Mensualités : ', COUNT(*), ' enregistrements') AS résultat FROM mensualites;
SELECT CONCAT('Stages : ', COUNT(*), ' enregistrements') AS résultat FROM stages;
