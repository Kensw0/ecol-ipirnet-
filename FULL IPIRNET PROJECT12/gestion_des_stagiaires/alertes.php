<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$moisCourant = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['marquer_paye_id'])) {
        $sid = (int)$_POST['marquer_paye_id'];
        $stCheck = $pdo->prepare('SELECT id_mensualite FROM mensualites WHERE id_stagiaire=? AND mois_ref=?');
        $stCheck->execute([$sid, $moisCourant]);
        $mid = $stCheck->fetchColumn();
        if ($mid) {
            $pdo->prepare('UPDATE mensualites SET est_paye=1, marque_le=NOW() WHERE id_mensualite=?')->execute([$mid]);
        } else {
            $pdo->prepare('INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, marque_le) VALUES (?, ?, 1, NOW())')->execute([$sid, $moisCourant]);
        }
        flash_set('Cotisation enregistrée avec succès.');
        redirect('alertes.php');
    }
    if (isset($_POST['justifier_absence_id'])) {
        $aid = (int)$_POST['justifier_absence_id'];
        $pdo->prepare("UPDATE absences SET est_justifiee=1, justificatif='Justifié rapidement via tableau de bord Alertes' WHERE id_absence=?")->execute([$aid]);
        flash_set('Absence marquée comme justifiée.');
        redirect('alertes.php');
    }
}

$curPage = 'alertes';
$pageTitle = 'Alertes';
require __DIR__ . '/includes/header.php';

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM stagiaires s
     LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1
     WHERE m.id_mensualite IS NULL'
);
$st->execute([$moisCourant]);
$sansCotisationCount = (int) $st->fetchColumn();

$absCount = (int) $pdo->query("SELECT COUNT(*) FROM absences WHERE est_justifiee=0 AND date_absence >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();

$rapportsCount = (int) $pdo->query("SELECT COUNT(*) FROM stages WHERE (rapport_url IS NULL OR rapport_url='') AND date_fin < CURDATE()")->fetchColumn();

$rowsCotis = $pdo->prepare(
    'SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom FROM stagiaires s
     LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1
     WHERE m.id_mensualite IS NULL
     ORDER BY s.nom, s.prenom LIMIT 30'
);
$rowsCotis->execute([$moisCourant]);
$listeSans = $rowsCotis->fetchAll();

$rowsAbs = $pdo->query('
    SELECT a.id_absence, a.date_absence, s.id_stagiaire, s.num_inscri, s.nom, s.prenom, f.nom_filiere 
    FROM absences a 
    JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire 
    JOIN classes c ON c.id_classe = s.id_classe
    JOIN filieres f ON f.id_filiere = c.id_filiere
    WHERE a.est_justifiee=0 AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
    ORDER BY a.date_absence DESC LIMIT 30
')->fetchAll();

// Count absences per stagiaire this month
$absCountPerStagiaire = [];
$firstDayOfMonth = date('Y-m-01');
$stmtAbsCounts = $pdo->query("SELECT id_stagiaire, COUNT(*) as c FROM absences WHERE date_absence >= '$firstDayOfMonth' GROUP BY id_stagiaire");
foreach($stmtAbsCounts->fetchAll() as $row) {
    $absCountPerStagiaire[$row['id_stagiaire']] = (int)$row['c'];
}

function getAvatarInitials($nom, $prenom) {
    return strtoupper(mb_substr($prenom ?? '', 0, 1) . mb_substr($nom ?? '', 0, 1));
}

function getSeverityColorCSS($count, $isCotis = false) {
    if ($count == 0) return '#10b981'; // Green (OK)
    if ($count <= ($isCotis ? 2 : 4)) return '#f59e0b'; // Orange (Attention)
    return '#ef4444'; // Red (Critique)
}

function getSeverityLabel($count, $isCotis = false) {
    if ($count == 0) return 'OK';
    if ($count <= ($isCotis ? 2 : 4)) return 'Attention';
    return 'Critique';
}

$hasAlerts = ($sansCotisationCount > 0 || $absCount > 0 || $rapportsCount > 0);

// Define days overdue for cotisations (just an approximation based on the 10th of the month)
$dueDay = 10;
$todayDay = (int)date('d');
$daysOverdue = max(0, $todayDay - $dueDay);
$isLate = $daysOverdue > 0;
?>

<!-- Add pulse animation definition -->
<style>
@keyframes pulseGlow {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.5); }
    100% { opacity: 0; transform: scale(2); }
}
.pulse-dot {
    position: relative;
    width: 12px; height: 12px;
    border-radius: 50%;
    display: inline-block;
}
.pulse-dot::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: inherit;
    animation: pulseGlow 1.5s infinite;
}
</style>

