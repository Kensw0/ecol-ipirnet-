<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Gestion des Absences';
$curPage   = 'absences';

// ── GET: AJAX detail loader ───────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_student_absences') {
    header('Content-Type: application/json');
    $sid   = (int)($_GET['id_stagiaire'] ?? 0);
    $dfrom = trim((string)($_GET['date_from'] ?? ''));
    $dto   = trim((string)($_GET['date_to']   ?? ''));
    if ($sid <= 0) { echo json_encode([]); exit; }
    $where = ['a.id_stagiaire = ?']; $params = [$sid];
    if ($dfrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dfrom)) { $where[] = 'a.date_absence >= ?'; $params[] = $dfrom; }
    if ($dto   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dto))   { $where[] = 'a.date_absence <= ?'; $params[] = $dto;   }
    $st = $pdo->prepare('SELECT a.*, m.nom_module FROM absences a LEFT JOIN modules m ON m.id_module=a.id_module WHERE ' . implode(' AND ', $where) . ' ORDER BY a.date_absence DESC, a.heure_debut DESC');
    $st->execute($params);
    echo json_encode($st->fetchAll());
    exit;
}

// ── POST HANDLERS (all JSON) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    header('Content-Type: application/json');

    // Save absence (create or update)
    if (isset($_POST['save_absence'])) {
        $sid     = (int)($_POST['id_stagiaire']   ?? 0);
        $aid     = (int)($_POST['id_absence_edit'] ?? 0);
        // Secretaire may only add, never edit or justify
        if ($aid > 0 && !gds_is_directeur()) { echo json_encode(['success'=>false,'error'=>'Action réservée au Directeur.']); exit; }
        $dateAbs = trim((string)($_POST['date_absence'] ?? ''));
        $heureD  = trim((string)($_POST['heure_debut']  ?? '')) ?: null;
        $heureF  = trim((string)($_POST['heure_fin']    ?? '')) ?: null;
        $justif  = trim((string)($_POST['justificatif'] ?? '')) ?: null;
        $estJust = (isset($_POST['est_justifiee']) && gds_is_directeur()) ? 1 : 0;
        $idMod   = (int)($_POST['id_module'] ?? 0) ?: null;
        if ($sid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAbs)) {
            echo json_encode(['success'=>false,'error'=>'Données invalides.']); exit;
        }
        try {
            if ($aid > 0) {
                $pdo->prepare('UPDATE absences SET date_absence=?,heure_debut=?,heure_fin=?,justificatif=?,est_justifiee=?,id_module=? WHERE id_absence=? AND id_stagiaire=?')
                    ->execute([$dateAbs,$heureD,$heureF,$justif,$estJust,$idMod,$aid,$sid]);
                $absId = $aid;
            } else {
                $pdo->prepare('INSERT INTO absences (date_absence,heure_debut,heure_fin,justificatif,est_justifiee,id_stagiaire,id_module) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$dateAbs,$heureD,$heureF,$justif,$estJust,$sid,$idMod]);
                $absId = (int)$pdo->lastInsertId();
            }
            echo json_encode(['success'=>true,'id_absence'=>$absId,'date_absence'=>$dateAbs,'est_justifiee'=>$estJust,'justificatif'=>$justif,'heure_debut'=>$heureD,'heure_fin'=>$heureF]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'Erreur enregistrement.']);
        }
        exit;
    }

    // Delete absence
    if (isset($_POST['delete_absence'])) {
        if (!gds_is_directeur()) { echo json_encode(['success'=>false,'error'=>'Action réservée au Directeur.']); exit; }
        $aid = (int)($_POST['id_absence'] ?? 0);
        if ($aid <= 0) { echo json_encode(['success'=>false,'error'=>'ID invalide']); exit; }
        try {
            $pdo->prepare('DELETE FROM absences WHERE id_absence=?')->execute([$aid]);
            echo json_encode(['success'=>true]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'Erreur suppression.']);
        }
        exit;
    }

    // Bulk mark absent
    if (isset($_POST['bulk_mark_absent'])) {
        $bulkDate = trim((string)($_POST['bulk_date'] ?? ''));
        $sids     = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
        $heureD   = trim((string)($_POST['heure_debut'] ?? '')) ?: null;
        $heureF   = trim((string)($_POST['heure_fin']   ?? '')) ?: null;
        $idMod    = (int)($_POST['id_module'] ?? 0) ?: null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bulkDate) || empty($sids)) {
            echo json_encode(['success'=>false,'error'=>'Date ou sélection invalide.']); exit;
        }
        $inserted = 0; $skipped = 0;
        // Duplicate guard: same student + same date + same module (allows multiple absences per day for different modules)
        $chk = $pdo->prepare('SELECT COUNT(*) FROM absences WHERE id_stagiaire=? AND date_absence=? AND id_module<=>?');
        $ins = $pdo->prepare('INSERT INTO absences (date_absence,heure_debut,heure_fin,est_justifiee,id_stagiaire,id_module) VALUES (?,?,?,0,?,?)');
        try {
            $pdo->beginTransaction();
            foreach ($sids as $sid) {
                $chk->execute([$sid,$bulkDate,$idMod]);
                if ((int)$chk->fetchColumn() > 0) { $skipped++; continue; }
                $ins->execute([$bulkDate,$heureD,$heureF,$sid,$idMod]);
                $inserted++;
            }
            $pdo->commit();
            echo json_encode(['success'=>true,'inserted'=>$inserted,'skipped'=>$skipped]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'error'=>'Erreur enregistrement.']);
        }
        exit;
    }

    // Bulk justify
    if (isset($_POST['bulk_justify'])) {
        if (!gds_is_directeur()) { echo json_encode(['success'=>false,'error'=>'Action réservée au Directeur.']); exit; }
        $sids   = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
        $justif = trim((string)($_POST['justificatif'] ?? '')) ?: null;
        $dfrom  = trim((string)($_POST['date_from'] ?? ''));
        $dto    = trim((string)($_POST['date_to']   ?? ''));
        if (empty($sids)) { echo json_encode(['success'=>false,'error'=>'Aucun stagiaire sélectionné.']); exit; }
        try {
            $ph   = implode(',', array_fill(0, count($sids), '?'));
            $sql  = "UPDATE absences SET est_justifiee=1, justificatif=? WHERE est_justifiee=0 AND id_stagiaire IN ($ph)";
            $args = array_merge([$justif], array_values($sids));
            if ($dfrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dfrom)) { $sql .= ' AND date_absence >= ?'; $args[] = $dfrom; }
            if ($dto   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dto))   { $sql .= ' AND date_absence <= ?'; $args[] = $dto;   }
            $st = $pdo->prepare($sql); $st->execute($args);
            echo json_encode(['success'=>true,'updated'=>$st->rowCount()]);
        } catch (\Throwable $e) {
            echo json_encode(['success'=>false,'error'=>'Erreur justification.']);
        }
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Action inconnue.']); exit;
}

