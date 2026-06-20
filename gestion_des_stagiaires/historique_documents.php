<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
gds_require_admin_session();

$pageTitle = 'Historique des Documents';
$curPage   = 'historique_docs';

// ── Labels & icons ────────────────────────────────────────────────────────────
$typeLabels = [
    'certificat_scolarite' => 'Certificat de Scolarité',
    'billet_excuse'        => "Billet d'Excuse",
    'etat_mensualites'     => 'État des Mensualités',
    'fiche_inscription'    => "Fiche d'Inscription",
    'recu_paiement'        => 'Reçu de Paiement',
    'releve_notes'         => 'Relevé de Notes',
    'bulletin'             => 'Bulletin',
    'attestation_reussite' => 'Attestation de Réussite',
    'convention_stage'     => 'Convention de Stage',
    'fiche_preinscription' => 'Fiche de Pré-inscription',
    'liste_stagiaires'     => 'Liste des Stagiaires',
    'etat_paiement'        => 'État de Paiement',
    'rapport_individuel'   => 'Rapport Individuel',
    'autre'                => 'Autre',
];
$typeIcons = [
    'certificat_scolarite' => ['fa-graduation-cap',      'rgba(168,85,247,0.15)',  '#a855f7'],
    'billet_excuse'        => ['fa-file-circle-exclamation','rgba(248,113,113,0.15)','#f87171'],
    'etat_mensualites'     => ['fa-file-invoice-dollar', 'rgba(16,185,129,0.15)',  '#10b981'],
    'fiche_inscription'    => ['fa-user-plus',           'rgba(96,165,250,0.15)',  '#60a5fa'],
    'recu_paiement'        => ['fa-receipt',             'rgba(52,211,153,0.15)',  '#34d399'],
    'releve_notes'         => ['fa-table-list',          'rgba(251,191,36,0.15)',  '#fbbf24'],
    'bulletin'             => ['fa-chart-bar',           'rgba(56,189,248,0.15)',  '#38bdf8'],
    'attestation_reussite' => ['fa-award',               'rgba(74,222,128,0.15)',  '#4ade80'],
    'convention_stage'     => ['fa-briefcase',           'rgba(192,132,252,0.15)', '#c084fc'],
    'fiche_preinscription' => ['fa-clipboard-list',      'rgba(251,146,60,0.15)',  '#fb923c'],
    'liste_stagiaires'     => ['fa-users',               'rgba(129,140,248,0.15)', '#818cf8'],
    'etat_paiement'        => ['fa-file-invoice',        'rgba(244,114,182,0.15)', '#f472b6'],
    'rapport_individuel'   => ['fa-file-lines',          'rgba(251,146,60,0.15)',  '#fb923c'],
    'autre'                => ['fa-file',                'rgba(113,113,122,0.15)', '#71717a'],
];

