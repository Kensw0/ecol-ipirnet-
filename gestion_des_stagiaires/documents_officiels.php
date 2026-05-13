<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'officiels';
$pageTitle = 'Documents officiels (génération)';
require __DIR__ . '/includes/header.php';

$sid = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Quick stagiaire search/filter
$qStag = trim((string) ($_GET['q'] ?? ''));
$sqlList = 'SELECT id_stagiaire, matricule, nom, prenom FROM stagiaires';
$paramsList = [];
if ($qStag !== '') {
    $sqlList .= ' WHERE nom LIKE ? OR prenom LIKE ? OR matricule LIKE ?';
    $like = '%' . $qStag . '%';
    $paramsList = [$like, $like, $like];
}
$sqlList .= ' ORDER BY nom, prenom';
$stStag = $pdo->prepare($sqlList);
$stStag->execute($paramsList);
$stag = $stStag->fetchAll();

if ($sid <= 0): ?>
<div class="card">
    <h2 style="margin-top:0;">Documents officiels — CDC §4.1</h2>
    <p style="margin:0 0 1rem;color:var(--muted);">
        Choisissez un stagiaire pour accéder aux modèles imprimables&nbsp;: fiche d'inscription, certificat,
        relevé, bulletin, billet d'excuse, attestation, convention, reçu et état des cotisations.
    </p>
    <form method="get" action="documents_officiels.php" class="no-print" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <input type="search" name="q" value="<?= h($qStag) ?>" placeholder="Rechercher par nom, prénom ou matricule…" style="flex:1;min-width:220px;">
        <button class="btn" type="submit">Rechercher</button>
        <?php if ($qStag !== ''): ?><a class="btn secondary" href="documents_officiels.php">Réinitialiser</a><?php endif; ?>
    </form>
    <table class="data">
        <tr><th>Matricule</th><th>Nom</th><th></th></tr>
        <?php foreach ($stag as $s): ?>
            <tr>
                <td><?= h((string) $s['matricule']) ?></td>
                <td><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td>
                <td><a class="btn secondary" href="documents_officiels.php?id=<?= (int) $s['id_stagiaire'] ?>">Documents</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$stag): ?><tr><td colspan="3"><em>Aucun stagiaire trouvé.</em></td></tr><?php endif; ?>
    </table>
</div>
<?php else:
    $st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire = ?');
    $st->execute([$sid]);
    $s = $st->fetch();
    if (!$s) {
        flash_set('Stagiaire introuvable.');
        redirect('documents_officiels.php');
    }
    $mods = $pdo->prepare('SELECT m.id_module, m.nom_module FROM modules m JOIN stagiaires st ON st.id_stagiaire = ? JOIN classes c ON c.id_classe = st.id_classe AND c.id_filiere = m.id_filiere ORDER BY m.nom_module');
    $mods->execute([$sid]);
    $mlist = $mods->fetchAll();
    $absList = $pdo->prepare('SELECT id_absence, date_absence FROM absences WHERE id_stagiaire = ? ORDER BY date_absence DESC LIMIT 12');
    $absList->execute([$sid]);
    $absRows = $absList->fetchAll();
    $stgList = $pdo->prepare('SELECT id_stage, type_stage, entreprise, date_debut FROM stages WHERE id_stagiaire = ? ORDER BY date_debut DESC');
    $stgList->execute([$sid]);
    $stgRows = $stgList->fetchAll();
    $curMois = date('Y-m');
