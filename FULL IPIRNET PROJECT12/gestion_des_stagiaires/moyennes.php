<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'moyennes';
$pageTitle = 'Moyennes Générales et Classement';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$filiereFilter = isset($_GET['filiere']) ? (int)$_GET['filiere'] : 0;

$query = "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, c.nom_classe, f.nom_filiere, f.id_filiere,
            ROUND(SUM(v.moyenne_module * v.coefficient) / SUM(v.coefficient), 2) AS moyenne_generale,
            COUNT(v.id_module) AS total_evaluations
       FROM stagiaires s
       JOIN classes c ON c.id_classe = s.id_classe
       JOIN filieres f ON f.id_filiere = c.id_filiere
       JOIN v_moyennes_par_module v ON v.id_stagiaire = s.id_stagiaire AND v.moyenne_module IS NOT NULL";

$params = [];
if ($filiereFilter > 0) {
    $query .= " WHERE f.id_filiere = ?";
    $params[] = $filiereFilter;
}

$query .= " GROUP BY s.id_stagiaire, s.num_inscri, s.nom, s.prenom, c.nom_classe, f.nom_filiere, f.id_filiere
            ORDER BY moyenne_generale DESC";

$st = $pdo->prepare($query);
$st->execute($params);
$rows = $st->fetchAll();

// Calculate Stats
$cnt = count($rows);
$sum = 0; $min = 20; $max = 0;
foreach($rows as $r) {
  $val = (float)$r['moyenne_generale'];
  $sum += $val;
  if($val < $min) $min = $val;
  if($val > $max) $max = $val;
}
$avg = $cnt > 0 ? $sum / $cnt : 0;
if ($cnt === 0) { $min = 0; $max = 0; }

function getAppreciation($note) {
    if ($note >= 16) return '<span class="badge" style="background:rgba(16, 185, 129, 0.2); color:#10b981; border: 1px solid rgba(16, 185, 129, 0.4);">Très Bien</span>';
    if ($note >= 14) return '<span class="badge" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6; border: 1px solid rgba(59, 130, 246, 0.4);">Bien</span>';
    if ($note >= 12) return '<span class="badge" style="background:rgba(234, 179, 8, 0.2); color:#facc15; border: 1px solid rgba(234, 179, 8, 0.4);">Assez Bien</span>';
    if ($note >= 10) return '<span class="badge" style="background:rgba(249, 115, 22, 0.2); color:#f97316; border: 1px solid rgba(249, 115, 22, 0.4);">Passable</span>';
    return '<span class="badge" style="background:rgba(239, 68, 68, 0.2); color:#f87171; border: 1px solid rgba(239, 68, 68, 0.4);">Insuffisant</span>';
}

function getRankBadge($rank) {
    if ($rank === 1) return '<span style="font-size:1.6rem; filter: drop-shadow(0 0 5px rgba(250,204,21,0.6));">🥇</span>';
    if ($rank === 2) return '<span style="font-size:1.6rem; filter: drop-shadow(0 0 5px rgba(156,163,175,0.6));">🥈</span>';
    if ($rank === 3) return '<span style="font-size:1.6rem; filter: drop-shadow(0 0 5px rgba(180,83,9,0.6));">🥉</span>';
    return '<span style="color:#a1a1aa; font-weight:bold; font-size:1.1rem;">#' . $rank . '</span>';
}

function generateInitials($nom, $prenom) {
    return strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
}

$top3 = array_slice($rows, 0, 3);
// Layout the podium (2nd, 1st, 3rd)
$podiumClasses = ['podium-silver', 'podium-gold', 'podium-bronze'];
$podiumIndexes = [];
if (isset($top3[1])) $podiumIndexes[] = 1;
if (isset($top3[0])) $podiumIndexes[] = 0;
if (isset($top3[2])) $podiumIndexes[] = 2;
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Classement et Moyennes</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:1.5rem;">Aperçu des performances globales. Visualisez les écarts et identifiez rapidement les majors de promotion.</p>

