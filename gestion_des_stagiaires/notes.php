<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
gds_require_admin_session();

$pageTitle = 'Gestion des notes';
$curPage   = 'notes';

// ── BULK SAVE ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save_notes'])) {
    csrf_verify();
    $id_module    = (int)($_POST['id_module']    ?? 0);
    $nb_controles = max(1, min(10, (int)($_POST['nb_controles'] ?? 1)));
    $rows         = $_POST['notes'] ?? [];
    $saved        = 0;

    if ($id_module > 0 && is_array($rows)) {
        // Delete existing notes for these students first to avoid stale/duplicate rows
        $validSids = array_filter(array_map('intval', array_keys($rows)));
        if (!empty($validSids)) {
            $delPh = implode(',', array_fill(0, count($validSids), '?'));
            $pdo->prepare("DELETE FROM module_notes WHERE id_module=? AND id_stagiaire IN ($delPh)")
                ->execute(array_merge([$id_module], $validSids));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO module_notes (id_stagiaire, id_module, note, type) VALUES (?, ?, ?, ?)'
        );
        foreach ($rows as $sid => $vals) {
            $sid = (int)$sid;
            if ($sid <= 0) continue;

            // Contrôles 1..nb_controles — skip blank (never insert NULL)
            for ($i = 1; $i <= $nb_controles; $i++) {
                $key = "controle_$i";
                $raw = trim((string)($vals[$key] ?? ''));
                if ($raw === '') continue;
                $val = (float)str_replace(',', '.', $raw);
                if ($val < 0 || $val > 20) continue;
                $stmt->execute([$sid, $id_module, $val, $key]);
            }
            // Théorique — skip blank
            $raw = trim((string)($vals['theorique'] ?? ''));
            if ($raw !== '') {
                $val = (float)str_replace(',', '.', $raw);
                if ($val >= 0 && $val <= 20) $stmt->execute([$sid, $id_module, $val, 'theorique']);
            }
            // Pratique — skip blank
            $raw = trim((string)($vals['pratique'] ?? ''));
            if ($raw !== '') {
                $val = (float)str_replace(',', '.', $raw);
                if ($val >= 0 && $val <= 20) $stmt->execute([$sid, $id_module, $val, 'pratique']);
            }

            $saved++;
        }
    }
    flash_set("Notes enregistrées pour $saved stagiaire(s).", 'success');
    $qs = http_build_query([
        'id_classe'  => $_POST['id_classe']  ?? '',
        'id_module'  => $_POST['id_module']  ?? '',
        'annee'      => $_POST['annee']      ?? '',
        'id_filiere' => $_POST['id_filiere'] ?? '',
        'niveau'     => $_POST['niveau']     ?? '',
    ]);
    header("Location: notes.php?$qs");
    exit;
}

// ── FILTER PARAMS ─────────────────────────────────────────────────────────
$selAnnee   = trim((string)($_GET['annee']      ?? ''));
$selFiliere = (int)($_GET['id_filiere'] ?? 0);
$selNiveau  = trim((string)($_GET['niveau']     ?? ''));
$selClasse  = (int)($_GET['id_classe']  ?? 0);
$selModule  = (int)($_GET['id_module']  ?? 0);
$highlightSid = (int)($_GET['highlight'] ?? 0);

// ── DATA LOADS ────────────────────────────────────────────────────────────
$allAnnees = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);
if ($selAnnee === '') { $selAnnee = $_SESSION['global_annee_scolaire'] ?? ($allAnnees[0] ?? ''); }

$allFilieres = $pdo->query(
    "SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere"
)->fetchAll();
if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }

$allNiveaux = [];
if ($selFiliere > 0 && $selAnnee !== '') {
    $st = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $st->execute([$selFiliere, $selAnnee]);
    $allNiveaux = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($selNiveau === '' && !empty($allNiveaux)) { $selNiveau = $allNiveaux[0]; }
}

