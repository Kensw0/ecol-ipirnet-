<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
gds_require_admin_session();

// Auto-seed: ensure every filière has 2 classes (1ère année + 2ème année)
// This runs only when a filière has zero classes, so it's safe and fast.
(function () use ($pdo): void {
    $filiereMap = [
        'Technicien Spécialisé en Développement Informatique' => ['1A TSDI', '2A TSDI'],
        'Technicien Spécialisé en Gestion des Entreprises'    => ['1A TSGE', '2A TSGE'],
        'Technicien en Informatique de Gestion'                => ['1A TGI',  '2A TGI'],
        'Opérateur Administratif'                              => ['1A OPAD', '2A OPAD'],
    ];

    // Ensure all filieres exist
    foreach (array_keys($filiereMap) as $nom) {
        $pdo->prepare('INSERT IGNORE INTO filieres (nom_filiere) VALUES (?)')->execute([$nom]);
    }

    // For each filiere, insert missing classes
    $annees = ['1ère année', '2ème année'];
    foreach ($filiereMap as $nomFiliere => $classes) {
        $st = $pdo->prepare('SELECT id_filiere FROM filieres WHERE nom_filiere = ?');
        $st->execute([$nomFiliere]);
        $row = $st->fetch();
        if (!$row) {
            continue;
        }
        $fid = (int) $row['id_filiere'];
        foreach ($classes as $i => $nomClasse) {
            $pdo->prepare(
                'INSERT IGNORE INTO classes (nom_classe, annee_scolaire, id_filiere) VALUES (?, ?, ?)'
            )->execute([$nomClasse, $annees[$i], $fid]);
        }
    }
})();