// ── FILTER PARAMS ─────────────────────────────────────────────────────────
$selAnnee   = trim((string)($_GET['annee']      ?? ''));
$selFiliere = (int)($_GET['id_filiere'] ?? 0);
$selNiveau  = trim((string)($_GET['niveau']     ?? ''));
$selClasse  = (int)($_GET['id_classe']  ?? 0);
$selDateFrom = trim((string)($_GET['date_from'] ?? ''));
$selDateTo   = trim((string)($_GET['date_to']   ?? ''));
if ($selDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDateFrom)) $selDateFrom = '';
if ($selDateTo   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDateTo))   $selDateTo   = '';

// ── HIGHLIGHT (linked from student hub) ───────────────────────────────────
$highlightAid  = (int)($_GET['highlight']     ?? 0);
$highlightRowSid = (int)($_GET['highlight_sid'] ?? 0);   // highlight a student row in the table
$highlightSid  = 0;
$highlightName = '';
if ($highlightAid > 0) {
    $hq = $pdo->prepare("SELECT s.id_stagiaire, UPPER(s.nom) AS nom, s.prenom FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire WHERE a.id_absence=? LIMIT 1");
    $hq->execute([$highlightAid]);
    $hrow = $hq->fetch();
    if ($hrow) {
        $highlightSid  = (int)$hrow['id_stagiaire'];
        $highlightName = trim($hrow['nom'].' '.$hrow['prenom']);
    }
}

// ── CASCADE DATA ──────────────────────────────────────────────────────────
$allAnnees   = $pdo->query("SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC")->fetchAll(PDO::FETCH_COLUMN);
if ($selAnnee === '') { $selAnnee = $_SESSION['global_annee_scolaire'] ?? ($allAnnees[0] ?? ''); }
$allFilieres = $pdo->query("SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere=f.id_filiere ORDER BY f.nom_filiere")->fetchAll();
if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }

$allNiveaux = [];
if ($selFiliere > 0 && $selAnnee !== '') {
    $st = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
    $st->execute([$selFiliere,$selAnnee]); $allNiveaux = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($selNiveau === '' && !empty($allNiveaux)) { $selNiveau = $allNiveaux[0]; }
}
$allClasses = [];
if ($selFiliere > 0 && $selAnnee !== '' && $selNiveau !== '') {
    $st = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
    $st->execute([$selFiliere,$selAnnee,$selNiveau]); $allClasses = $st->fetchAll();
    if ($selClasse === 0 && !empty($allClasses)) { $selClasse = (int)$allClasses[0]['id_classe']; }
}
$allModules = [];
if ($selFiliere > 0) {
    $st = $pdo->prepare("SELECT id_module, nom_module FROM modules WHERE id_filiere=? ORDER BY nom_module");
    $st->execute([$selFiliere]); $allModules = $st->fetchAll();
    if ($selModule === 0 && !empty($allModules)) { $selModule = (int)$allModules[0]['id_module']; }
}

// ── STUDENT DATA ──────────────────────────────────────────────────────────
$stagiaires = []; $classeInfo = null;
if ($selClasse > 0) {
    $r = $pdo->prepare("SELECT c.nom_classe,f.nom_filiere,c.annee_scolaire,c.niveau FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.id_classe=?");
    $r->execute([$selClasse]); $classeInfo = $r->fetch();

    $joinCond = 'a.id_stagiaire = s.id_stagiaire';
    $params   = [$selClasse];
    if ($selDateFrom !== '') { $joinCond .= ' AND a.date_absence >= ?'; $params[] = $selDateFrom; }
    if ($selDateTo   !== '') { $joinCond .= ' AND a.date_absence <= ?'; $params[] = $selDateTo;   }

    $sql = "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
                   COUNT(a.id_absence) AS total_absences,
                   SUM(COALESCE(a.est_justifiee,0)) AS nb_justifiees,
                   COUNT(a.id_absence) - SUM(COALESCE(a.est_justifiee,0)) AS nb_non_justifiees,
                   MAX(a.date_absence) AS derniere_absence
            FROM stagiaires s
            LEFT JOIN absences a ON $joinCond
            WHERE s.id_classe = ?
            GROUP BY s.id_stagiaire
            ORDER BY s.nom, s.prenom";
    // params order: date_from, date_to (for JOIN), then id_classe (for WHERE)
    // Need to reorder: id_classe first since it's in WHERE, join conditions are in ON
    // Actually MySQL evaluates ON params in order they appear in prepared statement
    // The JOIN ON uses $selDateFrom/$selDateTo, WHERE uses $selClasse
    // params array was built as [id_classe, date_from?, date_to?] — need to fix order
    // ON clause params come before WHERE params in param binding
    $joinParams = [];
    if ($selDateFrom !== '') $joinParams[] = $selDateFrom;
    if ($selDateTo   !== '') $joinParams[] = $selDateTo;
    $finalParams = array_merge($joinParams, [$selClasse]);

    $st = $pdo->prepare($sql); $st->execute($finalParams);
    $stagiaires = $st->fetchAll();
}

$totalAbs  = array_sum(array_column($stagiaires, 'total_absences'));
$totalJust = array_sum(array_column($stagiaires, 'nb_justifiees'));
$totalNonJ = $totalAbs - $totalJust;
$nbWithAbs = count(array_filter($stagiaires, fn($r) => (int)$r['total_absences'] > 0));

require __DIR__ . '/includes/header.php';
?>

