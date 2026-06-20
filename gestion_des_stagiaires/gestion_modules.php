<?php
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  $pageTitle = 'Gestion des Modules';
  $curPage   = 'modules';

  // ── POST: save module fields ──────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
      header('Content-Type: application/json');

      if (isset($_POST['save_nb_controles'])) {
          $idModule    = (int)($_POST['id_module']    ?? 0);
          $nbControles = (int)($_POST['nb_controles'] ?? 0);
          if ($idModule <= 0 || $nbControles < 0 || $nbControles > 10) {
              echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
          }
          try {
              $pdo->prepare("UPDATE modules SET nb_controles=? WHERE id_module=?")
                  ->execute([$nbControles, $idModule]);
              echo json_encode(['success' => true, 'msg' => 'Nombre de contrôles mis à jour.', 'nb_controles' => $nbControles]);
          } catch (\Throwable $e) {
              echo json_encode(['success' => false, 'error' => 'Erreur : ' . $e->getMessage()]);
          }
          exit;
      }

      if (isset($_POST['save_module_fields'])) {
          $idModule      = (int)($_POST['id_module']      ?? 0);
          $coefficient   = (float)str_replace(',', '.', (string)($_POST['coefficient']   ?? 0));
          $semestre      = (int)($_POST['semestre']       ?? 0);
          $masseHoraire  = (float)str_replace(',', '.', (string)($_POST['masse_horaire'] ?? 0));
          if ($idModule <= 0
              || $coefficient < 1 || $coefficient > 7
              || $semestre < 1   || $semestre > 2
              || $masseHoraire < 0 || $masseHoraire > 9999) {
              echo json_encode(['success' => false, 'error' => 'Données invalides. Coeff. 1–7, semestre 1–2.']); exit;
          }
          try {
              $pdo->prepare("UPDATE modules SET coefficient=?, semestre=?, masse_horaire=? WHERE id_module=?")
                  ->execute([$coefficient, $semestre, $masseHoraire, $idModule]);
              echo json_encode(['success' => true, 'msg' => 'Module mis à jour.']);
          } catch (\Throwable $e) {
              echo json_encode(['success' => false, 'error' => 'Erreur : ' . $e->getMessage()]);
          }
          exit;
      }

      echo json_encode(['success' => false, 'error' => 'Action inconnue.']); exit;
  }

  // ── FILTER PARAMS ─────────────────────────────────────────────────────────
  $selFiliere = (int)($_GET['id_filiere'] ?? 0);
  $selModule  = (int)($_GET['id_module']  ?? 0);

  // ── CASCADE DATA ──────────────────────────────────────────────────────────
  $allFilieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll();
  if ($selFiliere === 0 && !empty($allFilieres)) { $selFiliere = (int)$allFilieres[0]['id_filiere']; }

  $allModules = [];
  if ($selFiliere > 0) {
      $st = $pdo->prepare("SELECT id_module, nom_module, semestre FROM modules WHERE id_filiere=? ORDER BY semestre, nom_module");
      $st->execute([$selFiliere]);
      $allModules = $st->fetchAll();
      if ($selModule === 0 && !empty($allModules)) { $selModule = (int)$allModules[0]['id_module']; }
  }

  // ── MODULE DETAILS ────────────────────────────────────────────────────────
  $moduleInfo = null;
  if ($selModule > 0) {
      $st = $pdo->prepare("SELECT m.*, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere=m.id_filiere WHERE m.id_module=?");
      $st->execute([$selModule]);
      $moduleInfo = $st->fetch();
  }

  // ── STUDENT LIST WITH GRADES ──────────────────────────────────────────────
  // Shows Moy. Contrôles (average of controle_1..N), Théorique, Pratique per student.
  $stagiaires  = [];
  $nbControles = $moduleInfo ? (int)($moduleInfo['nb_controles'] ?? 0) : 0;
  $globalAnnee = $_SESSION['global_annee_scolaire'] ?? '';

  if ($selModule > 0 && $selFiliere > 0 && $globalAnnee !== '') {
      $st = $pdo->prepare("
          SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, c.nom_classe, c.niveau
          FROM stagiaires s
          JOIN classes c ON c.id_classe = s.id_classe
          WHERE c.id_filiere = ? AND c.annee_scolaire = ?
          ORDER BY c.nom_classe, s.nom, s.prenom
      ");
      $st->execute([$selFiliere, $globalAnnee]);
      $students = $st->fetchAll();

      if (!empty($students)) {
          $sids = array_column($students, 'id_stagiaire');
          $ph   = implode(',', array_fill(0, count($sids), '?'));
          $stN  = $pdo->prepare("SELECT id_stagiaire, type, note FROM module_notes WHERE id_module=? AND id_stagiaire IN ($ph)");
          $stN->execute(array_merge([$selModule], $sids));
          $notesMap = [];
          foreach ($stN->fetchAll() as $n) {
              if ($n['note'] !== null) {
                  $notesMap[(int)$n['id_stagiaire']][$n['type']] = (float)$n['note'];
              }
          }
          foreach ($students as $s) {
              $sid      = (int)$s['id_stagiaire'];
              $allN     = $notesMap[$sid] ?? [];
              // Average of controle_* keys only
              $controls = array_filter($allN, fn($k) => str_starts_with($k, 'controle_'), ARRAY_FILTER_USE_KEY);
              $s['moy_controles'] = count($controls) > 0
                  ? round(array_sum($controls) / count($controls), 2)
                  : null;
              $s['nb_saisies']    = count($controls);
              $s['theorique']     = $allN['theorique'] ?? null;
              $s['pratique']      = $allN['pratique']  ?? null;
              $stagiaires[] = $s;
          }
      }
  }

  require __DIR__ . '/includes/header.php';
  ?>
  <style>
  .mod-filter-bar{display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.5rem}
  .mod-filter-bar label{font-size:.78rem;color:#a1a1aa;text-transform:uppercase;letter-spacing:.08em;font-weight:600;display:block;margin-bottom:.35rem}
  .mod-filter-bar select{background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.45rem .85rem;font-size:.88rem;min-width:190px;outline:none;cursor:pointer}
  .mod-filter-bar select:focus{border-color:#a855f7}
  .mod-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem}
  .mod-card h2{font-size:1.15rem;font-weight:700;color:#fff;margin:0 0 1rem}
  .mod-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.25rem}
  .mod-meta-item{background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.15);border-radius:10px;padding:.75rem 1rem}
  .mod-meta-item .label{font-size:.7rem;color:rgba(168,85,247,.7);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:.25rem}
  .mod-meta-item .value{font-size:1rem;font-weight:700;color:#e4e4e7}
  .mod-nb-form{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
  .mod-nb-form label{font-size:.82rem;color:#a1a1aa;font-weight:600}
  .mod-nb-input{background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.35);border-radius:8px;padding:.4rem .75rem;font-size:.9rem;width:80px;text-align:center;outline:none}
  .mod-nb-input:focus{border-color:#a855f7}
  .btn-save-nb{background:#a855f7;color:#fff;border:none;border-radius:8px;padding:.45rem 1.1rem;font-size:.85rem;font-weight:700;cursor:pointer;transition:background .2s}
  .btn-save-nb:hover{background:#9333ea}
  .mod-save-msg{font-size:.82rem;font-weight:600;margin-left:.5rem}
  .stag-table-wrap{overflow-x:auto}
  .stag-table{width:100%;border-collapse:collapse;font-size:.85rem}
  .stag-table th{background:rgba(168,85,247,.12);color:#d8b4fe;font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;padding:.65rem 1rem;text-align:left;white-space:nowrap}
  .stag-table td{padding:.6rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);color:#e4e4e7;vertical-align:middle;white-space:nowrap}
  .stag-table tr:hover td{background:rgba(168,85,247,.04)}
  .badge-note{display:inline-block;padding:.15rem .55rem;border-radius:6px;font-weight:700;font-size:.8rem}
  .badge-note.good{background:rgba(16,185,129,.15);color:#6ee7b7}
  .badge-note.avg {background:rgba(245,158,11,.15);color:#fcd34d}
  .badge-note.fail{background:rgba(239,68,68,.15);color:#fca5a5}
  .badge-note.none{background:rgba(255,255,255,.05);color:#71717a}
  .link-hub{color:#a855f7;text-decoration:none;font-size:.8rem;font-weight:600}
  .link-hub:hover{color:#d8b4fe;text-decoration:underline}
  .empty-state{text-align:center;padding:3rem 1rem;color:#52525b}
  .empty-state i{font-size:2rem;margin-bottom:.75rem;display:block}
  </style>

  <div class="mod-filter-bar">
      <div>
          <label for="sel-filiere">Filière</label>
          <select id="sel-filiere" onchange="applyFilter()">
              <?php foreach ($allFilieres as $f): ?>
              <option value="<?= h((string)$f['id_filiere']) ?>" <?= $selFiliere === (int)$f['id_filiere'] ? 'selected' : '' ?>><?= h($f['nom_filiere']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>
      <?php if (!empty($allModules)): ?>
      <div>
          <label for="sel-module">Module</label>
          <select id="sel-module" onchange="applyFilter()">
              <?php
              $curSem = null;
              foreach ($allModules as $m):
                  if ($m['semestre'] !== $curSem) {
                      if ($curSem !== null) echo '</optgroup>';
                      echo '<optgroup label="' . h('Semestre ' . $m['semestre']) . '">';
                      $curSem = $m['semestre'];
                  }
              ?>
              <option value="<?= h((string)$m['id_module']) ?>" <?= $selModule === (int)$m['id_module'] ? 'selected' : '' ?>><?= h($m['nom_module']) ?></option>
              <?php endforeach; if ($curSem !== null) echo '</optgroup>'; ?>
          </select>
      </div>
      <?php endif; ?>
  </div>

  <?php if ($moduleInfo): ?>
  <div class="mod-card">
      <h2><i data-lucide="layers" width="18" height="18" style="vertical-align:-3px;margin-right:.4rem;stroke:#a855f7;"></i><?= h($moduleInfo['nom_module']) ?></h2>
      <div class="mod-meta-grid">
          <div class="mod-meta-item"><div class="label">Filière</div><div class="value"><?= h($moduleInfo['nom_filiere']) ?></div></div>
      </div>

      <div class="mod-nb-form" style="flex-wrap:wrap;gap:1.25rem;align-items:flex-end;">

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Nb. contrôles</label>
              <div style="display:flex;align-items:center;gap:.5rem;">
                  <input type="number" id="nb-controles-input" class="mod-nb-input" min="0" max="10"
                         value="<?= h((string)$nbControles) ?>">
                  <button class="btn-save-nb" onclick="saveNbControles()">
                      <i class="fa-solid fa-floppy-disk" style="margin-right:.3rem;"></i>Sauv.
                  </button>
                  <span id="save-nb-msg" class="mod-save-msg"></span>
              </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Coefficient <span style="font-weight:400;opacity:.6;">(1–7)</span></label>
              <div style="display:flex;align-items:center;gap:.5rem;">
                  <input type="number" id="mod-coefficient-input" class="mod-nb-input" min="1" max="7" step="0.5"
                         style="width:80px;" value="<?= h((string)($moduleInfo['coefficient'] ?? 1)) ?>">
              </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Semestre</label>
              <div style="display:flex;align-items:center;gap:.5rem;">
                  <select id="mod-semestre-input" class="mod-nb-input" style="width:80px;padding:.35rem .5rem;cursor:pointer;">
                      <option value="1" <?= ($moduleInfo['semestre'] ?? 1) == 1 ? 'selected' : '' ?>>1</option>
                      <option value="2" <?= ($moduleInfo['semestre'] ?? 1) == 2 ? 'selected' : '' ?>>2</option>
                  </select>
              </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:.35rem;">
              <label style="font-size:.78rem;color:#a1a1aa;font-weight:600;">Masse horaire <span style="font-weight:400;opacity:.6;">(h)</span></label>
              <div style="display:flex;align-items:center;gap:.5rem;">
                  <input type="number" id="mod-masse-horaire-input" class="mod-nb-input" min="0" max="9999" step="0.5"
                         style="width:90px;" value="<?= h((string)($moduleInfo['masse_horaire'] ?? 0)) ?>">
              </div>
          </div>

          <div>
              <button class="btn-save-nb" onclick="saveModuleFields()" style="padding:.45rem 1.25rem;">
                  <i class="fa-solid fa-floppy-disk" style="margin-right:.35rem;"></i>Enregistrer
              </button>
              <span id="save-fields-msg" class="mod-save-msg"></span>
          </div>

      </div>
  </div>

  <div class="mod-card">
      <h2 style="margin-bottom:1rem;">
          <i data-lucide="users" width="18" height="18" style="vertical-align:-3px;margin-right:.4rem;stroke:#a855f7;"></i>
          Stagiaires &mdash; <?= h($globalAnnee !== '' ? $globalAnnee : 'Toutes années') ?>
          <span style="font-size:.8rem;font-weight:500;color:#71717a;margin-left:.5rem;">(<?= count($stagiaires) ?> stagiaire<?= count($stagiaires) !== 1 ? 's' : '' ?>)</span>
      </h2>
      <?php if (!empty($stagiaires)): ?>
      <div class="stag-table-wrap">
          <table class="stag-table">
              <thead>
                  <tr>
                      <th>#</th>
                      <th>Nom &amp; Prénom</th>
                      <th>Classe</th>
                      <?php if ($nbControles > 0): ?>
                      <th title="Moyenne de <?= $nbControles ?> contrôle(s) saisis">Moy. Contrôles<?php if ($nbControles > 0): ?> <span style="font-weight:400;opacity:.6;">(sur <?= $nbControles ?>)</span><?php endif; ?></th>
                      <?php endif; ?>
                      <th>Théorique</th>
                      <th>Pratique</th>
                      <th></th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($stagiaires as $i => $s): ?>
                  <?php
                      $mc   = $s['moy_controles'];
                      $th   = $s['theorique'];
                      $pr   = $s['pratique'];
                      $mcCl = $mc === null ? 'none' : ($mc >= 10 ? 'good' : ($mc >= 7 ? 'avg' : 'fail'));
                      $thCl = $th === null ? 'none' : ($th >= 10 ? 'good' : ($th >= 7 ? 'avg' : 'fail'));
                      $prCl = $pr === null ? 'none' : ($pr >= 10 ? 'good' : ($pr >= 7 ? 'avg' : 'fail'));
                      $nb   = $s['nb_saisies'];
                  ?>
                  <tr>
                      <td style="color:#52525b;font-size:.78rem;"><?= $i + 1 ?></td>
                      <td>
                          <div style="font-weight:600;color:#e4e4e7;"><?= h($s['nom'] . ' ' . $s['prenom']) ?></div>
                          <div style="font-size:.75rem;color:#71717a;"><?= h($s['num_inscri'] ?? '') ?></div>
                      </td>
                      <td><span style="font-size:.8rem;background:rgba(168,85,247,.1);color:#d8b4fe;padding:.15rem .55rem;border-radius:6px;"><?= h($s['nom_classe']) ?></span></td>
                      <?php if ($nbControles > 0): ?>
                      <td>
                          <span class="badge-note <?= $mcCl ?>">
                              <?= $mc !== null ? h(number_format($mc, 2)) : '—' ?>
                          </span>
                          <?php if ($mc !== null && $nb < $nbControles): ?>
                          <span style="font-size:.7rem;color:#f59e0b;margin-left:.35rem;" title="<?= $nb ?>/<?= $nbControles ?> contrôles saisis">⚠</span>
                          <?php endif; ?>
                      </td>
                      <?php endif; ?>
                      <td><span class="badge-note <?= $thCl ?>"><?= $th !== null ? h(number_format($th, 2)) : '—' ?></span></td>
                      <td><span class="badge-note <?= $prCl ?>"><?= $pr !== null ? h(number_format($pr, 2)) : '—' ?></span></td>
                      <td><a href="stagiaires.php?id=<?= (int)$s['id_stagiaire'] ?>" class="link-hub"><i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.75rem;margin-right:.3rem;"></i>Hub</a></td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
          <i class="fa-solid fa-users-slash"></i>
          <p>Aucun stagiaire trouvé pour cette filière / cette année scolaire.</p>
          <?php if ($globalAnnee === ''): ?>
          <p style="font-size:.82rem;color:#a855f7;">Sélectionnez une année scolaire via le sélecteur en bas de la barre latérale.</p>
          <?php endif; ?>
      </div>
      <?php endif; ?>
  </div>
  <?php elseif ($selFiliere > 0 && empty($allModules)): ?>
  <div class="mod-card"><div class="empty-state"><i class="fa-solid fa-box-open"></i><p>Aucun module trouvé pour cette filière.</p></div></div>
  <?php else: ?>
  <div class="mod-card"><div class="empty-state"><i class="fa-solid fa-hand-pointer"></i><p>Sélectionnez une filière pour commencer.</p></div></div>
  <?php endif; ?>

  <script>
  function applyFilter() {
      const f = document.getElementById('sel-filiere')?.value ?? '';
      const m = document.getElementById('sel-module')?.value  ?? '';
      const url = new URL(window.location.href);
      url.searchParams.set('id_filiere', f);
      if (m) url.searchParams.set('id_module', m);
      else url.searchParams.delete('id_module');
      window.location.href = url.toString();
  }

  function saveModuleFields() {
      const idModule     = <?= (int)$selModule ?>;
      const coefficient  = parseFloat(document.getElementById('mod-coefficient-input')?.value ?? '');
      const semestre     = parseInt(document.getElementById('mod-semestre-input')?.value ?? '', 10);
      const masseHoraire = parseFloat(document.getElementById('mod-masse-horaire-input')?.value ?? '');
      const msgEl        = document.getElementById('save-fields-msg');
      if (isNaN(coefficient) || coefficient < 1 || coefficient > 7) {
          msgEl.textContent = 'Coefficient invalide (1–7).';
          msgEl.style.color = '#fca5a5';
          return;
      }
      if (isNaN(semestre) || semestre < 1 || semestre > 2) {
          msgEl.textContent = 'Semestre invalide (1 ou 2).';
          msgEl.style.color = '#fca5a5';
          return;
      }
      if (isNaN(masseHoraire) || masseHoraire < 0) {
          msgEl.textContent = 'Masse horaire invalide.';
          msgEl.style.color = '#fca5a5';
          return;
      }
      msgEl.textContent = 'Enregistrement…';
      msgEl.style.color = '#a1a1aa';

      const fd = new FormData();
      fd.append('save_module_fields', '1');
      fd.append('id_module',     idModule);
      fd.append('coefficient',   coefficient);
      fd.append('semestre',      semestre);
      fd.append('masse_horaire', masseHoraire);
      fd.append('csrf_token',    document.querySelector('meta[name="csrf-token"]').content);

      fetch('gestion_modules.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(d => {
              if (d.success) {
                  msgEl.textContent = '✓ ' + d.msg;
                  msgEl.style.color = '#6ee7b7';
                  setTimeout(() => location.reload(), 800);
              } else {
                  msgEl.textContent = '✗ ' + d.error;
                  msgEl.style.color = '#fca5a5';
              }
              setTimeout(() => { msgEl.textContent = ''; }, 4000);
          })
          .catch(() => {
              msgEl.textContent = '✗ Erreur réseau.';
              msgEl.style.color = '#fca5a5';
          });
  }

  function saveNbControles() {
      const idModule    = <?= (int)$selModule ?>;
      const nbControles = parseInt(document.getElementById('nb-controles-input').value, 10);
      const msgEl       = document.getElementById('save-nb-msg');
      if (isNaN(nbControles) || nbControles < 0 || nbControles > 10) {
          msgEl.textContent = 'Valeur invalide (0–10).';
          msgEl.style.color = '#fca5a5';
          return;
      }
      msgEl.textContent = 'Enregistrement…';
      msgEl.style.color = '#a1a1aa';

      const fd = new FormData();
      fd.append('save_nb_controles', '1');
      fd.append('id_module',    idModule);
      fd.append('nb_controles', nbControles);
      fd.append('csrf_token',   document.querySelector('meta[name="csrf-token"]').content);

      fetch('gestion_modules.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(d => {
              if (d.success) {
                  msgEl.textContent = '✓ ' + d.msg;
                  msgEl.style.color = '#6ee7b7';
                  setTimeout(() => location.reload(), 800);
              } else {
                  msgEl.textContent = '✗ ' + d.error;
                  msgEl.style.color = '#fca5a5';
              }
              setTimeout(() => { msgEl.textContent = ''; }, 4000);
          })
          .catch(() => {
              msgEl.textContent = '✗ Erreur réseau.';
              msgEl.style.color = '#fca5a5';
          });
  }
  </script>
  <?php require __DIR__ . '/includes/footer.php'; ?>
  