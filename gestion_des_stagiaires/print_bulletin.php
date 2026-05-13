<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$mid = (int) ($_GET['mid'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s || $mid <= 0) {
    http_response_code(404);
    exit('Paramètres invalides');
}
$mod = $pdo->prepare('SELECT nom_module FROM modules WHERE id_module=?');
$mod->execute([$mid]);
$mname = (string) ($mod->fetchColumn() ?: '');
$notes = $pdo->prepare('SELECT * FROM evaluer WHERE id_stagiaire=? AND id_module=? ORDER BY date_evaluation');
$notes->execute([$id, $mid]);
$rows = $notes->fetchAll();
$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=? AND id_module=?');
$moy->execute([$id, $mid]);
$mm = $moy->fetchColumn();
log_document_gen($pdo, 'bulletin', $id, $s['matricule'] . '-M' . $mid);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a></p>
<h1 style="text-align:center;">BULLETIN DE NOTES</h1>
<p><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?> — <?= h((string) $s['matricule']) ?></p>
<p><strong>Module :</strong> <?= h($mname) ?></p>
<table class="data">
    <tr><th>Date</th><th>Type</th><th>Note</th><th>Commentaire</th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h((string) $r['date_evaluation']) ?></td>
            <td><?= h((string) $r['type_evaluation']) ?></td>
            <td><?= h((string) $r['valeur_note']) ?></td>
            <td><?= h((string) ($r['commentaire'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p><strong>Moyenne du module :</strong> <?= h((string) $mm) ?> / 20</p>
</div>
</body>
</html>
