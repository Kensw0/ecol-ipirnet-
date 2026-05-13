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
$nbPaye = 0; $nbImpaye = 0;
foreach ($hist as $r) { if ((int) $r['est_paye'] === 1) $nbPaye++; else $nbImpaye++; }
log_document_gen($pdo, 'etat_mensualites', $id, (string) $s['matricule']);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>État des cotisations — <?= h((string) $s['nom']) ?></title>
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
                <div class="paper-letterhead__sub">Service comptabilité</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> ET-<?= h((string) $s['matricule']) ?>-<?= date('Y') ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">ÉTAT DES COTISATIONS MENSUELLES</h1>
    <p class="paper-subtitle">Récapitulatif sur les 36 derniers mois — Année <?= h((string) $s['annee_scolaire']) ?></p>

    <section class="paper-section">
        <table class="paper-fields">
            <tr><th>Nom &amp; prénom</th><td colspan="3"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td></tr>
            <tr><th>Mois payés</th><td><strong style="color:#16a34a;"><?= $nbPaye ?></strong></td><th>Mois non payés</th><td><strong style="color:#b91c1c;"><?= $nbImpaye ?></strong></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>Détail mensuel</h2>
        <table class="paper-grades">
            <thead><tr><th>Mois</th><th>Statut</th><th>Marqué le</th></tr></thead>
            <tbody>
                <?php foreach ($hist as $e): ?>
                    <tr>
                        <td><?= h((string) $e['mois_ref']) ?></td>
                        <td><?= (int) $e['est_paye'] ? '<strong style="color:#16a34a;">Payé</strong>' : '<strong style="color:#b91c1c;">Non payé</strong>' ?></td>
                        <td><?= h((string) ($e['marque_le'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$hist): ?><tr><td colspan="3"><em>Aucun enregistrement.</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="paper-engagements" style="margin-top:1.5rem;">
        <div class="paper-signatures">
            <div style="grid-column:3;">
                <p class="paper-signatures__role">Caissière / direction</p>
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
