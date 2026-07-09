<?php
  /**
   * notes.php — Saisie centralisée des notes par module et par classe
   *
   * Fonctionnalités :
   *   - Filtres en cascade : Année → Filière → Niveau → Classe → Module
   *   - Saisie en masse des notes (contrôles, théorique, pratique) via tableau
   *   - Enregistrement AJAX (sans rechargement de page) avec toast de confirmation
   *   - Surlignage automatique d'un stagiaire (paramètre highlight=)
   *   - Impression du tableau de contrôle (modal de sélection si plusieurs contrôles)
   *   - Impression du relevé de notes individuel (complet / contrôles / examens)
   *
   * Tables : module_notes, stagiaires, classes, filieres, modules
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  $pageTitle = 'Gestion des notes';
  $curPage   = 'notes';


  // ============================================================
  //  SECTION 1 : Enregistrement en masse des notes (POST → JSON)
  //  Appelé en AJAX depuis le bouton "Enregistrer" du tableau
  // ============================================================

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save_notes'])) {
      // Capture any stray PHP output (notices/warnings) that would corrupt the JSON
      ob_start();

      csrf_verify();

      ob_clean(); // discard any output that slipped in before this point
      header('Content-Type: application/json; charset=utf-8');

      $idModuleSave  = (int)($_POST['id_module']    ?? 0);
      $nbControles   = max(1, min(10, (int)($_POST['nb_controles'] ?? 1)));
      $lignesNotes   = $_POST['notes'] ?? [];   // [id_stagiaire => [type => valeur]]
      $nbSauvegardes = 0;

      /**
       * Valide et nettoie une note brute (chaîne) avant insertion.
       * Retourne float si valide [0–20], null sinon (vide ou hors plage).
       */
      $validerNote = static function (string $brut): ?float {
          $brut = trim($brut);
          if ($brut === '') return null;
          $valeur = (float)str_replace(',', '.', $brut);
          return ($valeur >= 0 && $valeur <= 20) ? $valeur : null;
      };

      try {
          if ($idModuleSave > 0 && is_array($lignesNotes)) {

              // ── Suppression préalable des notes existantes pour éviter les doublons ──
              $idsStagiaires = array_values(array_filter(array_map('intval', array_keys($lignesNotes))));

              if (!empty($idsStagiaires)) {
                  $placeholdersDel = implode(',', array_fill(0, count($idsStagiaires), '?'));
                  $pdo->prepare(
                      "DELETE FROM module_notes WHERE id_module = ? AND id_stagiaire IN ($placeholdersDel)"
                  )->execute(array_merge([$idModuleSave], $idsStagiaires));
              }

              // ── Insertion des nouvelles notes ──
              $reqInsertion = $pdo->prepare(
                  'INSERT INTO module_notes (id_stagiaire, id_module, note, type) VALUES (?, ?, ?, ?)'
              );

              foreach ($lignesNotes as $idStagiaire => $valeursNote) {
                  $idStagiaire = (int)$idStagiaire;
                  if ($idStagiaire <= 0) continue;

                  // Contrôles 1 … nb_controles — on ignore les cases vides
                  for ($numControle = 1; $numControle <= $nbControles; $numControle++) {
                      $typeControle = "controle_$numControle";
                      $noteControle = $validerNote((string)($valeursNote[$typeControle] ?? ''));
                      if ($noteControle !== null) {
                          $reqInsertion->execute([$idStagiaire, $idModuleSave, $noteControle, $typeControle]);
                      }
                  }

                  // Note théorique — on ignore si vide
                  $noteTheorique = $validerNote((string)($valeursNote['theorique'] ?? ''));
                  if ($noteTheorique !== null) {
                      $reqInsertion->execute([$idStagiaire, $idModuleSave, $noteTheorique, 'theorique']);
                  }

                  // Note pratique — on ignore si vide
                  $notePratique = $validerNote((string)($valeursNote['pratique'] ?? ''));
                  if ($notePratique !== null) {
                      $reqInsertion->execute([$idStagiaire, $idModuleSave, $notePratique, 'pratique']);
                  }

                  $nbSauvegardes++;
              }
          }

          ob_end_clean();
          echo json_encode([
              'success' => true,
              'message' => "Notes enregistrées pour $nbSauvegardes stagiaire(s).",
              'saved'   => $nbSauvegardes,
          ]);
      } catch (\Throwable $err) {
          ob_end_clean();
          http_response_code(500);
          echo json_encode([
              'success' => false,
              'error'   => 'Erreur base de données : ' . $err->getMessage(),
          ]);
      }
      exit;
  }


  // ============================================================
  //  SECTION 2 : Lecture des paramètres de filtrage (GET)
  // ============================================================

  $anneeSelectionnee  = trim((string)($_GET['annee']      ?? ''));
  $idFiliereSelecte   = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne  = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte    = (int)($_GET['id_classe']  ?? 0);
  $idModuleSelecte    = (int)($_GET['id_module']  ?? 0);
  $idStagiaireHighlight = (int)($_GET['highlight'] ?? 0);   // surlignage depuis le hub


  // ============================================================
  //  SECTION 3 : Chargement des listes pour les filtres
  // ============================================================

  // Années scolaires disponibles
  $toutesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes
        WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
        ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);

  // Valeur par défaut : année globale de la session ou première de la liste
  if ($anneeSelectionnee === '') {
      $anneeSelectionnee = $_SESSION['global_annee_scolaire'] ?? ($toutesAnnees[0] ?? '');
  }

  // Filières disponibles (toutes années confondues)
  $toutesFilières = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f
        INNER JOIN classes c ON c.id_filiere = f.id_filiere
        ORDER BY f.nom_filiere"
  )->fetchAll();

  if ($idFiliereSelecte === 0 && !empty($toutesFilières)) {
      $idFiliereSelecte = (int)$toutesFilières[0]['id_filiere'];
  }

  // Niveaux disponibles pour la filière + année sélectionnées
  $tousNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $reqNiveaux = $pdo->prepare(
          "SELECT DISTINCT niveau FROM classes WHERE id_filiere = ? AND annee_scolaire = ? ORDER BY niveau"
      );
      $reqNiveaux->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousNiveaux = $reqNiveaux->fetchAll(PDO::FETCH_COLUMN);

      if ($niveauSelectionne === '' && !empty($tousNiveaux)) {
          $niveauSelectionne = $tousNiveaux[0];
      }
  }

  // Classes disponibles pour filière + année + niveau
  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '' && $niveauSelectionne !== '') {
      $reqClasses = $pdo->prepare(
          "SELECT id_classe, nom_classe FROM classes
            WHERE id_filiere = ? AND annee_scolaire = ? AND niveau = ?
            ORDER BY nom_classe"
      );
      $reqClasses->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $reqClasses->fetchAll();

      // Revenir à la première classe si la sélection n'est plus valide
      $idsClassesValides = array_map('intval', array_column($toutesLesClasses, 'id_classe'));
      if (!empty($toutesLesClasses) && !in_array($idClasseSelecte, $idsClassesValides, true)) {
          $idClasseSelecte = (int)$toutesLesClasses[0]['id_classe'];
      }
  }

  // Modules disponibles pour la filière sélectionnée
  $tousModules = [];
  if ($idFiliereSelecte > 0) {
      $reqModules = $pdo->prepare(
          "SELECT id_module, nom_module, nb_controles FROM modules WHERE id_filiere = ? ORDER BY nom_module"
      );
      $reqModules->execute([$idFiliereSelecte]);
      $tousModules = $reqModules->fetchAll();

      if ($idModuleSelecte === 0 && !empty($tousModules)) {
          $idModuleSelecte = (int)$tousModules[0]['id_module'];
      }
  }

  // ── Redirection auto si arrivée depuis le hub stagiaire sans module ──
  if ($idStagiaireHighlight > 0 && $idClasseSelecte > 0 && $idModuleSelecte === 0 && !empty($tousModules)) {
      $qsRedirect = http_build_query([
          'annee'      => $anneeSelectionnee,
          'id_filiere' => $idFiliereSelecte,
          'niveau'     => $niveauSelectionne,
          'id_classe'  => $idClasseSelecte,
          'id_module'  => (int)$tousModules[0]['id_module'],
          'highlight'  => $idStagiaireHighlight,
      ]);
      header("Location: notes.php?$qsRedirect");
      exit;
  }


  // ============================================================
  //  SECTION 4 : Informations sur la classe et le module sélectionnés
  // ============================================================

  $infoClasse  = null;
  $infoModule  = null;
  $nbControles = 1;

  if ($idClasseSelecte > 0) {
      $reqInfoClasse = $pdo->prepare(
          "SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire
             FROM classes c
             JOIN filieres f ON f.id_filiere = c.id_filiere
            WHERE c.id_classe = ?"
      );
      $reqInfoClasse->execute([$idClasseSelecte]);
      $infoClasse = $reqInfoClasse->fetch();
  }

  if ($idModuleSelecte > 0) {
      $reqInfoModule = $pdo->prepare(
          "SELECT nom_module, nb_controles FROM modules WHERE id_module = ?"
      );
      $reqInfoModule->execute([$idModuleSelecte]);
      $infoModule = $reqInfoModule->fetch();

      if ($infoModule) {
          $nbControles = max(1, (int)$infoModule['nb_controles']);
      }
  }


  // ============================================================
  //  SECTION 5 : Chargement des stagiaires et de leurs notes
  // ============================================================

  $stagiaires      = [];
  $notesByEtudiant = [];   // [id_stagiaire][type] = note (chaîne)

  if ($idClasseSelecte > 0 && $idModuleSelecte > 0) {

      // Liste ordonnée des stagiaires de la classe
      $reqStagiaires = $pdo->prepare(
          "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, s.cin
             FROM stagiaires s
            WHERE s.id_classe = ?
            ORDER BY s.nom, s.prenom"
      );
      $reqStagiaires->execute([$idClasseSelecte]);
      $stagiaires = $reqStagiaires->fetchAll();

      // Notes existantes pour ce module (tous stagiaires de la classe)
      if (!empty($stagiaires)) {
          $idsStagiaires   = array_column($stagiaires, 'id_stagiaire');
          $placeholdersNotes = implode(',', array_fill(0, count($idsStagiaires), '?'));

          $reqNotes = $pdo->prepare(
              "SELECT id_stagiaire, type, note
                 FROM module_notes
                WHERE id_stagiaire IN ($placeholdersNotes) AND id_module = ?"
          );
          $reqNotes->execute([...$idsStagiaires, $idModuleSelecte]);

          foreach ($reqNotes->fetchAll() as $ligneNote) {
              $notesByEtudiant[(int)$ligneNote['id_stagiaire']][$ligneNote['type']] =
                  $ligneNote['note'] !== null ? (string)$ligneNote['note'] : '';
          }
      }
  }

  require_once __DIR__ . '/includes/header.php';
  ?>
  <style>
  /* ── Conteneur principal ──────────────────────────────────────────────── */
  .notes-shell { max-width: 1200px; margin: 0 auto; padding-bottom: 3rem; }

  /* ── Carte de filtres ─────────────────────────────────────────────────── */
  .notes-filter-card {
      background: #16161e;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px;
      padding: 1.5rem;
      margin-bottom: 1.75rem;
  }
  .notes-filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 1rem;
      align-items: end;
  }
  .notes-filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
  .notes-filter-group label {
      font-size: 0.72rem;
      color: #71717a;
      text-transform: uppercase;
      letter-spacing: .08em;
      font-weight: 700;
  }
  .notes-filter-group select {
      background: #0d0d14;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px;
      color: #fff;
      padding: 0.55rem 0.75rem;
      font-size: 0.88rem;
      cursor: pointer;
      width: 100%;
  }
  .notes-filter-group select:disabled { opacity: 0.4; cursor: not-allowed; }

  /* ── Boutons ──────────────────────────────────────────────────────────── */
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

  .btn-save-notes {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(16,185,129,0.2);
      color: #10b981;
      border: 1px solid rgba(16,185,129,0.4);
      border-radius: 10px;
      padding: 0.7rem 1.8rem;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
  }
  .btn-save-notes:hover { background: rgba(16,185,129,0.35); }
  .btn-save-notes:disabled { opacity: 0.5; cursor: not-allowed; }

  /* ── Tableau des notes ────────────────────────────────────────────────── */
  .notes-table-wrap { overflow-x: auto; }
  .notes-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  .notes-table thead th {
      background: rgba(255,255,255,0.03);
      color: #71717a;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      font-weight: 800;
      padding: 0.9rem 1rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      text-align: left;
      white-space: nowrap;
  }
  .notes-table thead th:not(:first-child):not(:nth-child(2)) { text-align: center; }
  .notes-table thead th.th-group-controle {
      background: rgba(168,85,247,0.07);
      color: #c084fc;
      border-bottom-color: rgba(168,85,247,0.2);
  }
  .notes-table thead th.th-group-examen {
      background: rgba(56,189,248,0.06);
      color: #7dd3fc;
      border-bottom-color: rgba(56,189,248,0.15);
  }
  .notes-table tbody tr {
      border-bottom: 1px solid rgba(255,255,255,0.04);
      transition: background .15s;
  }
  .notes-table tbody tr:hover { background: rgba(168,85,247,0.08); }
  .notes-table tbody td { padding: 0.7rem 1rem; }
  .notes-table tbody td:not(:first-child):not(:nth-child(2)) { text-align: center; }

  .stag-name { font-weight: 700; color: #fff; font-size: 0.88rem; }
  .stag-cin  { color: #71717a; font-size: 0.75rem; margin-top: 2px; }

  /* ── Champs de saisie des notes ───────────────────────────────────────── */
  .note-input {
      background: rgba(0,0,0,0.35);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 7px;
      color: #fff;
      width: 72px;
      padding: 0.45rem 0.5rem;
      text-align: center;
      font-size: 0.88rem;
      transition: border-color .2s, background .2s;
  }
  .note-input:focus {
      outline: none;
      border-color: rgba(168,85,247,0.6);
      background: rgba(168,85,247,0.08);
  }
  .note-input.has-value { border-color: rgba(16,185,129,0.4); background: rgba(16,185,129,0.07); }

  /* ── Barre d'en-tête du tableau ───────────────────────────────────────── */
  .notes-header-bar {
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

  /* ── Toast de confirmation AJAX ───────────────────────────────────────── */
  #notes-toast {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      padding: 0.85rem 1.4rem;
      border-radius: 10px;
      font-size: 0.9rem;
      font-weight: 600;
      display: none;
      align-items: center;
      gap: 0.6rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
      animation: slideUp .25s ease;
  }
  #notes-toast.toast-success {
      background: rgba(16,185,129,0.18);
      border: 1px solid rgba(16,185,129,0.4);
      color: #6ee7b7;
  }
  #notes-toast.toast-error {
      background: rgba(239,68,68,0.18);
      border: 1px solid rgba(239,68,68,0.4);
      color: #fca5a5;
  }
  @keyframes slideUp {
      from { transform: translateY(12px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
  }

  /* ── Modal de sélection du contrôle à imprimer ────────────────────────── */
  .ctrl-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.65);
      z-index: 9999;
      align-items: center;
      justify-content: center;
  }
  .ctrl-modal-overlay.open { display: flex; }
  .ctrl-modal {
      background: #18181f;
      border: 1px solid rgba(168,85,247,0.35);
      border-radius: 14px;
      padding: 1.75rem 2rem;
      min-width: 300px;
      max-width: 90vw;
  }
  .ctrl-modal h3 {
      font-size: 1rem;
      font-weight: 700;
      color: #e4e4e7;
      margin: 0 0 1rem;
  }
  .ctrl-modal select {
      width: 100%;
      background: #0d0d14;
      border: 1px solid rgba(168,85,247,0.4);
      border-radius: 8px;
      color: #fff;
      padding: 0.55rem 0.75rem;
      font-size: 0.9rem;
      margin-bottom: 1.25rem;
      cursor: pointer;
  }
  .ctrl-modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
  .ctrl-modal-actions button {
      border-radius: 8px;
      padding: 0.5rem 1.2rem;
      font-size: 0.88rem;
      font-weight: 700;
      cursor: pointer;
      border: 1px solid transparent;
  }
  .btn-modal-cancel { background: rgba(255,255,255,0.07); color: #a1a1aa; border-color: rgba(255,255,255,0.1); }
  .btn-modal-print  { background: rgba(245,158,11,0.2);   color: #fcd34d; border-color: rgba(245,158,11,0.4); }

  /* ── État vide ────────────────────────────────────────────────────────── */
  .notes-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: #52525b;
      font-size: 0.95rem;
  }
  .notes-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; color: #3f3f46; }
  </style>

  <div class="notes-shell">

      <div style="margin-bottom:1rem;">
          <a href="index.php" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
              <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
          </a>
      </div>

      <!-- ── Carte de filtres ── -->
      <div class="notes-filter-card">
          <form method="get" action="notes.php" id="notes-filter-form">
          <div class="notes-filter-grid">

              <!-- Année scolaire -->
              <div class="notes-filter-group">
                  <label>Année scolaire</label>
                  <select name="annee" id="nf-annee">
                      <option value="">— Choisir —</option>
                      <?php foreach ($toutesAnnees as $annee): ?>
                      <option value="<?= h($annee) ?>" <?= $annee === $anneeSelectionnee ? 'selected' : '' ?>><?= h($annee) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <!-- Filière -->
              <div class="notes-filter-group">
                  <label>Filière</label>
                  <select name="id_filiere" id="nf-filiere" <?= $anneeSelectionnee === '' ? 'disabled' : '' ?>>
                      <option value="">— Choisir —</option>
                      <?php foreach ($toutesFilières as $filiere): ?>
                      <option value="<?= (int)$filiere['id_filiere'] ?>"
                          <?= (int)$filiere['id_filiere'] === $idFiliereSelecte ? 'selected' : '' ?>>
                          <?= h((string)$filiere['nom_filiere']) ?>
                      </option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <!-- Niveau -->
              <div class="notes-filter-group">
                  <label>Niveau</label>
                  <select name="niveau" id="nf-niveau" <?= ($idFiliereSelecte === 0 || $anneeSelectionnee === '') ? 'disabled' : '' ?>>
                      <?php if (empty($tousNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
                      <?php foreach ($tousNiveaux as $niveau): ?>
                      <option value="<?= h($niveau) ?>" <?= $niveau === $niveauSelectionne ? 'selected' : '' ?>><?= h($niveau) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <!-- Classe -->
              <div class="notes-filter-group">
                  <label>Classe</label>
                  <select name="id_classe" id="nf-classe" <?= ($niveauSelectionne === '' || $idFiliereSelecte === 0) ? 'disabled' : '' ?>>
                      <?php if (empty($toutesLesClasses)): ?><option value="">— Aucune —</option><?php endif; ?>
                      <?php foreach ($toutesLesClasses as $classe): ?>
                      <option value="<?= (int)$classe['id_classe'] ?>"
                          <?= (int)$classe['id_classe'] === $idClasseSelecte ? 'selected' : '' ?>>
                          <?= h((string)$classe['nom_classe']) ?>
                      </option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <!-- Module -->
              <div class="notes-filter-group">
                  <label>Module</label>
                  <select name="id_module" id="nf-module" <?= ($idFiliereSelecte === 0 || $idClasseSelecte === 0) ? 'disabled' : '' ?>>
                      <option value="">— Choisir —</option>
                      <?php foreach ($tousModules as $module): ?>
                      <option value="<?= (int)$module['id_module'] ?>"
                          <?= (int)$module['id_module'] === $idModuleSelecte ? 'selected' : '' ?>>
                          <?= h((string)$module['nom_module']) ?>
                      </option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <!-- Bouton Afficher -->
              <div class="notes-filter-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn-afficher"
                      <?= ($idClasseSelecte === 0 || $idModuleSelecte === 0) ? 'disabled' : '' ?>>
                      <i class="fa-solid fa-table-list"></i> Afficher
                  </button>
              </div>

          </div>
          </form>
      </div>

      <!-- ── Tableau de saisie des notes ── -->
      <?php if ($idClasseSelecte > 0 && $idModuleSelecte > 0): ?>

      <form method="post" action="notes.php" id="notes-save-form">
          <?= csrf_hidden() ?>
          <input type="hidden" name="bulk_save_notes" value="1">
          <input type="hidden" name="id_module"    value="<?= $idModuleSelecte ?>">
          <input type="hidden" name="id_classe"    value="<?= $idClasseSelecte ?>">
          <input type="hidden" name="annee"        value="<?= h($anneeSelectionnee) ?>">
          <input type="hidden" name="id_filiere"   value="<?= $idFiliereSelecte ?>">
          <input type="hidden" name="niveau"       value="<?= h($niveauSelectionne) ?>">
          <input type="hidden" name="nb_controles" value="<?= $nbControles ?>">

          <!-- Barre de contexte + actions -->
          <div class="notes-header-bar">
              <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;">
                  <?php if ($infoClasse): ?>
                  <div class="notes-context-badge">
                      <i class="fa-solid fa-users"></i>
                      <?= h((string)$infoClasse['nom_classe']) ?>
                      <span>·</span><?= h((string)$infoClasse['nom_filiere']) ?>
                      <span>·</span><?= h((string)$infoClasse['annee_scolaire']) ?>
                  </div>
                  <?php endif; ?>
                  <?php if ($infoModule): ?>
                  <div class="notes-context-badge" style="background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.25);color:#6ee7b7;">
                      <i class="fa-solid fa-book-open"></i>
                      <?= h((string)$infoModule['nom_module']) ?>
                  </div>
                  <?php endif; ?>
                  <div class="notes-context-badge" style="background:rgba(250,204,21,0.08);border-color:rgba(250,204,21,0.2);color:#fde047;">
                      <i class="fa-solid fa-user-graduate"></i>
                      <?= count($stagiaires) ?> stagiaire<?= count($stagiaires) !== 1 ? 's' : '' ?>
                  </div>
              </div>

              <!-- Boutons impression + enregistrement -->
              <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                  <?php
                  $qsImpression = http_build_query(['id_classe' => $idClasseSelecte, 'id_module' => $idModuleSelecte]);
                  ?>
                  <?php if ($nbControles <= 1): ?>
                  <!-- Un seul contrôle : lien direct vers le tableau d'impression -->
                  <a href="print_tableau_notes_controle.php?<?= $qsImpression ?>&controle_no=1"
                     target="_blank"
                     class="btn-save-notes"
                     style="text-decoration:none;background:rgba(245,158,11,0.15);color:#fcd34d;border-color:rgba(245,158,11,0.3);">
                      <i class="fa-solid fa-print"></i> Tableau de Contrôle
                  </a>
                  <?php else: ?>
                  <!-- Plusieurs contrôles : modal de sélection -->
                  <button type="button" onclick="ouvrirModalControle()"
                      class="btn-save-notes"
                      style="background:rgba(245,158,11,0.15);color:#fcd34d;border-color:rgba(245,158,11,0.3);">
                      <i class="fa-solid fa-print"></i> Tableau de Contrôle
                  </button>
                  <?php endif; ?>

                  <!-- Fiche vierge : tableau de contrôle sans notes (numéro de contrôle laissé vide à l'impression) -->
                  <a href="print_tableau_notes_controle.php?<?= $qsImpression ?>&vierge=1"
                     target="_blank"
                     class="btn-save-notes"
                     style="text-decoration:none;background:rgba(255,255,255,0.05);color:#a1a1aa;border-color:rgba(255,255,255,0.15);">
                      <i class="fa-solid fa-file-lines"></i> Fiche vierge
                  </a>

                  <!-- Enregistrement AJAX -->
                  <button type="submit" class="btn-save-notes" id="btn-enregistrer">
                      <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                  </button>
              </div>
          </div>

          <!-- Tableau des notes -->
          <?php if (count($stagiaires) === 0): ?>
          <div class="notes-empty">
              <i class="fa-solid fa-user-slash"></i>
              Aucun stagiaire dans cette classe.
          </div>
          <?php else: ?>
          <div class="card" style="padding:0;overflow:hidden;">
              <div class="notes-table-wrap">
                  <table class="notes-table">
                      <thead>
                          <tr>
                              <th style="width:8%;white-space:nowrap;">Code</th>
                              <th style="width:22%;">Stagiaire</th>
                              <!-- Colonnes contrôles -->
                              <?php if ($nbControles === 1): ?>
                                  <th class="th-group-controle">Contrôle</th>
                              <?php else: ?>
                                  <?php for ($numCtrl = 1; $numCtrl <= $nbControles; $numCtrl++): ?>
                                      <th class="th-group-controle">Contrôle <?= $numCtrl ?></th>
                                  <?php endfor; ?>
                              <?php endif; ?>
                              <!-- Colonnes examen -->
                              <th class="th-group-examen">Théorique</th>
                              <th class="th-group-examen">Pratique</th>
                              <th style="white-space:nowrap;text-align:center;">Relevés</th>
                          </tr>
                      </thead>
                      <tbody>
                      <?php foreach ($stagiaires as $stagiaire):
                          $idStagiaire   = (int)$stagiaire['id_stagiaire'];
                          $notesEtudiant = $notesByEtudiant[$idStagiaire] ?? [];
                      ?>
                      <tr id="row-<?= $idStagiaire ?>">
                          <!-- Numéro d'inscription -->
                          <td style="font-size:0.75rem;color:#a1a1aa;font-weight:600;white-space:nowrap;">
                              <?= h((string)($stagiaire['num_inscri'] ?? '')) ?>
                          </td>
                          <!-- Identité -->
                          <td>
                              <div class="stag-name"><?= h(trim($stagiaire['nom'] . ' ' . $stagiaire['prenom'])) ?></div>
                              <?php if (!empty($stagiaire['cin'])): ?>
                              <div class="stag-cin"><?= h((string)$stagiaire['cin']) ?></div>
                              <?php endif; ?>
                          </td>
                          <!-- Champs contrôles 1..N -->
                          <?php for ($numCtrl = 1; $numCtrl <= $nbControles; $numCtrl++):
                              $typeCtrl  = "controle_$numCtrl";
                              $valCtrl   = $notesEtudiant[$typeCtrl] ?? '';
                          ?>
                          <td>
                              <input type="number"
                                  class="note-input<?= $valCtrl !== '' ? ' has-value' : '' ?>"
                                  name="notes[<?= $idStagiaire ?>][<?= $typeCtrl ?>]"
                                  value="<?= h($valCtrl) ?>"
                                  min="0" max="20" step="0.25"
                                  placeholder="—">
                          </td>
                          <?php endfor; ?>
                          <!-- Note théorique -->
                          <td>
                              <?php $noteTheo = $notesEtudiant['theorique'] ?? ''; ?>
                              <input type="number"
                                  class="note-input<?= $noteTheo !== '' ? ' has-value' : '' ?>"
                                  name="notes[<?= $idStagiaire ?>][theorique]"
                                  value="<?= h($noteTheo) ?>"
                                  min="0" max="20" step="0.25"
                                  placeholder="—">
                          </td>
                          <!-- Note pratique -->
                          <td>
                              <?php $notePrat = $notesEtudiant['pratique'] ?? ''; ?>
                              <input type="number"
                                  class="note-input<?= $notePrat !== '' ? ' has-value' : '' ?>"
                                  name="notes[<?= $idStagiaire ?>][pratique]"
                                  value="<?= h($notePrat) ?>"
                                  min="0" max="20" step="0.25"
                                  placeholder="—">
                          </td>
                          <!-- Liens relevés individuels -->
                          <td style="text-align:center;white-space:nowrap;padding:0.4rem 0.5rem;">
                              <div style="display:flex;flex-direction:column;gap:0.3rem;align-items:center;">
                                  <a href="print_releve_notes.php?id=<?= $idStagiaire ?>&mode=combined" target="_blank"
                                     title="Relevé Complet"
                                     style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.55rem;border-radius:6px;font-size:0.72rem;font-weight:600;text-decoration:none;background:rgba(168,85,247,0.12);border:1px solid rgba(168,85,247,0.3);color:#c4b5fd;white-space:nowrap;">
                                      <i class="fa-solid fa-print"></i> Complet
                                  </a>
                                  <a href="print_releve_notes.php?id=<?= $idStagiaire ?>&mode=controle" target="_blank"
                                     title="Relevé Contrôles"
                                     style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.55rem;border-radius:6px;font-size:0.72rem;font-weight:600;text-decoration:none;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;white-space:nowrap;">
                                      <i class="fa-solid fa-list-check"></i> Contrôles
                                  </a>
                                  <a href="print_releve_notes.php?id=<?= $idStagiaire ?>&mode=examen" target="_blank"
                                     title="Relevé Examens"
                                     style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.55rem;border-radius:6px;font-size:0.72rem;font-weight:600;text-decoration:none;background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.3);color:#5eead4;white-space:nowrap;">
                                      <i class="fa-solid fa-clipboard-check"></i> Examens
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

      </form>

      <?php else: ?>
      <!-- Aucune sélection active : invitation à choisir classe + module -->
      <div class="notes-empty">
          <i class="fa-solid fa-graduation-cap"></i>
          Sélectionnez une classe et un module, puis cliquez sur <strong>Afficher</strong>.
      </div>
      <?php endif; ?>

  </div>

  <!-- ── Toast de confirmation AJAX ── -->
  <div id="notes-toast"></div>

  <!-- ── Modal de sélection du contrôle à imprimer (plusieurs contrôles) ── -->
  <?php if ($nbControles > 1): ?>
  <div class="ctrl-modal-overlay" id="ctrl-modal-overlay">
      <div class="ctrl-modal">
          <h3><i class="fa-solid fa-print" style="margin-right:.5rem;color:#fcd34d;"></i>Quel contrôle imprimer ?</h3>
          <select id="ctrl-modal-select">
              <?php for ($numCtrl = 1; $numCtrl <= $nbControles; $numCtrl++): ?>
              <option value="<?= $numCtrl ?>">Contrôle <?= $numCtrl ?></option>
              <?php endfor; ?>
          </select>
          <div class="ctrl-modal-actions">
              <button class="btn-modal-cancel" onclick="fermerModalControle()">Annuler</button>
              <button class="btn-modal-print"  onclick="imprimerControle()">
                  <i class="fa-solid fa-print" style="margin-right:.35rem;"></i>Imprimer
              </button>
          </div>
      </div>
  </div>
  <?php endif; ?>

  <script>
  (function () {
      'use strict';

      // ── Références DOM ──────────────────────────────────────────────────────
      var formFiltre  = document.getElementById('notes-filter-form');
      var selAnnee    = document.getElementById('nf-annee');
      var selFiliere  = document.getElementById('nf-filiere');
      var selNiveau   = document.getElementById('nf-niveau');
      var selClasse   = document.getElementById('nf-classe');
      var selModule   = document.getElementById('nf-module');
      var btnAfficher = formFiltre.querySelector('.btn-afficher');

      // ── Cascade des filtres : désactive les niveaux inférieurs et recharge ──
      function cascadeFiltre(selectModifie) {
          var ordre = [selAnnee, selFiliere, selNiveau, selClasse, selModule];
          var idx   = ordre.indexOf(selectModifie);
          for (var i = idx + 1; i < ordre.length; i++) {
              ordre[i].value    = '';
              ordre[i].disabled = true;
          }
          formFiltre.submit();
      }

      // Activer les selects selon l'état initial (chargement de page)
      if (selAnnee.value)   selFiliere.disabled = false;
      if (selFiliere.value) selNiveau.disabled  = false;
      if (selNiveau.value)  selClasse.disabled  = false;
      if (selClasse.value)  selModule.disabled  = false;

      // Synchronise l'état du bouton Afficher
      function syncBoutonAfficher() {
          btnAfficher.disabled = !(selClasse.value && selModule.value);
      }
      syncBoutonAfficher();

      selAnnee.addEventListener('change',   function() { cascadeFiltre(selAnnee); });
      selFiliere.addEventListener('change', function() { cascadeFiltre(selFiliere); });
      selNiveau.addEventListener('change',  function() { cascadeFiltre(selNiveau); });
      selClasse.addEventListener('change',  function() { cascadeFiltre(selClasse); });
      selModule.addEventListener('change',  function() { syncBoutonAfficher(); formFiltre.submit(); });

      // ── Coloration des champs note remplis + Enter → save (pas de saut de champ) ──
      document.querySelectorAll('.note-input').forEach(function(champ) {
          champ.addEventListener('input', function() {
              this.classList.toggle('has-value', this.value !== '');
          });
          // Enter déclenche la sauvegarde au lieu de passer au champ suivant
          champ.addEventListener('keydown', function(e) {
              if (e.key === 'Enter') {
                  e.preventDefault();
                  var btn = document.getElementById('btn-enregistrer');
                  if (btn && !btn.disabled) btn.click();
              }
          });
      });

      // ── Surlignage du stagiaire cible (paramètre highlight=) ───────────────
      var idHighlight = <?= $idStagiaireHighlight ?>;
      if (idHighlight > 0) {
          var ligneHl = document.getElementById('row-' + idHighlight);
          if (ligneHl) {
              setTimeout(function() {
                  ligneHl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  ligneHl.style.transition   = 'background .2s, outline .2s';
                  ligneHl.style.background   = 'rgba(168,85,247,0.22)';
                  ligneHl.style.outline      = '2px solid rgba(168,85,247,0.6)';
                  ligneHl.style.borderRadius = '6px';
                  setTimeout(function() {
                      ligneHl.style.background = '';
                      ligneHl.style.outline    = '';
                  }, 2800);
              }, 300);
          }
      }

      // ── Enregistrement AJAX (sans rechargement de page) ─────────────────────
      var formSave    = document.getElementById('notes-save-form');
      var btnSave     = document.getElementById('btn-enregistrer');
      var toastEl     = document.getElementById('notes-toast');
      var toastTimer  = null;

      /**
       * Affiche un toast de confirmation pendant 3 secondes.
       * @param {string} message  Texte à afficher
       * @param {string} type     'success' | 'error'
       */
      function afficherToast(message, type) {
          if (toastTimer) clearTimeout(toastTimer);
          toastEl.className   = 'toast-' + type;
          toastEl.innerHTML   = '<i class="fa-solid fa-' + (type === 'success' ? 'check-circle' : 'triangle-exclamation') + '"></i> ' + message;
          toastEl.style.display = 'flex';
          toastTimer = setTimeout(function() { toastEl.style.display = 'none'; }, 3500);
      }

      if (formSave) {
          formSave.addEventListener('submit', function(e) {
              e.preventDefault();   // empêche le rechargement complet de la page

              btnSave.disabled = true;
              btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

              fetch('notes.php', {
                  method:  'POST',
                  headers: { 'X-Requested-With': 'XMLHttpRequest' },
                  body:    new FormData(formSave),
              })
              .then(function(reponse) {
                  if (!reponse.ok) throw new Error('Erreur réseau ' + reponse.status);
                  return reponse.json();
              })
              .then(function(data) {
                  if (data.success) {
                      afficherToast(data.message, 'success');
                  } else {
                      afficherToast(data.error || 'Erreur lors de l\'enregistrement.', 'error');
                  }
              })
              .catch(function() {
                  afficherToast('Erreur de connexion. Veuillez réessayer.', 'error');
              })
              .finally(function() {
                  btnSave.disabled = false;
                  btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
              });
          });
      }

  })();

  // ── Modal impression tableau de contrôle ────────────────────────────────────
  var _urlImpression = 'print_tableau_notes_controle.php?id_classe=<?= $idClasseSelecte ?>&id_module=<?= $idModuleSelecte ?>';
  function ouvrirModalControle() {
      var el = document.getElementById('ctrl-modal-overlay');
      if (el) el.classList.add('open');
  }

  function fermerModalControle() {
      var el = document.getElementById('ctrl-modal-overlay');
      if (el) el.classList.remove('open');
  }

  function imprimerControle() {
      var sel     = document.getElementById('ctrl-modal-select');
      var numCtrl = sel ? sel.value : 1;
      window.open(_urlImpression + '&controle_no=' + numCtrl, '_blank');
      fermerModalControle();
  }

  // Fermeture de la modal avec la touche Échap
  document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') fermerModalControle();
  });
  </script>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  