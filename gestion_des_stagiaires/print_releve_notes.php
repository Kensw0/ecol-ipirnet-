<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School / director constants (same source of truth as the certificat) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$DIRECTOR_NAME       = 'TOUIJER JILLALI';
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
log_document_gen($pdo, 'releve_notes', $id, (string) $s['matricule']);

$notes = $pdo->prepare('SELECT e.*, m.nom_module FROM evaluer e JOIN modules m ON m.id_module=e.id_module WHERE e.id_stagiaire=? ORDER BY m.nom_module, e.date_evaluation');
$notes->execute([$id]);
$rows = $notes->fetchAll();

$byModule = [];
foreach ($rows as $r) {
    $mname = gds_module_label((string) $r['nom_module']);
    $byModule[$mname][] = $r;
}

$moyMod = [];
foreach ($byModule as $mname => $list) {
    $sum = 0.0;
    $c = 0;
    foreach ($list as $r) {
        $sum += (float) $r['valeur_note'];
        $c++;
    }
    $moyMod[$mname] = $c > 0 ? round($sum / $c, 2) : null;
}

$moy = $pdo->prepare('SELECT ROUND(AVG(valeur_note),2) FROM evaluer WHERE id_stagiaire=?');
$moy->execute([$id]);
$gm = $moy->fetchColumn();

// Numbering like the cert: "01/25-26".
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='releve_notes'")->fetchColumn();
$annee = (string) ($s['annee_scolaire'] ?? '');
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$relNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$nomComplet = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$classe     = (string) ($s['nom_classe'] ?? '');
$filiere    = (string) ($s['nom_filiere'] ?? '');

$fmtFr = static function (?string $d): string {
    if (!$d) return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d/m/Y', $t);
};

$fmtNote = static function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float) $v;
    return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
};

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé de Notes — <?= h($nomComplet) ?></title>
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

        /* ===== Letterhead 3-column (same as certificat) ===== */
        .cs-head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .cs-head td {
            border: 1px solid #111;
            padding: 8px 10px;
            vertical-align: middle;
            text-align: center;
        }
        .cs-head .cs-head-left,
        .cs-head .cs-head-right { width: 18%; }
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

        /* ===== Title in oval (same as certificat) ===== */
        .cs-title-wrap {
            display: flex;
            justify-content: center;
            margin: 24px 0 18px;
        }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a;
            border-radius: 50%;
            padding: 16px 60px;
            min-width: 60%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 1.6rem;
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

        /* ===== Body fields (same as certificat) ===== */
        .cs-body { padding: 0 8px; }
        .cs-fields { width: 100%; border-collapse: collapse; margin: 4px 0 14px; }
        .cs-fields td { padding: 4px 6px; font-size: 12pt; line-height: 1.5; vertical-align: top; }
        .cs-fields td:first-child { width: 32%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }
        .cs-year {
            text-align: center;
            font-style: italic;
            font-size: 1rem;
            margin: 4px 0 14px;
            color: #444;
        }

        /* ===== Grades table — black, neat ===== */
        .rn-table {
            width: calc(100% - 8px);
            margin: 6px 4px 8px;
            border-collapse: collapse;
            font-size: 11.5pt;
        }
        .rn-table th, .rn-table td {
            border: 1px solid #111;
            padding: 6px 9px;
            text-align: left;
            vertical-align: middle;
        }
        .rn-table thead th {
            background: #f4f4f5;
            font-weight: 700;
            text-align: center;
        }
        .rn-table td.num, .rn-table th.num { text-align: center; width: 70px; }
        .rn-table td.type, .rn-table th.type { text-align: center; width: 110px; }
        .rn-table td.date, .rn-table th.date { text-align: center; width: 110px; }
        .rn-table tbody tr.module-head td {
            background: #fafafa;
            font-weight: 700;
        }
        .rn-table tfoot th {
            background: #f4f4f5;
            text-align: right;
            font-weight: 700;
        }
        .rn-table tfoot th.note { text-align: center; font-size: 12.5pt; }

        /* ===== Signature single box ===== */
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

        /* ===== Footer (same as certificat) ===== */
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
            Relevé de Notes
            <span class="cs-num">N° <?= h($relNum) ?></span>
        </div>
    </div>

    <p class="cs-year">Année scolaire <?= h($annee) ?></p>

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
                <td>Filière</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($filiere) ?></strong></td>
            </tr>
        </table>
    </div>

    <table class="rn-table">
        <thead>
            <tr>
                <th>Module</th>
                <th class="type">Type</th>
                <th class="date">Date</th>
                <th class="num">Note</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$byModule): ?>
                <tr><td colspan="4" style="text-align:center; font-style:italic;">Aucune note enregistrée.</td></tr>
            <?php else: ?>
                <?php foreach ($byModule as $mname => $list): ?>
                    <?php foreach ($list as $i => $r): ?>
                        <tr>
                            <td><?= $i === 0 ? h($mname) : '' ?></td>
                            <td class="type"><?= h((string) $r['type_evaluation']) ?></td>
                            <td class="date"><?= h($fmtFr((string) $r['date_evaluation'])) ?></td>
                            <td class="num"><?= h($fmtNote($r['valeur_note'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if ($byModule): ?>
        <tfoot>
            <tr>
                <th colspan="3">Moyenne générale</th>
                <th class="note"><?= h($fmtNote($gm)) ?></th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <table class="cs-sign">
        <tr>
            <th>Le Directeur Pédagogique</th>
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
