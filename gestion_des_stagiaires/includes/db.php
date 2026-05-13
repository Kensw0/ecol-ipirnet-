<?php
declare(strict_types=1);

/**
 * Optional: GDS_ADMIN_PASSWORD — plain text admin login for the whole app (see login.php).
 * If unset, default password is `admin123` (change immediately on any real host).
 *
 * XAMPP default — change if your MySQL user/password differs:
 */
$DB_HOST = getenv('GDS_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('GDS_DB_NAME') ?: 'gestion_des_stagiaires';
$DB_USER = getenv('GDS_DB_USER') ?: 'root';
$DB_PASS = getenv('GDS_DB_PASS') ?: '';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
