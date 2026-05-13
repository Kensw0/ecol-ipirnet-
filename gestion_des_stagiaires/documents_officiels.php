<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'officiels';
$pageTitle = 'Documents officiels (génération)';
require __DIR__ . '/includes/header.php';

$sid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stag = $pdo->query('SELECT id_stagiaire, matricule, nom, prenom FROM stagiaires ORDER BY nom, prenom')->fetchAll();

if ($sid <= 0): ?>
<div class="card">
    <p style="margin:0 0 1rem;color:var(--muted);">Choisissez un stagiaire pour accéder aux modèles imprimables prévus au CDC §4.1 (certificat, relevé, bulletin, billet d’excuse, attestation, convention, état des cotisations mensuelles).</p>
    <table class="data">
        <tr><th>Matricule</th><th>Nom</th><th></th></tr>
        <?php foreach ($stag as $s): ?>
            <tr>
                <td><?= h((string) $s['matricule']) ?></td>
                <td><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?></td>
                <td><a class="btn secondary" href="documents_officiels.php?id=<?= (int) $s['id_stagiaire'] ?>">Documents</a></td>
            </tr>
        <?php endforeach; ?>
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
?>
<div class="card">
    <h2><?= h((string) $s['nom'] . ' ' . (string) $s['prenom']) ?> — <?= h((string) $s['matricule']) ?></h2>
    <p class="link-row no-print">
        <a href="print_certificat_scolarite.php?id=<?= $sid ?>" target="_blank">Certificat de scolarité</a>
        <a href="print_releve_notes.php?id=<?= $sid ?>" target="_blank">Relevé de notes</a>
        <a href="print_attestation_reussite.php?id=<?= $sid ?>" target="_blank">Attestation de réussite</a>
        <a href="print_etat_paiement.php?id=<?= $sid ?>" target="_blank">État des cotisations (par mois)</a>
    </p>
    <p><strong>Bulletin par module</strong> :</p>
    <ul class="no-print">
        <?php foreach ($mlist as $m): ?>
            <li><a href="print_bulletin.php?id=<?= $sid ?>&amp;mid=<?= (int) $m['id_module'] ?>" target="_blank"><?= h((string) $m['nom_module']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <p><strong>Billets d’excuse</strong> : depuis la liste des <a href="absences.php">absences</a> (lien par ligne).</p>
    <p><strong>Convention de stage</strong> : depuis la liste des <a href="stages.php">stages</a>.</p>
    <p><strong>Cotisation du mois</strong> : marquage sur la page <a href="stagiaires.php">Stagiaires</a> (bouton « Payé ce mois »).</p>
    <p class="no-print"><a class="btn secondary" href="documents_officiels.php">Autre stagiaire</a></p>
</div>
<div class="card">
    <h2>Historique des générations</h2>
    <?php
    $h = $pdo->prepare('SELECT * FROM documents_generes WHERE id_stagiaire = ? ORDER BY genere_le DESC LIMIT 30');
    $h->execute([$sid]);
    $hist = $h->fetchAll();
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
