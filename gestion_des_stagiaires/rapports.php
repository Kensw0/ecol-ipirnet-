<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Rapports & Exports';
$curPage   = 'rapports';
$isDir     = gds_is_directeur();

// ── Active tab ─────────────────────────────────────────────────────────────
$validTabs = ['stagiaires', 'notes', 'paiements', 'absences', 'historique'];
$activeTab = trim((string)($_GET['tab'] ?? 'stagiaires'));
if (!in_array($activeTab, $validTabs, true)) $activeTab = 'stagiaires';

// ── Filter params ──────────────────────────────────────────────────────────
$selAnnee   = trim((string)($_GET['annee']      ?? ''));
$selFiliere = (int)($_GET['id_filiere'] ?? 0);
$selNiveau  = trim((string)($_GET['niveau']     ?? ''));
$selClasse  = (int)($_GET['id_classe']  ?? 0);
$selModule   = (int)($_GET['id_module']   ?? 0);
$selSemestre = trim((string)($_GET['semestre']  ?? ''));
if (!in_array($selSemestre, ['', '1', '2'], true)) $selSemestre = '';
$selDateDe  = trim((string)($_GET['date_de']    ?? ''));
$selDateA   = trim((string)($_GET['date_a']     ?? ''));

// Validate date formats
if ($selDateDe !== '' && !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $selDateDe)) $selDateDe = '';
if ($selDateA  !== '' && !preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $selDateA))  $selDateA  = '';

// ── WHERE builder helper ────────────────────────────────────────────────────
/**
 * Build WHERE clauses for class-based filters.
 * $tClasse = alias for `classes` table, $tFiliere = alias for `filieres`.
 */
function rpt_class_where(int $selClasse, int $selFiliere, string $selNiveau, string $selAnnee,
                          string $tClasse = 'c', string $tFiliere = 'f'): array {
    $where = []; $params = [];
    if ($selClasse > 0) {
        $where[] = "{$tClasse}.id_classe = ?"; $params[] = $selClasse;
    } else {
        if ($selFiliere > 0)  { $where[] = "{$tClasse}.id_filiere = ?"; $params[] = $selFiliere; }
        if ($selNiveau !== '') { $where[] = "{$tClasse}.niveau = ?";    $params[] = $selNiveau; }
        if ($selAnnee  !== '') { $where[] = "{$tClasse}.annee_scolaire = ?"; $params[] = $selAnnee; }
    }
    return [$where, $params];
}

// ── CSV Export (early exit before HTML) ───────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportTab = trim((string)($_GET['tab'] ?? 'stagiaires'));
    if (!in_array($exportTab, $validTabs, true)) $exportTab = 'stagiaires';
    if ($exportTab === 'paiements' && !$isDir) {
        http_response_code(403); exit('Accès refusé.');
    }

    [$cw, $cp] = rpt_class_where($selClasse, $selFiliere, $selNiveau, $selAnnee);
    $cwSql = $cw ? 'WHERE ' . implode(' AND ', $cw) : '';

    $rows = [];
    $headers = [];

    if ($exportTab === 'stagiaires') {
        $headers = ['Numéro inscription','Nom','Prénom','CIN','Email','Téléphone','Classe','Filière','Année','Niveau','Remise mensuelle (MAD)'];
        $sql = "SELECT s.num_inscri,s.nom,s.prenom,s.cin,s.email,s.telephone,c.nom_classe,f.nom_filiere,c.annee_scolaire,c.niveau,s.remise_mensuelle
                FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
                " . ($cw ? 'WHERE ' . implode(' AND ', $cw) : '') . "
                ORDER BY c.annee_scolaire DESC,f.nom_filiere,c.nom_classe,s.nom,s.prenom";
        $st = $pdo->prepare($sql); $st->execute($cp);
        foreach ($st->fetchAll() as $r)
            $rows[] = [$r['num_inscri'],$r['nom'],$r['prenom'],$r['cin'],$r['email'],$r['telephone'],$r['nom_classe'],$r['nom_filiere'],$r['annee_scolaire'],$r['niveau'],number_format((float)$r['remise_mensuelle'],2,',','')];

    } elseif ($exportTab === 'notes') {
        $headers = ['Semestre','Module','Coefficient','Moy. classe','Nb notes','Nb admis (≥10)','Taux admis %'];
        $nWhere = $cw; $nParams = $cp;
        if ($selModule   > 0)  { $nWhere[] = 'vm.id_module = ?'; $nParams[] = $selModule; }
        if ($selSemestre !== '') { $nWhere[] = 'm.semestre = ?'; $nParams[] = $selSemestre; }
        $sql = "SELECT m.nom_module,m.coefficient,m.semestre,
                       ROUND(AVG(vm.moyenne_module),2) as moy,
                       COUNT(vm.id_stagiaire) as nb,
                       SUM(CASE WHEN vm.moyenne_module>=10 THEN 1 ELSE 0 END) as admis
                FROM v_moyennes_par_module vm
                JOIN modules m ON m.id_module=vm.id_module
                JOIN stagiaires s ON s.id_stagiaire=vm.id_stagiaire
                JOIN classes c ON c.id_classe=s.id_classe
                JOIN filieres f ON f.id_filiere=c.id_filiere
                " . ($nWhere ? 'WHERE ' . implode(' AND ', $nWhere) : '') . "
                GROUP BY vm.id_module,m.nom_module,m.coefficient,m.semestre ORDER BY m.semestre,m.nom_module";
        $st = $pdo->prepare($sql); $st->execute($nParams);
        foreach ($st->fetchAll() as $r) {
            $taux = $r['nb'] > 0 ? round($r['admis'] / $r['nb'] * 100, 1) : 0;
            $rows[] = [$r['semestre'],$r['nom_module'],$r['coefficient'],$r['moy'],$r['nb'],$r['admis'],$taux];
        }

    } elseif ($exportTab === 'paiements') {
        $headers = ['Mois','Total dû (MAD)','Perçu (MAD)','Restant (MAD)','Payés','Partiels','Impayés','Total'];
        $pWhere = $cw; $pParams = $cp;
        if ($selDateDe !== '') { $pWhere[] = 'mn.mois_ref >= ?'; $pParams[] = substr($selDateDe,0,7); }
        if ($selDateA  !== '') { $pWhere[] = 'mn.mois_ref <= ?'; $pParams[] = substr($selDateA, 0,7); }
        $sql = "SELECT mn.mois_ref,
                       SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)))) as du,
                       SUM(mn.montant_paye) as percu,
                       SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)) - mn.montant_paye)) as restant,
                       SUM(mn.statut_paiement='payé') as paye, SUM(mn.statut_paiement='partiel') as partiel,
                       SUM(mn.statut_paiement='impayé') as impaye, COUNT(*) as total
                FROM mensualites mn JOIN stagiaires s ON s.id_stagiaire=mn.id_stagiaire
                JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
                " . ($pWhere ? 'WHERE ' . implode(' AND ', $pWhere) : '') . "
                GROUP BY mn.mois_ref ORDER BY mn.mois_ref";
        $st = $pdo->prepare($sql); $st->execute($pParams);
        foreach ($st->fetchAll() as $r)
            $rows[] = [$r['mois_ref'],number_format((float)$r['du'],2,',',''),number_format((float)$r['percu'],2,',',''),number_format((float)$r['restant'],2,',',''),$r['paye'],$r['partiel'],$r['impaye'],$r['total']];

    } elseif ($exportTab === 'absences') {
        $headers = ['Date','Module','Heure début','Heure fin','Nom','Prénom','N° Inscription','Classe','Justifiée'];
        $aWhere = $cw; $aParams = $cp;
        if ($selDateDe !== '') { $aWhere[] = 'a.date_absence >= ?'; $aParams[] = $selDateDe; }
        if ($selDateA  !== '') { $aWhere[] = 'a.date_absence <= ?'; $aParams[] = $selDateA; }
        $sql = "SELECT a.date_absence, COALESCE(m.nom_module,'—') as nom_module,
                       a.heure_debut, a.heure_fin,
                       s.nom, s.prenom, s.num_inscri, c.nom_classe, a.est_justifiee
                FROM absences a
                JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire
                JOIN classes c ON c.id_classe=s.id_classe
                JOIN filieres f ON f.id_filiere=c.id_filiere
                LEFT JOIN modules m ON m.id_module=a.id_module
                " . ($aWhere ? 'WHERE ' . implode(' AND ', $aWhere) : '') . "
                ORDER BY a.date_absence DESC, s.nom, s.prenom";
        $st = $pdo->prepare($sql); $st->execute($aParams);
        foreach ($st->fetchAll() as $r)
            $rows[] = [$r['date_absence'],$r['nom_module'],$r['heure_debut']??'',$r['heure_fin']??'',$r['nom'],$r['prenom'],$r['num_inscri'],$r['nom_classe'],$r['est_justifiee']?'Oui':'Non'];
    }

    $fname = 'rapport_' . $exportTab . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers, ';', '"', '\\', "\r\n");
    foreach ($rows as $row) fputcsv($out, $row, ';', '"', '\\', "\r\n");
    fclose($out);
    exit;
}