<!-- Header Row with Title and Refresh -->
<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:2rem;">
    <div>
        <h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.5rem; margin-bottom: 0.25rem; display:flex; align-items:center; gap:0.75rem;">
            Alertes Système 
            <?php if($hasAlerts): ?>
                <span class="pulse-dot" style="background:#ef4444; width:14px; height:14px;"></span>
            <?php endif; ?>
        </h1>
        <p style="color:var(--muted); font-size:0.95rem; margin:0;">Supervision active des anomalies, retards de paiement et absences.</p>
    </div>
    
    <div style="text-align:right;">
        <button onclick="window.location.reload()" class="btn btn-outline" style="border-radius:20px; font-size:0.85rem; padding:0.4rem 1rem;">
            <i class="fa-solid fa-rotate-right"></i> Rafraîchir
        </button>
    </div>
</div>

<?php if (!$hasAlerts): ?>
    <div class="card" style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding: 5rem 2rem; border-color: rgba(16,185,129,0.2); background: linear-gradient(180deg, rgba(16,185,129,0.05) 0%, transparent 100%);">
        <div style="width:100px; height:100px; border-radius:50%; background:rgba(16,185,129,0.15); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:3rem; margin-bottom:1.5rem; box-shadow: 0 0 40px rgba(16,185,129,0.2);">
            <i class="fa-solid fa-check"></i>
        </div>
        <h2 style="font-size: 2rem; color:#fff; margin-bottom:0.5rem;">Tout est en ordre ✅</h2>
        <p style="color:#a1a1aa; max-width:400px;">Aucune absence à justifier, aucune cotisation en retard et tous les rapports sont remis. Beau travail !</p>
    </div>
