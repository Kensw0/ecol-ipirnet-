<?php
  /**
   * gestion_modules.php — Administration des modules pédagogiques
   *
   * Permet de :
   *   - Consulter et modifier les paramètres d'un module
   *     (coefficient, semestre, masse horaire, nombre de contrôles)
   *   - Créer un nouveau module rattaché à une filière
   *   - Visualiser les notes des stagiaires de la filière pour le module actif
   *
   * Actions POST (réponse JSON) :
   *   • save_all_fields — mise à jour des champs d'un module existant
   *   • add_module      — création d'un nouveau module
   *
   * Tables : modules, filieres, stagiaires, classes, module_notes
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  $pageTitle = 'Gestion des Modules';
  $curPage   = 'modules';


  // ── Helpers partagés ──────────────────────────────────────────────────────

  /**
   * Valide les champs numériques communs aux deux handlers POST.
   *
   * Centralise la logique de validation pour éviter sa duplication entre
   * le handler "save_all_fields" et le handler "add_module".
   *
   * @param float $coefficient  Valeur du coefficient (plage autorisée : 1–7).
   * @param int   $semestre     Numéro du semestre (1 ou 2).
   * @param float $masseHoraire Volume horaire du module (0–9999 h).
   * @param int   $nbControles  Nombre de contrôles planifiés (0–10).
   * @return string|null         Message d'erreur si invalide, null si tout est correct.
   */
  function validerChampsModule(
      float $coefficient,
      int   $semestre,
      float $masseHoraire,
      int   $nbControles
  ): ?string {
      if ($coefficient  < 1  || $coefficient  > 7)    return 'Coefficient invalide (1–7).';
      if ($semestre     < 1  || $semestre     > 2)    return 'Semestre invalide (1 ou 2).';
      if ($masseHoraire < 0  || $masseHoraire > 9999) return 'Masse horaire invalide (0–9999 h).';
      if ($nbControles  < 0  || $nbControles  > 10)   return 'Nb. contrôles invalide (0–10).';
      return null;
  }

  /**
   * Retourne la classe CSS du badge de note affiché dans le tableau stagiaires.
   *
   * Seuils : ≥ 10 → good (vert), ≥ 7 → avg (orange), < 7 → fail (rouge), null → none (gris).
   *
   * @param float|null $note Valeur de la note, ou null si absente.
   */
  function classeNoteBadge(?float $note): string {
      if ($note === null) return 'none';
      return $note >= 10 ? 'good' : ($note >= 7 ? 'avg' : 'fail');
  }


  // ============================================================
  //  SECTION 1 : Handler POST — enregistrement des champs du module
  // ============================================================

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
      header('Content-Type: application/json');

      // ── Mise à jour des champs d'un module existant ───────────────────────
      if (isset($_POST['save_all_fields'])) {
          $idModulePost     = (int)($_POST['id_module']     ?? 0);
          $nbControlesPost  = (int)($_POST['nb_controles']  ?? 0);
          $coefficientPost  = (float)str_replace(',', '.', (string)($_POST['coefficient']   ?? 0));
          $semestrePost     = (int)($_POST['semestre']      ?? 0);
          $masseHorairePost = (float)str_replace(',', '.', (string)($_POST['masse_horaire'] ?? 0));

          if ($idModulePost <= 0) {
              echo json_encode(['success' => false, 'error' => 'Module introuvable.']); exit;
          }

          // Validation des champs numériques (factoriée dans validerChampsModule)
          $erreurValidation = validerChampsModule($coefficientPost, $semestrePost, $masseHorairePost, $nbControlesPost);
          if ($erreurValidation !== null) {
              echo json_encode(['success' => false, 'error' => $erreurValidation]); exit;
          }

          try {
              $pdo->prepare(
                  'UPDATE modules SET nb_controles=?, coefficient=?, semestre=?, masse_horaire=? WHERE id_module=?'
              )->execute([$nbControlesPost, $coefficientPost, $semestrePost, $masseHorairePost, $idModulePost]);
              echo json_encode(['success' => true, 'msg' => 'Module mis à jour.']);
          } catch (\Throwable $e) {
              error_log('[gestion_modules.php] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
          }
          exit;
      }


  // ============================================================
  //  SECTION 2 : Handler POST — création d'un nouveau module
  // ============================================================

      if (isset($_POST['add_module'])) {
          $nomModulePost    = trim((string)($_POST['nom_module']    ?? ''));
          $idFilierePost    = (int)($_POST['id_filiere']            ?? 0);
          $coefficientPost  = (float)str_replace(',', '.', (string)($_POST['coefficient']   ?? 0));
          $semestrePost     = (int)($_POST['semestre']              ?? 0);
          $masseHorairePost = (float)str_replace(',', '.', (string)($_POST['masse_horaire'] ?? 0));
          $nbControlesPost  = (int)($_POST['nb_controles']          ?? 0);

          if ($nomModulePost === '' || $idFilierePost <= 0) {
              echo json_encode(['success' => false, 'error' => 'Nom du module et filière requis.']); exit;
          }

          // Validation des champs numériques (même helper que save_all_fields)
          $erreurValidation = validerChampsModule($coefficientPost, $semestrePost, $masseHorairePost, $nbControlesPost);
          if ($erreurValidation !== null) {
              echo json_encode(['success' => false, 'error' => $erreurValidation]); exit;
          }

          try {
              $pdo->prepare(
                  'INSERT INTO modules (nom_module, id_filiere, coefficient, semestre, masse_horaire, nb_controles)
                   VALUES (?,?,?,?,?,?)'
              )->execute([$nomModulePost, $idFilierePost, $coefficientPost, $semestrePost, $masseHorairePost, $nbControlesPost]);
              $idNouveauModule = (int)$pdo->lastInsertId();
              echo json_encode([
                  'success'    => true,
                  'msg'        => 'Module créé.',
                  'id_module'  => $idNouveauModule,
                  'id_filiere' => $idFilierePost,
              ]);
          } catch (\Throwable $e) {
              error_log('[gestion_modules.php] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
          }
          exit;
      }

      echo json_encode(['success' => false, 'error' => 'Action inconnue.']); exit;
  }


  // ============================================================
  //  SECTION 3 : Paramètres de filtrage (GET)
  // ============================================================

  $idFiliereSelecte = (int)($_GET['id_filiere'] ?? 0);
  $idModuleSelecte  = (int)($_GET['id_module']  ?? 0);


  // ============================================================
  //  SECTION 4 : Données en cascade filière → module
  // ============================================================

  // Liste de toutes les filières (pour le sélecteur)
  $toutesFilières = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();

  // Auto-sélection de la première filière si aucune n'est choisie
  if ($idFiliereSelecte === 0 && !empty($toutesFilières)) {
      $idFiliereSelecte = (int)$toutesFilières[0]['id_filiere'];
  }

  // Modules de la filière sélectionnée, groupés par semestre
  $tousModules = [];
  if ($idFiliereSelecte > 0) {
      $reqModules = $pdo->prepare(
          'SELECT id_module, nom_module, semestre FROM modules WHERE id_filiere=? ORDER BY semestre, nom_module'
      );
      $reqModules->execute([$idFiliereSelecte]);
      $tousModules = $reqModules->fetchAll();

      // Auto-sélection du premier module si aucun n'est choisi
      if ($idModuleSelecte === 0 && !empty($tousModules)) {
          $idModuleSelecte = (int)$tousModules[0]['id_module'];
      }
  }


  // ============================================================
  //  SECTION 5 : Détails du module sélectionné
  // ============================================================

  $infoModule  = null;
  $nbControles = 0; // Nombre de contrôles configuré pour ce module

  if ($idModuleSelecte > 0) {
      $reqInfoModule = $pdo->prepare(
          'SELECT m.*, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere=m.id_filiere WHERE m.id_module=?'
      );
      $reqInfoModule->execute([$idModuleSelecte]);
      $infoModule  = $reqInfoModule->fetch();
      $nbControles = $infoModule ? (int)($infoModule['nb_controles'] ?? 0) : 0;
  }


  // ============================================================
  //  SECTION 6 : Liste des stagiaires avec leurs notes pour ce module
  // ============================================================

  $listeEtudiants   = [];
  $anneeScolaireGlobale = $_SESSION['global_annee_scolaire'] ?? '';

  if ($idModuleSelecte > 0 && $idFiliereSelecte > 0 && $anneeScolaireGlobale !== '') {
      // Récupérer tous les stagiaires de la filière pour l'année en cours
      $reqStagiaires = $pdo->prepare('
          SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, c.nom_classe, c.niveau
            FROM stagiaires s
            JOIN classes c ON c.id_classe = s.id_classe
           WHERE c.id_filiere = ? AND c.annee_scolaire = ?
           ORDER BY c.nom_classe, s.nom, s.prenom
      ');
      $reqStagiaires->execute([$idFiliereSelecte, $anneeScolaireGlobale]);
      $donneesBrutes = $reqStagiaires->fetchAll();

      if (!empty($donneesBrutes)) {
          $idsStagiaires   = array_column($donneesBrutes, 'id_stagiaire');
          $placeholdersSid = implode(',', array_fill(0, count($idsStagiaires), '?'));

          // Charger toutes les notes du module en une seule requête
          $reqNotes = $pdo->prepare(
              "SELECT id_stagiaire, type, note FROM module_notes
                WHERE id_module=? AND id_stagiaire IN ($placeholdersSid)"
          );
          $reqNotes->execute(array_merge([$idModuleSelecte], $idsStagiaires));

          // Indexer : notesParEtudiant[id_stagiaire][type] = valeur
          $notesParEtudiant = [];
          foreach ($reqNotes->fetchAll() as $noteEntry) {
              if ($noteEntry['note'] !== null) {
                  $notesParEtudiant[(int)$noteEntry['id_stagiaire']][$noteEntry['type']] = (float)$noteEntry['note'];
              }
          }

          // Enrichir chaque stagiaire avec ses notes calculées
          foreach ($donneesBrutes as $etudiant) {
              $idStagiaire  = (int)$etudiant['id_stagiaire'];
              $toutesNotes  = $notesParEtudiant[$idStagiaire] ?? [];

              // Moyenne des contrôles : ne prend que les clés controle_* disponibles
              $notesControle = array_filter($toutesNotes, fn($k) => str_starts_with($k, 'controle_'), ARRAY_FILTER_USE_KEY);
              $etudiant['moy_controles'] = count($notesControle) > 0
                  ? round(array_sum($notesControle) / count($notesControle), 2)
                  : null;
              $etudiant['nb_saisies']  = count($notesControle);
              $etudiant['theorique']   = $toutesNotes['theorique'] ?? null;
              $etudiant['pratique']    = $toutesNotes['pratique']  ?? null;
              $listeEtudiants[] = $etudiant;
          }
      }
  }

  require __DIR__ . '/includes/header.php';
  ?>
  <style>
  /* ── Barre de filtrage filière / module ── */
  .mod-filter-bar{display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.5rem}
  .mod-filter-bar label{font-size:.78rem;color:#a1a1aa;text-transform:uppercase;letter-spacing:.08em;font-weight:600;display:block;margin-bottom:.35rem}
  .mod-filter-bar select{background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.45rem .85rem;font-size:.88rem;min-width:190px;outline:none;cursor:pointer}
  .mod-filter-bar select:focus{border-color:#a855f7}

  /* ── Cartes de contenu ── */
  .mod-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem}
  .mod-card h2{font-size:1.15rem;font-weight:700;color:#fff;margin:0 0 1rem}
  .mod-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.25rem}
  .mod-meta-item{background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.15);border-radius:10px;padding:.75rem 1rem}
  .mod-meta-item .label{font-size:.7rem;color:rgba(168,85,247,.7);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:.25rem}
  .mod-meta-item .value{font-size:1rem;font-weight:700;color:#e4e4e7}

  /* ── Champs éditables du module ── */
  .mod-nb-form{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
  .mod-nb-form label{font-size:.82rem;color:#a1a1aa;font-weight:600}
  .mod-nb-input{background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.4rem .75rem;font-size:.9rem;width:80px;text-align:center;outline:none}
  .mod-nb-input:focus{border-color:#a855f7}
  .btn-save-nb{background:#a855f7;color:#fff;border:none;border-radius:8px;padding:.45rem 1.1rem;font-size:.85rem;font-weight:700;cursor:pointer;transition:background .2s}
  .btn-save-nb:hover{background:#9333ea}
  .mod-save-msg{font-size:.82rem;font-weight:600;margin-left:.5rem}

  /* ── Tableau des stagiaires ── */
  .stag-table-wrap{overflow-x:auto}
  .stag-table{width:100%;border-collapse:collapse;font-size:.85rem}
  .stag-table th{background:rgba(168,85,247,.12);color:#d8b4fe;font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;padding:.65rem 1rem;text-align:left;white-space:nowrap}
  .stag-table td{padding:.6rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);color:#e4e4e7;vertical-align:middle;white-space:nowrap}
  .stag-table tr:hover td{background:rgba(168,85,247,.04)}

  /* ── Badges de note (good / avg / fail / none) ── */
  .badge-note{display:inline-block;padding:.15rem .55rem;border-radius:6px;font-weight:700;font-size:.8rem}
  .badge-note.good{background:rgba(16,185,129,.15);color:#6ee7b7}
  .badge-note.avg {background:rgba(245,158,11,.15);color:#fcd34d}
  .badge-note.fail{background:rgba(239,68,68,.15); color:#fca5a5}
  .badge-note.none{background:rgba(255,255,255,.05);color:#71717a}

  .link-hub{color:#a855f7;text-decoration:none;font-size:.8rem;font-weight:600}
  .link-hub:hover{color:#d8b4fe;text-decoration:underline}
  .empty-state{text-align:center;padding:3rem 1rem;color:#52525b}
  .empty-state i{font-size:2rem;margin-bottom:.75rem;display:block}
  </style>

  <!-- ── Barre de filtrage (filière + module) ── -->
  <div class="mod-filter-bar">
      <div>
          <label for="sel-filiere">Filière</label>
          <select id="sel-filiere" onchange="applyFilter()">
              <?php foreach ($toutesFilières as $filiere): ?>
              <option value="<?= h((string)$filiere['id_filiere']) ?>" <?= $idFiliereSelecte === (int)$filiere['id_filiere'] ? 'selected' : '' ?>><?= h($filiere['nom_filiere']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <?php if (!empty($tousModules)): ?>
      <div>
          <label for="sel-module">Module</label>
          <select id="sel-module" onchange="applyFilter()">
              <?php
              // Grouper les modules par semestre (optgroup)
              $semestreCourant = null;
              foreach ($tousModules as $module):
                  if ($module['semestre'] !== $semestreCourant) {
                      if ($semestreCourant !== null) echo '</optgroup>';
                      echo '<optgroup label="' . h('Semestre ' . $module['semestre']) . '">';
                      $semestreCourant = $module['semestre'];
                  }
              ?>
              <option value="<?= h((string)$module['id_module']) ?>" <?= $idModuleSelecte === (int)$module['id_module'] ? 'selected' : '' ?>><?= h($module['nom_module']) ?></option>
              <?php endforeach; if ($semestreCourant !== null) echo '</optgroup>'; ?>
          </select>
      </div>
      <?php endif; ?>
      <div style="margin-left:auto;align-self:flex-end;">
          <button class="btn-save-nb" onclick="openAddModal()" style="background:rgba(168,85,247,.18);border:1px solid rgba(168,85,247,.4);color:#d8b4fe;padding:.5rem 1.2rem;">
              <i class="fa-solid fa-plus" style="margin-right:.4rem;"></i>Ajouter un module
          </button>
      </div>
  </div>

  <!-- ── Modal : ajout d'un nouveau module ── -->
  <div id="modal-add-module" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
      <div style="background:#12121f;border:1px solid rgba(168,85,247,.35);border-radius:16px;padding:2rem;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.5);position:relative;">
          <button onclick="closeAddModal()" style="position:absolute;top:.85rem;right:.85rem;background:none;border:none;color:#71717a;font-size:1.1rem;cursor:pointer;">✕</button>
          <h3 style="margin:0 0 1.5rem;font-size:1.05rem;font-weight:700;color:#fff;">
              <i class="fa-solid fa-plus" style="color:#a855f7;margin-right:.5rem;"></i>Ajouter un module
          </h3>
          <div style="display:flex;flex-direction:column;gap:1rem;">
              <div>
                  <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Nom du module *</label>
                  <input type="text" id="add-nom-module" maxlength="255" placeholder="ex: Techniques de Réseaux"
                         style="width:100%;background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.5rem .85rem;font-size:.9rem;outline:none;box-sizing:border-box;">
              </div>
              <div>
                  <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Filière *</label>
                  <select id="add-filiere" style="width:100%;background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.5rem .85rem;font-size:.9rem;cursor:pointer;outline:none;box-sizing:border-box;">
                      <?php foreach ($toutesFilières as $filiere): ?>
                      <option value="<?= h((string)$filiere['id_filiere']) ?>" <?= $idFiliereSelecte === (int)$filiere['id_filiere'] ? 'selected' : '' ?>><?= h($filiere['nom_filiere']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                  <div>
                      <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Coefficient (1–7)</label>
                      <input type="number" id="add-coefficient" min="1" max="7" step="0.5" value="1"
                             class="mod-nb-input" style="width:100%;box-sizing:border-box;">
                  </div>
                  <div>
                      <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Semestre</label>
                      <select id="add-semestre" class="mod-nb-input" style="width:100%;padding:.4rem .5rem;cursor:pointer;box-sizing:border-box;">
                          <option value="1">1</option>
                          <option value="2">2</option>
                      </select>
                  </div>
                  <div>
                      <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Masse horaire (h)</label>
                      <input type="number" id="add-masse-horaire" min="0" max="9999" step="0.5" value="0"
                             class="mod-nb-input" style="width:100%;box-sizing:border-box;">
                  </div>
                  <div>
                      <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;display:block;margin-bottom:.35rem;">Nb. contrôles</label>
                      <input type="number" id="add-nb-controles" min="0" max="10" value="0"
                             class="mod-nb-input" style="width:100%;box-sizing:border-box;">
                  </div>
              </div>
              <span id="add-module-msg" class="mod-save-msg" style="min-height:1.2rem;"></span>
              <button class="btn-save-nb" onclick="submitAddModule()" style="width:100%;padding:.6rem;font-size:.95rem;">
                  <i class="fa-solid fa-floppy-disk" style="margin-right:.4rem;"></i>Créer le module
              </button>
          </div>
      </div>
  </div>

  <?php if ($infoModule): ?>
  <!-- ── Carte des paramètres éditables du module ── -->
  <div class="mod-card">
      <h2><i data-lucide="layers" width="18" height="18" style="vertical-align:-3px;margin-right:.4rem;stroke:#a855f7;"></i><?= h($infoModule['nom_module']) ?></h2>
      <div class="mod-meta-grid">
          <div class="mod-meta-item">
              <div class="label">Filière</div>
              <div class="value"><?= h($infoModule['nom_filiere']) ?></div>
          </div>
      </div>

      <!-- Formulaire inline : 4 champs éditables enregistrés en AJAX -->
      <div class="mod-nb-form" style="flex-wrap:wrap;gap:1.25rem;align-items:flex-end;">

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Nb. contrôles</label>
              <input type="number" id="nb-controles-input" class="mod-nb-input" min="0" max="10"
                     value="<?= h((string)$nbControles) ?>">
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Coefficient <span style="font-weight:400;opacity:.6;">(1–7)</span></label>
              <input type="number" id="mod-coefficient-input" class="mod-nb-input" min="1" max="7" step="0.5"
                     style="width:80px;" value="<?= h((string)($infoModule['coefficient'] ?? 1)) ?>">
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Semestre</label>
              <select id="mod-semestre-input" class="mod-nb-input" style="width:80px;padding:.35rem .5rem;cursor:pointer;">
                  <option value="1" <?= ($infoModule['semestre'] ?? 1) == 1 ? 'selected' : '' ?>>1</option>
                  <option value="2" <?= ($infoModule['semestre'] ?? 1) == 2 ? 'selected' : '' ?>>2</option>
              </select>
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Masse horaire <span style="font-weight:400;opacity:.6;">(h)</span></label>
              <input type="number" id="mod-masse-horaire-input" class="mod-nb-input" min="0" max="9999" step="0.5"
                     style="width:90px;" value="<?= h((string)($infoModule['masse_horaire'] ?? 0)) ?>">
          </div>

          <div style="display:flex;align-items:flex-end;gap:.65rem;">
              <button class="btn-save-nb" onclick="saveAllFields()" style="padding:.45rem 1.25rem;">
                  <i class="fa-solid fa-floppy-disk" style="margin-right:.35rem;"></i>Enregistrer
              </button>
              <span id="save-all-msg" class="mod-save-msg"></span>
          </div>

      </div>
  </div>

  <!-- ── Carte : tableau des stagiaires avec leurs notes ── -->
  <div class="mod-card">
      <h2 style="margin-bottom:1rem;">
          <i data-lucide="users" width="18" height="18" style="vertical-align:-3px;margin-right:.4rem;stroke:#a855f7;"></i>
          Stagiaires &mdash; <?= h($anneeScolaireGlobale !== '' ? $anneeScolaireGlobale : 'Toutes années') ?>
          <span style="font-size:.8rem;font-weight:500;color:#71717a;margin-left:.5rem;">(<?= count($listeEtudiants) ?> stagiaire<?= count($listeEtudiants) !== 1 ? 's' : '' ?>)</span>
      </h2>
      <?php if (!empty($listeEtudiants)): ?>
      <div class="stag-table-wrap">
          <table class="stag-table">
              <thead>
                  <tr>
                      <th>#</th>
                      <th>Nom &amp; Prénom</th>
                      <th>Classe</th>
                      <?php if ($nbControles > 0): ?>
                      <!-- id="ctrl-th" : cible du DOM update JS après changement de nb_controles -->
                      <th id="ctrl-th" title="Moyenne de <?= $nbControles ?> contrôle(s) saisis">
                          Moy. Contrôles
                          <span style="font-weight:400;opacity:.6;">(sur <span id="ctrl-col-count"><?= $nbControles ?></span>)</span>
                      </th>
                      <?php endif; ?>
                      <th>Théorique</th>
                      <th>Pratique</th>
                      <th></th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($listeEtudiants as $idx => $etudiant):
                      $moyControles  = $etudiant['moy_controles'];
                      $noteTheorique = $etudiant['theorique'];
                      $notePratique  = $etudiant['pratique'];
                      $nbSaisies     = $etudiant['nb_saisies'];
                      // Classe CSS du badge : extraite via classeNoteBadge() pour éviter 3× le ternaire
                      $cssCtrl  = classeNoteBadge($moyControles);
                      $cssTheo  = classeNoteBadge($noteTheorique);
                      $cssPrat  = classeNoteBadge($notePratique);
                  ?>
                  <tr>
                      <td style="color:#52525b;font-size:.78rem;"><?= $idx + 1 ?></td>
                      <td>
                          <div style="font-weight:600;color:#e4e4e7;"><?= h($etudiant['nom'] . ' ' . $etudiant['prenom']) ?></div>
                          <div style="font-size:.75rem;color:#71717a;"><?= h($etudiant['num_inscri'] ?? '') ?></div>
                      </td>
                      <td>
                          <span style="font-size:.8rem;background:rgba(168,85,247,.1);color:#d8b4fe;padding:.15rem .55rem;border-radius:6px;">
                              <?= h($etudiant['nom_classe']) ?>
                          </span>
                      </td>
                      <?php if ($nbControles > 0): ?>
                      <!-- data-ctrl-td : cible du JS pour afficher/masquer la colonne -->
                      <td data-ctrl-td>
                          <span class="badge-note <?= $cssCtrl ?>">
                              <?= $moyControles !== null ? h(number_format($moyControles, 2)) : '—' ?>
                          </span>
                          <?php if ($moyControles !== null && $nbSaisies < $nbControles): ?>
                          <!-- data-nb-saisies : JS met à jour la visibilité selon le nouveau nb_controles -->
                          <span data-nb-saisies="<?= $nbSaisies ?>"
                                style="font-size:.7rem;color:#f59e0b;margin-left:.35rem;"
                                title="<?= $nbSaisies ?>/<?= $nbControles ?> contrôles saisis">⚠</span>
                          <?php endif; ?>
                      </td>
                      <?php endif; ?>
                      <td><span class="badge-note <?= $cssTheo ?>"><?= $noteTheorique !== null ? h(number_format($noteTheorique, 2)) : '—' ?></span></td>
                      <td><span class="badge-note <?= $cssPrat ?>"><?= $notePratique !== null ? h(number_format($notePratique, 2)) : '—' ?></span></td>
                      <td>
                          <a href="stagiaires.php?id=<?= (int)$etudiant['id_stagiaire'] ?>" class="link-hub">
                              <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.75rem;margin-right:.3rem;"></i>Hub
                          </a>
                      </td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
          <i class="fa-solid fa-users-slash"></i>
          <p>Aucun stagiaire trouvé pour cette filière / cette année scolaire.</p>
          <?php if ($anneeScolaireGlobale === ''): ?>
          <p style="font-size:.82rem;color:#a855f7;">Sélectionnez une année scolaire via le sélecteur en bas de la barre latérale.</p>
          <?php endif; ?>
      </div>
      <?php endif; ?>
  </div>

  <?php elseif ($idFiliereSelecte > 0 && empty($tousModules)): ?>
  <div class="mod-card"><div class="empty-state"><i class="fa-solid fa-box-open"></i><p>Aucun module trouvé pour cette filière.</p></div></div>
  <?php else: ?>
  <div class="mod-card"><div class="empty-state"><i class="fa-solid fa-hand-pointer"></i><p>Sélectionnez une filière pour commencer.</p></div></div>
  <?php endif; ?>

  <script>
  (function () {

      /* ══════════════════════════════════════════════════════════════════════
         Toast de notification — affiché sans rechargement de page
         ══════════════════════════════════════════════════════════════════════ */
      function showToast(msg, type) {
          const couleurs = {
              success: { bg: 'rgba(16,185,129,.15)',  border: 'rgba(16,185,129,.35)',  text: '#6ee7b7' },
              error:   { bg: 'rgba(239,68,68,.15)',   border: 'rgba(239,68,68,.35)',   text: '#fca5a5' },
              info:    { bg: 'rgba(168,85,247,.15)',   border: 'rgba(168,85,247,.35)',  text: '#d8b4fe' },
          };
          const c = couleurs[type] ?? couleurs.info;

          // Supprimer un éventuel toast précédent
          document.getElementById('gm-toast')?.remove();

          const toast = document.createElement('div');
          toast.id = 'gm-toast';
          Object.assign(toast.style, {
              position:     'fixed',
              bottom:       '1.5rem',
              right:        '1.5rem',
              background:   c.bg,
              border:       '1px solid ' + c.border,
              color:        c.text,
              padding:      '.75rem 1.25rem',
              borderRadius: '10px',
              fontWeight:   '600',
              fontSize:     '.88rem',
              zIndex:       '9999',
              boxShadow:    '0 4px 20px rgba(0,0,0,.4)',
              transition:   'opacity .4s',
          });
          toast.textContent = msg;
          document.body.appendChild(toast);
          setTimeout(() => { toast.style.opacity = '0'; }, 3600);
          setTimeout(() => toast.remove(), 4000);
      }


      /* ══════════════════════════════════════════════════════════════════════
         Filtre filière / module — navigue vers l'URL mise à jour
         ══════════════════════════════════════════════════════════════════════ */
      window.applyFilter = function () {
          const idFiliere = document.getElementById('sel-filiere')?.value ?? '';
          const idModule  = document.getElementById('sel-module')?.value  ?? '';
          const url = new URL(window.location.href);
          url.searchParams.set('id_filiere', idFiliere);
          if (idModule) url.searchParams.set('id_module', idModule);
          else          url.searchParams.delete('id_module');
          window.location.href = url.toString();
      };


      /* ══════════════════════════════════════════════════════════════════════
         Enregistrement des champs du module — AJAX, DOM update sans reload
         ══════════════════════════════════════════════════════════════════════ */
      window.saveAllFields = function () {
          const idModuleSelecte = <?= (int)$idModuleSelecte ?>;
          const nbControles     = parseInt(document.getElementById('nb-controles-input')?.value  ?? '', 10);
          const coefficient     = parseFloat(document.getElementById('mod-coefficient-input')?.value ?? '');
          const semestre        = parseInt(document.getElementById('mod-semestre-input')?.value   ?? '', 10);
          const masseHoraire    = parseFloat(document.getElementById('mod-masse-horaire-input')?.value ?? '');
          const msgEl           = document.getElementById('save-all-msg');

          // Validation côté client (miroir de validerChampsModule en PHP)
          if (isNaN(nbControles)  || nbControles  < 0  || nbControles  > 10) { msgEl.textContent = 'Nb. contrôles invalide (0–10).'; msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(coefficient)  || coefficient  < 1  || coefficient  > 7)  { msgEl.textContent = 'Coefficient invalide (1–7).';    msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(semestre)     || semestre     < 1  || semestre     > 2)  { msgEl.textContent = 'Semestre invalide (1 ou 2).';    msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(masseHoraire) || masseHoraire < 0)                       { msgEl.textContent = 'Masse horaire invalide.';         msgEl.style.color = '#fca5a5'; return; }

          msgEl.textContent = 'Enregistrement…';
          msgEl.style.color = '#a1a1aa';

          const fd = new FormData();
          fd.append('save_all_fields', '1');
          fd.append('id_module',     idModuleSelecte);
          fd.append('nb_controles',  nbControles);
          fd.append('coefficient',   coefficient);
          fd.append('semestre',      semestre);
          fd.append('masse_horaire', masseHoraire);
          fd.append('csrf_token',    document.querySelector('meta[name="csrf-token"]').content);

          fetch('gestion_modules.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(d => {
                  msgEl.textContent = '';
                  if (!d.success) {
                      showToast(d.error, 'error');
                      return;
                  }

                  showToast(d.msg, 'success');

                  // ── Mise à jour du DOM sans rechargement ──────────────────────────
                  // Cas particulier : si nb_controles passe de 0 à >0, la colonne
                  // "Moy. Contrôles" n'existe pas encore dans le DOM — un rechargement
                  // minimal est inévitable pour afficher les données réelles des TD.
                  const ctrlTh = document.getElementById('ctrl-th');
                  if (!ctrlTh && nbControles > 0) {
                      setTimeout(() => location.reload(), 700);
                      return;
                  }

                  // Mettre à jour le compteur dans l'en-tête ("sur N")
                  const compteurSpan = document.getElementById('ctrl-col-count');
                  if (compteurSpan) compteurSpan.textContent = nbControles;

                  // Afficher ou masquer toute la colonne Moy. Contrôles
                  const afficherCol = nbControles > 0;
                  if (ctrlTh) ctrlTh.style.display = afficherCol ? '' : 'none';
                  document.querySelectorAll('[data-ctrl-td]').forEach(td => {
                      td.style.display = afficherCol ? '' : 'none';
                  });

                  // Mettre à jour les icônes ⚠ (visibles si saisies < nouveau nb_controles)
                  document.querySelectorAll('[data-nb-saisies]').forEach(icone => {
                      const nbSaisies = parseInt(icone.dataset.nbSaisies, 10);
                      icone.style.display = (afficherCol && nbSaisies < nbControles) ? '' : 'none';
                  });
              })
              .catch(() => {
                  msgEl.textContent = '';
                  showToast('Erreur réseau.', 'error');
              });
      };


      /* ══════════════════════════════════════════════════════════════════════
         Modal ajout de module
         ══════════════════════════════════════════════════════════════════════ */
      window.openAddModal = function () {
          const modal = document.getElementById('modal-add-module');
          modal.style.display = 'flex';
          document.getElementById('add-nom-module').focus();
      };

      window.closeAddModal = function () {
          document.getElementById('modal-add-module').style.display = 'none';
          document.getElementById('add-module-msg').textContent = '';
      };

      // Fermeture par Echap ou clic sur le fond
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAddModal(); });
      document.getElementById('modal-add-module').addEventListener('click', function (e) {
          if (e.target === this) closeAddModal();
      });


      /* ══════════════════════════════════════════════════════════════════════
         Soumission de la création d'un module
         La navigation vers le nouveau module (window.location.href) est
         intentionnelle : le serveur doit renvoyer la page avec le nouveau
         module déjà sélectionné.
         ══════════════════════════════════════════════════════════════════════ */
      window.submitAddModule = function () {
          const nomModule    = document.getElementById('add-nom-module').value.trim();
          const idFiliere    = parseInt(document.getElementById('add-filiere').value, 10);
          const coefficient  = parseFloat(document.getElementById('add-coefficient').value);
          const semestre     = parseInt(document.getElementById('add-semestre').value, 10);
          const masseHoraire = parseFloat(document.getElementById('add-masse-horaire').value);
          const nbControles  = parseInt(document.getElementById('add-nb-controles').value, 10);
          const msgEl        = document.getElementById('add-module-msg');

          // Validation côté client
          if (!nomModule)                                                        { msgEl.textContent = 'Le nom du module est requis.';   msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(idFiliere)    || idFiliere    <= 0)                          { msgEl.textContent = 'Sélectionnez une filière.';       msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(coefficient)  || coefficient  < 1 || coefficient  > 7)      { msgEl.textContent = 'Coefficient invalide (1–7).';     msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(semestre)     || semestre     < 1 || semestre     > 2)      { msgEl.textContent = 'Semestre invalide (1 ou 2).';     msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(masseHoraire) || masseHoraire < 0)                          { msgEl.textContent = 'Masse horaire invalide.';          msgEl.style.color = '#fca5a5'; return; }
          if (isNaN(nbControles)  || nbControles  < 0 || nbControles  > 10)    { msgEl.textContent = 'Nb. contrôles invalide (0–10).';  msgEl.style.color = '#fca5a5'; return; }

          msgEl.textContent = 'Création en cours…';
          msgEl.style.color = '#a1a1aa';

          const fd = new FormData();
          fd.append('add_module',    '1');
          fd.append('nom_module',    nomModule);
          fd.append('id_filiere',    idFiliere);
          fd.append('coefficient',   coefficient);
          fd.append('semestre',      semestre);
          fd.append('masse_horaire', masseHoraire);
          fd.append('nb_controles',  nbControles);
          fd.append('csrf_token',    document.querySelector('meta[name="csrf-token"]').content);

          fetch('gestion_modules.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(d => {
                  if (d.success) {
                      msgEl.textContent = '✓ ' + d.msg;
                      msgEl.style.color = '#6ee7b7';
                      // Navigation vers le nouveau module (rechargement intentionnel)
                      const url = new URL(window.location.href);
                      url.searchParams.set('id_filiere', d.id_filiere);
                      url.searchParams.set('id_module',  d.id_module);
                      setTimeout(() => { window.location.href = url.toString(); }, 600);
                  } else {
                      msgEl.textContent = '✗ ' + d.error;
                      msgEl.style.color = '#fca5a5';
                  }
              })
              .catch(() => {
                  msgEl.textContent = '✗ Erreur réseau.';
                  msgEl.style.color = '#fca5a5';
              });
      };

  }());
  </script>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  