// ── Filter cascade queries ─────────────────────────────────────────────────
$allAnnees   = $pdo->query("SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}' ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);
if ($selAnnee === '') { $selAnnee = $_SESSION['global_annee_scolaire'] ?? ($allAnnees[0] ?? ''); }
$allFilieres = $pdo->query("SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere")->fetchAll();
if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }
$allNiveaux  = [];
$allClasses  = [];
$allModules  = [];

if ($selAnnee !== '' && $selFiliere > 0) {
    $st = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $st->execute([$selFiliere, $selAnnee]);
    $allNiveaux = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allNiveaux) && !in_array($selNiveau, $allNiveaux, true)) { $selNiveau = $allNiveaux[0]; }
}
if ($selNiveau !== '' && $selFiliere > 0 && $selAnnee !== '') {
    $st = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $st->execute([$selFiliere, $selAnnee, $selNiveau]);
    $allClasses = $st->fetchAll();
    $_vcids = array_map('intval', array_column($allClasses, 'id_classe'));
    if (!empty($allClasses) && !in_array($selClasse, $_vcids, true)) { $selClasse = (int)$allClasses[0]['id_classe']; }
}
if ($selFiliere > 0) {
    $st = $pdo->prepare("SELECT id_module, nom_module FROM modules WHERE id_filiere=? ORDER BY nom_module");
    $st->execute([$selFiliere]);
    $allModules = $st->fetchAll();
    if ($selModule === 0 && !empty($allModules)) { $selModule = (int)$allModules[0]['id_module']; }
}

$hasFilters = ($selAnnee !== '' || $selFiliere > 0 || $selClasse > 0);

