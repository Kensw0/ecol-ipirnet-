<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT st.*, s.nom, s.prenom, s.matricule, s.cin, s.date_naissance, s.adresse, c.nom_classe, c.annee_scolaire, f.nom_filiere FROM stages st JOIN stagiaires s ON s.id_stagiaire=st.id_stagiaire JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere WHERE st.id_stage=?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    http_response_code(404);
    exit;
}
log_document_gen($pdo, 'convention_stage', (int) $t['id_stagiaire'], 'ST-' . $id);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convention de stage — <?= h((string) $t['nom']) ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=4">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=4">
</head>
<body class="print-page paper-page">
<div class="paper-doc">
    <p class="no-print" style="text-align:center;">
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimer</button>
        <a class="btn btn--ghost btn--sm" href="stages.php">Retour</a>
    </p>
    <header class="paper-letterhead">
        <div class="paper-letterhead__brand">
            <img src="assets/img/logo.png" alt="" class="paper-letterhead__logo">
            <div>
                <div class="paper-letterhead__org">Groupe IPIRNET</div>
                <div class="paper-letterhead__sub">Service stages &amp; PFE</div>
            </div>
        </div>
        <div class="paper-letterhead__meta">
            <div><strong>N° :</strong> CV-<?= h((string) $t['matricule']) ?>-<?= h((string) $id) ?></div>
            <div><strong>Date :</strong> <?= h(date('d/m/Y')) ?></div>
        </div>
    </header>

    <h1 class="paper-title">CONVENTION DE STAGE</h1>
    <p class="paper-subtitle"><?= h((string) $t['type_stage']) === 'pfe' ? 'Projet de fin d\u2019\xc3\xa9tudes (PFE)' : 'Stage en entreprise' ?> — Année <?= h((string) $t['annee_scolaire']) ?></p>

    <section class="paper-section">
        <h2>1. Stagiaire</h2>
        <table class="paper-fields">
            <tr><th>Nom &amp; prénom</th><td colspan="3"><?= h((string) $t['nom'] . ' ' . (string) $t['prenom']) ?></td></tr>
            <tr><th>Matricule</th><td><?= h((string) $t['matricule']) ?></td><th>CIN</th><td><?= h((string) ($t['cin'] ?? '')) ?></td></tr>
            <tr><th>Classe / filière</th><td colspan="3"><?= h((string) $t['nom_classe'] . ' — ' . (string) $t['nom_filiere']) ?></td></tr>
            <tr><th>Adresse</th><td colspan="3"><?= h((string) ($t['adresse'] ?? '')) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>2. Organisme d'accueil</h2>
        <table class="paper-fields">
            <tr><th>Entreprise / organisme</th><td colspan="3"><?= h((string) ($t['entreprise'] ?? '')) ?></td></tr>
            <tr><th>Sujet du stage</th><td colspan="3"><?= h((string) ($t['sujet'] ?? '')) ?></td></tr>
        </table>
    </section>

    <section class="paper-section">
        <h2>3. Période</h2>
        <table class="paper-fields">
            <tr><th>Date de début</th><td><?= h((string) ($t['date_debut'] ?? '')) ?></td><th>Date de fin</th><td><?= h((string) ($t['date_fin'] ?? '')) ?></td></tr>
            <?php if (!empty($t['date_soutenance'])): ?>
            <tr><th>Date de soutenance</th><td colspan="3"><?= h((string) $t['date_soutenance']) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($t['jury'])): ?>
            <tr><th>Jury / modalités</th><td colspan="3"><?= nl2br(h((string) $t['jury'])) ?></td></tr>
            <?php endif; ?>
        </table>
    </section>

    <section class="paper-section paper-engagements">
        <h2>4. Engagements</h2>
        <p>Le stagiaire s'engage à respecter le règlement intérieur de l'organisme d'accueil et la confidentialité des informations auxquelles il aura accès. L'organisme d'accueil s'engage à assurer un encadrement pédagogique adéquat.</p>
        <div class="paper-signatures">
            <div>
                <p class="paper-signatures__role">L'établissement (IPIRNET)</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">L'entreprise d'accueil</p>
                <p class="paper-signatures__line">&nbsp;</p>
            </div>
            <div>
                <p class="paper-signatures__role">Le stagiaire</p>
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
