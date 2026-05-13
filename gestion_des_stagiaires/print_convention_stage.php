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
    <link rel="stylesheet" href="assets/css/app.css?v=5">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=5">
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
    <p class="paper-subtitle"><?= h((string) $t['type_stage']) === 'pfe' ? 'Projet de fin d\'études (PFE)' : 'Stage en entreprise' ?> — Année <?= h((string) $t['annee_scolaire']) ?></p>

    <section class="paper-section">
        <h2>Stagiaire</h2>
        <dl class="paper-info">
            <dt>Nom &amp; prénom</dt><dd><?= h((string) $t['nom'] . ' ' . (string) $t['prenom']) ?></dd>
            <dt>Matricule</dt><dd><?= h((string) $t['matricule']) ?></dd>
            <dt>CIN</dt><dd><?= h((string) ($t['cin'] ?? '')) ?></dd>
            <dt>Classe / filière</dt><dd><?= h((string) $t['nom_classe'] . ' — ' . (string) $t['nom_filiere']) ?></dd>
            <dt>Adresse</dt><dd><?= h((string) ($t['adresse'] ?? '')) ?></dd>
        </dl>
    </section>

    <section class="paper-section">
        <h2>Organisme d'accueil</h2>
        <dl class="paper-info">
            <dt>Entreprise / organisme</dt><dd><?= h((string) ($t['entreprise'] ?? '')) ?></dd>
            <dt>Sujet du stage</dt><dd><?= h((string) ($t['sujet'] ?? '')) ?></dd>
        </dl>
    </section>

    <section class="paper-section">
        <h2>Période</h2>
        <dl class="paper-info">
            <dt>Date de début</dt><dd><?= h((string) ($t['date_debut'] ?? '')) ?></dd>
            <dt>Date de fin</dt><dd><?= h((string) ($t['date_fin'] ?? '')) ?></dd>
            <?php if (!empty($t['date_soutenance'])): ?>
            <dt>Date de soutenance</dt><dd><?= h((string) $t['date_soutenance']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($t['jury'])): ?>
            <dt>Jury / modalités</dt><dd><?= nl2br(h((string) $t['jury'])) ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="paper-section paper-engagements">
        <h2>Engagements</h2>
        <p class="paper-lead">Le stagiaire s'engage à respecter le règlement intérieur de l'organisme d'accueil et la confidentialité des informations auxquelles il aura accès. L'organisme d'accueil s'engage à assurer un encadrement pédagogique adéquat.</p>
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
