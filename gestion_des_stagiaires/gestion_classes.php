<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Gestion des Classes';
$curPage   = 'classes';

if (!gds_is_directeur()) {
    flash_set('Accès réservé au Directeur.', 'warning');
    redirect('index.php');
}

// Ensure capacite column exists
try { $pdo->exec("ALTER TABLE classes ADD COLUMN IF NOT EXISTS capacite INT UNSIGNED NOT NULL DEFAULT 30"); } catch (\Throwable $ignored) {}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    header('Content-Type: application/json');

    if (isset($_POST['add_classe'])) {
        $nomClasse = trim((string)($_POST['nom_classe']     ?? ''));
        $idFiliere = (int)($_POST['id_filiere']             ?? 0);
        $niveau    = trim((string)($_POST['niveau']         ?? ''));
        $annee     = trim((string)($_POST['annee_scolaire'] ?? ''));
        $capacite  = max(1, (int)($_POST['capacite']        ?? 30));
        if ($nomClasse === '' || $idFiliere <= 0 || $niveau === '' || $annee === '') {
            echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis.']); exit;
        }
        $chkF = $pdo->prepare('SELECT id_filiere FROM filieres WHERE id_filiere = ?');
        $chkF->execute([$idFiliere]);
        if (!$chkF->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Filière invalide.']); exit;
        }
        try {
            $pdo->prepare('INSERT INTO classes (nom_classe, annee_scolaire, niveau, id_filiere, capacite) VALUES (?,?,?,?,?)')
                ->execute([$nomClasse, $annee, $niveau, $idFiliere, $capacite]);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'msg' => 'Classe créée avec succès.', 'id_classe' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    if (isset($_POST['edit_classe'])) {
        $idClasse  = (int)($_POST['id_classe']  ?? 0);
        $nomClasse = trim((string)($_POST['nom_classe'] ?? ''));
        $capacite  = max(1, (int)($_POST['capacite'] ?? 30));
        if ($idClasse <= 0 || $nomClasse === '') {
            echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
        }
        try {
            $pdo->prepare('UPDATE classes SET nom_classe=?, capacite=? WHERE id_classe=?')
                ->execute([$nomClasse, $capacite, $idClasse]);
            echo json_encode(['success' => true, 'msg' => 'Classe mise à jour.']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
$classes = $pdo->query(
    "SELECT c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau,
            COALESCE(c.capacite, 30) AS capacite,
            f.nom_filiere, f.id_filiere,
            COUNT(s.id_stagiaire) AS effectif,
            GREATEST(0, COALESCE(c.capacite, 30) - COUNT(s.id_stagiaire)) AS places_libres
     FROM classes c
     JOIN filieres f ON f.id_filiere = c.id_filiere
     LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
     GROUP BY c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau, c.capacite,
              f.nom_filiere, f.id_filiere
     ORDER BY c.annee_scolaire DESC, f.nom_filiere ASC, c.niveau ASC"
)->fetchAll();

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere ASC')->fetchAll();
$annees   = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/includes/header.php';
?>
<style>
.gc-card { background:#12122a; border:1px solid rgba(168,85,247,0.15); border-radius:12px; overflow:hidden; }
.gc-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.gc-table th {
    background:rgba(168,85,247,0.08); color:#a1a1aa; font-size:0.7rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.07em; padding:0.65rem 1rem;
    text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); white-space:nowrap;
}
.gc-table td { padding:0.75rem 1rem; border-bottom:1px solid rgba(255,255,255,0.05); color:#e4e4e7; vertical-align:middle; }
.gc-table tr:last-child td { border-bottom:none; }
.gc-table tr:hover td { background:rgba(168,85,247,0.04); }
.gc-badge { display:inline-flex; align-items:center; padding:0.15rem 0.55rem; border-radius:6px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
.gc-badge-filiere { background:rgba(168,85,247,0.12); color:#c4b5fd; border:1px solid rgba(168,85,247,0.25); }
.gc-badge-niveau  { background:rgba(99,102,241,0.12); color:#a5b4fc; border:1px solid rgba(99,102,241,0.2); }
.gc-cap-bar  { display:flex; align-items:center; gap:0.5rem; }
.gc-cap-track { height:6px; width:72px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; flex-shrink:0; }
.gc-cap-fill  { height:100%; border-radius:3px; }
.gc-btn-edit { background:rgba(168,85,247,0.12); border:1px solid rgba(168,85,247,0.3); color:#c4b5fd; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; white-space:nowrap; }
.gc-btn-edit:hover { background:rgba(168,85,247,0.25); }
.gc-btn-voir { background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.25); color:#a5b4fc; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; white-space:nowrap; }
.gc-btn-voir:hover { background:rgba(99,102,241,0.22); }
.gc-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9000; align-items:center; justify-content:center; padding:1rem; }
.gc-modal-box { background:#1a1a2e; border-radius:14px; padding:1.75rem; width:100%; max-width:480px; }
.gc-field { margin-bottom:1rem; }
.gc-field label { display:block; font-size:0.78rem; color:#a1a1aa; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; }
.gc-field input, .gc-field select {
    width:100%; background:#111; border:1.5px solid rgba(255,255,255,0.1); border-radius:8px;
    color:#fff; padding:0.62rem 0.85rem; font-size:0.9rem; outline:none; box-sizing:border-box;
}
.gc-field input:focus, .gc-field select:focus { border-color:#a855f7; }
.gc-submit { width:100%; padding:0.7rem; border-radius:8px; background:rgba(168,85,247,0.15); border:1px solid rgba(168,85,247,0.4); color:#d8b4fe; font-weight:700; font-size:0.92rem; cursor:pointer; }
.gc-submit:hover { background:rgba(168,85,247,0.28); }
.gc-err-box { display:none; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:0.55rem 0.9rem; margin-bottom:0.85rem; font-size:0.83rem; color:#f87171; }
.gc-toast {
    position:fixed; bottom:1.5rem; right:1.5rem; z-index:99999;
    background:#1e1e3f; border:1px solid rgba(168,85,247,0.4); border-radius:10px;
    padding:0.85rem 1.2rem; font-size:0.88rem; color:#e4e4e7;
    display:flex; align-items:center; gap:0.6rem;
    box-shadow:0 4px 24px rgba(0,0,0,0.4);
    opacity:0; transform:translateY(12px); transition:all .25s; pointer-events:none; max-width:380px;
}
.gc-toast.show { opacity:1; transform:translateY(0); pointer-events:auto; }
@media (max-width:680px) {
    .gc-table th:nth-child(4), .gc-table td:nth-child(4) { display:none; }
    .gc-table th:nth-child(6), .gc-table td:nth-child(6) { display:none; }
}
</style>

<div class="main-content">

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 0.2rem;font-size:1.3rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:0.6rem;">
            <i class="fa-solid fa-chalkboard" style="color:#a855f7;"></i> Gestion des Classes
        </h1>
        <p style="margin:0;font-size:0.83rem;color:#71717a;"><?= count($classes) ?> classe(s) enregistrée(s)</p>
    </div>
    <button type="button" onclick="openAddModal()"
            style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.1rem;border-radius:8px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.35);color:#6ee7b7;font-weight:700;font-size:0.88rem;cursor:pointer;">
        <i class="fa-solid fa-plus"></i> Ajouter une classe
    </button>
</div>

<?php if (empty($classes)): ?>
<div style="text-align:center;padding:4rem 1rem;color:#71717a;">
    <i class="fa-solid fa-chalkboard" style="font-size:2.5rem;margin-bottom:1rem;display:block;opacity:0.25;"></i>
    Aucune classe enregistrée. Cliquez sur "Ajouter une classe" pour commencer.
</div>
<?php else: ?>

<div class="gc-card">
    <div style="overflow-x:auto;">
    <table class="gc-table">
        <thead>
            <tr>
                <th><i class="fa-solid fa-chalkboard-user" style="margin-right:0.3rem;color:#a855f7;"></i> Classe</th>
                <th>Filière</th>
                <th>Niveau</th>
                <th>Année scolaire</th>
                <th>Capacité</th>
                <th>Occupancy</th>
                <th>Places libres</th>
                <th style="text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($classes as $cls): ?>
            <?php
                $eff    = (int)$cls['effectif'];
                $cap    = (int)$cls['capacite'];
                $pct    = $cap > 0 ? min(100, (int)round($eff / $cap * 100)) : 0;
                $barCol = $pct >= 100 ? '#ef4444' : ($pct >= 80 ? '#fb923c' : '#10b981');
                $libres = max(0, $cap - $eff);
            ?>
            <tr>
                <td>
                    <span style="font-weight:700;color:#fff;"><?= h($cls['nom_classe']) ?></span>
                </td>
                <td><span class="gc-badge gc-badge-filiere"><?= h($cls['nom_filiere']) ?></span></td>
                <td><span class="gc-badge gc-badge-niveau"><?= h($cls['niveau']) ?></span></td>
                <td style="color:#a1a1aa;font-size:0.85rem;"><?= h($cls['annee_scolaire']) ?></td>
                <td style="font-weight:600;color:#d8b4fe;"><?= $cap ?></td>
                <td>
                    <div class="gc-cap-bar">
                        <span style="font-weight:600;color:<?= $barCol ?>;min-width:24px;"><?= $eff ?></span>
                        <div class="gc-cap-track">
                            <div class="gc-cap-fill" style="width:<?= $pct ?>%;background:<?= $barCol ?>;"></div>
                        </div>
                        <span style="font-size:0.7rem;color:#71717a;"><?= $pct ?>%</span>
                    </div>
                </td>
                <td>
                    <?php if ($libres === 0): ?>
                        <span style="font-weight:700;color:#ef4444;font-size:0.82rem;">
                            <i class="fa-solid fa-ban"></i> Pleine
                        </span>
                    <?php elseif ($libres <= 5): ?>
                        <span style="font-weight:700;color:#fb923c;"><?= $libres ?> place(s)</span>
                    <?php else: ?>
                        <span style="font-weight:700;color:#10b981;"><?= $libres ?> place(s)</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <div style="display:flex;gap:0.4rem;justify-content:center;flex-wrap:wrap;">
                        <button class="gc-btn-edit"
                            onclick="openEditModal(<?= $cls['id_classe'] ?>, <?= htmlspecialchars(json_encode($cls['nom_classe']), ENT_QUOTES) ?>, <?= $cap ?>)">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </button>
                        <a href="stagiaires.php?classe=<?= $cls['id_classe'] ?>" class="gc-btn-voir">
                            <i class="fa-solid fa-users"></i> Étudiants
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>
</div>

<!-- ── EDIT MODAL ────────────────────────────────────────────────────────────── -->
<div id="gc-edit-modal" class="gc-modal">
    <div class="gc-modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#a855f7;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-pen"></i> Modifier la classe
            </h3>
            <button type="button" onclick="closeEditModal()" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-tag" style="color:#a855f7;margin-right:0.3rem;"></i> Nom de la classe</label>
            <input type="text" id="edit-nom" maxlength="128" placeholder="Ex: 1A TSDI">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-users" style="color:#a855f7;margin-right:0.3rem;"></i> Capacité (places)</label>
            <input type="number" id="edit-cap" min="1" max="500">
        </div>
        <div id="gc-edit-err" class="gc-err-box"></div>
        <button type="button" class="gc-submit" onclick="submitEdit()">
            <i class="fa-solid fa-check" style="margin-right:0.4rem;"></i> Enregistrer les modifications
        </button>
    </div>
</div>

<!-- ── ADD MODAL ─────────────────────────────────────────────────────────────── -->
<div id="gc-add-modal" class="gc-modal">
    <div class="gc-modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#10b981;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-plus"></i> Nouvelle classe
            </h3>
            <button type="button" onclick="closeAddModal()" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-tag" style="color:#10b981;margin-right:0.3rem;"></i> Nom de la classe <span style="color:#ef4444;">*</span></label>
            <input type="text" id="add-nom" maxlength="128" placeholder="Ex: 1A TSDI">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-layer-group" style="color:#10b981;margin-right:0.3rem;"></i> Filière <span style="color:#ef4444;">*</span></label>
            <select id="add-filiere">
                <option value="">— Sélectionner —</option>
                <?php foreach ($filieres as $f): ?>
                <option value="<?= $f['id_filiere'] ?>"><?= h($f['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-stairs" style="color:#10b981;margin-right:0.3rem;"></i> Niveau <span style="color:#ef4444;">*</span></label>
            <select id="add-niveau">
                <option value="">— Sélectionner —</option>
                <option value="1ère année">1ère année</option>
                <option value="2ème année">2ème année</option>
            </select>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-calendar" style="color:#10b981;margin-right:0.3rem;"></i> Année scolaire <span style="color:#ef4444;">*</span></label>
            <select id="add-annee" onchange="document.getElementById('add-annee-custom-wrap').style.display=this.value==='_custom'?'block':'none'">
                <option value="">— Sélectionner —</option>
                <?php foreach ($annees as $ay): ?>
                <option value="<?= h($ay) ?>"><?= h($ay) ?></option>
                <?php endforeach; ?>
                <option value="_custom">Autre (saisir manuellement)</option>
            </select>
        </div>
        <div class="gc-field" id="add-annee-custom-wrap" style="display:none;">
            <label>Saisir l'année (format AAAA/AAAA)</label>
            <input type="text" id="add-annee-custom" maxlength="9" placeholder="2026/2027">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-users" style="color:#10b981;margin-right:0.3rem;"></i> Capacité</label>
            <input type="number" id="add-cap" min="1" max="500" value="30">
        </div>
        <div id="gc-add-err" class="gc-err-box"></div>
        <button type="button" onclick="submitAdd()"
                style="width:100%;padding:0.7rem;border-radius:8px;background:rgba(16,185,129,0.14);border:1px solid rgba(16,185,129,0.4);color:#6ee7b7;font-weight:700;font-size:0.92rem;cursor:pointer;">
            <i class="fa-solid fa-plus" style="margin-right:0.4rem;"></i> Créer la classe
        </button>
    </div>
</div>

<div id="gc-toast" class="gc-toast"></div>

<script>
var _editId = 0;
var _csrf   = <?= json_encode(csrf_token()) ?>;

function showToast(msg, ok) {
    var t = document.getElementById('gc-toast');
    var icon = ok ? 'circle-check' : 'triangle-exclamation';
    var col  = ok ? '#10b981' : '#ef4444';
    t.innerHTML = '<i class="fa-solid fa-' + icon + '" style="color:' + col + ';"></i> ' + msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3500);
}

// ── Edit ──────────────────────────────────────────────────────────────────────
function openEditModal(id, nom, cap) {
    _editId = id;
    document.getElementById('edit-nom').value = nom;
    document.getElementById('edit-cap').value = cap;
    document.getElementById('gc-edit-err').style.display = 'none';
    document.getElementById('gc-edit-modal').style.display = 'flex';
    setTimeout(function() { document.getElementById('edit-nom').focus(); }, 80);
}
function closeEditModal() {
    document.getElementById('gc-edit-modal').style.display = 'none';
}
function submitEdit() {
    var nom = document.getElementById('edit-nom').value.trim();
    var cap = parseInt(document.getElementById('edit-cap').value, 10);
    var err = document.getElementById('gc-edit-err');
    if (!nom || isNaN(cap) || cap < 1) {
        err.textContent = 'Nom et capacité requis (capacité ≥ 1).';
        err.style.display = 'block'; return;
    }
    err.style.display = 'none';
    var fd = new FormData();
    fd.append('edit_classe', '1');
    fd.append('csrf_token', _csrf);
    fd.append('id_classe',  _editId);
    fd.append('nom_classe', nom);
    fd.append('capacite',   cap);
    fetch('gestion_classes.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                closeEditModal();
                showToast(d.msg, true);
                setTimeout(function() { location.reload(); }, 900);
            } else {
                err.textContent = d.error || 'Erreur inconnue.';
                err.style.display = 'block';
            }
        })
        .catch(function() {
            err.textContent = 'Erreur réseau.';
            err.style.display = 'block';
        });
}

// ── Add ───────────────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('add-nom').value    = '';
    document.getElementById('add-filiere').value = '';
    document.getElementById('add-niveau').value  = '';
    document.getElementById('add-annee').value   = '';
    document.getElementById('add-cap').value     = 30;
    document.getElementById('add-annee-custom-wrap').style.display = 'none';
    document.getElementById('gc-add-err').style.display = 'none';
    document.getElementById('gc-add-modal').style.display = 'flex';
    setTimeout(function() { document.getElementById('add-nom').focus(); }, 80);
}
function closeAddModal() {
    document.getElementById('gc-add-modal').style.display = 'none';
}
function submitAdd() {
    var nom    = document.getElementById('add-nom').value.trim();
    var fil    = document.getElementById('add-filiere').value;
    var niv    = document.getElementById('add-niveau').value;
    var anneeV = document.getElementById('add-annee').value;
    var annee  = anneeV === '_custom' ? document.getElementById('add-annee-custom').value.trim() : anneeV;
    var cap    = parseInt(document.getElementById('add-cap').value, 10);
    var err    = document.getElementById('gc-add-err');

    if (!nom || !fil || !niv || !annee || isNaN(cap) || cap < 1) {
        err.textContent = 'Tous les champs sont requis (capacité ≥ 1).';
        err.style.display = 'block'; return;
    }
    if (anneeV === '_custom' && !/^\d{4}\/\d{4}$/.test(annee)) {
        err.textContent = 'Format année invalide — utilisez AAAA/AAAA (ex: 2026/2027).';
        err.style.display = 'block'; return;
    }
    err.style.display = 'none';

    var fd = new FormData();
    fd.append('add_classe',     '1');
    fd.append('csrf_token',     _csrf);
    fd.append('nom_classe',     nom);
    fd.append('id_filiere',     fil);
    fd.append('niveau',         niv);
    fd.append('annee_scolaire', annee);
    fd.append('capacite',       cap);
    fetch('gestion_classes.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                closeAddModal();
                showToast(d.msg, true);
                setTimeout(function() { location.reload(); }, 900);
            } else {
                err.textContent = d.error || 'Erreur inconnue.';
                err.style.display = 'block';
            }
        })
        .catch(function() {
            err.textContent = 'Erreur réseau.';
            err.style.display = 'block';
        });
}

// Backdrop + ESC close
['gc-edit-modal','gc-add-modal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('gc-edit-modal').style.display = 'none';
        document.getElementById('gc-add-modal').style.display  = 'none';
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
