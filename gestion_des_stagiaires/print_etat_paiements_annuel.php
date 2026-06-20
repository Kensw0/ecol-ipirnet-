<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ── School constants ─────────────────────────────────────────────────────────
$SCHOOL_ORG         = 'Groupe IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique, Réseau et Nouvelles Etudes de Télécommunication";
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N°: 03/02/2003   Du : 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N°: 21/DFP/F0301/199 du 21/11/2021";
$SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13';
$SCHOOL_LEGAL       = 'Email : ipirnet@menara.ma,  R.C :6693, Patente N° : 40724575, IF :14374293';

// ── Student lookup (accepts ?id_stagiaire= or ?id=) ─────────────────────────
$id = (int)($_GET['id_stagiaire'] ?? $_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Identifiant manquant.'); }

$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire = ?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); exit('Stagiaire introuvable.'); }

// ── Fetch remise_mensuelle and filière tarif ─────────────────────────────────
$stRem = $pdo->prepare('SELECT COALESCE(remise_mensuelle, 0) FROM stagiaires WHERE id_stagiaire = ?');
$stRem->execute([$id]);
$remiseMensuelle = max(0.0, (float)($stRem->fetchColumn() ?: 0));

$tarifsDefaut = [2 => 700.0, 3 => 600.0, 4 => 800.0];
$idFiliere    = (int)($s['id_filiere'] ?? 0);
$tarifStd     = $tarifsDefaut[$idFiliere] ?? 700.0;
$tarifEffectifBase = max(0.0, $tarifStd - $remiseMensuelle); // default when no record

// ── Build the 12-month list (Sept → Jun) from the student's annee_scolaire ──
$annee = (string)($s['annee_scolaire'] ?? '');
$parts = explode('/', $annee);
$year1 = (int)($parts[0] ?? date('Y'));
$year2 = (int)($parts[1] ?? ($year1 + 1));

$moisList = [
    sprintf('%04d-09', $year1), sprintf('%04d-10', $year1),
    sprintf('%04d-11', $year1), sprintf('%04d-12', $year1),
    sprintf('%04d-01', $year2), sprintf('%04d-02', $year2),
    sprintf('%04d-03', $year2), sprintf('%04d-04', $year2),
    sprintf('%04d-05', $year2), sprintf('%04d-06', $year2),
];

// ── Fetch all mensualites records for these months ───────────────────────────
$placeholders = implode(',', array_fill(0, count($moisList), '?'));
$stMens = $pdo->prepare(
    "SELECT mois_ref, montant_total, montant_paye, montant_restant, remise, statut_paiement, date_paiement
     FROM mensualites
     WHERE id_stagiaire = ? AND mois_ref IN ($placeholders)"
);
$stMens->execute(array_merge([$id], $moisList));
$records = [];
foreach ($stMens->fetchAll() as $r) { $records[$r['mois_ref']] = $r; }

// ── Build row data + totals ──────────────────────────────────────────────────
$moisLabels = [
    '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
    '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
    '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre',
];

$rows = [];
$totalDu = 0.0; $totalPaye = 0.0; $totalRestant = 0.0;

foreach ($moisList as $m) {
    $r = $records[$m] ?? null;
    [$yyyy, $mm] = explode('-', $m);
    $label = ($moisLabels[$mm] ?? $mm) . ' ' . $yyyy;

    if ($r) {
        // Use per-payment remise; fall back to student's remise_mensuelle
        $eRemise  = max(0.0, (float)($r['remise'] ?? 0));
        if ($eRemise === 0.0) $eRemise = $remiseMensuelle;
        $du       = max(0.0, (float)$r['montant_total'] - $eRemise);
        $paye     = max(0.0, (float)($r['montant_paye']    ?? 0));
        $restant  = max(0.0, (float)($r['montant_restant'] ?? 0));
        $statut   = (string)($r['statut_paiement'] ?? 'impayé');
        $datePaie = $r['date_paiement'] ?? null;
    } else {
        $du       = $tarifEffectifBase;
        $paye     = 0.0;
        $restant  = $du;
        $statut   = 'impayé';
        $datePaie = null;
    }

    $totalDu      += $du;
    $totalPaye    += $paye;
    $totalRestant += $restant;

    $rows[] = compact('label', 'du', 'paye', 'restant', 'statut', 'datePaie');
}

// ── Log document generation ──────────────────────────────────────────────────
log_document_gen($pdo, 'etat_paiements_annuel', $id, (string)$s['num_inscri']);

$auto       = isset($_GET['auto']) && $_GET['auto'] === '1';
$nomComplet = trim((string)$s['nom'] . ' ' . (string)$s['prenom']);
$classe     = (string)($s['nom_classe']  ?? '');
$filiere    = mb_strtoupper((string)($s['nom_filiere'] ?? ''), 'UTF-8');

