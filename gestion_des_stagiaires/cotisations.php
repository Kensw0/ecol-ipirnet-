<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Gestion des Paiements';
$curPage   = 'cotisations';

// ── Tarifs par filière ────────────────────────────────────────────────────
$tarifsDefaut = [2 => 700.0, 3 => 600.0, 4 => 800.0];

function getTarif(int $idFiliere, array $tarifsDefaut): float {
    return $tarifsDefaut[$idFiliere] ?? 700.0;
}

// ── POST HANDLERS (all JSON) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    header('Content-Type: application/json');

    // ── Single payment ────────────────────────────────────────────────────
    if (isset($_POST['save_payment'])) {
        $sid         = (int)($_POST['id_stagiaire'] ?? 0);
        $moisRef     = trim((string)($_POST['mois_ref'] ?? ''));
        $statut      = trim((string)($_POST['statut_paiement'] ?? 'impayé'));
        $datePaie    = ($_POST['date_paiement'] ?? '') !== '' ? (string)$_POST['date_paiement'] : null;
        $nouveauVers = (float)($_POST['nouveau_versement'] ?? 0);
        $isAjout     = isset($_POST['is_ajout']) && (string)$_POST['is_ajout'] === '1';

        if ($sid <= 0 || !preg_match('/^\d{4}-\d{2}$/', $moisRef)) {
            echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
        }
        try {
            $stStag = $pdo->prepare('SELECT c.id_filiere FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe WHERE s.id_stagiaire=?');
            $stStag->execute([$sid]);
            $stagRow = $stStag->fetch();
            if (!$stagRow) { echo json_encode(['success' => false, 'error' => 'Stagiaire introuvable.']); exit; }
            $tarif = getTarif((int)$stagRow['id_filiere'], $tarifsDefaut);

            $stExist = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire = ? AND mois_ref = ?');
            $stExist->execute([$sid, $moisRef]);
            $existing = $stExist->fetch();

            if ($isAjout && $existing) {
                $ancienPaye = (float)($existing['montant_paye'] ?? 0);
                $newPaye    = min($tarif, $ancienPaye + $nouveauVers);
                $newRestant = max(0.0, $tarif - $newPaye);
                $newStatut  = $newRestant <= 0 ? 'payé' : ($newPaye > 0 ? 'partiel' : 'impayé');
                $newEstPaye = $newRestant <= 0 ? 1 : 0;
                $pdo->prepare("UPDATE mensualites SET montant_paye=?, montant_restant=?, statut_paiement=?, est_paye=?, date_paiement=COALESCE(?,date_paiement), marque_le=NOW() WHERE id_stagiaire=? AND mois_ref=?")
                    ->execute([$newPaye, $newRestant, $newStatut, $newEstPaye, $datePaie, $sid, $moisRef]);
            } else {
                $montantTotal   = $tarif;
                $montantPaye    = (float)($_POST['montant_paye'] ?? 0);
                if ($statut === 'payé') { $montantPaye = $montantTotal; }
                $montantPaye    = min($montantPaye, $montantTotal);
                $montantRestant = $statut === 'payé' ? 0.0 : max(0.0, $montantTotal - $montantPaye);
                $estPaye        = ($statut === 'payé') ? 1 : 0;
                // Check if record exists → UPDATE, else INSERT
                if ($existing) {
                    $pdo->prepare("UPDATE mensualites SET est_paye=?, montant_total=?, montant_paye=?, montant_restant=?, statut_paiement=?, date_paiement=?, marque_le=NOW() WHERE id_stagiaire=? AND mois_ref=?")
                        ->execute([$estPaye, $montantTotal, $montantPaye, $montantRestant, $statut, $datePaie, $sid, $moisRef]);
                } else {
                    $pdo->prepare("INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, montant_total, montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le) VALUES (?,?,?,?,?,?,0,?,?,NOW())")
                        ->execute([$sid, $moisRef, $estPaye, $montantTotal, $montantPaye, $montantRestant, $statut, $datePaie]);
                }
            }
            $stUpd = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire=? AND mois_ref=? ORDER BY id_mensualite DESC LIMIT 1');
            $stUpd->execute([$sid, $moisRef]);
            $upd = $stUpd->fetch() ?: null;
            echo json_encode(['success' => true, 'msg' => 'Paiement enregistré.', 'row' => $upd, 'tarif' => $tarif]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur enregistrement : ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Bulk mark as paid (Directeur only) ───────────────────────────────
    if (isset($_POST['bulk_mark_paid'])) {
        if (!gds_is_directeur()) { echo json_encode(['success' => false, 'error' => 'Action réservée au Directeur.']); exit; }
        $sids    = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
        $moisRef = trim((string)($_POST['mois_ref'] ?? ''));
        if (empty($sids) || !preg_match('/^\d{4}-\d{2}$/', $moisRef)) {
            echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
        }
        $updated = 0;
        try {
            $pdo->beginTransaction();
            foreach ($sids as $sid) {
                $stStag = $pdo->prepare('SELECT c.id_filiere FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe WHERE s.id_stagiaire=?');
                $stStag->execute([$sid]);
                $stagRow = $stStag->fetch();
                $tarif = getTarif((int)($stagRow['id_filiere'] ?? 0), $tarifsDefaut);
                $pdo->prepare("INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, montant_total, montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                    VALUES (?, ?, 1, ?, ?, 0, 0, 'payé', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE est_paye=1, montant_total=VALUES(montant_total), montant_paye=VALUES(montant_total), montant_restant=0, cumul_restant=0, statut_paiement='payé', date_paiement=NOW(), marque_le=NOW()")
                    ->execute([$sid, $moisRef, $tarif, $tarif]);
                $updated++;
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'updated' => $updated]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
        }
        exit;
    }

    // ── Bulk partial payment ──────────────────────────────────────────────
    if (isset($_POST['bulk_partial'])) {
        $sids      = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
        $moisRef   = trim((string)($_POST['mois_ref'] ?? ''));
        $montant   = (float)($_POST['montant_partiel'] ?? 0);
        $datePaie  = ($_POST['date_paiement'] ?? '') !== '' ? (string)$_POST['date_paiement'] : null;
        if (empty($sids) || !preg_match('/^\d{4}-\d{2}$/', $moisRef) || $montant <= 0) {
            echo json_encode(['success' => false, 'error' => 'Données invalides (montant ou sélection manquant).']); exit;
        }
        $updated = 0;
        try {
            $pdo->beginTransaction();
            foreach ($sids as $sid) {
                $stStag = $pdo->prepare('SELECT c.id_filiere FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe WHERE s.id_stagiaire=?');
                $stStag->execute([$sid]);
                $stagRow = $stStag->fetch();
                $tarif = getTarif((int)($stagRow['id_filiere'] ?? 0), $tarifsDefaut);

                $stExist = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire=? AND mois_ref=?');
                $stExist->execute([$sid, $moisRef]);
                $existing = $stExist->fetch();

                if ($existing) {
                    $ancienPaye = (float)($existing['montant_paye'] ?? 0);
                    $newPaye = min($tarif, $ancienPaye + $montant);
                    $newRestant = max(0, $tarif - $newPaye);
                    $newStatut = $newRestant <= 0 ? 'payé' : ($newPaye > 0 ? 'partiel' : 'impayé');
                    $pdo->prepare("UPDATE mensualites SET montant_paye=?, montant_restant=?, statut_paiement=?, est_paye=?, date_paiement=COALESCE(?,date_paiement), marque_le=NOW() WHERE id_stagiaire=? AND mois_ref=?")
                        ->execute([$newPaye, $newRestant, $newStatut, ($newRestant <= 0 ? 1 : 0), $datePaie, $sid, $moisRef]);
                } else {
                    $newPaye = min($tarif, $montant);
                    $newRestant = max(0, $tarif - $newPaye);
                    $newStatut = $newRestant <= 0 ? 'payé' : ($newPaye > 0 ? 'partiel' : 'impayé');
                    $pdo->prepare("INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, montant_total, montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())")
                        ->execute([$sid, $moisRef, ($newRestant <= 0 ? 1 : 0), $tarif, $newPaye, $newRestant, $newStatut, $datePaie]);
                }
                $updated++;
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'updated' => $updated]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
        }
        exit;
    }

    // ── Student full-year payment detail ─────────────────────────────────
    if (isset($_POST['get_student_payments'])) {
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        if ($sid <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalide.']); exit; }

        // Get student's class info for annee_scolaire + tarif
        $stSt = $pdo->prepare(
            'SELECT s.nom, s.prenom, s.num_inscri, c.annee_scolaire, c.id_filiere
             FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe
             WHERE s.id_stagiaire = ?'
        );
        $stSt->execute([$sid]);
        $info = $stSt->fetch();
        if (!$info) { echo json_encode(['success' => false, 'error' => 'Stagiaire introuvable.']); exit; }

        $tarif  = getTarif((int)$info['id_filiere'], $tarifsDefaut);
        $annee  = (string)$info['annee_scolaire']; // e.g. "2025/2026"
        $parts  = explode('/', $annee);
        $year1  = (int)($parts[0] ?? date('Y'));
        $year2  = (int)($parts[1] ?? ($year1 + 1));

        $moisList = [
            sprintf('%04d-09', $year1), sprintf('%04d-10', $year1),
            sprintf('%04d-11', $year1), sprintf('%04d-12', $year1),
            sprintf('%04d-01', $year2), sprintf('%04d-02', $year2),
            sprintf('%04d-03', $year2), sprintf('%04d-04', $year2),
            sprintf('%04d-05', $year2), sprintf('%04d-06', $year2),
        ];

        // Fetch all existing records for these months
        $ph = implode(',', array_fill(0, count($moisList), '?'));
        $stPay = $pdo->prepare(
            "SELECT mois_ref, montant_total, montant_paye, montant_restant,
                    statut_paiement, est_paye, date_paiement
             FROM mensualites WHERE id_stagiaire = ? AND mois_ref IN ($ph)"
        );
        $stPay->execute(array_merge([$sid], $moisList));
        $records = [];
        foreach ($stPay->fetchAll() as $r) { $records[$r['mois_ref']] = $r; }

        // Build month rows
        $rows = [];
        $totDu = 0; $totPaye = 0; $totRest = 0;
        $lastPayDate = null;
        foreach ($moisList as $m) {
            $r  = $records[$m] ?? null;
            $du   = $r ? (float)$r['montant_total']   : $tarif;
            $paye = $r ? (float)$r['montant_paye']     : 0.0;
            $rest = $r ? (float)$r['montant_restant']  : $du;
            $stat = $r ? (string)$r['statut_paiement'] : '';
            $date = $r ? $r['date_paiement']            : null;
            if ($date) $lastPayDate = $date;
            $totDu   += $du;
            $totPaye += $paye;
            $totRest += $rest;
            $rows[] = ['mois' => $m, 'du' => $du, 'paye' => $paye, 'restant' => $rest,
                       'statut' => $stat, 'date_paiement' => $date];
        }

        echo json_encode([
            'success'   => true,
            'nom'       => trim((string)$info['nom'] . ' ' . (string)$info['prenom']),
            'num_inscri'=> (string)$info['num_inscri'],
            'annee'     => $annee,
            'tarif'     => $tarif,
            'rows'      => $rows,
            'total_du'  => $totDu,
            'total_paye'=> $totPaye,
            'total_rest'=> $totRest,
            'last_pay_date' => $lastPayDate,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue.']); exit;
}

// ── FILTER PARAMS ─────────────────────────────────────────────────────────
$selAnnee   = trim((string)($_GET['annee']      ?? ''));
$selFiliere = (int)($_GET['id_filiere'] ?? 0);
$selNiveau  = trim((string)($_GET['niveau']     ?? ''));
$selClasse  = (int)($_GET['id_classe']  ?? 0);
$selMois    = trim((string)($_GET['mois']       ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $selMois)) $selMois = date('Y-m');

// ── CASCADE DATA ──────────────────────────────────────────────────────────
$allAnnees   = $pdo->query("SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);
if ($selAnnee === '') { $selAnnee = $_SESSION['global_annee_scolaire'] ?? ($allAnnees[0] ?? ''); }
$allFilieres = $pdo->query("SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere")->fetchAll();
if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }

$allNiveaux = [];
if ($selFiliere > 0 && $selAnnee !== '') {
    $st = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $st->execute([$selFiliere, $selAnnee]); $allNiveaux = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allNiveaux) && !in_array($selNiveau, $allNiveaux, true)) { $selNiveau = $allNiveaux[0]; }
}
$allClasses = [];
if ($selFiliere > 0 && $selAnnee !== '' && $selNiveau !== '') {
    $st = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $st->execute([$selFiliere, $selAnnee, $selNiveau]); $allClasses = $st->fetchAll();
    $_vcids = array_map('intval', array_column($allClasses, 'id_classe'));
    if (!empty($allClasses) && !in_array($selClasse, $_vcids, true)) { $selClasse = (int)$allClasses[0]['id_classe']; }
}

// ── STUDENT + PAYMENT DATA ────────────────────────────────────────────────
$stagiaires = []; $classeInfo = null; $tarifClasse = 700.0;
if ($selClasse > 0) {
    $r = $pdo->prepare("SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau, c.id_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $r->execute([$selClasse]); $classeInfo = $r->fetch();
    if ($classeInfo) { $tarifClasse = getTarif((int)$classeInfo['id_filiere'], $tarifsDefaut); }

    $st = $pdo->prepare("
        SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
               m.montant_total, m.montant_paye, m.montant_restant, m.statut_paiement, m.est_paye, m.date_paiement
        FROM stagiaires s
        LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ?
        WHERE s.id_classe = ?
        ORDER BY s.nom, s.prenom
    ");
    $st->execute([$selMois, $selClasse]);
    $stagiaires = $st->fetchAll();
}

// ── SUMMARY STATS ─────────────────────────────────────────────────────────
$totalDu      = 0; $totalPaye = 0; $totalRestant = 0;
$nbPaye = 0; $nbPartiel = 0; $nbImpaye = 0;
foreach ($stagiaires as $s) {
    $mTotal  = $s['montant_total']   !== null ? (float)$s['montant_total']   : $tarifClasse;
    $mPaye   = $s['montant_paye']    !== null ? (float)$s['montant_paye']    : 0;
    $mRest   = $s['montant_restant'] !== null ? (float)$s['montant_restant'] : $mTotal;
    $sp      = (string)($s['statut_paiement'] ?? '');
    $isPaye  = (int)($s['est_paye'] ?? 0) === 1 || $sp === 'payé';
    $isPartiel = $sp === 'partiel';
    $totalDu   += $mTotal;
    $totalPaye += $mPaye;
    $totalRestant += $mRest;
    if ($isPaye) $nbPaye++;
    elseif ($isPartiel) $nbPartiel++;
    else $nbImpaye++;
}

// ── BUILD MONTH LIST FOR FILTER ───────────────────────────────────────────
$moisOptions = [];
for ($i = 11; $i >= 0; $i--) {
    $moisOptions[] = date('Y-m', strtotime("-$i months"));
}

require __DIR__ . '/includes/header.php';
?>

<style>
.cot-filter-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
.cot-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;align-items:end;}
.cot-filter-grid label{display:flex;flex-direction:column;gap:0.35rem;font-size:0.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.cot-filter-grid select,.cot-filter-grid input[type="month"]{background:#09090b;border:1px solid rgba(255,255,255,0.12);color:#e4e4e7;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.9rem;width:100%;color-scheme:dark;}
.cot-filter-grid select:disabled{opacity:0.4;cursor:not-allowed;}
.cot-filter-grid select:focus,.cot-filter-grid input:focus{outline:none;border-color:rgba(168,85,247,0.5);box-shadow:0 0 0 2px rgba(168,85,247,0.15);}
.cot-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;}
.cot-stat-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1rem 1.25rem;text-align:center;}
.cot-stat-val{font-size:1.6rem;font-weight:800;line-height:1.1;}
.cot-stat-lbl{font-size:0.72rem;color:#71717a;margin-top:0.35rem;text-transform:uppercase;letter-spacing:.05em;}
.cot-table-wrap{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;overflow:hidden;}
.cot-table-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.cot-table{width:100%;border-collapse:collapse;}
.cot-table th{padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#71717a;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);}
.cot-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:.88rem;color:#e4e4e7;vertical-align:middle;}
.cot-table tbody tr:hover td{background:rgba(168,85,247,0.06);}
.cot-table .cb-col{width:40px;text-align:center;}
.cot-table input[type="checkbox"]{accent-color:#a855f7;width:16px;height:16px;cursor:pointer;}
.badge-cot{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;white-space:nowrap;}
.badge-cot.paye{background:rgba(52,211,153,.13);color:#34d399;border:1px solid rgba(52,211,153,.3);}
.badge-cot.partiel{background:rgba(251,146,60,.13);color:#fb923c;border:1px solid rgba(251,146,60,.3);}
.badge-cot.impaye{background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.3);}
.badge-cot.aucun{background:rgba(113,113,122,.13);color:#a1a1aa;border:1px solid rgba(113,113,122,.2);}
.btn-cot{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;}
.btn-cot.primary{background:#a855f7;color:#fff;}.btn-cot.primary:hover{background:#9333ea;}
.btn-cot.ghost{background:rgba(168,85,247,.1);color:#c084fc;border:1px solid rgba(168,85,247,.25);}.btn-cot.ghost:hover{background:rgba(168,85,247,.2);}
.btn-cot.success{background:rgba(52,211,153,.15);color:#34d399;border:1px solid rgba(52,211,153,.3);}.btn-cot.success:hover{background:rgba(52,211,153,.25);}
.btn-cot.danger{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}.btn-cot.danger:hover{background:rgba(239,68,68,.25);}
.btn-cot.sm{padding:3px 9px;font-size:.75rem;}
.bulk-bar{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:14px;padding:.85rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:900;opacity:0;transition:all .25s;pointer-events:none;flex-wrap:wrap;min-width:460px;}
.bulk-bar.visible{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:all;}
.bulk-count{font-size:.85rem;font-weight:700;color:#c084fc;white-space:nowrap;}
.bulk-sep{width:1px;height:24px;background:rgba(255,255,255,.1);}
.empty-state{text-align:center;padding:3.5rem 2rem;color:#52525b;}
.empty-state i{font-size:2.5rem;margin-bottom:1rem;display:block;color:#3f3f46;}
.cot-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:9999;display:none;align-items:center;justify-content:center;}
.cot-modal-card{background:#18181b;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:0;width:min(480px,95vw);display:flex;flex-direction:column;overflow:hidden;}
.cot-modal-header{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.07);}
.cot-modal-header h3{margin:0;font-size:1.05rem;font-weight:700;color:#f4f4f5;}
.cot-modal-body{padding:1.5rem;display:flex;flex-direction:column;gap:1rem;}
.cot-modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:flex-end;gap:.75rem;}
.cot-info-row{display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.04);border-radius:8px;padding:.6rem .9rem;font-size:.87rem;}
.cot-info-row .label{color:#71717a;}
.cot-info-row .val{font-weight:700;color:#e4e4e7;}
.cot-form-group{display:flex;flex-direction:column;gap:.3rem;}
.cot-form-group label{font-size:.76rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.cot-form-group input,.cot-form-group select{background:#09090b;border:1px solid rgba(255,255,255,.1);color:#e4e4e7;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;width:100%;box-sizing:border-box;}
.cot-form-group input:focus,.cot-form-group select:focus{outline:none;border-color:rgba(168,85,247,.5);}
.ajout-banner{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:.75rem 1rem;font-size:.84rem;display:flex;flex-direction:column;gap:.4rem;}
.gds-toast{position:fixed;top:1.25rem;right:1.25rem;z-index:99998;border-radius:10px;padding:.8rem 1.25rem;font-weight:600;font-size:.88rem;box-shadow:0 6px 24px rgba(0,0,0,.5);border:1px solid;max-width:360px;line-height:1.4;animation:toastIn .2s ease;}
@keyframes toastIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.gds-toast.success{background:#18181b;border-color:rgba(34,197,94,.4);color:#86efac;}
.gds-toast.error{background:#18181b;border-color:rgba(239,68,68,.4);color:#fca5a5;}
.gds-toast.info{background:#18181b;border-color:rgba(168,85,247,.4);color:#c084fc;}
.gds-confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:99999;display:none;align-items:center;justify-content:center;}
.gds-confirm-card{background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:16px;padding:2rem 2rem 1.5rem;width:min(380px,92vw);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.6);}
.gds-confirm-icon{font-size:2rem;margin-bottom:.75rem;}
.gds-confirm-msg{color:#e4e4e7;font-size:.95rem;margin:0 0 1.5rem;line-height:1.55;}
.gds-confirm-btns{display:flex;gap:.75rem;justify-content:center;}
.page-header-cot{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.page-header-cot h1{font-size:1.6rem;font-weight:800;color:#f4f4f5;margin:0;}
.page-header-cot p{margin:.3rem 0 0;font-size:.88rem;color:#71717a;}
.amount-green{color:#34d399;font-weight:700;}
.amount-red{color:#f87171;font-weight:700;}
.amount-gray{color:#71717a;}

/* ── Student payment drawer ── */
.pmt-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10000;display:none;backdrop-filter:blur(2px);}
.pmt-overlay.open{display:block;}
.pmt-drawer{position:fixed;top:0;right:0;height:100%;width:min(480px,100vw);background:#111113;border-left:1px solid rgba(255,255,255,.08);z-index:10001;transform:translateX(100%);transition:transform .32s cubic-bezier(.16,1,.3,1);display:flex;flex-direction:column;overflow:hidden;box-shadow:-12px 0 48px rgba(0,0,0,.6);}
.pmt-drawer.open{transform:translateX(0);}

/* head */
.pmt-drawer-head{display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0;background:rgba(168,85,247,.06);}
.pmt-drawer-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff;flex-shrink:0;margin-right:.85rem;}
.pmt-drawer-title{font-size:1rem;font-weight:700;color:#f4f4f5;margin:0 0 .15rem;line-height:1.2;}
.pmt-drawer-sub{font-size:.74rem;color:#71717a;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;}
.pmt-drawer-sub .pip{width:3px;height:3px;border-radius:50%;background:#3f3f46;display:inline-block;}

/* body */
.pmt-drawer-body{flex:1;overflow-y:auto;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.55rem;}
.pmt-drawer-body::-webkit-scrollbar{width:4px;}.pmt-drawer-body::-webkit-scrollbar-track{background:transparent;}.pmt-drawer-body::-webkit-scrollbar-thumb{background:#3f3f46;border-radius:4px;}

/* month cards */
.pmt-card{background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:.85rem 1rem;transition:border-color .15s;}
.pmt-card:hover{border-color:rgba(168,85,247,.3);}
.pmt-card.is-current{border-color:rgba(168,85,247,.4);background:rgba(168,85,247,.06);}
.pmt-card.is-paye{border-color:rgba(52,211,153,.2);}
.pmt-card.is-impaye{border-color:rgba(248,113,113,.2);}
.pmt-card.is-partiel{border-color:rgba(251,146,60,.2);}
.pmt-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;}
.pmt-card-month{font-size:.88rem;font-weight:700;color:#e4e4e7;display:flex;align-items:center;gap:.4rem;}
.pmt-card-right{display:flex;align-items:center;gap:.5rem;}
.pmt-badge{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:20px;font-size:.69rem;font-weight:700;white-space:nowrap;}
.pmt-badge.paye{background:rgba(52,211,153,.13);color:#34d399;border:1px solid rgba(52,211,153,.25);}
.pmt-badge.partiel{background:rgba(251,146,60,.13);color:#fb923c;border:1px solid rgba(251,146,60,.25);}
.pmt-badge.impaye{background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.25);}
.pmt-badge.aucun{background:rgba(113,113,122,.1);color:#71717a;border:1px solid rgba(113,113,122,.18);}
.pmt-edit-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:7px;font-size:.72rem;font-weight:600;border:1px solid rgba(168,85,247,.3);background:rgba(168,85,247,.08);color:#c084fc;cursor:pointer;transition:all .15s;white-space:nowrap;}
.pmt-edit-btn:hover{background:rgba(168,85,247,.2);border-color:rgba(168,85,247,.5);}

/* progress bar */
.pmt-progress-wrap{position:relative;height:5px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;margin-bottom:.65rem;}
.pmt-progress-fill{position:absolute;left:0;top:0;height:100%;border-radius:99px;transition:width .5s ease;}

/* amounts row */
.pmt-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;}
.pmt-amount-item{display:flex;flex-direction:column;gap:.1rem;}
.pmt-amount-lbl{font-size:.64rem;text-transform:uppercase;letter-spacing:.06em;color:#52525b;font-weight:600;}
.pmt-amount-val{font-size:.82rem;font-weight:700;font-variant-numeric:tabular-nums;}
.pmt-date-line{font-size:.71rem;color:#52525b;margin-top:.5rem;display:flex;align-items:center;gap:.3rem;}

/* footer */
.pmt-drawer-foot{flex-shrink:0;border-top:1px solid rgba(255,255,255,.07);padding:1rem 1.1rem;background:#111113;}
.pmt-summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.7rem;}
.pmt-sum-card{background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:.65rem .75rem;text-align:center;}
.pmt-sum-val{font-size:.95rem;font-weight:800;line-height:1.15;margin-bottom:.15rem;font-variant-numeric:tabular-nums;}
.pmt-sum-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:#52525b;font-weight:600;}
.pmt-last-pay{font-size:.76rem;color:#71717a;text-align:center;display:flex;align-items:center;justify-content:center;gap:.35rem;}

/* name trigger */
.btn-stag-name{background:none;border:none;color:#e4e4e7;font-weight:600;cursor:pointer;padding:0;text-align:left;text-decoration:underline;text-underline-offset:2px;text-decoration-color:rgba(168,85,247,.4);transition:color .15s;}
.btn-stag-name:hover{color:#c084fc;}
.btn-eye{background:none;border:none;color:#52525b;cursor:pointer;padding:0 0 0 6px;font-size:.82rem;transition:color .15s;vertical-align:middle;}
.btn-eye:hover{color:#a855f7;}

/* skeleton */
.pmt-skeleton{display:flex;flex-direction:column;gap:.55rem;}
.pmt-skel-row{height:96px;background:rgba(255,255,255,.04);border-radius:12px;animation:skel-pulse 1.3s ease-in-out infinite;}
@keyframes skel-pulse{0%,100%{opacity:.4;}50%{opacity:.8;}}
</style>

<div style="max-width:1200px;margin:0 auto;padding:1.5rem;">

  <!-- Page header -->
  <div class="page-header-cot">
    <div>
      <h1><i class="fa-solid fa-money-bill-transfer" style="color:#a855f7;margin-right:.5rem;"></i>Gestion des Paiements</h1>
      <p>Système centralisé de gestion des paiements par classe</p>
    </div>
  </div>

  <!-- Filter card -->
  <div class="cot-filter-card">
    <form method="get" id="filter-form">
      <div class="cot-filter-grid">
        <label>
          Année scolaire
          <select name="annee" onchange="this.form.submit()">
            <?php foreach ($allAnnees as $a): ?>
              <option value="<?= h($a) ?>" <?= $selAnnee === $a ? 'selected' : '' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Filière
          <select name="id_filiere" onchange="this.form.submit()">
            <option value="0">— Choisir —</option>
            <?php foreach ($allFilieres as $f): ?>
              <option value="<?= (int)$f['id_filiere'] ?>" <?= $selFiliere === (int)$f['id_filiere'] ? 'selected' : '' ?>><?= h($f['nom_filiere']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Niveau
          <select name="niveau" onchange="this.form.submit()" <?= empty($allNiveaux) ? 'disabled' : '' ?>>
            <?php if (empty($allNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
            <?php foreach ($allNiveaux as $n): ?>
              <option value="<?= h($n) ?>" <?= $selNiveau === $n ? 'selected' : '' ?>><?= h($n) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Classe
          <select name="id_classe" onchange="this.form.submit()" <?= empty($allClasses) ? 'disabled' : '' ?>>
            <?php if (empty($allClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
            <?php foreach ($allClasses as $c): ?>
              <option value="<?= (int)$c['id_classe'] ?>" <?= $selClasse === (int)$c['id_classe'] ? 'selected' : '' ?>><?= h($c['nom_classe']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Mois
          <input type="month" name="mois" value="<?= h($selMois) ?>" onchange="this.form.submit()">
        </label>
      </div>
    </form>
  </div>

  <?php if ($selClasse > 0 && $classeInfo): ?>
  <!-- Summary stats + print button row -->
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div class="cot-stats-row" style="grid-template-columns:repeat(2,minmax(150px,220px));margin-bottom:0;flex:0 0 auto;">
      <div class="cot-stat-card">
        <div class="cot-stat-val" style="color:#e4e4e7;"><?= count($stagiaires) ?></div>
        <div class="cot-stat-lbl">Total stagiaires</div>
      </div>
      <div class="cot-stat-card" style="border-color:rgba(248,113,113,.25);">
        <div class="cot-stat-val" style="color:#f87171;"><?= $nbImpaye ?></div>
        <div class="cot-stat-lbl">Impayés</div>
      </div>
    </div>
    <?php if ($nbImpaye > 0): ?>
    <?php
      $printUrl = 'print_liste_impayes.php?' . http_build_query([
        'id_filiere'    => $selFiliere,
        'id_classe'     => $selClasse,
        'annee_scolaire'=> $selAnnee,
        'niveau'        => $selNiveau,
        'mois'          => $selMois,
        'impaye'        => '1',
        'auto'          => '1',
      ]);
    ?>
    <a href="<?= h($printUrl) ?>" target="_blank"
       style="display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1.2rem;background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.3);border-radius:9px;font-size:.85rem;font-weight:700;text-decoration:none;white-space:nowrap;transition:background .15s;"
       onmouseover="this.style.background='rgba(248,113,113,.22)'" onmouseout="this.style.background='rgba(248,113,113,.13)'">
        <i class="fa-solid fa-print"></i> Imprimer liste des impayés
    </a>
    <?php endif; ?>
  </div>

  <!-- Table -->
  <div class="cot-table-wrap">
    <div class="cot-table-header">
      <div>
        <strong style="color:#f4f4f5;"><?= h($classeInfo['nom_classe']) ?></strong>
        <span style="color:#71717a;font-size:.85rem;margin-left:.75rem;"><?= h($classeInfo['nom_filiere']) ?> · <?= h($classeInfo['niveau']) ?> · <?= h($classeInfo['annee_scolaire']) ?></span>
        <span style="background:rgba(168,85,247,.15);color:#c084fc;border:1px solid rgba(168,85,247,.3);border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;margin-left:.75rem;">
          <?= h(date('M Y', strtotime($selMois . '-01'))) ?> · Tarif <?= number_format($tarifClasse, 0, ',', ' ') ?> MAD
        </span>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;">
        <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;color:#a1a1aa;cursor:pointer;">
          <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" style="accent-color:#a855f7;width:15px;height:15px;">
          Tout sélectionner
        </label>
      </div>
    </div>

    <?php if (empty($stagiaires)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-users-slash"></i>
        <p>Aucun stagiaire dans cette classe.</p>
      </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="cot-table">
      <thead>
        <tr>
          <th class="cb-col"><i class="fa-solid fa-check-square" style="color:#52525b;"></i></th>
          <th>Stagiaire</th>
          <th style="text-align:right;">Montant dû</th>
          <th style="text-align:right;">Payé</th>
          <th style="text-align:right;">Restant</th>
          <th style="text-align:center;">Statut</th>
          <th style="text-align:center;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($stagiaires as $s):
        $sp       = (string)($s['statut_paiement'] ?? '');
        $isPaye   = (int)($s['est_paye'] ?? 0) === 1 || $sp === 'payé';
        $isPartiel= $sp === 'partiel';
        $isImpaye = !$isPaye && !$isPartiel;
        $mTotal   = $s['montant_total']   !== null ? (float)$s['montant_total']   : $tarifClasse;
        $mPaye    = $s['montant_paye']    !== null ? (float)$s['montant_paye']    : 0.0;
        $mRest    = $s['montant_restant'] !== null ? (float)$s['montant_restant'] : $mTotal;
        $hasRecord = $s['statut_paiement'] !== null;
        if (!$hasRecord) { $mPaye = 0; $mRest = $mTotal; }
        $statusClass = $isPaye ? 'paye' : ($isPartiel ? 'partiel' : ($hasRecord ? 'impaye' : 'aucun'));
        $statusLabel = $isPaye ? 'Payé' : ($isPartiel ? 'Partiel' : ($hasRecord ? 'Impayé' : 'Aucun'));
        $rowData = json_encode([
          'id_stagiaire' => (int)$s['id_stagiaire'],
          'nom'          => trim($s['nom'] . ' ' . $s['prenom']),
          'mois_ref'     => $selMois,
          'tarif'        => $tarifClasse,
          'montant_paye' => $mPaye,
          'montant_restant' => $mRest,
          'has_record'   => $hasRecord,
          'statut'       => $sp,
        ]);
      ?>
        <tr data-sid="<?= (int)$s['id_stagiaire'] ?>" id="row-<?= (int)$s['id_stagiaire'] ?>"<?= $isImpaye ? ' style="background:rgba(255,60,60,.07);"' : '' ?>>
          <td class="cb-col">
            <input type="checkbox" class="row-cb" value="<?= (int)$s['id_stagiaire'] ?>" onchange="updateBulkBar()">
          </td>
          <td>
            <button type="button" class="btn-stag-name" onclick="openPmtDrawer(<?= (int)$s['id_stagiaire'] ?>)"><?= h(trim($s['nom'].' '.$s['prenom'])) ?></button>
            <button type="button" class="btn-eye" onclick="openPmtDrawer(<?= (int)$s['id_stagiaire'] ?>)" title="Voir historique annuel"><i class="fa-solid fa-eye"></i></button>
            <div style="font-size:.76rem;color:#71717a;"><?= h((string)($s['num_inscri'] ?? '')) ?></div>
          </td>
          <td style="text-align:right;" class="col-du"><?= number_format($mTotal, 2) ?> MAD</td>
          <td style="text-align:right;" class="col-paye <?= $mPaye > 0 ? 'amount-green' : 'amount-gray' ?>"><?= number_format($mPaye, 2) ?> MAD</td>
          <td style="text-align:right;" class="col-restant <?= $mRest > 0 ? 'amount-red' : 'amount-gray' ?>"><?= number_format($mRest, 2) ?> MAD</td>
          <td style="text-align:center;" class="col-statut">
            <span class="badge-cot <?= $statusClass ?>"><?= $statusLabel ?></span>
          </td>
          <td style="text-align:center;">
            <button type="button" class="btn-cot ghost btn-cot sm" onclick='openPayModal(<?= htmlspecialchars($rowData, ENT_QUOTES) ?>)'>
              <i class="fa-solid fa-money-bill-wave"></i> Paiement
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03);">
          <td colspan="2" style="font-weight:700;color:#e4e4e7;padding:.65rem 1rem;">TOTAUX</td>
          <td style="text-align:right;color:#a1a1aa;font-weight:700;padding:.65rem 1rem;"><?= number_format($totalDu, 2) ?> MAD</td>
          <td style="text-align:right;font-weight:700;color:#34d399;padding:.65rem 1rem;"><?= number_format($totalPaye, 2) ?> MAD</td>
          <td style="text-align:right;font-weight:700;color:<?= $totalRestant > 0 ? '#f87171' : '#71717a' ?>;padding:.65rem 1rem;"><?= number_format($totalRestant, 2) ?> MAD</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($selClasse === 0 && $selFiliere > 0): ?>
  <div class="empty-state" style="background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:14px;">
    <i class="fa-solid fa-arrow-pointer"></i>
    <p>Sélectionnez un niveau et une classe pour afficher les cotisations.</p>
  </div>
  <?php elseif ($selFiliere === 0): ?>
  <div class="empty-state" style="background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:14px;">
    <i class="fa-solid fa-filter"></i>
    <p>Sélectionnez une filière pour commencer.</p>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<!-- ── BULK ACTION BAR ──────────────────────────────────────────────────── -->
<div id="bulk-bar" class="bulk-bar">
  <span class="bulk-count"><i class="fa-solid fa-users"></i> <span id="bulk-count-label">0</span> sélectionné(s)</span>
  <div class="bulk-sep"></div>
  <?php if (gds_is_directeur()): ?>
  <button type="button" class="btn-cot success" onclick="doBulkMarkPaid()">
    <i class="fa-solid fa-circle-check"></i> Marquer payé
  </button>
  <?php endif; ?>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <input type="number" id="bulk-montant" placeholder="Montant (MAD)" min="0" step="0.01"
           style="background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;width:140px;">
    <button type="button" class="btn-cot primary" onclick="doBulkPartial()">
      <i class="fa-solid fa-coins"></i> Paiement partiel
    </button>
  </div>
</div>

<!-- ── SINGLE PAYMENT MODAL ─────────────────────────────────────────────── -->
<div id="modal-pay" class="cot-modal-overlay">
  <div class="cot-modal-card">
    <div class="cot-modal-header">
      <h3><i class="fa-solid fa-money-bill-wave" style="color:#a855f7;margin-right:.4rem;"></i><span id="pay-modal-title">Enregistrer un paiement</span></h3>
      <button type="button" onclick="closePayModal()" style="background:none;border:none;color:#71717a;cursor:pointer;font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cot-modal-body">
      <!-- Context info -->
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <div class="cot-info-row"><span class="label">Mois</span><span class="val" id="pay-info-mois">—</span></div>
        <div class="cot-info-row" id="pay-info-existant-row" style="display:none;">
          <span class="label">Déjà payé</span><span class="val amount-green" id="pay-info-paye">—</span>
        </div>
        <div class="cot-info-row" id="pay-info-restant-row" style="display:none;">
          <span class="label">Restant dû</span><span class="val amount-red" id="pay-info-restant">—</span>
        </div>
      </div>

      <!-- Mode: new record -->
      <div id="pay-section-new">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="cot-form-group">
            <label>Statut</label>
            <select id="pay-statut" onchange="payUpdateFields()">
              <option value="payé">✅ Payé (complet)</option>
              <option value="partiel">⚠️ Partiel</option>
              <option value="impayé">❌ Impayé</option>
            </select>
          </div>
          <div class="cot-form-group">
            <label>Date paiement</label>
            <input type="date" id="pay-date" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="cot-form-group" id="pay-montant-wrap">
          <label>Montant payé (MAD)</label>
          <input type="number" id="pay-montant-paye" min="0" step="0.01" placeholder="0.00" oninput="payCapMontant()">
        </div>
      </div>

      <!-- Mode: add to existing -->
      <div id="pay-section-ajout" style="display:none;">
        <div class="ajout-banner">
          <div style="color:#c7d2fe;font-weight:600;font-size:.84rem;"><i class="fa-solid fa-circle-info"></i> Ajout sur paiement existant</div>
          <div style="color:#a1a1aa;font-size:.82rem;">Un paiement existe déjà pour ce mois. Entrez le montant supplémentaire à ajouter.</div>
        </div>
        <div class="cot-form-group" style="margin-top:.5rem;">
          <label>Nouveau versement (MAD)</label>
          <input type="number" id="pay-nouveau-versement" min="0" step="0.01" placeholder="0.00" oninput="payPreviewAjout()">
        </div>
        <div id="pay-ajout-preview" style="display:none;background:rgba(0,0,0,.2);border-radius:8px;padding:.6rem .9rem;font-size:.82rem;color:#a1a1aa;margin-top:.25rem;"></div>
        <div class="cot-form-group" style="margin-top:.5rem;">
          <label>Date paiement</label>
          <input type="date" id="pay-date-ajout" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
    </div>
    <div class="cot-modal-footer">
      <button type="button" class="btn-cot ghost" onclick="closePayModal()">Annuler</button>
      <button type="button" class="btn-cot primary" id="pay-save-btn" onclick="savePayment()">
        <i class="fa-solid fa-floppy-disk"></i> Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- ── STUDENT PAYMENT DRAWER ─────────────────────────────────────────────── -->
<div id="pmt-overlay" class="pmt-overlay" onclick="closePmtDrawer()"></div>
<div id="pmt-drawer" class="pmt-drawer">
  <div class="pmt-drawer-head">
    <div style="display:flex;align-items:center;">
      <div class="pmt-drawer-avatar" id="pmt-drawer-avatar">?</div>
      <div>
        <div class="pmt-drawer-title" id="pmt-drawer-name">Chargement…</div>
        <div class="pmt-drawer-sub" id="pmt-drawer-sub"></div>
      </div>
    </div>
    <button type="button" onclick="closePmtDrawer()" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#71717a;cursor:pointer;font-size:.9rem;padding:.35rem .55rem;border-radius:8px;transition:all .15s;" onmouseover="this.style.color='#f4f4f5'" onmouseout="this.style.color='#71717a'"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="pmt-drawer-body" id="pmt-drawer-body">
    <div class="pmt-skeleton">
      <?php for ($i=0;$i<10;$i++): ?><div class="pmt-skel-row"></div><?php endfor; ?>
    </div>
  </div>
  <div class="pmt-drawer-foot" id="pmt-drawer-foot" style="display:none;">
    <div class="pmt-summary-grid">
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-du" style="color:#a1a1aa;">—</div>
        <div class="pmt-sum-lbl">Total Dû</div>
      </div>
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-paye" style="color:#34d399;">—</div>
        <div class="pmt-sum-lbl">Total Payé</div>
      </div>
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-rest">—</div>
        <div class="pmt-sum-lbl">Solde Restant</div>
      </div>
    </div>
    <div class="pmt-last-pay" id="pmt-last-pay"></div>
  </div>
</div>

<!-- ── CUSTOM CONFIRM ─────────────────────────────────────────────────────── -->
<div id="gds-confirm-overlay" class="gds-confirm-overlay">
  <div class="gds-confirm-card">
    <div class="gds-confirm-icon">⚠️</div>
    <p class="gds-confirm-msg" id="gds-confirm-msg">Confirmer l'action ?</p>
    <div class="gds-confirm-btns">
      <button class="btn-cot ghost" onclick="gdsConfirmResolve(false)"><i class="fa-solid fa-xmark"></i> Annuler</button>
      <button class="btn-cot primary" onclick="gdsConfirmResolve(true)"><i class="fa-solid fa-check"></i> Confirmer</button>
    </div>
  </div>
</div>

<script>
const GDS_CSRF         = <?= json_encode(csrf_token()) ?>;
const GDS_IS_DIRECTEUR = <?= json_encode(gds_is_directeur()) ?>;
const SEL_MOIS         = <?= json_encode($selMois) ?>;
const TARIF_CLASSE     = <?= json_encode($tarifClasse) ?>;

// ── Checkbox helpers ─────────────────────────────────────────────────────
function getSelectedIds() {
  return [...document.querySelectorAll('.row-cb:checked')].map(cb => parseInt(cb.value));
}
function toggleSelectAll(masterCb) {
  document.querySelectorAll('.row-cb').forEach(cb => cb.checked = masterCb.checked);
  updateBulkBar();
}
function updateBulkBar() {
  const ids = getSelectedIds();
  const bar = document.getElementById('bulk-bar');
  document.getElementById('bulk-count-label').textContent = ids.length;
  bar.classList.toggle('visible', ids.length > 0);
  // sync master checkbox
  const all = document.querySelectorAll('.row-cb');
  const masterCb = document.getElementById('select-all');
  if (masterCb) masterCb.checked = all.length > 0 && ids.length === all.length;
}

// ── Payment modal ────────────────────────────────────────────────────────
let _payCurrentRow = null;

function openPayModal(rowData) {
  _payCurrentRow = rowData;
  const hasRecord = rowData.has_record;
  document.getElementById('pay-modal-title').textContent = 'Paiement — ' + rowData.nom;
  document.getElementById('pay-info-mois').textContent = formatMois(rowData.mois_ref);

  if (hasRecord) {
    // Ajout mode
    document.getElementById('pay-section-new').style.display  = 'none';
    document.getElementById('pay-section-ajout').style.display = '';
    document.getElementById('pay-info-existant-row').style.display = '';
    document.getElementById('pay-info-restant-row').style.display  = '';
    document.getElementById('pay-info-paye').textContent    = fmtAmt(rowData.montant_paye);
    document.getElementById('pay-info-restant').textContent = fmtAmt(rowData.montant_restant);
    document.getElementById('pay-nouveau-versement').value = '';
    document.getElementById('pay-nouveau-versement').max = rowData.montant_restant;
    document.getElementById('pay-ajout-preview').style.display = 'none';
  } else {
    // New record mode
    document.getElementById('pay-section-new').style.display   = '';
    document.getElementById('pay-section-ajout').style.display = 'none';
    document.getElementById('pay-info-existant-row').style.display = 'none';
    document.getElementById('pay-info-restant-row').style.display  = 'none';
    document.getElementById('pay-statut').value = 'payé';
    payUpdateFields();
  }
  document.getElementById('modal-pay').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('modal-pay').style.display = 'none';
  _payCurrentRow = null;
}

function payUpdateFields() {
  const statut = document.getElementById('pay-statut').value;
  const mWrap  = document.getElementById('pay-montant-wrap');
  if (statut === 'payé' || statut === 'impayé') {
    mWrap.style.display = 'none';
  } else {
    mWrap.style.display = '';
  }
}

function payCapMontant() {
  const inp = document.getElementById('pay-montant-paye');
  if (parseFloat(inp.value) > TARIF_CLASSE) inp.value = TARIF_CLASSE;
}

function payPreviewAjout() {
  if (!_payCurrentRow) return;
  const v = parseFloat(document.getElementById('pay-nouveau-versement').value) || 0;
  const newPaye   = Math.min(TARIF_CLASSE, _payCurrentRow.montant_paye + v);
  const newRestant= Math.max(0, TARIF_CLASSE - newPaye);
  const preview   = document.getElementById('pay-ajout-preview');
  if (v > 0) {
    preview.style.display = '';
    const newStatut = newRestant <= 0 ? '✅ Payé' : (newPaye > 0 ? '⚠️ Partiel' : '❌ Impayé');
    preview.innerHTML = `Nouveau total payé : <strong style="color:#34d399;">${fmtAmt(newPaye)}</strong> · Restant : <strong style="color:${newRestant>0?'#f87171':'#34d399'};">${fmtAmt(newRestant)}</strong> · Statut → <strong>${newStatut}</strong>`;
  } else {
    preview.style.display = 'none';
  }
}

function savePayment() {
  if (!_payCurrentRow) return;
  const fd = new FormData();
  fd.append('save_payment', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_stagiaire', _payCurrentRow.id_stagiaire);
  fd.append('mois_ref', _payCurrentRow.mois_ref);

  if (_payCurrentRow.has_record) {
    // Ajout mode
    const vers = parseFloat(document.getElementById('pay-nouveau-versement').value) || 0;
    if (vers <= 0) { showToast('Entrez un montant à ajouter.', 'error'); return; }
    fd.append('is_ajout', '1');
    fd.append('nouveau_versement', vers);
    fd.append('statut_paiement', 'partiel');
    fd.append('date_paiement', document.getElementById('pay-date-ajout').value);
  } else {
    // New record
    const statut = document.getElementById('pay-statut').value;
    fd.append('statut_paiement', statut);
    fd.append('date_paiement', document.getElementById('pay-date').value);
    if (statut === 'partiel') {
      const mp = parseFloat(document.getElementById('pay-montant-paye').value) || 0;
      if (mp <= 0) { showToast('Entrez un montant payé.', 'error'); return; }
      fd.append('montant_paye', mp);
    } else if (statut === 'payé') {
      fd.append('montant_paye', TARIF_CLASSE);
    } else {
      fd.append('montant_paye', 0);
    }
  }

  document.getElementById('pay-save-btn').disabled = true;
  document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      document.getElementById('pay-save-btn').disabled = false;
      document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
      if (data.success) {
        const prevRow = _payCurrentRow;
        closePayModal();
        showToast('Paiement enregistré.', 'success');
        updateRow(prevRow.id_stagiaire, data.row, data.tarif, prevRow.nom);
      } else {
        showToast('Erreur : ' + data.error, 'error');
      }
    }).catch(e => {
      document.getElementById('pay-save-btn').disabled = false;
      document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
      showToast('Erreur réseau (' + e.message + ').', 'error');
    });
}

// ── Update a row in place after save ────────────────────────────────────
function updateRow(sid, row, tarif, nomFallback) {
  const tr = document.getElementById('row-' + sid);
  if (!tr) { setTimeout(() => location.reload(), 800); return; }
  const mTotal   = row && row.montant_total   ? parseFloat(row.montant_total)   : tarif;
  const mPaye    = row && row.montant_paye    ? parseFloat(row.montant_paye)    : 0;
  const mRest    = row && row.montant_restant ? parseFloat(row.montant_restant) : mTotal;
  const sp       = row ? (row.statut_paiement || '') : '';
  const isPaye   = (row ? (parseInt(row.est_paye) === 1) : false) || sp === 'payé';
  const isPartiel= sp === 'partiel';
  const statusClass = isPaye ? 'paye' : (isPartiel ? 'partiel' : 'impaye');
  const statusLabel = isPaye ? 'Payé' : (isPartiel ? 'Partiel' : 'Impayé');
  tr.querySelector('.col-paye').textContent  = fmtAmt(mPaye);
  tr.querySelector('.col-paye').className    = 'col-paye ' + (mPaye > 0 ? 'amount-green' : 'amount-gray');
  tr.querySelector('.col-restant').textContent = fmtAmt(mRest);
  tr.querySelector('.col-restant').className   = 'col-restant ' + (mRest > 0 ? 'amount-red' : 'amount-gray');
  tr.querySelector('.col-statut').innerHTML  = `<span class="badge-cot ${statusClass}">${statusLabel}</span>`;
  // Update the openPayModal data for next click
  tr.querySelector('button').setAttribute('onclick',
    `openPayModal(${JSON.stringify({
      id_stagiaire: sid,
      nom: nomFallback || '',
      mois_ref: SEL_MOIS,
      tarif: tarif,
      montant_paye: mPaye,
      montant_restant: mRest,
      has_record: true,
      statut: sp,
    })})`
  );
}

// ── Bulk mark as paid ────────────────────────────────────────────────────
async function doBulkMarkPaid() {
  const ids = getSelectedIds();
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire.', 'error'); return; }
  const ok = await gdsConfirm(`Marquer ${ids.length} stagiaire(s) comme entièrement payé(s) pour ${formatMois(SEL_MOIS)} ?`);
  if (!ok) return;
  const fd = new FormData();
  fd.append('bulk_mark_paid', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('mois_ref', SEL_MOIS);
  ids.forEach(id => fd.append('student_ids[]', id));
  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`${data.updated} cotisation(s) marquée(s) comme payée(s).`, 'success');
        setTimeout(() => location.reload(), 900);
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e => showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Bulk partial payment ─────────────────────────────────────────────────
async function doBulkPartial() {
  const ids = getSelectedIds();
  const montant = parseFloat(document.getElementById('bulk-montant').value) || 0;
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire.', 'error'); return; }
  if (montant <= 0) { showToast('Entrez un montant dans le champ "Montant (MAD)".', 'error'); document.getElementById('bulk-montant').focus(); return; }
  const ok = await gdsConfirm(`Ajouter ${fmtAmt(montant)} de paiement pour ${ids.length} stagiaire(s) — ${formatMois(SEL_MOIS)} ?`);
  if (!ok) return;
  const fd = new FormData();
  fd.append('bulk_partial', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('mois_ref', SEL_MOIS);
  fd.append('montant_partiel', montant);
  fd.append('date_paiement', new Date().toISOString().slice(0, 10));
  ids.forEach(id => fd.append('student_ids[]', id));
  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`Paiement partiel appliqué à ${data.updated} stagiaire(s).`, 'success');
        setTimeout(() => location.reload(), 900);
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e => showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Helpers ──────────────────────────────────────────────────────────────
function fmtAmt(v) {
  return parseFloat(v || 0).toFixed(2) + ' MAD';
}
function formatMois(m) {
  const [y, mo] = (m || '').split('-');
  const noms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
  return (noms[parseInt(mo)] || mo) + ' ' + y;
}
function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'gds-toast ' + (type || 'info');
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, type === 'error' ? 5000 : 3500);
}
let _gdsConfirmCb = null;
function gdsConfirm(msg) {
  return new Promise(resolve => {
    _gdsConfirmCb = resolve;
    document.getElementById('gds-confirm-msg').textContent = msg;
    document.getElementById('gds-confirm-overlay').style.display = 'flex';
  });
}
function gdsConfirmResolve(result) {
  document.getElementById('gds-confirm-overlay').style.display = 'none';
  if (_gdsConfirmCb) { _gdsConfirmCb(result); _gdsConfirmCb = null; }
}
document.getElementById('gds-confirm-overlay')?.addEventListener('click', function(e) {
  if (e.target === this) gdsConfirmResolve(false);
});
document.getElementById('modal-pay')?.addEventListener('click', function(e) {
  if (e.target === this) closePayModal();
});

// ── Student full-year payment drawer ─────────────────────────────────────
const MOIS_NOMS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const MOIS_ICONS = { paye:'fa-circle-check', partiel:'fa-circle-half-stroke', impayé:'fa-circle-xmark', '':'fa-circle' };

let _pmtDrawerSid  = null;
let _pmtDrawerData = null; // last fetched data, used for refresh after modal save

function openPmtDrawer(sid) {
  _pmtDrawerSid = sid;
  const body = document.getElementById('pmt-drawer-body');
  const foot = document.getElementById('pmt-drawer-foot');

  document.getElementById('pmt-drawer-name').textContent = 'Chargement…';
  document.getElementById('pmt-drawer-sub').innerHTML    = '';
  document.getElementById('pmt-drawer-avatar').textContent = '?';
  body.innerHTML = '<div class="pmt-skeleton">' + Array(10).fill('<div class="pmt-skel-row"></div>').join('') + '</div>';
  foot.style.display = 'none';

  document.getElementById('pmt-overlay').classList.add('open');
  document.getElementById('pmt-drawer').classList.add('open');
  document.body.style.overflow = 'hidden';

  _fetchPmtDrawer(sid);
}

function _fetchPmtDrawer(sid) {
  const fd = new FormData();
  fd.append('get_student_payments', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_stagiaire', sid);

  fetch('cotisations.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('pmt-drawer-body').innerHTML =
          `<div style="color:#f87171;padding:1.5rem;text-align:center;"><i class="fa-solid fa-triangle-exclamation" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>${data.error||'Erreur.'}</div>`;
        return;
      }
      _pmtDrawerData = data;
      _renderPmtDrawer(data);
    })
    .catch(e => {
      document.getElementById('pmt-drawer-body').innerHTML =
        `<div style="color:#f87171;padding:1rem;">Erreur réseau : ${e.message}</div>`;
    });
}

function _renderPmtDrawer(data) {
  // Avatar initials
  const parts    = data.nom.trim().split(' ');
  const initials = (parts[0]?.[0]||'') + (parts[1]?.[0]||'');
  document.getElementById('pmt-drawer-avatar').textContent = initials.toUpperCase();
  document.getElementById('pmt-drawer-name').textContent   = data.nom;
  document.getElementById('pmt-drawer-sub').innerHTML =
    `<span>${data.num_inscri||''}</span>` +
    (data.num_inscri ? '<span class="pip"></span>' : '') +
    `<span>${data.annee||''}</span>` +
    `<span class="pip"></span><span>Tarif : <strong style="color:#c084fc;">${fmtAmtN(data.tarif)}</strong>/mois</span>`;

  // Build month cards
  let html = '';
  data.rows.forEach(row => {
    const [y, m]    = row.mois.split('-');
    const moisLabel = (MOIS_NOMS[parseInt(m)]||m) + ' ' + y;
    const isPaye    = row.statut === 'payé';
    const isPartiel = row.statut === 'partiel';
    const isImpaye  = row.statut === 'impayé';
    const isEmpty   = !row.statut;
    const isCurrent = row.mois === SEL_MOIS;

    const cardCls   = 'pmt-card' +
      (isCurrent ? ' is-current' : '') +
      (isPaye    ? ' is-paye'    : '') +
      (isPartiel ? ' is-partiel' : '') +
      (isImpaye  ? ' is-impaye'  : '');

    const badgeCls  = isPaye ? 'paye' : isPartiel ? 'partiel' : isImpaye ? 'impaye' : 'aucun';
    const badgeIcon = isPaye ? 'fa-circle-check' : isPartiel ? 'fa-circle-half-stroke' : isImpaye ? 'fa-circle-xmark' : 'fa-circle';
    const badgeLbl  = isPaye ? 'Payé' : isPartiel ? 'Partiel' : isImpaye ? 'Impayé' : 'Non enregistré';

    // progress bar
    const pct       = data.tarif > 0 ? Math.min(100, (row.paye / data.tarif) * 100) : 0;
    const barColor  = isPaye ? '#34d399' : isPartiel ? '#fb923c' : '#3f3f46';

    // amounts colors
    const payeColor = row.paye > 0   ? '#34d399' : '#52525b';
    const restColor = row.restant > 0 ? '#f87171' : '#52525b';

    // date
    const dateStr = row.date_paiement
      ? '<i class="fa-regular fa-calendar-check" style="color:#34d399;"></i> ' + row.date_paiement.slice(0,10)
      : '<i class="fa-regular fa-calendar" style="color:#3f3f46;"></i> <span style="color:#3f3f46;">Aucun paiement</span>';

    // edit button — show always so you can record a new payment or update
    const editRowData = JSON.stringify({
      id_stagiaire: _pmtDrawerSid,
      nom: data.nom,
      mois_ref: row.mois,
      tarif: data.tarif,
      montant_paye: row.paye,
      montant_restant: row.restant,
      has_record: !!row.statut,
      statut: row.statut || '',
    });

    html += `<div class="${cardCls}">
      <div class="pmt-card-top">
        <div class="pmt-card-month">
          ${isCurrent ? '<i class="fa-solid fa-bookmark" style="color:#a855f7;font-size:.7rem;"></i>' : ''}
          ${moisLabel}
        </div>
        <div class="pmt-card-right">
          <span class="pmt-badge ${badgeCls}"><i class="fa-solid ${badgeIcon}"></i> ${badgeLbl}</span>
          <button type="button" class="pmt-edit-btn" onclick='openPayModalFromDrawer(${editRowData})'>
            <i class="fa-solid fa-pen-to-square"></i> Modifier
          </button>
        </div>
      </div>
      <div class="pmt-progress-wrap">
        <div class="pmt-progress-fill" style="width:${pct}%;background:${barColor};"></div>
      </div>
      <div class="pmt-amounts">
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Dû</span>
          <span class="pmt-amount-val" style="color:#a1a1aa;">${fmtAmtN(row.du)}</span>
        </div>
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Payé</span>
          <span class="pmt-amount-val" style="color:${payeColor};">${fmtAmtN(row.paye)}</span>
        </div>
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Restant</span>
          <span class="pmt-amount-val" style="color:${restColor};">${fmtAmtN(row.restant)}</span>
        </div>
      </div>
      <div class="pmt-date-line">${dateStr}</div>
    </div>`;
  });

  document.getElementById('pmt-drawer-body').innerHTML = html;

  // Footer
  const restCol = data.total_rest > 0 ? '#f87171' : '#34d399';
  document.getElementById('pmt-tot-du').textContent    = fmtAmtN(data.total_du);
  document.getElementById('pmt-tot-paye').textContent  = fmtAmtN(data.total_paye);
  document.getElementById('pmt-tot-rest').textContent  = fmtAmtN(data.total_rest);
  document.getElementById('pmt-tot-rest').style.color  = restCol;

  const lastEl = document.getElementById('pmt-last-pay');
  if (data.last_pay_date) {
    lastEl.innerHTML = '<i class="fa-regular fa-calendar-check" style="color:#34d399;"></i> Dernier paiement : <strong style="color:#e4e4e7;">' + data.last_pay_date.slice(0,10) + '</strong>';
  } else {
    lastEl.innerHTML = '<i class="fa-regular fa-calendar-xmark" style="color:#f87171;"></i> Aucun paiement enregistré';
  }
  document.getElementById('pmt-drawer-foot').style.display = '';
}

// Open pay modal pre-filled from drawer, then refresh drawer on save
function openPayModalFromDrawer(rowData) {
  openPayModal(rowData);
  // After save, the existing savePayment() success path calls updateRow().
  // We hook in via _pmtDrawerAfterSave flag.
  _pmtDrawerAfterSaveNeeded = true;
}

let _pmtDrawerAfterSaveNeeded = false;

// Patch into the existing savePayment success flow
const _origUpdateRow = window.updateRow || function(){};
// We override savePayment's success callback by wrapping it
const _origSavePayment = window.savePayment;

function closePmtDrawer() {
  document.getElementById('pmt-overlay').classList.remove('open');
  document.getElementById('pmt-drawer').classList.remove('open');
  document.body.style.overflow = '';
  _pmtDrawerAfterSaveNeeded = false;
}

function fmtAmtN(v) {
  return parseFloat(v||0).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MAD';
}

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closePmtDrawer(); closePayModal(); }
});

// ── Hook: after a payment modal save, refresh the drawer if open ──────────
// We do this by monkey-patching the fetch response in savePayment.
// Instead, we watch for the pay-save-btn to re-enable (save done) and reload.
(function() {
  const btn = document.getElementById('pay-save-btn');
  if (!btn) return;
  const observer = new MutationObserver(() => {
    if (!btn.disabled && _pmtDrawerAfterSaveNeeded && _pmtDrawerSid) {
      _pmtDrawerAfterSaveNeeded = false;
      // Small delay so the modal close animation finishes
      setTimeout(() => {
        document.getElementById('pmt-drawer-body').innerHTML =
          '<div class="pmt-skeleton">' + Array(10).fill('<div class="pmt-skel-row"></div>').join('') + '</div>';
        document.getElementById('pmt-drawer-foot').style.display = 'none';
        _fetchPmtDrawer(_pmtDrawerSid);
      }, 350);
    }
  });
  observer.observe(btn, { attributes: true, attributeFilter: ['disabled'] });
})();
</script>

<?php
$highlightSid = (int)($_GET['highlight'] ?? 0);
if ($highlightSid > 0):
?>
<style>
@keyframes gds-row-flash {
  0%   { background: transparent; }
  15%  { background: rgba(168,85,247,0.28); box-shadow: inset 0 0 0 2px rgba(168,85,247,0.6); }
  50%  { background: rgba(168,85,247,0.14); }
  85%  { background: rgba(168,85,247,0.28); box-shadow: inset 0 0 0 2px rgba(168,85,247,0.6); }
  100% { background: transparent; }
}
.gds-highlight-row td { animation: gds-row-flash 1.6s ease 2; }
.gds-highlight-row td:first-child { border-left: 3px solid #a855f7; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const sid = <?= $highlightSid ?>;
  const tr = document.getElementById('row-' + sid);
  if (!tr) return;
  tr.classList.add('gds-highlight-row');
  setTimeout(function () { tr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200);
  setTimeout(function () { tr.classList.remove('gds-highlight-row'); }, 4000);
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
