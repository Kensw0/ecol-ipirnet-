<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Introuvable');
}
log_document_gen($pdo, 'releve_notes', $id, $s['matricule']);
$notes = $pdo->prepare('SELECT e.*, m.nom_module FROM evaluer e JOIN modules m ON m.id_module=e.id_module WHERE e.id_stagiaire=? ORDER BY m.nom_module, e.date_evaluation');
$notes->execute([$id]);
$rows = $notes->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RelevÃ© de notes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a></p>
<h1 style="text-align:center;">RELEVÃ‰ DE NOTES</h1>
<p><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?> â€” <?= h((string) $s['matricule']) ?> â€” <?= h((string) $s['nom_classe']) ?> (<?= h((string) $s['annee_scolaire']) ?>)</p>
<table class="data">
    <tr><th>Module</th><th>Type</th><th>Date</th><th>Note</th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h((string) $r['nom_module']) ?></td>
            <td><?= h((string) $r['type_evaluation']) ?></td>
            <td><?= h((string) $r['date_evaluation']) ?></td>
            <td><?= h((string) $r['valeur_note']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php
$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=?');
$moy->execute([$id]);
$gm = $moy->fetchColumn();
?>
<p><strong>Moyenne gÃ©nÃ©rale (tous modules, tous contrÃ´les) :</strong> <?= h((string) $gm) ?> / 20</p>
</div>
</body>
</html>
