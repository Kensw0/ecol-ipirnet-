<?php
declare(strict_types=1);

// ============================================================
//  db.php — Connexion PDO à la base de données MariaDB/MySQL
//
//  Ce fichier crée la variable $pdo disponible dans toutes les pages
//  via bootstrap.php. Il ne définit aucune fonction : il est conçu
//  pour être chargé une seule fois au démarrage.
//
//  Configuration par variables d'environnement (recommandé en production) :
//    GDS_DB_HOST — adresse du serveur MySQL  (défaut : 127.0.0.1)
//    GDS_DB_NAME — nom de la base de données (défaut : gestion_des_stagiaires)
//    GDS_DB_USER — utilisateur MySQL          (défaut : root)
//    GDS_DB_PASS — mot de passe MySQL         (défaut : vide, typique XAMPP)
//
//  En développement local (XAMPP), les valeurs par défaut fonctionnent
//  sans configuration supplémentaire.
//  En production, définir ces variables d'environnement sur le serveur.
// ============================================================

// --- Paramètres de connexion ---
// L'opérateur ?: utilise la valeur d'environnement si elle est définie et non vide,
// sinon la valeur par défaut (configuration XAMPP standard).
$hoteDB = getenv('GDS_DB_HOST') ?: '127.0.0.1';
$nomDB  = getenv('GDS_DB_NAME') ?: 'gestion_des_stagiaires';
$userDB = getenv('GDS_DB_USER') ?: 'root';
$passDB = getenv('GDS_DB_PASS') ?: '';

// --- DSN (Data Source Name) ---
// charset=utf8mb4 : encodage complet Unicode (supporte les emojis et tous
// les caractères accentués), à préférer à utf8 qui est limité en MySQL.
$dsn = "mysql:host={$hoteDB};dbname={$nomDB};charset=utf8mb4";

// --- Création de la connexion PDO ---
$pdo = new PDO($dsn, $userDB, $passDB, [
    // Lance une exception PHP à chaque erreur SQL plutôt que de retourner false
    // silencieusement — les catch() dans les pages captent ces exceptions.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // fetch() et fetchAll() retournent des tableaux associatifs par défaut
    // (clé = nom de colonne), sans avoir à passer PDO::FETCH_ASSOC à chaque appel.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