<style>
.abs-filter-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
.abs-filter-card form{background:transparent;}
.abs-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;align-items:end;}
.abs-filter-grid label{display:flex;flex-direction:column;gap:0.35rem;font-size:0.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.abs-filter-grid select,.abs-filter-grid input[type="date"]{background:#09090b;border:1px solid rgba(255,255,255,0.12);color:#e4e4e7;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.9rem;width:100%;color-scheme:dark;-webkit-color-scheme:dark;}
.abs-filter-grid select:disabled{opacity:0.4;cursor:not-allowed;}
.abs-filter-grid select:focus,.abs-filter-grid input:focus{outline:none;border-color:rgba(168,85,247,0.5);box-shadow:0 0 0 2px rgba(168,85,247,0.15);}
.abs-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;}
.abs-stat-card{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1rem 1.25rem;text-align:center;}
.abs-stat-val{font-size:2rem;font-weight:800;line-height:1;}
.abs-stat-lbl{font-size:0.75rem;color:#71717a;margin-top:0.3rem;text-transform:uppercase;letter-spacing:.05em;}
.abs-table-wrap{background:#18181b;border:1px solid rgba(255,255,255,0.07);border-radius:14px;overflow:hidden;}
.abs-table-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.abs-table{width:100%;border-collapse:collapse;}
.abs-table th{padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#71717a;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);}
.abs-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:.88rem;color:#e4e4e7;vertical-align:middle;}
.abs-table tbody tr:hover td{background:rgba(168,85,247,0.07);}
.abs-table .cb-col{width:40px;text-align:center;}
.abs-table input[type="checkbox"]{accent-color:#a855f7;width:16px;height:16px;cursor:pointer;}
.badge-abs{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;}
.badge-abs.red{background:rgba(239,68,68,.15);color:#fca5a5;}
.badge-abs.green{background:rgba(34,197,94,.12);color:#86efac;}
.badge-abs.yellow{background:rgba(234,179,8,.12);color:#fde047;}
.badge-abs.gray{background:rgba(113,113,122,.15);color:#a1a1aa;}
.btn-abs{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;}
.btn-abs.primary{background:#a855f7;color:#fff;}.btn-abs.primary:hover{background:#9333ea;}
.btn-abs.ghost{background:rgba(168,85,247,.1);color:#c084fc;border:1px solid rgba(168,85,247,.25);}.btn-abs.ghost:hover{background:rgba(168,85,247,.2);}
.btn-abs.danger{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}.btn-abs.danger:hover{background:rgba(239,68,68,.25);}
.btn-abs.sm{padding:3px 9px;font-size:.75rem;}
.bulk-bar{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:14px;padding:.85rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:900;opacity:0;transition:all .25s;pointer-events:none;min-width:520px;flex-wrap:wrap;}
.bulk-bar.visible{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:all;}
.bulk-bar label{font-size:.8rem;color:#a1a1aa;white-space:nowrap;}
.bulk-bar input[type="date"],.bulk-bar input[type="text"]{background:#09090b;border:1px solid rgba(255,255,255,.1);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;}
.bulk-count{font-size:.85rem;font-weight:700;color:#c084fc;white-space:nowrap;}
.empty-state{text-align:center;padding:3.5rem 2rem;color:#52525b;}
.empty-state i{font-size:2.5rem;margin-bottom:1rem;display:block;color:#3f3f46;}
.abs-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;display:none;align-items:center;justify-content:center;}
.abs-modal-card{background:#18181b;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:0;width:min(520px,95vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;}
.abs-modal-header{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.07);}
.abs-modal-header h3{margin:0;font-size:1.05rem;font-weight:700;color:#f4f4f5;}
.abs-modal-body{padding:1.5rem;overflow-y:auto;}
.abs-modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:flex-end;gap:.75rem;}
.abs-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.abs-form-grid label,.abs-form-full label{display:flex;flex-direction:column;gap:.3rem;font-size:.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.abs-form-full{grid-column:span 2;}
.abs-form-grid input,.abs-form-grid select,.abs-form-full input,.abs-form-full select,.abs-form-full textarea{background:#09090b;border:1px solid rgba(255,255,255,.1);color:#e4e4e7;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;width:100%;box-sizing:border-box;}
.abs-form-grid input:focus,.abs-form-full input:focus,.abs-form-full select:focus{outline:none;border-color:rgba(168,85,247,.5);}
.icon-btn-sm{background:none;border:1px solid rgba(255,255,255,.08);border-radius:6px;color:#a1a1aa;padding:4px 8px;cursor:pointer;font-size:.8rem;transition:all .15s;}
.icon-btn-sm:hover{background:rgba(168,85,247,.1);color:#c084fc;border-color:rgba(168,85,247,.3);}
.icon-btn-sm.danger:hover{background:rgba(239,68,68,.12);color:#fca5a5;border-color:rgba(239,68,68,.25);}
.gds-confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:99999;display:none;align-items:center;justify-content:center;}
.gds-confirm-card{background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:16px;padding:2rem 2rem 1.5rem;width:min(380px,92vw);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.6);}
.gds-confirm-icon{font-size:2rem;margin-bottom:.75rem;}
.gds-confirm-msg{color:#e4e4e7;font-size:.95rem;margin:0 0 1.5rem;line-height:1.55;}
.gds-confirm-btns{display:flex;gap:.75rem;justify-content:center;}
.gds-toast{position:fixed;top:1.25rem;right:1.25rem;z-index:99998;border-radius:10px;padding:.8rem 1.25rem;font-weight:600;font-size:.88rem;box-shadow:0 6px 24px rgba(0,0,0,.5);border:1px solid;max-width:360px;line-height:1.4;animation:toastIn .2s ease;}
@keyframes toastIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.gds-toast.success{background:#18181b;border-color:rgba(34,197,94,.4);color:#86efac;}
.gds-toast.error{background:#18181b;border-color:rgba(239,68,68,.4);color:#fca5a5;}
.gds-toast.info{background:#18181b;border-color:rgba(168,85,247,.4);color:#c084fc;}
.detail-abs-row{display:flex;align-items:center;gap:.75rem;padding:.6rem .5rem;border-bottom:1px solid rgba(255,255,255,.05);font-size:.85rem;}
.detail-abs-row:last-child{border-bottom:none;}
.page-header-abs{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.page-header-abs h1{font-size:1.6rem;font-weight:800;color:#f4f4f5;margin:0;}
.page-header-abs p{margin:.3rem 0 0;font-size:.88rem;color:#71717a;}
</style>

<div style="max-width:1200px;margin:0 auto;padding:1.5rem;">

  <!-- Page header -->
  <div class="page-header-abs">
    <div>
      <h1><i class="fa-solid fa-user-clock" style="color:#a855f7;margin-right:.5rem;"></i>Gestion des Absences</h1>
      <p>Système centralisé de gestion des absences par classe</p>
    </div>

  </div>

  <!-- Flash -->
  <?php $flash = flash_get(); if ($flash):
    $fType = $flash['type'] ?? 'success';
    $fStyle = match($fType) {
      'error'   => 'background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#fca5a5;',
      'warning' => 'background:rgba(234,179,8,.1);border-color:rgba(234,179,8,.3);color:#fde047;',
      default   => 'background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:#86efac;',
    };
    $fIcon = match($fType) {
      'error'   => 'fa-circle-xmark',
      'warning' => 'fa-triangle-exclamation',
      default   => 'fa-circle-check',
    };
  ?>
  <div style="border:1px solid;border-radius:10px;padding:.85rem 1.25rem;margin-bottom:1.5rem;font-weight:600;<?= $fStyle ?>">
    <i class="fa-solid <?= $fIcon ?>"></i> <?= h($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Filter card -->
  <div class="abs-filter-card">
    <form method="get" action="absences.php" id="filter-form">
      <div class="abs-filter-grid">

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
            <?php foreach ($allFilieres as $f): ?>
              <option value="<?= (int)$f['id_filiere'] ?>" <?= $selFiliere===(int)$f['id_filiere']?'selected':'' ?>><?= h(gds_filiere_code((string)$f['nom_filiere'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Niveau
          <select name="niveau" onchange="this.form.submit()" <?= ($selFiliere===0||$selAnnee==='')?'disabled':'' ?>>
            <option value="">— Choisir —</option>
            <?php foreach ($allNiveaux as $niv): ?>
              <option value="<?= h($niv) ?>" <?= $selNiveau===$niv?'selected':'' ?>><?= h($niv) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Classe
          <select name="id_classe" onchange="this.form.submit()" <?= ($selNiveau===''||$selFiliere===0)?'disabled':'' ?>>
            <option value="0">— Choisir —</option>
            <?php foreach ($allClasses as $cl): ?>
              <option value="<?= (int)$cl['id_classe'] ?>" <?= $selClasse===(int)$cl['id_classe']?'selected':'' ?>><?= h($cl['nom_classe']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Date début
          <input type="date" name="date_from" value="<?= h($selDateFrom) ?>" <?= $selClasse===0?'disabled':'' ?>>
        </label>

        <label>Date fin
          <input type="date" name="date_to" value="<?= h($selDateTo) ?>" <?= $selClasse===0?'disabled':'' ?>>
        </label>

        <label style="justify-content:flex-end;">
          <button type="submit" class="btn-abs primary" style="width:100%;justify-content:center;padding:.6rem;">
            <i class="fa-solid fa-filter"></i> Filtrer
          </button>
        </label>

      </div>
    </form>
  </div>

  <?php if ($selClasse === 0): ?>
  <div class="empty-state">
    <i class="fa-solid fa-users-rectangle"></i>
    <p style="font-size:1.05rem;font-weight:600;color:#71717a;">Sélectionnez une classe pour afficher les absences</p>
    <p style="font-size:.85rem;color:#3f3f46;">Utilisez les filtres ci-dessus : Année → Filière → Niveau → Classe</p>
  </div>

  <?php else: ?>

  <!-- Stats bar -->
  <div class="abs-stats-row">
    <div class="abs-stat-card" style="border-top:3px solid #a855f7;">
      <div class="abs-stat-val" style="color:#c084fc;"><?= count($stagiaires) ?></div>
      <div class="abs-stat-lbl">Stagiaires</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #ef4444;">
      <div class="abs-stat-val" style="color:#fca5a5;"><?= $totalAbs ?></div>
      <div class="abs-stat-lbl">Absences <?= ($selDateFrom||$selDateTo)?'(période)':'(total)' ?></div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #22c55e;">
      <div class="abs-stat-val" style="color:#86efac;"><?= $totalJust ?></div>
      <div class="abs-stat-lbl">Justifiées</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #f59e0b;">
      <div class="abs-stat-val" style="color:#fde047;"><?= $totalNonJ ?></div>
      <div class="abs-stat-lbl">Non justifiées</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #a855f7;">
      <div class="abs-stat-val" style="color:#c084fc;"><?= $nbWithAbs ?></div>
      <div class="abs-stat-lbl">Avec absences</div>
    </div>
  </div>

  <!-- Student table -->
  <div class="abs-table-wrap">
    <div class="abs-table-header">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <input type="checkbox" id="select-all" title="Tout sélectionner" style="accent-color:#a855f7;width:16px;height:16px;cursor:pointer;">
        <span style="font-size:.85rem;font-weight:700;color:#e4e4e7;">
          <?= h((string)$classeInfo['nom_classe']) ?> — <?= h(gds_filiere_code((string)$classeInfo['nom_filiere'])) ?>
          <span style="color:#71717a;font-weight:400;"> · <?= count($stagiaires) ?> stagiaire(s)</span>
        </span>
      </div>

    </div>

    <?php if (empty($stagiaires)): ?>
    <div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>Aucun stagiaire dans cette classe.</p></div>
    <?php else: ?>
    <table class="abs-table">
      <thead>
        <tr>
          <th class="cb-col"></th>
          <th>#</th>
          <th>Nom &amp; Prénom</th>
          <th>Code</th>
          <th style="text-align:center;">Total abs.</th>
          <th style="text-align:center;">Justifiées</th>
          <th style="text-align:center;">Non just.</th>
          <th>Dernière abs.</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stagiaires as $i => $stag): ?>
        <?php
          $tot  = (int)$stag['total_absences'];
          $just = (int)$stag['nb_justifiees'];
          $nonj = (int)$stag['nb_non_justifiees'];
          $last = $stag['derniere_absence'] ? date('d/m/Y', strtotime($stag['derniere_absence'])) : '—';
        ?>
        <tr id="row-<?= (int)$stag['id_stagiaire'] ?>" data-sid="<?= (int)$stag['id_stagiaire'] ?>">
          <td class="cb-col">
            <input type="checkbox" class="row-cb" value="<?= (int)$stag['id_stagiaire'] ?>">
          </td>
          <td style="color:#71717a;font-size:.8rem;"><?= $i+1 ?></td>
          <td style="font-weight:700;">
            <a href="stagiaires.php?id=<?= (int)$stag['id_stagiaire'] ?>" style="color:#e4e4e7;text-decoration:none;" target="_blank">
              <?= h(strtoupper($stag['nom']).' '.$stag['prenom']) ?>
            </a>
          </td>
          <td style="color:#71717a;font-size:.82rem;font-family:monospace;"><?= h($stag['num_inscri']) ?></td>
          <td style="text-align:center;">
            <?php if ($tot === 0): ?>
              <span class="badge-abs green"><i class="fa-solid fa-check"></i> 0</span>
            <?php elseif ($tot >= 5): ?>
              <span class="badge-abs red"><i class="fa-solid fa-triangle-exclamation"></i> <?= $tot ?></span>
            <?php else: ?>
              <span class="badge-abs yellow"><?= $tot ?></span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;color:#86efac;"><?= $just > 0 ? $just : '<span style="color:#3f3f46;">—</span>' ?></td>
          <td style="text-align:center;">
            <?= $nonj > 0 ? '<span style="color:#fca5a5;font-weight:700;">'.$nonj.'</span>' : '<span style="color:#3f3f46;">—</span>' ?>
          </td>
          <td style="color:#71717a;font-size:.82rem;"><?= $last ?></td>
          <td style="text-align:center;white-space:nowrap;">
              <button type="button" class="icon-btn-sm" title="Ajouter une absence"
                onclick="openAddAbsenceModal(<?= (int)$stag['id_stagiaire'] ?>, '<?= h(addslashes(strtoupper($stag['nom']).' '.$stag['prenom'])) ?>')"
                style="color:#c084fc;border-color:rgba(168,85,247,.25);">
                <i class="fa-solid fa-plus"></i>
              </button>
              <button type="button" class="icon-btn-sm" title="Voir le détail des absences"
                onclick="openDetailModal(<?= (int)$stag['id_stagiaire'] ?>, '<?= h(addslashes(strtoupper($stag['nom']).' '.$stag['prenom'])) ?>')">
                <i class="fa-solid fa-list"></i>
              </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div>

<!-- ─── BULK ACTION BAR ─────────────────────────────────────────────────── -->
<div class="bulk-bar" id="bulk-bar">
  <span class="bulk-count" id="bulk-count">0 sélectionné(s)</span>

  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
    <label style="font-size:.78rem;color:#a1a1aa;">Module *</label>
    <select id="bulk-module" style="background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;color-scheme:dark;">
      <option value="0">— Choisir —</option>
      <?php foreach ($allModules as $mod): ?>
        <option value="<?= (int)$mod['id_module'] ?>"><?= h(gds_module_label((string)$mod['nom_module'])) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="font-size:.78rem;color:#a1a1aa;">Date *</label>
    <input type="date" id="bulk-date" value="<?= date('Y-m-d') ?>" style="color-scheme:dark;">
    <label style="font-size:.78rem;color:#a1a1aa;">De</label>
    <input type="time" id="bulk-hdebut" style="background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;color-scheme:dark;">
    <label style="font-size:.78rem;color:#a1a1aa;">À</label>
    <input type="time" id="bulk-hfin" style="background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;color-scheme:dark;">
    <button type="button" class="btn-abs danger sm" onclick="doBulkMarkAbsent()">
      <i class="fa-solid fa-user-slash"></i> Marquer absents
    </button>
  </div>

  <?php if (gds_is_directeur()): ?>
  <div style="width:1px;height:24px;background:rgba(255,255,255,.1);"></div>
  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
    <input type="text" id="bulk-justif" placeholder="Motif de justification…" style="min-width:180px;">
    <button type="button" class="btn-abs ghost sm" onclick="doBulkJustify()">
      <i class="fa-solid fa-certificate"></i> Justifier
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD / EDIT ABSENCE MODAL ──────────────────────────────────────────── -->
<div class="abs-modal-overlay" id="modal-add-abs">
  <div class="abs-modal-card">
    <div class="abs-modal-header">
      <h3 id="add-modal-title">Nouvelle absence</h3>
      <button type="button" class="icon-btn-sm" onclick="closeModal('modal-add-abs')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="abs-modal-body">
      <p id="add-modal-desc" style="font-size:.85rem;color:#71717a;margin:0 0 1rem;"></p>
      <div class="abs-form-grid">
        <input type="hidden" id="add-sid" value="">
        <input type="hidden" id="add-aid" value="0">
        <label>Date *
          <input type="date" id="add-date" required value="<?= date('Y-m-d') ?>">
        </label>
        <label>Module *
          <select id="add-module" required>
            <option value="0">— Sélectionner un module —</option>
            <?php foreach ($allModules as $mod): ?>
              <option value="<?= (int)$mod['id_module'] ?>"><?= h(gds_module_label((string)$mod['nom_module'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Heure début
          <input type="time" id="add-hdebut">
        </label>
        <label>Heure fin
          <input type="time" id="add-hfin">
        </label>
        <div class="abs-form-full">
          <label>Justificatif / Motif
            <input type="text" id="add-justif" placeholder="Ex: Certificat médical, Absence autorisée…">
          </label>
        </div>
        <?php if (gds_is_directeur()): ?>
        <div class="abs-form-full" style="margin-top:.25rem;">
          <label style="flex-direction:row;align-items:center;gap:.5rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;font-weight:500;color:#d4d4d8;">
            <input type="checkbox" id="add-justifiee" style="accent-color:#22c55e;width:16px;height:16px;">
            Absence déjà justifiée
          </label>
        </div>
        <?php else: ?>
        <input type="hidden" id="add-justifiee" value="">
        <?php endif; ?>
      </div>
    </div>
    <div class="abs-modal-footer">
      <button type="button" class="btn-abs ghost" onclick="closeModal('modal-add-abs')">Annuler</button>
      <button type="button" class="btn-abs primary" onclick="submitAddAbsence()">
        <i class="fa-solid fa-floppy-disk"></i> <span id="add-btn-label">Enregistrer</span>
      </button>
    </div>
  </div>
</div>

<!-- ─── DETAIL MODAL ──────────────────────────────────────────────────────── -->
<div class="abs-modal-overlay" id="modal-detail">
  <div class="abs-modal-card" style="width:min(640px,95vw);">
    <div class="abs-modal-header">
      <h3 id="detail-title">Absences — Stagiaire</h3>
      <div style="display:flex;align-items:center;gap:.5rem;">
        <button type="button" class="btn-abs ghost sm" id="detail-add-btn" onclick="openAddAbsenceModal(_detailCurrentSid, _detailCurrentName)" title="Ajouter une absence">
          <i class="fa-solid fa-plus"></i> Ajouter
        </button>
        <button type="button" class="icon-btn-sm" onclick="closeModal('modal-detail')"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
    <div class="abs-modal-body" id="detail-body" style="padding:1rem 1.5rem;">
      <div style="text-align:center;color:#52525b;padding:2rem;"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>
    </div>
    <div class="abs-modal-footer">
      <button type="button" class="btn-abs ghost" onclick="closeModal('modal-detail')">Fermer</button>
    </div>
  </div>
</div>

<script>
var GDS_CSRF   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
var GDS_IS_DIRECTEUR = <?= gds_is_directeur() ? 'true' : 'false' ?>;
var SEL_DATE_FROM = <?= json_encode($selDateFrom) ?>;
var SEL_DATE_TO   = <?= json_encode($selDateTo) ?>;
var SEL_CLASSE    = <?= $selClasse ?>;
var HIGHLIGHT_AID     = <?= $highlightAid ?>;
var HIGHLIGHT_SID     = <?= $highlightSid ?>;
var HIGHLIGHT_NAME    = <?= json_encode($highlightName) ?>;
var HIGHLIGHT_ROW_SID = <?= $highlightRowSid ?>;

// ── Bulk selection ──────────────────────────────────────────────────────
document.getElementById('select-all')?.addEventListener('change', function(){
  document.querySelectorAll('.row-cb').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});
document.querySelectorAll('.row-cb').forEach(cb => cb.addEventListener('change', updateBulkBar));

function updateBulkBar(){
  const checked = document.querySelectorAll('.row-cb:checked');
  const bar = document.getElementById('bulk-bar');
  document.getElementById('bulk-count').textContent = checked.length + ' sélectionné(s)';
  if (bar) { bar.classList.toggle('visible', checked.length > 0); }
}

function getSelectedIds(){
  return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => parseInt(cb.value));
}

// ── Modals ──────────────────────────────────────────────────────────────
function closeModal(id){ document.getElementById(id).style.display = 'none'; }
document.querySelectorAll('.abs-modal-overlay').forEach(m => {
  m.addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
});

function openAddAbsenceModal(sid, name){
  document.getElementById('add-sid').value    = sid;
  document.getElementById('add-aid').value    = '0';
  document.getElementById('add-modal-title').textContent = sid ? 'Nouvelle absence' : 'Ajouter une absence';
  document.getElementById('add-modal-desc').textContent  = name ? 'Stagiaire : ' + name : 'Sélectionner un stagiaire dans la liste.';
  document.getElementById('add-date').value   = new Date().toISOString().split('T')[0];
  document.getElementById('add-module').value = '0';
  document.getElementById('add-hdebut').value = '';
  document.getElementById('add-hfin').value   = '';
  document.getElementById('add-justif').value = '';
  document.getElementById('add-justifiee').checked = false;
  document.getElementById('add-btn-label').textContent = 'Enregistrer';
  document.getElementById('modal-add-abs').style.display = 'flex';
}

function openEditAbsModal(data){
  document.getElementById('add-sid').value    = data.id_stagiaire;
  document.getElementById('add-aid').value    = data.id_absence;
  document.getElementById('add-modal-title').textContent = 'Modifier l\'absence';
  document.getElementById('add-modal-desc').textContent  = 'Absence du ' + (data.date_absence || '').split('-').reverse().join('/');
  document.getElementById('add-date').value   = data.date_absence || '';
  document.getElementById('add-module').value = data.id_module || '0';
  document.getElementById('add-hdebut').value = (data.heure_debut||'').substring(0,5);
  document.getElementById('add-hfin').value   = (data.heure_fin||'').substring(0,5);
  document.getElementById('add-justif').value = data.justificatif || '';
  document.getElementById('add-justifiee').checked = parseInt(data.est_justifiee) === 1;
  document.getElementById('add-btn-label').textContent = 'Mettre à jour';
  closeModal('modal-detail');
  document.getElementById('modal-add-abs').style.display = 'flex';
}

function submitAddAbsence(){
  const sid = document.getElementById('add-sid').value;
  if (!sid || parseInt(sid) <= 0) { showToast('Sélectionnez un stagiaire d\'abord.', 'error'); return; }
  const modVal = parseInt(document.getElementById('add-module').value);
  if (!modVal || modVal <= 0) { showToast('Le module est obligatoire.', 'error'); document.getElementById('add-module').focus(); return; }
  const dateVal = document.getElementById('add-date').value;
  if (!dateVal) { showToast('La date est obligatoire.', 'error'); return; }
  const fd = new FormData();
  fd.append('save_absence','1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_stagiaire', sid);
  fd.append('id_absence_edit', document.getElementById('add-aid').value);
  fd.append('date_absence', dateVal);
  fd.append('id_module', modVal);
  fd.append('heure_debut', document.getElementById('add-hdebut').value);
  fd.append('heure_fin', document.getElementById('add-hfin').value);
  fd.append('justificatif', document.getElementById('add-justif').value);
  if (document.getElementById('add-justifiee').checked) fd.append('est_justifiee','1');
  fetch('absences.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(data=>{
      if (data.success){
        closeModal('modal-add-abs');
        showToast('Absence enregistrée.', 'success');
        setTimeout(()=>location.reload(), 800);
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e=>showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Bulk mark absent ────────────────────────────────────────────────────
async function doBulkMarkAbsent(){
  const ids = getSelectedIds();
  const date = document.getElementById('bulk-date').value;
  const modId = parseInt(document.getElementById('bulk-module')?.value || '0');
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire dans la liste.', 'error'); return; }
  if (!date) { showToast('Choisissez une date.', 'error'); return; }
  if (!modId || modId <= 0) { showToast('Le module est obligatoire pour marquer les absences.', 'error'); return; }
  const ok = await gdsConfirm('Marquer ' + ids.length + ' stagiaire(s) absent(s) le ' + date.split('-').reverse().join('/') + ' ?');
  if (!ok) return;
  const bulkHdebut = document.getElementById('bulk-hdebut').value;
  const bulkHfin   = document.getElementById('bulk-hfin').value;
  const fd = new FormData();
  fd.append('bulk_mark_absent','1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('bulk_date', date);
  fd.append('id_module', modId);
  if (bulkHdebut) fd.append('heure_debut', bulkHdebut);
  if (bulkHfin)   fd.append('heure_fin', bulkHfin);
  ids.forEach(id => fd.append('student_ids[]', id));
  fetch('absences.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(data=>{
      if (data.success){
        showToast(data.inserted + ' absence(s) enregistrée(s)' + (data.skipped ? ' · ' + data.skipped + ' déjà présente(s) pour ce module/cette date (ignorée(s)).' : '.'), data.inserted > 0 ? 'success' : 'info');
        setTimeout(()=>location.reload(), 900);
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e=>showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Bulk justify ────────────────────────────────────────────────────────
async function doBulkJustify(){
  const ids = getSelectedIds();
  const justif = document.getElementById('bulk-justif').value.trim();
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire dans la liste.', 'error'); return; }
  if (!justif) { showToast('Entrez un motif de justification.', 'error'); document.getElementById('bulk-justif').focus(); return; }
  const ok = await gdsConfirm('Justifier toutes les absences non justifiées de ' + ids.length + ' stagiaire(s) ?');
  if (!ok) return;
  const fd = new FormData();
  fd.append('bulk_justify','1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('justificatif', justif);
  ids.forEach(id => fd.append('student_ids[]', id));
  if (SEL_DATE_FROM) fd.append('date_from', SEL_DATE_FROM);
  if (SEL_DATE_TO)   fd.append('date_to',   SEL_DATE_TO);
  fetch('absences.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(data=>{
      if (data.success){
        showToast(data.updated + ' absence(s) justifiée(s).', 'success');
        setTimeout(()=>location.reload(), 900);
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e=>showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Detail modal ────────────────────────────────────────────────────────
var _detailCurrentSid  = 0;
var _detailCurrentName = '';

function openDetailModal(sid, name){
  _detailCurrentSid  = sid;
  _detailCurrentName = name;
  document.getElementById('detail-title').textContent = 'Absences — ' + name;
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:2rem;color:#71717a;"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>';
  document.getElementById('modal-detail').style.display = 'flex';
  let url = 'absences.php?action=get_student_absences&id_stagiaire=' + sid;
  if (SEL_DATE_FROM) url += '&date_from=' + SEL_DATE_FROM;
  if (SEL_DATE_TO)   url += '&date_to='   + SEL_DATE_TO;
  fetch(url, { credentials:'same-origin' }).then(r=>r.json()).then(rows=>{
    renderDetailBody(rows, sid);
  }).catch(()=>{
    document.getElementById('detail-body').innerHTML = '<p style="color:#fca5a5;padding:1rem;">Erreur de chargement.</p>';
  });
}

function applyDetailFilter(){
  const df = document.getElementById('detail-date-filter');
  const sf = document.getElementById('detail-status-filter');
  const dv = df ? df.value : '';
  const sv = sf ? sf.value : '';
  document.querySelectorAll('#detail-body .detail-abs-row').forEach(function(row){
    let dm = true;
    if (dv) {
      const p = dv.split('-');
      dm = row.dataset.date === p[2]+'/'+p[1]+'/'+p[0];
    }
    const sm = !sv || row.dataset.justif === sv;
    row.style.display = (dm && sm) ? '' : 'none';
  });
}

function clearDetailFilters(){
  const df = document.getElementById('detail-date-filter');
  const sf = document.getElementById('detail-status-filter');
  if (df) df.value = '';
  if (sf) sf.value = '';
  applyDetailFilter();
}

function renderDetailBody(rows, sid){
  if (!rows.length){
    document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:2rem;color:#52525b;"><i class="fa-solid fa-check-circle" style="color:#86efac;font-size:1.5rem;"></i><p>Aucune absence enregistrée pour cette période.</p></div>';
    return;
  }
  // Filter bar
  let html = '<div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;padding:.6rem .75rem;background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.18);border-radius:8px;">'
    + '<i class="fa-solid fa-filter" style="color:#a855f7;font-size:.8rem;"></i>'
    + '<input type="date" id="detail-date-filter" onchange="applyDetailFilter()" style="background:#09090b;border:1px solid rgba(168,85,247,.3);color:#e4e4e7;border-radius:7px;padding:.3rem .65rem;font-size:.82rem;color-scheme:dark;">'
    + '<select id="detail-status-filter" onchange="applyDetailFilter()" style="background:#09090b;border:1px solid rgba(168,85,247,.3);color:#e4e4e7;border-radius:7px;padding:.3rem .65rem;font-size:.82rem;">'
    + '<option value="">Tous les statuts</option>'
    + '<option value="1">Justifiée</option>'
    + '<option value="0">Non justifiée</option>'
    + '</select>'
    + '<button type="button" onclick="clearDetailFilters();" style="background:transparent;border:1px solid rgba(255,255,255,.1);color:#71717a;border-radius:7px;padding:.3rem .65rem;font-size:.78rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>'
    + '</div>';
  rows.forEach(r => {
    const date    = (r.date_absence||'').split('-').reverse().join('/');
    const heures  = (r.heure_debut && r.heure_fin) ? r.heure_debut.substring(0,5)+' – '+r.heure_fin.substring(0,5) : '—';
    const justifie = parseInt(r.est_justifiee) === 1;
    const badge   = justifie ? '<span class="badge-abs green" style="font-size:.7rem;">Justifiée</span>' : '<span class="badge-abs red" style="font-size:.7rem;">Non just.</span>';
    const module  = r.nom_module ? '<small style="color:#71717a;">'+escHtml(r.nom_module)+'</small>' : '';
    const justif  = r.justificatif ? '<small style="color:#a1a1aa;font-style:italic;">'+escHtml(r.justificatif)+'</small>' : '';
    html += '<div class="detail-abs-row" data-aid="'+r.id_absence+'" data-date="'+date+'" data-justif="'+(justifie?'1':'0')+'">'
      + '<div style="flex:1;">'
      + '<span style="font-weight:700;color:#e4e4e7;">'+date+'</span>'
      + (module ? ' · ' + module : '')
      + '<br><small style="color:#71717a;">'+heures+'</small>'
      + (justif ? '<br>' + justif : '')
      + '</div>'
      + badge
      + '<div style="display:flex;gap:4px;">'
      + (GDS_IS_DIRECTEUR ? '<button type="button" class="icon-btn-sm" title="Modifier" onclick="openEditAbsModal('+escHtml(JSON.stringify({id_stagiaire:sid,id_absence:r.id_absence,date_absence:r.date_absence,heure_debut:r.heure_debut,heure_fin:r.heure_fin,justificatif:r.justificatif,est_justifiee:r.est_justifiee,id_module:r.id_module}))+')" ><i class="fa-solid fa-pen"></i></button>' : '')
      + (GDS_IS_DIRECTEUR ? '<button type="button" class="icon-btn-sm danger" title="Supprimer" onclick="deleteAbsence('+r.id_absence+',this)"><i class="fa-solid fa-trash"></i></button>' : '')
      + (justifie ? '<a href="print_billet_excuse.php?id='+r.id_absence+'&auto=1" target="_blank" class="icon-btn-sm" title="Billet d&#39;excuse" style="color:#86efac;"><i class="fa-solid fa-print"></i></a>' : '')
      + '</div>'
      + '</div>';
  });
  document.getElementById('detail-body').innerHTML = html;
  // Highlight & scroll to a specific absence if opened from hub
  if (HIGHLIGHT_AID > 0) {
    const target = document.querySelector('#detail-body .detail-abs-row[data-aid="'+HIGHLIGHT_AID+'"]');
    if (target) {
      target.scrollIntoView({ behavior:'smooth', block:'center' });
      target.style.transition = 'background .2s';
      target.style.background = 'rgba(168,85,247,.22)';
      target.style.borderRadius = '8px';
      target.style.outline = '2px solid rgba(168,85,247,.6)';
      setTimeout(function(){ target.style.background=''; target.style.outline=''; }, 2800);
    }
  }
}

async function deleteAbsence(aid, btn){
  const ok = await gdsConfirm('Supprimer cette absence ?');
  if (!ok) return;
  const fd = new FormData();
  fd.append('delete_absence','1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_absence', aid);
  fetch('absences.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r=>r.json()).then(data=>{
      if (data.success){ btn.closest('.detail-abs-row').remove(); showToast('Absence supprimée.','success'); setTimeout(()=>location.reload(),800); }
      else alert('Erreur: ' + data.error);
    }).catch(()=>alert('Erreur réseau.'));
}

// ── Helpers ─────────────────────────────────────────────────────────────
function escHtml(s){
  if (typeof s !== 'string') return JSON.stringify(s).replace(/"/g,'&quot;');
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type){
  const t = document.createElement('div');
  t.className = 'gds-toast ' + (type || 'info');
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, type==='error'?5000:3500);
}
let _gdsConfirmCb = null;
function gdsConfirm(msg){
  return new Promise(resolve => {
    _gdsConfirmCb = resolve;
    document.getElementById('gds-confirm-msg').textContent = msg;
    document.getElementById('gds-confirm-overlay').style.display = 'flex';
  });
}
function gdsConfirmResolve(result){
  document.getElementById('gds-confirm-overlay').style.display = 'none';
  if (_gdsConfirmCb) { _gdsConfirmCb(result); _gdsConfirmCb = null; }
}
document.getElementById('gds-confirm-overlay')?.addEventListener('click', function(e){
  if(e.target===this) gdsConfirmResolve(false);
});

// Auto-open detail modal when arriving from student hub with a highlight
if (HIGHLIGHT_AID > 0 && HIGHLIGHT_SID > 0 && SEL_CLASSE > 0) {
  document.addEventListener('DOMContentLoaded', function(){
    openDetailModal(HIGHLIGHT_SID, HIGHLIGHT_NAME || 'Stagiaire');
  });
}

// Flash & scroll to a student row when arriving from the absences hub tab
if (HIGHLIGHT_ROW_SID > 0) {
  document.addEventListener('DOMContentLoaded', function(){
    var tr = document.getElementById('row-' + HIGHLIGHT_ROW_SID);
    if (!tr) return;
    setTimeout(function(){
      tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
      tr.style.transition = 'background 0.2s, outline 0.2s';
      tr.style.background  = 'rgba(168,85,247,0.22)';
      tr.style.outline     = '2px solid rgba(168,85,247,0.6)';
      tr.style.borderRadius = '6px';
      setTimeout(function(){
        tr.style.background  = '';
        tr.style.outline     = '';
      }, 2800);
    }, 250);
  });
}
</script>

<!-- ─── CUSTOM CONFIRM DIALOG ─────────────────────────────────────────────── -->
<div id="gds-confirm-overlay" class="gds-confirm-overlay">
  <div class="gds-confirm-card">
    <div class="gds-confirm-icon">⚠️</div>
    <p class="gds-confirm-msg" id="gds-confirm-msg">Confirmer l'action ?</p>
    <div class="gds-confirm-btns">
      <button class="btn-abs ghost" onclick="gdsConfirmResolve(false)"><i class="fa-solid fa-xmark"></i> Annuler</button>
      <button class="btn-abs danger" onclick="gdsConfirmResolve(true)"><i class="fa-solid fa-check"></i> Confirmer</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
