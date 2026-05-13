<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
log_document_gen($pdo, 'fiche_inscription', $id, (string) $s['matricule']);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche d'inscription — <?= h((string) $s['nom']) ?></title>
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
                <div class="paper-letterhead__sub">Établissement de formation professionnelle</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> FI-<?= h((string) $s['matricule']) ?>-<?= date('Y') ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">FICHE D'INSCRIPTION</h1>
    <p class="paper-subtitle">Année scolaire <?= h((string) $s['annee_scolaire']) ?></p>

    <section class="paper-section">
        <h2>1. Identité du stagiaire</h2>
        <table class="paper-fields">
            <tr><th>Nom</th><td><?= h((string) $s['nom']) ?></td><th>Prénom</th><td><?= h((string) $s['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $s['matricule']) ?></td><th>CIN</th><td><?= h((string) ($s['cin'] ?? '')) ?></td></tr>
            <tr><th>Date de naissance</th><td><?= h((string) ($s['date_naissance'] ?? '')) ?></td><th>Téléphone</th><td><?= h((string) ($s['telephone'] ?? '')) ?></td></tr>
            <tr><th>Adresse</th><td colspan="3"><?= h((string) ($s['adresse'] ?? '')) ?></td></tr>
            <tr><th>Email</th><td colspan="3"><?= h((string) ($s['email'] ?? '')) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>2. Responsable / parent</h2>
        <table class="paper-fields">
            <tr><th>Nom du père / tuteur</th><td><?= h((string) ($s['nom_tuteur'] ?? '')) ?></td><th>Téléphone parent</th><td><?= h((string) ($s['telephone_parent'] ?? '')) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>3. Inscription</h2>
        <table class="paper-fields">
            <tr><th>Date d'inscription</th><td><?= h((string) ($s['date_inscription'] ?? '')) ?></td><th>Classe</th><td><?= h((string) $s['nom_classe']) ?></td></tr>
            <tr><th>Filière</th><td colspan="3"><?= h((string) $s['nom_filiere']) ?></td></tr>
        </table>
    </section>

    <section class="paper-section paper-engagements">
        <h2>4. Engagement</h2>
        <p>Je soussigné(e) <strong><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></strong> déclare avoir pris connaissance du règlement intérieur de l'établissement et m'engage à le respecter.</p>
        <div class="paper-signatures">
            <div>
                <p class="paper-signatures__role">Signature du stagiaire</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Signature du parent / tuteur</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Cachet de l'établissement</p>
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