$allClasses = [];
if ($selFiliere > 0 && $selAnnee !== '' && $selNiveau !== '') {
    $st = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $st->execute([$selFiliere, $selAnnee, $selNiveau]);
    $allClasses = $st->fetchAll();
    if ($selClasse === 0 && !empty($allClasses)) { $selClasse = (int)$allClasses[0]['id_classe']; }
}

$allModules = [];
if ($selFiliere > 0) {
    $st = $pdo->prepare("SELECT id_module, nom_module, nb_controles FROM modules WHERE id_filiere=? ORDER BY nom_module");
    $st->execute([$selFiliere]);
    $allModules = $st->fetchAll();
    if ($selModule === 0 && !empty($allModules)) { $selModule = (int)$allModules[0]['id_module']; }
}

// Auto-select first module when arriving from hub
if ($highlightSid > 0 && $selClasse > 0 && $selModule === 0 && !empty($allModules)) {
    $qs = http_build_query([
        'annee'      => $selAnnee,
        'id_filiere' => $selFiliere,
        'niveau'     => $selNiveau,
        'id_classe'  => $selClasse,
        'id_module'  => (int)$allModules[0]['id_module'],
        'highlight'  => $highlightSid,
    ]);
    header("Location: notes.php?$qs");
    exit;
}

// ── MODULE INFO + nb_controles ────────────────────────────────────────────
$classeInfo   = null;
$moduleInfo   = null;
$nb_controles = 1;

