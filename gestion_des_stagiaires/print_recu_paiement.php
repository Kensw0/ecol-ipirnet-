<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School constants (same source of truth as the other 4.1 documents) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_CITY         = 'Berrechid';
$SCHOOL_ADDRESS      = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL        = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

$id   = (int) ($_GET['id'] ?? 0);
$mois = (string) ($_GET['mois'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $mois = date('Y-m');
}
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
$men = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire=? AND mois_ref=?');
$men->execute([$id, $mois]);
$m = $men->fetch();
$estPaye      = $m ? (int) $m['est_paye'] === 1 : false;
$marqueLe     = $m['marque_le'] ?? null;
$mode         = trim((string) ($_GET['mode'] ?? ''));

// ── Pull real amounts & statut from the mensualites row ──
$tarifsDefaut  = [2 => 700.0, 3 => 600.0, 4 => 800.0];
$idFiliere     = (int)($s['id_filiere'] ?? 0);
$tarifStd      = $tarifsDefaut[$idFiliere] ?? 700.0;
// Remise: per-payment remise from mensualites, or student's global remise_mensuelle
$remisePmt = $m ? max(0.0, (float)($m['remise'] ?? 0)) : 0.0;
if ($remisePmt === 0.0) {
    // Fall back to student's global monthly discount
    $stRem = $pdo->prepare('SELECT COALESCE(remise_mensuelle, 0) FROM stagiaires WHERE id_stagiaire = ?');
    $stRem->execute([$id]);
    $remisePmt = max(0.0, (float)($stRem->fetchColumn() ?: 0));
}
$tarifBrut     = $m && $m['montant_total'] !== null ? (float)$m['montant_total'] : $tarifStd;
$tarifEffectif = max(0.0, $tarifBrut - $remisePmt);
$montantTotal  = number_format($tarifEffectif, 2, '.', ' ');
$montantPaye   = $m && $m['montant_paye']    !== null ? number_format((float)$m['montant_paye'],    2, '.', ' ') : '—';
$montantRestant= $m && $m['montant_restant'] !== null ? number_format((float)$m['montant_restant'], 2, '.', ' ') : '—';
$statutPay     = $m ? (string)($m['statut_paiement'] ?? '') : '';
if ($statutPay === '') { $statutPay = $estPaye ? 'payé' : 'impayé'; }
$statutLabel   = match(strtolower($statutPay)) {
    'payé', 'paye'   => 'PAYÉ ✔',
    'partiel'        => 'PARTIEL ⚠',
    default          => 'NON PAYÉ ✘',
};
$datePaiement  = ($m && !empty($m['date_paiement'])) ? date('d/m/Y', strtotime($m['date_paiement'])) : '—';
$montant       = trim((string) ($_GET['montant'] ?? $montantPaye));

log_document_gen($pdo, 'recu_paiement', $id, (string) $s['num_inscri'] . '-' . $mois);

// Friendly month label.
$moisAff = $mois;
$dt = DateTime::createFromFormat('Y-m', $mois);
if ($dt) {
    $months  = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $moisAff = $months[(int) $dt->format('n')] . ' ' . $dt->format('Y');
}

// Numbering like the cert: "01/25-26".
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='recu_paiement'")->fetchColumn();
$annee = (string) ($s['annee_scolaire'] ?? '');
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$recuNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$nomComplet = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$classe     = (string) ($s['nom_classe'] ?? '');
$filiere    = gds_fix_text((string) ($s['nom_filiere'] ?? ''));

$dateEnc = '—';
if ($marqueLe !== null && $marqueLe !== '') {
    $t = strtotime((string) $marqueLe);
    $dateEnc = $t !== false ? date('d/m/Y H:i', $t) : (string) $marqueLe;
} elseif ($estPaye) {
    $dateEnc = date('d/m/Y H:i');
}

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu de Paiement — <?= h($nomComplet) ?> — <?= h($mois) ?></title>
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

        /* ===== Letterhead 3-column ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 90px; max-height: 90px; display: inline-block; }
        .cs-stamp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
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
        .cs-head-mid .cs-org { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* ===== Title in oval ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 14px; }
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

        /* ===== Body ===== */
        .cs-body { padding: 0 8px; }
        .cs-fields { width: 100%; border-collapse: collapse; margin: 4px 0 14px; }
        .cs-fields td { padding: 4px 6px; font-size: 12pt; line-height: 1.5; vertical-align: top; }
        .cs-fields td:first-child { width: 32%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }
        .cs-closing { margin: 10px 0 6px; line-height: 1.6; text-align: justify; }

        /* ===== Détail box (clean black border, no color blocks) ===== */
        .cs-detail {
            border-collapse: collapse;
            width: 100%;
            margin: 4px 0 16px;
            font-size: 11.5pt;
        }
        .cs-detail caption {
            caption-side: top;
            text-align: left;
            font-weight: 700;
            padding: 4px 0 6px;
            color: #0b3b66;
            letter-spacing: 0.02em;
        }
        .cs-detail th, .cs-detail td {
            border: 1px solid #111;
            padding: 7px 12px;
            vertical-align: top;
        }
        .cs-detail th {
            width: 32%;
            background: #f4f4f5;
            font-weight: 700;
            text-align: left;
        }

        /* ===== Signatures (3 boxes: stagiaire / caissière / cachet) ===== */
        .cs-sign {
            margin: 18px 0 8px;
            width: 100%;
            border-collapse: collapse;
        }
        .cs-sign th, .cs-sign td {
            border: 1px solid #111;
            padding: 12px 14px;
            vertical-align: top;
            width: 33.33%;
        }
        .cs-sign th {
            font-weight: 400;
            font-style: italic;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-size: 1.15rem;
            color: #0b3b66;
            text-align: center;
            background: #fafafa;
        }
        .cs-sign td { height: 110px; font-size: 11.5pt; }

        /* ===== Footer ===== */
        .cs-footer {
            border-top: 1px solid #111;
            padding-top: 6px;
            margin: 18px 6px 0;
            text-align: center;
            font-size: .82rem;
            line-height: 1.45;
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
            .no-print, .cs-print-btns { display: none !important; }
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="stagiaires.php">Retour</a>
    </div>

    <?php require __DIR__ . '/includes/print_letterhead.php'; ?>

    <div class="cs-title-wrap">
        <div class="cs-title-oval">
            Reçu de Paiement
            <span class="cs-num">N° <?= h($recuNum) ?></span>
        </div>
    </div>

    <p class="cs-year">Cotisation mensuelle — <?= h($moisAff) ?></p>

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
                <td><strong><?= h((string) $s['num_inscri']) ?></strong></td>
            </tr>
            <tr>
                <td>Classe</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($classe) ?></strong></td>
            </tr>
            <tr>
                <td>Filière</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($filiere) ?></strong></td>
            </tr>
        </table>

        <table class="cs-detail">
            <caption>Détail du règlement</caption>
            <tr>
                <th>Mois concerné</th>
                <td><?= h($moisAff) ?></td>
            </tr>
            <tr>
                <th>Statut</th>
                <td><strong><?= h($statutLabel) ?></strong></td>
            </tr>
            <?php if ($remisePmt > 0): ?>
            <tr>
                <th>Tarif standard</th>
                <td><?= number_format($tarifBrut, 2, '.', ' ') ?> MAD</td>
            </tr>
            <tr>
                <th>Réduction accordée</th>
                <td style="color:#1a7a1a; font-weight:700;">- <?= number_format($remisePmt, 2, '.', ' ') ?> MAD</td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Montant mensuel dû</th>
                <td><strong><?= h($montantTotal) ?> MAD</strong></td>
            </tr>
            <tr>
                <th>Montant payé</th>
                <td><?= h($montantPaye) ?> MAD</td>
            </tr>
            <tr>
                <th>Reste à payer</th>
                <td><?= h($montantRestant) ?> MAD</td>
            </tr>
            <tr>
                <th>Mode de règlement</th>
                <td><?= $mode !== '' ? h($mode) : '<em>espèces / chèque / virement</em>' ?></td>
            </tr>
            <tr>
                <th>Date de paiement</th>
                <td><?= h($datePaiement) ?></td>
            </tr>
            <tr>
                <th>Date d'enregistrement</th>
                <td><?= h($dateEnc) ?></td>
            </tr>
        </table>

        <p class="cs-closing">
            Ce reçu est délivré au stagiaire désigné ci-dessus pour faire valoir ce que de droit.
        </p>
    </div>

    <table class="cs-sign">
        <tr>
            <th>Signature du stagiaire</th>
            <th>Caissière / Direction</th>
            <th>Cachet</th>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>
                <p>Fait à <?= h($SCHOOL_CITY) ?> le&nbsp;: <?= h(date('d/m/Y')) ?></p>
            </td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <?php require __DIR__ . '/includes/print_footer.php'; ?>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>

