<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ── School constants (same source of truth as all other print pages) ─────────
$SCHOOL_ORG         = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_CITY        = 'Berrechid';
$SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL       = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

// ── Student lookup (accepts ?id_stagiaire= or ?id=) ──────────────────────────
$id = (int)($_GET['id_stagiaire'] ?? $_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Identifiant manquant.'); }

$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire = ?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); exit('Stagiaire introuvable.'); }

// ── Fetch remise_mensuelle ────────────────────────────────────────────────────
$stRem = $pdo->prepare('SELECT COALESCE(remise_mensuelle, 0) FROM stagiaires WHERE id_stagiaire = ?');
$stRem->execute([$id]);
$remiseMensuelle = max(0.0, (float)($stRem->fetchColumn() ?: 0));

// ── Filière tarif ─────────────────────────────────────────────────────────────
$tarifsDefaut        = [2 => 700.0, 3 => 600.0, 4 => 800.0];
$idFiliere           = (int)($s['id_filiere'] ?? 0);
$tarifStd            = $tarifsDefaut[$idFiliere] ?? 700.0;
$tarifEffectifBase   = max(0.0, $tarifStd - $remiseMensuelle);

// ── Build Sept–Jun month list from annee_scolaire ────────────────────────────
$annee  = (string)($s['annee_scolaire'] ?? '');
$parts  = explode('/', $annee);
$year1  = (int)($parts[0] ?? date('Y'));
$year2  = (int)($parts[1] ?? ($year1 + 1));

$moisList = [
    sprintf('%04d-09', $year1), sprintf('%04d-10', $year1),
    sprintf('%04d-11', $year1), sprintf('%04d-12', $year1),
    sprintf('%04d-01', $year2), sprintf('%04d-02', $year2),
    sprintf('%04d-03', $year2), sprintf('%04d-04', $year2),
    sprintf('%04d-05', $year2), sprintf('%04d-06', $year2),
];

// ── Fetch mensualites records ─────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($moisList), '?'));
$stMens = $pdo->prepare(
    "SELECT mois_ref, montant_total, montant_paye, montant_restant, remise, statut_paiement, date_paiement, marque_le
     FROM mensualites
     WHERE id_stagiaire = ? AND mois_ref IN ($placeholders)"
);
$stMens->execute(array_merge([$id], $moisList));
$records = [];
foreach ($stMens->fetchAll() as $r) { $records[$r['mois_ref']] = $r; }

// ── Month labels ──────────────────────────────────────────────────────────────
$moisLabels = [
    '01' => 'Janvier',  '02' => 'Février',   '03' => 'Mars',
    '04' => 'Avril',    '05' => 'Mai',        '06' => 'Juin',
    '07' => 'Juillet',  '08' => 'Août',       '09' => 'Septembre',
    '10' => 'Octobre',  '11' => 'Novembre',   '12' => 'Décembre',
];

// ── Build rows + totals ───────────────────────────────────────────────────────
$rows         = [];
$totalDu      = 0.0;
$totalPaye    = 0.0;
$totalRestant = 0.0;
$nbPaye = $nbPartiel = $nbImpaye = 0;

foreach ($moisList as $m) {
    $r = $records[$m] ?? null;
    [$yyyy, $mm] = explode('-', $m);
    $label = ($moisLabels[$mm] ?? $mm) . ' ' . $yyyy;

    if ($r) {
        $eRemise = max(0.0, (float)($r['remise'] ?? 0));
        if ($eRemise === 0.0) $eRemise = $remiseMensuelle;
        $du      = max(0.0, (float)$r['montant_total'] - $eRemise);
        $paye    = max(0.0, (float)($r['montant_paye']    ?? 0));
        $restant = max(0.0, (float)($r['montant_restant'] ?? 0));
        $statut  = strtolower(trim((string)($r['statut_paiement'] ?? 'impayé')));
        $datePaie = !empty($r['date_paiement']) ? $r['date_paiement']
                  : (!empty($r['marque_le'])    ? $r['marque_le'] : null);
    } else {
        $du       = $tarifEffectifBase;
        $paye     = 0.0;
        $restant  = $du;
        $statut   = 'impayé';
        $datePaie = null;
    }

    match($statut) {
        'payé', 'paye' => ($nbPaye++),
        'partiel'      => ($nbPartiel++),
        default        => ($nbImpaye++),
    };

    $totalDu      += $du;
    $totalPaye    += $paye;
    $totalRestant += $restant;

    $rows[] = compact('label', 'du', 'paye', 'restant', 'statut', 'datePaie');
}

