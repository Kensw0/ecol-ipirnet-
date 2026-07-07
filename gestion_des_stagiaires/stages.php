<?php
  /**
   * stages.php — Gestion des stages et PFE des stagiaires
   *
   * Fonctionnalités :
   *   - Affichage des stages par filière / niveau / classe / année scolaire
   *   - Ajout et modification d'un stage (modal AJAX, sans rechargement de page)
   *   - Suppression d'un stage (AJAX, mise à jour DOM immédiate)
   *   - Garde côté serveur : un seul stage entreprise + un seul PFE par étudiant/an
   *   - Impression de la convention de stage
   *
   * Tables : stages, stagiaires, classes, filieres
   */
  declare(strict_types=1);
  require_once __DIR__ . '/includes/auth.php';
  require_once __DIR__ . '/includes/db.php';
  require_once __DIR__ . '/includes/helpers.php';
  gds_require_admin_session();

  $pageTitle = 'Gestion des stages';
  $curPage   = 'stages';


  // ============================================================
  //  SECTION 1 : Gestionnaires POST (toutes les réponses sont JSON)
  // ============================================================

  // ── 1a. Enregistrement d'un stage (ajout ou modification) ────────────────
  if (isset($_POST['quick_save_stage'])) {
      header('Content-Type: application/json');

      // Récupération et validation des données du formulaire
      $idStagiaire          = (int)($_POST['id_stagiaire']            ?? 0);
      $typeStage            = in_array((string)($_POST['type_stage'] ?? ''), ['stage_entreprise', 'pfe'], true)
                              ? (string)$_POST['type_stage'] : 'stage_entreprise';
      $sujet                = trim((string)($_POST['sujet']            ?? ''));
      $entreprise           = trim((string)($_POST['entreprise']       ?? ''));
      $dateDebut            = ($_POST['date_debut']       ?? '') === '' ? null : (string)$_POST['date_debut'];
      $dateFin              = ($_POST['date_fin']         ?? '') === '' ? null : (string)$_POST['date_fin'];
      $noteStage            = ($_POST['note_stage']       ?? '') === '' ? null : (float)str_replace(',', '.', (string)$_POST['note_stage']);
      $conventionUrl        = trim((string)($_POST['convention_url']   ?? ''));
      $rapportUrl           = trim((string)($_POST['rapport_url']      ?? ''));
      $evaluationEntreprise = trim((string)($_POST['evaluation_entreprise'] ?? ''));
      $dateSoutenance       = ($_POST['date_soutenance']  ?? '') === '' ? null : (string)$_POST['date_soutenance'];
      $jury                 = trim((string)($_POST['jury']             ?? ''));
      $anneeScolairePost    = trim((string)($_POST['annee_scolaire']   ?? ''));
      $idStageEdite         = (int)($_POST['id_stage']                ?? 0);

      if ($idStagiaire <= 0) {
          echo json_encode(['success' => false, 'msg' => 'Données invalides.']); exit;
      }

      try {
          if ($idStageEdite > 0) {
              // ── Mode édition : mise à jour d'un stage existant ──
              $pdo->prepare(
                  'UPDATE stages SET type_stage=?, sujet=?, entreprise=?, date_debut=?, date_fin=?,
                   note_stage=?, convention_url=?, rapport_url=?, evaluation_entreprise=?,
                   date_soutenance=?, jury=?, annee_scolaire=?
                   WHERE id_stage=? AND id_stagiaire=?'
              )->execute([
                  $typeStage,
                  $sujet       === '' ? null : $sujet,
                  $entreprise  === '' ? null : $entreprise,
                  $dateDebut, $dateFin, $noteStage,
                  $conventionUrl  === '' ? null : $conventionUrl,
                  $rapportUrl     === '' ? null : $rapportUrl,
                  $evaluationEntreprise === '' ? null : $evaluationEntreprise,
                  $dateSoutenance, $jury === '' ? null : $jury,
                  $anneeScolairePost, $idStageEdite, $idStagiaire,
              ]);
              $msgRetour = 'Stage mis à jour.';
          } else {
              // ── Mode création : vérification doublon avant insertion ──
              // Règle : un seul stage_entreprise et un seul PFE par étudiant et par année
              $reqDoublon = $pdo->prepare(
                  'SELECT id_stage FROM stages WHERE id_stagiaire=? AND type_stage=? AND annee_scolaire=? LIMIT 1'
              );
              $reqDoublon->execute([$idStagiaire, $typeStage, $anneeScolairePost]);
              if ($reqDoublon->fetch()) {
                  $libelleType = $typeStage === 'pfe' ? 'PFE' : 'stage en entreprise';
                  echo json_encode(['success' => false, 'msg' => "Ce stagiaire a déjà un $libelleType pour l'année $anneeScolairePost."]);
                  exit;
              }
              $pdo->prepare(
                  'INSERT INTO stages
                      (type_stage, sujet, entreprise, date_debut, date_fin,
                       note_stage, convention_url, rapport_url, evaluation_entreprise,
                       date_soutenance, jury, id_stagiaire, annee_scolaire)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
              )->execute([
                  $typeStage,
                  $sujet      === '' ? null : $sujet,
                  $entreprise === '' ? null : $entreprise,
                  $dateDebut, $dateFin, $noteStage,
                  $conventionUrl  === '' ? null : $conventionUrl,
                  $rapportUrl     === '' ? null : $rapportUrl,
                  $evaluationEntreprise === '' ? null : $evaluationEntreprise,
                  $dateSoutenance, $jury === '' ? null : $jury,
                  $idStagiaire, $anneeScolairePost,
              ]);
              $msgRetour = 'Stage ajouté.';
          }

          // Retourner les stages mis à jour pour que le DOM soit rafraîchi sans rechargement
          $reqStagesUpdate = $pdo->prepare(
              'SELECT * FROM stages WHERE id_stagiaire=? AND annee_scolaire=? ORDER BY type_stage'
          );
          $reqStagesUpdate->execute([$idStagiaire, $anneeScolairePost]);
          $stagesMisAJour = $reqStagesUpdate->fetchAll(PDO::FETCH_ASSOC);

          echo json_encode(['success' => true, 'msg' => $msgRetour, 'stages' => $stagesMisAJour]);
      } catch (\Exception $e) {
          error_log('[stages.php] ' . $e->getMessage());
          echo json_encode(['success' => false, 'msg' => 'Une erreur est survenue. Veuillez réessayer.']);
      }
      exit;
  }

  // ── 1b. Suppression d'un stage ────────────────────────────────────────────
  if (isset($_POST['quick_delete_stage'])) {
      header('Content-Type: application/json');
      $idStagiaire  = (int)($_POST['id_stagiaire'] ?? 0);
      $idStage      = (int)($_POST['id_stage']     ?? 0);
      $anneeScolairePost = trim((string)($_POST['annee_scolaire'] ?? ''));

      if ($idStagiaire <= 0 || $idStage <= 0) {
          echo json_encode(['success' => false, 'msg' => 'Données invalides.']); exit;
      }

      $pdo->prepare('DELETE FROM stages WHERE id_stage=? AND id_stagiaire=?')
          ->execute([$idStage, $idStagiaire]);

      // Retourner les stages restants pour mise à jour du DOM côté client
      $reqStagesRestants = $pdo->prepare(
          'SELECT * FROM stages WHERE id_stagiaire=? AND annee_scolaire=? ORDER BY type_stage'
      );
      $reqStagesRestants->execute([$idStagiaire, $anneeScolairePost]);
      $stagesRestants = $reqStagesRestants->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode(['success' => true, 'msg' => 'Stage supprimé.', 'stages' => $stagesRestants]);
      exit;
  }


  // ============================================================
  //  SECTION 2 : Paramètres de filtrage (GET)
  // ============================================================

  $anneeSelectionnee = trim((string)($_GET['annee']      ?? ''));
  $idFiliereSelecte  = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte   = (int)($_GET['id_classe']  ?? 0);


  // ============================================================
  //  SECTION 3 : Données en cascade filière → niveau → classe
  // ============================================================

  // Années scolaires disponibles
  $toutesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes
        WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
        ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);
  if ($anneeSelectionnee === '') {
      $anneeSelectionnee = $_SESSION['global_annee_scolaire'] ?? ($toutesAnnees[0] ?? '');
  }

  // Filières disponibles
  $toutesFilières = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f
        INNER JOIN classes c ON c.id_filiere = f.id_filiere
        ORDER BY f.nom_filiere"
  )->fetchAll();
  if ($idFiliereSelecte === 0 && !empty($toutesFilières)) {
      $idFiliereSelecte = (int)$toutesFilières[0]['id_filiere'];
  }

  // Niveaux pour la filière + année sélectionnées
  $tousNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $reqNiveaux = $pdo->prepare(
          'SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau'
      );
      $reqNiveaux->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousNiveaux = $reqNiveaux->fetchAll(PDO::FETCH_COLUMN);
      if (!empty($tousNiveaux) && !in_array($niveauSelectionne, $tousNiveaux, true)) {
          $niveauSelectionne = $tousNiveaux[0];
      }
  }

  // Classes pour filière + année + niveau
  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '' && $niveauSelectionne !== '') {
      $reqClasses = $pdo->prepare(
          'SELECT id_classe, nom_classe FROM classes
            WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe'
      );
      $reqClasses->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $reqClasses->fetchAll();
      $idsClassesValides = array_map('intval', array_column($toutesLesClasses, 'id_classe'));
      if (!empty($toutesLesClasses) && !in_array($idClasseSelecte, $idsClassesValides, true)) {
          $idClasseSelecte = (int)$toutesLesClasses[0]['id_classe'];
      }
  }


  // ============================================================
  //  SECTION 4 : Chargement des stagiaires et de leurs stages
  // ============================================================

  $stagiaires = [];
  if ($idClasseSelecte > 0) {
      // Récupérer tous les étudiants de la classe triés alphabétiquement
      $reqStagiaires = $pdo->prepare(
          'SELECT id_stagiaire, num_inscri, nom, prenom, cin FROM stagiaires WHERE id_classe=? ORDER BY nom, prenom'
      );
      $reqStagiaires->execute([$idClasseSelecte]);
      $listeStagiaires = $reqStagiaires->fetchAll(PDO::FETCH_ASSOC);

      foreach ($listeStagiaires as $stagiaire) {
          $idStagCourant = (int)$stagiaire['id_stagiaire'];

          // Stages de l'étudiant pour l'année scolaire sélectionnée
          $reqStages = $pdo->prepare(
              'SELECT st.* FROM stages st WHERE st.id_stagiaire=? AND st.annee_scolaire=? ORDER BY st.type_stage'
          );
          $reqStages->execute([$idStagCourant, $anneeSelectionnee]);
          $stagiaire['stages_data'] = $reqStages->fetchAll(PDO::FETCH_ASSOC);

          // Détecter les types de stages déjà déposés
          $aStageEntreprise = false;
          $aPFE             = false;
          foreach ($stagiaire['stages_data'] as $stage) {
              if ($stage['type_stage'] === 'stage_entreprise') $aStageEntreprise = true;
              if ($stage['type_stage'] === 'pfe')             $aPFE             = true;
          }
          $stagiaire['has_stage'] = $aStageEntreprise;
          $stagiaire['has_pfe']   = $aPFE;

          // Calcul du statut selon le niveau (1ère année : seul le stage requis ; 2ème : stage + PFE)
          if (strpos($niveauSelectionne, '1') !== false) {
              $stagiaire['status'] = $aStageEntreprise ? 'complet' : 'manquant';
              $stagiaire['recap']  = $aStageEntreprise ? '✅ Stage validé' : '🔴 Stage requis';
          } else {
              if ($aStageEntreprise && $aPFE) {
                  $stagiaire['status'] = 'complet';
                  $stagiaire['recap']  = '✅ Stage & PFE validés';
              } elseif ($aStageEntreprise || $aPFE) {
                  $stagiaire['status'] = 'partiel';
                  $stagiaire['recap']  = (!$aStageEntreprise ? '🔴 Stage' : '') . (!$aPFE ? ' 🔴 PFE' : '') . ' manquant';
              } else {
                  $stagiaire['status'] = 'vide';
                  $stagiaire['recap']  = '❌ Aucun document';
              }
          }

          $stagiaires[] = $stagiaire;
      }
  }

  // Informations de la classe sélectionnée (pour l'en-tête du tableau)
  $infoClasse = null;
  if ($idClasseSelecte > 0) {
      $reqClasse = $pdo->prepare(
          'SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau
             FROM classes c
             JOIN filieres f ON f.id_filiere = c.id_filiere
            WHERE c.id_classe = ?'
      );
      $reqClasse->execute([$idClasseSelecte]);
      $infoClasse = $reqClasse->fetch();
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

  /* ── Cartes récapitulatives ── */
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

  /* ── Tableau des stages ── */
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

  /* ── Badges de statut ── */
  .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
  }
  .status-complet  { background: rgba(16,185,129,0.1);  color: #10b981; border: 1px solid rgba(16,185,129,0.2);  }
  .status-partiel  { background: rgba(245,158,11,0.1);  color: #f59e0b; border: 1px solid rgba(245,158,11,0.2);  }
  .status-manquant { background: rgba(239,68,68,0.1);   color: #ef4444; border: 1px solid rgba(239,68,68,0.2);   }
  .status-vide     { background: rgba(239,68,68,0.1);   color: #ef4444; border: 1px solid rgba(239,68,68,0.2);   }

  /* ── Mini-fiches stage dans la cellule Documents ── */
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
  .mini-stage-card.pfe   { border-left: 3px solid #a855f7; }
  .mini-stage-card.stage { border-left: 3px solid #3b82f6; }

  /* ── Boutons d'action ── */
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

  /* ── Modale de saisie du stage ── */
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

  /* ── Toast notifications ── */
  .gds-toast {
      position: fixed; bottom: 1.5rem; right: 1.5rem;
      padding: .75rem 1.25rem; border-radius: 10px; font-size: .88rem; font-weight: 600;
      color: #fff; z-index: 99999; pointer-events: none;
      box-shadow: 0 8px 24px rgba(0,0,0,.4);
  }
  .gds-toast.success { background: #16a34a; }
  .gds-toast.error   { background: #dc2626; }
  .gds-toast.info    { background: #2563eb; }
  </style>

  <div class="stages-shell">

      <div style="margin-bottom:1.5rem;">
          <a href="index.php" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
              <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
          </a>
      </div>

      <!-- ── Formulaire de filtrage par filière / niveau / classe ── -->
      <div class="filter-card no-print">
          <form method="get" id="stages-filter-form">
              <div class="filter-grid">
                  <div class="filter-group">
                      <label>Année Scolaire</label>
                      <select name="annee" id="f-annee">
                          <?php foreach ($toutesAnnees as $annee): ?>
                              <option value="<?= h($annee) ?>" <?= $annee === $anneeSelectionnee ? 'selected' : '' ?>><?= h($annee) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>Filière</label>
                      <select name="id_filiere" id="f-filiere">
                          <option value="0">— Choisir —</option>
                          <?php foreach ($toutesFilières as $filiere): ?>
                              <option value="<?= $filiere['id_filiere'] ?>" <?= $filiere['id_filiere'] == $idFiliereSelecte ? 'selected' : '' ?>><?= h($filiere['nom_filiere']) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>Niveau</label>
                      <select name="niveau" id="f-niveau" <?= $idFiliereSelecte == 0 ? 'disabled' : '' ?>>
                          <?php if (empty($tousNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
                          <?php foreach ($tousNiveaux as $niveau): ?>
                              <option value="<?= h($niveau) ?>" <?= $niveau === $niveauSelectionne ? 'selected' : '' ?>><?= h($niveau) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="filter-group">
                      <label>Classe</label>
                      <select name="id_classe" id="f-classe" <?= $niveauSelectionne === '' ? 'disabled' : '' ?>>
                          <?php if (empty($toutesLesClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
                          <?php foreach ($toutesLesClasses as $classe): ?>
                              <option value="<?= $classe['id_classe'] ?>" <?= $classe['id_classe'] == $idClasseSelecte ? 'selected' : '' ?>><?= h($classe['nom_classe']) ?></option>
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

      <?php if ($idClasseSelecte > 0 && $infoClasse): ?>

          <!-- ── Tableau des stagiaires avec leurs stages ── -->
          <div class="card" style="background:transparent; border:none; padding:0;">
              <table class="stage-table">
                  <thead>
                      <tr>
                          <th style="width:250px;">Stagiaire</th>
                          <th>Statut (<?= h($niveauSelectionne) ?>)</th>
                          <th>Documents de Stage</th>
                          <th style="text-align:right;">Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (empty($stagiaires)): ?>
                          <tr><td colspan="4" style="text-align:center; padding:3rem; color:#71717a;">Aucun stagiaire dans cette classe.</td></tr>
                      <?php endif; ?>
                      <?php foreach ($stagiaires as $stagiaire): ?>
                          <?php
                          // Pré-calculer si l'étudiant peut encore ajouter un stage
                          $estAnnee1 = strpos($niveauSelectionne, '1') !== false;
                          $peutAjouter = true;
                          if ($estAnnee1 && $stagiaire['has_stage'])            $peutAjouter = false;
                          if (!$estAnnee1 && $stagiaire['has_stage'] && $stagiaire['has_pfe']) $peutAjouter = false;
                          ?>
                          <tr class="stage-row" id="row-<?= $stagiaire['id_stagiaire'] ?>"
                              data-sid="<?= (int)$stagiaire['id_stagiaire'] ?>"
                              data-annee="<?= h($anneeSelectionnee) ?>"
                              data-niveau-num="<?= $estAnnee1 ? 1 : 2 ?>">
                              <!-- Identité -->
                              <td>
                                  <div style="font-weight:700; color:#fff; font-size:0.95rem;"><?= h($stagiaire['nom'] . ' ' . $stagiaire['prenom']) ?></div>
                                  <div style="font-size:0.75rem; color:#71717a; margin-top:2px;">CIN: <?= h($stagiaire['cin'] ?? '—') ?></div>
                              </td>
                              <!-- Statut du dossier -->
                              <td class="col-statut">
                                  <span class="status-badge status-<?= $stagiaire['status'] ?>">
                                      <i class="fa-solid <?= $stagiaire['status'] === 'complet' ? 'fa-check' : ($stagiaire['status'] === 'partiel' ? 'fa-hourglass-half' : 'fa-xmark') ?>"></i>
                                      <?= $stagiaire['recap'] ?>
                                  </span>
                              </td>
                              <!-- Mini-fiches de chaque stage déposé -->
                              <td class="col-docs">
                                  <?php if (empty($stagiaire['stages_data'])): ?>
                                      <span style="font-size:0.8rem; color:#3f3f46; font-style:italic;">Aucune donnée saisie</span>
                                  <?php else: ?>
                                      <?php foreach ($stagiaire['stages_data'] as $stage): ?>
                                          <div class="mini-stage-card <?= $stage['type_stage'] === 'pfe' ? 'pfe' : 'stage' ?>">
                                              <div style="display:flex; flex-direction:column;">
                                                  <strong style="color:#e4e4e7; font-size:0.72rem; text-transform:uppercase;">
                                                      <?= $stage['type_stage'] === 'pfe' ? 'PFE' : 'Stage' ?>
                                                  </strong>
                                                  <span style="color:#a1a1aa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">
                                                      <?= h($stage['entreprise'] ?: 'Non spécifié') ?>
                                                  </span>
                                              </div>
                                              <div style="display:flex; gap:6px;">
                                                  <button class="action-btn" onclick='openStageModal(<?= h(json_encode($stage)) ?>, <?= $stagiaire['id_stagiaire'] ?>)' title="Modifier">
                                                      <i class="fa-solid fa-pen-to-square" style="font-size:0.7rem;"></i>
                                                  </button>
                                                  <a href="print_convention_stage.php?id=<?= $stage['id_stage'] ?>" target="_blank" class="action-btn print" title="Imprimer Convention">
                                                      <i class="fa-solid fa-file-pdf" style="font-size:0.7rem;"></i>
                                                  </a>
                                                  <button class="action-btn" onclick='deleteStage(<?= $stage["id_stage"] ?>, <?= $stagiaire["id_stagiaire"] ?>)' title="Supprimer" style="color:#ef4444;">
                                                      <i class="fa-solid fa-trash" style="font-size:0.7rem;"></i>
                                                  </button>
                                              </div>
                                          </div>
                                      <?php endforeach; ?>
                                  <?php endif; ?>
                              </td>
                              <!-- Bouton Ajouter (masqué si dossier complet) -->
                              <td class="col-actions" style="text-align:right;">
                                  <?php if ($peutAjouter): ?>
                                      <button class="btn btn-primary small"
                                              onclick='openStageModal(null, <?= $stagiaire["id_stagiaire"] ?>, <?= $estAnnee1 ? 1 : 2 ?>, <?= $stagiaire["has_stage"] ? "true" : "false" ?>, <?= $stagiaire["has_pfe"] ? "true" : "false" ?>)'
                                              style="background:rgba(168,85,247,0.1); color:#a855f7; border:1px solid rgba(168,85,247,0.2);">
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
          <!-- Invite de sélection quand aucune classe n'est choisie -->
          <div style="text-align:center; padding:5rem 2rem; background:rgba(255,255,255,0.02); border-radius:20px; border:2px dashed rgba(255,255,255,0.05);">
              <i class="fa-solid fa-briefcase" style="font-size:3rem; color:rgba(255,255,255,0.1); margin-bottom:1.5rem;"></i>
              <h2 style="color:#71717a; font-weight:400;">Sélectionnez une classe pour gérer les stages</h2>
              <p style="color:#3f3f46; font-size:0.9rem; margin-top:0.5rem;">Les rapports et conventions seront générés automatiquement.</p>
          </div>
      <?php endif; ?>

  </div><!-- /stages-shell -->

  <!-- ── Modale de saisie / modification d'un stage ── -->
  <div id="modal-quick-stage" class="modal-overlay" style="display:none; z-index:10000;">
      <div class="modal-card" style="max-width:700px;">
          <div class="modal-header">
              <h2 id="stage-modal-title">Ajouter un Stage / PFE</h2>
              <button type="button" class="icon-btn" onclick="fermerModal()"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <form method="post" id="stage-form" onsubmit="submitStage(event)">
              <input type="hidden" name="id_stagiaire"    id="stage-sid"      value="">
              <input type="hidden" name="id_stage"        id="stage-edit-id"  value="">
              <input type="hidden" name="quick_save_stage" value="1">
              <div class="modal-body">
                  <fieldset class="modal-fieldset">
                      <legend style="color: #a855f7; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Informations Générales</legend>
                      <label>Type *
                          <select name="type_stage" id="stage-type">
                              <option value="stage_entreprise">Stage en entreprise</option>
                              <option value="pfe">PFE</option>
                          </select>
                      </label>
                      <label>Année Scolaire *
                          <select name="annee_scolaire" id="stage-anneescolaire" required>
                              <?php foreach ($toutesAnnees as $annee): ?>
                                  <option value="<?= h($annee) ?>" <?= $annee === $anneeSelectionnee ? 'selected' : '' ?>><?= h($annee) ?></option>
                              <?php endforeach; ?>
                          </select>
                      </label>
                      <label style="grid-column: span 2;">Sujet / Mission
                          <input type="text" name="sujet" id="stage-sujet" maxlength="512" placeholder="Ex: Développement d'une application web">
                      </label>
                      <label style="grid-column: span 2;">Entreprise / Organisme
                          <input type="text" name="entreprise" id="stage-entreprise" maxlength="255" placeholder="Ex: IPIRNET SARL">
                      </label>
                  </fieldset>
                  <fieldset class="modal-fieldset">
                      <legend style="color: #a855f7; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Calendrier</legend>
                      <label>Date début    <input type="date" name="date_debut"      id="stage-datedebut"></label>
                      <label>Date fin      <input type="date" name="date_fin"        id="stage-datefin"></label>
                      <label>Date soutenance <input type="date" name="date_soutenance" id="stage-soutenance"></label>
                      <label>Jury / Modalités <input type="text" name="jury" id="stage-jury" placeholder="Ex: Mr. Dupont, Mme. Martin"></label>
                  </fieldset>
              </div>
              <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 1rem;">
                  <button type="button" class="btn btn-outline" onclick="fermerModal()">Annuler</button>
                  <button type="submit" class="btn btn-primary" id="stage-save-btn">
                      <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                  </button>
              </div>
          </form>
      </div>
  </div>

  <script>
  // ── Cascade de filtres : soumet le formulaire à chaque changement ─────────
  const fAnnee   = document.getElementById('f-annee');
  const fFiliere = document.getElementById('f-filiere');
  const fNiveau  = document.getElementById('f-niveau');
  const fClasse  = document.getElementById('f-classe');
  const filterForm = document.getElementById('stages-filter-form');

  const cascadeFiltre = (el) => {
      // Réinitialiser les filtres dépendants en aval
      if (el === fAnnee || el === fFiliere) { fNiveau.value = ''; fClasse.value = ''; }
      if (el === fNiveau) { fClasse.value = ''; }
      filterForm.submit();
  };

  fAnnee.addEventListener('change',   () => cascadeFiltre(fAnnee));
  fFiliere.addEventListener('change', () => cascadeFiltre(fFiliere));
  fNiveau.addEventListener('change',  () => cascadeFiltre(fNiveau));
  fClasse.addEventListener('change',  () => cascadeFiltre(fClasse));

  // ── Ouverture de la modale (mode ajout ou édition) ───────────────────────
  function openStageModal(stg, sid, niveauNum, hasStage, hasPFE) {
      document.getElementById('modal-quick-stage').style.display = 'flex';
      document.getElementById('stage-sid').value      = sid;
      document.getElementById('stage-edit-id').value  = stg ? stg.id_stage : '';
      document.getElementById('stage-modal-title').textContent =
          stg ? 'Modifier le Stage' : 'Ajouter un Stage / PFE';

      // Construire la liste des types disponibles selon le niveau et les stages déjà présents
      const typeSelect = document.getElementById('stage-type');
      typeSelect.innerHTML  = '';
      typeSelect.disabled   = false;

      if (stg) {
          // Mode édition : le type est fixe et non modifiable
          const opt = document.createElement('option');
          opt.value = stg.type_stage;
          opt.textContent = stg.type_stage === 'pfe' ? 'PFE' : 'Stage en entreprise';
          typeSelect.appendChild(opt);
          typeSelect.disabled = true;
      } else {
          // Mode ajout : proposer uniquement les types non encore déposés
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

      // Pré-remplir les champs avec les données existantes (mode édition)
      document.getElementById('stage-anneescolaire').value = stg ? stg.annee_scolaire : '<?= h($anneeSelectionnee) ?>';
      document.getElementById('stage-sujet').value         = stg ? (stg.sujet       || '') : '';
      document.getElementById('stage-entreprise').value    = stg ? (stg.entreprise   || '') : '';
      document.getElementById('stage-datedebut').value     = stg ? (stg.date_debut   || '') : '';
      document.getElementById('stage-datefin').value       = stg ? (stg.date_fin     || '') : '';
      document.getElementById('stage-soutenance').value    = stg ? (stg.date_soutenance || '') : '';
      document.getElementById('stage-jury').value          = stg ? (stg.jury         || '') : '';
  }

  /** Ferme la modale de saisie du stage. */
  function fermerModal() {
      document.getElementById('modal-quick-stage').style.display = 'none';
  }

  // Fermeture au clic sur l'arrière-plan
  document.getElementById('modal-quick-stage').addEventListener('click', function(e) {
      if (e.target === this) fermerModal();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });

  // ── Soumission AJAX du formulaire stage ──────────────────────────────────
  // Après succès, le DOM est mis à jour en place — aucun rechargement de page.
  function submitStage(e) {
      e.preventDefault();
      const btn = document.getElementById('stage-save-btn');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

      const fd = new FormData(document.getElementById('stage-form'));
      const sid = parseInt(document.getElementById('stage-sid').value);

      fetch('stages.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
              if (res.success) {
                  fermerModal();
                  showToast(res.msg, 'success');
                  // Mettre à jour la ligne du tableau sans rechargement
                  if (res.stages !== undefined) rafraichirLigne(sid, res.stages);
              } else {
                  showToast(res.msg, 'error');
              }
          })
          .catch(err => {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
              showToast('Erreur réseau : ' + err.message, 'error');
          });
  }

  // ── Suppression AJAX d'un stage ──────────────────────────────────────────
  // Après succès, la mini-fiche est retirée du DOM et le statut est recalculé.
  function deleteStage(idStage, sid) {
      if (!confirm('Supprimer ce stage définitivement ?')) return;

      const tr = document.getElementById('row-' + sid);
      const annee = tr ? tr.dataset.annee : '';

      const fd = new FormData();
      fd.append('quick_delete_stage', '1');
      fd.append('id_stage',     idStage);
      fd.append('id_stagiaire', sid);
      fd.append('annee_scolaire', annee);

      fetch('stages.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
              if (res.success) {
                  showToast(res.msg, 'success');
                  if (res.stages !== undefined) rafraichirLigne(sid, res.stages);
              } else {
                  showToast(res.msg, 'error');
              }
          })
          .catch(err => showToast('Erreur réseau : ' + err.message, 'error'));
  }

  // ── Mise à jour DOM d'une ligne après save ou delete ────────────────────
  /**
   * Recalcule et redessine la cellule Documents, le badge Statut et le
   * bouton Ajouter d'une ligne de tableau à partir du tableau stages retourné
   * par le serveur, sans rechargement de page.
   *
   * @param {number} sid     - id_stagiaire de la ligne à mettre à jour
   * @param {Array}  stages  - tableau d'objets stage retourné par le serveur
   */
  function rafraichirLigne(sid, stages) {
      const tr = document.getElementById('row-' + sid);
      if (!tr) return;

      const niveauNum = parseInt(tr.dataset.niveauNum || '1');
      const annee     = tr.dataset.annee || '';

      // Recalculer has_stage / has_pfe
      const aStageEntreprise = stages.some(s => s.type_stage === 'stage_entreprise');
      const aPFE             = stages.some(s => s.type_stage === 'pfe');

      // Recalculer statut et libellé selon le niveau
      let statut, recap;
      if (niveauNum === 1) {
          statut = aStageEntreprise ? 'complet'  : 'manquant';
          recap  = aStageEntreprise ? '✅ Stage validé' : '🔴 Stage requis';
      } else {
          if (aStageEntreprise && aPFE) {
              statut = 'complet'; recap = '✅ Stage & PFE validés';
          } else if (aStageEntreprise || aPFE) {
              statut = 'partiel';
              recap  = (!aStageEntreprise ? '🔴 Stage' : '') + (!aPFE ? ' 🔴 PFE' : '') + ' manquant';
          } else {
              statut = 'vide'; recap = '❌ Aucun document';
          }
      }

      // Icône du badge selon le statut
      const icone = statut === 'complet' ? 'fa-check' : (statut === 'partiel' ? 'fa-hourglass-half' : 'fa-xmark');
      tr.querySelector('.col-statut').innerHTML =
          `<span class="status-badge status-${statut}"><i class="fa-solid ${icone}"></i> ${recap}</span>`;

      // Cellule Documents : reconstruire les mini-fiches
      const docsCell = tr.querySelector('.col-docs');
      if (stages.length === 0) {
          docsCell.innerHTML = '<span style="font-size:0.8rem; color:#3f3f46; font-style:italic;">Aucune donnée saisie</span>';
      } else {
          docsCell.innerHTML = stages.map(stg => construireMiniCarte(stg, sid)).join('');
      }

      // Cellule Actions : afficher / masquer le bouton Ajouter
      const peutAjouter = niveauNum === 1 ? !aStageEntreprise : !(aStageEntreprise && aPFE);
      const actCell = tr.querySelector('.col-actions');
      if (peutAjouter) {
          actCell.innerHTML = `<button class="btn btn-primary small"
              onclick='openStageModal(null, ${sid}, ${niveauNum}, ${aStageEntreprise}, ${aPFE})'
              style="background:rgba(168,85,247,0.1); color:#a855f7; border:1px solid rgba(168,85,247,0.2);">
              <i class="fa-solid fa-plus-circle"></i> Ajouter
          </button>`;
      } else {
          actCell.innerHTML = '';
      }

      // Flash de confirmation visuel sur la ligne
      tr.style.transition  = 'background .4s';
      tr.style.background  = 'rgba(168,85,247,0.12)';
      setTimeout(() => { tr.style.background = ''; }, 1400);
  }

  /**
   * Génère le HTML d'une mini-fiche stage pour la cellule Documents.
   * Doit rester cohérent avec le rendu PHP équivalent ci-dessus.
   */
  function construireMiniCarte(stg, sid) {
      const estPFE  = stg.type_stage === 'pfe';
      const typeLabel = estPFE ? 'PFE' : 'Stage';
      const cssClass  = estPFE ? 'pfe' : 'stage';
      const entreprise = stg.entreprise || 'Non spécifié';
      const stgJson    = JSON.stringify(stg).replace(/'/g, "\\'");
      return `<div class="mini-stage-card ${cssClass}">
          <div style="display:flex; flex-direction:column;">
              <strong style="color:#e4e4e7; font-size:0.72rem; text-transform:uppercase;">${typeLabel}</strong>
              <span style="color:#a1a1aa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">${entreprise}</span>
          </div>
          <div style="display:flex; gap:6px;">
              <button class="action-btn" onclick='openStageModal(${stgJson}, ${sid})' title="Modifier">
                  <i class="fa-solid fa-pen-to-square" style="font-size:0.7rem;"></i>
              </button>
              <a href="print_convention_stage.php?id=${stg.id_stage}" target="_blank" class="action-btn print" title="Imprimer Convention">
                  <i class="fa-solid fa-file-pdf" style="font-size:0.7rem;"></i>
              </a>
              <button class="action-btn" onclick='deleteStage(${stg.id_stage}, ${sid})' title="Supprimer" style="color:#ef4444;">
                  <i class="fa-solid fa-trash" style="font-size:0.7rem;"></i>
              </button>
          </div>
      </div>`;
  }

  // ── Notifications toast ──────────────────────────────────────────────────
  function showToast(msg, type) {
      const t = document.createElement('div');
      t.className   = 'gds-toast ' + (type || 'info');
      t.textContent = msg;
      document.body.appendChild(t);
      const duree = type === 'error' ? 5000 : 3500;
      setTimeout(() => {
          t.style.opacity    = '0';
          t.style.transition = 'opacity .3s';
          setTimeout(() => t.remove(), 300);
      }, duree);
  }

  // ── Mise en évidence d'un stagiaire à l'arrivée depuis le hub ────────────
  (function() {
      const sidHighlight = <?= (int)($_GET['highlight'] ?? 0) ?>;
      if (sidHighlight > 0) {
          const tr = document.getElementById('row-' + sidHighlight);
          if (tr) {
              setTimeout(function() {
                  tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  tr.style.transition   = 'background 0.2s, outline 0.2s';
                  tr.style.background   = 'rgba(168,85,247,0.22)';
                  tr.style.outline      = '2px solid rgba(168,85,247,0.6)';
                  tr.style.borderRadius = '6px';
                  setTimeout(function() { tr.style.background = ''; tr.style.outline = ''; }, 2800);
              }, 300);
          }
      }
  }());
  </script>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  