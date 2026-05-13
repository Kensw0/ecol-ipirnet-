<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School constants (same source of truth as the certificat) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_CITY         = 'Berrechid';
$SCHOOL_ADDRESS      = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL        = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
$rows = $pdo->prepare('SELECT mois_ref, est_paye, marque_le FROM mensualites WHERE id_stagiaire=? ORDER BY mois_ref DESC LIMIT 36');
$rows->execute([$id]);
$hist = $rows->fetchAll();
$nbPaye = 0;
$nbImpaye = 0;
foreach ($hist as $r) {
    if ((int) $r['est_paye'] === 1) {
        $nbPaye++;
    } else {
        $nbImpaye++;
    }
}
log_document_gen($pdo, 'etat_mensualites', $id, (string) $s['matricule']);

// Numbering like the cert: "01/25-26".
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='etat_mensualites'")->fetchColumn();
$annee = (string) ($s['annee_scolaire'] ?? '');
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$etatNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$nomComplet = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$classe     = (string) ($s['nom_classe'] ?? '');

$fmtMois = static function (string $m): string {
    if (preg_match('/^(\d{4})-(\d{2})$/', $m, $mm)) {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $idx = (int) $mm[2];
        if (isset($months[$idx])) {
            return $months[$idx] . ' ' . $mm[1];
        }
    }
    return $m;
};

$fmtDt = static function (?string $d): string {
    if ($d === null || $d === '') return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d/m/Y H:i', $t);
};

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>État des Cotisations Mensuelles — <?= h($nomComplet) ?></title>
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

        /* ===== Letterhead 3-column (same as certificat / relevé) ===== */
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

        /* ===== Title in oval (same as certificat / relevé) ===== */
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

        /* ===== Body fields (same as certificat / relevé) ===== */
        .cs-body { padding: 0 8px; }
        .cs-fields { width: 100%; border-collapse: collapse; margin: 4px 0 14px; }
        .cs-fields td { padding: 4px 6px; font-size: 12pt; line-height: 1.5; vertical-align: top; }
        .cs-fields td:first-child { width: 32%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }

        /* ===== Cotisation detail table — black, neat, no colour blocks ===== */
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
        .ep-table td.mois, .ep-table th.mois { width: 30%; }
        .ep-table td.statut, .ep-table th.statut { width: 22%; text-align: center; font-weight: 700; }
        .ep-table td.date, .ep-table th.date { width: 30%; text-align: center; }
        .ep-table tfoot th { background: #f4f4f5; font-weight: 700; text-align: right; }
        .ep-table tfoot th.num { text-align: center; }
        .ep-empty { text-align: center; font-style: italic; padding: 14px; }

        /* ===== Signature single box (same as relevé) ===== */
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
        .cs-sign td { height: 140px; font-size: 11.5pt; }
        .cs-sign .cs-script {
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            text-align: center;
            margin-top: 60px;
            font-size: 1.15rem;
            color: #111;
        }

        /* ===== Footer (same as certificat / relevé) ===== */
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
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="documents_officiels.php?id=<?= $id ?>">Retour</a>
    </div>

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
                <div class="cs-stamp">ACCREDITÉ</div>
            </td>
        </tr>
    </table>

    <div class="cs-title-wrap">
        <div class="cs-title-oval">
            État des Cotisations Mensuelles
            <span class="cs-num">N° <?= h($etatNum) ?></span>
        </div>
    </div>

    <p class="cs-year">Récapitulatif sur les 36 derniers mois — Année scolaire <?= h($annee) ?></p>

    <div class="cs-body">
        <table class="cs-fields">
            <tr>
                <td>Nom et prénom</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($nomComplet) ?></strong></td>
            </tr>
            <tr>
                <td>Matricule</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h((string) $s['matricule']) ?></strong></td>
            </tr>
            <tr>
                <td>Classe</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($classe) ?></strong></td>
            </tr>
            <tr>
                <td>Mois payés</td>
                <td class="cs-sep">:</td>
                <td><strong><?= (int) $nbPaye ?></strong></td>
            </tr>
            <tr>
                <td>Mois non payés</td>
                <td class="cs-sep">:</td>
                <td><strong><?= (int) $nbImpaye ?></strong></td>
            </tr>
        </table>
    </div>

    <table class="ep-table">
        <thead>
            <tr>
                <th class="mois">Mois</th>
                <th class="statut">Statut</th>
                <th class="date">Marqué le</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$hist): ?>
                <tr><td colspan="3" class="ep-empty">Aucun enregistrement de cotisation.</td></tr>
            <?php else: ?>
                <?php foreach ($hist as $e): ?>
                    <tr>
                        <td class="mois"><?= h($fmtMois((string) $e['mois_ref'])) ?></td>
                        <td class="statut"><?= (int) $e['est_paye'] === 1 ? 'Payé' : 'Non payé' ?></td>
                        <td class="date"><?= h($fmtDt((string) ($e['marque_le'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if ($hist): ?>
        <tfoot>
            <tr>
                <th class="mois">Total</th>
                <th class="num"><?= (int) ($nbPaye + $nbImpaye) ?> mois</th>
                <th class="date"><?= (int) $nbPaye ?> payés / <?= (int) $nbImpaye ?> non payés</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <table class="cs-sign">
        <tr>
            <th>Caissière / Direction</th>
        </tr>
        <tr>
            <td>
                <p>Fait à <?= h($SCHOOL_CITY) ?> le&nbsp;: <?= h(date('d/m/Y')) ?></p>
                <p class="cs-script">Signature</p>
            </td>
        </tr>
    </table>

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