// ── Document numbering (same pattern as reçu / état) ──────────────────────────
log_document_gen($pdo, 'etat_paiements_annuel', $id, (string)$s['num_inscri']);
$seq = (int)$pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='etat_paiements_annuel'")->fetchColumn();
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $ma)) {
    $shortAnnee = substr($ma[1], -2) . '-' . substr($ma[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $ma)) {
    $y = (int)$ma[1];
    $shortAnnee = substr((string)$y, -2) . '-' . substr((string)($y + 1), -2);
}
$docNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$auto       = isset($_GET['auto']) && $_GET['auto'] === '1';
$nomComplet = trim((string)$s['nom'] . ' ' . (string)$s['prenom']);
$classe     = (string)($s['nom_classe']  ?? '');
$filiere    = gds_fix_text((string)($s['nom_filiere'] ?? ''));

$fmtDt = static function (?string $d): string {
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

        /* ===== Letterhead 3-column (identical to reçu / état) ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 90px; max-height: 90px; display: inline-block; }
        .cs-stamp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px; height: 88px;
            border-radius: 50%;
            border: 2px solid #b8860b;
            color: #b8860b;
            font-family: "Times New Roman", serif;
            font-weight: 700;
            font-size: .95rem;
            letter-spacing: 0.05em;
            background:
              radial-gradient(circle, #fff 55%, transparent 56%),
              repeating-conic-gradient(#b8860b 0 6deg, transparent 6deg 12deg);
            padding: 4px;
        }
        .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag  { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* ===== Oval title (identical to reçu / état) ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 16px; }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a;
            border-radius: 50%;
            padding: 14px 60px;
            min-width: 55%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 1.55rem;
            color: #0b3b66;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .cs-title-oval .cs-num {
            font-family: "Cambria", "Times New Roman", serif;
            font-style: normal;
            font-weight: 700;
            color: #111;
            font-size: 1.05rem;
            margin-left: 18px;
        }
        .cs-year {
            text-align: center;
            font-style: italic;
            font-size: 1rem;
            margin: 2px 0 14px;
            color: #444;
        }

        /* ===== Body fields ===== */
        .cs-body { padding: 0 8px; }
        .cs-fields { width: 100%; border-collapse: collapse; margin: 4px 0 14px; }
        .cs-fields td { padding: 4px 6px; font-size: 12pt; line-height: 1.5; vertical-align: top; }
        .cs-fields td:first-child { width: 32%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }

        /* ===== Monthly table ===== */
        .ep-table {
            width: calc(100% - 8px);
            margin: 6px 4px 8px;
            border-collapse: collapse;
            font-size: 11.5pt;
        }
        .ep-table th, .ep-table td {
            border: 1px solid #111;
            padding: 6px 10px;
            text-align: left;
            vertical-align: middle;
        }
        .ep-table thead th {
            background: #f4f4f5;
            font-weight: 700;
            text-align: center;
        }
        .ep-table td.mois  { width: 24%; }
        .ep-table td.amt, .ep-table th.amt   { width: 15%; text-align: right; }
        .ep-table td.statut, .ep-table th.statut { width: 16%; text-align: center; font-weight: 700; }
        .ep-table td.date, .ep-table th.date     { width: 14%; text-align: center; }
        .ep-table tfoot th { background: #f4f4f5; font-weight: 700; text-align: right; }
        .ep-table tfoot th.lbl { text-align: left; }

        /* ===== Closing sentence ===== */
        .cs-closing { margin: 10px 4px 6px; line-height: 1.6; text-align: justify; font-size: 11pt; font-style: italic; }

        /* ===== Signature single box (same as état des cotisations) ===== */
        .cs-sign {
            margin: 22px 4px 18px auto;
            width: 280px;
            border-collapse: collapse;
        }
        .cs-sign th, .cs-sign td {
            border: 1px solid #111;
            padding: 12px 14px;
            vertical-align: top;
        }
        .cs-sign th {
            font-weight: 400;
            font-style: italic;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-size: 1.2rem;
            color: #0b3b66;
            text-align: center;
            background: #fafafa;
        }
        .cs-sign td { height: 130px; font-size: 11.5pt; }
        .cs-sign .cs-script {
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            text-align: center;
            margin-top: 52px;
            font-size: 1.15rem;
            color: #111;
        }

        /* ===== Footer ===== */
        .cs-footer {
            border-top: 1px solid #111;
            padding-top: 6px;
            margin: 18px 6px 0;
            text-align: center;
            font-size: .82rem;
            line-height: 1.45;
        }

        /* ===== Print rules ===== */
        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
            .no-print, .cs-print-btns { display: none !important; }
            .ep-table thead th { background: #f4f4f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ep-table tfoot th { background: #f4f4f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cs-sign th         { background: #fafafa  !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="stagiaires.php?id=<?= $id ?>">Retour au dossier</a>
        <a href="cotisations.php">Cotisations</a>
    </div>

    <!-- ══ Letterhead ════════════════════════════════════════════════════════ -->
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
                <div class="cs-stamp">ACCRÉDITÉ</div>
            </td>
        </tr>
    </table>

    <!-- ══ Title oval ════════════════════════════════════════════════════════ -->
    <div class="cs-title-wrap">
        <div class="cs-title-oval">
            État des Paiements Annuels
            <span class="cs-num">N° <?= h($docNum) ?></span>
        </div>
    </div>
    <p class="cs-year">Année scolaire <?= h($annee) ?> (Septembre – Juin) — Édité le <?= date('d/m/Y') ?></p>

    <!-- ══ Student fields ════════════════════════════════════════════════════ -->
    <div class="cs-body">
        <table class="cs-fields">
            <tr>
                <td>Nom et prénom</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($nomComplet) ?></strong></td>
            </tr>
            <tr>
                <td>N° Inscription</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h((string)($s['num_inscri'] ?? '')) ?></strong></td>
            </tr>
            <tr>
                <td>Filière</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($filiere) ?></strong></td>
            </tr>
            <tr>
                <td>Classe</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($classe) ?></strong></td>
            </tr>
            <tr>
                <td>Mois payés / partiels / impayés</td>
                <td class="cs-sep">:</td>
                <td>
                    <strong style="color:#1a7a1a;"><?= $nbPaye ?> payé<?= $nbPaye > 1 ? 's' : '' ?></strong>
                    <?php if ($nbPartiel > 0): ?> / <strong style="color:#b45309;"><?= $nbPartiel ?> partiel<?= $nbPartiel > 1 ? 's' : '' ?></strong><?php endif; ?>
                    / <strong style="color:#b91c1c;"><?= $nbImpaye ?> impayé<?= $nbImpaye > 1 ? 's' : '' ?></strong>
                </td>
            </tr>
            <?php if ($remiseMensuelle > 0): ?>
            <tr>
                <td>Remise mensuelle</td>
                <td class="cs-sep">:</td>
                <td><strong style="color:#1a7a1a;">- <?= number_format($remiseMensuelle, 2, ',', ' ') ?> MAD / mois</strong></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- ══ Monthly payment table ═════════════════════════════════════════════ -->
    <table class="ep-table">
        <thead>
            <tr>
                <th class="mois">Mois</th>
                <th class="amt">Dû (MAD)</th>
                <th class="amt">Payé (MAD)</th>
                <th class="amt">Restant (MAD)</th>
                <th class="statut">Statut</th>
                <th class="date">Date paiement</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $sp = $row['statut'];
                if ($sp === 'payé' || $sp === 'paye') {
                    $statLabel = 'Payé ✔';
                    $statStyle = 'color:#1a7a1a; font-weight:700;';
                } elseif ($sp === 'partiel') {
                    $statLabel = 'Partiel ⚠';
                    $statStyle = 'color:#b45309; font-weight:700;';
                } else {
                    $statLabel = 'Impayé ✘';
                    $statStyle = 'color:#b91c1c; font-weight:700;';
                }
                $restStyle = $row['restant'] > 0 ? 'color:#b91c1c;' : '';
            ?>
            <tr>
                <td class="mois"><?= h($row['label']) ?></td>
                <td class="amt"><?= number_format($row['du'],      2, ',', ' ') ?></td>
                <td class="amt"><?= number_format($row['paye'],    2, ',', ' ') ?></td>
                <td class="amt" style="<?= $restStyle ?>"><?= number_format($row['restant'], 2, ',', ' ') ?></td>
                <td class="statut" style="<?= $statStyle ?>"><?= $statLabel ?></td>
                <td class="date" style="font-size:10.5pt;"><?= h($fmtDt($row['datePaie'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="lbl">Totaux</th>
                <th class="amt" style="text-align:right;"><?= number_format($totalDu,   2, ',', ' ') ?></th>
                <th class="amt" style="text-align:right;"><?= number_format($totalPaye, 2, ',', ' ') ?></th>
                <th class="amt" style="text-align:right; <?= $totalRestant > 0 ? 'color:#b91c1c;' : '' ?>"><?= number_format($totalRestant, 2, ',', ' ') ?></th>
                <th colspan="2"></th>
            </tr>
            <?php if ($totalRestant > 0): ?>
            <tr>
                <th colspan="3" style="text-align:right; font-style:italic;">Solde restant dû</th>
                <th class="amt" style="text-align:right; color:#b91c1c;"><?= number_format($totalRestant, 2, ',', ' ') ?> MAD</th>
                <th colspan="2"></th>
            </tr>
            <?php else: ?>
            <tr>
                <th colspan="6" style="text-align:center; color:#1a7a1a; font-style:italic;">✔ Solde soldé — aucun restant dû</th>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <!-- ══ Closing sentence ══════════════════════════════════════════════════ -->
    <p class="cs-closing">
        Ce document est établi à la demande de l'intéressé(e) et lui est délivré pour faire valoir ce que de droit.
    </p>

    <!-- ══ Signature ═════════════════════════════════════════════════════════ -->
    <table class="cs-sign">
        <tr><th>Caissière / Direction</th></tr>
        <tr>
            <td>
                <p>Fait à <?= h($SCHOOL_CITY) ?> le&nbsp;: <?= date('d/m/Y') ?></p>
                <p class="cs-script">Signature</p>
            </td>
        </tr>
    </table>

    <!-- ══ Footer ════════════════════════════════════════════════════════════ -->
    <div class="cs-footer">
        <?= h($SCHOOL_ADDRESS) ?><br>
        <?= h($SCHOOL_LEGAL) ?>
    </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
