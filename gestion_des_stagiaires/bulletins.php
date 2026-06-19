<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
gds_require_admin_session();

$pageTitle = 'Bulletins de classe';
$curPage   = 'notes'; // keep notes nav item active

// ── FILTER PARAMS ─────────────────────────────────────────────────────────
$allAnnees   = $pdo->query("SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);
$selAnnee    = trim((string)($_GET['annee']      ?? ''));
if ($selAnnee === '' && !empty($allAnnees)) $selAnnee = $allAnnees[0];

$selFiliere  = (int)($_GET['id_filiere'] ?? 0);
$selNiveau   = trim((string)($_GET['niveau']     ?? ''));
$selClasse   = (int)($_GET['id_classe']  ?? 0);
$selModule   = (int)($_GET['id_module']  ?? 0);

$allFilieres = $pdo->query("SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere")->fetchAll();

$allNiveaux = [];
if ($selFiliere > 0 && $selAnnee !== '') {
    $st = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $st->execute([$selFiliere, $selAnnee]);
    $allNiveaux = $st->fetchAll(PDO::FETCH_COLUMN);
}

$allClasses = [];
if ($selFiliere > 0 && $selAnnee !== '' && $selNiveau !== '') {
    $st = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $st->execute([$selFiliere, $selAnnee, $selNiveau]);
    $allClasses = $st->fetchAll();
}

// ── DATA: modules of this filière ──────────────────────────────────────────
$modules = [];
if ($selFiliere > 0) {
    $st = $pdo->prepare("SELECT id_module, nom_module FROM modules WHERE id_filiere=? ORDER BY nom_module");
    $st->execute([$selFiliere]);
    $modules = $st->fetchAll();
}

// ── DATA: stagiaires + all their notes for this classe ────────────────────
$stagiaires  = [];
$notesByStag = []; // [id_stagiaire][id_module] = [nc,nt,np,moy]
$classeInfo  = null;

if ($selClasse > 0 && !empty($modules)) {
    // Class info
    $r = $pdo->prepare("SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $r->execute([$selClasse]);
    $classeInfo = $r->fetch();

    // All stagiaires in class
    $st = $pdo->prepare("SELECT id_stagiaire, nom, prenom, cin FROM stagiaires WHERE id_classe=? ORDER BY nom, prenom");
    $st->execute([$selClasse]);
    $stagiaires = $st->fetchAll();

    if (!empty($stagiaires)) {
        $sids = array_column($stagiaires, 'id_stagiaire');
        $mids = array_column($modules, 'id_module');

        $placeholdersSid = implode(',', array_fill(0, count($sids), '?'));
        $placeholdersMid = implode(',', array_fill(0, count($mids), '?'));

        $stEv = $pdo->prepare(
            "SELECT id_stagiaire, id_module, type, note
             FROM module_notes
             WHERE id_stagiaire IN ($placeholdersSid) AND id_module IN ($placeholdersMid)"
        );
        $stEv->execute(array_merge($sids, $mids));

        // Build raw[sid][mid][type] = note
        $raw = [];
        foreach ($stEv->fetchAll() as $ev) {
            $raw[(int)$ev['id_stagiaire']][(int)$ev['id_module']][$ev['type']] =
                $ev['note'] !== null ? (float)$ev['note'] : null;
        }

        foreach ($raw as $sid => $modData) {
            foreach ($modData as $mid => $typeData) {
                // Average all controle_N entries (mirrors updated view formula)
                $cVals = [];
                foreach ($typeData as $tp => $val) {
                    if (str_starts_with($tp, 'controle_') && $val !== null) {
                        $cVals[] = $val;
                    }
                }
                $nc = !empty($cVals) ? array_sum($cVals) / count($cVals) : null;
                $nt = $typeData['theorique'] ?? null;
                $np = $typeData['pratique']  ?? null;

                if ($nc !== null && $nt !== null && $np !== null) {
                    $moy = round($nc*0.4 + $nt*0.3 + $np*0.3, 2);
                } elseif ($nc !== null && ($nt !== null || $np !== null)) {
                    $moy = round($nc*0.4 + ($nt ?? 0.0)*0.3 + ($np ?? 0.0)*0.3, 2);
                } elseif ($nc !== null) {
                    $moy = round($nc, 2);
                } else {
                    $moy = null;
                }
                $notesByStag[$sid][$mid] = compact('nc','nt','np','moy');
            }
        }
    }

    // Compute moyenne générale per stagiaire & rank
    foreach ($stagiaires as &$s) {
        $sid  = (int)$s['id_stagiaire'];
        $sum  = 0; $cnt = 0;
        foreach ($modules as $m) {
            $mid = (int)$m['id_module'];
            $entry = $notesByStag[$sid][$mid] ?? null;
            if ($entry && $entry['moy'] !== null) {
                $sum += $entry['moy'];
                $cnt++;
            }
        }
        $s['moy_generale'] = $cnt > 0 ? round($sum / $cnt, 2) : null;
    }
    unset($s);

    // Sort by moyenne desc for ranking
    usort($stagiaires, function($a, $b) {
        if ($a['moy_generale'] === null && $b['moy_generale'] === null) return 0;
        if ($a['moy_generale'] === null) return 1;
        if ($b['moy_generale'] === null) return -1;
        return $b['moy_generale'] <=> $a['moy_generale'];
    });

    // Assign ranks
    $rank = 1;
    foreach ($stagiaires as &$s) {
        $s['rang'] = $s['moy_generale'] !== null ? $rank++ : null;
    }
    unset($s);
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.bul-shell { max-width: 1200px; margin: 0 auto; padding-bottom: 3rem; }

/* Filter card — same style as notes.php */
.notes-filter-card {
    background: #16161e;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    padding: 1.5rem;
    margin-bottom: 1.75rem;
}
.notes-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
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

/* Header bar */
.bul-header-bar {
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

/* Bulletin table */
.bul-table-wrap { overflow-x: auto; }
.bul-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.bul-table thead tr:first-child th {
    background: rgba(168,85,247,0.1);
    color: #d8b4fe;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: .1em;
    font-weight: 800;
    padding: 0.75rem 0.6rem;
    border-bottom: 1px solid rgba(168,85,247,0.2);
    text-align: center;
    white-space: nowrap;
}
.bul-table thead tr:first-child th:first-child,
.bul-table thead tr:first-child th:nth-child(2) { text-align: left; }

.bul-table thead tr.sub-header th {
    background: rgba(255,255,255,0.02);
    color: #52525b;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 0.4rem 0.6rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    text-align: center;
    white-space: nowrap;
}
.bul-table thead tr.sub-header th:first-child,
.bul-table thead tr.sub-header th:nth-child(2) { text-align: left; }

.bul-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background .15s;
}
.bul-table tbody tr:hover { background: rgba(168,85,247,0.04); }
.bul-table tbody td { padding: 0.65rem 0.6rem; text-align: center; vertical-align: middle; }
.bul-table tbody td:first-child { text-align: center; }
.bul-table tbody td:nth-child(2) { text-align: left; }

.stag-name { font-weight: 700; color: #fff; font-size: 0.85rem; }
.stag-cin  { color: #71717a; font-size: 0.72rem; }

.rang-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px; height: 28px;
    border-radius: 50%;
    font-weight: 800;
    font-size: 0.8rem;
}
.rang-1 { background: rgba(250,204,21,0.2); color: #fde047; border: 1px solid rgba(250,204,21,0.3); }
.rang-2 { background: rgba(161,161,170,0.15); color: #d4d4d8; border: 1px solid rgba(161,161,170,0.25); }
.rang-3 { background: rgba(180,83,9,0.15); color: #fdba74; border: 1px solid rgba(180,83,9,0.3); }
.rang-other { background: rgba(255,255,255,0.04); color: #71717a; border: 1px solid rgba(255,255,255,0.08); }

.note-val { color: #e4e4e7; }
.note-null { color: #3f3f46; }
.moy-mod { font-weight: 700; }
.moy-ok  { color: #34d399; }
.moy-fail { color: #f87171; }

.moy-gen-cell { font-size: 1rem; font-weight: 800; }
.statut-admis  { color: #34d399; font-size: 0.72rem; font-weight: 700; }
.statut-ajourne { color: #f87171; font-size: 0.72rem; font-weight: 700; }

.col-moy-gen {
    background: rgba(168,85,247,0.06);
    border-left: 1px solid rgba(168,85,247,0.15);
    border-right: 1px solid rgba(168,85,247,0.15);
}
.col-sep { border-left: 1px solid rgba(255,255,255,0.05); }

.notes-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #52525b;
    font-size: 0.95rem;
}
.notes-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; color: #3f3f46; }

/* Stats bar */
.bul-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.bul-stat-card {
    flex: 1;
    min-width: 130px;
    background: #16161e;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    text-align: center;
}
.bul-stat-val  { font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1; }
.bul-stat-label { font-size: 0.72rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; margin-top: 0.3rem; }
</style>

<div class="bul-shell">

    <!-- top nav link back to notes -->
    <div style="margin-bottom:1rem;">
        <?php
        $backQs = http_build_query(['annee'=>$selAnnee,'id_filiere'=>$selFiliere,'niveau'=>$selNiveau,'id_classe'=>$selClasse,'id_module'=>$selModule]);
        ?>
        <a href="notes.php?<?= $backQs ?>" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Retour à la saisie des notes
        </a>
    </div>

    <!-- Filter card -->
    <div class="notes-filter-card">
        <form method="get" action="bulletins.php" id="bul-filter-form">
        <div class="notes-filter-grid">

            <div class="notes-filter-group">
                <label>Année scolaire</label>
                <select name="annee" id="bf-annee">
                    <option value="">— Choisir —</option>
                    <?php foreach ($allAnnees as $ay): ?>
                    <option value="<?= h($ay) ?>" <?= $ay === $selAnnee ? 'selected' : '' ?>><?= h($ay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Filière</label>
                <select name="id_filiere" id="bf-filiere" <?= $selAnnee === '' ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allFilieres as $f): ?>
                    <option value="<?= (int)$f['id_filiere'] ?>" <?= (int)$f['id_filiere'] === $selFiliere ? 'selected' : '' ?>><?= h((string)$f['nom_filiere']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Niveau</label>
                <select name="niveau" id="bf-niveau" <?= ($selFiliere === 0 || $selAnnee === '') ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allNiveaux as $nv): ?>
                    <option value="<?= h($nv) ?>" <?= $nv === $selNiveau ? 'selected' : '' ?>><?= h($nv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>Classe</label>
                <select name="id_classe" id="bf-classe" <?= ($selNiveau === '' || $selFiliere === 0) ? 'disabled' : '' ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($allClasses as $cl): ?>
                    <option value="<?= (int)$cl['id_classe'] ?>" <?= (int)$cl['id_classe'] === $selClasse ? 'selected' : '' ?>><?= h((string)$cl['nom_classe']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="notes-filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-afficher" <?= $selClasse === 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-chart-bar"></i> Afficher
                </button>
            </div>

        </div>
        </form>
    </div>

    <?php if ($selClasse > 0 && !empty($stagiaires) && !empty($modules)): ?>

    <?php
    // Stats
    $admisCount   = 0; $totalWithMoy = 0; $moySum = 0;
    foreach ($stagiaires as $s) {
        if ($s['moy_generale'] !== null) {
            $totalWithMoy++;
            $moySum += $s['moy_generale'];
            if ($s['moy_generale'] >= 10) $admisCount++;
        }
    }
    $moyClasse = $totalWithMoy > 0 ? round($moySum / $totalWithMoy, 2) : null;
    $tauxReussite = $totalWithMoy > 0 ? round($admisCount / $totalWithMoy * 100) : 0;
    ?>

    <!-- Context badges -->
    <div class="bul-header-bar">
        <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;">
            <?php if ($classeInfo): ?>
            <div class="notes-context-badge">
                <i class="fa-solid fa-users"></i>
                <?= h((string)$classeInfo['nom_classe']) ?>
                <span>·</span><?= h((string)$classeInfo['nom_filiere']) ?>
                <span>·</span><?= h((string)$classeInfo['annee_scolaire']) ?>
            </div>
            <?php endif; ?>
            <div class="notes-context-badge" style="background:rgba(250,204,21,0.08);border-color:rgba(250,204,21,0.2);color:#fde047;">
                <i class="fa-solid fa-user-graduate"></i> <?= count($stagiaires) ?> stagiaire<?= count($stagiaires) !== 1 ? 's' : '' ?>
            </div>
            <div class="notes-context-badge" style="background:rgba(56,189,248,0.08);border-color:rgba(56,189,248,0.2);color:#7dd3fc;">
                <i class="fa-solid fa-book-open"></i> <?= count($modules) ?> module<?= count($modules) !== 1 ? 's' : '' ?>
            </div>
        </div>
    </div>

    <!-- Stats bar -->
    <div class="bul-stats">
        <div class="bul-stat-card">
            <div class="bul-stat-val"><?= count($stagiaires) ?></div>
            <div class="bul-stat-label">Stagiaires</div>
        </div>
        <div class="bul-stat-card">
            <div class="bul-stat-val" style="color:#34d399;"><?= $admisCount ?></div>
            <div class="bul-stat-label">Admis</div>
        </div>
        <div class="bul-stat-card">
            <div class="bul-stat-val" style="color:#f87171;"><?= $totalWithMoy - $admisCount ?></div>
            <div class="bul-stat-label">Ajournés</div>
        </div>
        <div class="bul-stat-card">
            <div class="bul-stat-val" style="color:#a855f7;"><?= $tauxReussite ?>%</div>
            <div class="bul-stat-label">Taux de réussite</div>
        </div>
        <?php if ($moyClasse !== null): ?>
        <div class="bul-stat-card">
            <div class="bul-stat-val" style="color:#fde047;"><?= number_format($moyClasse, 2, ',', '') ?></div>
            <div class="bul-stat-label">Moyenne classe</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bulletin table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <div class="bul-table-wrap">
            <table class="bul-table">
                <thead>
                    <tr>
                        <th style="width:40px;">Rang</th>
                        <th style="min-width:160px;">Stagiaire</th>
                        <?php foreach ($modules as $m): ?>
                        <th colspan="4" class="col-sep"><?= h((string)$m['nom_module']) ?></th>
                        <?php endforeach; ?>
                        <th colspan="2" class="col-moy-gen">Moyenne générale</th>
                    </tr>
                    <tr class="sub-header">
                        <th></th>
                        <th></th>
                        <?php foreach ($modules as $m): ?>
                        <th class="col-sep">Ctrl</th>
                        <th>Théo</th>
                        <th>Prat</th>
                        <th>Moy</th>
                        <?php endforeach; ?>
                        <th class="col-moy-gen">/20</th>
                        <th class="col-moy-gen">Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stagiaires as $s):
                    $sid = (int)$s['id_stagiaire'];
                    $rang = $s['rang'];
                    $mg   = $s['moy_generale'];
                    $rangClass = match($rang) { 1 => 'rang-1', 2 => 'rang-2', 3 => 'rang-3', default => 'rang-other' };
                ?>
                <tr>
                    <td>
                        <?php if ($rang !== null): ?>
                        <span class="rang-badge <?= $rangClass ?>"><?= $rang ?></span>
                        <?php else: ?>
                        <span class="note-null">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="stag-name"><?= h(trim($s['nom'].' '.$s['prenom'])) ?></div>
                        <?php if (!empty($s['cin'])): ?>
                        <div class="stag-cin"><?= h((string)$s['cin']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($modules as $m):
                        $mid   = (int)$m['id_module'];
                        $entry = $notesByStag[$sid][$mid] ?? null;
                        $nc    = $entry['nc']  ?? null;
                        $nt    = $entry['nt']  ?? null;
                        $np    = $entry['np']  ?? null;
                        $moy   = $entry['moy'] ?? null;
                        $moyClass = $moy !== null ? ($moy >= 10 ? 'moy-ok' : 'moy-fail') : 'note-null';
                    ?>
                    <td class="col-sep"><?= $nc  !== null ? '<span class="note-val">'.number_format($nc,2).'</span>'  : '<span class="note-null">—</span>' ?></td>
                    <td><?= $nt  !== null ? '<span class="note-val">'.number_format($nt,2).'</span>'  : '<span class="note-null">—</span>' ?></td>
                    <td><?= $np  !== null ? '<span class="note-val">'.number_format($np,2).'</span>'  : '<span class="note-null">—</span>' ?></td>
                    <td><span class="moy-mod <?= $moyClass ?>"><?= $moy !== null ? number_format($moy,2,',','') : '<span class="note-null">—</span>' ?></span></td>
                    <?php endforeach; ?>
                    <td class="col-moy-gen moy-gen-cell <?= $mg !== null ? ($mg >= 10 ? 'moy-ok' : 'moy-fail') : 'note-null' ?>">
                        <?= $mg !== null ? number_format($mg, 2, ',', '') : '—' ?>
                    </td>
                    <td class="col-moy-gen">
                        <?php if ($mg !== null): ?>
                        <span class="<?= $mg >= 10 ? 'statut-admis' : 'statut-ajourne' ?>">
                            <?= $mg >= 10 ? 'Admis(e)' : 'Ajourné(e)' ?>
                        </span>
                        <?php else: ?>
                        <span class="note-null">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($selClasse > 0 && empty($stagiaires)): ?>
    <div class="notes-empty">
        <i class="fa-solid fa-user-slash"></i>
        Aucun stagiaire dans cette classe.
    </div>
    <?php else: ?>
    <div class="notes-empty">
        <i class="fa-solid fa-chart-bar"></i>
        Sélectionnez une classe pour afficher les bulletins.
    </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const form    = document.getElementById('bul-filter-form');
    const annee   = document.getElementById('bf-annee');
    const filiere = document.getElementById('bf-filiere');
    const niveau  = document.getElementById('bf-niveau');
    const classe  = document.getElementById('bf-classe');
    const btn     = form.querySelector('.btn-afficher');

    function cascade(changed) {
        const order = [annee, filiere, niveau, classe];
        const idx   = order.indexOf(changed);
        for (let i = idx + 1; i < order.length; i++) {
            order[i].value    = '';
            order[i].disabled = true;
        }
        form.submit();
    }

    if (annee.value)   filiere.disabled = false;
    if (filiere.value) niveau.disabled  = false;
    if (niveau.value)  classe.disabled  = false;

    function syncBtn() { btn.disabled = !classe.value; }
    syncBtn();

    annee.addEventListener('change',   () => cascade(annee));
    filiere.addEventListener('change', () => cascade(filiere));
    niveau.addEventListener('change',  () => cascade(niveau));
    classe.addEventListener('change',  () => syncBtn());
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
