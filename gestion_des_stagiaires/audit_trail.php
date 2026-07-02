<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
gds_require_admin_session();

$pageTitle = 'Journal des Modifications';
$curPage   = 'audit_trail';

// ── POST actions (delete / edit note) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $actId = (int)($_POST['id'] ?? 0);
    if ($actId > 0 && gds_is_directeur()) {
        try {
            if ($_POST['action'] === 'delete') {
                $pdo->prepare("DELETE FROM stagiaire_historique WHERE id = ?")->execute([$actId]);
            } elseif ($_POST['action'] === 'edit_note') {
                $newNote = trim((string)($_POST['note'] ?? ''));
                $pdo->prepare("UPDATE stagiaire_historique SET note = ? WHERE id = ?")
                    ->execute([$newNote !== '' ? $newNote : null, $actId]);
            }
        } catch (\PDOException $e) {}
    }
    // Redirect back preserving filters
    $rp = [];
    foreach (['champ','q','from','to','p'] as $k) {
        if (!empty($_POST[$k])) $rp[$k] = $_POST[$k];
    }
    header('Location: audit_trail.php' . ($rp ? '?'.http_build_query($rp) : ''));
    exit;
}

// ── Labels ────────────────────────────────────────────────────────────────
$champLabels = [
    'classe'         => ['Classe',         'fa-chalkboard-user', '#a855f7'],
    'filiere'        => ['Filière',        'fa-sitemap',          '#60a5fa'],
    'annee_scolaire' => ['Année scolaire', 'fa-calendar-days',    '#fbbf24'],
];

// ── Check table exists ────────────────────────────────────────────────────
$tableExists = false;
try {
    $pdo->query("SELECT 1 FROM stagiaire_historique LIMIT 1");
    $tableExists = true;
} catch (\PDOException $e) {}

// ── Filters ───────────────────────────────────────────────────────────────
$fSearch = trim((string)($_GET['q']    ?? ''));
$fChamp  = trim((string)($_GET['champ']?? ''));
$fFrom   = trim((string)($_GET['from'] ?? ''));
$fTo     = trim((string)($_GET['to']   ?? ''));
$page    = max(1, (int)($_GET['p']     ?? 1));
$perPage = 50;

$totalAll = 0; $totalFiltered = 0; $totalPages = 1; $docs = []; $typeCounts = [];

