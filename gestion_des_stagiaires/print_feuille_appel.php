<?php
  /**
   * print_feuille_appel.php — Feuille d'appel imprimable (A4)
   *
   * Génère une feuille d'appel officielle pour une classe, un module et une
   * date donnés. Le professeur coche manuellement la colonne [ ] présence/absence.
   *
   * Paramètres GET (cascade) :
   *   annee      — année scolaire (ex: 2024/2025)
   *   id_filiere — ID filière
   *   niveau     — niveau (ex: 1ère année)
   *   id_classe  — ID classe
   *   id_module  — ID module (facultatif)
   *   date_appel — date de l'appel (YYYY-MM-DD, défaut = aujourd'hui)
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  // ── Constantes établissement ─────────────────────────────────────────────
  $SCHOOL_ORG         = 'GROUPE IPIRNET';
  $SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
  $SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
  $SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
  $SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
  $SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
  $SCHOOL_LEGAL       = 'Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293';

  // ── Paramètres de filtre ─────────────────────────────────────────────────
  $anneeSelectionnee = trim((string)($_GET['annee']      ?? ''));
  $idFiliereSelecte  = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte   = (int)($_GET['id_classe']  ?? 0);
  $idModuleSelecte   = (int)($_GET['id_module']  ?? 0);
  $dateAppel  = trim((string)($_GET['date_appel']  ?? date('Y-m-d')));
  $heureDebut = trim((string)($_GET['heure_debut'] ?? ''));
  $heureFin   = trim((string)($_GET['heure_fin']   ?? ''));

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAppel)) $dateAppel = date('Y-m-d');
  $dateAppelFr = date('d/m/Y', strtotime($dateAppel));

  // Sanitise — keep only valid HH:MM values
  if (!preg_match('/^\d{2}:\d{2}$/', $heureDebut)) $heureDebut = '';
  if (!preg_match('/^\d{2}:\d{2}$/', $heureFin))   $heureFin   = '';

  // Display label shown in the session info block on the printed page
  $horaireAffiche = ($heureDebut !== '' && $heureFin !== '')
      ? $heureDebut . ' – ' . $heureFin
      : '';

  // ── Données en cascade ───────────────────────────────────────────────────
  $toutesLesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes
        WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
        ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);

  if ($anneeSelectionnee === '') $anneeSelectionnee = $_SESSION['global_annee_scolaire'] ?? ($toutesLesAnnees[0] ?? '');

  $toutesLesFilieres = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f INNER JOIN classes c ON c.id_filiere = f.id_filiere
         ORDER BY f.nom_filiere"
  )->fetchAll();

  if ($idFiliereSelecte === 0 && !empty($toutesLesFilieres)) {
      $idFiliereSelecte = (int)$toutesLesFilieres[0]['id_filiere'];
  }

  $tousLesNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $rq = $pdo->prepare("SELECT DISTINCT niveau FROM classes WHERE id_filiere=? AND annee_scolaire=? ORDER BY niveau");
      $rq->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousLesNiveaux = $rq->fetchAll(PDO::FETCH_COLUMN);
      if (!empty($tousLesNiveaux) && !in_array($niveauSelectionne, $tousLesNiveaux, true)) {
          $niveauSelectionne = $tousLesNiveaux[0];
      }
  }

  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $niveauSelectionne !== '') {
      $rq = $pdo->prepare("SELECT id_classe, nom_classe FROM classes WHERE id_filiere=? AND annee_scolaire=? AND niveau=? ORDER BY nom_classe");
      $rq->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $rq->fetchAll();
      $idsValides = array_map('intval', array_column($toutesLesClasses, 'id_classe'));
      if (!empty($toutesLesClasses) && !in_array($idClasseSelecte, $idsValides, true)) {
          $idClasseSelecte = (int)$toutesLesClasses[0]['id_classe'];
      }
  }

  $tousLesModules = [];
  if ($idFiliereSelecte > 0) {
      $rq = $pdo->prepare("SELECT id_module, nom_module FROM modules WHERE id_filiere=? ORDER BY nom_module");
      $rq->execute([$idFiliereSelecte]);
      $tousLesModules = $rq->fetchAll();
  }

  // ── Stagiaires + infos classe ────────────────────────────────────────────
  $stagiaires = [];
  $infoClasse = null;
  $infoModule = null;

  if ($idClasseSelecte > 0) {
      $rq = $pdo->prepare(
          "SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau
             FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere
            WHERE c.id_classe=?"
      );
      $rq->execute([$idClasseSelecte]);
      $infoClasse = $rq->fetch();

      $rq = $pdo->prepare(
          "SELECT id_stagiaire, nom, prenom, num_inscri
             FROM stagiaires
            WHERE id_classe=?
            ORDER BY nom, prenom"
      );
      $rq->execute([$idClasseSelecte]);
      $stagiaires = $rq->fetchAll();
  }

  if ($idModuleSelecte > 0) {
      $rq = $pdo->prepare("SELECT nom_module FROM modules WHERE id_module=?");
      $rq->execute([$idModuleSelecte]);
      $infoModule = $rq->fetchColumn();
  }

  $peutImprimer = ($idClasseSelecte > 0 && !empty($stagiaires));
  ?><!DOCTYPE html>
  <html lang="fr">
  <head>
      <meta charset="utf-8">
      <title>Feuille d'appel<?= $infoClasse ? ' — ' . h((string)$infoClasse['nom_classe']) : '' ?></title>
      <style>
          @page { size: A4; margin: 10mm 12mm; }
          * { box-sizing: border-box; }
          html, body { background: #f1f3f5; }
          body {
              margin: 0;
              padding: 18px 0 40px;
              font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
              color: #111;
              font-size: 11pt;
          }

          /* ── Barre de filtres (non imprimée) ──────────────────── */
          .fa-filter-bar {
              max-width: 880px; margin: 0 auto 16px;
              background: #1c1c1f; border: 1px solid rgba(255,255,255,.08);
              border-radius: 14px; padding: 1.1rem 1.5rem;
          }
          .fa-filter-bar form { background: transparent; }
          .fa-filter-grid {
              display: grid;
              grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
              gap: .75rem; align-items: end;
          }
          .fa-filter-grid label {
              display: flex; flex-direction: column; gap: .3rem;
              font-size: .75rem; font-weight: 600; color: #a1a1aa;
              text-transform: uppercase; letter-spacing: .05em;
          }
          .fa-filter-grid select,
          .fa-filter-grid input[type="date"] {
              background: #09090b; border: 1px solid rgba(255,255,255,.12);
              color: #e4e4e7; border-radius: 7px; padding: .45rem .7rem;
              font-size: .88rem; width: 100%; color-scheme: dark;
          }
          .fa-filter-grid select:disabled { opacity: .4; cursor: not-allowed; }
          .fa-filter-grid select:focus, .fa-filter-grid input:focus {
              outline: none; border-color: rgba(168,85,247,.5);
              box-shadow: 0 0 0 3px rgba(168,85,247,.12);
          }
          .fa-btn-row { display: flex; gap: .6rem; margin-top: .75rem; align-items: center; }
          .fa-btn {
              display: inline-flex; align-items: center; gap: 5px;
              padding: .5rem 1.1rem; border-radius: 8px; font-size: .83rem;
              font-weight: 600; border: none; cursor: pointer; transition: all .18s;
          }
          .fa-btn.primary {
              background: linear-gradient(135deg,#9333ea,#a855f7);
              color: #fff; box-shadow: 0 4px 14px rgba(168,85,247,.35);
          }
          .fa-btn.primary:hover { box-shadow: 0 6px 20px rgba(168,85,247,.5); transform: translateY(-1px); }
          .fa-btn.ghost {
              background: rgba(168,85,247,.12); color: #c084fc;
              border: 1px solid rgba(168,85,247,.3);
          }
          .fa-btn.ghost:hover { background: rgba(168,85,247,.22); }

          /* ── Document A4 ──────────────────────────────────────── */
          .cs-doc {
              max-width: 880px; margin: 0 auto; background: #fff;
              padding: 28px 34px 24px;
              box-shadow: 0 4px 14px rgba(0,0,0,.08);
              border: 1px solid #cdd0d4;
          }

          /* ── En-tête 3 colonnes (identique aux autres documents) ── */
          .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
          .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
          .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
          .cs-head-logo { max-width: 88px; max-height: 88px; display: inline-block; }
          .cs-stamp {
              display: inline-flex; align-items: center; justify-content: center;
              width: 88px; height: 88px; border-radius: 50%;
              border: 2px solid #b8860b; color: #b8860b;
              font-family: "Times New Roman", serif; font-weight: 700;
              font-size: .95rem; letter-spacing: .05em;
              background: radial-gradient(circle, #fff 55%, transparent 56%),
                          repeating-conic-gradient(#b8860b 0 6deg, transparent 6deg 12deg);
              padding: 4px;
          }
          .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.55rem; letter-spacing: .03em; }
          .cs-head-mid .cs-tag  { font-style: italic; font-size: .93rem; margin-top: 2px; }
          .cs-head-mid .cs-auth { font-size: .78rem; margin-top: 4px; }

          /* ── Titre ───────────────────────────────────────────── */
          .cs-title-wrap { display: flex; justify-content: center; margin: 20px 0 14px; }
          .cs-title-oval {
              border: 1.5px solid #1a1a1a; border-radius: 50%;
              padding: 14px 60px; min-width: 50%; text-align: center;
              font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
              font-style: italic; font-size: 1.6rem; color: #0b3b66;
              white-space: nowrap; letter-spacing: .02em;
          }

          /* ── Infos de la séance ──────────────────────────────── */
          .fa-seance {
              display: flex; justify-content: space-between; align-items: flex-start;
              margin: 0 4px 16px; gap: 1rem; flex-wrap: wrap;
              border-bottom: 1.5px solid #333; padding-bottom: 8px;
          }
          .fa-seance-bloc { font-size: 10.5pt; line-height: 1.7; }
          .fa-seance-bloc .label { font-weight: 700; }

          /* ── Tableau d'appel ─────────────────────────────────── */
          .fa-table {
              width: 100%; border-collapse: collapse;
              margin-top: 8px; font-size: 10.5pt;
          }
          .fa-table th {
              background: #e8e8e8; border: 1px solid #000;
              padding: 6px 8px; text-align: center; font-weight: bold; font-size: 10pt;
              letter-spacing: .04em;
              -webkit-print-color-adjust: exact; print-color-adjust: exact;
          }
          .fa-table td {
              border: 1px solid #000; padding: 5px 8px;
              vertical-align: middle; line-height: 1.4;
          }
          .fa-table .col-n     { width: 6%;  text-align: center; }
          .fa-table .col-nom   { width: 60%; font-weight: 600; }
          .fa-table .col-heure { width: 16%; text-align: center; font-size: 9.5pt; }
          .fa-table .col-abs   { width: 17%; text-align: center; }
          .fa-table .col-pre   { width: 17%; text-align: center; }
          /* Hauteur minimale pour que le prof puisse écrire */
          .fa-table tbody td { height: 22px; }

          /* ── Zone de signature ───────────────────────────────── */
          .cs-sign { margin: 22px 4px 16px; width: calc(100% - 8px); border-collapse: collapse; }
          .cs-sign th, .cs-sign td { border: 1px solid #111; padding: 8px 12px; vertical-align: top; }
          .cs-sign th {
              font-weight: 400; font-style: italic;
              font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
              font-size: 1.1rem; color: #0b3b66; text-align: center; background: #fafafa;
          }
          .cs-sign td { height: 55px; }

          /* ── Pied de page ────────────────────────────────────── */
          .cs-footer {
              border-top: 1px solid #111; padding-top: 5px;
              margin: 14px 4px 0; text-align: center;
              font-size: .78rem; line-height: 1.45; color: #444;
          }

          @media print {
              html, body { background: #fff; padding: 0; }
              .fa-filter-bar, .no-print { display: none !important; }
              .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
          }
      </style>
  </head>
  <body>

  <!-- ── Barre de filtres (non imprimée) ────────────────────────────────── -->
  <div class="fa-filter-bar no-print">
      <form method="get" action="print_feuille_appel.php" id="fa-form">
          <div class="fa-filter-grid">

              <label>Année scolaire
                  <select name="annee" onchange="this.form.submit()">
                      <?php foreach ($toutesLesAnnees as $an): ?>
                      <option value="<?= h($an) ?>" <?= $anneeSelectionnee===$an?'selected':'' ?>><?= h($an) ?></option>
                      <?php endforeach; ?>
                  </select>
              </label>

              <label>Filière
                  <select name="id_filiere" onchange="this.form.submit()" <?= $anneeSelectionnee===''?'disabled':'' ?>>
                      <option value="0">— Choisir —</option>
                      <?php foreach ($toutesLesFilieres as $f): ?>
                      <option value="<?= (int)$f['id_filiere'] ?>" <?= $idFiliereSelecte===(int)$f['id_filiere']?'selected':'' ?>><?= h(gds_filiere_code((string)$f['nom_filiere'])) ?></option>
                      <?php endforeach; ?>
                  </select>
              </label>

              <label>Niveau
                  <select name="niveau" onchange="this.form.submit()" <?= ($idFiliereSelecte===0)?'disabled':'' ?>>
                      <?php foreach ($tousLesNiveaux as $niv): ?>
                      <option value="<?= h($niv) ?>" <?= $niveauSelectionne===$niv?'selected':'' ?>><?= h($niv) ?></option>
                      <?php endforeach; ?>
                  </select>
              </label>

              <label>Classe
                  <select name="id_classe" onchange="this.form.submit()" <?= ($niveauSelectionne==='')?'disabled':'' ?>>
                      <option value="0">— Choisir —</option>
                      <?php foreach ($toutesLesClasses as $cl): ?>
                      <option value="<?= (int)$cl['id_classe'] ?>" <?= $idClasseSelecte===(int)$cl['id_classe']?'selected':'' ?>><?= h($cl['nom_classe']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </label>

              <label>Module
                  <select name="id_module" onchange="this.form.submit()" <?= ($idClasseSelecte===0)?'disabled':'' ?>>
                      <option value="0">— Tous —</option>
                      <?php foreach ($tousLesModules as $mod): ?>
                      <option value="<?= (int)$mod['id_module'] ?>" <?= $idModuleSelecte===(int)$mod['id_module']?'selected':'' ?>><?= h(gds_module_label((string)$mod['nom_module'])) ?></option>
                      <?php endforeach; ?>
                  </select>
              </label>

              <label>Date de l'appel
                  <input type="date" name="date_appel" value="<?= h($dateAppel) ?>" <?= ($idClasseSelecte===0)?'disabled':'' ?>>
              </label>

              <label>Heure début
                  <input type="time" name="heure_debut"
                         value="<?= h($heureDebut) ?>"
                         <?= ($idClasseSelecte===0)?'disabled':'' ?>>
              </label>

              <label>Heure fin
                  <input type="time" name="heure_fin"
                         value="<?= h($heureFin) ?>"
                         <?= ($idClasseSelecte===0)?'disabled':'' ?>>
              </label>

          </div>
          <div class="fa-btn-row">
              <button type="submit" class="fa-btn primary">
                  <i class="fa-solid fa-filter"></i> Générer
              </button>
              <?php if ($peutImprimer): ?>
              <button type="button" class="fa-btn ghost" onclick="window.print()">
                  <i class="fa-solid fa-print"></i> Imprimer
              </button>
              <?php endif; ?>
              <a href="absences.php" style="color:#71717a;font-size:.82rem;text-decoration:none;margin-left:.5rem;">← Retour aux absences</a>
          </div>
      </form>
  </div>

  <!-- ── Document A4 ─────────────────────────────────────────────────────── -->
  <div class="cs-doc">

      <!-- En-tête officiel (partagé entre toutes les pages d'impression) -->
      <?php require __DIR__ . '/includes/print_letterhead.php'; ?>

      <!-- Titre -->
      <div class="cs-title-wrap">
          <div class="cs-title-oval">Feuille d'Appel</div>
      </div>

      <?php if (!$peutImprimer): ?>
      <!-- État vide -->
      <div style="text-align:center;padding:3rem 1rem;color:#555;">
          <p style="font-size:1.1rem;font-weight:600;">Sélectionnez une classe pour générer la feuille d'appel.</p>
          <p style="font-size:.9rem;color:#888;">Utilisez les filtres ci-dessus : Année → Filière → Niveau → Classe</p>
      </div>

      <?php else: ?>

      <!-- Informations de la séance -->
      <div class="fa-seance">
          <div class="fa-seance-bloc">
              <div><span class="label">Classe :</span> <?= h((string)$infoClasse['nom_classe']) ?> — <?= h(gds_filiere_code((string)$infoClasse['nom_filiere'])) ?></div>
              <div><span class="label">Niveau :</span> <?= h((string)$infoClasse['niveau']) ?></div>
              <div><span class="label">Année scolaire :</span> <?= h((string)$infoClasse['annee_scolaire']) ?></div>
          </div>
          <div class="fa-seance-bloc" style="text-align:right;">
              <div><span class="label">Date :</span> <?= h($dateAppelFr) ?></div>
              <?php if ($infoModule): ?>
              <div><span class="label">Module :</span> <?= h((string)$infoModule) ?></div>
              <?php endif; ?>
              <?php if ($horaireAffiche !== ''): ?>
              <div><span class="label">Horaire :</span> <?= h($horaireAffiche) ?></div>
              <?php endif; ?>
              <div><span class="label">Effectif :</span> <?= count($stagiaires) ?> stagiaire(s)</div>
          </div>
      </div>

      <!-- Tableau d'appel -->
      <table class="fa-table">
          <thead>
              <tr>
                  <th class="col-n">N°</th>
                  <th class="col-nom" style="text-align:left;">Nom &amp; Prénom</th>
                  <th class="col-abs">Absent</th>
                  <th class="col-pre">Présent</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($stagiaires as $idx => $stag): ?>
              <tr>
                  <td class="col-n"><?= $idx + 1 ?></td>
                  <td class="col-nom"><?= h(strtoupper($stag['nom']) . ' ' . $stag['prenom']) ?></td>
                  <td class="col-abs">&#9633;</td>
                  <td class="col-pre">&#9633;</td>
              </tr>
              <?php endforeach; ?>

              <!-- Lignes vides supplémentaires pour les stagiaires non inscrits éventuels -->
              <?php for ($extra = 1; $extra <= 2; $extra++): ?>
              <tr>
                  <td class="col-n"><?= count($stagiaires) + $extra ?></td>
                  <td class="col-nom">&nbsp;</td>
                  <td class="col-abs">&#9633;</td>
                  <td class="col-pre">&#9633;</td>
              </tr>
              <?php endfor; ?>
          </tbody>
      </table>

      <!-- Zone de signatures -->
      <table class="cs-sign">
          <tr>
              <th>Le Professeur</th>
              <th>Le Directeur / Directrice</th>
              <th>Cachet de l'établissement</th>
          </tr>
          <tr>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
          </tr>
      </table>

      <?php endif; ?>

      <!-- Pied de page (partagé entre toutes les pages d'impression) -->
      <?php require __DIR__ . '/includes/print_footer.php'; ?>

  </div>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
  </body>
  </html>