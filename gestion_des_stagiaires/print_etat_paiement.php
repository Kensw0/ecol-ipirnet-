<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit;
}
$rows = $pdo->prepare('SELECT mois_ref, est_paye, marque_le FROM mensualites WHERE id_stagiaire=? ORDER BY mois_ref DESC LIMIT 36');
$rows->execute([$id]);
$hist = $rows->fetchAll();
log_document_gen($pdo, 'etat_mensualites', $id, (string) $s['matricule']);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ã‰tat des cotisations mensuelles</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a></p>
<h1 style="text-align:center;">Ã‰TAT DES COTISATIONS (PAR MOIS)</h1>
<p><strong><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></strong> â€” <?= h((string) $s['matricule']) ?></p>
<p class="muted" style="font-size:0.95rem;">Historique des marquages Â« payÃ© / non payÃ© Â» par mois (pas de montants ni dâ€™Ã©chÃ©ances Merise).</p>
<h2>MensualitÃ©s</h2>
<table class="data">
    <tr><th>Mois</th><th>PayÃ©</th><th>MarquÃ© le</th></tr>
    <?php foreach ($hist as $e): ?>
        <tr>
            <td><?= h((string) $e['mois_ref']) ?></td>
            <td><?= (int) $e['est_paye'] ? 'oui' : 'non' ?></td>
            <td><?= h((string) ($e['marque_le'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$hist): ?><tr><td colspan="3">Aucun enregistrement.</td></tr><?php endif; ?>
</table>
</div>
</body>
</html>
