<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT a.*, s.nom, s.prenom, s.matricule, c.nom_classe, c.annee_scolaire FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire JOIN classes c ON c.id_classe=s.id_classe WHERE a.id_absence=?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    http_response_code(404);
    exit;
}
log_document_gen($pdo, 'billet_excuse', (int) $a['id_stagiaire'], 'ABS-' . $id);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billet d'excuse — <?= h((string) $a['nom']) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=4">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=4">
</head>
<body class="print-page paper-page">
<div class="paper-doc">
    <p class="no-print" style="text-align:center;">
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button>
        <a class="btn btn--ghost btn--sm" href="absences.php">Retour</a>
    </p>
    <header class="paper-letterhead">
        <div class="paper-letterhead__brand">
            <img src="assets/img/logo.png" alt="" class="paper-letterhead__logo">
            <div>
                <div class="paper-letterhead__org">Groupe IPIRNET</div>
                <div class="paper-letterhead__sub">Surveillance générale</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> BE-<?= h((string) $a['matricule']) ?>-<?= h((string) $a['id_absence']) ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">BILLET D'EXCUSE</h1>
    <p class="paper-subtitle">Année scolaire <?= h((string) $a['annee_scolaire']) ?></p>

    <section class="paper-body">
        <table class="paper-fields">
            <tr><th>Élève</th><td colspan="3"><?= h((string) $a['nom'] . ' ' . (string) $a['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $a['matricule']) ?></td><th>Classe</th><td><?= h((string) $a['nom_classe']) ?></td></tr>
            <tr><th>Date de l'absence</th><td><?= h((string) $a['date_absence']) ?></td>
                <th>Horaire</th>
                <td><?php if (!empty($a['heure_debut'])): ?><?= h(substr((string) $a['heure_debut'], 0, 5)) ?> – <?= h(substr((string) ($a['heure_fin'] ?? ''), 0, 5)) ?><?php else: ?>journée<?php endif; ?></td>
            </tr>
            <tr><th>Motif / justificatif</th><td colspan="3"><?= h((string) ($a['justificatif'] ?? '—')) ?></td></tr>
            <tr><th>Statut</th><td colspan="3"><?= (int) $a['est_justifiee'] ? '<strong style="color:#16a34a;">Justifiée</strong>' : '<strong style="color:#b91c1c;">Non justifiée</strong>' ?></td></tr>
        </table>
        <p style="margin-top:1rem;">L'élève désigné(e) ci-dessus est autorisé(e) à réintégrer sa classe.</p>
    </section>

    <section class="paper-engagements" style="margin-top:2rem;">
        <div class="paper-signatures">
            <div>
                <p class="paper-signatures__role">Signature du parent / tuteur</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Surveillant général</p>
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