// ── Class info label ───────────────────────────────────────────────────────
$classeInfo = null;
if ($selClasse > 0) {
    $st = $pdo->prepare("SELECT c.nom_classe,f.nom_filiere,c.annee_scolaire,c.niveau FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $st->execute([$selClasse]);
    $classeInfo = $st->fetch();
}

// ── Build shared WHERE clauses ─────────────────────────────────────────────
[$cw, $cp] = rpt_class_where($selClasse, $selFiliere, $selNiveau, $selAnnee);

// ─── 1. STAGIAIRES data ────────────────────────────────────────────────────
$stagByFiliere = [];
$stagByClasse  = [];
$stagTotal     = 0;

{
    $sql = "SELECT f.nom_filiere, COUNT(*) as total
            FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
            " . ($cw ? 'WHERE ' . implode(' AND ', $cw) : '') . "
            GROUP BY f.id_filiere, f.nom_filiere ORDER BY f.nom_filiere";
    $st  = $pdo->prepare($sql); $st->execute($cp);
    $stagByFiliere = $st->fetchAll();
    $stagTotal     = (int) array_sum(array_column($stagByFiliere, 'total'));
}
{
    $sql = "SELECT c.nom_classe, c.niveau, f.nom_filiere, c.annee_scolaire, COUNT(*) as total
            FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
            " . ($cw ? 'WHERE ' . implode(' AND ', $cw) : '') . "
            GROUP BY c.id_classe, c.nom_classe, c.niveau, f.nom_filiere, c.annee_scolaire
            ORDER BY c.annee_scolaire DESC, f.nom_filiere, c.niveau, c.nom_classe";
    $st  = $pdo->prepare($sql); $st->execute($cp);
    $stagByClasse = $st->fetchAll();
}

// ─── 2. NOTES data ─────────────────────────────────────────────────────────
$notesModules  = [];
$notesRanking  = [];

{
    $nw = $cw; $np = $cp;
    if ($selModule   > 0)  { $nw[] = 'vm.id_module = ?'; $np[] = $selModule; }
    if ($selSemestre !== '') { $nw[] = 'm.semestre = ?'; $np[] = $selSemestre; }
    $sql = "SELECT m.nom_module, m.coefficient, m.semestre,
                   ROUND(AVG(vm.moyenne_module),2) as moy_classe,
                   COUNT(vm.id_stagiaire) as nb_notes,
                   SUM(CASE WHEN vm.moyenne_module >= 10 THEN 1 ELSE 0 END) as nb_admis
            FROM v_moyennes_par_module vm
            JOIN modules m ON m.id_module=vm.id_module
            JOIN stagiaires s ON s.id_stagiaire=vm.id_stagiaire
            JOIN classes c ON c.id_classe=s.id_classe
            JOIN filieres f ON f.id_filiere=c.id_filiere
            " . ($nw ? 'WHERE ' . implode(' AND ', $nw) : '') . "
            GROUP BY vm.id_module, m.nom_module, m.coefficient, m.semestre ORDER BY m.semestre, m.nom_module";
    $st = $pdo->prepare($sql); $st->execute($np);
    $notesModules = $st->fetchAll();
}
{
    $rw = $cw; $rp = $cp;
    if ($selSemestre !== '') { $rw[] = 'm.semestre = ?'; $rp[] = $selSemestre; }
    $sql = "SELECT s.nom, s.prenom, s.num_inscri,
                   ROUND(AVG(vm.moyenne_module),2) as moy_gen,
                   SUM(CASE WHEN vm.moyenne_module >= 10 THEN 1 ELSE 0 END) as mods_admis,
                   COUNT(vm.id_module) as total_mods
            FROM v_moyennes_par_module vm
            JOIN modules m ON m.id_module=vm.id_module
            JOIN stagiaires s ON s.id_stagiaire=vm.id_stagiaire
            JOIN classes c ON c.id_classe=s.id_classe
            JOIN filieres f ON f.id_filiere=c.id_filiere
            " . ($rw ? 'WHERE ' . implode(' AND ', $rw) : '') . "
            GROUP BY vm.id_stagiaire, s.nom, s.prenom, s.num_inscri ORDER BY moy_gen DESC";
    $st = $pdo->prepare($sql); $st->execute($rp);
    $notesRanking = $st->fetchAll();
}

// ─── 3. PAIEMENTS data (director only) ────────────────────────────────────
$paieKpis    = [];
$paieMensuel = [];

if ($isDir) {
    $pw = $cw; $pp = $cp;
    if ($selDateDe !== '') { $pw[] = 'mn.mois_ref >= ?'; $pp[] = substr($selDateDe, 0, 7); }
    if ($selDateA  !== '') { $pw[] = 'mn.mois_ref <= ?'; $pp[] = substr($selDateA,  0, 7); }
    $pWhere = $pw ? 'WHERE ' . implode(' AND ', $pw) : '';

    $st = $pdo->prepare(
        "SELECT SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)))) as total_du,
                SUM(mn.montant_paye) as total_percu,
                SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)) - mn.montant_paye)) as total_restant,
                SUM(mn.statut_paiement='payé')    as nb_paye,
                SUM(mn.statut_paiement='partiel') as nb_partiel,
                SUM(mn.statut_paiement='impayé')  as nb_impaye,
                COUNT(*) as total_lignes
         FROM mensualites mn JOIN stagiaires s ON s.id_stagiaire=mn.id_stagiaire
         JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
         $pWhere");
    $st->execute($pp);
    $paieKpis = $st->fetch() ?: [];

    $st = $pdo->prepare(
        "SELECT mn.mois_ref,
                SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)))) as du,
                SUM(mn.montant_paye) as percu,
                SUM(GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)) - mn.montant_paye)) as restant,
                SUM(mn.statut_paiement='payé')    as nb_paye,
                SUM(mn.statut_paiement='partiel') as nb_partiel,
                SUM(mn.statut_paiement='impayé')  as nb_impaye,
                COUNT(*) as total
         FROM mensualites mn JOIN stagiaires s ON s.id_stagiaire=mn.id_stagiaire
         JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
         $pWhere
         GROUP BY mn.mois_ref ORDER BY mn.mois_ref");
    $st->execute($pp);
    $paieMensuel = $st->fetchAll();
}

// ─── 4. ABSENCES data ──────────────────────────────────────────────────────
$absParClasse = [];
$absTopStags  = [];

