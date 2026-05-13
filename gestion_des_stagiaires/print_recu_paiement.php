<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$mois = (string) ($_GET['mois'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $mois = date('Y-m');
}
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
$men = $pdo->prepare('SELECT est_paye, marque_le FROM mensualites WHERE id_stagiaire=? AND mois_ref=?');
$men->execute([$id, $mois]);
$m = $men->fetch();
$estPaye = $m ? (int) $m['est_paye'] === 1 : false;
$marqueLe = $m['marque_le'] ?? null;
$montant = (string) ($_GET['montant'] ?? '');
$mode = (string) ($_GET['mode'] ?? '');
log_document_gen($pdo, 'recu_paiement', $id, (string) $s['matricule'] . '-' . $mois);

$moisAff = $mois;
try {
    $dt = DateTime::createFromFormat('Y-m', $mois);
    if ($dt) {
        $months = [1=>'janvier','f\xc3\xa9vrier','mars','avril','mai','juin','juillet','ao\xc3\xbbt','septembre','octobre','novembre','d\xc3\xa9cembre'];
        $moisAff = $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
    }
} catch (Throwable $e) {}

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu de paiement — <?= h((string) $s['nom']) ?> — <?= h($mois) ?></title>
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
                <div class="paper-letterhead__sub">Service comptabilité / scolarité</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>Reçu N° :</strong> R-<?= h((string) $s['matricule']) ?>-<?= h(str_replace('-','',$mois)) ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">REÇU DE PAIEMENT</h1>
    <p class="paper-subtitle">Cotisation mensuelle — <?= h($moisAff) ?></p>

    <section class="paper-section">
        <table class="paper-fields">
            <tr><th>Nom &amp; prénom</th><td colspan="3"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td></tr>
            <tr><th>Filière</th><td colspan="3"><?= h((string) $s['nom_filiere']) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>Détail du règlement</h2>
        <table class="paper-fields">
            <tr><th>Mois concerné</th><td><?= h($moisAff) ?></td><th>Statut</th><td><?= $estPaye ? '<strong style="color:#16a34a;">PAYÉ</strong>' : '<strong style="color:#b91c1c;">NON PAYÉ</strong>' ?></td></tr>
            <tr><th>Montant</th><td><?= $montant !== '' ? h($montant) . ' MAD' : '<em>à compléter par la caissière</em>' ?></td><th>Mode de règlement</th><td><?= $mode !== '' ? h($mode) : '<em>espèces / chèque / virement</em>' ?></td></tr>
            <tr><th>Date d'encaissement</th><td colspan="3"><?= h((string) ($marqueLe ?? date('d/m/Y H:i'))) ?></td></tr>
        </table>
    </section>

    <section class="paper-section paper-engagements">
        <p>Ce reçu est délivré au stagiaire désigné ci-dessus pour faire valoir ce que de droit.</p>
        <div class="paper-signatures">
            <div>
                <p class="paper-signatures__role">Signature du stagiaire</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Caissière / direction</p>
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
