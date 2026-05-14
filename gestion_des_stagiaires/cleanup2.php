<?php
$DB_HOST = getenv('GDS_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('GDS_DB_NAME') ?: 'gestion_des_stagiaires';
$DB_USER = getenv('GDS_DB_USER') ?: 'root';
$DB_PASS = getenv('GDS_DB_PASS') ?: '';
$pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

$pdo->exec("DELETE f1 FROM filieres f1 INNER JOIN filieres f2 ON f1.nom_filiere = f2.nom_filiere AND f1.id_filiere > f2.id_filiere");

$map = [
    'TSDI' => 'Technicien Sp\u00e9cialis\u00e9 en D\u00e9veloppement Informatique',
    'TGI'  => 'Technicien en Informatique de Gestion',
    'TSGE' => 'Technicien Sp\u00e9cialis\u00e9 en Gestion des Entreprises',
    'OPAD' => 'Op\u00e9rateur Administratif',
];

foreach ($map as $code => $label) {
    $st = $pdo->prepare("SELECT id_filiere, nom_filiere FROM filieres WHERE nom_filiere IN (?,?) ORDER BY id_filiere");
    $st->execute([$code, $label]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) == 0) continue;
    $keep = $rows[0]['id_filiere'];
    $pdo->prepare("UPDATE filieres SET nom_filiere=? WHERE id_filiere=?")->execute([$label, $keep]);
    foreach ($rows as $r) {
        if ((int)$r['id_filiere'] !== (int)$keep) {
            $pdo->prepare("UPDATE classes SET id_filiere=? WHERE id_filiere=?")->execute([$keep, $r['id_filiere']]);
            $pdo->prepare("DELETE FROM filieres WHERE id_filiere=?")->execute([$r['id_filiere']]);
        }
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo '<h2>Done! Filieres:</h2><pre>';
foreach ($pdo->query('SELECT * FROM filieres ORDER BY id_filiere')->fetchAll(PDO::FETCH_ASSOC) as $r)
    echo $r['id_filiere'].' - '.$r['nom_filiere']."\n";
echo '</pre><h2>Classes:</h2><pre>';
foreach ($pdo->query('SELECT c.*,f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY c.id_classe')->fetchAll(PDO::FETCH_ASSOC) as $r)
    echo $r['id_classe'].' - '.$r['nom_classe'].' (filiere '.$r['id_filiere'].': '.$r['nom_filiere'].')'."\n";
echo '</pre><p style="color:red"><strong>DELETE THIS FILE NOW!</strong></p>';
