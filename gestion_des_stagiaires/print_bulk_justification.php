<?php
  /**
   * print_bulk_justification.php — Récapitulatif d'une justification en masse
   *
   * Génère un document A4 imprimable listant toutes les absences justifiées
   * lors d'une opération de justification en masse (bulk_justify).
   *
   * Paramètres GET :
   *   ids[]  — IDs des absences concernées (doit être est_justifiee=1)
   *   motif  — Motif de justification (affiché en en-tête du document)
   *
   * Sécurité :
   *   - Réservé au Directeur (gds_is_directeur())
   *   - Seules les absences effectivement justifiées sont retournées
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  if (!gds_is_directeur()) {
      http_response_code(403);
      exit('Accès réservé au Directeur.');
  }

  // ── Constantes établissement ─────────────────────────────────────────────
  $SCHOOL_ORG         = 'GROUPE IPIRNET';
  $SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
  $SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
  $SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
  $SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
  $SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13';
  $SCHOOL_LEGAL       = 'Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293';

  // ── Paramètres ───────────────────────────────────────────────────────────
  $idsAbsences = array_filter(array_map('intval', (array)($_GET['ids'] ?? [])));
  $motifSaisi  = trim((string)($_GET['motif'] ?? ''));
  $dateImpression = date('d/m/Y');

  if (empty($idsAbsences)) {
      http_response_code(400);
      exit("Aucun identifiant d'absence fourni.");
  }

  // ── Requête sécurisée — uniquement les absences justifiées ───────────────
  $placeholders   = implode(',', array_fill(0, count($idsAbsences), '?'));
  $requeteAbsences = $pdo->prepare(
      "SELECT a.id_absence, a.date_absence, a.heure_debut, a.heure_fin,
              a.justificatif, a.id_stagiaire,
              UPPER(s.nom) AS nom, s.prenom, s.num_inscri,
              c.nom_classe, c.annee_scolaire, f.nom_filiere,
              m.nom_module
         FROM absences a
         JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
         JOIN classes c    ON c.id_classe    = s.id_classe
         JOIN filieres f   ON f.id_filiere   = c.id_filiere
         LEFT JOIN modules m ON m.id_module  = a.id_module
        WHERE a.id_absence IN ($placeholders)
          AND a.est_justifiee = 1
        ORDER BY s.nom, s.prenom, a.date_absence"
  );
  $requeteAbsences->execute(array_values($idsAbsences));
  $lignesAbsences = $requeteAbsences->fetchAll();

  if (empty($lignesAbsences)) {
      http_response_code(404);
      exit('Aucune absence justifiée trouvée pour ces identifiants.');
  }

  // ── Grouper par stagiaire ─────────────────────────────────────────────────
  $groupes    = [];
  $anneeDoc   = '';
  $classeDoc  = '';
  foreach ($lignesAbsences as $ligne) {
      $sid = (int)$ligne['id_stagiaire'];
      if (!isset($groupes[$sid])) {
          $groupes[$sid] = [
              'nom'      => $ligne['nom'],
              'prenom'   => $ligne['prenom'],
              'num_inscri'=> $ligne['num_inscri'],
              'classe'   => $ligne['nom_classe'],
              'filiere'  => $ligne['nom_filiere'],
              'absences' => [],
          ];
          if (!$anneeDoc)  $anneeDoc  = (string)$ligne['annee_scolaire'];
          if (!$classeDoc) $classeDoc = (string)$ligne['nom_classe'];
      }
      $groupes[$sid]['absences'][] = $ligne;
  }

  $nbStagiaires = count($groupes);
  $nbAbsences   = count($lignesAbsences);

  // Motif réel = celui stocké en DB (priorité) ou GET param
  $motifAffiche = $motifSaisi ?: ($lignesAbsences[0]['justificatif'] ?? '—');

  $fmtFr = static function (?string $d): string {
      if (!$d) return '—';
      $t = strtotime($d);
      return $t !== false ? date('d/m/Y', $t) : $d;
  };
  ?><!DOCTYPE html>
  <html lang="fr">
  <head>
      <meta charset="utf-8">
      <title>Justification en masse — <?= h($dateImpression) ?></title>
      <style>
          @page { size: A4; margin: 12mm; }
          * { box-sizing: border-box; }
          html, body { background: #f1f3f5; }
          body {
              margin: 0;
              padding: 18px 0 40px;
              font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
              color: #111;
              font-size: 11pt;
          }
          .cs-doc {
              max-width: 880px;
              margin: 0 auto;
              background: #fff;
              padding: 28px 34px 24px;
              box-shadow: 0 4px 14px rgba(0,0,0,0.08);
              border: 1px solid #cdd0d4;
          }
          .cs-print-btns {
              text-align: center;
              margin-bottom: 14px;
          }
          .cs-print-btns button, .cs-print-btns a {
              background: #f4f4f5;
              border: 1px solid #ccc;
              padding: .35rem .8rem;
              border-radius: 8px;
              font-size: .85rem;
              cursor: pointer;
              text-decoration: none;
              color: #111;
              margin: 0 4px;
          }

          /* ── En-tête 3 colonnes ─────────────────────────────────── */
          .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
          .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
          .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
          .cs-head-logo { max-width: 88px; max-height: 88px; display: inline-block; }
          .cs-stamp {
              display: inline-flex; align-items: center; justify-content: center;
              width: 88px; height: 88px; border-radius: 50%;
              border: 2px solid #b8860b; color: #b8860b;
              font-family: "Times New Roman", serif; font-weight: 700;
              font-size: .95rem; letter-spacing: 0.05em;
              background: radial-gradient(circle, #fff 55%, transparent 56%),
                          repeating-conic-gradient(#b8860b 0 6deg, transparent 6deg 12deg);
              padding: 4px;
          }
          .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.55rem; letter-spacing: .03em; }
          .cs-head-mid .cs-tag  { font-style: italic; font-size: .93rem; margin-top: 2px; }
          .cs-head-mid .cs-auth { font-size: .78rem; margin-top: 4px; }

          /* ── Titre encadré ──────────────────────────────────────── */
          .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 16px; }
          .cs-title-oval {
              border: 1.5px solid #1a1a1a; border-radius: 50%;
              padding: 14px 56px; min-width: 55%; text-align: center;
              font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
              font-style: italic; font-size: 1.55rem; color: #0b3b66;
              white-space: nowrap; letter-spacing: .02em;
          }

          /* ── Bloc de métadonnées ────────────────────────────────── */
          .cs-meta {
              width: 100%; border-collapse: collapse;
              margin: 0 0 18px; border: 1px solid #b0b4ba;
          }
          .cs-meta td { padding: 5px 10px; font-size: 10.5pt; border-bottom: 1px solid #e0e0e0; }
          .cs-meta td:first-child { width: 30%; font-weight: 600; background: #f8f8f8; color: #333; }
          .cs-meta tr:last-child td { border-bottom: none; }

          /* ── Groupe par stagiaire ───────────────────────────────── */
          .stag-section { margin-bottom: 16px; break-inside: avoid; }
          .stag-nom {
              font-weight: 700; font-size: 11.5pt;
              background: #f0f0f0; border: 1px solid #ccc;
              padding: 5px 10px; margin-bottom: 0;
              display: flex; justify-content: space-between; align-items: center;
          }
          .stag-num-inscri { font-size: 9pt; font-weight: 400; color: #555; }

          /* ── Tableau des absences ───────────────────────────────── */
          .abs-table {
              width: 100%; border-collapse: collapse;
              border: 2px solid #000; font-size: 10.5pt; margin-bottom: 4px;
          }
          .abs-table th {
              background: #e8e8e8; border: 1px solid #000;
              padding: 6px 8px; text-align: center; font-weight: bold; font-size: 10pt;
              -webkit-print-color-adjust: exact; print-color-adjust: exact;
          }
          .abs-table td {
              border: 1px solid #000; padding: 5px 8px;
              vertical-align: middle; line-height: 1.4;
          }

          /* ── Zone de signature ──────────────────────────────────── */
          .cs-sign {
              margin: 24px 14px 18px;
              width: calc(100% - 28px);
              border-collapse: collapse;
          }
          .cs-sign th, .cs-sign td {
              border: 1px solid #111; padding: 10px 14px; vertical-align: top;
          }
          .cs-sign th {
              font-weight: 400; font-style: italic;
              font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
              font-size: 1.15rem; color: #0b3b66; text-align: center; background: #fafafa;
          }
          .cs-sign td { height: 60px; font-size: 10pt; }

          /* ── Pied de page ───────────────────────────────────────── */
          .cs-footer {
              border-top: 1px solid #111; padding-top: 6px;
              margin: 18px 6px 0; text-align: center;
              font-size: .79rem; line-height: 1.45; color: #444;
          }

          @media print {
              html, body { background: #fff; padding: 0; }
              .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
              .no-print, .cs-print-btns { display: none !important; }
              .stag-section { break-inside: avoid; }
          }
      </style>
  </head>
  <body>
  <div class="cs-doc">

      <div class="cs-print-btns no-print">
          <button type="button" onclick="window.print()">🖨 Imprimer</button>
          <a href="absences.php">← Retour aux absences</a>
      </div>

      <!-- En-tête officiel -->
      <table class="cs-head">
          <tr>
              <td class="cs-head-left">
                  <img src="assets/img/logo.png" alt="" class="cs-head-logo">
              </td>
              <td class="cs-head-mid">
                  <div class="cs-org"><?= h($SCHOOL_ORG) ?></div>
                  <div class="cs-tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
                  <div class="cs-tag"><?= h($SCHOOL_TAGLINE_2) ?></div>
                  <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
                  <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
              </td>
              <td class="cs-head-right">
                  <img src="assets/img/stamp_accredite.jpg" alt="Accrédité"
                       style="width:80px;height:80px;object-fit:contain;border-radius:50%;">
              </td>
          </tr>
      </table>

      <!-- Titre -->
      <div class="cs-title-wrap">
          <div class="cs-title-oval">Récapitulatif de Justification</div>
      </div>

      <!-- Métadonnées -->
      <table class="cs-meta">
          <tr>
              <td>Date d'impression</td>
              <td><?= h($dateImpression) ?></td>
          </tr>
          <tr>
              <td>Année scolaire</td>
              <td><?= h($anneeDoc) ?></td>
          </tr>
          <tr>
              <td>Motif de justification</td>
              <td><strong><?= h($motifAffiche) ?></strong></td>
          </tr>
          <tr>
              <td>Total</td>
              <td><?= $nbAbsences ?> absence(s) justifiée(s) — <?= $nbStagiaires ?> stagiaire(s)</td>
          </tr>
      </table>

      <!-- Absences groupées par stagiaire -->
      <?php foreach ($groupes as $stag): ?>
      <div class="stag-section">
          <div class="stag-nom">
              <span><?= h($stag['nom'] . ' ' . $stag['prenom']) ?>
                  &nbsp;<span class="stag-num-inscri">(<?= h($stag['num_inscri']) ?>)</span>
              </span>
              <span style="font-size:9pt;font-weight:400;color:#555;"><?= h($stag['classe']) ?></span>
          </div>
          <table class="abs-table">
              <thead>
                  <tr>
                      <th style="width:14%;">Date</th>
                      <th style="width:18%;">Horaire</th>
                      <th>Module</th>
                      <th>Justificatif</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($stag['absences'] as $abs): ?>
                  <?php
                      $dateF  = $fmtFr($abs['date_absence']);
                      $hDebut = $abs['heure_debut'] ? substr((string)$abs['heure_debut'], 0, 5) : null;
                      $hFin   = $abs['heure_fin']   ? substr((string)$abs['heure_fin'],   0, 5) : null;
                      $horaire = ($hDebut && $hFin) ? "$hDebut – $hFin" : 'Journée';
                      $module  = $abs['nom_module'] ?? '—';
                      $motifLigne = trim((string)($abs['justificatif'] ?? '')) ?: h($motifAffiche);
                  ?>
                  <tr>
                      <td><?= h($dateF) ?></td>
                      <td><?= h($horaire) ?></td>
                      <td><?= h($module) ?></td>
                      <td><?= h($motifLigne) ?></td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      </div>
      <?php endforeach; ?>

      <!-- Zone de signatures -->
      <table class="cs-sign">
          <tr>
              <th>Le Directeur</th>
              <th>La Secrétaire</th>
              <th>Visa / Cachet</th>
          </tr>
          <tr>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
          </tr>
      </table>

      <!-- Pied de page -->
      <div class="cs-footer">
          <?= h($SCHOOL_ADDRESS) ?><br>
          <?= h($SCHOOL_LEGAL) ?>
      </div>

  </div>
  <?php if (isset($_GET['auto']) && $_GET['auto'] === '1'): ?>
  <script>window.addEventListener('load', function(){ window.print(); });</script>
  <?php endif; ?>
  </body>
  </html>