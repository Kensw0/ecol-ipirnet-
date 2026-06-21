<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
gds_require_admin_session();

$pageTitle = 'Gestion des stages';
$curPage   = 'stages';

// ── FILTER PARAMS ────────────────────────────────────────────────────────────
$selAnnee   = trim((string)($_GET['annee']      ?? ''));
$selFiliere = (int)($_GET['id_filiere'] ?? 0);
$selNiveau  = trim((string)($_GET['niveau']     ?? ''));
$selClasse  = (int)($_GET['id_classe']  ?? 0);

// ── DATA LOADS ───────────────────────────────────────────────────────────────
$allAnnees  = $pdo->query("SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);
if ($selAnnee === '') { $selAnnee = $_SESSION['global_annee_scolaire'] ?? ($allAnnees[0] ?? ''); }

$allFilieres = $pdo->query("SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere")->fetchAll();
if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }

$allNiveaux = [];
if ($selFiliere > 0 && $selAnnee !== '') {
    $stNiv = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $stNiv->execute([$selFiliere, $selAnnee]);
    $allNiveaux = $stNiv->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allNiveaux) && !in_array($selNiveau, $allNiveaux, true)) { $selNiveau = $allNiveaux[0]; }
}

$allClasses = [];
if ($selFiliere > 0 && $selAnnee !== '' && $selNiveau !== '') {
    $stCl = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $stCl->execute([$selFiliere, $selAnnee, $selNiveau]);
    $allClasses = $stCl->fetchAll();
    $_vcids = array_map('intval', array_column($allClasses, 'id_classe'));
    if (!empty($allClasses) && !in_array($selClasse, $_vcids, true)) { $selClasse = (int)$allClasses[0]['id_classe']; }
}

// ── LOAD STAGIAIRES + STAGES ──────────────────────────────────────────────────
$stagiaires = [];
if ($selClasse > 0) {
    // Get all students in class
    $stSt = $pdo->prepare("SELECT id_stagiaire, num_inscri, nom, prenom, cin FROM stagiaires WHERE id_classe = ? ORDER BY nom, prenom");
    $stSt->execute([$selClasse]);
    $tempStag = $stSt->fetchAll(PDO::FETCH_ASSOC);

    // Get all stages for these students in this academic year
    foreach ($tempStag as $s) {
        $sid = (int)$s['id_stagiaire'];
        $stStages = $pdo->prepare(
              "SELECT st.* FROM stages st
               WHERE st.id_stagiaire = ? AND st.annee_scolaire = ?
               ORDER BY st.type_stage"
          );
          $stStages->execute([$sid, $selAnnee]);
        $s['stages_data'] = $stStages->fetchAll(PDO::FETCH_ASSOC);
        
        // Logical Analysis
        $hasStage = false;
        $hasPFE   = false;
        foreach ($s['stages_data'] as $stg) {
            if ($stg['type_stage'] === 'stage_entreprise') $hasStage = true;
            if ($stg['type_stage'] === 'pfe') $hasPFE = true;
        }
        
        $s['has_stage'] = $hasStage;
        $s['has_pfe']   = $hasPFE;
        
        // Determine status based on niveau
        // Assuming Year 1 = "1ère année" and Year 2 = "2ème année" based on notes.php patterns
        if (strpos($selNiveau, '1') !== false) {
            $s['status'] = $hasStage ? 'complet' : 'manquant';
            $s['recap']  = $hasStage ? '✅ Stage validé' : '🔴 Stage requis';
        } else {
            if ($hasStage && $hasPFE) {
                $s['status'] = 'complet';
                $s['recap']  = '✅ Stage & PFE validés';
            } elseif ($hasStage || $hasPFE) {
                $s['status'] = 'partiel';
                $s['recap']  = (!$hasStage ? '🔴 Stage' : '') . (!$hasPFE ? ' 🔴 PFE' : '') . ' manquant';
            } else {
                $s['status'] = 'vide';
                $s['recap']  = '❌ Aucun document';
            }
        }
        
        $stagiaires[] = $s;
    }
}

