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
$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=?');
$moy->execute([$id]);
$gm = $moy->fetchColumn();
log_document_gen($pdo, 'attestation_reussite', $id, $s['matricule']);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attestation de réussite</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a></p>
<h1 style="text-align:center;">ATTESTATION DE RÉUSSITE</h1>
<p>Le Directeur certifie que <strong><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></strong>, matricule <strong><?= h((string) $s['matricule']) ?></strong>,</p>
<p>a satisfait aux épreuves et contrôles continus avec une moyenne générale actuelle de <strong><?= h((string) $gm) ?> / 20</strong> (calcul sur les notes saisies).</p>
<p>Année : <?= h((string) $s['annee_scolaire']) ?> — Classe : <?= h((string) $s['nom_classe']) ?>.</p>
<p>Le <?= h(date('d/m/Y')) ?></p>
</div>
</body>
</html>
