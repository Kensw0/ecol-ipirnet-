<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    echo 'Introuvable';
    exit;
}
log_document_gen($pdo, 'certificat_scolarite', $id, $s['matricule']);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificat de scolaritÃ©</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a></p>
<h1 style="text-align:center;">CERTIFICAT DE SCOLARITÃ‰</h1>
<p>Le Directeur de <strong>Groupe IPIRNET</strong> certifie que lâ€™Ã©lÃ¨ve :</p>
<p><strong><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></strong>, matricule <strong><?= h((string) $s['matricule']) ?></strong>,</p>
<p>est rÃ©guliÃ¨rement inscrit(e) pour lâ€™annÃ©e scolaire <strong><?= h((string) $s['annee_scolaire']) ?></strong> en classe de <strong><?= h((string) $s['nom_classe']) ?></strong>, filiÃ¨re <strong><?= h((string) $s['nom_filiere']) ?></strong>.</p>
<p>Fait pour servir et valoir ce que de droit.</p>
<p>Le <?= h(date('d/m/Y')) ?></p>
</div>
</body>
</html>
