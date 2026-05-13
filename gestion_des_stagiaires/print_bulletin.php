<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$mid = (int) ($_GET['mid'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s || $mid <= 0) {
    http_response_code(404);
    exit('Paramètres invalides');
}
$mod = $pdo->prepare('SELECT nom_module FROM modules WHERE id_module=?');
$mod->execute([$mid]);
$mname = (string) ($mod->fetchColumn() ?: '');
$notes = $pdo->prepare('SELECT * FROM evaluer WHERE id_stagiaire=? AND id_module=? ORDER BY date_evaluation');
$notes->execute([$id, $mid]);
$rows = $notes->fetchAll();
$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=? AND id_module=?');
$moy->execute([$id, $mid]);
$mm = $moy->fetchColumn();
log_document_gen($pdo, 'bulletin', $id, (string) $s['matricule'] . '-M' . $mid);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin — <?= h((string) $s['nom']) ?> — <?= h($mname) ?></title>
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
            <div><strong>N° :</strong> B-<?= h((string) $s['matricule']) ?>-M<?= h((string) $mid) ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">BULLETIN DE NOTES</h1>
    <p class="paper-subtitle">Module : <?= h($mname) ?> — Année <?= h((string) $s['annee_scolaire']) ?></p>

    <section class="paper-section">
        <table class="paper-fields">
            <tr><th>Nom et prénom</th><td colspan="3"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>Évaluations</h2>
        <table class="paper-grades">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Note / 20</th><th>Commentaire</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= h((string) $r['date_evaluation']) ?></td>
                        <td><?= h((string) $r['type_evaluation']) ?></td>
                        <td><?= h((string) $r['valeur_note']) ?></td>
                        <td><?= h((string) ($r['commentaire'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4"><em>Aucune note enregistrée.</em></td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="2">Moyenne du module</th><th colspan="2"><?= h((string) ($mm ?? '—')) ?> / 20</th></tr>
            </tfoot>
        </table>
    </section>

    <section class="paper-engagements" style="margin-top:1.5rem;">
        <div class="paper-signatures">
            <div>
                <p class="paper-signatures__role">Le formateur</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Le directeur pédagogique</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Cachet</p>
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
