<?php
/**
 * historique_documents.php — Journal de tous les documents générés
 *
 * Page en lecture seule accessible à tous les admins.
 * Affiche l'ensemble des entrées de la table documents_generes avec :
 *   - 4 cartes de statistiques globales (total, aujourd'hui, type le plus fréquent, résultats filtrés)
 *   - Barre de filtres : type de document, recherche stagiaire (nom/prénom/code), plage de dates
 *   - Tableau paginé (40 lignes / page) avec badge coloré par type et lien vers le hub stagiaire
 *   - Pagination avec ellipsis (fenêtre glissante ± 2 pages)
 *
 * Tables : documents_generes, stagiaires
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Historique des Documents';
$curPage   = 'historique_docs';


// ============================================================
//  SECTION 1 : Référentiels — libellés et icônes par type de document
// ============================================================

/**
 * Retourne le libellé français d'un type de document.
 * Si le type est inconnu, transforme les underscores en espaces.
 *
 * @param string $cleType         Code interne du type (ex: 'certificat_scolarite').
 * @param array  $carteLabelTypes Tableau de correspondance code → libellé.
 * @return string                 Libellé affichable.
 */
function libelleType(string $cleType, array $carteLabelTypes): string
{
    if (isset($carteLabelTypes[$cleType])) {
        return $carteLabelTypes[$cleType];
    }
    return trim($cleType) !== '' ? ucfirst(str_replace('_', ' ', $cleType)) : 'Inconnu';
}

