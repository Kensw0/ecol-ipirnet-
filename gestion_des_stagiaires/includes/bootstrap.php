<?php
declare(strict_types=1);

// ============================================================
//  bootstrap.php — Point d'entrée commun à toutes les pages
//
//  Chaque page PHP du projet commence par :
//    require __DIR__ . '/includes/bootstrap.php';
//
//  Ce fichier doit être chargé en premier, avant tout autre code.
//  L'ordre des require ci-dessous est intentionnel et ne doit pas
//  être modifié :
//    1. db.php     — ouvre la connexion PDO ($pdo) dont helpers et
//                    auth peuvent avoir besoin.
//    2. helpers.php — définit les fonctions utilitaires (h(), flash_set(),
//                    etc.) utilisées partout, y compris dans auth.php.
//    3. auth.php   — définit les fonctions de session/rôle et démarre
//                    la session si ce n'est pas encore fait.
//
//  Après les require, gds_require_admin_session() redirige vers
//  login.php si aucun utilisateur n'est connecté, bloquant l'accès
//  à toutes les pages protégées en un seul endroit.
// ============================================================

// Démarre la session si elle n'est pas encore active.
// (auth.php le fait aussi par sécurité, mais le gérer ici en premier
// garantit que la session est prête avant tout appel à db.php ou helpers.php.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Connexion à la base de données — fournit la variable $pdo à toutes les pages.
require __DIR__ . '/db.php';

// 2. Fonctions utilitaires partagées (h, flash_set/get, redirect, log_document_gen…).
require __DIR__ . '/helpers.php';

// 3. Authentification, rôles et protection CSRF.
require __DIR__ . '/auth.php';

// Vérifie que l'utilisateur est connecté ; redirige vers login.php sinon.
// Les pages login.php et logout.php sont automatiquement exemptées.
gds_require_admin_session();
