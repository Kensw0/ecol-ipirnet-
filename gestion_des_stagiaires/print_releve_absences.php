<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School constants ----
$SCHOOL_ORG         = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_CITY        = 'Berrechid';
$SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL       = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

// ---- Filtres ----
$idClasse    = (int)($_GET['id_classe']  ?? 0);
$annee       = trim((string)($_GET['annee']       ?? ''));
$mois        = trim((string)($_GET['mois']        ?? ''));   // format YYYY-MM
$dateDebut   = trim((string)($_GET['date_debut']  ?? ''));
$dateFin     = trim((string)($_GET['date_fin']    ?? ''));

// Validation des dates
if ($dateDebut !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) $dateDebut = '';
if ($dateFin   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin))   $dateFin   = '';
if ($mois      !== '' && !preg_match('/^\d{4}-\d{2}$/', $mois))             $mois      = '';

// Si un mois est choisi, il prime sur date_debut/date_fin
if ($mois !== '') {
    [$annMois, $numMois] = explode('-', $mois);
    $dateDebut = $mois . '-01';
    $dateFin   = date('Y-m-t', strtotime($dateDebut));
}

// ---- Chargement des filtres en cascade ----
$toutesLesAnnees = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$' ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);

if ($annee === '' && !empty($toutesLesAnnees)) {
    $annee = $_SESSION['global_annee_scolaire'] ?? $toutesLesAnnees[0];
}

$toutesLesFilieres = $pdo->query(
    "SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f
      INNER JOIN classes c ON c.id_filiere = f.id_filiere ORDER BY f.nom_filiere"
)->fetchAll();