// ── Filter params ─────────────────────────────────────────────────────────────
$fType    = trim((string)($_GET['type']   ?? ''));
$fSearch  = trim((string)($_GET['q']      ?? ''));
$fFrom    = trim((string)($_GET['from']   ?? ''));
$fTo      = trim((string)($_GET['to']     ?? ''));
$page     = max(1, (int)($_GET['p']       ?? 1));
$perPage  = 40;
$offset   = ($page - 1) * $perPage;

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($fType !== '') {
    $where[]  = 'd.type_document = ?';
    $params[] = $fType;
}
if ($fSearch !== '') {
    $where[]  = "(s.nom LIKE ? OR s.prenom LIKE ? OR s.num_inscri LIKE ?)";
    $term     = "%$fSearch%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}
if ($fFrom !== '') {
    $where[]  = 'DATE(d.genere_le) >= ?';
    $params[] = $fFrom;
}
if ($fTo !== '') {
    $where[]  = 'DATE(d.genere_le) <= ?';
    $params[] = $fTo;
}
$whereSQL = implode(' AND ', $where);

// ── Stats (unfiltered) ────────────────────────────────────────────────────────
$totalAll   = (int)$pdo->query("SELECT COUNT(*) FROM documents_generes")->fetchColumn();
$todayCount = (int)$pdo->query("SELECT COUNT(*) FROM documents_generes WHERE DATE(genere_le) = CURDATE()")->fetchColumn();
$topTypeRow = $pdo->query("SELECT type_document, COUNT(*) as c FROM documents_generes GROUP BY type_document ORDER BY c DESC LIMIT 1")->fetch();
$topType    = $topTypeRow ? (string)$topTypeRow['type_document'] : '';
$topTypeCount = $topTypeRow ? (int)$topTypeRow['c'] : 0;

// ── Paginated results ─────────────────────────────────────────────────────────
$countSt = $pdo->prepare("SELECT COUNT(*) FROM documents_generes d JOIN stagiaires s ON s.id_stagiaire = d.id_stagiaire WHERE $whereSQL");
$countSt->execute($params);
$totalFiltered = (int)$countSt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$listSt = $pdo->prepare(
    "SELECT d.id_gen, d.type_document, d.reference, d.genere_le,
            s.id_stagiaire, s.nom, s.prenom, s.num_inscri
     FROM documents_generes d
     JOIN stagiaires s ON s.id_stagiaire = d.id_stagiaire
     WHERE $whereSQL
     ORDER BY d.id_gen DESC
     LIMIT $perPage OFFSET $offset"
);
$listSt->execute($params);
$docs = $listSt->fetchAll();

// ── Type breakdown for filter bar ─────────────────────────────────────────────
$typeCounts = $pdo->query("SELECT type_document, COUNT(*) as c FROM documents_generes GROUP BY type_document ORDER BY c DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<style>
.hist-shell   { max-width: 1200px; margin: 0 auto; padding-bottom: 3rem; }
.hist-stats   { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
.hist-stat    { background: #16161e; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1.2rem 1.4rem; display: flex; flex-direction: column; gap: 0.3rem; }
.hist-stat__val  { font-size: 2rem; font-weight: 800; line-height: 1; }
.hist-stat__lbl  { font-size: 0.72rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.hist-stat__sub  { font-size: 0.78rem; color: #a1a1aa; margin-top: 0.1rem; }

.hist-filter  { background: #16161e; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; padding: 1.2rem 1.5rem; margin-bottom: 1.5rem; }
.hist-filter__grid { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; }
.hist-filter__field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 160px; flex: 1; }
.hist-filter__field label { font-size: 0.7rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.hist-filter__field select,
.hist-filter__field input  { background: #0d0d14; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; padding: 0.5rem 0.75rem; font-size: 0.86rem; width: 100%; }
.hist-filter__field input[type="date"] { color-scheme: dark; }
.hist-btn { background: rgba(168,85,247,0.2); color: #a855f7; border: 1px solid rgba(168,85,247,0.35); border-radius: 8px; padding: 0.55rem 1.2rem; font-size: 0.87rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .2s; }
.hist-btn:hover { background: rgba(168,85,247,0.35); }
.hist-btn.ghost { background: transparent; color: #71717a; border-color: rgba(255,255,255,0.1); }
.hist-btn.ghost:hover { background: rgba(255,255,255,0.04); color: #e4e4e7; }

.hist-table-wrap { overflow-x: auto; }
.hist-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.hist-table thead th {
    background: rgba(255,255,255,0.025);
    color: #71717a; font-size: 0.68rem; text-transform: uppercase;
    letter-spacing: .1em; font-weight: 800;
    padding: 0.85rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: left; white-space: nowrap;
}
.hist-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background .15s; }
.hist-table tbody tr:hover td { background: rgba(168,85,247,0.07); }
.hist-table tbody td { padding: 0.7rem 1rem; vertical-align: middle; }

.doc-type-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.28rem 0.7rem; border-radius: 20px; font-size: 0.77rem; font-weight: 600; white-space: nowrap;
}
.stag-link { color: #c4b5fd; font-weight: 600; text-decoration: none; font-size: 0.87rem; }
.stag-link:hover { color: #a855f7; text-decoration: underline; }
.num-inscri { font-size: 0.73rem; color: #71717a; margin-top: 2px; }

.hist-pagination { display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-top: 1.5rem; flex-wrap: wrap; }
.pg-btn { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 7px; color: #a1a1aa; padding: 0.4rem 0.75rem; font-size: 0.83rem; text-decoration: none; transition: all .15s; }
.pg-btn:hover { background: rgba(168,85,247,0.15); border-color: rgba(168,85,247,0.3); color: #c4b5fd; }
.pg-btn.active { background: rgba(168,85,247,0.25); border-color: rgba(168,85,247,0.5); color: #e9d5ff; font-weight: 700; }
.pg-btn.disabled { opacity: 0.3; pointer-events: none; }

.hist-empty { text-align: center; padding: 3.5rem 1rem; color: #52525b; }
.hist-empty i { font-size: 2.2rem; display: block; margin-bottom: 0.75rem; color: #3f3f46; }
.hist-count-badge { display: inline-block; background: rgba(168,85,247,0.12); color: #c4b5fd; border-radius: 20px; padding: 0.15rem 0.65rem; font-size: 0.75rem; font-weight: 700; margin-left: 0.5rem; }
</style>

<div class="hist-shell">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.35rem; font-weight:800; color:#e4e4e7; margin:0 0 0.2rem 0;">
                <i class="fa-solid fa-scroll" style="color:#a855f7; margin-right:0.5rem;"></i>Historique des Documents
            </h1>
            <p style="color:#71717a; font-size:0.83rem; margin:0;">Tous les documents générés depuis l'application</p>
        </div>
        <a href="index.php" style="color:#a855f7; font-size:0.85rem; font-weight:600; text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <!-- Stats -->
    <div class="hist-stats">
        <div class="hist-stat" style="border-top: 3px solid #a855f7;">
            <div class="hist-stat__val" style="color:#a855f7;"><?= number_format($totalAll) ?></div>
            <div class="hist-stat__lbl">Documents générés</div>
            <div class="hist-stat__sub">total historique</div>
        </div>
        <div class="hist-stat" style="border-top: 3px solid #fbbf24;">
            <div class="hist-stat__val" style="color:#fbbf24;"><?= number_format($todayCount) ?></div>
            <div class="hist-stat__lbl">Aujourd'hui</div>
            <div class="hist-stat__sub"><?= date('d/m/Y') ?></div>
        </div>
        <div class="hist-stat" style="border-top: 3px solid #34d399;">
            <div class="hist-stat__val" style="color:#34d399;"><?= number_format($topTypeCount) ?></div>
            <div class="hist-stat__lbl">Type le plus fréquent</div>
            <div class="hist-stat__sub"><?= $topType !== '' ? h($typeLabels[$topType] ?? str_replace('_', ' ', $topType)) : '—' ?></div>
        </div>
        <div class="hist-stat" style="border-top: 3px solid #60a5fa;">
            <div class="hist-stat__val" style="color:#60a5fa;"><?= number_format($totalFiltered) ?></div>
            <div class="hist-stat__lbl">Résultats filtrés</div>
            <div class="hist-stat__sub">page <?= $page ?> / <?= $totalPages ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="hist-filter">
        <form method="get" action="historique_documents.php" id="hist-filter-form">
        <div class="hist-filter__grid">
            <div class="hist-filter__field" style="max-width:220px;">
                <label>Type de document</label>
                <select name="type">
                    <option value="">— Tous les types —</option>
                    <?php foreach ($typeCounts as $tc):
                        $tkey   = (string)$tc['type_document'];
                        $tlabel = $typeLabels[$tkey] ?? str_replace('_', ' ', $tkey);
                    ?>
                    <option value="<?= h($tkey) ?>" <?= $fType === $tkey ? 'selected' : '' ?>>
                        <?= h($tlabel) ?> (<?= (int)$tc['c'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hist-filter__field">
                <label>Rechercher stagiaire</label>
                <input type="search" name="q" value="<?= h($fSearch) ?>" placeholder="Nom, prénom, code...">
            </div>
            <div class="hist-filter__field" style="max-width:165px;">
                <label>Du</label>
                <input type="date" name="from" value="<?= h($fFrom) ?>">
            </div>
            <div class="hist-filter__field" style="max-width:165px;">
                <label>Au</label>
                <input type="date" name="to" value="<?= h($fTo) ?>">
            </div>
            <div style="display:flex; gap:0.5rem; padding-bottom:2px;">
                <button type="submit" class="hist-btn"><i class="fa-solid fa-filter"></i> Filtrer</button>
                <?php if ($fType !== '' || $fSearch !== '' || $fFrom !== '' || $fTo !== ''): ?>
                <a href="historique_documents.php" class="hist-btn ghost"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </div>
        </div>
        </form>
    </div>

    <!-- Table -->
    <div class="card" style="padding:0; overflow:hidden;">
        <?php if (empty($docs)): ?>
        <div class="hist-empty">
            <i class="fa-solid fa-scroll"></i>
            Aucun document trouvé pour ces critères.
        </div>
        <?php else: ?>
        <div style="padding:1rem 1.25rem 0.75rem; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
            <span style="font-size:0.83rem; color:#71717a;">
                <?= number_format($totalFiltered) ?> document<?= $totalFiltered !== 1 ? 's' : '' ?> trouvé<?= $totalFiltered !== 1 ? 's' : '' ?>
                <?php if ($totalFiltered > $perPage): ?>
                · Affichage <?= $offset+1 ?>–<?= min($offset+$perPage, $totalFiltered) ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="hist-table-wrap">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th style="width:14%;">Date & heure</th>
                        <th style="width:28%;">Type de document</th>
                        <th style="width:30%;">Stagiaire</th>
                        <th>Référence</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($docs as $d):
                    $tkey   = (string)$d['type_document'];
                    $tlabel = $typeLabels[$tkey] ?? ($tkey ?: 'Inconnu');
                    $icon   = $typeIcons[$tkey]  ?? ['fa-file', 'rgba(113,113,122,0.15)', '#71717a'];
                    $hubUrl = 'stagiaires.php?id=' . (int)$d['id_stagiaire'];
                ?>
                <tr>
                    <td style="color:#a1a1aa; font-size:0.8rem; white-space:nowrap;">
                        <?= date('d/m/Y', strtotime((string)$d['genere_le'])) ?><br>
                        <span style="color:#52525b;"><?= date('H:i', strtotime((string)$d['genere_le'])) ?></span>
                    </td>
                    <td>
                        <span class="doc-type-badge" style="background:<?= $icon[1] ?>;color:<?= $icon[2] ?>;">
                            <i class="fa-solid <?= $icon[0] ?>" style="font-size:0.75rem;"></i>
                            <?= h($tlabel) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= $hubUrl ?>" class="stag-link" target="_blank">
                            <?= h(trim($d['prenom'].' '.$d['nom'])) ?>
                        </a>
                        <?php if (!empty($d['num_inscri'])): ?>
                        <div class="num-inscri"><?= h((string)$d['num_inscri']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:#71717a; font-size:0.82rem;">
                        <?= $d['reference'] !== null ? h((string)$d['reference']) : '<span style="color:#3f3f46;">—</span>' ?>
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
        $qBase = [];
        if ($fType)   $qBase['type'] = $fType;
        if ($fSearch) $qBase['q']    = $fSearch;
        if ($fFrom)   $qBase['from'] = $fFrom;
        if ($fTo)     $qBase['to']   = $fTo;
        function pgUrl(array $base, int $p): string {
            $base['p'] = $p;
            return 'historique_documents.php?' . http_build_query($base);
        }
    ?>
    <div class="hist-pagination">
        <a href="<?= pgUrl($qBase, 1) ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?= pgUrl($qBase, $page - 1) ?>" class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php
        $startPg = max(1, $page - 2);
        $endPg   = min($totalPages, $page + 2);
        if ($startPg > 1): ?><span class="pg-btn disabled">…</span><?php endif;
        for ($pg = $startPg; $pg <= $endPg; $pg++): ?>
        <a href="<?= pgUrl($qBase, $pg) ?>" class="pg-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
        <?php endfor;
        if ($endPg < $totalPages): ?><span class="pg-btn disabled">…</span><?php endif; ?>
        <a href="<?= pgUrl($qBase, $page + 1) ?>" class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?= pgUrl($qBase, $totalPages) ?>" class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
