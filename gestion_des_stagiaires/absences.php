<?php
  /**
   * absences.php — Gestion centralisée des absences des stagiaires
   *
   * Fonctionnalités :
   *   - Affichage des absences par classe avec filtres (année, filière, niveau, classe, période)
   *   - Ajout / modification d'une absence individuelle (modal AJAX)
   *   - Suppression d'une absence (Directeur uniquement)
   *   - Marquage en masse comme absent (bulk)
   *   - Justification en masse avec prévisualisation par absence (Directeur uniquement)
   *   - Détail annuel des absences par stagiaire (modal AJAX)
   *   - Toutes les actions AJAX mettent à jour le DOM sans rechargement de page
   *
   * Tables : absences, stagiaires, classes, filieres, modules
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  $pageTitle = 'Gestion des Absences';
  $curPage   = 'absences';


  // ============================================================
  //  SECTION 1 : Chargement AJAX du détail des absences d'un stagiaire
  //  Réponse JSON — appelé depuis openDetailModal() en JS
  // ============================================================

  if (isset($_GET['action']) && $_GET['action'] === 'get_student_absences') {
      header('Content-Type: application/json');

      $idStagiaire = (int)($_GET['id_stagiaire'] ?? 0);
      $dateDebut   = trim((string)($_GET['date_from'] ?? ''));
      $dateFin     = trim((string)($_GET['date_to']   ?? ''));

      if ($idStagiaire <= 0) { echo json_encode([]); exit; }

      $clausesWhere = ['a.id_stagiaire = ?'];
      $parametres   = [$idStagiaire];

      if ($dateDebut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
          $clausesWhere[] = 'a.date_absence >= ?';
          $parametres[]   = $dateDebut;
      }
      if ($dateFin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
          $clausesWhere[] = 'a.date_absence <= ?';
          $parametres[]   = $dateFin;
      }

      $requeteDetail = $pdo->prepare(
          'SELECT a.*, m.nom_module
             FROM absences a
             LEFT JOIN modules m ON m.id_module = a.id_module
            WHERE ' . implode(' AND ', $clausesWhere) .
          ' ORDER BY a.date_absence DESC, a.heure_debut DESC'
      );
      $requeteDetail->execute($parametres);
      echo json_encode($requeteDetail->fetchAll());
      exit;
  }

  // ── SECTION 1b : Prévisualisation des absences à justifier (GET, Directeur) ──
  if (isset($_GET['action']) && $_GET['action'] === 'preview_bulk_justify') {
      header('Content-Type: application/json');
      if (!gds_is_directeur()) {
          echo json_encode(['success' => false, 'error' => 'Accès refusé.']); exit;
      }

      $idsStagiairesPreview = array_filter(array_map('intval', (array)($_GET['ids'] ?? [])));
      $datePrevDebut        = trim((string)($_GET['date_from'] ?? ''));
      $datePrevFin          = trim((string)($_GET['date_to']   ?? ''));

      if (empty($idsStagiairesPreview)) { echo json_encode([]); exit; }

      if ($datePrevDebut !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $datePrevDebut)) $datePrevDebut = '';
      if ($datePrevFin   !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $datePrevFin))   $datePrevFin   = '';

      $phPreview       = implode(',', array_fill(0, count($idsStagiairesPreview), '?'));
      $clausesDatePrev = '';
      $paramDatePrev   = [];
      if ($datePrevDebut !== '') { $clausesDatePrev .= ' AND a.date_absence >= ?'; $paramDatePrev[] = $datePrevDebut; }
      if ($datePrevFin   !== '') { $clausesDatePrev .= ' AND a.date_absence <= ?'; $paramDatePrev[] = $datePrevFin; }

      $requetePreview = $pdo->prepare(
          "SELECT a.id_absence, a.date_absence, a.heure_debut, a.heure_fin,
                  a.id_stagiaire, UPPER(s.nom) AS nom, s.prenom, m.nom_module
             FROM absences a
             JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
             LEFT JOIN modules m ON m.id_module = a.id_module
            WHERE a.est_justifiee = 0
              AND a.id_stagiaire IN ($phPreview)
              $clausesDatePrev
            ORDER BY s.nom, s.prenom, a.date_absence DESC"
      );
      $requetePreview->execute(array_merge(array_values($idsStagiairesPreview), $paramDatePrev));
      $lignesPreview = $requetePreview->fetchAll();

      $groupes = [];
      foreach ($lignesPreview as $ligneAbs) {
          $sidPreview = (int)$ligneAbs['id_stagiaire'];
          if (!isset($groupes[$sidPreview])) {
              $groupes[$sidPreview] = [
                  'id_stagiaire' => $sidPreview,
                  'nom'          => $ligneAbs['nom'],
                  'prenom'       => $ligneAbs['prenom'],
                  'absences'     => [],
              ];
          }
          $groupes[$sidPreview]['absences'][] = [
              'id_absence'   => (int)$ligneAbs['id_absence'],
              'date_absence' => $ligneAbs['date_absence'],
              'heure_debut'  => $ligneAbs['heure_debut'] ?? '',
              'heure_fin'    => $ligneAbs['heure_fin']   ?? '',
              'nom_module'   => $ligneAbs['nom_module']  ?? '',
          ];
      }

      echo json_encode(array_values($groupes));
      exit;
  }

  // ── SECTION 1c : Liste des stagiaires avec absences non justifiées (pour modale billets) ──
  if (isset($_GET['action']) && $_GET['action'] === 'get_stagiaires_non_justifies') {
      header('Content-Type: application/json');

      try {
          $idClasseBillets  = (int)($_GET['id_classe'] ?? 0);
          $rechercheBillets = trim((string)($_GET['q'] ?? ''));
          $modeRecent       = (int)($_GET['recent'] ?? 0);

          $clausesBillets = [];
          $paramsBillets  = [];

          if ($idClasseBillets > 0) {
              $clausesBillets[] = 's.id_classe = ?';
              $paramsBillets[]  = $idClasseBillets;
          }

          if ($rechercheBillets !== '') {
              $clausesBillets[] = '(UPPER(s.nom) LIKE ? OR UPPER(s.prenom) LIKE ? OR s.num_inscri LIKE ? OR s.cin LIKE ?)';
              $like = '%' . strtoupper($rechercheBillets) . '%';
              $paramsBillets = array_merge($paramsBillets, [$like, $like, '%'.$rechercheBillets.'%', '%'.$rechercheBillets.'%']);
          }

          $whereBillets = $clausesBillets ? 'WHERE ' . implode(' AND ', $clausesBillets) : '';

          $sqlBillets = "SELECT s.id_stagiaire, s.num_inscri, UPPER(s.nom) AS nom, s.prenom,
                                COALESCE(s.cin, '') AS cin,
                                (SELECT COUNT(*) FROM absences a WHERE a.id_stagiaire = s.id_stagiaire AND a.est_justifiee = 0) AS nb_non_justifiees
                           FROM stagiaires s
                           $whereBillets
                          ORDER BY s.nom, s.prenom";

          $stmtBillets = $pdo->prepare($sqlBillets);
          $stmtBillets->execute($paramsBillets);
          $lignesBillets = $stmtBillets->fetchAll();

          if ($modeRecent) {
              $lignesBillets = array_values(array_filter($lignesBillets, fn($l) => (int)$l['nb_non_justifiees'] > 0));
          }

          echo json_encode($lignesBillets);
      } catch (\Throwable $e) {
          echo json_encode(['error' => $e->getMessage()]);
      }
      exit;
  }


  // ============================================================
  //  SECTION 2 : Gestionnaires POST (toutes les réponses sont JSON)
  // ============================================================

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
      header('Content-Type: application/json');

      if (isset($_POST['save_absence'])) {
          $idStagiaire  = (int)($_POST['id_stagiaire']   ?? 0);
          $idAbsence    = (int)($_POST['id_absence_edit'] ?? 0);

          if ($idAbsence > 0 && !gds_is_directeur()) {
              echo json_encode(['success' => false, 'error' => 'Action réservée au Directeur.']);
              exit;
          }

          $dateAbsence    = trim((string)($_POST['date_absence'] ?? ''));
          $heureDebut     = trim((string)($_POST['heure_debut']  ?? '')) ?: null;
          $heureFin       = trim((string)($_POST['heure_fin']    ?? '')) ?: null;
          $justificatif   = trim((string)($_POST['justificatif'] ?? '')) ?: null;
          $estJustifiee   = (isset($_POST['est_justifiee']) && gds_is_directeur()) ? 1 : 0;
          $idModule       = (int)($_POST['id_module'] ?? 0) ?: null;

          if ($idStagiaire <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAbsence)) {
              echo json_encode(['success' => false, 'error' => 'Données invalides.']);
              exit;
          }

          try {
              if ($idAbsence > 0) {
                  $pdo->prepare(
                      'UPDATE absences
                          SET date_absence = ?, heure_debut = ?, heure_fin = ?,
                              justificatif = ?, est_justifiee = ?, id_module = ?
                        WHERE id_absence = ? AND id_stagiaire = ?'
                  )->execute([$dateAbsence, $heureDebut, $heureFin, $justificatif, $estJustifiee, $idModule, $idAbsence, $idStagiaire]);
              } else {
                  $pdo->prepare(
                      'INSERT INTO absences
                          (date_absence, heure_debut, heure_fin, justificatif, est_justifiee, id_stagiaire, id_module)
                       VALUES (?, ?, ?, ?, ?, ?, ?)'
                  )->execute([$dateAbsence, $heureDebut, $heureFin, $justificatif, $estJustifiee, $idStagiaire, $idModule]);
                  $idAbsence = (int)$pdo->lastInsertId();
              }

              echo json_encode([
                  'success'       => true,
                  'id_absence'    => $idAbsence,
                  'date_absence'  => $dateAbsence,
                  'est_justifiee' => $estJustifiee,
                  'justificatif'  => $justificatif,
                  'heure_debut'   => $heureDebut,
                  'heure_fin'     => $heureFin,
              ]);
          } catch (\Throwable $e) {
              error_log('[absences.php save_absence] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => "Erreur lors de l'enregistrement."]);
          }
          exit;
      }

      if (isset($_POST['delete_absence'])) {
          if (!gds_is_directeur()) {
              echo json_encode(['success' => false, 'error' => 'Action réservée au Directeur.']);
              exit;
          }

          $idAbsence = (int)($_POST['id_absence'] ?? 0);
          if ($idAbsence <= 0) {
              echo json_encode(['success' => false, 'error' => "ID d'absence invalide."]);
              exit;
          }

          try {
              $requeteStatut = $pdo->prepare('SELECT est_justifiee FROM absences WHERE id_absence = ?');
              $requeteStatut->execute([$idAbsence]);
              $ligneStatut = $requeteStatut->fetch();
              $etaitJustifiee = $ligneStatut ? (int)$ligneStatut['est_justifiee'] : 0;

              $pdo->prepare('DELETE FROM absences WHERE id_absence = ?')->execute([$idAbsence]);
              echo json_encode(['success' => true, 'etait_justifiee' => $etaitJustifiee]);
          } catch (\Throwable $e) {
              error_log('[absences.php delete_absence] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression.']);
          }
          exit;
      }

      if (isset($_POST['bulk_mark_absent'])) {
          $dateBulk      = trim((string)($_POST['bulk_date'] ?? ''));
          $idsStagiaires = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
          $heureDebut    = trim((string)($_POST['heure_debut'] ?? '')) ?: null;
          $heureFin      = trim((string)($_POST['heure_fin']   ?? '')) ?: null;
          $idModule      = (int)($_POST['id_module'] ?? 0) ?: null;

          if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateBulk) || empty($idsStagiaires)) {
              echo json_encode(['success' => false, 'error' => 'Date ou sélection invalide.']);
              exit;
          }

          $nbInseres = 0;
          $nbIgnores = 0;

          $requeteVerif  = $pdo->prepare('SELECT COUNT(*) FROM absences WHERE id_stagiaire = ? AND date_absence = ? AND id_module <=> ?');
          $requeteInsert = $pdo->prepare('INSERT INTO absences (date_absence, heure_debut, heure_fin, est_justifiee, id_stagiaire, id_module) VALUES (?, ?, ?, 0, ?, ?)');

          try {
              $pdo->beginTransaction();
              foreach ($idsStagiaires as $idStag) {
                  $requeteVerif->execute([$idStag, $dateBulk, $idModule]);
                  if ((int)$requeteVerif->fetchColumn() > 0) {
                      $nbIgnores++;
                      continue;
                  }
                  $requeteInsert->execute([$dateBulk, $heureDebut, $heureFin, $idStag, $idModule]);
                  $nbInseres++;
              }
              $pdo->commit();
              echo json_encode(['success' => true, 'inserted' => $nbInseres, 'skipped' => $nbIgnores]);
          } catch (\Throwable $e) {
              $pdo->rollBack();
              error_log('[absences.php bulk_mark_absent] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => "Erreur lors de l'enregistrement en masse."]);
          }
          exit;
      }

      if (isset($_POST['bulk_justify'])) {
          if (!gds_is_directeur()) {
              echo json_encode(['success' => false, 'error' => 'Action réservée au Directeur.']);
              exit;
          }

          $idsAbsences   = array_filter(array_map('intval', (array)($_POST['absence_ids'] ?? [])));
          $idsStagiaires = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
          $justificatif  = trim((string)($_POST['justificatif'] ?? '')) ?: null;

          if (empty($idsAbsences)) {
              echo json_encode(['success' => false, 'error' => 'Aucune absence sélectionnée.']);
              exit;
          }
          if (empty($idsStagiaires)) {
              echo json_encode(['success' => false, 'error' => 'Paramètres de sécurité manquants.']);
              exit;
          }

          try {
              $phAbs  = implode(',', array_fill(0, count($idsAbsences),   '?'));
              $phStag = implode(',', array_fill(0, count($idsStagiaires), '?'));

              $requeteSecurite = $pdo->prepare(
                  "SELECT COUNT(*) FROM absences
                    WHERE id_absence IN ($phAbs)
                      AND id_stagiaire NOT IN ($phStag)"
              );
              $requeteSecurite->execute(array_merge(
                  array_values($idsAbsences),
                  array_values($idsStagiaires)
              ));

              if ((int)$requeteSecurite->fetchColumn() > 0) {
                  error_log('[absences.php bulk_justify] Tentative de justification non autorisée.');
                  echo json_encode(['success' => false, 'error' => 'Vérification de sécurité échouée.']);
                  exit;
              }

              $requeteUpdate = $pdo->prepare(
                  "UPDATE absences SET est_justifiee = 1, justificatif = ?
                    WHERE id_absence IN ($phAbs) AND est_justifiee = 0"
              );
              $requeteUpdate->execute(array_merge([$justificatif], array_values($idsAbsences)));

              $requeteBilan = $pdo->prepare(
                  "SELECT id_stagiaire, COUNT(*) AS nb
                     FROM absences
                    WHERE id_absence IN ($phAbs)
                    GROUP BY id_stagiaire"
              );
              $requeteBilan->execute(array_values($idsAbsences));
              $bilanParStag = [];
              foreach ($requeteBilan->fetchAll() as $ligneBilan) {
                  $bilanParStag[(int)$ligneBilan['id_stagiaire']] = (int)$ligneBilan['nb'];
              }

              echo json_encode([
                  'success'        => true,
                  'updated'        => $requeteUpdate->rowCount(),
                  'bilan_par_stag' => $bilanParStag,
              ]);
          } catch (\Throwable $e) {
              error_log('[absences.php bulk_justify] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => "Erreur lors de la justification."]);
          }
          exit;
      }
      echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
      exit;
  }


  // ============================================================
  //  SECTION 3 : Paramètres de filtrage (GET)
  // ============================================================

  $anneeSelectionnee  = trim((string)($_GET['annee']      ?? ''));
  $idFiliereSelecte   = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne  = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte    = (int)($_GET['id_classe']  ?? 0);
  $dateFiltreUnique   = trim((string)($_GET['date_filter'] ?? ''));

  if ($dateFiltreUnique !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFiltreUnique)) $dateFiltreUnique = '';

  // On conserve date_from/date_to pour les AJAX existants, on les alimente depuis le filtre unique
  $dateDebutFiltre    = $dateFiltreUnique;
  $dateFinFiltre      = $dateFiltreUnique;


  // ============================================================
  //  SECTION 4 : Surlignage (arrivée depuis le hub stagiaire)
  // ============================================================

  $highlightAid    = (int)($_GET['highlight']     ?? 0);
  $highlightRowSid = (int)($_GET['highlight_sid'] ?? 0);
  $highlightSid    = 0;
  $highlightNom    = '';

  if ($highlightAid > 0) {
      $requeteHighlight = $pdo->prepare(
          'SELECT s.id_stagiaire, UPPER(s.nom) AS nom, s.prenom
             FROM absences a
             JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
            WHERE a.id_absence = ?
            LIMIT 1'
      );
      $requeteHighlight->execute([$highlightAid]);
      $ligneHighlight = $requeteHighlight->fetch();
      if ($ligneHighlight) {
          $highlightSid = (int)$ligneHighlight['id_stagiaire'];
          $highlightNom = trim($ligneHighlight['nom'] . ' ' . $ligneHighlight['prenom']);
      }
  }


  // ============================================================
  //  SECTION 5 : Données en cascade (filière → niveau → classe → modules)
  // ============================================================

  $toutesLesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);

  if ($anneeSelectionnee === '') {
      $anneeSelectionnee = $_SESSION['global_annee_scolaire'] ?? ($toutesLesAnnees[0] ?? '');
  }

  $toutesLesFilieres = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f
        INNER JOIN classes c ON c.id_filiere = f.id_filiere
        ORDER BY f.nom_filiere"
  )->fetchAll();

  if ($idFiliereSelecte === 0 && !empty($toutesLesFilieres)) {
      $idFiliereSelecte = (int)$toutesLesFilieres[0]['id_filiere'];
  }

  $tousLesNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $requeteNiveaux = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere = ? AND annee_scolaire = ? ORDER BY niveau");
      $requeteNiveaux->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousLesNiveaux = $requeteNiveaux->fetchAll(PDO::FETCH_COLUMN);
      if (!empty($tousLesNiveaux) && !in_array($niveauSelectionne, $tousLesNiveaux, true)) {
          $niveauSelectionne = $tousLesNiveaux[0];
      }
  }

  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '' && $niveauSelectionne !== '') {
      $requeteClasses = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere = ? AND annee_scolaire = ? AND niveau = ? ORDER BY nom_classe");
      $requeteClasses->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $requeteClasses->fetchAll();

      $idsClassesValides = array_map('intval', array_column($toutesLesClasses, 'id_classe'));
      if (!empty($toutesLesClasses) && !in_array($idClasseSelecte, $idsClassesValides, true)) {
          $idClasseSelecte = (int)$toutesLesClasses[0]['id_classe'];
      }
  }

  $tousLesModules = [];
  if ($idFiliereSelecte > 0) {
      $requeteModules = $pdo->prepare("SELECT id_module, nom_module FROM modules WHERE id_filiere = ? ORDER BY nom_module");
      $requeteModules->execute([$idFiliereSelecte]);
      $tousLesModules = $requeteModules->fetchAll();
  }


  // ============================================================
  //  SECTION 6 : Données stagiaires et statistiques d'absences
  // ============================================================

  $stagiaires = [];
  $infoClasse = null;

  if ($idClasseSelecte > 0) {
      $requeteClasse = $pdo->prepare(
          "SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau
             FROM classes c
             JOIN filieres f ON f.id_filiere = c.id_filiere
            WHERE c.id_classe = ?"
      );
      $requeteClasse->execute([$idClasseSelecte]);
      $infoClasse = $requeteClasse->fetch();

      $parametresJointure = [];
      $conditionsJointure = 'a.id_stagiaire = s.id_stagiaire';

      if ($dateDebutFiltre !== '') {
          $conditionsJointure .= ' AND a.date_absence >= ?';
          $parametresJointure[] = $dateDebutFiltre;
      }
      if ($dateFinFiltre !== '') {
          $conditionsJointure .= ' AND a.date_absence <= ?';
          $parametresJointure[] = $dateFinFiltre;
      }

      $sqlStagiaires = "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
                               COUNT(a.id_absence)                       AS total_absences,
                               SUM(COALESCE(a.est_justifiee, 0))         AS nb_justifiees,
                               COUNT(a.id_absence) - SUM(COALESCE(a.est_justifiee, 0)) AS nb_non_justifiees,
                               MAX(a.date_absence)                       AS derniere_absence
                          FROM stagiaires s
                          LEFT JOIN absences a ON $conditionsJointure
                         WHERE s.id_classe = ?
                         GROUP BY s.id_stagiaire
                         ORDER BY s.nom, s.prenom";

      $parametresFinaux = array_merge($parametresJointure, [$idClasseSelecte]);

      $requeteStagiaires = $pdo->prepare($sqlStagiaires);
      $requeteStagiaires->execute($parametresFinaux);
      $stagiaires = $requeteStagiaires->fetchAll();
  }

  $totalAbsences      = array_sum(array_column($stagiaires, 'total_absences'));
  $totalJustifiees    = array_sum(array_column($stagiaires, 'nb_justifiees'));
  $totalNonJustifiees = $totalAbsences - $totalJustifiees;
  $nbAvecAbsences     = count(array_filter($stagiaires, fn($ligne) => (int)$ligne['total_absences'] > 0));

  require __DIR__ . '/includes/header.php';
?>

<style>
/* ── Cartes de filtres ────────────────────────────────────────────────── */
.abs-filter-card{background:linear-gradient(135deg,#1c1c20,#18181b);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.25);}
.abs-filter-card form{background:transparent;}
.abs-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;align-items:end;}
.abs-filter-grid label{display:flex;flex-direction:column;gap:0.35rem;font-size:0.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.abs-filter-grid select,.abs-filter-grid input[type="date"]{background:#09090b;border:1px solid rgba(255,255,255,0.12);color:#e4e4e7;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.9rem;width:100%;color-scheme:dark;-webkit-color-scheme:dark;transition:border-color .2s,box-shadow .2s;}
.abs-filter-grid select:disabled{opacity:0.4;cursor:not-allowed;}
.abs-filter-grid select:focus,.abs-filter-grid input:focus{outline:none;border-color:rgba(168,85,247,0.5);box-shadow:0 0 0 3px rgba(168,85,247,0.12);}

/* ── Panneau de session ────────────────────────────────────────────────── */
.session-panel{background:linear-gradient(135deg,rgba(168,85,247,.12),rgba(139,92,246,.06));border:1px solid rgba(168,85,247,.3);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 16px rgba(168,85,247,.08);}
.session-panel-title{font-size:.72rem;font-weight:700;color:#a855f7;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .9rem;display:flex;align-items:center;gap:.4rem;}
.session-panel-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.85rem;align-items:end;}
.session-panel-fields label{display:flex;flex-direction:column;gap:.3rem;font-size:.76rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.session-panel-fields select,
.session-panel-fields input[type="date"],
.session-panel-fields input[type="time"]{background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:8px;padding:.5rem .75rem;font-size:.88rem;width:100%;color-scheme:dark;-webkit-color-scheme:dark;box-sizing:border-box;transition:border-color .2s,box-shadow .2s;}
.session-panel-fields select:focus,
.session-panel-fields input:focus{outline:none;border-color:rgba(168,85,247,.5);box-shadow:0 0 0 3px rgba(168,85,247,.12);}
.session-panel-actions{display:flex;align-items:center;gap:.75rem;margin-top:1rem;flex-wrap:wrap;}
.session-counter{font-size:.82rem;font-weight:700;color:#c084fc;background:rgba(168,85,247,.12);border:1px solid rgba(168,85,247,.25);border-radius:8px;padding:.3rem .75rem;white-space:nowrap;}

/* ── Cartes statistiques ──────────────────────────────────────────────── */
.abs-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;}
.abs-stat-card{background:linear-gradient(135deg,#1c1c20,#18181b);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1rem 1.25rem;text-align:center;transition:border-color .2s,box-shadow .2s;}
.abs-stat-card:hover{border-color:rgba(168,85,247,.25);box-shadow:0 4px 16px rgba(168,85,247,.1);}
.abs-stat-val{font-size:2rem;font-weight:800;line-height:1;}
.abs-stat-lbl{font-size:0.75rem;color:#71717a;margin-top:0.3rem;text-transform:uppercase;letter-spacing:.05em;}

/* ── Tableau principal ────────────────────────────────────────────────── */
.abs-table-wrap{background:#18181b;border:1px solid rgba(255,255,255,0.08);border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.2);}
.abs-table-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.07);gap:.75rem;flex-wrap:wrap;}
.abs-table{width:100%;border-collapse:collapse;}
.abs-table th{padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#71717a;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);}
.abs-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:.88rem;color:#e4e4e7;vertical-align:middle;}
.abs-table tbody tr:hover td{background:rgba(168,85,247,.04);}
.abs-table tbody tr.row-selected td{background:rgba(168,85,247,.1);}
.abs-table .cb-col{width:44px;text-align:center;}
.abs-table input[type="checkbox"]{accent-color:#a855f7;width:18px;height:18px;cursor:pointer;}

/* ── Badges de statut ─────────────────────────────────────────────────── */
.badge-abs{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.73rem;font-weight:700;letter-spacing:.02em;}
.badge-abs.red{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}
.badge-abs.green{background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.18);}
.badge-abs.yellow{background:rgba(234,179,8,.12);color:#fde047;border:1px solid rgba(234,179,8,.18);}
.badge-abs.gray{background:rgba(113,113,122,.15);color:#a1a1aa;border:1px solid rgba(113,113,122,.2);}

/* ── Boutons ──────────────────────────────────────────────────────────── */
.btn-abs{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:all .18s cubic-bezier(.16,1,.3,1);}
.btn-abs.primary{background:linear-gradient(135deg,#9333ea,#a855f7);color:#fff;box-shadow:0 4px 14px rgba(168,85,247,.38);}.btn-abs.primary:hover{background:linear-gradient(135deg,#a855f7,#9333ea);box-shadow:0 6px 20px rgba(168,85,247,.52);transform:translateY(-1px);}
.btn-abs.ghost{background:linear-gradient(135deg,rgba(168,85,247,.14),rgba(139,92,246,.08));color:#c084fc;border:1px solid rgba(168,85,247,.32);}.btn-abs.ghost:hover{background:linear-gradient(135deg,rgba(168,85,247,.28),rgba(139,92,246,.18));border-color:rgba(168,85,247,.6);box-shadow:0 0 14px rgba(168,85,247,.28);transform:translateY(-1px);color:#d8b4fe;}
.btn-abs.danger{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}.btn-abs.danger:hover{background:rgba(239,68,68,.22);box-shadow:0 0 12px rgba(239,68,68,.2);transform:translateY(-1px);}
.btn-abs.sm{padding:3px 9px;font-size:.75rem;}

/* ── État vide ────────────────────────────────────────────────────────── */
.empty-state{text-align:center;padding:3.5rem 2rem;color:#52525b;}
.empty-state i{font-size:2.5rem;margin-bottom:1rem;display:block;color:#3f3f46;}

/* ── Modales ──────────────────────────────────────────────────────────── */
.abs-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:9999;display:none;align-items:center;justify-content:center;}
.abs-modal-card{background:#18181b;border:1px solid rgba(168,85,247,.18);border-radius:20px;padding:0;width:min(520px,95vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 32px 80px rgba(0,0,0,.85),0 0 0 1px rgba(168,85,247,.1);animation:absModalIn .28s cubic-bezier(.16,1,.3,1);}
@keyframes absModalIn{from{transform:translateY(18px) scale(.98);opacity:0;}to{transform:translateY(0) scale(1);opacity:1;}}
.abs-modal-header{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid rgba(168,85,247,.18);background:linear-gradient(135deg,rgba(168,85,247,.1),rgba(139,92,246,.05));position:relative;}
.abs-modal-header::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#7c3aed,#a855f7,#c084fc,#a855f7,#7c3aed);border-radius:20px 20px 0 0;}
.abs-modal-header h3{margin:0;font-size:1.05rem;font-weight:700;color:#f4f4f5;}
.abs-modal-body{padding:1.5rem;overflow-y:auto;}
.abs-modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:flex-end;gap:.75rem;}

/* ── Formulaire dans la modale ────────────────────────────────────────── */
.abs-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.abs-form-grid label,.abs-form-full label{display:flex;flex-direction:column;gap:.3rem;font-size:.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.abs-form-full{grid-column:span 2;}
.abs-form-grid input,.abs-form-grid select,.abs-form-full input,.abs-form-full select,.abs-form-full textarea{background:#09090b;border:1px solid rgba(255,255,255,.1);color:#e4e4e7;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;width:100%;box-sizing:border-box;transition:border-color .2s,box-shadow .2s;}
.abs-form-grid input:focus,.abs-form-full input:focus,.abs-form-full select:focus{outline:none;border-color:rgba(168,85,247,.5);box-shadow:0 0 0 3px rgba(168,85,247,.1);}

/* ── Boutons icônes compacts ──────────────────────────────────────────── */
.icon-btn-sm{background:none;border:1px solid rgba(255,255,255,.08);border-radius:6px;color:#a1a1aa;padding:4px 8px;cursor:pointer;font-size:.8rem;transition:all .15s;}
.icon-btn-sm:hover{background:rgba(168,85,247,.1);color:#c084fc;border-color:rgba(168,85,247,.3);}
.icon-btn-sm.danger:hover{background:rgba(239,68,68,.12);color:#fca5a5;border-color:rgba(239,68,68,.25);}

/* ── Dialog de confirmation ──────────────────────────────────────────── */
.gds-confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:99999;display:none;align-items:center;justify-content:center;}
.gds-confirm-card{background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:16px;padding:2rem 2rem 1.5rem;width:min(380px,92vw);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.6);}
.gds-confirm-icon{font-size:2rem;margin-bottom:.75rem;}
.gds-confirm-msg{color:#e4e4e7;font-size:.95rem;margin:0 0 1.5rem;line-height:1.55;}
.gds-confirm-btns{display:flex;gap:.75rem;justify-content:center;}

/* ── Notifications toast ──────────────────────────────────────────────── */
.gds-toast{position:fixed;top:1.25rem;right:1.25rem;z-index:99998;border-radius:12px;padding:.85rem 1.35rem;font-weight:600;font-size:.88rem;box-shadow:0 8px 32px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04);border:1px solid;max-width:380px;line-height:1.45;animation:toastIn .22s cubic-bezier(.16,1,.3,1);}
@keyframes toastIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.gds-toast.success{background:#18181b;border-color:rgba(34,197,94,.4);color:#86efac;}
.gds-toast.error{background:#18181b;border-color:rgba(239,68,68,.4);color:#fca5a5;}
.gds-toast.info{background:#18181b;border-color:rgba(168,85,247,.4);color:#c084fc;}

/* ── Lignes du détail d'absence ───────────────────────────────────────── */
.detail-abs-row{display:flex;align-items:center;gap:.75rem;padding:.6rem .5rem;border-bottom:1px solid rgba(255,255,255,.05);font-size:.85rem;}
.detail-abs-row:last-child{border-bottom:none;}

/* ── En-tête de page ──────────────────────────────────────────────────── */
.page-header-abs{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.page-header-abs h1{font-size:1.6rem;font-weight:800;color:#f4f4f5;margin:0;}
.page-header-abs p{margin:.3rem 0 0;font-size:.88rem;color:#71717a;}

/* ── Drawer justification ──────────────────────────────────────────────── */
.justify-drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:9990;display:none;justify-content:flex-end;}
.justify-drawer-overlay.open{display:flex;}
.justify-drawer{background:#18181b;border-left:1px solid rgba(168,85,247,.25);width:min(480px,100vw);max-height:100vh;display:flex;flex-direction:column;box-shadow:-16px 0 60px rgba(0,0,0,.6);animation:drawerIn .28s cubic-bezier(.16,1,.3,1);}
@keyframes drawerIn{from{transform:translateX(40px);opacity:.6;}to{transform:translateX(0);opacity:1;}}
.justify-drawer-header{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid rgba(168,85,247,.18);background:linear-gradient(135deg,rgba(168,85,247,.1),rgba(139,92,246,.05));position:relative;flex-shrink:0;}
.justify-drawer-header::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#7c3aed,#a855f7,#c084fc,#a855f7,#7c3aed);}
.justify-drawer-header h3{margin:0;font-size:1rem;font-weight:700;color:#f4f4f5;}
.justify-drawer-motif{padding:.9rem 1.25rem;border-bottom:1px solid rgba(168,85,247,.15);background:rgba(168,85,247,.04);flex-shrink:0;display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap;}
.justify-drawer-motif label{flex:1;min-width:200px;display:flex;flex-direction:column;gap:.3rem;font-size:.76rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.justify-drawer-motif input{background:#09090b;border:1px solid rgba(168,85,247,.35);color:#e4e4e7;border-radius:8px;padding:.45rem .75rem;font-size:.9rem;width:100%;box-sizing:border-box;}
.justify-drawer-motif input:focus{outline:none;border-color:rgba(168,85,247,.6);box-shadow:0 0 0 3px rgba(168,85,247,.12);}
.justify-drawer-body{flex:1;overflow-y:auto;padding:1rem 1.25rem;}
.justify-drawer-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;gap:.75rem;flex-shrink:0;}

/* ── Lignes de prévisualisation ───────────────────────────────────────── */
.prev-stag-groupe{margin-bottom:.6rem;border:1px solid rgba(168,85,247,.14);border-radius:10px;overflow:hidden;}
.prev-stag-header{display:flex;justify-content:space-between;align-items:center;padding:.6rem .9rem;background:rgba(168,85,247,.08);border-bottom:1px solid rgba(168,85,247,.12);}
.prev-abs-liste{display:flex;flex-direction:column;gap:0;}
.prev-abs-row{display:flex;align-items:center;gap:.75rem;padding:.5rem .9rem;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.04);transition:background .12s;}
.prev-abs-row:last-child{border-bottom:none;}
.prev-abs-row:hover{background:rgba(168,85,247,.07);}
.prev-abs-info{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;flex:1;}
.prev-abs-date{font-weight:700;color:#e4e4e7;font-size:.85rem;}
.prev-abs-module{font-size:.78rem;color:#71717a;background:rgba(255,255,255,.05);padding:1px 7px;border-radius:12px;}
.prev-abs-heures{font-size:.77rem;color:#52525b;margin-left:.25rem;}
</style>

<div style="max-width:1200px;margin:0 auto;padding:1.5rem;">

  <!-- En-tête de page -->
  <div class="page-header-abs">
    <div>
      <h1><i class="fa-solid fa-user-clock" style="color:#a855f7;margin-right:.5rem;"></i>Gestion des Absences</h1>
      <p>Système centralisé de gestion des absences par classe</p>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
      <a href="print_feuille_appel.php" target="_blank"
         style="display:inline-flex;align-items:center;gap:6px;padding:.52rem 1rem;border-radius:9px;font-size:.82rem;font-weight:600;
                background:rgba(168,85,247,.14);color:#c084fc;border:1px solid rgba(168,85,247,.3);text-decoration:none;transition:all .18s;"
         onmouseover="this.style.background='rgba(168,85,247,.28)'"
         onmouseout="this.style.background='rgba(168,85,247,.14)'"
         title="Générer une feuille d'appel A4 pour le professeur">
        <i class="fa-solid fa-print" style="font-size:.9rem;"></i> Feuille d'appel
      </a>
      <button type="button" onclick="ouvrirModalBillets()"
         style="display:inline-flex;align-items:center;gap:6px;padding:.52rem 1rem;border-radius:9px;font-size:.82rem;font-weight:600;
                background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.3);cursor:pointer;transition:all .18s;"
         onmouseover="this.style.background='rgba(34,197,94,.22)'"
         onmouseout="this.style.background='rgba(34,197,94,.12)'"
         title="Imprimer des billets d'excuse pour plusieurs stagiaires">
        <i class="fa-solid fa-ticket" style="font-size:.9rem;"></i> Imprimer les billets
      </button>
      <a href="print_releve_absences.php<?= $idClasseSelecte > 0 ? '?id_classe='.$idClasseSelecte.'&annee='.urlencode($anneeSelectionnee) : '' ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:6px;padding:.52rem 1rem;border-radius:9px;font-size:.82rem;font-weight:600;
                background:rgba(251,191,36,.1);color:#fde047;border:1px solid rgba(251,191,36,.3);text-decoration:none;transition:all .18s;"
         onmouseover="this.style.background='rgba(251,191,36,.2)'"
         onmouseout="this.style.background='rgba(251,191,36,.1)'"
         title="Imprimer le relevé d'absences par classe et par mois">
        <i class="fa-solid fa-file-lines" style="font-size:.9rem;"></i> Relevé d'absences
      </a>
    </div>
  </div>

  <!-- Message flash -->
  <?php gds_render_flash(); ?>

  <!-- Carte de filtres -->
  <div class="abs-filter-card">
    <form method="get" action="absences.php" id="filter-form">
      <div class="abs-filter-grid">

        <label>Année scolaire
          <select name="annee" onchange="this.form.submit()">
            <option value="">— Toutes —</option>
            <?php foreach ($toutesLesAnnees as $annee): ?>
              <option value="<?= h($annee) ?>" <?= $anneeSelectionnee === $annee ? 'selected' : '' ?>><?= h($annee) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Filière
          <select name="id_filiere" onchange="this.form.submit()" <?= $anneeSelectionnee === '' ? 'disabled' : '' ?>>
            <option value="0">— Choisir —</option>
            <?php foreach ($toutesLesFilieres as $filiere): ?>
              <option value="<?= (int)$filiere['id_filiere'] ?>" <?= $idFiliereSelecte === (int)$filiere['id_filiere'] ? 'selected' : '' ?>><?= h(gds_filiere_code((string)$filiere['nom_filiere'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Niveau
          <select name="niveau" onchange="this.form.submit()" <?= ($idFiliereSelecte === 0 || $anneeSelectionnee === '') ? 'disabled' : '' ?>>
            <?php if (empty($tousLesNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
            <?php foreach ($tousLesNiveaux as $niveau): ?>
              <option value="<?= h($niveau) ?>" <?= $niveauSelectionne === $niveau ? 'selected' : '' ?>><?= h($niveau) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Classe
          <select name="id_classe" onchange="this.form.submit()" <?= ($niveauSelectionne === '' || $idFiliereSelecte === 0) ? 'disabled' : '' ?>>
            <?php if (empty($toutesLesClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
            <?php foreach ($toutesLesClasses as $classe): ?>
              <option value="<?= (int)$classe['id_classe'] ?>" <?= $idClasseSelecte === (int)$classe['id_classe'] ? 'selected' : '' ?>><?= h($classe['nom_classe']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Date
          <input type="date" name="date_filter" value="<?= h($dateFiltreUnique) ?>" <?= $idClasseSelecte === 0 ? 'disabled' : '' ?>>
        </label>

        <label style="justify-content:flex-end;">
          <button type="submit" class="btn-abs primary" style="width:100%;justify-content:center;padding:.6rem;">
            <i class="fa-solid fa-filter"></i> Filtrer
          </button>
        </label>

      </div>
    </form>
  </div>

  <?php if ($idClasseSelecte === 0): ?>
  <div class="empty-state">
    <i class="fa-solid fa-users-rectangle"></i>
    <p style="font-size:1.05rem;font-weight:600;color:#71717a;">Sélectionnez une classe pour afficher les absences</p>
    <p style="font-size:.85rem;color:#3f3f46;">Utilisez les filtres ci-dessus : Année → Filière → Niveau → Classe</p>
  </div>

  <?php else: ?>

  <!-- ─── PANNEAU DE SESSION ──────────────────────────────────────────────── -->
  <div class="session-panel" id="session-panel">
    <div class="session-panel-title">
      <i class="fa-solid fa-calendar-check"></i> Session d'appel — définissez la session, puis cochez les absents dans le tableau
    </div>
    <div class="session-panel-fields">

      <label>Module *
        <select id="bulk-module" style="color-scheme:dark;">
          <option value="0">— Choisir un module —</option>
          <?php foreach ($tousLesModules as $module): ?>
            <option value="<?= (int)$module['id_module'] ?>"><?= h(gds_module_label((string)$module['nom_module'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Date *
        <input type="date" id="bulk-date" value="<?= date('Y-m-d') ?>" style="color-scheme:dark;">
      </label>

      <label>Heure début
        <input type="time" id="bulk-hdebut" style="color-scheme:dark;">
      </label>

      <label>Heure fin
        <input type="time" id="bulk-hfin" style="color-scheme:dark;">
      </label>

    </div>

    <div class="session-panel-actions">
      <span class="session-counter" id="session-counter">0 sélectionné(s)</span>
      <button type="button" class="btn-abs danger" id="btn-enregistrer"
        onclick="faireMarquageAbsentEnMasse()"
        style="padding:.6rem 1.25rem;font-size:.85rem;">
        <i class="fa-solid fa-user-slash"></i>
        <span id="btn-enregistrer-label">Enregistrer les absences</span>
      </button>
      <?php if (gds_is_directeur()): ?>
      <button type="button" class="btn-abs ghost" id="btn-justifier"
        onclick="ouvrirDrawerJustif()"
        style="padding:.6rem 1.25rem;font-size:.85rem;display:none;">
        <i class="fa-solid fa-certificate"></i> Justifier la sélection
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Statistiques récapitulatives -->
  <div class="abs-stats-row">
    <div class="abs-stat-card" style="border-top:3px solid #a855f7;">
      <div class="abs-stat-val" style="color:#c084fc;"><?= count($stagiaires) ?></div>
      <div class="abs-stat-lbl">Stagiaires</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #ef4444;">
      <div class="abs-stat-val" style="color:#fca5a5;"><?= $totalAbsences ?></div>
      <div class="abs-stat-lbl">Absences <?= $dateFiltreUnique ? '(ce jour)' : '(total)' ?></div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #22c55e;">
      <div class="abs-stat-val" style="color:#86efac;"><?= $totalJustifiees ?></div>
      <div class="abs-stat-lbl">Justifiées</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #f59e0b;">
      <div class="abs-stat-val" style="color:#fde047;"><?= $totalNonJustifiees ?></div>
      <div class="abs-stat-lbl">Non justifiées</div>
    </div>
    <div class="abs-stat-card" style="border-top:3px solid #a855f7;">
      <div class="abs-stat-val" style="color:#c084fc;"><?= $nbAvecAbsences ?></div>
      <div class="abs-stat-lbl">Avec absences</div>
    </div>
  </div>

  <!-- Tableau des stagiaires -->
  <div class="abs-table-wrap">
    <div class="abs-table-header">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <input type="checkbox" id="select-all" title="Tout sélectionner" style="accent-color:#a855f7;width:18px;height:18px;cursor:pointer;">
        <span style="font-size:.85rem;font-weight:700;color:#e4e4e7;">
          <?= h((string)$infoClasse['nom_classe']) ?> — <?= h(gds_filiere_code((string)$infoClasse['nom_filiere'])) ?>
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
        <?php foreach ($stagiaires as $indexStag => $stag): ?>
        <?php
          $totalAbsStag    = (int)$stag['total_absences'];
          $nbJustifiees    = (int)$stag['nb_justifiees'];
          $nbNonJustifiees = (int)$stag['nb_non_justifiees'];
          $derniereAbsence = $stag['derniere_absence'] ? date('d/m/Y', strtotime($stag['derniere_absence'])) : '—';
          $nomComplet      = strtoupper($stag['nom']) . ' ' . $stag['prenom'];
        ?>
        <tr id="row-<?= (int)$stag['id_stagiaire'] ?>"
            data-sid="<?= (int)$stag['id_stagiaire'] ?>"
            data-total="<?= $totalAbsStag ?>"
            data-just="<?= $nbJustifiees ?>"
            data-nonj="<?= $nbNonJustifiees ?>">
          <td class="cb-col">
            <input type="checkbox" class="row-cb" value="<?= (int)$stag['id_stagiaire'] ?>">
          </td>
          <td style="color:#71717a;font-size:.8rem;"><?= $indexStag + 1 ?></td>
          <td style="font-weight:700;">
            <a href="stagiaires.php?id=<?= (int)$stag['id_stagiaire'] ?>" style="color:#e4e4e7;text-decoration:none;" target="_blank">
              <?= h($nomComplet) ?>
            </a>
          </td>
          <td style="color:#71717a;font-size:.82rem;font-family:monospace;"><?= h($stag['num_inscri']) ?></td>

          <td style="text-align:center;" class="col-total">
            <?php if ($totalAbsStag === 0): ?>
              <span class="badge-abs green"><i class="fa-solid fa-check"></i> 0</span>
            <?php elseif ($totalAbsStag >= 5): ?>
              <span class="badge-abs red"><i class="fa-solid fa-triangle-exclamation"></i> <?= $totalAbsStag ?></span>
            <?php else: ?>
              <span class="badge-abs yellow"><?= $totalAbsStag ?></span>
            <?php endif; ?>
          </td>

          <td style="text-align:center;color:#86efac;" class="col-just">
            <?= $nbJustifiees > 0 ? $nbJustifiees : '<span style="color:#3f3f46;">—</span>' ?>
          </td>

          <td style="text-align:center;" class="col-nonj">
            <?= $nbNonJustifiees > 0
              ? '<span style="color:#fca5a5;font-weight:700;">' . $nbNonJustifiees . '</span>'
              : '<span style="color:#3f3f46;">—</span>'
            ?>
          </td>

          <td style="color:#71717a;font-size:.82rem;" class="col-date"><?= $derniereAbsence ?></td>

          <td style="text-align:center;white-space:nowrap;">
            <button type="button" class="icon-btn-sm" title="Ajouter une absence"
              onclick="ouvrirModalAjout(<?= (int)$stag['id_stagiaire'] ?>, '<?= h(addslashes($nomComplet)) ?>')"
              style="color:#c084fc;border-color:rgba(168,85,247,.25);">
              <i class="fa-solid fa-plus"></i>
            </button>
            <button type="button" class="icon-btn-sm" title="Voir le détail des absences"
              onclick="ouvrirModalDetail(<?= (int)$stag['id_stagiaire'] ?>, '<?= h(addslashes($nomComplet)) ?>')">
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

</div><!-- /container principal -->


<!-- ─── MODALE IMPRIMER LES BILLETS D'EXCUSE ──────────────────────────── -->
<div class="abs-modal-overlay" id="modal-billets" style="z-index:10000;">
  <div class="abs-modal-card" style="width:min(640px,95vw);">
    <div class="abs-modal-header">
      <h3><i class="fa-solid fa-ticket" style="color:#22c55e;margin-right:.4rem;"></i>Imprimer les billets d'excuse</h3>
      <button type="button" class="icon-btn-sm" onclick="fermerModal('modal-billets')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="abs-modal-body" style="padding:1rem 1.25rem;">

      <!-- Barre de recherche & filtres billets -->
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.85rem;">
        <input type="text" id="billets-search" placeholder="Rechercher par nom, CIN, code…"
          style="flex:1;min-width:200px;background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:8px;padding:.45rem .75rem;font-size:.88rem;"
          oninput="chargerListeBillets()">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:#a1a1aa;cursor:pointer;user-select:none;">
          <input type="checkbox" id="billets-recent" style="accent-color:#22c55e;width:14px;height:14px;" onchange="chargerListeBillets()">
          Absences non just. seulement
        </label>
      </div>

      <!-- Actions sélection -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;font-size:.8rem;color:#71717a;">
        <div style="display:flex;gap:.5rem;">
          <button type="button" onclick="billetsSelectAll(true)"  style="background:transparent;border:1px solid rgba(255,255,255,.1);color:#a1a1aa;border-radius:6px;padding:2px 9px;font-size:.77rem;cursor:pointer;">Tout sélectionner</button>
          <button type="button" onclick="billetsSelectAll(false)" style="background:transparent;border:1px solid rgba(255,255,255,.1);color:#a1a1aa;border-radius:6px;padding:2px 9px;font-size:.77rem;cursor:pointer;">Tout désélectionner</button>
        </div>
        <span id="billets-count-sel" style="font-weight:700;color:#86efac;">0 sélectionné(s)</span>
      </div>

      <!-- Liste des stagiaires -->
      <div id="billets-liste" style="max-height:340px;overflow-y:auto;border:1px solid rgba(255,255,255,.07);border-radius:10px;">
        <div style="text-align:center;padding:2rem;color:#52525b;">
          <i class="fa-solid fa-spinner fa-spin fa-lg"></i>
          <p style="margin-top:.5rem;">Chargement…</p>
        </div>
      </div>
    </div>
    <div class="abs-modal-footer">
      <button type="button" class="btn-abs ghost" onclick="fermerModal('modal-billets')">Annuler</button>
      <button type="button" class="btn-abs primary" id="billets-print-btn" onclick="imprimerBilletsSelectionnes()" disabled
        style="padding:.65rem 1.75rem;font-size:.9rem;border-radius:10px;background:linear-gradient(135deg,#16a34a,#22c55e);">
        <i class="fa-solid fa-print"></i> Imprimer
      </button>
    </div>
  </div>
</div>


<!-- ─── DRAWER JUSTIFICATION EN MASSE ─────────────────────────────────── -->
<div class="justify-drawer-overlay" id="justify-drawer-overlay" onclick="drawerOverlayClick(event)">
  <div class="justify-drawer">

    <div class="justify-drawer-header">
      <h3><i class="fa-solid fa-certificate" style="color:#a855f7;margin-right:.45rem;"></i>Justification en masse</h3>
      <button type="button" class="icon-btn-sm" onclick="fermerDrawerJustif()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="justify-drawer-motif">
      <label style="flex:1;min-width:200px;">
        Motif de justification *
        <input type="text" id="prev-justif-motif" placeholder="Ex : Certificat médical, Convocation officielle…">
      </label>
      <span id="prev-justif-total" style="font-size:.82rem;font-weight:700;color:#c084fc;white-space:nowrap;"></span>
    </div>

    <div class="justify-drawer-body" id="prev-justif-body">
      <div style="text-align:center;color:#52525b;padding:2rem;">
        <i class="fa-solid fa-spinner fa-spin fa-lg"></i>
        <p style="margin-top:.75rem;">Chargement des absences…</p>
      </div>
    </div>

    <div class="justify-drawer-footer">
      <button type="button" class="btn-abs ghost" onclick="fermerDrawerJustif()">
        <i class="fa-solid fa-xmark"></i> Annuler
      </button>
      <button type="button" class="btn-abs primary" id="prev-justif-confirm"
        onclick="confirmerJustification()" disabled
        style="padding:.65rem 1.75rem;font-size:.9rem;border-radius:10px;">
        <i class="fa-solid fa-certificate"></i> Confirmer
      </button>
    </div>

  </div>
</div>


<!-- ─── MODALE AJOUT / MODIFICATION D'ABSENCE ─────────────────────────────── -->
<div class="abs-modal-overlay" id="modal-add-abs">
  <div class="abs-modal-card">
    <div class="abs-modal-header">
      <h3 id="add-modal-title"><i class="fa-solid fa-user-clock" style="color:#a855f7;margin-right:.4rem;"></i>Nouvelle absence</h3>
      <button type="button" class="icon-btn-sm" onclick="fermerModal('modal-add-abs')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="abs-modal-body">
      <p id="add-modal-desc" style="font-size:.85rem;color:#71717a;margin:0 0 1rem;"></p>
      <div class="abs-form-grid">
        <input type="hidden" id="add-sid"  value="">
        <input type="hidden" id="add-aid"  value="0">
        <label>Date *
          <input type="date" id="add-date" required value="<?= date('Y-m-d') ?>">
        </label>
        <label>Module *
          <select id="add-module" required>
            <option value="0">— Sélectionner un module —</option>
            <?php foreach ($tousLesModules as $module): ?>
              <option value="<?= (int)$module['id_module'] ?>"><?= h(gds_module_label((string)$module['nom_module'])) ?></option>
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
      <button type="button" class="btn-abs ghost" onclick="fermerModal('modal-add-abs')">Annuler</button>
      <button type="button" class="btn-abs primary" id="add-save-btn" onclick="soumettreAjoutAbsence()" style="padding:.65rem 1.75rem;font-size:.9rem;border-radius:10px;">
        <i class="fa-solid fa-floppy-disk"></i> <span id="add-btn-label">Enregistrer</span>
      </button>
    </div>
  </div>
</div>


<!-- ─── MODALE DÉTAIL DES ABSENCES D'UN STAGIAIRE ─────────────────────────── -->
<div class="abs-modal-overlay" id="modal-detail">
  <div class="abs-modal-card" style="width:min(640px,95vw);">
    <div class="abs-modal-header">
      <h3 id="detail-title"><i class="fa-solid fa-list" style="color:#a855f7;margin-right:.4rem;"></i>Absences — Stagiaire</h3>
      <div style="display:flex;align-items:center;gap:.5rem;">
        <button type="button" class="btn-abs ghost sm" id="detail-add-btn"
          onclick="ouvrirModalAjout(_detailSidCourant, _detailNomCourant)" title="Ajouter une absence">
          <i class="fa-solid fa-plus"></i> Ajouter
        </button>
        <button type="button" class="icon-btn-sm" onclick="fermerModal('modal-detail')"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
    <div class="abs-modal-body" id="detail-body" style="padding:1rem 1.5rem;">
      <div style="text-align:center;color:#52525b;padding:2rem;"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>
    </div>
    <div class="abs-modal-footer">
      <button type="button" class="btn-abs ghost" onclick="fermerModal('modal-detail')">Fermer</button>
    </div>
  </div>
</div>


<!-- ─── DIALOG DE CONFIRMATION PERSONNALISÉ ──────────────────────────────── -->
<div id="gds-confirm-overlay" class="gds-confirm-overlay">
  <div class="gds-confirm-card">
    <div class="gds-confirm-icon">⚠️</div>
    <p class="gds-confirm-msg" id="gds-confirm-msg">Confirmer l'action ?</p>
    <div class="gds-confirm-btns">
      <button class="btn-abs ghost"  onclick="gdsConfirmRepondre(false)"><i class="fa-solid fa-xmark"></i> Annuler</button>
      <button class="btn-abs danger" onclick="gdsConfirmRepondre(true)"><i class="fa-solid fa-check"></i> Confirmer</button>
    </div>
  </div>
</div>


<script>
// ── Variables PHP → JS ────────────────────────────────────────────────────
var GDS_CSRF          = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
var GDS_IS_DIRECTEUR  = <?= gds_is_directeur() ? 'true' : 'false' ?>;
var SEL_DATE_FROM     = <?= json_encode($dateDebutFiltre) ?>;
var SEL_DATE_TO       = <?= json_encode($dateFinFiltre) ?>;
var SEL_CLASSE_ID     = <?= $idClasseSelecte ?>;
var SEL_CLASSE        = <?= $idClasseSelecte ?>;
var HIGHLIGHT_AID     = <?= $highlightAid ?>;
var HIGHLIGHT_SID     = <?= $highlightSid ?>;
var HIGHLIGHT_NOM     = <?= json_encode($highlightNom) ?>;
var HIGHLIGHT_ROW_SID = <?= $highlightRowSid ?>;


// ============================================================
//  Sélection en masse (checkboxes)
// ============================================================

document.getElementById('select-all')?.addEventListener('change', function () {
  document.querySelectorAll('.row-cb').forEach(cb => {
    cb.checked = this.checked;
    cb.closest('tr').classList.toggle('row-selected', this.checked);
  });
  mettreAJourSelectionUX();
});

document.querySelectorAll('.row-cb').forEach(cb => cb.addEventListener('change', function () {
  this.closest('tr').classList.toggle('row-selected', this.checked);
  mettreAJourSelectionUX();
}));

/** Met à jour le compteur du panneau de session et le bouton Justifier */
function mettreAJourSelectionUX() {
  const cochees  = document.querySelectorAll('.row-cb:checked');
  const n        = cochees.length;
  const counter  = document.getElementById('session-counter');
  const btnLabel = document.getElementById('btn-enregistrer-label');
  const btnJust  = document.getElementById('btn-justifier');

  if (counter)  counter.textContent = n + ' sélectionné(s)';
  if (btnLabel) btnLabel.textContent = n > 0
    ? 'Enregistrer les absences (' + n + ')'
    : 'Enregistrer les absences';
  if (btnJust)  btnJust.style.display = n > 0 ? '' : 'none';
}

/** Retourne la liste des IDs stagiaires cochés */
function obtenirIdsSelectionnes() {
  return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => parseInt(cb.value));
}


// ============================================================
//  Drawer justification
// ============================================================

async function ouvrirDrawerJustif() {
  const ids = obtenirIdsSelectionnes();
  if (!ids.length) {
    afficherToast('Sélectionnez au moins un stagiaire dans la liste.', 'error');
    return;
  }

  const overlay = document.getElementById('justify-drawer-overlay');
  const body    = document.getElementById('prev-justif-body');
  const motif   = document.getElementById('prev-justif-motif');

  body.innerHTML = '<div style="text-align:center;padding:2.5rem;color:#71717a;"><i class="fa-solid fa-spinner fa-spin fa-lg"></i><p style="margin-top:.75rem;">Chargement des absences…</p></div>';
  document.getElementById('prev-justif-total').textContent = '';
  document.getElementById('prev-justif-confirm').disabled  = true;
  if (motif) motif.value = '';
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  let url = 'absences.php?action=preview_bulk_justify';
  ids.forEach(id => { url += '&ids[]=' + id; });
  if (SEL_DATE_FROM) url += '&date_from=' + SEL_DATE_FROM;
  if (SEL_DATE_TO)   url += '&date_to='   + SEL_DATE_TO;

  fetch(url, { credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(groupes => rendrePreviewJustif(groupes, ids))
    .catch(e => {
      body.innerHTML = '<p style="color:#fca5a5;padding:1rem;">Erreur de chargement : ' + echapperHtml(e.message) + '.</p>';
    });
}

function fermerDrawerJustif() {
  document.getElementById('justify-drawer-overlay').classList.remove('open');
  document.body.style.overflow = '';
}

function drawerOverlayClick(e) {
  if (e.target === document.getElementById('justify-drawer-overlay')) fermerDrawerJustif();
}


// ============================================================
//  Gestion des modales
// ============================================================

function fermerModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.abs-modal-overlay').forEach(m => {
  m.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
});

function ouvrirModalAjout(idStagiaire, nomStagiaire) {
  document.getElementById('add-sid').value     = idStagiaire;
  document.getElementById('add-aid').value     = '0';
  document.getElementById('add-modal-title').firstChild.nextSibling.textContent = idStagiaire ? 'Nouvelle absence' : 'Ajouter une absence';
  document.getElementById('add-modal-desc').textContent = nomStagiaire ? 'Stagiaire : ' + nomStagiaire : 'Sélectionner un stagiaire dans la liste.';
  document.getElementById('add-date').value    = new Date().toISOString().split('T')[0];
  document.getElementById('add-module').value  = '0';
  document.getElementById('add-hdebut').value  = '';
  document.getElementById('add-hfin').value    = '';
  document.getElementById('add-justif').value  = '';
  const justCb = document.getElementById('add-justifiee');
  if (justCb && justCb.type === 'checkbox') justCb.checked = false;
  document.getElementById('add-btn-label').textContent = 'Enregistrer';
  document.getElementById('modal-add-abs').style.display = 'flex';
}

function ouvrirModalEdition(data) {
  document.getElementById('add-sid').value    = data.id_stagiaire;
  document.getElementById('add-aid').value    = data.id_absence;
  document.getElementById('add-modal-title').firstChild.nextSibling.textContent = "Modifier l'absence";
  document.getElementById('add-modal-desc').textContent = 'Absence du ' + (data.date_absence || '').split('-').reverse().join('/');
  document.getElementById('add-date').value   = data.date_absence || '';
  document.getElementById('add-module').value = data.id_module || '0';
  document.getElementById('add-hdebut').value = (data.heure_debut || '').substring(0, 5);
  document.getElementById('add-hfin').value   = (data.heure_fin   || '').substring(0, 5);
  document.getElementById('add-justif').value = data.justificatif || '';
  const justCb = document.getElementById('add-justifiee');
  if (justCb && justCb.type === 'checkbox') justCb.checked = parseInt(data.est_justifiee) === 1;
  document.getElementById('add-btn-label').textContent = 'Mettre à jour';
  fermerModal('modal-detail');
  document.getElementById('modal-add-abs').style.display = 'flex';
}

function soumettreAjoutAbsence() {
  const idStagiaire = document.getElementById('add-sid').value;
  if (!idStagiaire || parseInt(idStagiaire) <= 0) {
    afficherToast("Sélectionnez un stagiaire d'abord.", 'error'); return;
  }
  const idModule = parseInt(document.getElementById('add-module').value);
  if (!idModule || idModule <= 0) {
    afficherToast('Le module est obligatoire.', 'error');
    document.getElementById('add-module').focus(); return;
  }
  const dateAbs = document.getElementById('add-date').value;
  if (!dateAbs) { afficherToast('La date est obligatoire.', 'error'); return; }

  const idAbsEdit    = document.getElementById('add-aid').value;
  const estJustifiee = document.getElementById('add-justifiee');

  const fd = new FormData();
  fd.append('save_absence',    '1');
  fd.append('csrf_token',      GDS_CSRF);
  fd.append('id_stagiaire',    idStagiaire);
  fd.append('id_absence_edit', idAbsEdit);
  fd.append('date_absence',    dateAbs);
  fd.append('id_module',       idModule);
  fd.append('heure_debut',     document.getElementById('add-hdebut').value);
  fd.append('heure_fin',       document.getElementById('add-hfin').value);
  fd.append('justificatif',    document.getElementById('add-justif').value);
  if (estJustifiee && estJustifiee.type === 'checkbox' && estJustifiee.checked) {
    fd.append('est_justifiee', '1');
  }

  const btnSave = document.getElementById('add-save-btn');
  btnSave.disabled = true;
  btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

  fetch('absences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      btnSave.disabled = false;
      btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span id="add-btn-label">Enregistrer</span>';
      if (data.success) {
        fermerModal('modal-add-abs');
        afficherToast('Absence enregistrée.', 'success');
        const estModification = parseInt(idAbsEdit) > 0;
        const estJust         = parseInt(data.est_justifiee) === 1;
        if (!estModification) {
          mettreAJourLigne(parseInt(idStagiaire), +1, estJust ? +1 : 0, estJust ? 0 : +1, dateAbs);
        }
        if (_detailSidCourant === parseInt(idStagiaire)) chargerDetailModal(_detailSidCourant);
      } else {
        afficherToast('Erreur : ' + data.error, 'error');
      }
    })
    .catch(e => {
      btnSave.disabled = false;
      btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span id="add-btn-label">Enregistrer</span>';
      afficherToast('Erreur réseau (' + e.message + ').', 'error');
    });
}


// ============================================================
//  Actions en masse
// ============================================================

async function faireMarquageAbsentEnMasse() {
  const ids   = obtenirIdsSelectionnes();
  const date  = document.getElementById('bulk-date').value;
  const modId = parseInt(document.getElementById('bulk-module')?.value || '0');

  if (!ids.length) { afficherToast('Sélectionnez au moins un stagiaire dans la liste.', 'error'); return; }
  if (!date)       { afficherToast('Choisissez une date dans le panneau de session.', 'error'); return; }
  if (!modId || modId <= 0) { afficherToast('Le module est obligatoire pour marquer les absences.', 'error'); document.getElementById('bulk-module')?.focus(); return; }

  const ok = await gdsConfirmer('Marquer ' + ids.length + ' stagiaire(s) absent(s) le ' + date.split('-').reverse().join('/') + ' ?');
  if (!ok) return;

  const heureDebut = document.getElementById('bulk-hdebut').value;
  const heureFin   = document.getElementById('bulk-hfin').value;

  const fd = new FormData();
  fd.append('bulk_mark_absent', '1');
  fd.append('csrf_token',       GDS_CSRF);
  fd.append('bulk_date',        date);
  fd.append('id_module',        modId);
  if (heureDebut) fd.append('heure_debut', heureDebut);
  if (heureFin)   fd.append('heure_fin',   heureFin);
  ids.forEach(id => fd.append('student_ids[]', id));

  fetch('absences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      if (data.success) {
        const msgIgnores = data.skipped ? ' · ' + data.skipped + ' déjà présente(s) pour ce module/cette date (ignorée(s)).' : '.';
        afficherToast(data.inserted + ' absence(s) enregistrée(s)' + msgIgnores, data.inserted > 0 ? 'success' : 'info');
        ids.forEach(id => mettreAJourLigne(id, +1, 0, +1, date));
        document.querySelectorAll('.row-cb:checked').forEach(cb => {
          cb.checked = false;
          cb.closest('tr').classList.remove('row-selected');
        });
        const all = document.getElementById('select-all');
        if (all) all.checked = false;
        mettreAJourSelectionUX();
      } else {
        afficherToast('Erreur : ' + data.error, 'error');
      }
    })
    .catch(e => afficherToast('Erreur réseau (' + e.message + ').', 'error'));
}

function rendrePreviewJustif(groupes, idsStagiaires) {
  const body       = document.getElementById('prev-justif-body');
  const btnConfirm = document.getElementById('prev-justif-confirm');

  if (!groupes.length) {
    body.innerHTML = '<div style="text-align:center;padding:2rem;color:#52525b;"><i class="fa-solid fa-check-circle" style="color:#86efac;font-size:1.5rem;"></i><p>Aucune absence non justifiée trouvée pour cette sélection et cette période.</p></div>';
    document.getElementById('prev-justif-total').textContent = '0 absence';
    btnConfirm.disabled = true;
    return;
  }

  let totalAbsences = 0;
  let html = '';

  groupes.forEach(stag => {
    const nbAbs = stag.absences.length;
    totalAbsences += nbAbs;
    const groupId = 'prev-grp-' + stag.id_stagiaire;

    html += '<div class="prev-stag-groupe">'
      + '<div class="prev-stag-header">'
      + '<label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:700;color:#e4e4e7;">'
      + '<input type="checkbox" class="prev-stag-toggle" data-group="' + groupId + '" checked'
      + ' style="accent-color:#a855f7;width:15px;height:15px;" onchange="toggleGroupePrev(this)">'
      + echapperHtml(stag.nom) + ' ' + echapperHtml(stag.prenom)
      + '</label>'
      + '<span class="badge-abs gray" style="font-size:.7rem;">' + nbAbs + ' absence' + (nbAbs > 1 ? 's' : '') + '</span>'
      + '</div>'
      + '<div id="' + groupId + '" class="prev-abs-liste">';

    stag.absences.forEach(abs => {
      const dateF  = (abs.date_absence || '').split('-').reverse().join('/');
      const heures = (abs.heure_debut && abs.heure_fin)
        ? abs.heure_debut.substring(0,5) + ' – ' + abs.heure_fin.substring(0,5) : null;
      const module = abs.nom_module ? '<span class="prev-abs-module">' + echapperHtml(abs.nom_module) + '</span>' : '';

      html += '<label class="prev-abs-row">'
        + '<input type="checkbox" class="prev-abs-cb" name="absence_ids[]" value="' + abs.id_absence + '" checked'
        + ' data-sid="' + stag.id_stagiaire + '" data-group="' + groupId + '"'
        + ' style="accent-color:#a855f7;width:14px;height:14px;" onchange="mettreAJourCompteurPrev()">'
        + '<div class="prev-abs-info">'
        + '<span class="prev-abs-date">' + dateF + '</span>'
        + (module ? ' · ' + module : '')
        + (heures ? '<span class="prev-abs-heures">' + heures + '</span>' : '')
        + '</div>'
        + '</label>';
    });

    html += '</div></div>';
  });

  body.innerHTML = html;
  mettreAJourCompteurPrev();
}

function toggleGroupePrev(toggle) {
  const groupId = toggle.dataset.group;
  document.querySelectorAll('.prev-abs-cb[data-group="' + groupId + '"]').forEach(cb => {
    cb.checked = toggle.checked;
  });
  mettreAJourCompteurPrev();
}

function mettreAJourCompteurPrev() {
  const coches     = document.querySelectorAll('.prev-abs-cb:checked');
  const total      = document.querySelectorAll('.prev-abs-cb').length;
  const btnConfirm = document.getElementById('prev-justif-confirm');
  const lblTotal   = document.getElementById('prev-justif-total');
  lblTotal.textContent = coches.length + ' / ' + total + ' absence' + (total > 1 ? 's' : '');
  btnConfirm.disabled  = coches.length === 0;

  document.querySelectorAll('.prev-stag-toggle').forEach(toggle => {
    const groupId   = toggle.dataset.group;
    const toutesGrp = document.querySelectorAll('.prev-abs-cb[data-group="' + groupId + '"]');
    const cochesGrp = document.querySelectorAll('.prev-abs-cb[data-group="' + groupId + '"]:checked');
    toggle.indeterminate = cochesGrp.length > 0 && cochesGrp.length < toutesGrp.length;
    toggle.checked       = cochesGrp.length === toutesGrp.length;
  });
}

async function confirmerJustification() {
  const coches = document.querySelectorAll('.prev-abs-cb:checked');
  const justif = document.getElementById('prev-justif-motif').value.trim();

  if (!coches.length) { afficherToast('Aucune absence sélectionnée.', 'error'); return; }
  if (!justif)        { afficherToast('Le motif est obligatoire.', 'error'); document.getElementById('prev-justif-motif').focus(); return; }

  const idsAbsences   = Array.from(coches).map(cb => parseInt(cb.value));
  const idsStagiaires = Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => parseInt(cb.value));

  const btnConfirm = document.getElementById('prev-justif-confirm');
  btnConfirm.disabled = true;
  btnConfirm.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Justification…';

  const fd = new FormData();
  fd.append('bulk_justify', '1');
  fd.append('csrf_token',   GDS_CSRF);
  fd.append('justificatif', justif);
  idsAbsences.forEach(id   => fd.append('absence_ids[]', id));
  idsStagiaires.forEach(id => fd.append('student_ids[]', id));

  fetch('absences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      btnConfirm.disabled  = false;
      btnConfirm.innerHTML = '<i class="fa-solid fa-certificate"></i> Confirmer';

      if (data.success) {
        fermerDrawerJustif();
        afficherToast(data.updated + ' absence(s) justifiée(s).', 'success');

        const bilan = data.bilan_par_stag || {};
        Object.entries(bilan).forEach(([sid, nb]) => mettreAJourLigne(parseInt(sid), 0, +nb, -nb, null));

        document.querySelectorAll('.row-cb:checked').forEach(cb => {
          cb.checked = false;
          cb.closest('tr').classList.remove('row-selected');
        });
        const all = document.getElementById('select-all');
        if (all) all.checked = false;
        mettreAJourSelectionUX();
      } else {
        afficherToast('Erreur : ' + data.error, 'error');
      }
    })
    .catch(e => {
      btnConfirm.disabled  = false;
      btnConfirm.innerHTML = '<i class="fa-solid fa-certificate"></i> Confirmer';
      afficherToast('Erreur réseau (' + e.message + ').', 'error');
    });
}


// ============================================================
//  Modale de détail des absences d'un stagiaire
// ============================================================

var _detailSidCourant = 0;
var _detailNomCourant = '';

function ouvrirModalDetail(idStagiaire, nomStagiaire) {
  _detailSidCourant = idStagiaire;
  _detailNomCourant = nomStagiaire;
  document.getElementById('detail-title').querySelector('i').nextSibling.textContent = ' Absences — ' + nomStagiaire;
  document.getElementById('detail-body').innerHTML = '<div style="text-align:center;padding:2rem;color:#71717a;"><i class="fa-solid fa-spinner fa-spin"></i> Chargement…</div>';
  document.getElementById('modal-detail').style.display = 'flex';
  chargerDetailModal(idStagiaire);
}

function chargerDetailModal(idStagiaire) {
  let url = 'absences.php?action=get_student_absences&id_stagiaire=' + idStagiaire;
  if (SEL_DATE_FROM) url += '&date_from=' + SEL_DATE_FROM;
  if (SEL_DATE_TO)   url += '&date_to='   + SEL_DATE_TO;
  fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(lignes => rendreCorpsDetail(lignes, idStagiaire))
    .catch(() => {
      document.getElementById('detail-body').innerHTML = '<p style="color:#fca5a5;padding:1rem;">Erreur de chargement.</p>';
    });
}

function appliquerFiltreDetail() {
  const champDate   = document.getElementById('detail-date-filter');
  const champStatut = document.getElementById('detail-status-filter');
  const valDate     = champDate   ? champDate.value   : '';
  const valStatut   = champStatut ? champStatut.value : '';
  document.querySelectorAll('#detail-body .detail-abs-row').forEach(function (ligne) {
    const corresp = (!valDate   || ligne.dataset.date  === valDate.split('-').reverse().join('/')) &&
                    (!valStatut || ligne.dataset.justif === valStatut);
    ligne.style.display = corresp ? '' : 'none';
  });
}

function reinitialiserFiltresDetail() {
  const df = document.getElementById('detail-date-filter');
  const sf = document.getElementById('detail-status-filter');
  if (df) df.value = '';
  if (sf) sf.value = '';
  appliquerFiltreDetail();
}

function rendreCorpsDetail(lignes, idStagiaire) {
  if (!lignes.length) {
    document.getElementById('detail-body').innerHTML =
      '<div style="text-align:center;padding:2rem;color:#52525b;"><i class="fa-solid fa-check-circle" style="color:#86efac;font-size:1.5rem;"></i><p>Aucune absence enregistrée pour cette période.</p></div>';
    return;
  }

  let html = '<div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem;padding:.6rem .75rem;background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.18);border-radius:8px;">'
    + '<i class="fa-solid fa-filter" style="color:#a855f7;font-size:.8rem;"></i>'
    + '<input type="date" id="detail-date-filter" onchange="appliquerFiltreDetail()" style="background:#09090b;border:1px solid rgba(168,85,247,.3);color:#e4e4e7;border-radius:7px;padding:.3rem .65rem;font-size:.82rem;color-scheme:dark;">'
    + '<select id="detail-status-filter" onchange="appliquerFiltreDetail()" style="background:#09090b;border:1px solid rgba(168,85,247,.3);color:#e4e4e7;border-radius:7px;padding:.3rem .65rem;font-size:.82rem;">'
    + '<option value="">Tous les statuts</option>'
    + '<option value="1">Justifiée</option>'
    + '<option value="0">Non justifiée</option>'
    + '</select>'
    + '<button type="button" onclick="reinitialiserFiltresDetail()" style="background:transparent;border:1px solid rgba(255,255,255,.1);color:#71717a;border-radius:7px;padding:.3rem .65rem;font-size:.78rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>'
    + '</div>';

  lignes.forEach(ligne => {
    const dateFormatee = (ligne.date_absence || '').split('-').reverse().join('/');
    const heures       = (ligne.heure_debut && ligne.heure_fin)
      ? ligne.heure_debut.substring(0, 5) + ' – ' + ligne.heure_fin.substring(0, 5) : '—';
    const estJustifiee = parseInt(ligne.est_justifiee) === 1;
    const badgeStatut  = estJustifiee
      ? '<span class="badge-abs green" style="font-size:.7rem;">Justifiée</span>'
      : '<span class="badge-abs red"   style="font-size:.7rem;">Non just.</span>';
    const nomModule = ligne.nom_module ? '<small style="color:#71717a;">' + echapperHtml(ligne.nom_module) + '</small>' : '';
    const motif     = ligne.justificatif ? '<small style="color:#a1a1aa;font-style:italic;">' + echapperHtml(ligne.justificatif) + '</small>' : '';

    const dataEdition = JSON.stringify({
      id_stagiaire: idStagiaire, id_absence: ligne.id_absence,
      date_absence: ligne.date_absence, heure_debut: ligne.heure_debut,
      heure_fin: ligne.heure_fin, justificatif: ligne.justificatif,
      est_justifiee: ligne.est_justifiee, id_module: ligne.id_module,
    });

    html += '<div class="detail-abs-row" data-aid="' + ligne.id_absence + '" data-date="' + dateFormatee + '" data-justif="' + (estJustifiee ? '1' : '0') + '">'
      + '<div style="flex:1;">'
      + '<span style="font-weight:700;color:#e4e4e7;">' + dateFormatee + '</span>'
      + (nomModule ? ' · ' + nomModule : '')
      + '<br><small style="color:#71717a;">' + heures + '</small>'
      + (motif ? '<br>' + motif : '')
      + '</div>'
      + badgeStatut
      + '<div style="display:flex;gap:4px;">'
      + (GDS_IS_DIRECTEUR ? '<button type="button" class="icon-btn-sm" title="Modifier" onclick="ouvrirModalEdition(' + echapperHtml(dataEdition) + ')"><i class="fa-solid fa-pen"></i></button>' : '')
      + (GDS_IS_DIRECTEUR ? '<button type="button" class="icon-btn-sm danger" title="Supprimer" onclick="supprimerAbsence(' + ligne.id_absence + ',' + idStagiaire + ',this)"><i class="fa-solid fa-trash"></i></button>' : '')
      + (estJustifiee ? '<a href="print_billet_excuse.php?id=' + ligne.id_absence + '&auto=1" target="_blank" class="icon-btn-sm" title="Billet d\'excuse" style="color:#86efac;"><i class="fa-solid fa-print"></i></a>' : '')
      + '</div>'
      + '</div>';
  });

  document.getElementById('detail-body').innerHTML = html;

  if (HIGHLIGHT_AID > 0) {
    const cible = document.querySelector('#detail-body .detail-abs-row[data-aid="' + HIGHLIGHT_AID + '"]');
    if (cible) {
      cible.scrollIntoView({ behavior: 'smooth', block: 'center' });
      cible.style.transition   = 'background .2s';
      cible.style.background   = 'rgba(168,85,247,.22)';
      cible.style.borderRadius = '8px';
      cible.style.outline      = '2px solid rgba(168,85,247,.6)';
      setTimeout(() => { cible.style.background = ''; cible.style.outline = ''; }, 2800);
    }
  }
}

async function supprimerAbsence(idAbsence, idStagiaire, bouton) {
  const ok = await gdsConfirmer('Supprimer cette absence ?');
  if (!ok) return;

  const fd = new FormData();
  fd.append('delete_absence', '1');
  fd.append('csrf_token',     GDS_CSRF);
  fd.append('id_absence',     idAbsence);

  fetch('absences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        bouton.closest('.detail-abs-row').remove();
        afficherToast('Absence supprimée.', 'success');
        const etaitJustifiee = parseInt(data.etait_justifiee) === 1;
        mettreAJourLigne(idStagiaire, -1, etaitJustifiee ? -1 : 0, etaitJustifiee ? 0 : -1, null);
      } else {
        afficherToast('Erreur : ' + data.error, 'error');
      }
    })
    .catch(() => afficherToast('Erreur réseau lors de la suppression.', 'error'));
}


// ============================================================
//  Mise à jour en place des cellules du tableau principal
// ============================================================

function mettreAJourLigne(idStagiaire, deltaTotal, deltaJust, deltaNonJ, nouvelleDate) {
  const tr = document.getElementById('row-' + idStagiaire);
  if (!tr) return;

  const newTotal = Math.max(0, (parseInt(tr.dataset.total) || 0) + deltaTotal);
  const newJust  = Math.max(0, (parseInt(tr.dataset.just)  || 0) + deltaJust);
  const newNonJ  = Math.max(0, (parseInt(tr.dataset.nonj)  || 0) + deltaNonJ);

  tr.dataset.total = newTotal;
  tr.dataset.just  = newJust;
  tr.dataset.nonj  = newNonJ;

  let badgeTotal;
  if (newTotal === 0) {
    badgeTotal = '<span class="badge-abs green"><i class="fa-solid fa-check"></i> 0</span>';
  } else if (newTotal >= 5) {
    badgeTotal = '<span class="badge-abs red"><i class="fa-solid fa-triangle-exclamation"></i> ' + newTotal + '</span>';
  } else {
    badgeTotal = '<span class="badge-abs yellow">' + newTotal + '</span>';
  }

  const cellTotal = tr.querySelector('.col-total');
  const cellJust  = tr.querySelector('.col-just');
  const cellNonJ  = tr.querySelector('.col-nonj');
  const cellDate  = tr.querySelector('.col-date');

  if (cellTotal) cellTotal.innerHTML = badgeTotal;
  if (cellJust)  cellJust.innerHTML  = newJust > 0 ? newJust : '<span style="color:#3f3f46;">—</span>';
  if (cellNonJ)  cellNonJ.innerHTML  = newNonJ > 0
    ? '<span style="color:#fca5a5;font-weight:700;">' + newNonJ + '</span>'
    : '<span style="color:#3f3f46;">—</span>';

  if (cellDate && nouvelleDate) {
    const dateFormatee = nouvelleDate.split('-').reverse().join('/');
    if (cellDate.textContent === '—' || nouvelleDate > (cellDate.textContent.split('/').reverse().join('-') || '')) {
      cellDate.textContent = dateFormatee;
    }
  }

  tr.style.transition = 'background .4s';
  tr.style.background = 'rgba(168,85,247,.1)';
  setTimeout(() => { tr.style.background = ''; }, 1400);
}


// ============================================================
//  Utilitaires
// ============================================================

function echapperHtml(valeur) {
  if (typeof valeur !== 'string') return JSON.stringify(valeur).replace(/"/g, '&quot;');
  return valeur.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function afficherToast(message, type) {
  const toast = document.createElement('div');
  toast.className = 'gds-toast ' + (type || 'info');
  toast.textContent = message;
  document.body.appendChild(toast);
  const duree = type === 'error' ? 5000 : 3500;
  setTimeout(() => {
    toast.style.opacity    = '0';
    toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, duree);
}

// ============================================================
//  Modale — Imprimer les billets d'excuse
// ============================================================

var _billetsDebounce = null;

function ouvrirModalBillets() {
  document.getElementById('billets-search').value = '';
  document.getElementById('billets-recent').checked = false;
  document.getElementById('modal-billets').style.display = 'flex';
  chargerListeBillets();
}

function chargerListeBillets() {
  clearTimeout(_billetsDebounce);
  _billetsDebounce = setTimeout(_chargerListeBilletsNow, 250);
}

function _chargerListeBilletsNow() {
  const q      = document.getElementById('billets-search').value.trim();
  const recent = document.getElementById('billets-recent').checked ? 1 : 0;
  const liste  = document.getElementById('billets-liste');

  liste.innerHTML = '<div style="text-align:center;padding:2rem;color:#52525b;"><i class="fa-solid fa-spinner fa-spin fa-lg"></i></div>';

  let url = 'absences.php?action=get_stagiaires_non_justifies&recent=' + recent;
  if (SEL_CLASSE_ID > 0) url += '&id_classe=' + SEL_CLASSE_ID;
  if (q) url += '&q=' + encodeURIComponent(q);

  fetch(url, { credentials: 'same-origin' })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      if (!Array.isArray(data)) {
        const msg = data && data.error ? data.error : 'Réponse inattendue du serveur.';
        liste.innerHTML = '<p style="color:#fca5a5;padding:1rem;">Erreur : ' + echapperHtml(msg) + '</p>';
        return;
      }
      rendreListeBillets(data);
    })
    .catch(err => {
      liste.innerHTML = '<p style="color:#fca5a5;padding:1rem;">Erreur de chargement : ' + echapperHtml(err.message) + '</p>';
    });
}

function rendreListeBillets(lignes) {
  const liste = document.getElementById('billets-liste');
  if (!lignes.length) {
    liste.innerHTML = '<div style="text-align:center;padding:2rem;color:#52525b;"><i class="fa-solid fa-users-slash"></i><p style="margin-top:.5rem;">Aucun stagiaire trouvé.</p></div>';
    majCompteurBillets();
    return;
  }
  let html = '';
  lignes.forEach(s => {
    const nbNonJ  = parseInt(s.nb_non_justifiees) || 0;
    const badge   = nbNonJ > 0
      ? '<span style="font-size:.72rem;font-weight:700;color:#fca5a5;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.22);padding:1px 8px;border-radius:12px;">' + nbNonJ + ' non just.</span>'
      : '<span style="font-size:.72rem;color:#3f3f46;">0 non just.</span>';
    html += '<label style="display:flex;align-items:center;gap:.75rem;padding:.6rem .9rem;border-bottom:1px solid rgba(255,255,255,.05);cursor:pointer;transition:background .12s;" '
      + 'onmouseover="this.style.background=\'rgba(34,197,94,.06)\'" onmouseout="this.style.background=\'\'">'
      + '<input type="checkbox" class="billet-cb" value="' + s.id_stagiaire + '" onchange="majCompteurBillets()"'
      + ' style="accent-color:#22c55e;width:15px;height:15px;flex-shrink:0;">'
      + '<div style="flex:1;">'
      + '<span style="font-weight:700;color:#e4e4e7;font-size:.88rem;">' + echapperHtml(s.nom) + ' ' + echapperHtml(s.prenom) + '</span>'
      + '<span style="font-size:.77rem;color:#71717a;font-family:monospace;margin-left:.5rem;">' + echapperHtml(s.num_inscri || '') + '</span>'
      + (s.cin ? '<span style="font-size:.75rem;color:#52525b;margin-left:.4rem;">· ' + echapperHtml(s.cin) + '</span>' : '')
      + '</div>'
      + badge
      + '</label>';
  });
  liste.innerHTML = html;
  majCompteurBillets();
}

function majCompteurBillets() {
  const n    = document.querySelectorAll('.billet-cb:checked').length;
  const span = document.getElementById('billets-count-sel');
  const btn  = document.getElementById('billets-print-btn');
  if (span) span.textContent = n + ' sélectionné(s)';
  if (btn)  btn.disabled = n === 0;
}

function billetsSelectAll(etat) {
  document.querySelectorAll('.billet-cb').forEach(cb => { cb.checked = etat; });
  majCompteurBillets();
}

function imprimerBilletsSelectionnes() {
  const ids = Array.from(document.querySelectorAll('.billet-cb:checked')).map(cb => parseInt(cb.value));
  if (!ids.length) { afficherToast('Sélectionnez au moins un stagiaire.', 'error'); return; }
  ids.forEach(id => {
    window.open('print_billet_excuse.php?id_stagiaire=' + id + '&auto=1', '_blank');
  });
  fermerModal('modal-billets');
  afficherToast(ids.length + ' billet(s) ouvert(s) dans de nouveaux onglets.', 'success');
}

var _gdsConfirmCallback = null;

function gdsConfirmer(message) {
  return new Promise(resolve => {
    _gdsConfirmCallback = resolve;
    document.getElementById('gds-confirm-msg').textContent = message;
    document.getElementById('gds-confirm-overlay').style.display = 'flex';
  });
}

function gdsConfirmRepondre(resultat) {
  document.getElementById('gds-confirm-overlay').style.display = 'none';
  if (_gdsConfirmCallback) { _gdsConfirmCallback(resultat); _gdsConfirmCallback = null; }
}

document.getElementById('gds-confirm-overlay')?.addEventListener('click', function (e) {
  if (e.target === this) gdsConfirmRepondre(false);
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    fermerModal('modal-add-abs');
    fermerModal('modal-detail');
    fermerDrawerJustif();
  }
});


// ============================================================
//  Comportements automatiques à l'ouverture de la page
// ============================================================

if (HIGHLIGHT_AID > 0 && HIGHLIGHT_SID > 0 && SEL_CLASSE > 0) {
  document.addEventListener('DOMContentLoaded', function () {
    ouvrirModalDetail(HIGHLIGHT_SID, HIGHLIGHT_NOM || 'Stagiaire');
  });
}

if (HIGHLIGHT_ROW_SID > 0) {
  document.addEventListener('DOMContentLoaded', function () {
    const ligneCible = document.getElementById('row-' + HIGHLIGHT_ROW_SID);
    if (!ligneCible) return;
    setTimeout(function () {
      ligneCible.scrollIntoView({ behavior: 'smooth', block: 'center' });
      ligneCible.style.transition   = 'background 0.2s, outline 0.2s';
      ligneCible.style.background   = 'rgba(168,85,247,0.22)';
      ligneCible.style.outline      = '2px solid rgba(168,85,247,0.6)';
      ligneCible.style.borderRadius = '6px';
      setTimeout(function () {
        ligneCible.style.background = '';
        ligneCible.style.outline    = '';
      }, 2800);
    }, 250);
  });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
