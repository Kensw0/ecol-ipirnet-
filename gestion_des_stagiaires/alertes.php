<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'alertes';
$pageTitle = 'Alertes';
require __DIR__ . '/includes/header.php';

$moisCourant = date('Y-m');
$st = $pdo->prepare(
    'SELECT COUNT(*) FROM stagiaires s
     LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1
     WHERE m.id_mensualite IS NULL'
);
$st->execute([$moisCourant]);
$sansCotisation = (int) $st->fetchColumn();

$abs = $pdo->query("SELECT COUNT(*) FROM absences WHERE est_justifiee=0 AND date_absence >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();

$rowsCotis = $pdo->prepare(
    'SELECT s.matricule, s.nom, s.prenom FROM stagiaires s
     LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1
     WHERE m.id_mensualite IS NULL
     ORDER BY s.nom, s.prenom LIMIT 30'
);
$rowsCotis->execute([$moisCourant]);
$listeSans = $rowsCotis->fetchAll();

$rowsAbs = $pdo->query('SELECT a.date_absence, s.matricule, s.nom FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire WHERE a.est_justifiee=0 AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) ORDER BY a.date_absence DESC LIMIT 20')->fetchAll();
?>
<div class="grid-stats">
    <div class="stat"><div class="n"><?= $sansCotisation ?></div><div class="k">Sans cotisation payée (<?= h($moisCourant) ?>)</div></div>
    <div class="stat"><div class="n"><?= (int) $abs ?></div><div class="k">Absences non justifiées (14j)</div></div>
</div>
<div class="card">
    <h2>Stagiaires sans cotisation marquée pour <?= h($moisCourant) ?></h2>
    <p style="margin:0 0 1rem;color:var(--muted);font-size:0.9rem;">Marquez « Payé ce mois » sur la page <a href="stagiaires.php?mois=<?= urlencode($moisCourant) ?>">Stagiaires</a>.</p>
    <table class="data">
        <tr><th>Matricule</th><th>Nom</th></tr>
        <?php foreach ($listeSans as $r): ?>
            <tr>
                <td><?= h((string) $r['matricule']) ?></td>
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$listeSans): ?><tr><td colspan="2">Aucun (tous marqués payés pour ce mois).</td></tr><?php endif; ?>
    </table>
</div>
<div class="card">
    <h2>Absences non justifiées (14 derniers jours)</h2>
    <table class="data">
        <tr><th>Date</th><th>Matricule</th><th>Nom</th></tr>
        <?php foreach ($rowsAbs as $r): ?>
            <tr>
                <td><?= h((string) $r['date_absence']) ?></td>
                <td><?= h((string) $r['matricule']) ?></td>
                <td><?= h((string) $r['nom']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rowsAbs): ?><tr><td colspan="3">Aucune.</td></tr><?php endif; ?>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
