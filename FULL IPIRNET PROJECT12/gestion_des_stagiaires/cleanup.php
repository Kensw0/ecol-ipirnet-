<?php
// ONE-TIME CLEANUP - removes duplicate filieres and classes
// Open this in your browser: http://localhost/gestion_des_stagiaires/cleanup.php
// Then DELETE this file from your server immediately after!

$DB_HOST = getenv('GDS_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('GDS_DB_NAME') ?: 'gestion_des_stagiaires';
$DB_USER = getenv('GDS_DB_USER') ?: 'root';
$DB_PASS = getenv('GDS_DB_PASS') ?: '';
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

// Keep only the LOWEST id_filiere per nom_filiere, delete the rest
$pdo->exec("
    DELETE f1 FROM filieres f1
    INNER JOIN filieres f2
    ON f1.nom_filiere = f2.nom_filiere AND f1.id_filiere > f2.id_filiere
");

// Keep only the LOWEST id_classe per (nom_classe, id_filiere), delete the rest
$pdo->exec("
    DELETE c1 FROM classes c1
    INNER JOIN classes c2
    ON c1.nom_classe = c2.nom_classe AND c1.id_filiere = c2.id_filiere AND c1.id_classe > c2.id_classe
");

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$filieres = $pdo->query('SELECT * FROM filieres ORDER BY id_filiere')->fetchAll(PDO::FETCH_ASSOC);
$classes  = $pdo->query('SELECT * FROM classes ORDER BY id_classe')->fetchAll(PDO::FETCH_ASSOC);

echo '<h2>Done! Filieres remaining:</h2><pre>';
foreach ($filieres as $r) echo $r['id_filiere'] . ' - ' . $r['nom_filiere'] . "\n";
echo '</pre><h2>Classes remaining:</h2><pre>';
foreach ($classes as $r) echo $r['id_classe'] . ' - ' . $r['nom_classe'] . ' (filiere ' . $r['id_filiere'] . ')' . "\n";
echo '</pre><p><strong>Now delete cleanup.php from your server!</strong></p>';