if ($selClasse > 0) {
    $r = $pdo->prepare("SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $r->execute([$selClasse]);
    $classeInfo = $r->fetch();
}
if ($selModule > 0) {
    $r = $pdo->prepare("SELECT nom_module, nb_controles FROM modules WHERE id_module=?");
    $r->execute([$selModule]);
    $moduleInfo = $r->fetch();
    if ($moduleInfo) {
        $nb_controles = max(1, (int)$moduleInfo['nb_controles']);
    }
}

// ── STAGIAIRES + EVALUE ENTRIES ───────────────────────────────────────────
$stagiaires  = [];
$evalByStag  = []; // [id_stagiaire][type] = note

if ($selClasse > 0 && $selModule > 0) {
    $stSt = $pdo->prepare(
        "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, s.cin
         FROM stagiaires s
         WHERE s.id_classe = ?
         ORDER BY s.nom, s.prenom"
    );
    $stSt->execute([$selClasse]);
    $stagiaires = $stSt->fetchAll();

    if (!empty($stagiaires)) {
        $sids = array_column($stagiaires, 'id_stagiaire');
        $placeholders = implode(',', array_fill(0, count($sids), '?'));
        $stEv = $pdo->prepare(
            "SELECT id_stagiaire, type, note FROM module_notes
             WHERE id_stagiaire IN ($placeholders) AND id_module = ?"
        );
        $stEv->execute([...$sids, $selModule]);
        foreach ($stEv->fetchAll() as $ev) {
            $evalByStag[(int)$ev['id_stagiaire']][$ev['type']] =
                $ev['note'] !== null ? (string)$ev['note'] : '';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.notes-shell { max-width: 1200px; margin: 0 auto; padding-bottom: 3rem; }
.notes-filter-card {
    background: #16161e;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    padding: 1.5rem;
    margin-bottom: 1.75rem;
}
.notes-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 1rem;
    align-items: end;
}
.notes-filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
.notes-filter-group label { font-size: 0.72rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.notes-filter-group select {
    background: #0d0d14;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: #fff;
    padding: 0.55rem 0.75rem;
    font-size: 0.88rem;
    cursor: pointer;
    width: 100%;
}
.notes-filter-group select:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-afficher {
    background: rgba(168,85,247,0.2);
    color: #a855f7;
    border: 1px solid rgba(168,85,247,0.4);
    border-radius: 8px;
    padding: 0.6rem 1.4rem;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.btn-afficher:hover { background: rgba(168,85,247,0.35); }
.btn-afficher:disabled { opacity: 0.4; cursor: not-allowed; }

.notes-table-wrap { overflow-x: auto; }
.notes-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.notes-table thead th {
    background: rgba(255,255,255,0.03);
    color: #71717a;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: .1em;
    font-weight: 800;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: left;
    white-space: nowrap;
}
.notes-table thead th:not(:first-child):not(:nth-child(2)) { text-align: center; }
.notes-table thead th.th-group-controle {
    background: rgba(168,85,247,0.07);
    color: #c084fc;
    border-bottom-color: rgba(168,85,247,0.2);
}
.notes-table thead th.th-group-examen {
    background: rgba(56,189,248,0.06);
    color: #7dd3fc;
    border-bottom-color: rgba(56,189,248,0.15);
}
.notes-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background .15s;
}
.notes-table tbody tr:hover { background: rgba(168,85,247,0.08); }
.notes-table tbody td { padding: 0.7rem 1rem; }
.notes-table tbody td:not(:first-child):not(:nth-child(2)) { text-align: center; }

.stag-name { font-weight: 700; color: #fff; font-size: 0.88rem; }
.stag-cin  { color: #71717a; font-size: 0.75rem; margin-top: 2px; }

.note-input {
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    color: #fff;
    width: 72px;
    padding: 0.45rem 0.5rem;
    text-align: center;
    font-size: 0.88rem;
    transition: border-color .2s, background .2s;
}
.note-input:focus {
    outline: none;
    border-color: rgba(168,85,247,0.6);
    background: rgba(168,85,247,0.08);
}
.note-input.has-value { border-color: rgba(16,185,129,0.4); background: rgba(16,185,129,0.07); }

.btn-save-notes {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16,185,129,0.2);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.4);
    border-radius: 10px;
    padding: 0.7rem 1.8rem;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
}
.btn-save-notes:hover { background: rgba(16,185,129,0.35); }

.notes-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.notes-context-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(168,85,247,0.12);
    border: 1px solid rgba(168,85,247,0.25);
    border-radius: 8px;
    padding: 0.4rem 0.9rem;
    font-size: 0.82rem;
    color: #d8b4fe;
    font-weight: 600;
}
.notes-context-badge span { color: #a1a1aa; font-weight: 400; }

/* controle picker modal */
.ctrl-modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.ctrl-modal-overlay.open { display: flex; }
.ctrl-modal {
    background: #18181f;
    border: 1px solid rgba(168,85,247,0.35);
    border-radius: 14px;
    padding: 1.75rem 2rem;
    min-width: 300px;
    max-width: 90vw;
}
.ctrl-modal h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #e4e4e7;
    margin: 0 0 1rem;
}
.ctrl-modal select {
    width: 100%;
    background: #0d0d14;
    border: 1px solid rgba(168,85,247,0.4);
    border-radius: 8px;
    color: #fff;
    padding: 0.55rem 0.75rem;
    font-size: 0.9rem;
    margin-bottom: 1.25rem;
    cursor: pointer;
}
.ctrl-modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
.ctrl-modal-actions button {
    border-radius: 8px;
    padding: 0.5rem 1.2rem;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
}
.btn-modal-cancel { background: rgba(255,255,255,0.07); color: #a1a1aa; border-color: rgba(255,255,255,0.1); }
.btn-modal-print  { background: rgba(245,158,11,0.2); color: #fcd34d; border-color: rgba(245,158,11,0.4); }

.notes-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #52525b;
    font-size: 0.95rem;
}
.notes-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; color: #3f3f46; }
</style>

<div class="notes-shell">

    <div style="margin-bottom:1rem;">
        <a href="index.php" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <!-- ── Filter card ── -->
    <div class="notes-filter-card">
        <form method="get" action="notes.php" id="notes-filter-form">
        <div class="notes-filter-grid">

            <div class="notes-filter-group">
                <label>Année scolaire</label>
                <select name="annee" id="nf-annee">
                    <option value="">— Choisir —</option>
                    <?php foreach ($allAnnees as $ay): ?>
                    <option value="<?= h($ay) ?>" <?= $ay === $selAnnee ? 'selected' : '' ?>><?= h($ay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Filière</label>
                <select name="id_filiere" id="nf-filiere" <?= $selAnnee === '' ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allFilieres as $f): ?>
                    <option value="<?= (int)$f['id_filiere'] ?>" <?= (int)$f['id_filiere'] === $selFiliere ? 'selected' : '' ?>><?= h((string)$f['nom_filiere']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Niveau</label>
                <select name="niveau" id="nf-niveau" <?= ($selFiliere === 0 || $selAnnee === '') ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allNiveaux as $nv): ?>
                    <option value="<?= h($nv) ?>" <?= $nv === $selNiveau ? 'selected' : '' ?>><?= h($nv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Classe</label>
                <select name="id_classe" id="nf-classe" <?= ($selNiveau === '' || $selFiliere === 0) ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allClasses as $cl): ?>
                    <option value="<?= (int)$cl['id_classe'] ?>" <?= (int)$cl['id_classe'] === $selClasse ? 'selected' : '' ?>><?= h((string)$cl['nom_classe']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Module</label>
                <select name="id_module" id="nf-module" <?= ($selFiliere === 0 || $selClasse === 0) ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allModules as $m): ?>
                    <option value="<?= (int)$m['id_module'] ?>" <?= (int)$m['id_module'] === $selModule ? 'selected' : '' ?>><?= h((string)$m['nom_module']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-afficher"
                    <?= ($selClasse === 0 || $selModule === 0) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-table-list"></i> Afficher
                </button>
            </div>

        </div>
        </form>
    </div>

    <!-- ── Notes grid ── -->
    <?php if ($selClasse > 0 && $selModule > 0): ?>


    <form method="post" action="notes.php">
        <?= csrf_hidden() ?>
        <input type="hidden" name="bulk_save_notes" value="1">
        <input type="hidden" name="id_module"    value="<?= $selModule ?>">
        <input type="hidden" name="id_classe"    value="<?= $selClasse ?>">
        <input type="hidden" name="annee"        value="<?= h($selAnnee) ?>">
        <input type="hidden" name="id_filiere"   value="<?= $selFiliere ?>">
        <input type="hidden" name="niveau"       value="<?= h($selNiveau) ?>">
        <input type="hidden" name="nb_controles" value="<?= $nb_controles ?>">

        <div class="notes-header-bar">
            <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;">
                <?php if ($classeInfo): ?>
                <div class="notes-context-badge">
                    <i class="fa-solid fa-users"></i>
                    <?= h((string)$classeInfo['nom_classe']) ?>
                    <span>·</span><?= h((string)$classeInfo['nom_filiere']) ?>
                    <span>·</span><?= h((string)$classeInfo['annee_scolaire']) ?>
                </div>
                <?php endif; ?>
                <?php if ($moduleInfo): ?>
                <div class="notes-context-badge" style="background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.25);color:#6ee7b7;">
                    <i class="fa-solid fa-book-open"></i>
                    <?= h((string)$moduleInfo['nom_module']) ?>
                </div>
                <?php endif; ?>
                <div class="notes-context-badge" style="background:rgba(250,204,21,0.08);border-color:rgba(250,204,21,0.2);color:#fde047;">
                    <i class="fa-solid fa-user-graduate"></i>
                    <?= count($stagiaires) ?> stagiaire<?= count($stagiaires) !== 1 ? 's' : '' ?>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <?php
                $bulQs   = http_build_query(['annee'=>$selAnnee,'id_filiere'=>$selFiliere,'niveau'=>$selNiveau,'id_classe'=>$selClasse,'id_module'=>$selModule]);
                $printQs = http_build_query(['id_classe'=>$selClasse,'id_module'=>$selModule]);
                ?>
                <a href="bulletins.php?<?= $bulQs ?>" class="btn-save-notes" style="text-decoration:none;background:rgba(56,189,248,0.15);color:#7dd3fc;border-color:rgba(56,189,248,0.3);">
                    <i class="fa-solid fa-chart-bar"></i> Voir les bulletins
                </a>
                <?php if ($nb_controles <= 1): ?>
                <a href="print_tableau_notes_controle.php?<?= $printQs ?>&controle_no=1" target="_blank" class="btn-save-notes" style="text-decoration:none;background:rgba(245,158,11,0.15);color:#fcd34d;border-color:rgba(245,158,11,0.3);">
                    <i class="fa-solid fa-print"></i> Tableau de Contrôle
                </a>
                <?php else: ?>
                <button type="button" onclick="openCtrlModal()" class="btn-save-notes" style="background:rgba(245,158,11,0.15);color:#fcd34d;border-color:rgba(245,158,11,0.3);">
                    <i class="fa-solid fa-print"></i> Tableau de Contrôle
                </button>
                <?php endif; ?>
                <button type="submit" class="btn-save-notes">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                </button>
            </div>
        </div>


        <?php if (count($stagiaires) === 0): ?>
        <div class="notes-empty">
            <i class="fa-solid fa-user-slash"></i>
            Aucun stagiaire dans cette classe.
        </div>
        <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <div class="notes-table-wrap">
                <table class="notes-table">
                    <thead>
                        <tr>
                            <th style="width:8%;white-space:nowrap;">Code</th>
                            <th style="width:25%;">Stagiaire</th>
                            <?php if ($nb_controles === 1): ?>
                                <th class="th-group-controle">Contrôle</th>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= $nb_controles; $i++): ?>
                                    <th class="th-group-controle">Contrôle <?= $i ?></th>
                                <?php endfor; ?>
                            <?php endif; ?>
                            <th class="th-group-examen">Théorique</th>
                            <th class="th-group-examen">Pratique</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stagiaires as $s):
                        $sid = (int)$s['id_stagiaire'];
                        $ev  = $evalByStag[$sid] ?? [];
                    ?>
                    <tr id="row-<?= $sid ?>">
                        <td style="font-size:0.75rem;color:#a1a1aa;font-weight:600;white-space:nowrap;"><?= h((string)($s['num_inscri'] ?? '')) ?></td>
                        <td>
                            <div class="stag-name"><?= h(trim($s['nom'].' '.$s['prenom'])) ?></div>
                            <?php if (!empty($s['cin'])): ?>
                            <div class="stag-cin"><?= h((string)$s['cin']) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php for ($i = 1; $i <= $nb_controles; $i++):
                            $key = "controle_$i";
                            $val = $ev[$key] ?? '';
                        ?>
                        <td>
                            <input type="number" class="note-input<?= $val !== '' ? ' has-value' : '' ?>"
                                name="notes[<?= $sid ?>][<?= $key ?>]"
                                value="<?= h($val) ?>"
                                min="0" max="20" step="0.25"
                                placeholder="—">
                        </td>
                        <?php endfor; ?>
                        <td>
                            <?php $nt = $ev['theorique'] ?? ''; ?>
                            <input type="number" class="note-input<?= $nt !== '' ? ' has-value' : '' ?>"
                                name="notes[<?= $sid ?>][theorique]"
                                value="<?= h($nt) ?>"
                                min="0" max="20" step="0.25"
                                placeholder="—">
                        </td>
                        <td>
                            <?php $np = $ev['pratique'] ?? ''; ?>
                            <input type="number" class="note-input<?= $np !== '' ? ' has-value' : '' ?>"
                                name="notes[<?= $sid ?>][pratique]"
                                value="<?= h($np) ?>"
                                min="0" max="20" step="0.25"
                                placeholder="—">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </form>

    <?php else: ?>
    <div class="notes-empty">
        <i class="fa-solid fa-graduation-cap"></i>
        Sélectionnez une classe et un module, puis cliquez sur <strong>Afficher</strong>.
    </div>
    <?php endif; ?>

</div>

<?php if ($nb_controles > 1): ?>
<div class="ctrl-modal-overlay" id="ctrl-modal-overlay">
    <div class="ctrl-modal">
        <h3><i class="fa-solid fa-print" style="margin-right:.5rem;color:#fcd34d;"></i>Quel contrôle imprimer ?</h3>
        <select id="ctrl-modal-select">
            <?php for ($i = 1; $i <= $nb_controles; $i++): ?>
            <option value="<?= $i ?>">Contrôle <?= $i ?></option>
            <?php endfor; ?>
        </select>
        <div class="ctrl-modal-actions">
            <button class="btn-modal-cancel" onclick="closeCtrlModal()">Annuler</button>
            <button class="btn-modal-print"  onclick="goCtrlPrint()"><i class="fa-solid fa-print" style="margin-right:.35rem;"></i>Imprimer</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const form    = document.getElementById('notes-filter-form');
    const annee   = document.getElementById('nf-annee');
    const filiere = document.getElementById('nf-filiere');
    const niveau  = document.getElementById('nf-niveau');
    const classe  = document.getElementById('nf-classe');
    const module_ = document.getElementById('nf-module');
    const btn     = form.querySelector('.btn-afficher');

    function cascade(changed) {
        const order = [annee, filiere, niveau, classe, module_];
        const idx   = order.indexOf(changed);
        for (let i = idx + 1; i < order.length; i++) {
            order[i].value    = '';
            order[i].disabled = true;
        }
        form.submit();
    }

    if (annee.value)   { filiere.disabled = false; }
    if (filiere.value) { niveau.disabled  = false; }
    if (niveau.value)  { classe.disabled  = false; }
    if (classe.value)  { module_.disabled = false; }

    function syncBtn() { btn.disabled = !(classe.value && module_.value); }
    syncBtn();

    annee.addEventListener('change',   () => cascade(annee));
    filiere.addEventListener('change', () => cascade(filiere));
    niveau.addEventListener('change',  () => cascade(niveau));
    classe.addEventListener('change',  () => cascade(classe));
    module_.addEventListener('change', () => { syncBtn(); form.submit(); });

    document.querySelectorAll('.note-input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            this.classList.toggle('has-value', this.value !== '');
        });
    });

    var hlSid = <?= $highlightSid ?>;
    if (hlSid > 0) {
        var tr = document.getElementById('row-' + hlSid);
        if (tr) {
            setTimeout(function(){
                tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                tr.style.transition  = 'background 0.2s, outline 0.2s';
                tr.style.background  = 'rgba(168,85,247,0.22)';
                tr.style.outline     = '2px solid rgba(168,85,247,0.6)';
                tr.style.borderRadius = '6px';
                setTimeout(function(){ tr.style.background = ''; tr.style.outline = ''; }, 2800);
            }, 300);
        }
    }
})();

var _printBase = 'print_tableau_notes_controle.php?id_classe=<?= $selClasse ?>&id_module=<?= $selModule ?>';

function openCtrlModal() {
    var el = document.getElementById('ctrl-modal-overlay');
    if (el) el.classList.add('open');
}
function closeCtrlModal() {
    var el = document.getElementById('ctrl-modal-overlay');
    if (el) el.classList.remove('open');
}
function goCtrlPrint() {
    var sel = document.getElementById('ctrl-modal-select');
    var no  = sel ? sel.value : 1;
    window.open(_printBase + '&controle_no=' + no, '_blank');
    closeCtrlModal();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCtrlModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
