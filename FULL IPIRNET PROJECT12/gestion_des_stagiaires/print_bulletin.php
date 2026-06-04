<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id  = (int) ($_GET['id']  ?? 0);
$mid = (int) ($_GET['mid'] ?? 0);
$st  = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s || $mid <= 0) {
    http_response_code(404);
    exit('Paramètres invalides');
}
$mod = $pdo->prepare('SELECT nom_module FROM modules WHERE id_module=?');
$mod->execute([$mid]);
$mname = (string) ($mod->fetchColumn() ?: '');

// Use v_moyennes_par_module as single truth
$notes = $pdo->prepare(
    'SELECT note_controle, note_theorique, note_pratique, note_examen, moyenne_module
       FROM v_moyennes_par_module
      WHERE id_stagiaire=? AND id_module=?'
);
$notes->execute([$id, $mid]);
$r = $notes->fetch();

log_document_gen($pdo, 'bulletin', $id, (string) $s['num_inscri'] . '-M' . $mid);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

$fmtNote = static function ($v): string {
    if ($v === null || $v === '') return '—';
    $f = (float) $v;
    return number_format($f, 2, ',', '');
};
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin — <?= h((string) $s['nom']) ?> — <?= h($mname) ?></title>
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
            <img src="assets/img/logo.png" alt="" class="paper-letterhead__logo" onerror="this.style.display='none'">
            <div>
                <div class="paper-letterhead__org">Groupe IPIRNET</div>
                <div class="paper-letterhead__sub">Direction pédagogique</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> B-<?= h((string) $s['num_inscri']) ?>-M<?= h((string) $mid) ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">BULLETIN DE NOTES</h1>
    <p class="paper-subtitle">Module : <?= h($mname) ?> — Année <?= h((string) $s['annee_scolaire']) ?></p>

    <dl class="paper-info">
        <dt>Nom et prénom</dt><dd><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></dd>
        <dt>N° Inscription</dt><dd><?= h((string) $s['num_inscri']) ?></dd>
        <dt>Classe</dt><dd><?= h((string) $s['nom_classe']) ?></dd>
        <dt>Module</dt><dd><?= h($mname) ?></dd>
    </dl>

    <section class="paper-section">
        <h2>Détail du Module</h2>
        <table class="paper-grades">
            <thead>
                <tr><th>Structure</th><th>Note / 20</th></tr>
            </thead>
            <tbody>
                <?php if ($r): ?>
                    <tr>
                        <td>Contrôle Continu</td>
                        <td style="font-weight:bold;"><?= h($fmtNote($r['note_controle'])) ?></td>
                    </tr>
                    <tr>
                        <td>Examen Théorique</td>
                        <td style="font-weight:bold;"><?= h($fmtNote($r['note_theorique'])) ?></td>
                    </tr>
                    <tr>
                        <td>Examen Pratique</td>
                        <td style="font-weight:bold;"><?= h($fmtNote($r['note_pratique'])) ?></td>
                    </tr>
                    <tr style="background:#f4f4f5;">
                        <td><strong>Moyenne d'Examen</strong></td>
                        <td style="font-weight:bold;"><?= h($fmtNote($r['note_examen'])) ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="2"><em>Aucune évaluation enregistrée pour ce module.</em></td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr><th>Moyenne Générale du Module</th><th><?= $r ? h($fmtNote($r['moyenne_module'])) . ' / 20' : '—' ?></th></tr>
            </tfoot>
        </table>
    </section>

    <section class="paper-engagements" style="margin-top:2.5rem;">
        <div class="paper-signatures" style="display:flex; justify-content:space-around;">
            <div style="text-align:center;">
                <p class="paper-signatures__role">Le formateur</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div style="text-align:center;">
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
