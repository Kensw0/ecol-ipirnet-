<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM absences WHERE id_absence = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Absence supprimée.');
        redirect('absences.php');
    }
    if (isset($_POST['save'])) {
        $da = (string) ($_POST['date_absence'] ?? '');
        $hd = ($_POST['heure_debut'] ?? '') === '' ? null : (string) $_POST['heure_debut'];
        $hf = ($_POST['heure_fin'] ?? '') === '' ? null : (string) $_POST['heure_fin'];
        $ju = trim((string) ($_POST['justificatif'] ?? ''));
        $ej = isset($_POST['est_justifiee']) ? 1 : 0;
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid = ($_POST['id_module'] ?? '') === '' ? null : (int) $_POST['id_module'];
        
        if ($da === '' || $sid <= 0) {
            flash_set('Date et stagiaire requis.');
            redirect('absences.php');
        }
        // Relaxed constraint or kept it? Keep original logic.
        if ($ju !== '' && !preg_match('/[a-zA-ZÀ-ÿ]/', $ju)) {
            flash_set('Erreur : le justificatif doit contenir au moins une lettre.');
            redirect('absences.php');
        }
        
        if (isset($_POST['id_absence']) && (int) $_POST['id_absence'] > 0) {
            $pdo->prepare('UPDATE absences SET date_absence=?, heure_debut=?, heure_fin=?, justificatif=?, est_justifiee=?, id_stagiaire=?, id_module=? WHERE id_absence=?')
                ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid, (int) $_POST['id_absence']]);
            flash_set('Absence mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO absences (date_absence, heure_debut, heure_fin, justificatif, est_justifiee, id_stagiaire, id_module) VALUES (?,?,?,?,?,?,?)')
                ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid]);
            flash_set('Absence ajoutée.');
        }
        redirect('absences.php');
    }
}

$curPage = 'absences';
$pageTitle = 'Gestion des Absences';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag = $pdo->query('SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM absences WHERE id_absence = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT a.*, s.num_inscri, s.nom, s.prenom, f.id_filiere, f.nom_filiere, m.nom_module FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere LEFT JOIN modules m ON m.id_module=a.id_module ORDER BY a.date_absence DESC')->fetchAll();

$absCounts = [];
foreach ($pdo->query('SELECT id_stagiaire, COUNT(*) AS n FROM absences GROUP BY id_stagiaire') as $cr) {
    $absCounts[(int) $cr['id_stagiaire']] = (int) $cr['n'];
}

// Calculate Stats for Current Month
$currentMonthStr = date('Y-m');
$totalCeMois = 0;
$justifieesCeMois = 0;
$nonJustifieesCeMois = 0;

$weeklyCounts = [0, 0, 0, 0, 0]; // Weeks 1-5 Approx

foreach ($rows as $r) {
    if (strpos((string)$r['date_absence'], $currentMonthStr) === 0) {
        $totalCeMois++;
        if ((int)$r['est_justifiee'] === 1) {
            $justifieesCeMois++;
        } else {
            $nonJustifieesCeMois++;
        }
        
        $day = (int)substr((string)$r['date_absence'], 8, 2);
        $wIndex = min(4, (int)floor(($day - 1) / 7));
        $weeklyCounts[$wIndex]++;
    }
}
$maxWeekCount = max(1, max($weeklyCounts));
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Suivi des Absences</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:2rem;">Gérez et justifiez les absences. Maintenez le taux de présence à jour.</p>

<!-- STATS CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-regular fa-clock"></i>
        </div>
        <div>
            <div class="stat-label">Total Absences (Ce Mois)</div>
            <div class="stat-value" style="color:#ef4444;"><?= $totalCeMois ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-check"></i>
        </div>
        <div>
            <div class="stat-label">Justifiées (Ce Mois)</div>
            <div class="stat-value" style="color:#10b981;"><?= $justifieesCeMois ?></div>
        </div>
    </div>

    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <div class="stat-label">Injustifiées (Ce Mois)</div>
            <div class="stat-value" style="color:#f59e0b;"><?= $nonJustifieesCeMois ?></div>
        </div>
    </div>
</div>

<!-- SVG Heatmap / Bar Chart -->
<div class="card" style="margin-bottom: 2rem; display:flex; flex-direction:column; align-items:flex-start;">
    <h3 style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 1rem;">Absences par semaine (Mois en cours)</h3>
    <svg width="400" height="100" style="overflow: visible;">
        <?php foreach ($weeklyCounts as $i => $count): ?>
            <?php 
                $h = ($count / $maxWeekCount) * 80;
                $y = 80 - $h;
                $x = $i * 60;
                $fill = $count > 0 ? '#ef4444' : 'rgba(255,255,255,0.1)';
            ?>
            <rect x="<?= $x ?>" y="<?= $y ?>" width="40" height="<?= $h + 4 ?>" rx="4" fill="<?= $fill ?>" />
            <text x="<?= $x + 20 ?>" y="98" fill="#a1a1aa" font-size="12" text-anchor="middle">S<?= $i + 1 ?></text>
            <?php if ($count > 0): ?>
                <text x="<?= $x + 20 ?>" y="<?= $y - 8 ?>" fill="#fff" font-size="12" font-weight="bold" text-anchor="middle"><?= $count ?></text>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