$fmtFr = static function (?string $d): string {
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
};
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>État Annuel des Paiements — <?= h($nomComplet) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        html, body { background: #e5e7eb; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #000; font-size: 11pt; padding: 20px 0; }

        .cs-print-btns { text-align: center; margin: 0 auto 20px; max-width: 820px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,.1); border: 1px solid #ddd; }
        .cs-print-btns button, .cs-print-btns a { background: #f4f4f5; border: 1px solid #ccc; padding: 8px 16px; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; color: #111; margin: 0 5px; font-family: sans-serif; transition: all .2s; }
        .cs-print-btns button:hover, .cs-print-btns a:hover { background: #e4e4e7; }

        .doc-wrapper { max-width: 820px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,.1); }

        /* ── Letterhead ─────────────────────────────────────────────────────── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: middle; text-align: center; }
        .school-name { font-weight: bold; font-size: 20px; margin-bottom: 5px; }
        .school-desc { font-weight: bold; font-size: 13px; margin-bottom: 3px; }
        .school-auth { font-size: 11px; margin-bottom: 2px; }
        .logo-img    { max-width: 90px; }

        /* ── Document title ─────────────────────────────────────────────────── */
        .eval-title { text-align: center; text-transform: uppercase; font-weight: bold; font-size: 15px; text-decoration: underline; margin-bottom: 8px; line-height: 1.5; }
        .eval-subtitle { text-align: center; font-size: 12px; margin-bottom: 20px; color: #333; font-style: italic; }

        /* ── Student identity table ─────────────────────────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 20px; font-size: 12px; }
        .info-table td { border: 1px solid #000; padding: 5px 10px; }
        .info-table td:first-child { width: 190px; background: #f2f2f2; font-weight: bold; text-align: center; }
        .info-table td:nth-child(2) { width: 10px; text-align: center; border-left: none; border-right: none; }
        .info-table td:last-child { border-left: none; font-weight: bold; }

        /* ── Payment table ──────────────────────────────────────────────────── */
        .pay-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 11.5pt; margin-bottom: 6px; }
        .pay-table th, .pay-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        .pay-table thead th { background: #e8e8e8; font-weight: bold; text-align: center; }
        .pay-table td.mois-col { font-weight: 600; }
        .pay-table td.num-col  { text-align: right; }
        .pay-table td.stat-col { text-align: center; font-weight: bold; }
        .pay-table .tfoot-row  { background: #e8e8e8; font-weight: bold; }
        .pay-table .tfoot-row td { text-align: right; }
        .pay-table .tfoot-row td.lbl { text-align: right; font-style: italic; }

        /* ── Status colours (print-safe, no bg fill except in tfoot) ───────── */
        .st-paye    { color: #15803d; }
        .st-partiel { color: #92400e; }
        .st-impaye  { color: #b91c1c; }

        /* ── Solde summary ──────────────────────────────────────────────────── */
        .summary-block { margin-top: 14px; border: 2px solid #000; width: 100%; border-collapse: collapse; font-size: 12pt; }
        .summary-block td { border: 1px solid #000; padding: 7px 12px; }
        .summary-block td.lbl { background: #f2f2f2; font-weight: bold; width: 55%; text-align: right; }
        .summary-block td.val { font-weight: bold; text-align: right; }
        .summary-block .solde-row td { background: #000; color: #fff; }

        /* ── Signature ──────────────────────────────────────────────────────── */
        .sign-block { margin-top: 26px; display: flex; justify-content: flex-end; }
        .sign-table { border-collapse: collapse; width: 220px; }
        .sign-table th { border: 1px solid #000; padding: 6px; background: #f2f2f2; font-weight: bold; font-style: italic; font-size: 12px; text-align: center; }
        .sign-table td { border: 1px solid #000; height: 80px; }

        /* ── Footer ─────────────────────────────────────────────────────────── */
        .doc-footer { border-top: 1px solid #000; padding-top: 6px; margin-top: 20px; text-align: center; font-size: 10.5px; line-height: 1.5; }

        @media print {
            html, body { background: #fff; padding: 0; }
            .doc-wrapper { box-shadow: none; padding: 10px 0; }
            .cs-print-btns { display: none; }
            .pay-table thead th     { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .pay-table .tfoot-row   { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-table td:first-child { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-block td.lbl   { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-block .solde-row td { background: #000 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="cs-print-btns no-print">
    <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    <a href="stagiaires.php?id=<?= $id ?>">Retour au dossier</a>
    <a href="cotisations.php">Cotisations</a>
</div>

<div class="doc-wrapper">

    <!-- ══ Letterhead ════════════════════════════════════════════════════════ -->
    <table class="header-table">
        <tr>
            <td style="width:18%; text-align:left;">
                <img src="assets/img/logo.png" alt="Logo IPIRNET" class="logo-img" onerror="this.style.display='none'">
            </td>
            <td style="width:64%;">
                <div class="school-name"><?= h($SCHOOL_ORG) ?></div>
                <div class="school-desc"><?= h($SCHOOL_TAGLINE_1) ?></div>
                <div class="school-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
                <div class="school-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
            </td>
            <td style="width:18%; text-align:right;">
                <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:78px;height:78px;object-fit:contain;border-radius:50%;">
            </td>
        </tr>
    </table>

    <!-- ══ Title ═════════════════════════════════════════════════════════════ -->
    <div class="eval-title">État des Paiements Annuels</div>
    <div class="eval-subtitle">Année scolaire <?= h($annee) ?> — Édité le <?= date('d/m/Y') ?></div>

    <!-- ══ Student info ══════════════════════════════════════════════════════ -->
    <table class="info-table">
        <tr><td>N° Inscription</td><td>:</td><td><?= h((string)($s['num_inscri'] ?? '')) ?></td></tr>
        <tr><td>Nom et Prénom</td><td>:</td><td><?= h(mb_strtoupper($nomComplet, 'UTF-8')) ?></td></tr>
        <tr><td>Filière</td><td>:</td><td><?= h($filiere) ?></td></tr>
        <tr><td>Classe</td><td>:</td><td><?= h($classe) ?></td></tr>
        <tr><td>Année scolaire</td><td>:</td><td><?= h($annee) ?></td></tr>
        <?php if ($remiseMensuelle > 0): ?>
        <tr><td>Remise mensuelle</td><td>:</td><td><?= number_format($remiseMensuelle, 2, ',', ' ') ?> MAD / mois</td></tr>
        <?php endif; ?>
    </table>

    <!-- ══ Monthly payment table ═════════════════════════════════════════════ -->
    <table class="pay-table">
        <thead>
            <tr>
                <th style="width:28%; text-align:left;">Mois</th>
                <th style="width:16%;">Dû (MAD)</th>
                <th style="width:16%;">Payé (MAD)</th>
                <th style="width:16%;">Restant (MAD)</th>
                <th style="width:14%;">Statut</th>
                <th style="width:10%;">Date paiement</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $statStr  = strtolower(trim($row['statut']));
                $statClass = match($statStr) {
                    'payé', 'paye'   => 'st-paye',
                    'partiel'        => 'st-partiel',
                    default          => 'st-impaye',
                };
                $statLabel = match($statStr) {
                    'payé', 'paye'   => 'Payé ✓',
                    'partiel'        => 'Partiel',
                    default          => 'Impayé',
                };
            ?>
            <tr>
                <td class="mois-col"><?= h($row['label']) ?></td>
                <td class="num-col"><?= number_format($row['du'],      2, ',', ' ') ?></td>
                <td class="num-col"><?= number_format($row['paye'],    2, ',', ' ') ?></td>
                <td class="num-col <?= $row['restant'] > 0 ? 'st-impaye' : '' ?>"><?= number_format($row['restant'], 2, ',', ' ') ?></td>
                <td class="stat-col <?= $statClass ?>"><?= $statLabel ?></td>
                <td style="text-align:center; font-size:10pt;"><?= h($fmtFr($row['datePaie'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td class="lbl">Totaux</td>
                <td class="num-col"><?= number_format($totalDu,      2, ',', ' ') ?></td>
                <td class="num-col"><?= number_format($totalPaye,    2, ',', ' ') ?></td>
                <td class="num-col"><?= number_format($totalRestant, 2, ',', ' ') ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <!-- ══ Summary block ═════════════════════════════════════════════════════ -->
    <table class="summary-block">
        <tr>
            <td class="lbl">Total Dû</td>
            <td class="val"><?= number_format($totalDu,   2, ',', ' ') ?> MAD</td>
        </tr>
        <tr>
            <td class="lbl">Total Payé</td>
            <td class="val" style="color:#15803d;"><?= number_format($totalPaye, 2, ',', ' ') ?> MAD</td>
        </tr>
        <tr class="solde-row">
            <td class="lbl" style="background:#000; color:#fff;">Solde Global (Restant dû)</td>
            <td class="val" style="background:#000; color:#fff;"><?= number_format($totalRestant, 2, ',', ' ') ?> MAD</td>
        </tr>
    </table>

    <!-- ══ Signature ═════════════════════════════════════════════════════════ -->
    <div class="sign-block">
        <table class="sign-table">
            <tr><th>La Direction / Comptabilité</th></tr>
            <tr><td><p style="margin:6px 8px; font-size:11px;">Fait à Berrechid le : <?= date('d/m/Y') ?></p></td></tr>
        </table>
    </div>

    <!-- ══ Footer ════════════════════════════════════════════════════════════ -->
    <div class="doc-footer">
        <?= h($SCHOOL_ADDRESS) ?><br>
        <?= h($SCHOOL_LEGAL) ?>
    </div>

</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
