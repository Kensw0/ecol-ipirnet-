<?php
  /**
   * bulletins.php — Tableau de bord des bulletins de classe
   *
   * Affiche, pour une classe et une année scolaire données, le tableau complet
   * des notes par module (contrôle, théorique, pratique, moyenne) et la moyenne
   * générale de chaque stagiaire avec son rang et son statut (admis / ajourné).
   *
   * Ce fichier est en lecture seule : aucun POST n'est traité ici.
   * La navigation vers la saisie se fait via le lien "Retour à la saisie des notes".
   *
   * Tables : classes, filieres, modules, stagiaires, module_notes
   */
  declare(strict_types=1);
  require_once __DIR__ . '/includes/auth.php';
  require_once __DIR__ . '/includes/db.php';
  require_once __DIR__ . '/includes/helpers.php';
  gds_require_admin_session();

  $pageTitle = 'Bulletins de classe';
  $curPage   = 'notes'; // Maintient l'onglet « Notes » actif dans la navigation

  // ── Helpers de rendu partagés ──────────────────────────────────────────────

  /**
   * Affiche une note brute (Contrôle, Théorique ou Pratique) sur 20.
   * Retourne un tiret mis en forme si la note est nulle.
   *
   * @param float|null $note Valeur de la note, ou null si absente.
   */
  function afficherNoteSimple(?float $note): string {
      if ($note === null) return '<span class="note-null">—</span>';
      return '<span class="note-val">' . number_format($note, 2) . '</span>';
  }

  /**
   * Affiche la moyenne d'un module avec code couleur (vert ≥ 10, rouge < 10).
   * Retourne un tiret mis en forme si la moyenne est nulle.
   *
   * @param float|null $moy Moyenne calculée, ou null si données insuffisantes.
   */
  function afficherMoyenneModule(?float $moy): string {
      if ($moy === null) return '<span class="note-null">—</span>';
      $classCouleur = $moy >= 10 ? 'moy-ok' : 'moy-fail';
      return '<span class="moy-mod ' . $classCouleur . '">' . number_format($moy, 2, ',', '') . '</span>';
  }


  // ============================================================
  //  SECTION 1 : Paramètres de filtrage (GET)
  // ============================================================

  $toutesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes
        WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
        ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);

  $anneeSelectionnee = trim((string)($_GET['annee']      ?? ''));
  if ($anneeSelectionnee === '' && !empty($toutesAnnees)) {
      $anneeSelectionnee = $toutesAnnees[0];
  }

  $idFiliereSelecte  = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte   = (int)($_GET['id_classe']  ?? 0);
  $idModuleSelecte   = (int)($_GET['id_module']  ?? 0);


  // ============================================================
  //  SECTION 2 : Données en cascade filière → niveau → classe
  // ============================================================

  // Toutes les filières ayant au moins une classe
  $toutesFilières = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f
        INNER JOIN classes c ON c.id_filiere = f.id_filiere
        ORDER BY f.nom_filiere"
  )->fetchAll();

  // Niveaux disponibles pour la filière + année sélectionnées
  $tousNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $reqNiveaux = $pdo->prepare(
          'SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau'
      );
      $reqNiveaux->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousNiveaux = $reqNiveaux->fetchAll(PDO::FETCH_COLUMN);
  }

  // Classes disponibles pour filière + année + niveau
  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '' && $niveauSelectionne !== '') {
      $reqClasses = $pdo->prepare(
          'SELECT id_classe, nom_classe FROM classes
            WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe'
      );
      $reqClasses->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $reqClasses->fetchAll();
  }


  // ============================================================
  //  SECTION 3 : Modules de la filière sélectionnée
  // ============================================================

  $listeModules = [];
  if ($idFiliereSelecte > 0) {
      $reqModules = $pdo->prepare(
          'SELECT id_module, nom_module FROM modules WHERE id_filiere=? ORDER BY nom_module'
      );
      $reqModules->execute([$idFiliereSelecte]);
      $listeModules = $reqModules->fetchAll();
  }


  // ============================================================
  //  SECTION 4 : Stagiaires + notes + calcul des moyennes
  // ============================================================

  $stagiaires        = [];
  $notesByStagiaire  = []; // [id_stagiaire][id_module] = ['nc','nt','np','moy']
  $infoClasse        = null;

  if ($idClasseSelecte > 0 && !empty($listeModules)) {
      // Informations de la classe sélectionnée (pour l'en-tête)
      $reqClasse = $pdo->prepare(
          'SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau
             FROM classes c
             JOIN filieres f ON f.id_filiere = c.id_filiere
            WHERE c.id_classe = ?'
      );
      $reqClasse->execute([$idClasseSelecte]);
      $infoClasse = $reqClasse->fetch();

      // Liste des stagiaires de la classe, triés alphabétiquement
      $reqStagiaires = $pdo->prepare(
          'SELECT id_stagiaire, nom, prenom, cin FROM stagiaires WHERE id_classe=? ORDER BY nom, prenom'
      );
      $reqStagiaires->execute([$idClasseSelecte]);
      $stagiaires = $reqStagiaires->fetchAll();

      if (!empty($stagiaires)) {
          $idsStagiaires = array_column($stagiaires, 'id_stagiaire');
          $idsModules    = array_column($listeModules, 'id_module');

          $placeholdersSid = implode(',', array_fill(0, count($idsStagiaires), '?'));
          $placeholdersMid = implode(',', array_fill(0, count($idsModules), '?'));

          // Récupérer toutes les notes en une seule requête (évite N×M requêtes)
          $reqNotesBrutes = $pdo->prepare(
              "SELECT id_stagiaire, id_module, type, note
                 FROM module_notes
                WHERE id_stagiaire IN ($placeholdersSid)
                  AND id_module    IN ($placeholdersMid)"
          );
          $reqNotesBrutes->execute(array_merge($idsStagiaires, $idsModules));

          // Indexer les notes brutes : notesBrutes[id_stag][id_mod][type] = valeur
          $notesBrutes = [];
          foreach ($reqNotesBrutes->fetchAll() as $noteEntry) {
              $notesBrutes[(int)$noteEntry['id_stagiaire']]
                          [(int)$noteEntry['id_module']]
                          [$noteEntry['type']] =
                  $noteEntry['note'] !== null ? (float)$noteEntry['note'] : null;
          }

          // Calculer nc / nt / np / moy pour chaque combinaison stagiaire × module
          foreach ($notesBrutes as $idStagiaire => $donneesModules) {
              foreach ($donneesModules as $idModule => $donneesTypes) {
                  // Contrôle : moyenne de tous les controle_N disponibles
                  $valeursControle = [];
                  foreach ($donneesTypes as $typeNote => $valeur) {
                      if (str_starts_with($typeNote, 'controle_') && $valeur !== null) {
                          $valeursControle[] = $valeur;
                      }
                  }
                  $noteControle   = !empty($valeursControle) ? array_sum($valeursControle) / count($valeursControle) : null;
                  $noteTheorique  = $donneesTypes['theorique'] ?? null;
                  $notePratique   = $donneesTypes['pratique']  ?? null;

                  // Formule pondérée : 40 % contrôle + 30 % théorique + 30 % pratique
                  // (dégradée si une ou deux composantes sont absentes)
                  if ($noteControle !== null && $noteTheorique !== null && $notePratique !== null) {
                      $moyenneModule = round($noteControle * 0.4 + $noteTheorique * 0.3 + $notePratique * 0.3, 2);
                  } elseif ($noteControle !== null && ($noteTheorique !== null || $notePratique !== null)) {
                      $moyenneModule = round($noteControle * 0.4 + ($noteTheorique ?? 0.0) * 0.3 + ($notePratique ?? 0.0) * 0.3, 2);
                  } elseif ($noteControle !== null) {
                      $moyenneModule = round($noteControle, 2);
                  } else {
                      $moyenneModule = null;
                  }

                  $notesByStagiaire[$idStagiaire][$idModule] = [
                      'nc'  => $noteControle,
                      'nt'  => $noteTheorique,
                      'np'  => $notePratique,
                      'moy' => $moyenneModule,
                  ];
              }
          }
      }

      // Calculer la moyenne générale de chaque stagiaire (sur tous les modules)
      foreach ($stagiaires as &$stagiaire) {
          $idStagiaire        = (int)$stagiaire['id_stagiaire'];
          $sommeMoyennes      = 0.0;
          $nbModulesAvecMoy   = 0;
          foreach ($listeModules as $module) {
              $idModule = (int)$module['id_module'];
              $entree   = $notesByStagiaire[$idStagiaire][$idModule] ?? null;
              if ($entree && $entree['moy'] !== null) {
                  $sommeMoyennes    += $entree['moy'];
                  $nbModulesAvecMoy++;
              }
          }
          $stagiaire['moy_generale'] = $nbModulesAvecMoy > 0
              ? round($sommeMoyennes / $nbModulesAvecMoy, 2)
              : null;
      }
      unset($stagiaire);

      // Trier par moyenne décroissante puis attribuer les rangs
      usort($stagiaires, function ($a, $b) {
          if ($a['moy_generale'] === null && $b['moy_generale'] === null) return 0;
          if ($a['moy_generale'] === null) return 1;
          if ($b['moy_generale'] === null) return -1;
          return $b['moy_generale'] <=> $a['moy_generale'];
      });

      $rangCourant = 1;
      foreach ($stagiaires as &$stagiaire) {
          $stagiaire['rang'] = $stagiaire['moy_generale'] !== null ? $rangCourant++ : null;
      }
      unset($stagiaire);
  }

  require_once __DIR__ . '/includes/header.php';
  ?>
  <style>
  .bul-shell { max-width: 1200px; margin: 0 auto; padding-bottom: 3rem; }

  /* ── Carte de filtrage ── */
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

  /* ── Barre d'en-tête ── */
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

  /* ── Tableau des bulletins ── */
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

  /* ── Badges de rang (or, argent, bronze, autres) ── */
  .rang-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px; height: 28px;
      border-radius: 50%;
      font-weight: 800;
      font-size: 0.8rem;
  }
  .rang-1     { background: rgba(250,204,21,0.2);  color: #fde047; border: 1px solid rgba(250,204,21,0.3);  }
  .rang-2     { background: rgba(161,161,170,0.15); color: #d4d4d8; border: 1px solid rgba(161,161,170,0.25); }
  .rang-3     { background: rgba(180,83,9,0.15);   color: #fdba74; border: 1px solid rgba(180,83,9,0.3);   }
  .rang-other { background: rgba(255,255,255,0.04); color: #71717a; border: 1px solid rgba(255,255,255,0.08); }

  /* ── Valeurs de notes ── */
  .note-val   { color: #e4e4e7; }
  .note-null  { color: #3f3f46; }
  .moy-mod    { font-weight: 700; }
  .moy-ok     { color: #34d399; }
  .moy-fail   { color: #f87171; }

  .moy-gen-cell    { font-size: 1rem; font-weight: 800; }
  .statut-admis    { color: #34d399; font-size: 0.72rem; font-weight: 700; }
  .statut-ajourne  { color: #f87171; font-size: 0.72rem; font-weight: 700; }

  .col-moy-gen {
      background: rgba(168,85,247,0.06);
      border-left: 1px solid rgba(168,85,247,0.15);
      border-right: 1px solid rgba(168,85,247,0.15);
  }
  .col-sep { border-left: 1px solid rgba(255,255,255,0.05); }

  /* ── États vides ── */
  .notes-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: #52525b;
      font-size: 0.95rem;
  }
  .notes-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; color: #3f3f46; }

  /* ── Barre de statistiques de classe ── */
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
  .bul-stat-val   { font-size: 1.6rem; font-weight: 800; color: #fff; line-height: 1; }
  .bul-stat-label { font-size: 0.72rem; color: #71717a; text-transform: uppercase; letter-spacing: .08em; margin-top: 0.3rem; }
  </style>

  <div class="bul-shell">

      <!-- Lien de retour vers la saisie des notes -->
      <div style="margin-bottom:1rem;">
          <?php
          // Conserver tous les filtres actifs pour revenir sur la même vue dans notes.php
          $paramsRetour = http_build_query([
              'annee'      => $anneeSelectionnee,
              'id_filiere' => $idFiliereSelecte,
              'niveau'     => $niveauSelectionne,
              'id_classe'  => $idClasseSelecte,
              'id_module'  => $idModuleSelecte,
          ]);
          ?>
          <a href="notes.php?<?= $paramsRetour ?>" style="color:#a855f7;font-size:0.85rem;font-weight:600;text-decoration:none;">
              <i class="fa-solid fa-arrow-left"></i> Retour à la saisie des notes
          </a>
      </div>

      <!-- ── Formulaire de filtrage ── -->
      <div class="notes-filter-card">
          <form method="get" action="bulletins.php" id="bul-filter-form">
          <div class="notes-filter-grid">

              <div class="notes-filter-group">
                  <label>Année scolaire</label>
                  <select name="annee" id="bf-annee">
                      <option value="">— Choisir —</option>
                      <?php foreach ($toutesAnnees as $annee): ?>
                      <option value="<?= h($annee) ?>" <?= $annee === $anneeSelectionnee ? 'selected' : '' ?>><?= h($annee) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <div class="notes-filter-group">
                  <label>Filière</label>
                  <select name="id_filiere" id="bf-filiere" <?= $anneeSelectionnee === '' ? 'disabled' : '' ?>>
                      <option value="">— Choisir —</option>
                      <?php foreach ($toutesFilières as $filiere): ?>
                      <option value="<?= (int)$filiere['id_filiere'] ?>" <?= (int)$filiere['id_filiere'] === $idFiliereSelecte ? 'selected' : '' ?>><?= h((string)$filiere['nom_filiere']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <div class="notes-filter-group">
                  <label>Niveau</label>
                  <select name="niveau" id="bf-niveau" <?= ($idFiliereSelecte === 0 || $anneeSelectionnee === '') ? 'disabled' : '' ?>>
                      <option value="">— Choisir —</option>
                      <?php foreach ($tousNiveaux as $niveau): ?>
                      <option value="<?= h($niveau) ?>" <?= $niveau === $niveauSelectionne ? 'selected' : '' ?>><?= h($niveau) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <div class="notes-filter-group">
                  <label>Classe</label>
                  <select name="id_classe" id="bf-classe" <?= ($niveauSelectionne === '' || $idFiliereSelecte === 0) ? 'disabled' : '' ?>>
                      <option value="">— Choisir —</option>
                      <?php foreach ($toutesLesClasses as $classe): ?>
                      <option value="<?= (int)$classe['id_classe'] ?>" <?= (int)$classe['id_classe'] === $idClasseSelecte ? 'selected' : '' ?>><?= h((string)$classe['nom_classe']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>

              <div class="notes-filter-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn-afficher" <?= $idClasseSelecte === 0 ? 'disabled' : '' ?>>
                      <i class="fa-solid fa-chart-bar"></i> Afficher
                  </button>
              </div>

          </div>
          </form>
      </div>

      <?php if ($idClasseSelecte > 0 && !empty($stagiaires) && !empty($listeModules)): ?>

      <?php
      // ============================================================
      //  SECTION 5 : Statistiques récapitulatives de la classe
      // ============================================================
      $nbAdmis        = 0;
      $nbAvecMoyenne  = 0;
      $cumulMoyClasse = 0.0;
      foreach ($stagiaires as $stagiaire) {
          if ($stagiaire['moy_generale'] !== null) {
              $nbAvecMoyenne++;
              $cumulMoyClasse += $stagiaire['moy_generale'];
              if ($stagiaire['moy_generale'] >= 10) $nbAdmis++;
          }
      }
      $moyenneClasse = $nbAvecMoyenne > 0 ? round($cumulMoyClasse / $nbAvecMoyenne, 2) : null;
      $tauxReussite  = $nbAvecMoyenne > 0 ? round($nbAdmis / $nbAvecMoyenne * 100) : 0;
      ?>

      <!-- Badges contextuels (classe, effectif, nombre de modules) -->
      <div class="bul-header-bar">
          <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;">
              <?php if ($infoClasse): ?>
              <div class="notes-context-badge">
                  <i class="fa-solid fa-users"></i>
                  <?= h((string)$infoClasse['nom_classe']) ?>
                  <span>·</span><?= h((string)$infoClasse['nom_filiere']) ?>
                  <span>·</span><?= h((string)$infoClasse['annee_scolaire']) ?>
              </div>
              <?php endif; ?>
              <div class="notes-context-badge" style="background:rgba(250,204,21,0.08);border-color:rgba(250,204,21,0.2);color:#fde047;">
                  <i class="fa-solid fa-user-graduate"></i>
                  <?= count($stagiaires) ?> stagiaire<?= count($stagiaires) !== 1 ? 's' : '' ?>
              </div>
              <div class="notes-context-badge" style="background:rgba(56,189,248,0.08);border-color:rgba(56,189,248,0.2);color:#7dd3fc;">
                  <i class="fa-solid fa-book-open"></i>
                  <?= count($listeModules) ?> module<?= count($listeModules) !== 1 ? 's' : '' ?>
              </div>
          </div>
      </div>

      <!-- Cartes de statistiques : effectif, admis, ajournés, taux, moyenne -->
      <div class="bul-stats">
          <div class="bul-stat-card">
              <div class="bul-stat-val"><?= count($stagiaires) ?></div>
              <div class="bul-stat-label">Stagiaires</div>
          </div>
          <div class="bul-stat-card">
              <div class="bul-stat-val" style="color:#34d399;"><?= $nbAdmis ?></div>
              <div class="bul-stat-label">Admis</div>
          </div>
          <div class="bul-stat-card">
              <div class="bul-stat-val" style="color:#f87171;"><?= $nbAvecMoyenne - $nbAdmis ?></div>
              <div class="bul-stat-label">Ajournés</div>
          </div>
          <div class="bul-stat-card">
              <div class="bul-stat-val" style="color:#a855f7;"><?= $tauxReussite ?>%</div>
              <div class="bul-stat-label">Taux de réussite</div>
          </div>
          <?php if ($moyenneClasse !== null): ?>
          <div class="bul-stat-card">
              <div class="bul-stat-val" style="color:#fde047;"><?= number_format($moyenneClasse, 2, ',', '') ?></div>
              <div class="bul-stat-label">Moyenne classe</div>
          </div>
          <?php endif; ?>
      </div>

      <!-- ── Tableau des bulletins ── -->
      <div class="card" style="padding:0;overflow:hidden;">
          <div class="bul-table-wrap">
              <table class="bul-table">
                  <thead>
                      <!-- Ligne 1 : colonnes Rang + Stagiaire + une colonne par module + Moy. générale -->
                      <tr>
                          <th style="width:40px;">Rang</th>
                          <th style="min-width:160px;">Stagiaire</th>
                          <?php foreach ($listeModules as $module): ?>
                          <th colspan="4" class="col-sep"><?= h((string)$module['nom_module']) ?></th>
                          <?php endforeach; ?>
                          <th colspan="2" class="col-moy-gen">Moyenne générale</th>
                      </tr>
                      <!-- Ligne 2 : sous-entêtes (Ctrl / Théo / Prat / Moy par module) -->
                      <tr class="sub-header">
                          <th></th>
                          <th></th>
                          <?php foreach ($listeModules as $module): ?>
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
                  <?php foreach ($stagiaires as $stagiaire):
                      $idStagiaire  = (int)$stagiaire['id_stagiaire'];
                      $rangStagiaire = $stagiaire['rang'];
                      $moyGenerale  = $stagiaire['moy_generale'];
                      // Classe CSS du badge de rang (or / argent / bronze / autres)
                      $classeRang = match($rangStagiaire) {
                          1 => 'rang-1', 2 => 'rang-2', 3 => 'rang-3', default => 'rang-other'
                      };
                  ?>
                  <tr>
                      <!-- Rang -->
                      <td>
                          <?php if ($rangStagiaire !== null): ?>
                          <span class="rang-badge <?= $classeRang ?>"><?= $rangStagiaire ?></span>
                          <?php else: ?>
                          <span class="note-null">—</span>
                          <?php endif; ?>
                      </td>
                      <!-- Identité -->
                      <td>
                          <div class="stag-name"><?= h(trim($stagiaire['nom'] . ' ' . $stagiaire['prenom'])) ?></div>
                          <?php if (!empty($stagiaire['cin'])): ?>
                          <div class="stag-cin"><?= h((string)$stagiaire['cin']) ?></div>
                          <?php endif; ?>
                      </td>
                      <!-- Notes par module (Ctrl / Théo / Prat / Moy) -->
                      <?php foreach ($listeModules as $module):
                          $idModule    = (int)$module['id_module'];
                          $entreeNote  = $notesByStagiaire[$idStagiaire][$idModule] ?? null;
                          $noteCtrl    = $entreeNote['nc']  ?? null;
                          $noteTheo    = $entreeNote['nt']  ?? null;
                          $notePrat    = $entreeNote['np']  ?? null;
                          $moyModule   = $entreeNote['moy'] ?? null;
                      ?>
                      <td class="col-sep"><?= afficherNoteSimple($noteCtrl) ?></td>
                      <td><?= afficherNoteSimple($noteTheo) ?></td>
                      <td><?= afficherNoteSimple($notePrat) ?></td>
                      <td><?= afficherMoyenneModule($moyModule) ?></td>
                      <?php endforeach; ?>
                      <!-- Moyenne générale + statut -->
                      <td class="col-moy-gen moy-gen-cell <?= $moyGenerale !== null ? ($moyGenerale >= 10 ? 'moy-ok' : 'moy-fail') : 'note-null' ?>">
                          <?= $moyGenerale !== null ? number_format($moyGenerale, 2, ',', '') : '—' ?>
                      </td>
                      <td class="col-moy-gen">
                          <?php if ($moyGenerale !== null): ?>
                          <span class="<?= $moyGenerale >= 10 ? 'statut-admis' : 'statut-ajourne' ?>">
                              <?= $moyGenerale >= 10 ? 'Admis(e)' : 'Ajourné(e)' ?>
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

      <?php elseif ($idClasseSelecte > 0 && empty($stagiaires)): ?>
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

  </div><!-- /bul-shell -->

  <script>
  (function () {
      // ── Cascade de filtres ─────────────────────────────────────────────────
      // Règle : changer un filtre réinitialise tous les filtres en aval
      // et soumet le formulaire immédiatement (sauf pour Classe qui attend
      // le clic sur "Afficher").
      const form    = document.getElementById('bul-filter-form');
      const annee   = document.getElementById('bf-annee');
      const filiere = document.getElementById('bf-filiere');
      const niveau  = document.getElementById('bf-niveau');
      const classe  = document.getElementById('bf-classe');
      const btn     = form.querySelector('.btn-afficher');

      /** Réinitialise les filtres en aval du filtre modifié et soumet. */
      function cascade(changed) {
          const ordre = [annee, filiere, niveau, classe];
          const idx   = ordre.indexOf(changed);
          for (let i = idx + 1; i < ordre.length; i++) {
              ordre[i].value    = '';
              ordre[i].disabled = true;
          }
          form.submit();
      }

      /** Active le bouton "Afficher" uniquement quand une classe est sélectionnée. */
      function syncBouton() { btn.disabled = !classe.value; }

      // Réactiver les selects déjà remplis au chargement (valeurs conservées par PHP)
      if (annee.value)   filiere.disabled = false;
      if (filiere.value) niveau.disabled  = false;
      if (niveau.value)  classe.disabled  = false;

      syncBouton();

      annee.addEventListener('change',   () => cascade(annee));
      filiere.addEventListener('change', () => cascade(filiere));
      niveau.addEventListener('change',  () => cascade(niveau));
      // La classe ne soumet pas automatiquement : l'utilisateur clique "Afficher"
      classe.addEventListener('change',  () => syncBouton());
  }());
  </script>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  