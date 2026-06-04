<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=gestion_des_stagiaires;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("DESCRIBE mensualites");
    header("Content-Type: text/plain");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
