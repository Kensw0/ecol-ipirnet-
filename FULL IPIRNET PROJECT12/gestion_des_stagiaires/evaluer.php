<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $parts = explode('-', $_POST['delete_id']);
        if (count($parts) === 2) {
            $pdo->prepare('DELETE FROM module_notes WHERE id_stagiaire = ? AND id_module = ?')
                ->execute([(int) $parts[0], (int) $parts[1]]);
            flash_set('Notes supprimées.');
        }
        redirect('evaluer.php');
    }
    if (isset($_POST['save'])) {
        $sid  = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid  = (int) ($_POST['id_module'] ?? 0);

        $getFloat = function($val) {
            $val = trim((string)$val);
            if ($val === '') return null;
            return (float) str_replace(',', '.', $val);
        };

        $nc = $getFloat($_POST['note_controle'] ?? '');
        $nt = $getFloat($_POST['note_theorique'] ?? '');
        $np = $getFloat($_POST['note_pratique'] ?? '');

        if ($sid <= 0 || $mid <= 0) {
            flash_set('Stagiaire et module sont requis.');
            redirect('evaluer.php');
        }

        // Upsert logic (INSERT ON DUPLICATE KEY UPDATE)
        $pdo->prepare('
            INSERT INTO module_notes (id_stagiaire, id_module, note_controle, note_theorique, note_pratique) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            note_controle = VALUES(note_controle),
            note_theorique = VALUES(note_theorique),
            note_pratique = VALUES(note_pratique)
        ')->execute([$sid, $mid, $nc, $nt, $np]);
        
        flash_set('Notes enregistrées avec succès.');
        redirect('evaluer.php');
    }
}