<section class="gds-filter-bar no-print" style="margin-bottom:2rem;">
    <header class="gds-filter-bar__header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="gds-filter-bar__title">Filtrer par Filière</h3>
    </header>
    <form method="get" action="moyennes.php" style="display:flex; gap:1rem; align-items:flex-end; padding:1.25rem;">
        <label style="flex:1;">
            <select name="filiere" onchange="this.form.submit()" style="width:100%; border-radius:8px; padding:0.6rem; background:rgba(0,0,0,0.25); color:#fff; border:1px solid rgba(255,255,255,0.1);">
                <option value="0">— Toutes les filières —</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= $f['id_filiere'] ?>" <?= $filiereFilter === (int)$f['id_filiere'] ? 'selected' : '' ?>>
                        <?= h($f['nom_filiere']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<!-- Quick Stats Bar -->
<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2.5rem;">
    <div class="stat-card" style="align-items:center; text-align:center;">
        <div class="stat-label">Moyenne Globale</div>
        <div class="stat-value" style="color:#a855f7;"><?= number_format($avg, 2, ',', '') ?></div>
    </div>
    <div class="stat-card" style="align-items:center; text-align:center;">
        <div class="stat-label">Meilleure Note</div>
        <div class="stat-value" style="color:#10b981;"><?= number_format($max, 2, ',', '') ?></div>
    </div>
    <div class="stat-card" style="align-items:center; text-align:center;">
        <div class="stat-label">Note Minimum</div>
        <div class="stat-value" style="color:#ef4444;"><?= number_format($min, 2, ',', '') ?></div>
    </div>
</div>

<?php if (count($top3) > 0): ?>
    <!-- TOP 3 PODIUM -->
    <div style="display:flex; justify-content:center; align-items:flex-end; gap:1.5rem; margin-bottom:3rem; padding-top:2rem;">
        <?php foreach ($podiumIndexes as $idx): ?>
            <?php 
                $r = $top3[$idx];
                $mg = (float)$r['moyenne_generale'];
                $isGold = $idx === 0;
                $height = $isGold ? '220px' : ($idx === 1 ? '180px' : '150px');
                $border = $isGold ? '#facc15' : ($idx === 1 ? '#9ca3af' : '#b45309');
                $bgGrad = $isGold ? 'linear-gradient(to top, rgba(250,204,21,0.2), transparent)' : 'rgba(255,255,255,0.03)';
            ?>
            <div class="card" style="width:240px; height:<?= $height ?>; background:<?= $bgGrad ?>; border-top: 4px solid <?= $border ?>; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:1.5rem; position:relative; box-shadow: 0 10px 30px -10px <?= $border ?>40; transition: transform 0.3s; cursor:default;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="position:absolute; top:-25px;"><?= getRankBadge($idx + 1) ?></div>
                <div style="font-weight:700; color:#fff; font-size:1.1rem; margin-bottom:0.25rem;">
                    <?= h(strtoupper((string)$r['nom']) . ' ' . $r['prenom']) ?>
                </div>
                <div style="font-size:0.75rem; color:#a1a1aa; margin-bottom:1rem;"><?= h((string)$r['nom_filiere']) ?></div>
                <div style="font-size:1.5rem; font-weight:800; color:<?= $border ?>;">
                    <?= number_format($mg, 2, ',', '') ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card table-container" style="padding:0;">
    <table class="data">
        <thead>
            <tr>
                <th style="width: 80px; text-align: center;">Rang</th>
                <th>Stagiaire</th>
                <th>Filière & Classe</th>
                <th style="text-align:center;">Moyenne Générale</th>
                <th style="text-align:center;">Mention</th>
                <th class="no-print" style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $ranking = 1; ?>
            <?php foreach ($rows as $r): ?>
                <?php 
                    $mg = (float)$r['moyenne_generale']; 
                    $pct = min(100, max(0, ($mg / 20) * 100));
                    $barColor = $mg >= 16 ? '#10b981' : ($mg >= 12 ? '#3b82f6' : ($mg >= 10 ? '#facc15' : '#ef4444'));
                ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td style="text-align: center;">
                        <?= getRankBadge($ranking++) ?>
                    </td>
                    <td>
                        <div style="font-weight: 600; color:#e4e4e7;"><?= h(strtoupper((string) $r['nom']) . ' ' . ucfirst(strtolower((string) $r['prenom']))) ?></div>
                        <div style="font-size: 0.8rem; color: #71717a;; font-family:monospace;"><?= h((string) $r['num_inscri']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 500; font-size: 0.9rem; color:#cbd5e1;"><?= h((string) $r['nom_filiere']) ?></div>
                        <div style="font-size: 0.8rem; color: #71717a; margin-top:2px;"><i class="fa-solid fa-graduation-cap"></i> <?= h((string) $r['nom_classe']) ?></div>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; flex-direction:column; align-items:center; gap:0.4rem;">
                            <span style="font-size: 1.15rem; font-weight: bold; color: <?= $mg < 10 ? '#ef4444' : '#fff' ?>;">
                                <?= number_format($mg, 2, ',', '') ?> <span style="font-size:0.8rem; color:#a1a1aa; font-weight:normal;">/ 20</span>
                            </span>
                            <div style="width: 80px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; display:flex;">
                                <div style="height:100%; background: <?= $barColor ?>; width: <?= $pct ?>%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <?= getAppreciation($mg) ?>
                    </td>
                    <td class="link-row no-print" style="text-align:right;">
                        <a href="print_releve_notes.php?id=<?= (int)$r['id_stagiaire'] ?>&mode=combined" target="_blank" class="icon-btn" title="Imprimer Complet" style="color:#60a5fa;">
                            <i class="fa-solid fa-print"></i>
                        </a>
                        <a href="print_releve_notes.php?id=<?= (int)$r['id_stagiaire'] ?>&mode=controle" target="_blank" class="icon-btn" title="Imprimer Contrôles" style="color:#f59e0b;">
                            <i class="fa-solid fa-list-check"></i>
                        </a>
                        <a href="print_releve_notes.php?id=<?= (int)$r['id_stagiaire'] ?>&mode=examen" target="_blank" class="icon-btn" title="Imprimer Examens" style="color:#d97706;">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 4rem; color: var(--muted);">
                        <i class="fa-solid fa-medal" style="font-size:3rem; opacity:0.2; margin-bottom:1rem; display:block;"></i>
                        <em>Aucun classement disponible. Veuillez vérifier vos notes ou filtres.</em>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
