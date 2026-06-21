<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$SCHOOL_ORG         = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_CITY        = 'Berrechid';
$SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL       = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

// ---- Params ----
$idClasse  = (int)($_GET['id_classe']  ?? 0);
$annee     = trim((string)($_GET['annee']      ?? ''));
$mois      = trim((string)($_GET['mois']       ?? ''));
$dateDebut = trim((string)($_GET['date_debut'] ?? ''));
$dateFin   = trim((string)($_GET['date_fin']   ?? ''));

if ($dateDebut !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) $dateDebut = '';
if ($dateFin   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin))   $dateFin   = '';
if ($mois      !== '' && !preg_match('/^\d{4}-\d{2}$/',        $mois))      $mois      = '';

if ($mois !== '') {
    $dateDebut = $mois . '-01';
    $dateFin   = date('Y-m-t', strtotime($dateDebut));
}

// ---- Dropdown data ----
$toutesLesAnnees = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);

if ($annee === '' && !empty($toutesLesAnnees)) {
    $annee = $_SESSION['global_annee_scolaire'] ?? $toutesLesAnnees[0];
}

$toutesLesClasses = [];
if ($annee !== '') {
    $stC = $pdo->prepare("SELECT c.id_classe, c.nom_classe, f.nom_filiere, c.niveau
                            FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
                           WHERE c.annee_scolaire = ? ORDER BY f.nom_filiere, c.niveau, c.nom_classe");
    $stC->execute([$annee]);
    $toutesLesClasses = $stC->fetchAll();
}

// ---- Info classe ----
$infoClasse = null;
if ($idClasse > 0) {
    $stI = $pdo->prepare("SELECT c.nom_classe, c.niveau, c.annee_scolaire, f.nom_filiere
                            FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
                           WHERE c.id_classe = ?");
    $stI->execute([$idClasse]);
    $infoClasse = $stI->fetch();
}

// ---- Absences ----
$absences = [];
if ($idClasse > 0) {
    $clauses = ['s.id_classe = ?'];
    $params  = [$idClasse];
    if ($dateDebut !== '') { $clauses[] = 'a.date_absence >= ?'; $params[] = $dateDebut; }
    if ($dateFin   !== '') { $clauses[] = 'a.date_absence <= ?'; $params[] = $dateFin; }

    $st = $pdo->prepare(
        "SELECT s.id_stagiaire, UPPER(s.nom) AS nom, s.prenom, s.num_inscri,
                a.date_absence, a.heure_debut, a.heure_fin,
                a.est_justifiee, a.justificatif, m.nom_module
           FROM stagiaires s
           JOIN absences a ON a.id_stagiaire = s.id_stagiaire
           LEFT JOIN modules m ON m.id_module = a.id_module
          WHERE " . implode(' AND ', $clauses) . "
          ORDER BY s.nom, s.prenom, a.date_absence, a.heure_debut"
    );
    $st->execute($params);
    $absences = $st->fetchAll();
}

// ---- Group by student ----
$parStagiaire = [];
foreach ($absences as $r) {
    $sid = (int)$r['id_stagiaire'];
    if (!isset($parStagiaire[$sid])) {
        $parStagiaire[$sid] = ['nom' => $r['nom'], 'prenom' => $r['prenom'], 'num_inscri' => $r['num_inscri'], 'rows' => []];
    }
    $parStagiaire[$sid]['rows'][] = $r;
}

$totA = count($absences);
$totJ = count(array_filter($absences, fn($r) => (int)$r['est_justifiee'] === 1));
$totN = $totA - $totJ;

// ---- Helpers ----
$fmtDate = fn(string $d): string => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3/$2/$1', $d);
$fmtHor  = fn($r): string => !empty($r['heure_debut'])
    ? substr((string)$r['heure_debut'], 0, 5) . ' – ' . substr((string)($r['heure_fin'] ?? ''), 0, 5)
    : 'Journée';

function libPeriode(string $dd, string $df, string $mois): string {
    if ($mois !== '') {
        $mn = ['','Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $m = (int)substr($mois, 5, 2);
        return $mn[$m] . ' ' . substr($mois, 0, 4);
    }
    if ($dd === '' && $df === '') return 'Toute la période';
    $fmt = fn(string $d) => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3/$2/$1', $d);
    if ($dd !== '' && $df !== '') return 'Du ' . $fmt($dd) . ' au ' . $fmt($df);
    if ($dd !== '') return 'À partir du ' . $fmt($dd);
    return "Jusqu'au " . $fmt($df);
}

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé d'Absences<?= $infoClasse ? ' — ' . h((string)$infoClasse['nom_classe']) : '' ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body { background: #f1f3f5; }
        body {
            margin: 0;
            padding: 18px 0 40px;
            font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
            color: #111;
            font-size: 12pt;
        }

        /* ── Filter bar (screen only) ── */
        .filter-bar {
            max-width: 880px;
            margin: 0 auto 14px;
            background: #fff;
            border: 1px solid #cdd0d4;
            border-radius: 10px;
            padding: 12px 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar label { display: flex; flex-direction: column; gap: 3px; font-size: 9pt; font-weight: 600; color: #555; }
        .filter-bar select, .filter-bar input { border: 1px solid #ccc; border-radius: 6px; padding: 4px 8px; font-size: 9pt; }
        .filter-bar button { background: #1a3a6e; color: #fff; border: none; border-radius: 8px;
            padding: 6px 18px; font-size: 9pt; font-weight: 600; cursor: pointer; }

        /* ── cs-doc (identical to print_certificat_scolarite.php) ── */
        .cs-doc {
            max-width: 880px;
            margin: 0 auto;
            background: #fff;
            padding: 28px 34px 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border: 1px solid #cdd0d4;
        }
        .cs-print-btns { text-align: center; margin-bottom: 14px; }
        .cs-print-btns button, .cs-print-btns a {
            background: #f4f4f5; border: 1px solid #ccc;
            padding: .35rem .8rem; border-radius: 8px; font-size: .85rem;
            cursor: pointer; text-decoration: none; color: #111; margin: 0 4px;
        }

        /* ── Letterhead (identical) ── */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 90px; max-height: 90px; display: inline-block; }
        .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag  { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* ── Oval title (identical) ── */
        .cs-title-wrap { display: flex; justify-content: center; margin: 24px 0 16px; }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a; border-radius: 50%;
            padding: 14px 60px; min-width: 55%; text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic; font-size: 1.65rem; color: #0b3b66;
            letter-spacing: 0.02em; white-space: nowrap;
        }

        /* ── Meta line ── */
        .cs-meta { text-align: center; margin-bottom: 16px; font-size: 11pt; line-height: 1.6; }

        /* ── Main absence table ── */
        .abs-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 20px; }
        .abs-table th {
            background: #1a3a6e; color: #fff;
            border: 1px solid #1a3a6e;
            padding: 6px 8px; text-align: left; font-weight: 700; font-size: 9.5pt;
        }
        .abs-table td { border: 1px solid #ccc; padding: 5px 8px; vertical-align: middle; }
        /* Student sub-header row */
        .abs-table tr.stag-row td {
            background: #e8edf6; font-weight: 700; font-size: 10pt;
            border-top: 2px solid #1a3a6e; border-bottom: 1px solid #b0bcd8;
            color: #1a3a6e;
        }
        .abs-table tr.stag-row td .inscri {
            font-weight: 400; font-size: 8.5pt; color: #6b7280;
            font-family: monospace; margin-left: 8px;
        }
        /* Alternating rows per student (reset per student block) */
        .abs-table tr.abs-row-even td { background: #f7f9fc; }
        .abs-table tr.abs-row-odd  td { background: #fff; }
        /* Subtotal row */
        .abs-table tr.sub-total td {
            background: #f0f4fa; font-size: 9pt; font-style: italic; color: #444;
            border-top: 1px solid #b0bcd8;
        }
        /* Status badges */
        .badge-j  { color: #166534; font-weight: 700; }
        .badge-nj { color: #991b1b; font-weight: 700; }

        /* ── Grand total box ── */
        .totaux { margin: 6px 0 22px; display: flex; gap: 24px; justify-content: center; }
        .totaux div {
            border: 1px solid #ccc; border-radius: 8px; padding: 8px 22px;
            text-align: center; font-size: 11pt;
        }
        .totaux div span { display: block; font-size: 1.5rem; font-weight: 700; }
        .t-total { color: #111; }
        .t-just  { color: #166534; }
        .t-nj    { color: #991b1b; }

        /* ── Signatures ── */
        .cs-sign { margin: 22px 0 18px; width: 100%; border-collapse: collapse; }
        .cs-sign th, .cs-sign td { border: 1px solid #111; padding: 10px 14px; vertical-align: top; width: 33.33%; }
        .cs-sign th {
            font-weight: 400; font-style: italic;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-size: 1.15rem; color: #0b3b66; text-align: center; background: #fafafa;
        }
        .cs-sign td { height: 26mm; font-size: 10pt; }

        /* ── Footer (identical) ── */
        .cs-footer {
            border-top: 1px solid #111; padding-top: 6px; margin: 18px 0 0;
            text-align: center; font-size: .82rem; line-height: 1.45; color: #444;
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
            .no-print, .filter-bar, .cs-print-btns { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Filter bar (screen only) -->
<form method="get" action="print_releve_absences.php" class="filter-bar no-print">
  <label>Année
    <select name="annee" onchange="this.form.submit()">
      <option value="">— Choisir —</option>
      <?php foreach ($toutesLesAnnees as $an): ?>
        <option value="<?= h($an) ?>" <?= $annee === $an ? 'selected' : '' ?>><?= h($an) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Classe
    <select name="id_classe">
      <option value="0">— Toutes —</option>
      <?php foreach ($toutesLesClasses as $cl): ?>
        <option value="<?= (int)$cl['id_classe'] ?>" <?= $idClasse === (int)$cl['id_classe'] ? 'selected' : '' ?>>
          <?= h((string)$cl['nom_classe']) ?> (<?= h(gds_filiere_code((string)$cl['nom_filiere'])) ?> — <?= h((string)$cl['niveau']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Mois
    <input type="month" name="mois" value="<?= h($mois) ?>">
  </label>

  <label>Du
    <input type="date" name="date_debut" value="<?= h($dateDebut) ?>">
  </label>

  <label>Au
    <input type="date" name="date_fin" value="<?= h($dateFin) ?>">
  </label>

  <button type="submit">Filtrer</button>
</form>

<?php if ($idClasse === 0 && empty($absences)): ?>
<div style="max-width:880px;margin:0 auto;text-align:center;padding:3rem;color:#555;font-family:Cambria,serif;">
  <p style="font-size:1.1rem;">Sélectionnez une classe et une période pour générer le relevé.</p>
</div>
<?php else: ?>

<div class="cs-doc">

  <!-- Print/back buttons (screen only) -->
  <div class="cs-print-btns no-print">
    <button type="button" onclick="window.print()">🖨 Imprimer</button>
    <a href="absences.php">← Retour</a>
  </div>

  <!-- Letterhead -->
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

  <!-- Oval title -->
  <div class="cs-title-wrap">
    <div class="cs-title-oval">Relevé des Absences</div>
  </div>

  <!-- Meta -->
  <div class="cs-meta">
    <?php if ($infoClasse): ?>
      <strong>Classe : <?= h((string)$infoClasse['nom_classe']) ?></strong>
      &nbsp;·&nbsp; Filière : <?= h(gds_filiere_code((string)$infoClasse['nom_filiere'])) ?>
      &nbsp;·&nbsp; Niveau : <?= h((string)$infoClasse['niveau']) ?>
      &nbsp;·&nbsp; Année : <?= h($annee) ?><br>
    <?php endif; ?>
    <span style="font-size:10pt;color:#555;">
      Période : <?= h(libPeriode($dateDebut, $dateFin, $mois)) ?>
      &nbsp;·&nbsp; Édité le <?= h(date('d/m/Y H:i')) ?>
    </span>
  </div>

  <?php if (empty($parStagiaire)): ?>
    <p style="text-align:center;color:#666;padding:2rem;">Aucune absence pour cette sélection.</p>
  <?php else: ?>

  <!-- Single absence table, all students, sub-header row per student -->
  <table class="abs-table">
    <thead>
      <tr>
        <th style="width:13%">Date</th>
        <th style="width:14%">Horaire</th>
        <th style="width:22%">Module</th>
        <th style="width:12%">Statut</th>
        <th>Justification</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($parStagiaire as $stag):
        $nbT = count($stag['rows']);
        $nbJ = count(array_filter($stag['rows'], fn($r) => (int)$r['est_justifiee'] === 1));
        $nbN = $nbT - $nbJ;
        $i   = 0;
    ?>
      <!-- Student name row -->
      <tr class="stag-row">
        <td colspan="5">
          <?= h($stag['nom']) ?> <?= h($stag['prenom']) ?>
          <span class="inscri"><?= h($stag['num_inscri']) ?></span>
        </td>
      </tr>
      <!-- Absence rows -->
      <?php foreach ($stag['rows'] as $abs):
          $estJ  = (int)$abs['est_justifiee'] === 1;
          $motif = trim((string)($abs['justificatif'] ?? ''));
      ?>
      <tr class="<?= $i % 2 === 0 ? 'abs-row-even' : 'abs-row-odd' ?>">
        <td><?= h($fmtDate((string)$abs['date_absence'])) ?></td>
        <td><?= h($fmtHor($abs)) ?></td>
        <td><?= h((string)($abs['nom_module'] ?? '—')) ?></td>
        <td class="<?= $estJ ? 'badge-j' : 'badge-nj' ?>"><?= $estJ ? '✓ Justifiée' : '✗ Non justifiée' ?></td>
        <td style="font-style:<?= $motif ? 'normal' : 'italic' ?>;color:<?= $motif ? '#111' : '#999' ?>;"><?= h($motif ?: '—') ?></td>
      </tr>
      <?php $i++; endforeach; ?>
      <!-- Sub-total -->
      <tr class="sub-total">
        <td colspan="5">
          Sous-total : <strong><?= $nbT ?></strong> absence(s) &mdash;
          <span class="badge-j"><?= $nbJ ?> justifiée(s)</span> &mdash;
          <span class="badge-nj"><?= $nbN ?> non justifiée(s)</span>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Grand totals -->
  <div class="totaux">
    <div class="t-total"><span><?= $totA ?></span>Total absences</div>
    <div class="t-just"><span><?= $totJ ?></span>Justifiées</div>
    <div class="t-nj"><span><?= $totN ?></span>Non justifiées</div>
  </div>

  <?php endif; ?>

  <!-- Signatures -->
  <table class="cs-sign">
    <tr>
      <th>Secrétaire</th>
      <th>Directeur</th>
      <th>Cachet</th>
    </tr>
    <tr>
      <td>Fait à <?= h($SCHOOL_CITY) ?> le : <?= h(date('d/m/Y')) ?></td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
  </table>

  <!-- Footer -->
  <div class="cs-footer">
    <?= h($SCHOOL_ORG) ?> — <?= h($SCHOOL_ADDRESS) ?><br>
    <?= h($SCHOOL_LEGAL) ?><br>
    Document officiel généré le <?= h(date('d/m/Y H:i')) ?>
  </div>

</div>
<?php endif; ?>

<?php if ($auto): ?>
<script>window.addEventListener('load',function(){ setTimeout(function(){ window.print(); },300); });</script>
<?php endif; ?>
</body>
</html>