$classeInfo = null;
if ($selClasse > 0) {
    $r = $pdo->prepare("SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $r->execute([$selClasse]);
    $classeInfo = $r->fetch();
}

// ── SAVE LOGIC (AJAX from stagiaires.php logic) ────────────────────────────────
if (isset($_POST['quick_save_stage'])) {
    header('Content-Type: application/json');
    $sid   = (int)($_POST['id_stagiaire'] ?? 0);
    $ts    = in_array((string)($_POST['type_stage'] ?? ''), ['stage_entreprise','pfe'], true) ? (string)$_POST['type_stage'] : 'stage_entreprise';
    $su    = trim((string)($_POST['sujet'] ?? ''));
    $en    = trim((string)($_POST['entreprise'] ?? ''));
    $dd    = ($_POST['date_debut'] ?? '') === '' ? null : (string)$_POST['date_debut'];
    $df    = ($_POST['date_fin']   ?? '') === '' ? null : (string)$_POST['date_fin'];
    $ns    = ($_POST['note_stage'] ?? '') === '' ? null : (float)str_replace(',', '.', (string)$_POST['note_stage']);
    $cu    = trim((string)($_POST['convention_url'] ?? ''));
    $ru    = trim((string)($_POST['rapport_url'] ?? ''));
    $ev    = trim((string)($_POST['evaluation_entreprise'] ?? ''));
    $ds    = ($_POST['date_soutenance'] ?? '') === '' ? null : (string)$_POST['date_soutenance'];
    $ju    = trim((string)($_POST['jury'] ?? ''));
    $as    = trim((string)($_POST['annee_scolaire'] ?? ''));
    $edit_id = (int)($_POST['id_stage'] ?? 0);

    if ($sid > 0) {
        try {
            if ($edit_id > 0) {
                $pdo->prepare('UPDATE stages SET type_stage=?,sujet=?,entreprise=?,date_debut=?,date_fin=?,note_stage=?,convention_url=?,rapport_url=?,evaluation_entreprise=?,date_soutenance=?,jury=?,annee_scolaire=? WHERE id_stage=? AND id_stagiaire=?')
                    ->execute([$ts, $su===''?null:$su, $en===''?null:$en, $dd, $df, $ns, $cu===''?null:$cu, $ru===''?null:$ru, $ev===''?null:$ev, $ds, $ju===''?null:$ju, $as, $edit_id, $sid]);
                $msg = 'Stage mis à jour.';
            } else {
                // Server-side duplicate guard: one stage_entreprise + one PFE per student per year
                $chk = $pdo->prepare('SELECT id_stage FROM stages WHERE id_stagiaire=? AND type_stage=? AND annee_scolaire=? LIMIT 1');
                $chk->execute([$sid, $ts, $as]);
                if ($chk->fetch()) {
                    $typeLabel = $ts === 'pfe' ? 'PFE' : 'stage en entreprise';
                    echo json_encode(['success' => false, 'msg' => "Ce stagiaire a déjà un $typeLabel pour l'année $as."]);
                    exit;
                }
                $pdo->prepare('INSERT INTO stages (type_stage,sujet,entreprise,date_debut,date_fin,note_stage,convention_url,rapport_url,evaluation_entreprise,date_soutenance,jury,id_stagiaire,annee_scolaire) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$ts, $su===''?null:$su, $en===''?null:$en, $dd, $df, $ns, $cu===''?null:$cu, $ru===''?null:$ru, $ev===''?null:$ev, $ds, $ju===''?null:$ju, $sid, $as]);
                $msg = 'Stage ajouté.';
            }
            echo json_encode(['success' => true, 'msg' => $msg]);
        } catch (Exception $e) {
            error_log('[stages.php] ' . $e->getMessage());
            echo json_encode(['success' => false, 'msg' => 'Une erreur est survenue. Veuillez réessayer.']);
        }
    } else {
        echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
    }
    exit;
}

// ── DELETE LOGIC ─────────────────────────────────────────────────────────────
if (isset($_POST['quick_delete_stage'])) {
    header('Content-Type: application/json');
    $sid = (int)($_POST['id_stagiaire'] ?? 0);
    $stid = (int)($_POST['id_stage'] ?? 0);
    if ($sid > 0 && $stid > 0) {
        $pdo->prepare('DELETE FROM stages WHERE id_stage = ? AND id_stagiaire = ?')->execute([$stid, $sid]);
        echo json_encode(['success' => true, 'msg' => 'Stage supprimé.']);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
    }
    exit;
}


require_once __DIR__ . '/includes/header.php';
?>

<style>
.stages-shell { max-width: 1200px; margin: 0 auto; padding-bottom: 4rem; }
.filter-card {
    background: #16161e;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.3);
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
    align-items: end;
}
.filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
.filter-group label { font-size: 0.72rem; color: #71717a; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; }
.filter-group select {
    background: #09090b;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: #fff;
    padding: 0.7rem 0.9rem;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}
.filter-group select:hover:not(:disabled) { border-color: rgba(168,85,247,0.4); background: #12121a; }
.filter-group select:focus { outline: none; border-color: #a855f7; box-shadow: 0 0 0 3px rgba(168,85,247,0.2); }

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.summary-card {
    background: rgba(22,22,30,0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.summary-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}
.summary-info h3 { font-size: 0.75rem; color: #71717a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
.summary-info div { font-size: 1.5rem; font-weight: 800; color: #fff; }

.stage-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
.stage-table thead th {
    padding: 0.5rem 1rem;
    text-align: left;
    font-size: 0.72rem;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.stage-row {
    background: #16161e;
    transition: transform 0.2s, background 0.2s;
}
.stage-row:hover { transform: translateY(-2px); background: #1c1c27; }
.stage-row td {
    padding: 1.25rem 1rem;
    border-top: 1px solid rgba(255,255,255,0.04);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.stage-row td:first-child { border-left: 1px solid rgba(255,255,255,0.04); border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
.stage-row td:last-child { border-right: 1px solid rgba(255,255,255,0.04); border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.status-complet { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
.status-partiel { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
.status-manquant { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }

.mini-stage-card {
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.8rem;
    margin-bottom: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.mini-stage-card.pfe { border-left: 3px solid #a855f7; }
.mini-stage-card.stage { border-left: 3px solid #3b82f6; }

.action-btn {
    width: 34px; height: 34px;
    border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.04);
    color: #a1a1aa;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid rgba(255,255,255,0.08);
}
.action-btn:hover { background: #a855f7; color: #fff; border-color: #a855f7; transform: scale(1.1); }
.action-btn.print:hover { background: #3b82f6; border-color: #3b82f6; }

/* Modal tweaks to match stagiaires.php */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(12px);
    display: flex; align-items: center; justify-content: center; padding: 2rem;
}
.modal-card {
    background: #12121a; border: 1px solid rgba(255,255,255,0.1); border-radius: 20px;
    width: 100%; box-shadow: 0 40px 100px -20px rgba(0,0,0,1);
    display: flex; flex-direction: column; max-height: 90vh;
}
.modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 2rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.5rem; }
.modal-fieldset { border: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.modal-card input, .modal-card select { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color:#fff; padding: 10px 14px; width:100%; }
.modal-card label { display: flex; flex-direction: column; gap: 6px; font-size: 0.82rem; color: #71717a; }
</style>

<div class="stages-shell">
    
    <div style="margin-bottom:1.5rem;">
        <a href="index.php" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-card no-print">
        <form method="get" id="stages-filter-form">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Année Scolaire</label>
                    <select name="annee" id="f-annee">
                        <?php foreach ($allAnnees as $y): ?>
                            <option value="<?= h($y) ?>" <?= $y === $selAnnee ? 'selected' : '' ?>><?= h($y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Filière</label>
                    <select name="id_filiere" id="f-filiere">
                        <option value="0">— Choisir —</option>
                        <?php foreach ($allFilieres as $f): ?>
                            <option value="<?= $f['id_filiere'] ?>" <?= $f['id_filiere'] == $selFiliere ? 'selected' : '' ?>><?= h($f['nom_filiere']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Niveau</label>
                    <select name="niveau" id="f-niveau" <?= $selFiliere == 0 ? 'disabled' : '' ?>>
                        <?php if (empty($allNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
                        <?php foreach ($allNiveaux as $n): ?>
                            <option value="<?= h($n) ?>" <?= $n === $selNiveau ? 'selected' : '' ?>><?= h($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Classe</label>
                    <select name="id_classe" id="f-classe" <?= $selNiveau === '' ? 'disabled' : '' ?>>
                        <?php if (empty($allClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?= $c['id_classe'] ?>" <?= $c['id_classe'] == $selClasse ? 'selected' : '' ?>><?= h($c['nom_classe']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem; font-weight: 700;">
                        <i class="fa-solid fa-magnifying-glass"></i> Afficher
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selClasse > 0 && $classeInfo): ?>
        
        <?php
        $totalStag = count($stagiaires);
        $totalComplet = count(array_filter($stagiaires, fn($s) => $s['status'] === 'complet'));
        $totalPartiel = count(array_filter($stagiaires, fn($s) => $s['status'] === 'partiel' || $s['status'] === 'manquant'));
        ?>
        
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon" style="background:rgba(59,130,246,0.1); color:#3b82f6;"><i class="fa-solid fa-users"></i></div>
                <div class="summary-info">
                    <h3>Effectif Classe</h3>
                    <div><?= $totalStag ?></div>
                </div>
            </div>
            <div class="summary-card" style="border-bottom: 3px solid #10b981;">
                <div class="summary-icon" style="background:rgba(16,185,129,0.1); color:#10b981;"><i class="fa-solid fa-check-double"></i></div>
                <div class="summary-info">
                    <h3>Dossiers Complets</h3>
                    <div><?= $totalComplet ?></div>
                </div>
            </div>
            <div class="summary-card" style="border-bottom: 3px solid #ef4444;">
                <div class="summary-icon" style="background:rgba(239,68,68,0.1); color:#ef4444;"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="summary-info">
                    <h3>Dossiers Incomplets</h3>
                    <div><?= $totalPartiel ?></div>
                </div>
            </div>
        </div>

        <div class="card" style="background:transparent; border:none; padding:0;">
            <table class="stage-table">
                <thead>
                    <tr>
                        <th style="width:250px;">Stagiaire</th>
                        <th>Status (<?= h($selNiveau) ?>)</th>
                        <th>Documents de Stage</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stagiaires)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:3rem; color:#71717a;">Aucun stagiaire dans cette classe.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($stagiaires as $s): ?>
                        <tr class="stage-row" id="row-<?= $s['id_stagiaire'] ?>">
                            <td>
                                <div style="font-weight:700; color:#fff; font-size:0.95rem;"><?= h($s['nom'] . ' ' . $s['prenom']) ?></div>
                                <div style="font-size:0.75rem; color:#71717a; margin-top:2px;">CIN: <?= h($s['cin'] ?? '—') ?></div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $s['status'] ?>">
                                    <i class="fa-solid <?= $s['status'] === 'complet' ? 'fa-check' : ($s['status'] === 'partiel' ? 'fa-hourglass-half' : 'fa-xmark') ?>"></i>
                                    <?= $s['recap'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if (empty($s['stages_data'])): ?>
                                    <span style="font-size:0.8rem; color:#3f3f46; font-style:italic;">Aucune donnée saisie</span>
                                <?php else: ?>
                                    <?php foreach ($s['stages_data'] as $stg): ?>
                                        <div class="mini-stage-card <?= $stg['type_stage'] === 'pfe' ? 'pfe' : 'stage' ?>">
                                            <div style="display:flex; flex-direction:column;">
                                                <strong style="color:#e4e4e7; font-size:0.72rem; text-transform:uppercase;">
                                                    <?= $stg['type_stage'] === 'pfe' ? 'PFE' : 'Stage' ?>
                                                </strong>
                                                <span style="color:#a1a1aa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">
                                                    <?= h($stg['entreprise'] ?: 'Non spécifié') ?>
                                                </span>
                                            </div>
                                            <div style="display:flex; gap:6px;">
                                                <button class="action-btn" onclick='openStageModal(<?= h(json_encode($stg)) ?>, <?= $s['id_stagiaire'] ?>)' title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square" style="font-size:0.7rem;"></i>
                                                </button>
                                                <a href="print_convention_stage.php?id=<?= $stg['id_stage'] ?>" target="_blank" class="action-btn print" title="Imprimer Convention">
                                                    <i class="fa-solid fa-file-pdf" style="font-size:0.7rem;"></i>
                                                </a>
                                                <button class="action-btn" onclick='deleteStage(<?= $stg["id_stage"] ?>, <?= $s["id_stagiaire"] ?>)' title="Supprimer" style="color:#ef4444;">
                                                    <i class="fa-solid fa-trash" style="font-size:0.7rem;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <?php 
                                $isYear1 = (strpos($selNiveau, '1') !== false);
                                $canAdd = true;
                                if ($isYear1 && $s['has_stage']) $canAdd = false;
                                if (!$isYear1 && $s['has_stage'] && $s['has_pfe']) $canAdd = false;
                                
                                if ($canAdd): ?>
                                    <button class="btn btn-primary small" onclick='openStageModal(null, <?= $s["id_stagiaire"] ?>, <?= $isYear1?1:2 ?>, <?= $s["has_stage"]?"true":"false" ?>, <?= $s["has_pfe"]?"true":"false" ?>)' style="background:rgba(168,85,247,0.1); color:#a855f7; border:1px solid rgba(168,85,247,0.2);">
                                        <i class="fa-solid fa-plus-circle"></i> Ajouter
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div style="text-align:center; padding:5rem 2rem; background:rgba(255,255,255,0.02); border-radius:20px; border:2px dashed rgba(255,255,255,0.05);">
            <i class="fa-solid fa-briefcase" style="font-size:3rem; color:rgba(255,255,255,0.1); margin-bottom:1.5rem;"></i>
            <h2 style="color:#71717a; font-weight:400;">Sélectionnez une classe pour gérer les stages</h2>
            <p style="color:#3f3f46; font-size:0.9rem; margin-top:0.5rem;">Les rapports et conventions seront générés automatiquement.</p>
        </div>
    <?php endif; ?>

</div>

<!-- STAGE MODAL (SAME AS STAGIAIRES.PHP) -->
<div id="modal-quick-stage" class="modal-overlay" style="display:none; z-index:10000;">
    <div class="modal-card" style="max-width:700px;">
        <div class="modal-header">
            <h2 id="stage-modal-title">Ajouter un Stage / PFE</h2>
            <button type="button" class="icon-btn" onclick="document.getElementById('modal-quick-stage').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" id="stage-form" onsubmit="submitStage(event)">
            <input type="hidden" name="id_stagiaire" id="stage-sid" value="">
            <input type="hidden" name="id_stage" id="stage-edit-id" value="">
            <input type="hidden" name="quick_save_stage" value="1">
            <div class="modal-body">
                <fieldset class="modal-fieldset">
                    <legend style="color: #a855f7; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;"> Informations Générales</legend>
                    <label>Type *
                        <select name="type_stage" id="stage-type">
                            <option value="stage_entreprise">Stage en entreprise</option>
                            <option value="pfe">PFE</option>
                        </select>
                    </label>
                    <label>Année Scolaire *
                        <select name="annee_scolaire" id="stage-anneescolaire" required>
                            <?php foreach ($allAnnees as $yr): ?>
                                <option value="<?= h($yr) ?>" <?= $yr === $selAnnee ? 'selected' : '' ?>><?= h($yr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="grid-column: span 2;">Sujet / Mission
                        <input type="text" name="sujet" id="stage-sujet" placeholder="Ex: Développement d'une application web">
                    </label>
                    <label style="grid-column: span 2;">Entreprise / Organisme
                        <input type="text" name="entreprise" id="stage-entreprise" placeholder="Ex: IPIRNET SARL">
                    </label>
                </fieldset>
                <fieldset class="modal-fieldset">
                    <legend style="color: #a855f7; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;"> Calendrier</legend>
                    <label>Date début <input type="date" name="date_debut" id="stage-datedebut"></label>
                    <label>Date fin <input type="date" name="date_fin" id="stage-datefin"></label>
                    <label>Date soutenance <input type="date" name="date_soutenance" id="stage-soutenance"></label>
                    <label>Jury / Modalités <input type="text" name="jury" id="stage-jury" placeholder="Ex: Mr. Dupont, Mme. Martin"></label>
                </fieldset>
            </div>
            <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-quick-stage').style.display='none'">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter cascade (borrowed from notes.php but adapted)
const fAnnee = document.getElementById('f-annee');
const fFiliere = document.getElementById('f-filiere');
const fNiveau = document.getElementById('f-niveau');
const fClasse = document.getElementById('f-classe');
const form = document.getElementById('stages-filter-form');

const cascade = (el) => {
    if (el === fAnnee || el === fFiliere) { fNiveau.value = ''; fClasse.value = ''; }
    if (el === fNiveau) { fClasse.value = ''; }
    form.submit();
};

fAnnee.addEventListener('change', () => cascade(fAnnee));
fFiliere.addEventListener('change', () => cascade(fFiliere));
fNiveau.addEventListener('change', () => cascade(fNiveau));
fClasse.addEventListener('change', () => cascade(fClasse));

function openStageModal(stg, sid, niveauNum, hasStage, hasPFE) {
    document.getElementById('modal-quick-stage').style.display = 'flex';
    document.getElementById('stage-sid').value = sid;
    document.getElementById('stage-edit-id').value = stg ? stg.id_stage : '';
    document.getElementById('stage-modal-title').textContent = stg ? 'Modifier le Stage' : 'Ajouter un Stage / PFE';
    
    // Manage options based on rules
    const typeSelect = document.getElementById('stage-type');
    typeSelect.innerHTML = '';
    typeSelect.disabled = false;

    if (stg) {
        // EDIT MODE: show only current type
        const opt = document.createElement('option');
        opt.value = stg.type_stage;
        opt.textContent = stg.type_stage === 'pfe' ? 'PFE' : 'Stage en entreprise';
        typeSelect.appendChild(opt);
        typeSelect.disabled = true;
    } else {
        if (niveauNum === 1) {
            const opt = document.createElement('option');
            opt.value = 'stage_entreprise'; opt.textContent = 'Stage en entreprise';
            typeSelect.appendChild(opt);
        } else {
            if (!hasStage) {
                const opt = document.createElement('option');
                opt.value = 'stage_entreprise'; opt.textContent = 'Stage en entreprise';
                typeSelect.appendChild(opt);
            }
            if (!hasPFE) {
                const opt = document.createElement('option');
                opt.value = 'pfe'; opt.textContent = 'PFE';
                typeSelect.appendChild(opt);
            }
        }
    }

    document.getElementById('stage-anneescolaire').value = stg ? stg.annee_scolaire : '<?= h($selAnnee) ?>';
    document.getElementById('stage-sujet').value = stg ? (stg.sujet || '') : '';
    document.getElementById('stage-entreprise').value = stg ? (stg.entreprise || '') : '';
    document.getElementById('stage-datedebut').value = stg ? (stg.date_debut || '') : '';
    document.getElementById('stage-datefin').value = stg ? (stg.date_fin || '') : '';
    document.getElementById('stage-soutenance').value = stg ? (stg.date_soutenance || '') : '';
    document.getElementById('stage-jury').value = stg ? (stg.jury || '') : '';
}

function submitStage(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('stage-form'));
    
    fetch('stages.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload(); // Simple reload to refresh the table
        } else {
            alert(res.msg);
        }
    });
}

function deleteStage(stid, sid) {
    if (!confirm('Voulez-vous vraiment supprimer ce stage ?')) return;
    
    const fd = new FormData();
    fd.append('quick_delete_stage', '1');
    fd.append('id_stage', stid);
    fd.append('id_stagiaire', sid);
    
    fetch('stages.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.msg);
        }
    });
}

// Flash & scroll to a student row when arriving from the hub
(function() {
    var hlSid = <?= (int)($_GET['highlight'] ?? 0) ?>;
    if (hlSid > 0) {
        var tr = document.getElementById('row-' + hlSid);
        if (tr) {
            setTimeout(function(){
                tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                tr.style.transition = 'background 0.2s, outline 0.2s';
                tr.style.background  = 'rgba(168,85,247,0.22)';
                tr.style.outline     = '2px solid rgba(168,85,247,0.6)';
                tr.style.borderRadius = '6px';
                setTimeout(function(){ tr.style.background = ''; tr.style.outline = ''; }, 2800);
            }, 300);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
