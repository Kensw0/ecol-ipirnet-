<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT st.*, s.nom, s.prenom, s.matricule FROM stages st JOIN stagiaires s ON s.id_stagiaire=st.id_stagiaire WHERE st.id_stage=?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    http_response_code(404);
    exit;
}
log_document_gen($pdo, 'convention_stage', (int) $t['id_stagiaire'], 'ST-' . $id);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convention de stage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="stages.php">Retour</a></p>
<h1 style="text-align:center;">CONVENTION DE STAGE</h1>
<p><strong>Stagiaire :</strong> <?= h((string) $t['nom'] . ' ' . (string) $t['prenom']) ?> (<?= h((string) $t['matricule']) ?>)</p>
<p><strong>Organisme d’accueil :</strong> <?= h((string) ($t['entreprise'] ?? '')) ?></p>
<p><strong>Sujet :</strong> <?= h((string) ($t['sujet'] ?? '')) ?></p>
<p><strong>Période :</strong> <?= h((string) ($t['date_debut'] ?? '')) ?> au <?= h((string) ($t['date_fin'] ?? '')) ?></p>
<p><strong>Type :</strong> <?= h((string) $t['type_stage']) ?></p>
<p><strong>URL convention signée (réf.) :</strong> <?= h((string) ($t['convention_url'] ?? '—')) ?></p>
<p>Signatures : Établissement — Entreprise — Stagiaire</p>
</div>
</body>
</html>
