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
    <link rel="stylesheet" href="assets/css/app.css?v=5">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=5">
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

    <p class="paper-lead">Le Directeur du <strong>Groupe IPIRNET</strong> certifie que l'élève désigné ci-dessous est régulièrement inscrit(e) dans notre établissement :</p>

    <dl class="paper-info">
        <dt>Nom et prénom</dt><dd><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></dd>
        <dt>Matricule</dt><dd><?= h((string) $s['matricule']) ?></dd>
        <dt>CIN</dt><dd><?= h((string) ($s['cin'] ?? '')) ?></dd>
        <dt>Date de naissance</dt><dd><?= h((string) ($s['date_naissance'] ?? '')) ?></dd>
        <dt>Année scolaire</dt><dd><?= h((string) $s['annee_scolaire']) ?></dd>
        <dt>Classe</dt><dd><?= h((string) $s['nom_classe']) ?></dd>
        <dt>Filière</dt><dd><?= h((string) $s['nom_filiere']) ?></dd>
    </dl>

    <p class="paper-closing">Le présent certificat est délivré à l'intéressé(e) pour servir et valoir ce que de droit.</p>

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
