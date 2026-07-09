-- ============================================================
-- Attribution des semestres à tous les modules
-- Filière TSDI (id=2) · TGI (id=3) · TSGE (id=4)
-- ============================================================

-- ── TSDI — Semestre 1 ────────────────────────────────────────
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 3;   -- Bases de données
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 19;  -- Métier et Formation
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 20;  -- L'entreprise et son environnement
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 21;  -- Notion de mathématique appliquée
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 22;  -- Gestion du temps
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 24;  -- Logiciel d'application
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 26;  -- Technique de programmation structurée
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 29;  -- Concept et modélisation d'un SI
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 30;  -- Installation d'un poste informatique
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 31;  -- Communication en Anglais

-- ── TSDI — Semestre 2 ────────────────────────────────────────
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 23;  -- Veille technologique
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 25;  -- Programmation événementielle
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 27;  -- Langage de programmation structurée
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 28;  -- Programmation orientée objet
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 32;  -- Assistant technique à la clientèle

-- ── TSGE — Semestre 1 ────────────────────────────────────────
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 33;  -- Comptabilité générale
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 34;  -- Concept de base
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 37;  -- Marketing
UPDATE `modules` SET `semestre` = 1 WHERE `id_module` = 38;  -- Entreprise

-- ── TSGE — Semestre 2 ────────────────────────────────────────
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 35;  -- Traitement de salaire
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 36;  -- Charge de personnel
UPDATE `modules` SET `semestre` = 2 WHERE `id_module` = 39;  -- Statistique

-- ── TGI — déjà corrects, vérification ───────────────────────
-- 40 Algorithm          → 1 ✓
-- 41 Installation poste → 2 ✓
-- 42 Bureautique        → 2 ✓
-- 43 Comptabilité gén.  → 1 ✓
-- 44 Statistique        → 2 ✓

-- ── TSDI — déjà corrects ────────────────────────────────────
-- 2  Algorithmique et Programmation → 1 ✓
-- 4  Développement Web              → 2 ✓
-- 59 UML                            → 2 ✓
