<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'index';
$pageTitle = 'Tableau de bord';
require __DIR__ . '/includes/header.php';

$moisCourant = date('Y-m');
$nbStag = (int) $pdo->query('SELECT COUNT(*) FROM stagiaires')->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM mensualites WHERE mois_ref = ? AND est_paye = 1');
$st->execute([$moisCourant]);
$nbPayeCeMois = (int) $st->fetchColumn();
$nbSansCotisation = max(0, $nbStag - $nbPayeCeMois);
$nbDemandes = (int) $pdo->query("SELECT COUNT(*) FROM demandes_inscription WHERE statut = 'en_attente'")->fetchColumn();

$stats = [
    ['k' => 'Stagiaires', 'n' => $nbStag],
    ['k' => 'Demandes inscription (attente)', 'n' => $nbDemandes],
    ['k' => 'Évaluations (notes)', 'n' => (int) $pdo->query('SELECT COUNT(*) FROM evaluer')->fetchColumn()],
    ['k' => 'Cotisations payées (' . $moisCourant . ')', 'n' => $nbPayeCeMois],
    ['k' => 'Sans cotisation ce mois', 'n' => $nbSansCotisation],
    ['k' => 'Absences (30j)', 'n' => (int) $pdo->query("SELECT COUNT(*) FROM absences WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn()],
];
?>
<div class="dash-layout">
    <div class="dash-main">
        <div class="card">
            <h2>Couverture cahier des charges §4.1</h2>
            <p style="margin:0;font-size:0.95rem;">
                <strong>Inscription en ligne</strong> (candidats : <a href="inscription.php" target="_blank" rel="noopener">formulaire public</a> → file d’attente jusqu’à validation),
                <strong>saisie directe</strong> par l’admin sur Stagiaires,
                <strong>cotisation mensuelle</strong> (marquage payé / non payé par mois sur la liste des stagiaires — pas d’entité ECHEANCE Merise),
                <strong>pédagogie</strong> (notes de synthèse, moyennes auto, absences, stages/PFE/soutenance),
                <strong>documents officiels</strong> imprimables (certificat, relevé, bulletin, billet, convention, attestation, état des cotisations).
                Référentiel pédagogique (filières, classes, modules) : données en <strong>base uniquement</strong>. Le <strong>CRUD stagiaire</strong> reste sur <strong>Stagiaires</strong>.
                <strong>Accès</strong> : espace admin après <code>login.php</code> ; formulaire candidat sans compte sur <code>inscription.php</code>.
            </p>
        </div>
        <div class="stat-grid">
            <?php foreach ($stats as $s): ?>
                <div class="stat-card">
                    <div class="stat-value"><?= (string) $s['n'] ?></div>
                    <div class="stat-label"><?= h($s['k']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2>Raccourcis</h2>
            <div class="gds-shortcut-row">
                <a class="gds-shortcut" href="demandes_inscription.php"><i class="fa-solid fa-clock" aria-hidden="true"></i> Demandes d’inscription</a>
                <a class="gds-shortcut" href="stagiaires.php"><i class="fa-solid fa-users" aria-hidden="true"></i> Stagiaires (cotisation du mois)</a>
                <a class="gds-shortcut" href="moyennes.php"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Moyennes</a>
                <a class="gds-shortcut" href="alertes.php"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Alertes</a>
                <a class="gds-shortcut" href="documents_officiels.php"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Documents officiels</a>
            </div>
        </div>
    </div>
    <aside class="dash-aside no-print">
        <div class="card">
            <h2>Action rapide</h2>
            <a class="btn btn-accent" href="documents_officiels.php"><i class="fa-solid fa-file-lines"></i> Documents officiels</a>
            <a class="btn btn-outline" href="stagiaires.php"><i class="fa-solid fa-user-plus"></i> Liste stagiaires</a>
        </div>
        <div class="card" style="border-color:rgba(236,72,153,0.35);">
            <h2 style="color:#fbcfe8;">Alertes</h2>
            <p style="margin:0;font-size:0.9rem;">Consultez les <a href="alertes.php">impayés et absences</a> depuis le module Alertes.</p>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
