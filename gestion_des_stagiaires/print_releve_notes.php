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
log_document_gen($pdo, 'releve_notes', $id, (string) $s['matricule']);
$notes = $pdo->prepare('SELECT e.*, m.nom_module FROM evaluer e JOIN modules m ON m.id_module=e.id_module WHERE e.id_stagiaire=? ORDER BY m.nom_module, e.date_evaluation');
$notes->execute([$id]);
$rows = $notes->fetchAll();

// Group by module + compute per-module average
$byModule = [];
foreach ($rows as $r) {
    $m = (string) $r['nom_module'];
    $byModule[$m][] = $r;
}
$moyMod = [];
foreach ($byModule as $m => $list) {
    $sum = 0; $c = 0;
    foreach ($list as $r) { $sum += (float) $r['valeur_note']; $c++; }
    $moyMod[$m] = $c > 0 ? round($sum / $c, 2) : null;
}
$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=?');
$moy->execute([$id]);
$gm = $moy->fetchColumn();
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé de notes — <?= h((string) $s['nom']) ?></title>
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
                <div class="paper-letterhead__sub">Direction pédagogique</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> RN-<?= h((string) $s['matricule']) ?>-<?= date('Y') ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">RELEVÉ DE NOTES</h1>
    <p class="paper-subtitle">Année scolaire <?= h((string) $s['annee_scolaire']) ?></p>

    <section class="paper-section">
        <table class="paper-fields">
            <tr><th>Nom et prénom</th><td colspan="3"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td></tr>
            <tr><th>Filière</th><td colspan="3"><?= h((string) $s['nom_filiere']) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>Détail des évaluations par module</h2>
        <table class="paper-grades">
            <thead><tr><th>Module</th><th>Type</th><th>Date</th><th>Note / 20</th></tr></thead>
            <tbody>
                <?php foreach ($byModule as $mname => $list): ?>
                    <?php foreach ($list as $i => $r): ?>
                        <tr>
                            <td><?= $i === 0 ? h($mname) : '' ?></td>
                            <td><?= h((string) $r['type_evaluation']) ?></td>
                            <td><?= h((string) $r['date_evaluation']) ?></td>
                            <td><?= h((string) $r['valeur_note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="paper-grades__avg"><th colspan="3">Moyenne du module — <?= h($mname) ?></th><th><?= h((string) ($moyMod[$mname] ?? '—')) ?> / 20</th></tr>
                <?php endforeach; ?>
                <?php if (!$byModule): ?><tr><td colspan="4"><em>Aucune note enregistrée.</em></td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3">Moyenne générale</th><th><?= h((string) ($gm ?? '—')) ?> / 20</th></tr>
            </tfoot>
        </table>
    </section>

    <section class="paper-engagements" style="margin-top:1.5rem;">
        <div class="paper-signatures">
            <div style="grid-column:3;">
                <p class="paper-signatures__role">Le directeur pédagogique</p>
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