?>
<div class="card">
    <h2 style="margin-top:0;"><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?>
        <small style="color:var(--muted);font-weight:400;">(<?= h((string) $s['matricule']) ?> — <?= h((string) $s['nom_classe']) ?>)</small>
    </h2>
    <p class="no-print" style="margin:.25rem 0 1.25rem;"><a class="btn secondary" href="documents_officiels.php">← Autre stagiaire</a></p>

    <div class="doc-grid">
        <a class="doc-tile" href="print_fiche_inscription.php?id=<?= $sid ?>&amp;auto=1" target="_blank">
            <strong>Fiche d'inscription</strong>
            <span>Formulaire d'inscription pré-rempli avec engagement et signatures.</span>
        </a>
        <a class="doc-tile" href="print_certificat_scolarite.php?id=<?= $sid ?>&amp;auto=1" target="_blank">
            <strong>Certificat de scolarité</strong>
            <span>Atteste que l'élève est inscrit pour l'année en cours.</span>
        </a>
        <a class="doc-tile" href="print_releve_notes.php?id=<?= $sid ?>&amp;auto=1" target="_blank">
            <strong>Relevé de notes</strong>
            <span>Toutes les notes par module + moyenne générale.</span>
        </a>
        <a class="doc-tile" href="print_attestation_reussite.php?id=<?= $sid ?>&amp;auto=1" target="_blank">
            <strong>Attestation de réussite</strong>
            <span>Délivrée en fin d'année en cas d'admission.</span>
        </a>
        <a class="doc-tile" href="print_etat_paiement.php?id=<?= $sid ?>&amp;auto=1" target="_blank">
            <strong>État des cotisations</strong>
            <span>Historique mensuel payé / non payé.</span>
        </a>
        <a class="doc-tile" href="print_recu_paiement.php?id=<?= $sid ?>&amp;mois=<?= h($curMois) ?>&amp;auto=1" target="_blank">
            <strong>Reçu de paiement</strong>
            <span>Reçu mensuel imprimable (mois courant&nbsp;: <?= h($curMois) ?>).</span>
        </a>
    </div>

    <h3 style="margin:1.5rem 0 .5rem;">Bulletin par module</h3>
    <?php if ($mlist): ?>
    <div class="doc-grid no-print">
        <?php foreach ($mlist as $m): ?>
            <a class="doc-tile doc-tile--sm" href="print_bulletin.php?id=<?= $sid ?>&amp;mid=<?= (int) $m['id_module'] ?>&amp;auto=1" target="_blank">
                <strong><?= h((string) $m['nom_module']) ?></strong>
                <span>Bulletin du module — impression directe.</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--muted);">Aucun module rattaché à la classe de ce stagiaire.</p><?php endif; ?>

    <h3 style="margin:1.5rem 0 .5rem;">Billets d'excuse (absences récentes)</h3>
    <?php if ($absRows): ?>
    <div class="doc-grid no-print">
        <?php foreach ($absRows as $a): ?>
            <a class="doc-tile doc-tile--sm" href="print_billet_excuse.php?id=<?= (int) $a['id_absence'] ?>&amp;auto=1" target="_blank">
                <strong>Absence du <?= h((string) $a['date_absence']) ?></strong>
                <span>Billet d'excuse imprimable.</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--muted);">Aucune absence enregistrée (voir <a href="absences.php">Absences</a>).</p><?php endif; ?>

    <h3 style="margin:1.5rem 0 .5rem;">Conventions de stage</h3>
    <?php if ($stgRows): ?>
    <div class="doc-grid no-print">
        <?php foreach ($stgRows as $sg): ?>
            <a class="doc-tile doc-tile--sm" href="print_convention_stage.php?id=<?= (int) $sg['id_stage'] ?>&amp;auto=1" target="_blank">
                <strong><?= $sg['type_stage'] === 'pfe' ? 'PFE' : 'Stage' ?> — <?= h((string) ($sg['entreprise'] ?? '—')) ?></strong>
                <span>Début : <?= h((string) ($sg['date_debut'] ?? '')) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--muted);">Aucun stage enregistré (voir <a href="stages.php">Stages</a>).</p><?php endif; ?>
</div>

<div class="card">
    <h2>Historique des générations</h2>
    <?php
    $hQ = $pdo->prepare('SELECT * FROM documents_generes WHERE id_stagiaire = ? ORDER BY genere_le DESC LIMIT 30');
    $hQ->execute([$sid]);
    $hist = $hQ->fetchAll();
    ?>
    <table class="data">
        <tr><th>Date</th><th>Type</th><th>Réf.</th></tr>
        <?php foreach ($hist as $row): ?>
            <tr>
                <td><?= h((string) $row['genere_le']) ?></td>
                <td><?= h((string) $row['type_document']) ?></td>
                <td><?= h((string) ($row['reference'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$hist): ?><tr><td colspan="3">Aucune trace encore.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