</div>

<!-- MAIN TABLE -->
<div class="card">
<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Liste des absences</h3>
        <span class="gds-filter-bar__count">Affichées : <strong id="flt-abs-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-abs-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-abs-search" type="search" placeholder="Nom, prénom ou N° Inscription…">
        </label>
        <label class="gds-filter-bar__field">
            <span>Statut</span>
            <select id="flt-abs-statut">
                <option value="">— Toutes —</option>
                <option value="1">Justifiées</option>
                <option value="0">Non justifiées</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Tri</span>
            <select id="flt-abs-sort">
                <option value="date_desc"  data-sort-key="date" data-sort-dir="desc">Plus récentes d'abord</option>
                <option value="date_asc"   data-sort-key="date" data-sort-dir="asc">Plus anciennes d'abord</option>
                <option value="name"       data-sort-key="name">Nom du stagiaire</option>
            </select>
        </label>
    </div>
</section>

<div class="table-container">
    <table class="data" id="liste-abs-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Filière</th>
            <th>Module</th>
            <th>Justifiée</th>
            <th>Stagiaire</th>
            <th class="no-print" style="text-align: right;">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $rowSid = (int) $r['id_stagiaire'];
            $rowName = (string) $r['nom'] . ' ' . (string) $r['prenom'];
            $absN = $absCounts[$rowSid] ?? 0;
            $justifie = (int) $r['est_justifiee'] === 1;
            ?>
            <tr data-filterable="1"
                data-id="<?= (int) $r['id_absence'] ?>"
                data-filiere="<?= (int) ($r['id_filiere'] ?? 0) ?>"
                data-statut="<?= (int) $r['est_justifiee'] ?>"
                data-date="<?= h((string) $r['date_absence']) ?>"
                data-name="<?= h($rowName) ?>"
                data-num_inscri="<?= h((string) $r['num_inscri']) ?>">
                
                <td style="font-family:monospace; font-weight:bold; color:#cbd5e1;"><?= h((string) $r['date_absence']) ?></td>
                <td><span class="badge" style="background:rgba(255,255,255,0.1); color:#e4e4e7;"><?= h(gds_filiere_code((string) $r['nom_filiere'])) ?></span></td>
                <td style="color:#a1a1aa; font-size:0.85rem;"><?= h((string) ($r['nom_module'] ?? '—')) ?></td>
                <td>
                    <?php if ($justifie): ?>
                        <span class="badge" style="background:rgba(16, 185, 129, 0.2); color:#10b981; border: 1px solid rgba(16, 185, 129, 0.4);"><i class="fa-solid fa-check"></i> Justifiée</span>
                    <?php else: ?>
                        <span class="badge" style="background:rgba(239, 68, 68, 0.2); color:#ef4444; border: 1px solid rgba(239, 68, 68, 0.4);"><i class="fa-solid fa-xmark"></i> Non Justifiée</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight: 600; color:#e4e4e7;"><?= h($rowName) ?></div>
                    <div style="font-size: 0.75rem; color: #71717a;; font-family:monospace;"><?= h((string) $r['num_inscri']) ?></div>
                </td>
                <td class="link-row no-print" style="text-align: right; justify-content: flex-end;">
                    <a href="print_billet_excuse.php?id=<?= (int) $r['id_absence'] ?>" target="_blank" class="icon-btn" title="Générer Billet d'excuse" style="color:#60a5fa;"><i class="fa-solid fa-file-contract"></i></a>
                    <a href="absences.php?edit=<?= (int) $r['id_absence'] ?>" class="icon-btn" title="Modifier" style="color:#fbbf24;"><i class="fa-solid fa-pen"></i></a>
                    <form class="inline" method="post" data-confirm-custom="1" data-confirm-msg="Supprimer définitivement cette absence ?" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= (int) $r['id_absence'] ?>">
                        <button type="submit" class="icon-btn danger-hover" title="Supprimer" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr id="empty-state">
                <td colspan="6" style="text-align:center; padding: 4rem; color: var(--muted);">
                    <i class="fa-solid fa-calendar-check" style="font-size:3rem; opacity:0.2; margin-bottom:1rem; display:block;"></i>
                    <em>Aucune absence à afficher.</em>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- FAB Trigger for Add Modal -->