if ($tableExists) {
    $totalAll = (int)$pdo->query("SELECT COUNT(*) FROM stagiaire_historique")->fetchColumn();

    $where = ['1=1']; $params = [];
    if ($fChamp !== '')  { $where[] = 'h.champ = ?';                               $params[] = $fChamp; }
    if ($fSearch !== '') { $where[] = "(s.nom LIKE ? OR s.prenom LIKE ? OR s.num_inscri LIKE ?)";
                           $t = "%$fSearch%"; $params[] = $t; $params[] = $t; $params[] = $t; }
    if ($fFrom !== '')   { $where[] = 'DATE(h.change_le) >= ?';                    $params[] = $fFrom; }
    if ($fTo !== '')     { $where[] = 'DATE(h.change_le) <= ?';                    $params[] = $fTo; }
    $whereSQL = implode(' AND ', $where);

    $countSt = $pdo->prepare("SELECT COUNT(*) FROM stagiaire_historique h JOIN stagiaires s ON s.id_stagiaire = h.id_stagiaire WHERE $whereSQL");
    $countSt->execute($params);
    $totalFiltered = (int)$countSt->fetchColumn();
    $totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
    $page          = min($page, $totalPages);
    $offset        = ($page - 1) * $perPage;

    $listSt = $pdo->prepare(
        "SELECT h.id, h.champ, h.ancien, h.nouveau, h.note, h.change_le,
                s.id_stagiaire, s.nom, s.prenom, s.num_inscri
         FROM stagiaire_historique h
         JOIN stagiaires s ON s.id_stagiaire = h.id_stagiaire
         WHERE $whereSQL
         ORDER BY h.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $listSt->execute($params);
    $docs = $listSt->fetchAll();

    $typeCounts = $pdo->query("SELECT champ, COUNT(*) as c FROM stagiaire_historique GROUP BY champ ORDER BY c DESC")->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.audit-shell  { max-width: 1100px; margin: 0 auto; padding-bottom: 3rem; }
.audit-stats  { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
.audit-stat   { background: #16161e; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1.1rem 1.3rem; }
.audit-stat__val { font-size: 1.9rem; font-weight: 800; line-height: 1; }
.audit-stat__lbl { font-size: 0.7rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; margin-top: 0.25rem; }

.audit-filter { background: #16161e; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; padding: 1.1rem 1.4rem; margin-bottom: 1.5rem; }
.audit-filter__grid { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
.audit-filter__field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 150px; flex: 1; }
.audit-filter__field label { font-size: 0.68rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.audit-filter__field select,
.audit-filter__field input  { background: #0d0d14; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; padding: 0.48rem 0.7rem; font-size: 0.85rem; width: 100%; }
.audit-filter__field input[type="date"] { color-scheme: dark; }
.abtn { background: rgba(168,85,247,0.2); color: #a855f7; border: 1px solid rgba(168,85,247,0.35); border-radius: 8px; padding: 0.5rem 1.1rem; font-size: 0.85rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .2s; }
.abtn:hover { background: rgba(168,85,247,0.35); }
.abtn.ghost { background: transparent; color: #71717a; border-color: rgba(255,255,255,0.1); }
.abtn.ghost:hover { background: rgba(255,255,255,0.04); color: #e4e4e7; }

.audit-tbl { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
.audit-tbl thead th { background: rgba(255,255,255,0.025); color: #71717a; font-size: 0.67rem; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: left; white-space: nowrap; }
.audit-tbl tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background .15s; }
.audit-tbl tbody tr:hover td { background: rgba(168,85,247,0.07); }
.audit-tbl tbody td { padding: 0.65rem 1rem; vertical-align: middle; }

.champ-badge { display:inline-flex; align-items:center; gap:0.35rem; padding:0.22rem 0.65rem; border-radius:20px; font-size:0.75rem; font-weight:700; }
.val-pill    { display:inline-block; padding:0.18rem 0.55rem; border-radius:6px; font-size:0.82rem; font-weight:600; }
.val-old     { background:rgba(248,113,113,0.1); color:#f87171; text-decoration:line-through; opacity:0.85; }
.val-new     { background:rgba(74,222,128,0.1);  color:#4ade80; }
.stag-link   { color:#c4b5fd; font-weight:600; text-decoration:none; font-size:0.86rem; }
.stag-link:hover { color:#a855f7; text-decoration:underline; }

.audit-pg    { display:flex; align-items:center; justify-content:center; gap:0.4rem; margin-top:1.5rem; flex-wrap:wrap; }
.pgb         { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:7px; color:#a1a1aa; padding:0.38rem 0.7rem; font-size:0.82rem; text-decoration:none; transition:all .15s; }
.pgb:hover   { background:rgba(168,85,247,0.15); border-color:rgba(168,85,247,0.3); color:#c4b5fd; }
.pgb.active  { background:rgba(168,85,247,0.25); border-color:rgba(168,85,247,0.5); color:#e9d5ff; font-weight:700; }
.pgb.off     { opacity:.3; pointer-events:none; }

.no-table-banner { background:rgba(251,191,36,0.07); border:1px solid rgba(251,191,36,0.25); border-radius:12px; padding:1.5rem 1.75rem; margin-bottom:1.5rem; }
.no-table-banner h3 { color:#fbbf24; margin:0 0 0.75rem; font-size:1rem; }
.no-table-banner pre { background:#0d0d14; border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:1rem; color:#e4e4e7; font-size:0.82rem; overflow-x:auto; margin:0.75rem 0 0; line-height:1.6; }

.act-btn { display:inline-flex; align-items:center; gap:0.3rem; border:none; border-radius:6px; padding:0.28rem 0.55rem; font-size:0.75rem; font-weight:700; cursor:pointer; transition:background .15s; line-height:1; }
.act-btn.edit   { background:rgba(96,165,250,0.12); color:#60a5fa; }
.act-btn.edit:hover   { background:rgba(96,165,250,0.25); }
.act-btn.del    { background:rgba(248,113,113,0.1);  color:#f87171; }
.act-btn.del:hover    { background:rgba(248,113,113,0.22); }

/* Edit-note modal */
.anote-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:1000; align-items:center; justify-content:center; }
.anote-overlay.open { display:flex; }
.anote-box { background:#1a1a26; border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:1.6rem 1.8rem; width:100%; max-width:460px; box-shadow:0 20px 60px rgba(0,0,0,0.6); }
.anote-box h3 { font-size:1rem; font-weight:800; color:#e4e4e7; margin:0 0 1rem; }
.anote-box textarea { width:100%; background:#0d0d14; border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#e4e4e7; padding:0.6rem 0.75rem; font-size:0.87rem; resize:vertical; min-height:90px; box-sizing:border-box; }
.anote-box textarea:focus { outline:none; border-color:rgba(168,85,247,0.5); }
.anote-actions { display:flex; gap:0.6rem; justify-content:flex-end; margin-top:0.9rem; }

/* Delete confirm modal */
.adel-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:1000; align-items:center; justify-content:center; }
.adel-overlay.open { display:flex; }
.adel-box { background:#1a1a26; border:1px solid rgba(248,113,113,0.25); border-radius:14px; padding:1.6rem 1.8rem; width:100%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,0.6); text-align:center; }
.adel-box .adel-icon { font-size:2rem; color:#f87171; margin-bottom:0.75rem; }
.adel-box h3 { font-size:1rem; font-weight:800; color:#e4e4e7; margin:0 0 0.4rem; }
.adel-box p { font-size:0.85rem; color:#71717a; margin:0 0 1.2rem; }
.adel-box p strong { color:#c4b5fd; }
.adel-actions { display:flex; gap:0.6rem; justify-content:center; }
.abtn.danger { background:rgba(248,113,113,0.15); color:#f87171; border-color:rgba(248,113,113,0.35); }
.abtn.danger:hover { background:rgba(248,113,113,0.28); }
</style>

<div class="audit-shell">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.35rem; font-weight:800; color:#e4e4e7; margin:0 0 0.2rem 0;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#a855f7; margin-right:0.5rem;"></i>Journal des Modifications
            </h1>
            <p style="color:#71717a; font-size:0.83rem; margin:0;">Historique des changements structurels des dossiers stagiaires</p>
        </div>
        <a href="index.php" style="color:#a855f7; font-size:0.85rem; font-weight:600; text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Tableau de bord
        </a>
    </div>

    <?php if (!$tableExists): ?>
    <div class="no-table-banner">
        <h3><i class="fa-solid fa-triangle-exclamation"></i> Table manquante — à créer</h3>
        <p style="color:#a1a1aa; font-size:0.87rem; margin:0;">La table <code>stagiaire_historique</code> n'existe pas encore dans votre base de données. Exécutez le SQL ci-dessous dans phpMyAdmin, puis revenez ici.</p>
        <pre>CREATE TABLE IF NOT EXISTS stagiaire_historique (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  id_stagiaire INT NOT NULL,
  champ        VARCHAR(60)  NOT NULL,
  ancien       VARCHAR(255) DEFAULT NULL,
  nouveau      VARCHAR(255) DEFAULT NULL,
  note         VARCHAR(500) DEFAULT NULL,
  change_le    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_stagiaire) REFERENCES stagiaires(id_stagiaire) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
    </div>
    <?php else: ?>

    <!-- Stats -->
    <div class="audit-stats">
        <div class="audit-stat" style="border-top:3px solid #a855f7;">
            <div class="audit-stat__val" style="color:#a855f7;"><?= number_format($totalAll) ?></div>
            <div class="audit-stat__lbl">Modifications totales</div>
        </div>
        <?php foreach ($typeCounts as $tc):
            $cl = $champLabels[(string)$tc['champ']] ?? [ucfirst((string)$tc['champ']), 'fa-pen', '#71717a'];
        ?>
        <div class="audit-stat" style="border-top:3px solid <?= $cl[2] ?>;">
            <div class="audit-stat__val" style="color:<?= $cl[2] ?>;"><?= number_format((int)$tc['c']) ?></div>
            <div class="audit-stat__lbl"><?= h($cl[0]) ?></div>
        </div>
        <?php endforeach; ?>
        <div class="audit-stat" style="border-top:3px solid #60a5fa;">
            <div class="audit-stat__val" style="color:#60a5fa;"><?= number_format($totalFiltered) ?></div>
            <div class="audit-stat__lbl">Résultats filtrés</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="audit-filter">
        <form method="get" action="audit_trail.php">
        <div class="audit-filter__grid">
            <div class="audit-filter__field" style="max-width:200px;">
                <label>Champ modifié</label>
                <select name="champ">
                    <option value="">— Tous —</option>
                    <?php foreach ($typeCounts as $tc):
                        $tkey = (string)$tc['champ'];
                        $tl   = $champLabels[$tkey][0] ?? ucfirst(str_replace('_',' ',$tkey));
                    ?>
                    <option value="<?= h($tkey) ?>" <?= $fChamp === $tkey ? 'selected' : '' ?>><?= h($tl) ?> (<?= (int)$tc['c'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="audit-filter__field">
                <label>Rechercher stagiaire</label>
                <input type="search" name="q" value="<?= h($fSearch) ?>" placeholder="Nom, prénom, code...">
            </div>
            <div class="audit-filter__field" style="max-width:160px;">
                <label>Du</label>
                <input type="date" name="from" value="<?= h($fFrom) ?>">
            </div>
            <div class="audit-filter__field" style="max-width:160px;">
                <label>Au</label>
                <input type="date" name="to" value="<?= h($fTo) ?>">
            </div>
            <div style="display:flex; gap:0.5rem; padding-bottom:2px;">
                <button type="submit" class="abtn"><i class="fa-solid fa-filter"></i> Filtrer</button>
                <?php if ($fChamp !== '' || $fSearch !== '' || $fFrom !== '' || $fTo !== ''): ?>
                <a href="audit_trail.php" class="abtn ghost"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </div>
        </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card" style="padding:0; overflow:hidden;">
        <?php if (empty($docs)): ?>
        <div style="text-align:center; padding:3.5rem 1rem; color:#52525b;">
            <i class="fa-solid fa-clock-rotate-left" style="font-size:2rem; display:block; margin-bottom:0.75rem; color:#3f3f46;"></i>
            <?php if ($totalAll === 0): ?>
                <p style="margin:0;">Aucun changement enregistré. Modifiez la classe ou la filière d'un stagiaire pour commencer.</p>
            <?php else: ?>
                <p style="margin:0;">Aucun résultat pour ces critères.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="padding:0.85rem 1.2rem 0.65rem; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
            <span style="font-size:0.82rem; color:#71717a;">
                <?= number_format($totalFiltered) ?> modification<?= $totalFiltered !== 1 ? 's' : '' ?>
                <?php if ($totalFiltered > $perPage): ?> · <?= ($page-1)*$perPage+1 ?>–<?= min($page*$perPage, $totalFiltered) ?><?php endif; ?>
            </span>
        </div>
        <div style="overflow-x:auto;">
        <table class="audit-tbl">
            <thead>
                <tr>
                    <th style="width:12%;">Date</th>
                    <th style="width:15%;">Champ</th>
                    <th style="width:30%;">Modification</th>
                    <th style="width:25%;">Stagiaire</th>
                    <th>Note</th>
                    <th style="width:90px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($docs as $d):
                $champ = (string)$d['champ'];
                $cl    = $champLabels[$champ] ?? [ucfirst(str_replace('_',' ',$champ)), 'fa-pen', '#71717a'];
            ?>
            <tr>
                <td style="color:#71717a; font-size:0.78rem; white-space:nowrap;">
                    <?= date('d/m/Y', strtotime((string)$d['change_le'])) ?><br>
                    <span style="color:#3f3f46;"><?= date('H:i', strtotime((string)$d['change_le'])) ?></span>
                </td>
                <td>
                    <span class="champ-badge" style="background:<?= $cl[2] ?>18; color:<?= $cl[2] ?>;">
                        <i class="fa-solid <?= $cl[1] ?>" style="font-size:0.7rem;"></i>
                        <?= h($cl[0]) ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <span class="val-pill val-old"><?= h((string)($d['ancien'] ?? '—')) ?></span>
                        <i class="fa-solid fa-arrow-right" style="font-size:0.65rem; color:#52525b;"></i>
                        <span class="val-pill val-new"><?= h((string)($d['nouveau'] ?? '—')) ?></span>
                    </div>
                </td>
                <td>
                    <a href="stagiaires.php?id=<?= (int)$d['id_stagiaire'] ?>" class="stag-link" target="_blank">
                        <?= h(trim($d['prenom'].' '.$d['nom'])) ?>
                    </a>
                    <?php if (!empty($d['num_inscri'])): ?>
                    <div style="font-size:0.72rem; color:#52525b; margin-top:1px;"><?= h((string)$d['num_inscri']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="color:#71717a; font-size:0.8rem; font-style:italic;">
                    <?= !empty($d['note']) ? h((string)$d['note']) : '<span style="color:#3f3f46;">—</span>' ?>
                </td>
                <td style="text-align:center; white-space:nowrap;">
                    <?php if (gds_is_directeur()): ?>
                    <button type="button" class="act-btn edit"
                        onclick="openEdit(<?= (int)$d['id'] ?>, <?= h(json_encode((string)($d['note'] ?? ''))) ?>)"
                        title="Modifier la note">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="act-btn del"
                        onclick="openDel(<?= (int)$d['id'] ?>, <?= h(json_encode(trim($d['prenom'].' '.$d['nom']))) ?>)"
                        title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1):
        $qb = [];
        if ($fChamp)  $qb['champ'] = $fChamp;
        if ($fSearch) $qb['q']     = $fSearch;
        if ($fFrom)   $qb['from']  = $fFrom;
        if ($fTo)     $qb['to']    = $fTo;
        function atUrl(array $b, int $p): string { $b['p']=$p; return 'audit_trail.php?'.http_build_query($b); }
    ?>
    <div class="audit-pg">
        <a href="<?= atUrl($qb,1) ?>"        class="pgb <?= $page<=1?'off':'' ?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?= atUrl($qb,$page-1) ?>"  class="pgb <?= $page<=1?'off':'' ?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php $s=max(1,$page-2); $e=min($totalPages,$page+2);
              if($s>1): ?><span class="pgb off">…</span><?php endif;
              for($pg=$s;$pg<=$e;$pg++): ?>
        <a href="<?= atUrl($qb,$pg) ?>" class="pgb <?= $pg===$page?'active':'' ?>"><?= $pg ?></a>
        <?php endfor;
              if($e<$totalPages): ?><span class="pgb off">…</span><?php endif; ?>
        <a href="<?= atUrl($qb,$page+1) ?>"       class="pgb <?= $page>=$totalPages?'off':'' ?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?= atUrl($qb,$totalPages) ?>"   class="pgb <?= $page>=$totalPages?'off':'' ?>"><i class="fa-solid fa-angles-right"></i></a>
    </div>
    <?php endif; ?>

    <?php endif; /* tableExists */ ?>

</div>
<?php if (gds_is_directeur()): ?>
<!-- Delete confirm modal -->
<div class="adel-overlay" id="adelOverlay">
    <div class="adel-box">
        <div class="adel-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3>Supprimer cette entrée ?</h3>
        <p>Modification de <strong id="adelName"></strong><br>Cette action est irréversible.</p>
        <div class="adel-actions">
            <button type="button" class="abtn ghost" onclick="closeDel()">Annuler</button>
            <form method="post" action="audit_trail.php" id="adelForm" style="display:inline;">
                <?= csrf_hidden() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="adelId">
                <?php foreach (['champ'=>$fChamp,'q'=>$fSearch,'from'=>$fFrom,'to'=>$fTo,'p'=>$page] as $fk=>$fv): if($fv!=='' && $fv!==1): ?>
                <input type="hidden" name="<?= h($fk) ?>" value="<?= h((string)$fv) ?>">
                <?php endif; endforeach; ?>
                <button type="submit" class="abtn danger"><i class="fa-solid fa-trash"></i> Supprimer</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit-note modal -->
<div class="anote-overlay" id="anoteOverlay">
    <div class="anote-box">
        <h3><i class="fa-solid fa-pen" style="color:#60a5fa; margin-right:0.4rem;"></i>Modifier la note</h3>
        <form method="post" action="audit_trail.php" id="anoteForm">
            <?= csrf_hidden() ?>
            <input type="hidden" name="action" value="edit_note">
            <input type="hidden" name="id" id="anoteId">
            <?php foreach (['champ'=>$fChamp,'q'=>$fSearch,'from'=>$fFrom,'to'=>$fTo,'p'=>$page] as $fk=>$fv): if($fv!=='' && $fv!==1): ?>
            <input type="hidden" name="<?= h($fk) ?>" value="<?= h((string)$fv) ?>">
            <?php endif; endforeach; ?>
            <textarea name="note" id="anoteTxt" placeholder="Raison, contexte... (optionnel)"></textarea>
            <div class="anote-actions">
                <button type="button" class="abtn ghost" onclick="closeEdit()">Annuler</button>
                <button type="submit" class="abtn"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
function openDel(id, name) {
    document.getElementById('adelId').value = id;
    document.getElementById('adelName').textContent = name || 'ce stagiaire';
    document.getElementById('adelOverlay').classList.add('open');
}
function closeDel() {
    document.getElementById('adelOverlay').classList.remove('open');
}
document.getElementById('adelOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeDel();
});
function openEdit(id, note) {
    document.getElementById('anoteId').value = id;
    document.getElementById('anoteTxt').value = note || '';
    document.getElementById('anoteOverlay').classList.add('open');
    setTimeout(function(){ document.getElementById('anoteTxt').focus(); }, 80);
}
function closeEdit() {
    document.getElementById('anoteOverlay').classList.remove('open');
}
document.getElementById('anoteOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});
</script>
<?php endif; /* gds_is_directeur — modals + JS */ ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