$toutesLesClasses = [];
if ($annee !== '') {
    $stmtC = $pdo->prepare("SELECT c.id_classe, c.nom_classe, f.nom_filiere, c.niveau
                              FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
                             WHERE c.annee_scolaire = ? ORDER BY f.nom_filiere, c.niveau, c.nom_classe");
    $stmtC->execute([$annee]);
    $toutesLesClasses = $stmtC->fetchAll();
}

// ---- Info classe sélectionnée ----
$infoClasse = null;
if ($idClasse > 0) {
    $stmtIC = $pdo->prepare(
        "SELECT c.nom_classe, c.niveau, c.annee_scolaire, f.nom_filiere
           FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
          WHERE c.id_classe = ?"
    );
    $stmtIC->execute([$idClasse]);
    $infoClasse = $stmtIC->fetch();
}

// ---- Requête des absences ----
$lignesAbsences = [];
if ($idClasse > 0) {
    $clauses = ['s.id_classe = ?'];
    $params  = [$idClasse];

    if ($dateDebut !== '') { $clauses[] = 'a.date_absence >= ?'; $params[] = $dateDebut; }
    if ($dateFin   !== '') { $clauses[] = 'a.date_absence <= ?'; $params[] = $dateFin; }

    $sql = "SELECT s.id_stagiaire, UPPER(s.nom) AS nom, s.prenom, s.num_inscri,
                   a.id_absence, a.date_absence, a.heure_debut, a.heure_fin,
                   a.est_justifiee, a.justificatif, m.nom_module
              FROM stagiaires s
              JOIN absences a ON a.id_stagiaire = s.id_stagiaire
              LEFT JOIN modules m ON m.id_module = a.id_module
             WHERE " . implode(' AND ', $clauses) . "
             ORDER BY s.nom, s.prenom, a.date_absence, a.heure_debut";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lignesAbsences = $stmt->fetchAll();
}

// Grouper par stagiaire
$parStagiaire = [];
foreach ($lignesAbsences as $ligne) {
    $sid = (int)$ligne['id_stagiaire'];
    if (!isset($parStagiaire[$sid])) {
        $parStagiaire[$sid] = [
            'nom'       => $ligne['nom'],
            'prenom'    => $ligne['prenom'],
            'num_inscri'=> $ligne['num_inscri'],
            'absences'  => [],
        ];
    }
    $parStagiaire[$sid]['absences'][] = $ligne;
}

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

// Libellé de la période
function libellePeriode(string $dd, string $df): string {
    if ($dd === '' && $df === '') return 'Toute la période';
    $fmt = fn(string $d) => preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3/$2/$1', $d);
    if ($dd !== '' && $df !== '') {
        // Même mois ?
        if (substr($dd, 0, 7) === substr($df, 0, 7)) {
            $moisFr = ['','Janvier','Février','Mars','Avril','Mai','Juin',
                       'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
            $m = (int)substr($dd, 5, 2);
            $y = substr($dd, 0, 4);
            return $moisFr[$m] . ' ' . $y;
        }
        return 'Du ' . $fmt($dd) . ' au ' . $fmt($df);
    }
    if ($dd !== '') return 'À partir du ' . $fmt($dd);
    return "Jusqu'au " . $fmt($df);
}

$libPeriode = libellePeriode($dateDebut, $dateFin);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé d'Absences — <?= $infoClasse ? h((string)$infoClasse['nom_classe']) : 'Toutes classes' ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body { background: #f1f3f5; }
        body {
            margin: 0;
            padding: 18px 0 40px;
            font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
            color: #111;
            font-size: 10pt;
        }

        /* ── Formulaire de filtre (no-print) ── */
        .filter-bar {
            background: #fff;
            border: 1px solid #cdd0d4;
            border-radius: 10px;
            padding: 14px 20px;
            max-width: 880px;
            margin: 0 auto 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar label { display: flex; flex-direction: column; gap: 3px; font-size: 8.5pt; font-weight: 600; color: #555; }
        .filter-bar select, .filter-bar input[type="date"], .filter-bar input[type="month"] {
            border: 1px solid #ccc; border-radius: 6px; padding: 4px 8px; font-size: 9pt; background: #fafafa;
        }
        .filter-bar button {
            background: #1a3a6e; color: #fff; border: none; border-radius: 8px;
            padding: 6px 18px; font-size: 9pt; font-weight: 600; cursor: pointer;
        }
        .filter-bar button:hover { background: #25529e; }
        .print-btns { text-align: center; margin: 0 auto 14px; max-width: 880px; }
        .print-btns button, .print-btns a {
            background: #f4f4f5; border: 1px solid #ccc; padding: .35rem .8rem;
            border-radius: 8px; font-size: .85rem; cursor: pointer; text-decoration: none;
            color: #111; margin: 0 4px;
        }

        /* ── Document principal ── */
        .cs-doc {
            max-width: 880px;
            margin: 0 auto;
            background: #fff;
            padding: 22px 28px 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border: 1px solid #cdd0d4;
        }

        /* ── En-tête ── */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cs-head td { border: 1px solid #111; padding: 7px 9px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 80px; max-height: 80px; }
        .cs-stamp {
            display: inline-flex; align-items: center; justify-content: center;
            width: 76px; height: 76px; border-radius: 50%;
            border: 2px solid #b8860b; color: #b8860b;
            font-family: "Times New Roman", serif; font-weight: 700; font-size: .85rem;
            background: radial-gradient(circle, #fff 55%, transparent 56%),
                        repeating-conic-gradient(#b8860b 0 6deg, transparent 6deg 12deg);
            padding: 4px;
        }
        .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.35rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag  { font-style: italic; font-size: .85rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .72rem; margin-top: 1px; }

        /* ── Titre oval ── */
        .cs-title-wrap { display: flex; justify-content: center; margin: 10px 0 6px; }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a;
            border-radius: 50%;
            padding: 8px 40px;
            min-width: 55%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 1.45rem;
            color: #0b3b66;
        }

        /* ── Méta classe ── */
        .cs-meta { text-align: center; margin-bottom: 10px; font-size: 9.5pt; }
        .cs-meta strong { font-size: 11pt; }

        /* ── Tableau par stagiaire ── */
        .stag-block { margin-bottom: 14px; break-inside: avoid; }
        .stag-header {
            background: #1a3a6e;
            color: #fff;
            padding: 5px 10px;
            font-weight: 700;
            font-size: 9.5pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 4px 4px 0 0;
        }
        .stag-header .stag-code { font-family: monospace; font-size: 8pt; opacity: .82; }
        .abs-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        .abs-table th {
            background: #e8edf6;
            border: 1px solid #c0c8d8;
            padding: 4px 7px;
            text-align: left;
            font-weight: 700;
            font-size: 8pt;
            color: #1a3a6e;
        }
        .abs-table td { border: 1px solid #d0d5df; padding: 3px 7px; vertical-align: top; }
        .abs-table tr:nth-child(even) td { background: #f7f9fc; }
        .badge-j   { color: #166534; font-weight: 700; }
        .badge-nj  { color: #991b1b; font-weight: 700; }

        /* ── Résumé par stagiaire ── */
        .stag-summary { background: #f0f4fa; border: 1px solid #c0c8d8; border-top: none;
            padding: 3px 10px; font-size: 7.5pt; color: #333; border-radius: 0 0 4px 4px; }

        /* ── Totaux globaux ── */
        .totaux-table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 9pt; }
        .totaux-table th { background: #1a3a6e; color: #fff; border: 1px solid #1a3a6e; padding: 5px 8px; }
        .totaux-table td { border: 1px solid #c0c8d8; padding: 4px 8px; }
        .totaux-table tr:nth-child(even) td { background: #f7f9fc; }

        /* ── Pied de page ── */
        .cs-footer { border-top: 1px solid #111; padding-top: 4px; margin-top: 14px;
            text-align: center; font-size: 7pt; color: #444; line-height: 1.4; }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; }
            .no-print, .filter-bar, .print-btns { display: none !important; }
            .stag-block { break-inside: avoid; }
        }
    </style>
</head>
<body>

<!-- ── Barre de filtres (no-print) ─────────────────────────────────── -->
<form method="get" action="print_releve_absences.php" class="filter-bar no-print">
  <label>Année scolaire
    <select name="annee" onchange="this.form.submit()">
      <option value="">— Choisir —</option>
      <?php foreach ($toutesLesAnnees as $an): ?>
        <option value="<?= h($an) ?>" <?= $annee === $an ? 'selected' : '' ?>><?= h($an) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Classe
    <select name="id_classe">
      <option value="0">— Toutes les classes —</option>
      <?php foreach ($toutesLesClasses as $cl): ?>
        <option value="<?= (int)$cl['id_classe'] ?>" <?= $idClasse === (int)$cl['id_classe'] ? 'selected' : '' ?>>
          <?= h(gds_filiere_code((string)$cl['nom_filiere'])) ?> · <?= h((string)$cl['niveau']) ?> · <?= h((string)$cl['nom_classe']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Mois
    <input type="month" name="mois" value="<?= h($mois) ?>" placeholder="YYYY-MM">
  </label>

  <label>Date début
    <input type="date" name="date_debut" value="<?= h($dateDebut) ?>">
  </label>

  <label>Date fin
    <input type="date" name="date_fin" value="<?= h($dateFin) ?>">
  </label>

  <button type="submit">Filtrer</button>
</form>

<!-- ── Boutons (no-print) ──────────────────────────────────────────── -->
<div class="print-btns no-print">
  <button type="button" onclick="window.print()">🖨 Imprimer</button>
  <a href="absences.php">← Retour absences</a>
</div>

<?php if (empty($parStagiaire) && $idClasse === 0): ?>
<!-- Sélection vide -->
<div style="max-width:880px;margin:0 auto;text-align:center;padding:3rem;color:#555;">
  <p style="font-size:1.1rem;">Sélectionnez une classe et une période pour générer le relevé.</p>
</div>
<?php else: ?>

<!-- ── Document imprimable ─────────────────────────────────────────── -->
<div class="cs-doc">

  <!-- En-tête -->
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
        <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:72px;height:72px;object-fit:contain;border-radius:50%;">
      </td>
    </tr>
  </table>

  <!-- Titre -->
  <div class="cs-title-wrap">
    <div class="cs-title-oval">Relevé des Absences</div>
  </div>

  <!-- Méta -->
  <div class="cs-meta">
    <?php if ($infoClasse): ?>
      <strong>Classe : <?= h((string)$infoClasse['nom_classe']) ?></strong>
      — Filière : <?= h(gds_filiere_code((string)$infoClasse['nom_filiere'])) ?>
      — Niveau : <?= h((string)$infoClasse['niveau']) ?>
      — Année : <?= h($annee) ?><br>
    <?php else: ?>
      <strong>Toutes les classes</strong> — Année : <?= h($annee) ?><br>
    <?php endif; ?>
    <span style="font-size:9pt;color:#555;">Période : <?= h($libPeriode) ?>
    &nbsp;·&nbsp; Édité le <?= h(date('d/m/Y H:i')) ?></span>
  </div>

  <?php if (empty($parStagiaire)): ?>
    <p style="text-align:center;color:#666;padding:2rem;">Aucune absence enregistrée pour cette sélection.</p>
  <?php else: ?>

  <!-- Blocs par stagiaire -->
  <?php foreach ($parStagiaire as $sid => $stag):
      $nbTotal = count($stag['absences']);
      $nbJust  = count(array_filter($stag['absences'], fn($r) => (int)$r['est_justifiee'] === 1));
      $nbNonJ  = $nbTotal - $nbJust;
  ?>
  <div class="stag-block">
    <div class="stag-header">
      <span><?= h($stag['nom']) ?> <?= h($stag['prenom']) ?></span>
      <span class="stag-code"><?= h($stag['num_inscri']) ?></span>
    </div>
    <table class="abs-table">
      <thead>
        <tr>
          <th style="width:12%">Date</th>
          <th style="width:14%">Horaire</th>
          <th style="width:20%">Module</th>
          <th style="width:13%">Statut</th>
          <th>Justification</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stag['absences'] as $abs):
            $d = (string)($abs['date_absence'] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mm3)) {
                $d = "$mm3[3]/$mm3[2]/$mm3[1]";
            }
            $hor = '—';
            if (!empty($abs['heure_debut'])) {
                $hor = substr((string)$abs['heure_debut'], 0, 5) . ' – ' . substr((string)($abs['heure_fin'] ?? ''), 0, 5);
            }
            $estJ  = (int)$abs['est_justifiee'] === 1;
            $motif = trim((string)($abs['justificatif'] ?? ''));
        ?>
        <tr>
          <td><?= h($d) ?></td>
          <td><?= h($hor) ?></td>
          <td><?= h((string)($abs['nom_module'] ?? '—')) ?></td>
          <td class="<?= $estJ ? 'badge-j' : 'badge-nj' ?>"><?= $estJ ? 'Justifiée' : 'Non justifiée' ?></td>
          <td style="font-style:<?= $motif ? 'normal' : 'italic' ?>;color:<?= $motif ? '#111' : '#888' ?>;"><?= h($motif ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="stag-summary">
      Total : <strong><?= $nbTotal ?></strong> absence(s) &nbsp;|&nbsp;
      Justifiées : <strong style="color:#166534;"><?= $nbJust ?></strong> &nbsp;|&nbsp;
      Non justifiées : <strong style="color:#991b1b;"><?= $nbNonJ ?></strong>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Totaux globaux -->
  <?php
    $totA = array_sum(array_map(fn($s) => count($s['absences']), $parStagiaire));
    $totJ = array_sum(array_map(fn($s) => count(array_filter($s['absences'], fn($r) => (int)$r['est_justifiee'] === 1)), $parStagiaire));
    $totN = $totA - $totJ;
  ?>
  <table class="totaux-table">
    <thead>
      <tr>
        <th>Stagiaire</th>
        <th style="text-align:center;">Total absences</th>
        <th style="text-align:center;">Justifiées</th>
        <th style="text-align:center;">Non justifiées</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($parStagiaire as $stag2):
          $t2 = count($stag2['absences']);
          $j2 = count(array_filter($stag2['absences'], fn($r) => (int)$r['est_justifiee'] === 1));
          $n2 = $t2 - $j2;
      ?>
      <tr>
        <td><?= h($stag2['nom']) ?> <?= h($stag2['prenom']) ?> <span style="font-size:7.5pt;color:#888;"><?= h($stag2['num_inscri']) ?></span></td>
        <td style="text-align:center;font-weight:700;"><?= $t2 ?></td>
        <td style="text-align:center;color:#166534;font-weight:700;"><?= $j2 ?></td>
        <td style="text-align:center;color:#991b1b;font-weight:700;"><?= $n2 ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#e8edf6;">
        <td style="font-weight:700;font-size:9pt;">TOTAL</td>
        <td style="text-align:center;font-weight:700;font-size:9pt;"><?= $totA ?></td>
        <td style="text-align:center;font-weight:700;color:#166534;font-size:9pt;"><?= $totJ ?></td>
        <td style="text-align:center;font-weight:700;color:#991b1b;font-size:9pt;"><?= $totN ?></td>
      </tr>
    </tfoot>
  </table>

  <?php endif; ?>

  <!-- Signatures -->
  <table style="width:100%;border-collapse:collapse;margin-top:22px;">
    <tr>
      <td style="width:33%;text-align:center;border:1px solid #111;padding:6px 10px;font-style:italic;font-family:'Monotype Corsiva','Lucida Handwriting',cursive;font-size:10pt;color:#0b3b66;">Secrétaire</td>
      <td style="width:34%;text-align:center;border:1px solid #111;padding:6px 10px;font-style:italic;font-family:'Monotype Corsiva','Lucida Handwriting',cursive;font-size:10pt;color:#0b3b66;">Directeur</td>
      <td style="width:33%;text-align:center;border:1px solid #111;padding:6px 10px;font-style:italic;font-family:'Monotype Corsiva','Lucida Handwriting',cursive;font-size:10pt;color:#0b3b66;">Cachet</td>
    </tr>
    <tr>
      <td style="border:1px solid #111;height:22mm;padding:4px 8px;font-size:8pt;vertical-align:bottom;">
        <p style="margin:0;">Fait à <?= h($SCHOOL_CITY) ?> le : <?= h(date('d/m/Y')) ?></p>
      </td>
      <td style="border:1px solid #111;height:22mm;">&nbsp;</td>
      <td style="border:1px solid #111;height:22mm;">&nbsp;</td>
    </tr>
  </table>

  <div class="cs-footer">
    <?= h($SCHOOL_ORG) ?> — <?= h($SCHOOL_ADDRESS) ?><br>
    <?= h($SCHOOL_LEGAL) ?><br>
    Document officiel généré le <?= h(date('d/m/Y H:i')) ?>
  </div>

</div>
<?php endif; ?>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });</script>
<?php endif; ?>
</body>
</html>