<button type="button" class="fab-button" onclick="openAddAbsenceModal()" style="background: #ef4444; box-shadow: 0 10px 25px -5px rgba(239,68,68,0.5);">
    <i class="fa-solid fa-plus" style="margin-right:8px;"></i> Ajouter une absence
</button>

<!-- ADD / EDIT MODAL -->
<div id="modal-absence" class="modal-overlay" style="display: <?= $edit ? 'flex' : 'none' ?>;">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h2><?= $edit ? 'Modifier l\'absence' : 'Nouvelle absence' ?></h2>
            <button type="button" class="icon-btn" onclick="<?= $edit ? "window.location='absences.php'" : "document.getElementById('modal-absence').style.display='none'" ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" action="absences.php" class="modal-form" data-filiere-form="true" style="display:flex; flex-direction:column; height:100%;">
            <div class="modal-body">
                <fieldset class="modal-fieldset" style="grid-template-columns: 1fr;">
                    <?php if ($edit): ?><input type="hidden" name="id_absence" value="<?= (int) $edit['id_absence'] ?>"><?php endif; ?>
                    
                    <label>Filière (filtre)
                        <select data-role="filiere-filter">
                            <option value="">— Toutes —</option>
                            <?php foreach ($filieres as $fi): ?>
                                <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Stagiaire *
                        <select name="id_stagiaire" required data-filiere-filter="true">
                            <option value=""></option>
                            <?php foreach ($stag as $s): ?>
                                <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['num_inscri'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label>Date * <input type="date" name="date_absence" required value="<?= h((string) ($edit['date_absence'] ?? date('Y-m-d'))) ?>"></label>
                        <label>Module (Optionnel)
                            <select name="id_module" data-filiere-filter="true">
                                <option value="">—</option>
                                <?php foreach ($mods as $m): ?>
                                    <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>" <?= ($edit && (int)($edit['id_module'] ?? 0) === (int)$m['id_module']) ? 'selected' : '' ?>><?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label>Heure début <input type="time" name="heure_debut" value="<?= h(substr((string)($edit['heure_debut'] ?? ''), 0, 5)) ?>"></label>
                        <label>Heure fin <input type="time" name="heure_fin" value="<?= h(substr((string)($edit['heure_fin'] ?? ''), 0, 5)) ?>"></label>
                    </div>

                    <label>Justificatif (Document, note)
                        <input type="text" name="justificatif" pattern=".*[a-zA-ZÀ-ÿ].*" title="Doit contenir au moins une lettre" placeholder="Ex: Certificat médical" value="<?= h((string) ($edit['justificatif'] ?? '')) ?>">
                    </label>

                    <label style="flex-direction:row; align-items:center; gap:0.5rem; margin-top:0.5rem; color:#e4e4e7; font-weight:bold;">
                        <input type="checkbox" name="est_justifiee" value="1" <?= ($edit && (int)$edit['est_justifiee']) ? 'checked' : '' ?> style="width:20px; height:20px;">
                        Cette absence a été justifiée officiellement
                    </label>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $edit ? "window.location='absences.php'" : "document.getElementById('modal-absence').style.display='none'" ?>">Annuler</button>
                <button type="submit" name="save" value="1" class="btn" style="background:#ef4444; color:#fff;">
                    <i class="fa-solid fa-floppy-disk"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.gdsTableFilter) return;
    window.gdsTableFilter({
        table: '#liste-abs-table',
        counter: '#flt-abs-count',
        controls: [
            { selector: '#flt-abs-filiere', field: 'filiere', type: 'equals' },
            { selector: '#flt-abs-statut',  field: 'statut',  type: 'equals' },
            { selector: '#flt-abs-search',  field: 'search',  type: 'contains', searchFields: ['name', 'num_inscri'] },
            { selector: '#flt-abs-sort',    field: 'sort',    type: 'sort' }
        ]
    });
});

function openAddAbsenceModal() {
    const modal = document.getElementById('modal-absence');
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
        // Ensure hidden fields or specific states are cleared
        const hiddenId = form.querySelector('input[name="id_absence"]');
        if (hiddenId) hiddenId.value = '';
        
        // Reset the master filière filter in the modal
        const filiereFilter = form.querySelector('[data-role="filiere-filter"]');
        if (filiereFilter) filiereFilter.value = '';
        
        // Update labels to "Nouvelle"
        const header = modal.querySelector('.modal-header h2');
        if (header) header.textContent = 'Nouvelle absence';
        const submitBtn = modal.querySelector('button[name="save"]');
        if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
    }
    modal.style.display = 'flex';
    
    // Trigger the filière filter refresh to show all modules initially (or based on reset)
    if (window.gdsFiliereFilterRefresh) {
        window.gdsFiliereFilterRefresh();
    }
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
