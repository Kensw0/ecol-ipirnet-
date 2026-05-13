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
log_document_gen($pdo, 'certificat_scolarite', $id, (string) $s['matricule']);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificat de scolarité — <?= h((string) $s['nom']) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=4">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=4">
</head>
<body class="print-page paper-page">
<div class="paper-doc">
    <p class="no-print" style="text-align:center;">
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button>
        <a class="btn btn--ghost btn--sm" href="documents_officiels.php?id=<?= $id ?>">Retour</a>
    </p>
    <header class="paper-letterhead">
        <div class="paper-letterhead__brand">
            <img src="assets/img/logo.png" alt="" class="paper-letterhead__logo">
            <div>
                <div class="paper-letterhead__org">Groupe IPIRNET</div>
                <div class="paper-letterhead__sub">Direction de la scolarité</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> CS-<?= h((string) $s['matricule']) ?>-<?= date('Y') ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">CERTIFICAT DE SCOLARITÉ</h1>
    <p class="paper-subtitle">Année scolaire <?= h((string) $s['annee_scolaire']) ?></p>

    <section class="paper-body">
        <p>Le Directeur du <strong>Groupe IPIRNET</strong> certifie que l'élève désigné ci-dessous est régulièrement inscrit(e) dans notre établissement :</p>
        <table class="paper-fields">
            <tr><th>Nom et prénom</th><td colspan="3"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>CIN</th><td><?= h((string) ($s['cin'] ?? '')) ?></td></tr>
            <tr><th>Date de naissance</th><td><?= h((string) ($s['date_naissance'] ?? '')) ?></td><th>Année scolaire</th><td><?= h((string) $s['annee_scolaire']) ?></td></tr>
            <tr><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td><th>Filière</th><td><?= h((string) $s['nom_filiere']) ?></td></tr>
        </table>
        <p style="margin-top:1.25rem;">Le présent certificat est délivré à l'intéressé(e) pour servir et valoir ce que de droit.</p>
    </section>

    <section class="paper-engagements" style="margin-top:2rem;">
        <div class="paper-signatures">
            <div style="grid-column:3;">
                <p class="paper-signatures__role">Fait à Casablanca, le <?= h(date('d/m/Y')) ?></p>
                <p class="paper-signatures__role" style="margin-top:0.25rem;">Le Directeur</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
        </div>
    </section>

    <footer class="paper-footer">
        Groupe IPIRNET — Document officiel généré le <?= h(date('d/m/Y H:i')) ?>.
    </footer>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