// Correspondance code → libellé français
$carteLabelTypes = [
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

// Correspondance code → [classe Font Awesome, couleur de fond, couleur du texte]
$carteIconesTypes = [
    'certificat_scolarite' => ['fa-graduation-cap',        'rgba(168,85,247,0.15)',  '#a855f7'],
    'billet_excuse'        => ['fa-file-circle-exclamation','rgba(248,113,113,0.15)','#f87171'],
    'etat_mensualites'     => ['fa-file-invoice-dollar',   'rgba(16,185,129,0.15)',  '#10b981'],
    'fiche_inscription'    => ['fa-user-plus',             'rgba(96,165,250,0.15)',  '#60a5fa'],
    'recu_paiement'        => ['fa-receipt',               'rgba(52,211,153,0.15)',  '#34d399'],
    'releve_notes'         => ['fa-table-list',            'rgba(251,191,36,0.15)',  '#fbbf24'],
    'bulletin'             => ['fa-chart-bar',             'rgba(56,189,248,0.15)',  '#38bdf8'],
    'attestation_reussite' => ['fa-award',                 'rgba(74,222,128,0.15)',  '#4ade80'],
    'convention_stage'     => ['fa-briefcase',             'rgba(192,132,252,0.15)', '#c084fc'],
    'fiche_preinscription' => ['fa-clipboard-list',        'rgba(251,146,60,0.15)',  '#fb923c'],
    'liste_stagiaires'     => ['fa-users',                 'rgba(129,140,248,0.15)', '#818cf8'],
    'etat_paiement'        => ['fa-file-invoice',          'rgba(244,114,182,0.15)', '#f472b6'],
    'rapport_individuel'   => ['fa-file-lines',            'rgba(251,146,60,0.15)',  '#fb923c'],
    'autre'                => ['fa-file',                  'rgba(113,113,122,0.15)', '#71717a'],
];

// Icône de repli utilisée pour les types non répertoriés
$iconeParDefaut = ['fa-file', 'rgba(113,113,122,0.15)', '#71717a'];


// ============================================================
//  SECTION 2 : Paramètres de filtrage (GET) et pagination
// ============================================================

$filtreType      = trim((string)($_GET['type'] ?? ''));
$filtreRecherche = trim((string)($_GET['q']    ?? ''));
$filtreDepuis    = trim((string)($_GET['from'] ?? ''));
$filtreJusquA    = trim((string)($_GET['to']   ?? ''));

$pageCourante = max(1, (int)($_GET['p'] ?? 1));
$parPage      = 40;


// ============================================================
//  SECTION 3 : Construction de la clause WHERE et requêtes
// ============================================================

$conditionsSql = ['1=1'];
$parametresSql = [];

if ($filtreType !== '') {
    $conditionsSql[] = 'd.type_document = ?';
    $parametresSql[] = $filtreType;
}
if ($filtreRecherche !== '') {
    // Recherche sur nom, prénom ou code d'inscription
    $termeRecherche  = "%$filtreRecherche%";
    $conditionsSql[] = '(s.nom LIKE ? OR s.prenom LIKE ? OR s.num_inscri LIKE ?)';
    $parametresSql[] = $termeRecherche;
    $parametresSql[] = $termeRecherche;
    $parametresSql[] = $termeRecherche;
}
if ($filtreDepuis !== '') {
    $conditionsSql[] = 'DATE(d.genere_le) >= ?';
    $parametresSql[] = $filtreDepuis;
}
if ($filtreJusquA !== '') {
    $conditionsSql[] = 'DATE(d.genere_le) <= ?';
    $parametresSql[] = $filtreJusquA;
}
$clauseWhere = implode(' AND ', $conditionsSql);

// ── Statistiques globales (sans filtre) ───────────────────────────────────────
$totalDocuments      = (int)$pdo->query("SELECT COUNT(*) FROM documents_generes")->fetchColumn();
$documentsAujourdhui = (int)$pdo->query(
    "SELECT COUNT(*) FROM documents_generes WHERE DATE(genere_le) = CURDATE()"
)->fetchColumn();

$ligneTypePlusFrequent = $pdo->query(
    "SELECT type_document, COUNT(*) AS nombre
       FROM documents_generes
      GROUP BY type_document
      ORDER BY nombre DESC
      LIMIT 1"
)->fetch();
$codeTypePlusFrequent  = $ligneTypePlusFrequent ? (string)$ligneTypePlusFrequent['type_document'] : '';
$nombreTypePlusFrequent = $ligneTypePlusFrequent ? (int)$ligneTypePlusFrequent['nombre'] : 0;

// ── Comptage des résultats filtrés (pour pagination et carte) ─────────────────
$reqNombreTotal = $pdo->prepare(
    "SELECT COUNT(*)
       FROM documents_generes d
       JOIN stagiaires s ON s.id_stagiaire = d.id_stagiaire
      WHERE $clauseWhere"
);
$reqNombreTotal->execute($parametresSql);
$totalFiltres = (int)$reqNombreTotal->fetchColumn();

// Recalculer la page courante et le décalage après avoir le nombre exact de pages
$totalPages   = max(1, (int)ceil($totalFiltres / $parPage));
$pageCourante = min($pageCourante, $totalPages);
$decalage     = ($pageCourante - 1) * $parPage;

// ── Résultats paginés ─────────────────────────────────────────────────────────
$reqDocuments = $pdo->prepare(
    "SELECT d.id_gen, d.type_document, d.reference, d.genere_le,
            s.id_stagiaire, s.nom, s.prenom, s.num_inscri
       FROM documents_generes d
       JOIN stagiaires s ON s.id_stagiaire = d.id_stagiaire
      WHERE $clauseWhere
      ORDER BY d.id_gen DESC
      LIMIT $parPage OFFSET $decalage"
);
$reqDocuments->execute($parametresSql);
$listeDocuments = $reqDocuments->fetchAll();

// ── Répartition par type (pour les options du sélecteur de filtre) ────────────
$repartitionTypes = $pdo->query(
    "SELECT type_document, COUNT(*) AS nombre
       FROM documents_generes
      GROUP BY type_document
      ORDER BY nombre DESC"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<style>
/* ── Gabarit principal ── */
.hist-shell { max-width:1200px; margin:0 auto; padding-bottom:3rem; }

/* ── Cartes de statistiques ── */
.hist-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:1.75rem; }
.hist-stat  { background:#16161e; border:1px solid rgba(255,255,255,0.07); border-radius:12px; padding:1.2rem 1.4rem; display:flex; flex-direction:column; gap:0.3rem; }
.hist-stat__val { font-size:2rem; font-weight:800; line-height:1; }
.hist-stat__lbl { font-size:0.72rem; color:#71717a; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
.hist-stat__sub { font-size:0.78rem; color:#a1a1aa; margin-top:0.1rem; }

/* ── Barre de filtres ── */
.hist-filter { background:#16161e; border:1px solid rgba(255,255,255,0.07); border-radius:14px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; }
.hist-filter__grid  { display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; }
.hist-filter__field { display:flex; flex-direction:column; gap:0.35rem; min-width:160px; flex:1; }
.hist-filter__field label { font-size:0.7rem; color:#71717a; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
.hist-filter__field select,
.hist-filter__field input  { background:#0d0d14; border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:#fff; padding:0.5rem 0.75rem; font-size:0.86rem; width:100%; }
.hist-filter__field input[type="date"] { color-scheme:dark; }

/* ── Boutons de la barre de filtres ── */
.hist-btn { background:rgba(168,85,247,0.2); color:#a855f7; border:1px solid rgba(168,85,247,0.35); border-radius:8px; padding:0.55rem 1.2rem; font-size:0.87rem; font-weight:700; cursor:pointer; white-space:nowrap; transition:background .2s; }
.hist-btn:hover { background:rgba(168,85,247,0.35); }
.hist-btn.ghost { background:transparent; color:#71717a; border-color:rgba(255,255,255,0.1); }
.hist-btn.ghost:hover { background:rgba(255,255,255,0.04); color:#e4e4e7; }

/* ── Tableau des documents ── */
.hist-table-wrap { overflow-x:auto; }
.hist-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.hist-table thead th {
    background:rgba(255,255,255,0.025);
    color:#71717a; font-size:0.68rem; text-transform:uppercase;
    letter-spacing:.1em; font-weight:800;
    padding:0.85rem 1rem; border-bottom:1px solid rgba(255,255,255,0.06);
    text-align:left; white-space:nowrap;
}
.hist-table tbody tr  { border-bottom:1px solid rgba(255,255,255,0.04); transition:background .15s; }
.hist-table tbody tr:hover td { background:rgba(168,85,247,0.07); }
.hist-table tbody td  { padding:0.7rem 1rem; vertical-align:middle; }

/* ── Badge de type de document ── */
.doc-type-badge {
    display:inline-flex; align-items:center; gap:0.4rem;
    padding:0.28rem 0.7rem; border-radius:20px; font-size:0.77rem; font-weight:600; white-space:nowrap;
}

/* ── Lien stagiaire ── */
.stag-link    { color:#c4b5fd; font-weight:600; text-decoration:none; font-size:0.87rem; }
.stag-link:hover { color:#a855f7; text-decoration:underline; }
.num-inscri   { font-size:0.73rem; color:#71717a; margin-top:2px; }

/* ── Pagination ── */
.hist-pagination { display:flex; align-items:center; justify-content:center; gap:0.4rem; margin-top:1.5rem; flex-wrap:wrap; }
.pg-btn       { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:7px; color:#a1a1aa; padding:0.4rem 0.75rem; font-size:0.83rem; text-decoration:none; transition:all .15s; }
.pg-btn:hover { background:rgba(168,85,247,0.15); border-color:rgba(168,85,247,0.3); color:#c4b5fd; }
.pg-btn.active   { background:rgba(168,85,247,0.25); border-color:rgba(168,85,247,0.5); color:#e9d5ff; font-weight:700; }
.pg-btn.disabled { opacity:0.3; pointer-events:none; }

/* ── État vide ── */
.hist-empty { text-align:center; padding:3.5rem 1rem; color:#52525b; }
.hist-empty i { font-size:2.2rem; display:block; margin-bottom:0.75rem; color:#3f3f46; }
</style>

<div class="hist-shell">

    <!-- ── En-tête de page ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.35rem;font-weight:800;color:#e4e4e7;margin:0 0 0.2rem;">
                <i class="fa-solid fa-scroll" style="color:#a855f7;margin-right:0.5rem;"></i>Historique des Documents
            </h1>
            <p style="color:#71717a;font-size:0.83rem;margin:0;">Tous les documents générés depuis l'application</p>
        </div>
        <a href="index.php" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>


    <!-- ── SECTION 4 : Cartes de statistiques globales ── -->
    <div class="hist-stats">

        <!-- Total de tous les documents en base -->
        <div class="hist-stat" style="border-top:3px solid #a855f7;">
            <div class="hist-stat__val" style="color:#a855f7;"><?= number_format($totalDocuments) ?></div>
            <div class="hist-stat__lbl">Documents générés</div>
            <div class="hist-stat__sub">total historique</div>
        </div>

        <!-- Documents générés ce jour -->
        <div class="hist-stat" style="border-top:3px solid #fbbf24;">
            <div class="hist-stat__val" style="color:#fbbf24;"><?= number_format($documentsAujourdhui) ?></div>
            <div class="hist-stat__lbl">Aujourd'hui</div>
            <div class="hist-stat__sub"><?= date('d/m/Y') ?></div>
        </div>

        <!-- Type de document le plus émis (toutes périodes) -->
        <div class="hist-stat" style="border-top:3px solid #34d399;">
            <div class="hist-stat__val" style="color:#34d399;"><?= number_format($nombreTypePlusFrequent) ?></div>
            <div class="hist-stat__lbl">Type le plus fréquent</div>
            <div class="hist-stat__sub">
                <?= $codeTypePlusFrequent !== ''
                    ? h(libelleType($codeTypePlusFrequent, $carteLabelTypes))
                    : '—' ?>
            </div>
        </div>

        <!-- Nombre de résultats correspondant aux filtres actifs -->
        <div class="hist-stat" style="border-top:3px solid #60a5fa;">
            <div class="hist-stat__val" style="color:#60a5fa;"><?= number_format($totalFiltres) ?></div>
            <div class="hist-stat__lbl">Résultats filtrés</div>
            <div class="hist-stat__sub">page <?= $pageCourante ?> / <?= $totalPages ?></div>
        </div>

    </div>


    <!-- ── Barre de filtres ── -->
    <div class="hist-filter">
        <form method="get" action="historique_documents.php" id="hist-filter-form">
        <div class="hist-filter__grid">

            <!-- Filtre par type de document -->
            <div class="hist-filter__field" style="max-width:220px;">
                <label>Type de document</label>
                <select name="type">
                    <option value="">— Tous les types —</option>
                    <?php foreach ($repartitionTypes as $typeCompte):
                        $cleType     = (string)$typeCompte['type_document'];
                        $libType     = libelleType($cleType, $carteLabelTypes);
                    ?>
                    <option value="<?= h($cleType) ?>" <?= $filtreType === $cleType ? 'selected' : '' ?>>
                        <?= h($libType) ?> (<?= (int)$typeCompte['nombre'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Recherche par nom, prénom ou code d'inscription -->
            <div class="hist-filter__field">
                <label>Rechercher stagiaire</label>
                <input type="search" name="q" value="<?= h($filtreRecherche) ?>" placeholder="Nom, prénom, code…">
            </div>

            <!-- Filtre date de début -->
            <div class="hist-filter__field" style="max-width:165px;">
                <label>Du</label>
                <input type="date" name="from" value="<?= h($filtreDepuis) ?>">
            </div>

            <!-- Filtre date de fin -->
            <div class="hist-filter__field" style="max-width:165px;">
                <label>Au</label>
                <input type="date" name="to" value="<?= h($filtreJusquA) ?>">
            </div>

            <!-- Boutons : appliquer / réinitialiser -->
            <div style="display:flex;gap:0.5rem;padding-bottom:2px;">
                <button type="submit" class="hist-btn">
                    <i class="fa-solid fa-filter"></i> Filtrer
                </button>
                <?php if ($filtreType !== '' || $filtreRecherche !== '' || $filtreDepuis !== '' || $filtreJusquA !== ''): ?>
                <a href="historique_documents.php" class="hist-btn ghost">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <?php endif; ?>
            </div>

        </div>
        </form>
    </div>


    <!-- ── Tableau des documents ── -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if (empty($listeDocuments)): ?>

        <!-- État vide : aucun document ne correspond aux filtres -->
        <div class="hist-empty">
            <i class="fa-solid fa-scroll"></i>
            Aucun document trouvé pour ces critères.
        </div>

        <?php else: ?>

        <!-- Résumé : nombre de résultats et plage affichée -->
        <div style="padding:1rem 1.25rem 0.75rem;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
            <span style="font-size:0.83rem;color:#71717a;">
                <?= number_format($totalFiltres) ?> document<?= $totalFiltres !== 1 ? 's' : '' ?> trouvé<?= $totalFiltres !== 1 ? 's' : '' ?>
                <?php if ($totalFiltres > $parPage): ?>
                · Affichage <?= $decalage + 1 ?>–<?= min($decalage + $parPage, $totalFiltres) ?>
                <?php endif; ?>
            </span>
        </div>

        <div class="hist-table-wrap">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th style="width:14%;">Date &amp; heure</th>
                        <th style="width:28%;">Type de document</th>
                        <th style="width:30%;">Stagiaire</th>
                        <th>Référence</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($listeDocuments as $doc):
                    // Résolution du libellé et de l'icône du type de document
                    $cleType         = (string)$doc['type_document'];
                    $libelleDocument = libelleType($cleType, $carteLabelTypes);
                    $icone           = $carteIconesTypes[$cleType] ?? $iconeParDefaut;
                    $urlHubStagiaire = 'stagiaires.php?id=' . (int)$doc['id_stagiaire'];
                    $dateGeneration  = strtotime((string)$doc['genere_le']);
                ?>
                <tr>
                    <!-- Date et heure de génération -->
                    <td style="color:#a1a1aa;font-size:0.8rem;white-space:nowrap;">
                        <?= date('d/m/Y', $dateGeneration) ?><br>
                        <span style="color:#52525b;"><?= date('H:i', $dateGeneration) ?></span>
                    </td>

                    <!-- Badge coloré du type de document -->
                    <td>
                        <span class="doc-type-badge" style="background:<?= $icone[1] ?>;color:<?= $icone[2] ?>;">
                            <i class="fa-solid <?= $icone[0] ?>" style="font-size:0.75rem;"></i>
                            <?= h($libelleDocument) ?>
                        </span>
                    </td>

                    <!-- Nom du stagiaire avec lien vers son hub -->
                    <td>
                        <a href="<?= $urlHubStagiaire ?>" class="stag-link" target="_blank">
                            <?= h(trim($doc['prenom'] . ' ' . $doc['nom'])) ?>
                        </a>
                        <?php if (!empty($doc['num_inscri'])): ?>
                        <div class="num-inscri"><?= h((string)$doc['num_inscri']) ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- Référence du document (peut être nulle) -->
                    <td style="color:#71717a;font-size:0.82rem;">
                        <?= $doc['reference'] !== null
                            ? h((string)$doc['reference'])
                            : '<span style="color:#3f3f46;">—</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>


    <!-- ── Pagination (fenêtre glissante ± 2 pages) ── -->
    <?php if ($totalPages > 1):

        // Construire le tableau de base des paramètres GET à conserver dans les liens de pagination
        $baseUrlPagination = [];
        if ($filtreType      !== '') $baseUrlPagination['type'] = $filtreType;
        if ($filtreRecherche !== '') $baseUrlPagination['q']    = $filtreRecherche;
        if ($filtreDepuis    !== '') $baseUrlPagination['from'] = $filtreDepuis;
        if ($filtreJusquA    !== '') $baseUrlPagination['to']   = $filtreJusquA;

        /**
         * Génère l'URL d'un lien de pagination en conservant les filtres actifs.
         *
         * @param array $base  Paramètres GET courants (filtres).
         * @param int   $p     Numéro de page cible.
         * @return string      URL complète pour historique_documents.php.
         */
        function urlPagination(array $base, int $p): string
        {
            $base['p'] = $p;
            return 'historique_documents.php?' . http_build_query($base);
        }

        // Fenêtre de pages affichées : ± 2 autour de la page courante
        $pageDebut = max(1, $pageCourante - 2);
        $pageFin   = min($totalPages, $pageCourante + 2);
    ?>
    <div class="hist-pagination">

        <!-- Première page et précédente -->
        <a href="<?= urlPagination($baseUrlPagination, 1) ?>"
           class="pg-btn <?= $pageCourante <= 1 ? 'disabled' : '' ?>">
            <i class="fa-solid fa-angles-left"></i>
        </a>
        <a href="<?= urlPagination($baseUrlPagination, $pageCourante - 1) ?>"
           class="pg-btn <?= $pageCourante <= 1 ? 'disabled' : '' ?>">
            <i class="fa-solid fa-angle-left"></i>
        </a>

        <!-- Ellipsis de début si la fenêtre ne commence pas à la page 1 -->
        <?php if ($pageDebut > 1): ?>
        <span class="pg-btn disabled">…</span>
        <?php endif; ?>

        <!-- Pages numérotées dans la fenêtre -->
        <?php for ($i = $pageDebut; $i <= $pageFin; $i++): ?>
        <a href="<?= urlPagination($baseUrlPagination, $i) ?>"
           class="pg-btn <?= $i === $pageCourante ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>

        <!-- Ellipsis de fin si la fenêtre ne termine pas à la dernière page -->
        <?php if ($pageFin < $totalPages): ?>
        <span class="pg-btn disabled">…</span>
        <?php endif; ?>

        <!-- Suivante et dernière page -->
        <a href="<?= urlPagination($baseUrlPagination, $pageCourante + 1) ?>"
           class="pg-btn <?= $pageCourante >= $totalPages ? 'disabled' : '' ?>">
            <i class="fa-solid fa-angle-right"></i>
        </a>
        <a href="<?= urlPagination($baseUrlPagination, $totalPages) ?>"
           class="pg-btn <?= $pageCourante >= $totalPages ? 'disabled' : '' ?>">
            <i class="fa-solid fa-angles-right"></i>
        </a>

    </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
