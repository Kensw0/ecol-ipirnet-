<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT a.*, s.nom, s.prenom, s.matricule, c.nom_classe FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire JOIN classes c ON c.id_classe=s.id_classe WHERE a.id_absence=?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    http_response_code(404);
    exit;
}
log_document_gen($pdo, 'billet_excuse', (int) $a['id_stagiaire'], 'ABS-' . $id);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billet d'excuse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="print-page">
<div class="print-doc">
<p class="no-print"><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button> <a class="btn btn--ghost btn--sm" href="absences.php">Retour</a></p>
<h1 style="text-align:center;">BILLET D'EXCUSE</h1>
<p>L’élève <strong><?= h((string) $a['nom'] . ' ' . (string) $a['prenom']) ?></strong>, matricule <strong><?= h((string) $a['matricule']) ?></strong>, classe <strong><?= h((string) $a['nom_classe']) ?></strong>,</p>
<p>a été absent(e) le <strong><?= h((string) $a['date_absence']) ?></strong>
<?php if (!empty($a['heure_debut'])): ?> de <?= h(substr((string) $a['heure_debut'], 0, 5)) ?> à <?= h(substr((string) ($a['heure_fin'] ?? ''), 0, 5)) ?><?php endif; ?>.</p>
<p>Motif / justificatif : <?= h((string) ($a['justificatif'] ?? '—')) ?></p>
<p>Statut : <?= (int) $a['est_justifiee'] ? 'Justifiée' : 'Non justifiée' ?>.</p>
<p>Le <?= h(date('d/m/Y')) ?></p>
</div>
</body>
</html>