<?php else: ?>

    <!-- STATS CARDS -->
    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
        
        <!-- Cotisations Card -->
        <?php $c1C = getSeverityColorCSS($sansCotisationCount, true); ?>
        <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid <?= $c1C ?>40; box-shadow: 0 10px 30px -10px <?= $c1C ?>40; background: linear-gradient(180deg, <?= $c1C ?>1A 0%, rgba(255,255,255,0.02) 100%);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $c1C ?>26; color: <?= $c1C ?>; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <?php if($sansCotisationCount > 0): ?>
                    <span class="pulse-dot" style="background:<?= $c1C ?>;"></span>
                <?php endif; ?>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: <?= $c1C ?>; line-height:1; margin-bottom:0.25rem;">
                <?= $sansCotisationCount ?>
            </div>
            <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600; margin-bottom:0.5rem;">Sans cotisation payée</div>
            
            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.05);">
                <span style="width:8px; height:8px; border-radius:50%; background:<?= $c1C ?>;"></span>
                <span style="font-size:0.75rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.05em; font-weight:700;"><?= getSeverityLabel($sansCotisationCount, true) ?></span>
            </div>
        </div>

        <!-- Absences Card -->
        <?php $c2C = getSeverityColorCSS($absCount); ?>
        <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid <?= $c2C ?>40; box-shadow: 0 10px 30px -10px <?= $c2C ?>40; background: linear-gradient(180deg, <?= $c2C ?>1A 0%, rgba(255,255,255,0.02) 100%);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $c2C ?>26; color: <?= $c2C ?>; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                    <i class="fa-solid fa-siren-on"></i>
                </div>
                <?php if($absCount > 0): ?>
                    <span class="pulse-dot" style="background:<?= $c2C ?>;"></span>
                <?php endif; ?>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: <?= $c2C ?>; line-height:1; margin-bottom:0.25rem;">
                <?= $absCount ?>
            </div>
            <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600; margin-bottom:0.5rem;">Absences non justifiées (14j)</div>
            
            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.05);">
                <span style="width:8px; height:8px; border-radius:50%; background:<?= $c2C ?>;"></span>
                <span style="font-size:0.75rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.05em; font-weight:700;"><?= getSeverityLabel($absCount) ?></span>
            </div>
        </div>

        <!-- Rapports Card -->
        <?php $c3C = getSeverityColorCSS($rapportsCount, true); ?>
        <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid <?= $c3C ?>40; box-shadow: 0 10px 30px -10px <?= $c3C ?>40; background: linear-gradient(180deg, <?= $c3C ?>1A 0%, rgba(255,255,255,0.02) 100%);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $c3C ?>26; color: <?= $c3C ?>; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                    <i class="fa-solid fa-file-circle-exclamation"></i>
                </div>
                <?php if($rapportsCount > 0): ?>
                    <span class="pulse-dot" style="background:<?= $c3C ?>;"></span>
                <?php endif; ?>
            </div>
            <div style="font-size: 2.5rem; font-weight: 800; color: <?= $c3C ?>; line-height:1; margin-bottom:0.25rem;">
                <?= $rapportsCount ?>
            </div>
            <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600; margin-bottom:0.5rem;">Rapports manquants (Stages terminés)</div>
            
            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.05);">
                <span style="width:8px; height:8px; border-radius:50%; background:<?= $c3C ?>;"></span>
                <span style="font-size:0.75rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.05em; font-weight:700;"><?= getSeverityLabel($rapportsCount, true) ?></span>
            </div>
        </div>

    </div>

    <div style="display:grid; grid-template-columns: 1fr; gap:2rem;">
        
        <!-- COTISATIONS TABLE -->
        <?php if($listeSans): ?>
        <div class="card" style="padding:0; overflow:hidden; border: 1px solid rgba(249,115,22,0.2);">
            <div style="padding:1.5rem; border-bottom:1px solid rgba(255,255,255,0.05); background:rgba(249,115,22,0.05); display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0; font-size:1.25rem; color:#fb923c;"><i class="fa-solid fa-file-invoice-dollar" style="margin-right:0.5rem;"></i> Retards de cotisations (<?= h($moisCourant) ?>)</h2>
                <a href="print_liste_impayes.php" target="_blank" class="btn" style="background:#ef4444; color:#fff; border:none; padding:0.5rem 1rem; font-size:0.85rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem; text-decoration:none;">
                     <i class="fa-solid fa-print"></i> Imprimer la liste
                </a>
            </div>
            <div class="table-container" style="border:none; border-radius:0;">
                <table class="data">
                    <thead style="background:transparent;">
                        <tr>
                            <th style="padding-left:1.5rem;">Stagiaire</th>
                            <th>Statut Mensualité</th>
                            <th style="text-align:right; padding-right:1.5rem;">Action Rapide</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listeSans as $idx => $r): ?>
                            <?php $bg = "background-color: rgba(239, 68, 68, 0.03);"; ?>
                            <tr style="<?= $bg ?> border-bottom: 1px solid rgba(239, 68, 68, 0.08);">
                                <td style="padding-left:1.5rem; display:flex; align-items:center; gap:1rem;">
                                    <div style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:bold; color:#e4e4e7;">
                                        <?= getAvatarInitials($r['nom'], $r['prenom']) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600; color:#fff; font-size:0.95rem;"><?= h((string)$r['nom'] . ' ' . (string)$r['prenom']) ?></div>
                                        <div style="font-family:monospace; color:#a1a1aa; font-size:0.8rem;"><?= h((string)$r['num_inscri']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($isLate): ?>
                                        <span class="badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3);"><i class="fa-solid fa-clock"></i> <?= $daysOverdue ?>j. en retard</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:rgba(250,204,21,0.15); color:#facc15; border:1px solid rgba(250,204,21,0.3);">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; padding-right:1.5rem;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="marquer_paye_id" value="<?= (int) $r['id_stagiaire'] ?>">
                                        <button type="submit" class="btn" style="background:#10b981; color:#fff; padding:0.4rem 0.8rem; font-size:0.85rem;" title="Marquer le mois courant comme payé">
                                            <i class="fa-solid fa-check-double"></i> Marquer Payé
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABSENCES TABLE -->
        <?php if($rowsAbs): ?>
        <div class="card" style="padding:0; overflow:hidden; border: 1px solid rgba(239,68,68,0.2);">
            <div style="padding:1.5rem; border-bottom:1px solid rgba(255,255,255,0.05); background:rgba(239,68,68,0.05);">
                <h2 style="margin:0; font-size:1.25rem; color:#f87171;"><i class="fa-solid fa-user-xmark" style="margin-right:0.5rem;"></i> Absences Critiques (14 derniers jours)</h2>
            </div>
            <div class="table-container" style="border:none; border-radius:0;">
                <table class="data">
                    <thead style="background:transparent;">
                        <tr>
                            <th style="padding-left:1.5rem;">Date Absence</th>
                            <th>Stagiaire</th>
                            <th>Filière</th>
                            <th>Niveau d'Alerte</th>
                            <th style="text-align:right; padding-right:1.5rem;">Action Rapide</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsAbs as $r): ?>
                            <?php 
                                $c = $absCountPerStagiaire[$r['id_stagiaire']] ?? 1;
                                $isRepeated = $c >= 3;
                                $bg = $isRepeated ? "background-color: rgba(245, 158, 11, 0.05);" : "background-color: transparent;";
                            ?>
                            <tr style="<?= $bg ?> border-bottom: 1px solid rgba(255, 255, 255, 0.03);">
                                <td style="padding-left:1.5rem; font-weight:bold; color:#e4e4e7; font-family:monospace;">
                                    <?= h((string) $r['date_absence']) ?>
                                    <div style="font-size:0.75rem; color:#a1a1aa; font-family:sans-serif; font-weight:normal;">Non justifiée</div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:#fff; font-size:0.95rem;"><?= h((string)$r['nom'] . ' ' . (string)$r['prenom']) ?></div>
                                    <div style="font-family:monospace; color:#a1a1aa; font-size:0.8rem;"><?= h((string)$r['num_inscri']) ?></div>
                                </td>
                                <td style="color:#a1a1aa; font-size:0.85rem;">
                                    <?= h(gds_filiere_code((string)$r['nom_filiere'])) ?>
                                </td>
                                <td>
                                    <?php if($c >= 5): ?>
                                        <span class="badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3);">Élevé (<?= $c ?> abs. ce mois)</span>
                                    <?php elseif($c >= 3): ?>
                                        <span class="badge" style="background:rgba(249,115,22,0.15); color:#fb923c; border:1px solid rgba(249,115,22,0.3);">Moyen (<?= $c ?> abs. ce mois)</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:rgba(250,204,21,0.15); color:#facc15; border:1px solid rgba(250,204,21,0.3);">Faible (<?= $c ?> abs. ce mois)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; padding-right:1.5rem;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="justifier_absence_id" value="<?= (int) $r['id_absence'] ?>">
                                        <button type="submit" class="btn" style="background:#3b82f6; color:#fff; padding:0.4rem 0.8rem; font-size:0.85rem;" title="Marquer justifiée">
                                            <i class="fa-solid fa-clipboard-check"></i> Justifier
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