{
    $aw = $cw; $ap = $cp;
    if ($selDateDe !== '') { $aw[] = 'a.date_absence >= ?'; $ap[] = $selDateDe; }
    if ($selDateA  !== '') { $aw[] = 'a.date_absence <= ?'; $ap[] = $selDateA; }
    $aWhere = $aw ? 'WHERE ' . implode(' AND ', $aw) : '';

    $st = $pdo->prepare(
        "SELECT c.nom_classe, f.nom_filiere, c.niveau,
                COUNT(*) as total_abs, SUM(a.est_justifiee) as justifiees,
                COUNT(*)-SUM(a.est_justifiee) as injustifiees,
                COUNT(DISTINCT a.id_stagiaire) as nb_absents
         FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire
         JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
         $aWhere
         GROUP BY c.id_classe, c.nom_classe, f.nom_filiere, c.niveau
         ORDER BY f.nom_filiere, c.niveau, c.nom_classe");
    $st->execute($ap);
    $absParClasse = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT s.nom, s.prenom, s.num_inscri, c.nom_classe,
                COUNT(*) as total_abs, SUM(a.est_justifiee) as justifiees,
                COUNT(*)-SUM(a.est_justifiee) as injustifiees
         FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire
         JOIN classes c ON c.id_classe=s.id_classe JOIN filieres f ON f.id_filiere=c.id_filiere
         $aWhere
         GROUP BY a.id_stagiaire, s.nom, s.prenom, s.num_inscri, c.nom_classe
         ORDER BY total_abs DESC LIMIT 15");
    $st->execute($ap);
    $absTopStags = $st->fetchAll();
}

// ── Build export URL helper ────────────────────────────────────────────────
function rpt_export_url(string $tab, int $selClasse, int $selFiliere, string $selNiveau, string $selAnnee, int $selModule, string $selDateDe, string $selDateA, string $selSemestre = ''): string {
    $p = ['export'=>'csv','tab'=>$tab,'annee'=>$selAnnee,'id_filiere'=>$selFiliere,'niveau'=>$selNiveau,'id_classe'=>$selClasse,'id_module'=>$selModule,'semestre'=>$selSemestre,'date_de'=>$selDateDe,'date_a'=>$selDateA];
    return 'rapports.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0));
}

require __DIR__ . '/includes/header.php';
?>
<style>
/* ── Rapports page ───────────────────────────────────────────── */
.rpt-filter-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:1.4rem 1.5rem;margin-bottom:1.5rem;}
.rpt-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.85rem;align-items:end;}
.rpt-filter-grid label{display:flex;flex-direction:column;gap:.3rem;font-size:.77rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.rpt-filter-grid select,.rpt-filter-grid input[type="date"]{background:#09090b;border:1px solid rgba(255,255,255,0.12);color:#e4e4e7;border-radius:8px;padding:.48rem .75rem;font-size:.88rem;width:100%;color-scheme:dark;-webkit-color-scheme:dark;}
.rpt-filter-grid select:disabled,.rpt-filter-grid input:disabled{opacity:.38;cursor:not-allowed;}
.rpt-filter-grid select:focus,.rpt-filter-grid input:focus{outline:none;border-color:rgba(168,85,247,.5);box-shadow:0 0 0 2px rgba(168,85,247,.15);}
/* Tabs */
.rpt-tabs{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:1.5rem;background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:.4rem;}
.rpt-tab{padding:.55rem 1.1rem;border-radius:8px;border:none;background:transparent;color:#a1a1aa;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.45rem;transition:all .18s;white-space:nowrap;}
.rpt-tab:hover{background:rgba(255,255,255,.05);color:#e4e4e7;}
.rpt-tab.active{background:rgba(168,85,247,.18);color:#c084fc;border:1px solid rgba(168,85,247,.3);}
.rpt-tab.locked{color:#52525b;cursor:not-allowed;}
/* Cards */
.rpt-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:1.4rem 1.5rem;margin-bottom:1.25rem;}
.rpt-card-title{font-size:.8rem;font-weight:700;color:#a1a1aa;text-transform:uppercase;letter-spacing:.07em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
.rpt-card-title i{color:#a855f7;}
/* KPI grid */
.rpt-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;}
.rpt-kpi{background:#09090b;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1rem 1.2rem;text-align:center;}
.rpt-kpi .val{font-size:1.7rem;font-weight:800;color:#e4e4e7;line-height:1.1;}
.rpt-kpi .lbl{font-size:.72rem;font-weight:600;color:#71717a;text-transform:uppercase;letter-spacing:.05em;margin-top:.3rem;}
.rpt-kpi.purple .val{color:#c084fc;}
.rpt-kpi.green  .val{color:#34d399;}
.rpt-kpi.red    .val{color:#f87171;}
.rpt-kpi.orange .val{color:#fb923c;}
/* Tables */
.rpt-table-wrap{overflow-x:auto;border-radius:10px;border:1px solid rgba(255,255,255,0.07);}
.rpt-table{width:100%;border-collapse:collapse;font-size:.85rem;}
.rpt-table th{background:#09090b;color:#a1a1aa;font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:.65rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid rgba(255,255,255,0.07);}
.rpt-table td{padding:.65rem 1rem;color:#e4e4e7;border-bottom:1px solid rgba(255,255,255,0.04);}
.rpt-table tr:last-child td{border-bottom:none;}
.rpt-table tr:hover td{background:rgba(255,255,255,0.025);}
.rpt-table .num{text-align:right;font-variant-numeric:tabular-nums;}
.rpt-badge{display:inline-flex;align-items:center;justify-content:center;padding:.2rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.rpt-badge.green{background:rgba(52,211,153,.15);color:#34d399;}
.rpt-badge.orange{background:rgba(251,146,60,.15);color:#fb923c;}
.rpt-badge.red{background:rgba(248,113,113,.15);color:#f87171;}
.rpt-badge.purple{background:rgba(168,85,247,.15);color:#c084fc;}
/* Export btn */
.rpt-export-btn{display:inline-flex;align-items:center;gap:.5rem;background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.3);color:#c084fc;border-radius:8px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s;}
.rpt-export-btn:hover{background:rgba(168,85,247,.25);color:#e9d5ff;}
/* Section header */
.rpt-section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;}
.rpt-section-title{font-size:.95rem;font-weight:700;color:#e4e4e7;}
/* Empty state */
.rpt-empty{text-align:center;padding:3rem 1rem;color:#52525b;}
.rpt-empty i{font-size:2rem;margin-bottom:.75rem;display:block;color:#3f3f46;}
.rpt-empty p{font-size:.9rem;}
/* Rank badge */
.rpt-rank{width:24px;height:24px;border-radius:50%;background:rgba(168,85,247,.15);color:#a855f7;font-size:.72rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;}
.rpt-rank.gold{background:rgba(251,191,36,.15);color:#fbbf24;}
.rpt-rank.silver{background:rgba(148,163,184,.15);color:#94a3b8;}
.rpt-rank.bronze{background:rgba(251,146,60,.15);color:#fb923c;}
/* Access denied */
.rpt-denied{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:14px;padding:2.5rem;text-align:center;color:#f87171;}
.rpt-denied i{font-size:2.2rem;margin-bottom:.75rem;display:block;}
/* Tab sections */
.rpt-section{display:none;}
.rpt-section.active{display:block;}
/* Progress bar */
.rpt-bar-wrap{background:#09090b;border-radius:20px;height:6px;flex:1;min-width:80px;}
.rpt-bar{height:6px;border-radius:20px;background:linear-gradient(90deg,#a855f7,#7c3aed);}
.rpt-bar.green{background:linear-gradient(90deg,#34d399,#059669);}
.rpt-bar.red{background:linear-gradient(90deg,#f87171,#dc2626);}
</style>

<div class="page-header" style="margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;color:#e4e4e7;margin:0 0 .2rem;">
      <i class="fa-solid fa-chart-bar" style="color:#a855f7;margin-right:.5rem;"></i>Rapports & Exports
    </h1>
    <p style="font-size:.85rem;color:#71717a;margin:0;">Vue d'ensemble agrégée — données de toutes les classes</p>
  </div>
</div>

<?php
$__f = flash_get();
if ($__f):
    $fStyle = ['success'=>'rgba(52,211,153,.15);border-color:rgba(52,211,153,.4);color:#6ee7b7;','warning'=>'rgba(251,191,36,.1);border-color:rgba(251,191,36,.35);color:#fcd34d;','error'=>'rgba(248,113,113,.12);border-color:rgba(248,113,113,.4);color:#fca5a5;'];
    $fs = $fStyle[$__f['type']] ?? $fStyle['success'];
?>
<div style="background:<?= $fs ?>;border:1px solid;border-radius:10px;padding:.85rem 1.2rem;margin-bottom:1.2rem;font-size:.88rem;">
  <?= h($__f['msg']) ?>
</div>
<?php endif; ?>

<!-- ── Filter bar ──────────────────────────────────────────────────────── -->
<div class="rpt-filter-card">
  <form method="get" action="rapports.php" id="rpt-filter-form">
    <input type="hidden" name="tab" id="rpt-tab-hidden" value="<?= h($activeTab) ?>">
    <div class="rpt-filter-grid">

      <label>Année scolaire
        <select name="annee" onchange="this.form.submit()">
          <option value="">— Toutes —</option>
          <?php foreach ($allAnnees as $an): ?>
            <option value="<?= h($an) ?>" <?= $selAnnee===$an?'selected':'' ?>><?= h($an) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Filière
        <select name="id_filiere" onchange="this.form.submit()" <?= $selAnnee===''?'disabled':'' ?>>
          <option value="0">— Choisir —</option>
          <?php foreach ($allFilieres as $fil): ?>
            <option value="<?= (int)$fil['id_filiere'] ?>" <?= $selFiliere===(int)$fil['id_filiere']?'selected':'' ?>><?= h(gds_filiere_code((string)$fil['nom_filiere'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Niveau
        <select name="niveau" onchange="this.form.submit()" <?= ($selFiliere===0||$selAnnee==='')?'disabled':'' ?>>
          <?php if (empty($allNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
          <?php foreach ($allNiveaux as $niv): ?>
            <option value="<?= h($niv) ?>" <?= $selNiveau===$niv?'selected':'' ?>><?= h($niv) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Classe
        <select name="id_classe" onchange="this.form.submit()" <?= ($selNiveau===''||$selFiliere===0)?'disabled':'' ?>>
          <?php if (empty($allClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
          <?php foreach ($allClasses as $cl): ?>
            <option value="<?= (int)$cl['id_classe'] ?>" <?= $selClasse===(int)$cl['id_classe']?'selected':'' ?>><?= h($cl['nom_classe']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- Notes tab: semestre filter -->
      <label id="rpt-semestre-label" style="<?= $activeTab==='notes'?'':'display:none' ?>">Semestre
        <select name="semestre" onchange="this.form.submit()">
          <option value="" <?= $selSemestre===''?'selected':'' ?>>— Tous —</option>
          <option value="1" <?= $selSemestre==='1'?'selected':'' ?>>Semestre 1</option>
          <option value="2" <?= $selSemestre==='2'?'selected':'' ?>>Semestre 2</option>
        </select>
      </label>

      <!-- Notes tab: module filter -->
      <label id="rpt-module-label" style="<?= $activeTab==='notes'?'':'display:none' ?>">Module
        <select name="id_module" onchange="this.form.submit()" <?= (empty($allModules))?'disabled':'' ?>>
          <option value="0">— Tous —</option>
          <?php foreach ($allModules as $mod): ?>
            <option value="<?= (int)$mod['id_module'] ?>" <?= $selModule===(int)$mod['id_module']?'selected':'' ?>><?= h(gds_module_label((string)$mod['nom_module'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- Absences / Paiements: date range -->
      <label id="rpt-date-de-label" style="<?= in_array($activeTab,['absences','paiements'])?'':'display:none' ?>">Date de
        <input type="date" name="date_de" value="<?= h($selDateDe) ?>" color-scheme="dark">
      </label>
      <label id="rpt-date-a-label"  style="<?= in_array($activeTab,['absences','paiements'])?'':'display:none' ?>">Date à
        <input type="date" name="date_a"  value="<?= h($selDateA) ?>"  color-scheme="dark">
      </label>

      <label style="justify-content:flex-end;">
        <button type="submit" style="width:100%;justify-content:center;padding:.55rem .75rem;background:rgba(168,85,247,.18);border:1px solid rgba(168,85,247,.3);color:#c084fc;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
          <i class="fa-solid fa-filter"></i> Filtrer
        </button>
      </label>

      <?php if ($hasFilters): ?>
      <label style="justify-content:flex-end;">
        <a href="rapports.php?tab=<?= h($activeTab) ?>" style="width:100%;justify-content:center;padding:.55rem .75rem;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#a1a1aa;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;text-decoration:none;">
          <i class="fa-solid fa-times"></i> Effacer
        </a>
      </label>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Tab navigation ─────────────────────────────────────────────────── -->
<div class="rpt-tabs">
  <button class="rpt-tab <?= $activeTab==='stagiaires'?'active':'' ?>" onclick="rptTab('stagiaires')">
    <i class="fa-solid fa-users"></i> Stagiaires
    <?php if ($stagTotal > 0): ?>
      <span style="background:rgba(168,85,247,.2);color:#c084fc;border-radius:20px;padding:.05rem .45rem;font-size:.72rem;"><?= $stagTotal ?></span>
    <?php endif; ?>
  </button>
  <button class="rpt-tab <?= $activeTab==='notes'?'active':'' ?>" onclick="rptTab('notes')">
    <i class="fa-solid fa-book-open"></i> Notes
  </button>
  <?php if ($isDir): ?>
  <button class="rpt-tab <?= $activeTab==='paiements'?'active':'' ?>" onclick="rptTab('paiements')">
    <i class="fa-solid fa-banknote"></i> Paiements
  </button>
  <?php else: ?>
  <button class="rpt-tab locked" title="Accès réservé au Directeur" disabled>
    <i class="fa-solid fa-lock"></i> Paiements
  </button>
  <?php endif; ?>
  <button class="rpt-tab <?= $activeTab==='absences'?'active':'' ?>" onclick="rptTab('absences')">
    <i class="fa-solid fa-user-x"></i> Absences
  </button>
  <button class="rpt-tab <?= $activeTab==='historique'?'active':'' ?>" onclick="rptTab('historique')">
    <i class="fa-solid fa-clock-rotate-left"></i> Historique
  </button>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     TAB 1 — STAGIAIRES
     ════════════════════════════════════════════════════════════════════════ -->
<div id="rpt-stagiaires" class="rpt-section <?= $activeTab==='stagiaires'?'active':'' ?>">

  <!-- KPIs -->
  <div class="rpt-kpi-grid">
    <div class="rpt-kpi purple"><div class="val"><?= $stagTotal ?></div><div class="lbl">Total stagiaires</div></div>
    <div class="rpt-kpi"><div class="val"><?= count($stagByFiliere) ?></div><div class="lbl">Filières</div></div>
    <div class="rpt-kpi"><div class="val"><?= count($stagByClasse) ?></div><div class="lbl">Classes</div></div>
    <?php if ($selAnnee !== ''): ?>
    <div class="rpt-kpi"><div class="val"><?= h($selAnnee) ?></div><div class="lbl">Année scolaire</div></div>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;">
    <!-- Par filière -->
    <div class="rpt-card">
      <div class="rpt-section-header">
        <div class="rpt-card-title"><i class="fa-solid fa-layer-group"></i> Par filière</div>
        <a class="rpt-export-btn" href="<?= rpt_export_url('stagiaires',$selClasse,$selFiliere,$selNiveau,$selAnnee,$selModule,$selDateDe,$selDateA) ?>">
          <i class="fa-solid fa-file-csv"></i> Exporter CSV
        </a>
      </div>
      <?php if (empty($stagByFiliere)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-users"></i><p>Aucune donnée<?= $hasFilters ? ' pour ce filtre' : '' ?>.</p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead><tr><th>Filière</th><th class="num">Stagiaires</th><th>Répartition</th></tr></thead>
            <tbody>
              <?php foreach ($stagByFiliere as $row):
                $pct = $stagTotal > 0 ? round($row['total'] / $stagTotal * 100) : 0;
              ?>
              <tr>
                <td><?= h(gds_filiere_code((string)$row['nom_filiere'])) ?></td>
                <td class="num"><strong><?= (int)$row['total'] ?></strong></td>
                <td style="min-width:120px;">
                  <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="rpt-bar-wrap"><div class="rpt-bar" style="width:<?= $pct ?>%"></div></div>
                    <span style="font-size:.75rem;color:#71717a;width:30px;text-align:right;"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Par classe -->
    <div class="rpt-card">
      <div class="rpt-card-title"><i class="fa-solid fa-chalkboard"></i> Par classe</div>
      <?php if (empty($stagByClasse)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-chalkboard"></i><p>Aucune donnée<?= $hasFilters ? ' pour ce filtre' : '' ?>.</p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead><tr><th>Classe</th><th>Filière</th><th>Niv.</th><th class="num">Effectif</th></tr></thead>
            <tbody>
              <?php foreach ($stagByClasse as $row): ?>
              <tr>
                <td style="font-weight:600;"><?= h($row['nom_classe']) ?></td>
                <td><?= h(gds_filiere_code((string)$row['nom_filiere'])) ?></td>
                <td><span class="rpt-badge purple"><?= h($row['niveau']) ?></span></td>
                <td class="num"><?= (int)$row['total'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     TAB 2 — NOTES
     ════════════════════════════════════════════════════════════════════════ -->
<div id="rpt-notes" class="rpt-section <?= $activeTab==='notes'?'active':'' ?>">

  <?php if (empty($notesModules) && empty($notesRanking)): ?>
    <div class="rpt-card">
      <div class="rpt-empty">
        <i class="fa-solid fa-book-open"></i>
        <p><?= $hasFilters ? 'Aucune note trouvée pour ce filtre.' : 'Sélectionnez une filière (et optionnellement une classe) pour afficher les moyennes.' ?></p>
      </div>
    </div>
  <?php else: ?>

    <!-- Module averages -->
    <div class="rpt-card" style="margin-bottom:1.25rem;">
      <div class="rpt-section-header">
        <div class="rpt-card-title"><i class="fa-solid fa-chart-line"></i> Moyennes par module</div>
        <a class="rpt-export-btn" href="<?= rpt_export_url('notes',$selClasse,$selFiliere,$selNiveau,$selAnnee,$selModule,$selDateDe,$selDateA,$selSemestre) ?>">
          <i class="fa-solid fa-file-csv"></i> Exporter CSV
        </a>
      </div>
      <?php if (empty($notesModules)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-chart-line"></i><p>Aucune note saisie pour ce filtre.</p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th>Module</th>
                <th class="num">Coeff.</th>
                <th class="num">Moy. classe</th>
                <th class="num">Notes saisies</th>
                <th class="num">Admis (≥10)</th>
                <th class="num">Taux admis</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($notesModules as $row):
                $taux   = $row['nb_notes'] > 0 ? round($row['nb_admis'] / $row['nb_notes'] * 100) : 0;
                $moyOk  = $row['moy_classe'] !== null && $row['moy_classe'] >= 10;
                $moyBadge = $row['moy_classe'] === null ? '' : ($moyOk ? 'green' : 'red');
              ?>
              <tr>
                <td><?= h(gds_module_label((string)$row['nom_module'])) ?></td>
                <td class="num"><?= (int)$row['coefficient'] ?></td>
                <td class="num">
                  <?php if ($row['moy_classe'] !== null): ?>
                    <span class="rpt-badge <?= $moyBadge ?>"><?= number_format((float)$row['moy_classe'],2) ?>/20</span>
                  <?php else: ?><span style="color:#52525b;">—</span><?php endif; ?>
                </td>
                <td class="num"><?= (int)$row['nb_notes'] ?></td>
                <td class="num"><?= (int)$row['nb_admis'] ?></td>
                <td class="num">
                  <div style="display:flex;align-items:center;gap:.5rem;justify-content:flex-end;">
                    <div class="rpt-bar-wrap" style="min-width:60px;"><div class="rpt-bar <?= $taux>=50?'green':'red' ?>" style="width:<?= $taux ?>%"></div></div>
                    <span style="width:35px;font-size:.8rem;color:#a1a1aa;"><?= $taux ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Student ranking -->
    <?php if (!empty($notesRanking)): ?>
    <div class="rpt-card">
      <div class="rpt-card-title"><i class="fa-solid fa-ranking-star"></i> Classement général des stagiaires</div>
      <div class="rpt-table-wrap">
        <table class="rpt-table">
          <thead>
            <tr>
              <th style="width:40px;">#</th>
              <th>Stagiaire</th>
              <th>N° Inscr.</th>
              <th class="num">Moy. générale</th>
              <th class="num">Modules admis</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notesRanking as $i => $row):
              $rank = $i + 1;
              $rankCls = $rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':''));
              $moy = (float)($row['moy_gen'] ?? 0);
              $admis = $moy >= 10;
            ?>
            <tr>
              <td><span class="rpt-rank <?= $rankCls ?>"><?= $rank ?></span></td>
              <td style="font-weight:600;"><?= h($row['nom'].' '.$row['prenom']) ?></td>
              <td style="font-size:.8rem;color:#71717a;"><?= h($row['num_inscri']) ?></td>
              <td class="num">
                <span class="rpt-badge <?= $admis?'green':'red' ?>"><?= number_format($moy,2) ?>/20</span>
              </td>
              <td class="num"><?= (int)$row['mods_admis'] ?> / <?= (int)$row['total_mods'] ?></td>
              <td><span class="rpt-badge <?= $admis?'green':'red' ?>"><?= $admis?'Admis':'Ajourné' ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     TAB 3 — PAIEMENTS (director only)
     ════════════════════════════════════════════════════════════════════════ -->
<div id="rpt-paiements" class="rpt-section <?= $activeTab==='paiements'?'active':'' ?>">
  <?php if (!$isDir): ?>
    <div class="rpt-denied">
      <i class="fa-solid fa-lock"></i>
      <div style="font-size:1rem;font-weight:700;margin-bottom:.4rem;">Accès restreint</div>
      <div style="font-size:.85rem;color:#fca5a5;">Cette section est réservée au Directeur.</div>
    </div>
  <?php else: ?>

    <!-- KPIs -->
    <?php
      $totalDu      = (float)($paieKpis['total_du']      ?? 0);
      $totalPercu   = (float)($paieKpis['total_percu']   ?? 0);
      $totalRestant = (float)($paieKpis['total_restant'] ?? 0);
      $nbPaye       = (int)  ($paieKpis['nb_paye']       ?? 0);
      $nbPartiel    = (int)  ($paieKpis['nb_partiel']    ?? 0);
      $nbImpaye     = (int)  ($paieKpis['nb_impaye']     ?? 0);
      $tauxRecouvr  = $totalDu > 0 ? round($totalPercu / $totalDu * 100) : 0;
    ?>
    <div class="rpt-kpi-grid">
      <div class="rpt-kpi green"><div class="val"><?= number_format($totalPercu,0,',',' ') ?></div><div class="lbl">Perçu (MAD)</div></div>
      <div class="rpt-kpi red">  <div class="val"><?= number_format($totalRestant,0,',',' ') ?></div><div class="lbl">Restant (MAD)</div></div>
      <div class="rpt-kpi">      <div class="val"><?= number_format($totalDu,0,',',' ') ?></div><div class="lbl">Total dû (MAD)</div></div>
      <div class="rpt-kpi purple"><div class="val"><?= $tauxRecouvr ?>%</div><div class="lbl">Taux recouvrement</div></div>
      <div class="rpt-kpi green"><div class="val"><?= $nbPaye ?></div><div class="lbl">Payés</div></div>
      <div class="rpt-kpi orange"><div class="val"><?= $nbPartiel ?></div><div class="lbl">Partiels</div></div>
      <div class="rpt-kpi red">  <div class="val"><?= $nbImpaye ?></div><div class="lbl">Impayés</div></div>
    </div>

    <!-- Monthly table -->
    <div class="rpt-card">
      <div class="rpt-section-header">
        <div class="rpt-card-title"><i class="fa-solid fa-calendar-days"></i> Récapitulatif mensuel</div>
        <a class="rpt-export-btn" href="<?= rpt_export_url('paiements',$selClasse,$selFiliere,$selNiveau,$selAnnee,$selModule,$selDateDe,$selDateA) ?>">
          <i class="fa-solid fa-file-csv"></i> Exporter CSV
        </a>
      </div>
      <?php if (empty($paieMensuel)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-calendar-days"></i><p><?= $hasFilters ? 'Aucun enregistrement pour ce filtre.' : 'Appliquez un filtre pour afficher le récapitulatif mensuel.' ?></p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead>
              <tr>
                <th>Mois</th>
                <th class="num">Dû (MAD)</th>
                <th class="num">Perçu (MAD)</th>
                <th class="num">Restant (MAD)</th>
                <th class="num">Payés</th>
                <th class="num">Partiels</th>
                <th class="num">Impayés</th>
                <th class="num">Taux</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($paieMensuel as $row):
                $du       = (float)$row['du'];
                $percu    = (float)$row['percu'];
                $taux     = $du > 0 ? round($percu / $du * 100) : 0;
                $tauxCls  = $taux >= 80 ? 'green' : ($taux >= 40 ? 'orange' : 'red');
                $moisLbl  = (function(string $m): string {
                    $ms = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
                    $p  = explode('-',$m);
                    return ($ms[$p[1] ?? ''] ?? $p[1] ?? '') . ' ' . ($p[0] ?? $m);
                })((string)$row['mois_ref']);
              ?>
              <tr>
                <td style="font-weight:600;"><?= h($moisLbl) ?></td>
                <td class="num"><?= number_format($du,2,',',' ') ?></td>
                <td class="num" style="color:#34d399;"><?= number_format($percu,2,',',' ') ?></td>
                <td class="num" style="color:#f87171;"><?= number_format((float)$row['restant'],2,',',' ') ?></td>
                <td class="num" style="color:#34d399;"><?= (int)$row['nb_paye'] ?></td>
                <td class="num" style="color:#fb923c;"><?= (int)$row['nb_partiel'] ?></td>
                <td class="num" style="color:#f87171;"><?= (int)$row['nb_impaye'] ?></td>
                <td class="num"><span class="rpt-badge <?= $tauxCls ?>"><?= $taux ?>%</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     TAB 4 — ABSENCES
     ════════════════════════════════════════════════════════════════════════ -->
<div id="rpt-absences" class="rpt-section <?= $activeTab==='absences'?'active':'' ?>">

  <!-- KPIs -->
  <?php
    $totalAbsAll   = (int) array_sum(array_column($absParClasse, 'total_abs'));
    $totalJust     = (int) array_sum(array_column($absParClasse, 'justifiees'));
    $totalInjust   = $totalAbsAll - $totalJust;
    $nbClassesAbs  = count($absParClasse);
  ?>
  <div class="rpt-kpi-grid">
    <div class="rpt-kpi purple"><div class="val"><?= $totalAbsAll ?></div><div class="lbl">Total absences</div></div>
    <div class="rpt-kpi green"> <div class="val"><?= $totalJust ?></div><div class="lbl">Justifiées</div></div>
    <div class="rpt-kpi red">   <div class="val"><?= $totalInjust ?></div><div class="lbl">Injustifiées</div></div>
    <div class="rpt-kpi">       <div class="val"><?= $nbClassesAbs ?></div><div class="lbl">Classes concernées</div></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;">

    <!-- Par classe -->
    <div class="rpt-card">
      <div class="rpt-section-header">
        <div class="rpt-card-title"><i class="fa-solid fa-chalkboard-user"></i> Par classe</div>
        <a class="rpt-export-btn" href="<?= rpt_export_url('absences',$selClasse,$selFiliere,$selNiveau,$selAnnee,$selModule,$selDateDe,$selDateA) ?>">
          <i class="fa-solid fa-file-csv"></i> Exporter CSV
        </a>
      </div>
      <?php if (empty($absParClasse)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-chalkboard-user"></i><p><?= $hasFilters ? 'Aucune absence pour ce filtre.' : 'Appliquez un filtre pour afficher les données.' ?></p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead><tr><th>Classe</th><th>Filière</th><th class="num">Total</th><th class="num">Just.</th><th class="num">Injust.</th><th class="num">Absents</th></tr></thead>
            <tbody>
              <?php foreach ($absParClasse as $row): ?>
              <tr>
                <td style="font-weight:600;"><?= h($row['nom_classe']) ?></td>
                <td><?= h(gds_filiere_code((string)$row['nom_filiere'])) ?></td>
                <td class="num"><strong><?= (int)$row['total_abs'] ?></strong></td>
                <td class="num" style="color:#34d399;"><?= (int)$row['justifiees'] ?></td>
                <td class="num" style="color:#f87171;"><?= (int)$row['injustifiees'] ?></td>
                <td class="num"><?= (int)$row['nb_absents'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Top absents -->
    <div class="rpt-card">
      <div class="rpt-card-title"><i class="fa-solid fa-person-circle-exclamation"></i> Top 15 — plus absents</div>
      <?php if (empty($absTopStags)): ?>
        <div class="rpt-empty"><i class="fa-solid fa-person-circle-exclamation"></i><p><?= $hasFilters ? 'Aucune absence pour ce filtre.' : 'Appliquez un filtre pour afficher le classement.' ?></p></div>
      <?php else: ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table">
            <thead><tr><th>#</th><th>Stagiaire</th><th>Classe</th><th class="num">Total</th><th class="num">Just.</th><th class="num">Injust.</th></tr></thead>
            <tbody>
              <?php foreach ($absTopStags as $i => $row):
                $rankCls = $i===0?'gold':($i===1?'silver':($i===2?'bronze':''));
              ?>
              <tr>
                <td><span class="rpt-rank <?= $rankCls ?>"><?= $i+1 ?></span></td>
                <td>
                  <a href="stagiaires.php?search=<?= urlencode($row['num_inscri']) ?>" style="color:#e4e4e7;text-decoration:none;font-weight:600;">
                    <?= h($row['nom'].' '.$row['prenom']) ?>
                  </a>
                  <div style="font-size:.75rem;color:#52525b;"><?= h($row['num_inscri']) ?></div>
                </td>
                <td style="font-size:.8rem;color:#a1a1aa;"><?= h($row['nom_classe']) ?></td>
                <td class="num"><strong><?= (int)$row['total_abs'] ?></strong></td>
                <td class="num" style="color:#34d399;"><?= (int)$row['justifiees'] ?></td>
                <td class="num" style="color:#f87171;"><?= (int)$row['injustifiees'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     TAB 5 — HISTORIQUE (links only)
     ════════════════════════════════════════════════════════════════════════ -->
<div id="rpt-historique" class="rpt-section <?= $activeTab==='historique'?'active':'' ?>">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">

    <a href="historique_documents.php" style="text-decoration:none;">
      <div class="rpt-card" style="cursor:pointer;transition:border-color .18s;border-color:rgba(168,85,247,.2);" onmouseover="this.style.borderColor='rgba(168,85,247,.5)'" onmouseout="this.style.borderColor='rgba(168,85,247,.2)'">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa-solid fa-scroll" style="color:#a855f7;font-size:1.1rem;"></i>
          </div>
          <div>
            <div style="font-size:.95rem;font-weight:700;color:#e4e4e7;">Historique des documents</div>
            <div style="font-size:.8rem;color:#71717a;margin-top:.15rem;">Certificats, bulletins, reçus générés</div>
          </div>
        </div>
        <p style="font-size:.83rem;color:#a1a1aa;margin:0 0 1rem;">Consultez l'historique complet des documents générés pour chaque stagiaire.</p>
        <div style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#c084fc;font-weight:600;">
          Ouvrir la page <i class="fa-solid fa-arrow-right" style="font-size:.75rem;"></i>
        </div>
      </div>
    </a>

    <a href="audit_trail.php" style="text-decoration:none;">
      <div class="rpt-card" style="cursor:pointer;transition:border-color .18s;border-color:rgba(96,165,250,.2);" onmouseover="this.style.borderColor='rgba(96,165,250,.5)'" onmouseout="this.style.borderColor='rgba(96,165,250,.2)'">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(96,165,250,.12);border:1px solid rgba(96,165,250,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa-solid fa-clock-rotate-left" style="color:#60a5fa;font-size:1.1rem;"></i>
          </div>
          <div>
            <div style="font-size:.95rem;font-weight:700;color:#e4e4e7;">Journal des modifications</div>
            <div style="font-size:.8rem;color:#71717a;margin-top:.15rem;">Audit trail complet des actions</div>
          </div>
        </div>
        <p style="font-size:.83rem;color:#a1a1aa;margin:0 0 1rem;">Toutes les modifications enregistrées sur les dossiers stagiaires, notes et paiements.</p>
        <div style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#60a5fa;font-weight:600;">
          Ouvrir la page <i class="fa-solid fa-arrow-right" style="font-size:.75rem;"></i>
        </div>
      </div>
    </a>

  </div>
</div>

<script>
// ── Tab switching ─────────────────────────────────────────────────────────
function rptTab(name) {
    document.querySelectorAll('.rpt-section').forEach(function(s) { s.classList.remove('active'); });
    document.querySelectorAll('.rpt-tab').forEach(function(b) { b.classList.remove('active'); });
    const sec = document.getElementById('rpt-' + name);
    if (sec) sec.classList.add('active');
    // Find and activate the matching tab button
    document.querySelectorAll('.rpt-tab').forEach(function(b) {
        if (b.getAttribute('onclick') === "rptTab('" + name + "')") b.classList.add('active');
    });
    // Update hidden input so filter form preserves tab
    var hiddenTab = document.getElementById('rpt-tab-hidden');
    if (hiddenTab) hiddenTab.value = name;
    // Show/hide extra filter inputs
    var semestreLabel = document.getElementById('rpt-semestre-label');
    var moduleLabel  = document.getElementById('rpt-module-label');
    var dateDeLbl    = document.getElementById('rpt-date-de-label');
    var dateALbl     = document.getElementById('rpt-date-a-label');
    if (semestreLabel) semestreLabel.style.display = (name === 'notes') ? '' : 'none';
    if (moduleLabel) moduleLabel.style.display  = (name === 'notes')                          ? '' : 'none';
    if (dateDeLbl)  dateDeLbl.style.display     = (name === 'absences' || name === 'paiements') ? '' : 'none';
    if (dateALbl)   dateALbl.style.display      = (name === 'absences' || name === 'paiements') ? '' : 'none';
    // Update URL without full reload
    var url = new URL(window.location.href);
    url.searchParams.set('tab', name);
    window.history.replaceState(null, '', url.toString());
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