$curPage   = 'evaluer';
$pageTitle = 'Gestion des Notes (Contrôle / Examen)';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag     = $pdo->query('SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods     = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$editSid = isset($_GET['edit_sid']) ? (int) $_GET['edit_sid'] : null;
$editMid = isset($_GET['edit_mid']) ? (int) $_GET['edit_mid'] : null;
$edit = null;

if ($editSid !== null && $editMid !== null) {
    $st = $pdo->prepare('SELECT * FROM module_notes WHERE id_stagiaire = ? AND id_module = ?');
    $st->execute([$editSid, $editMid]);
    $edit = $st->fetch();
}

$rows = $pdo->query(
    'SELECT v.*, s.num_inscri, s.nom, s.prenom, f.id_filiere, f.nom_filiere 
       FROM v_moyennes_par_module v
       JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire
       JOIN classes c    ON c.id_classe    = s.id_classe
       JOIN filieres f   ON f.id_filiere   = c.id_filiere
      ORDER BY s.nom, s.prenom, v.nom_module'
)->fetchAll();

// STATS
$countGlobal = count($rows);
$notesCompletes = 0;
$sommeMoyennes = 0;
$countMoyennes = 0;

$distrib = ['red' => 0, 'yellow' => 0, 'blue' => 0, 'green' => 0];

foreach ($rows as $r) {
    if ($r['moyenne_module'] !== null) {
        $v = (float)$r['moyenne_module'];
        $sommeMoyennes += $v;
        $countMoyennes++;
        
        if ($v < 10) $distrib['red']++;
        elseif ($v < 12) $distrib['yellow']++;
        elseif ($v < 16) $distrib['blue']++;
        else $distrib['green']++;
    }
    
    if ($r['note_controle'] !== null && $r['note_theorique'] !== null && $r['note_pratique'] !== null) {
        $notesCompletes++;
    }
}
$avgNote = $countMoyennes > 0 ? $sommeMoyennes / $countMoyennes : 0;
$maxDist = max(1, max($distrib));

function getNoteBadge($val, $empty = '—') {
    if ($val === null) return '<span style="color:#a1a1aa;">' . $empty . '</span>';
    $v = (float) $val;
    $color = '#ef4444'; // red
    if ($v >= 16) $color = '#10b981'; // green
    elseif ($v >= 12) $color = '#3b82f6'; // blue
    elseif ($v >= 10) $color = '#facc15'; // yellow
    return '<span style="font-weight:600; color:' . $color . ';">' . number_format($v, 2, ',', '') . '</span>';
}
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Suivi des Notes</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:2rem;">Gérez les notes des contrôles et des examens (Théorie & Pratique) par module.</p>

<!-- STATS CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div>
            <div class="stat-label">Moyenne Générale des Modules</div>
            <div class="stat-value" style="color:#3b82f6;"><?= number_format($avgNote, 2, ',', '') ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div>
            <div class="stat-label">Modules Complétés</div>
            <div class="stat-value" style="color:#10b981;"><?= $notesCompletes ?></div>
        </div>
    </div>

    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(168, 85, 247, 0.15); color: #a855f7; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-file-pen"></i>
        </div>
        <div>
            <div class="stat-label">Modules Évalués</div>
            <div class="stat-value" style="color:#a855f7;"><?= $countGlobal ?></div>
        </div>
    </div>
</div>

<!-- SVG Sparkline Bar Chart (Distribution) -->
<div class="card" style="margin-bottom: 2rem; display:flex; flex-direction:column; align-items:flex-start;">
    <h3 style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 1rem;">Distribution des Moyennes Finales</h3>
    <svg width="400" height="100" style="overflow: visible;">
        <?php 
        $bars = [
            ['label' => '< 10', 'count' => $distrib['red'], 'color' => '#ef4444'],
            ['label' => '10-11', 'count' => $distrib['yellow'], 'color' => '#facc15'],
            ['label' => '12-15', 'count' => $distrib['blue'], 'color' => '#3b82f6'],
            ['label' => '16-20', 'count' => $distrib['green'], 'color' => '#10b981'],
        ];
        foreach ($bars as $i => $b): 
            $h = ($b['count'] / $maxDist) * 70;
            $y = 70 - $h;
            $x = $i * 80;
        ?>
            <rect x="<?= $x ?>" y="<?= $y ?>" width="50" height="<?= $h + 4 ?>" rx="4" fill="<?= $b['color'] ?>" />
            <text x="<?= $x + 25 ?>" y="92" fill="#a1a1aa" font-size="11" text-anchor="middle"><?= $b['label'] ?></text>
            <?php if ($b['count'] > 0): ?>
                <text x="<?= $x + 25 ?>" y="<?= $y - 8 ?>" fill="#fff" font-size="12" font-weight="bold" text-anchor="middle"><?= $b['count'] ?></text>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
</div>

<!-- MAIN TABLE -->
<div class="card" style="padding:0;">
<section class="gds-filter-bar no-print" style="padding: 1.5rem; border-bottom:1px solid rgba(255,255,255,0.05);">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Registre des Notes</h3>
        <span class="gds-filter-bar__count">Affichés : <strong id="flt-ev-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-ev-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-ev-search" type="search" placeholder="Nom, matricule, module…">
        </label>
    </div>
</section>

<div class="table-container" style="margin:0; border:none; border-radius:0;">
    <table class="data" id="evaluer-table">
        <thead>
            <tr>
                <th style="padding-left:1.5rem;">Stagiaire</th>
                <th>Module</th>
                <th style="text-align:center;">Contrôle</th>
                <th style="text-align:center;">Théorique</th>
                <th style="text-align:center;">Pratique</th>
                <th style="text-align:center; background:rgba(0,0,0,0.2);">Exam Moy.</th>
                <th style="text-align:center; background:rgba(255,255,255,0.05);">Moy. Générale</th>
                <th class="no-print" style="text-align:right; padding-right:1.5rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $idx => $r): ?>
                <?php 
                    $bg = ($idx % 2 === 0) ? 'background-color: rgba(255,255,255,0.01);' : 'background-color: transparent;';
                ?>
                <tr style="<?= $bg ?> border-bottom: 1px solid rgba(255,255,255,0.03);"
                    data-filiere="<?= (int) $r['id_filiere'] ?>"
                    data-search="<?= h(strtolower($r['nom'] . ' ' . $r['prenom'] . ' ' . $r['num_inscri'] . ' ' . $r['nom_module'])) ?>">
                    
                    <td style="padding-left:1.5rem;">
                        <div style="font-weight:600; color:#e4e4e7;"><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></div>
                        <div style="font-family:monospace; font-size:0.8rem; color:#71717a;"><?= h((string) $r['num_inscri']) ?></div>
                    </td>
                    <td style="color:#d4d4d8; font-size:0.9rem; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= h((string)$r['nom_module']) ?>">
                        <?= h(gds_module_label((string) $r['nom_module'])) ?>
                        <div style="color:#71717a; font-size:0.75rem;">Coef: <?= (int)$r['coefficient'] ?></div>
                    </td>
                    <td style="text-align:center;"><?= getNoteBadge($r['note_controle']) ?></td>
                    <td style="text-align:center;"><?= getNoteBadge($r['note_theorique']) ?></td>
                    <td style="text-align:center;"><?= getNoteBadge($r['note_pratique']) ?></td>
                    <td style="text-align:center; background:rgba(0,0,0,0.2);"><?= getNoteBadge($r['note_examen']) ?></td>
                    <td style="text-align:center; background:rgba(255,255,255,0.05);"><?= getNoteBadge($r['moyenne_module']) ?></td>
                    <td class="link-row no-print" style="text-align:right; padding-right:1.5rem; justify-content:flex-end;">
                        <a href="evaluer.php?edit_sid=<?= (int)$r['id_stagiaire'] ?>&edit_mid=<?= (int)$r['id_module'] ?>" class="icon-btn" title="Saisir/Modifier" style="color:#3b82f6;"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" data-confirm-custom="1" data-confirm-msg="Voulez-vous vraiment effacer TOUTES les notes de ce module pour ce stagiaire ?">
                            <input type="hidden" name="delete_id" value="<?= (int) $r['id_stagiaire'].'-'.(int) $r['id_module'] ?>">
                            <button type="submit" class="icon-btn danger-hover" title="Supprimer" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8" style="text-align:center; padding:4rem; color:var(--muted);"><i class="fa-solid fa-file-invoice" style="font-size:3rem; opacity:0.2; margin-bottom:1rem; display:block;"></i><em>Aucune note enregistrée.</em></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- FAB Trigger for Add Modal -->
<button type="button" class="fab-button" onclick="document.getElementById('modal-eval').style.display='flex'" style="background: #3b82f6; box-shadow: 0 10px 25px -5px rgba(59,130,246,0.5);">
    <i class="fa-solid fa-plus" style="margin-right:8px;"></i> Saisir des Notes
</button>

<!-- ADD / EDIT MODAL -->
<div id="modal-eval" class="modal-overlay" style="display: <?= $edit !== null ? 'flex' : 'none' ?>;">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h2><?= $edit !== null ? 'Modifier les notes du module' : 'Saisir les notes d\'un module' ?></h2>
            <button type="button" class="icon-btn" onclick="<?= $edit !== null ? "window.location='evaluer.php'" : "document.getElementById('modal-eval').style.display='none'" ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" action="evaluer.php" class="modal-form" data-filiere-form="true" style="display:flex; flex-direction:column; height:100%;">
            <div class="modal-body">
                
                <?php if ($edit === null): ?>
                <div style="background: rgba(59,130,246,0.1); padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center;">
                    <i class="fa-solid fa-circle-info" style="color:#3b82f6; font-size:1.5rem;"></i>
                    <p style="font-size:0.9rem; color:#a1a1aa; margin:0;">
                        Le système crée une <strong>fiche unique par module</strong> pour chaque stagiaire. 
                        Saisissez les notes acquises, laissez vide pour les notes non évaluées.
                    </p>
                </div>
                <?php endif; ?>

                <fieldset class="modal-fieldset" style="grid-template-columns: 1fr 1fr;">
                    <label style="grid-column: span 2;">Filière (filtre liste déroulante)
                        <select data-role="filiere-filter">
                            <option value="">— Toutes —</option>
                            <?php foreach ($filieres as $fi): ?>
                                <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label style="grid-column: span 2;">Stagiaire *
                        <select name="id_stagiaire" required data-filiere-filter="true" <?= $edit !== null ? 'style="pointer-events:none; opacity:0.7;" readonly' : '' ?>>
                            <option value=""></option>
                            <?php foreach ($stag as $s): ?>
                                <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>"
                                    <?= ($editSid === (int)$s['id_stagiaire']) ? 'selected' : '' ?>>
                                    <?= h($s['num_inscri'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label style="grid-column: span 2;">Module *
                        <select name="id_module" required data-filiere-filter="true" <?= $edit !== null ? 'style="pointer-events:none; opacity:0.7;" readonly' : '' ?>>
                            <option value=""></option>
                            <?php foreach ($mods as $m): ?>
                                <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>"
                                    <?= ($editMid === (int)$m['id_module']) ? 'selected' : '' ?>>
                                    <?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </fieldset>

                <h3 style="font-size:1.1rem; color:#fff; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem; margin:1.5rem 0 1rem; font-family:'Instrument Serif', serif;">Saisie des Notes (/ 20)</h3>
                
                <fieldset class="modal-fieldset" style="grid-template-columns: 1fr 1fr 1fr;">
                    <label>Contrôle Continu
                        <input name="note_controle" type="number" step="0.01" min="0" max="20" placeholder="Ex: 14.5"
                               value="<?= $edit !== null && $edit['note_controle'] !== null ? h((string) $edit['note_controle']) : '' ?>">
                    </label>

                    <label>Examen Théorique
                        <input name="note_theorique" type="number" step="0.01" min="0" max="20" placeholder="Ex: 12"
                               value="<?= $edit !== null && $edit['note_theorique'] !== null ? h((string) $edit['note_theorique']) : '' ?>">
                    </label>

                    <label>Examen Pratique
                        <input name="note_pratique" type="number" step="0.01" min="0" max="20" placeholder="Ex: 16"
                               value="<?= $edit !== null && $edit['note_pratique'] !== null ? h((string) $edit['note_pratique']) : '' ?>">
                    </label>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $edit !== null ? "window.location='evaluer.php'" : "document.getElementById('modal-eval').style.display='none'" ?>">Annuler</button>
                <button type="submit" name="save" value="1" class="btn" style="background:#3b82f6; color:#fff;">
                    <i class="fa-solid fa-floppy-disk"></i> <?= $edit !== null ? 'Enregistrer les modifications' : 'Créer la fiche de notes' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var table   = document.getElementById('evaluer-table');
    var fFil    = document.getElementById('flt-ev-filiere');
    var fSearch = document.getElementById('flt-ev-search');
    var counter = document.getElementById('flt-ev-count');
    if (!table) return;
    function applyFilters() {
        var fil    = fFil    ? fFil.value    : '';
        var search = fSearch ? fSearch.value.toLowerCase() : '';
        var rows   = table.querySelectorAll('tbody tr[data-filiere]');
        var shown  = 0;
        rows.forEach(function(row) {
            var ok = true;
            if (fil    && row.dataset.filiere !== fil)                     ok = false;
            if (search && row.dataset.search.indexOf(search) === -1)       ok = false;
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (counter) counter.textContent = shown;
    }
    [fFil, fSearch].forEach(function(el) { if (el) el.addEventListener('input', applyFilters); });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
