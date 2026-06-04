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

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT a.*, s.nom, s.prenom, s.num_inscri, c.nom_classe, c.annee_scolaire, f.nom_filiere
       FROM absences a
       JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
       JOIN classes c    ON c.id_classe   = s.id_classe
       JOIN filieres f   ON f.id_filiere  = c.id_filiere
      WHERE a.id_absence = ?'
);
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    http_response_code(404);
    exit('Absence introuvable');
}
log_document_gen($pdo, 'billet_excuse', (int) $a['id_stagiaire'], 'ABS-' . $id);

// Numbering like the cert: "01/25-26".
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='billet_excuse'")->fetchColumn();
$annee = (string) ($a['annee_scolaire'] ?? '');
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$billetNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$nomComplet = trim((string) $a['nom'] . ' ' . (string) $a['prenom']);
$classe     = (string) ($a['nom_classe'] ?? '');
$dateAbs    = (string) ($a['date_absence'] ?? '');
if ($dateAbs && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateAbs, $mm)) {
    $dateAbs = sprintf('%s/%s/%s', $mm[3], $mm[2], $mm[1]);
}
$horaire = 'journée';
if (!empty($a['heure_debut'])) {
    $horaire = substr((string) $a['heure_debut'], 0, 5) . ' – ' . substr((string) ($a['heure_fin'] ?? ''), 0, 5);
}
$motif   = trim((string) ($a['justificatif'] ?? ''));
if ($motif === '') {
    $motif = '—';
}
$justifie = (int) ($a['est_justifiee'] ?? 0) === 1;

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billet d'Excuse — <?= h($nomComplet) ?></title>
    <style>
        /* Small paper format (palm-sized: A6 portrait, 105 x 148 mm). */
        @page { size: A6 portrait; margin: 6mm; }
        * { box-sizing: border-box; }
        html, body { background: #eef0f3; }
        body {
            margin: 0;
            padding: 14px 0 24px;
            font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
            color: #111;
            font-size: 8.5pt;
        }
        .cs-doc {
            width: 105mm;
            min-height: 148mm;
            margin: 0 auto;
            background: #fff;
            padding: 5mm 6mm 4mm;
            box-shadow: 0 4px 10px rgba(0,0,0,0.10);
            border: 1px solid #cdd0d4;
        }
        .cs-print-btns { text-align: center; margin-bottom: 10px; }
        .cs-print-btns button, .cs-print-btns a {
            background: #f4f4f5;
            border: 1px solid #ccc;
            padding: .3rem .7rem;
            border-radius: 8px;
            font-size: .8rem;
            cursor: pointer;
            text-decoration: none;
            color: #111;
            margin: 0 3px;
        }

        /* ===== Compact 3-column letterhead ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .cs-head td { border: 1px solid #111; padding: 3px 4px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18mm; }
        .cs-head-logo { max-width: 14mm; max-height: 14mm; display: inline-block; }
        .cs-stamp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14mm;
            height: 14mm;
            border-radius: 50%;
            border: 1.5px solid #b8860b;
            color: #b8860b;
            font-family: "Times New Roman", serif;
            font-weight: 700;
            font-size: 5.5pt;
            letter-spacing: 0.04em;
            background:
              radial-gradient(circle, #fff 52%, transparent 53%),
              repeating-conic-gradient(#b8860b 0 6deg, transparent 6deg 12deg);
            padding: 1.5mm;
            line-height: 1;
        }
        .cs-head-mid .cs-org { font-weight: 700; font-size: 9pt; letter-spacing: 0.03em; line-height: 1.1; }
        .cs-head-mid .cs-tag { font-style: italic; font-size: 6pt; line-height: 1.1; }
        .cs-head-mid .cs-auth { font-size: 5pt; line-height: 1.1; margin-top: 1px; }

        /* ===== Mini oval title ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 6px 0 4px; }
        .cs-title-oval {
            border: 1.2px solid #1a1a1a;
            border-radius: 50%;
            padding: 5px 14px;
            min-width: 70%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 11pt;
            color: #0b3b66;
            white-space: nowrap;
        }
        .cs-title-oval .cs-num {
            font-family: "Cambria", "Times New Roman", serif;
            font-style: normal;
            font-weight: 700;
            color: #111;
            font-size: 7pt;
            margin-left: 6px;
        }
        .cs-year {
            text-align: center;
            font-style: italic;
            font-size: 7pt;
            margin: 0 0 5px;
            color: #444;
        }

        /* ===== Body fields ===== */
        .cs-fields { width: 100%; border-collapse: collapse; margin: 1px 0 4px; }
        .cs-fields td { padding: 1.4px 3px; font-size: 8pt; line-height: 1.3; vertical-align: top; }
        .cs-fields td:first-child { width: 38%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 1px; }
        .cs-closing { margin: 4px 0 6px; line-height: 1.3; text-align: justify; font-size: 8pt; }

        /* ===== Signatures (3 inline boxes, very compact) ===== */
        .cs-sign {
            margin: 4px 0 4px;
            width: 100%;
            border-collapse: collapse;
        }
        .cs-sign th, .cs-sign td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
            width: 33.33%;
        }
        .cs-sign th {
            font-weight: 400;
            font-style: italic;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-size: 8pt;
            color: #0b3b66;
            text-align: center;
            background: #fafafa;
        }
        .cs-sign td { height: 14mm; font-size: 6.5pt; }

        /* ===== Footer (very small) ===== */
        .cs-footer {
            border-top: 1px solid #111;
            padding-top: 2px;
            margin: 4px 0 0;
            text-align: center;
            font-size: 5.5pt;
            line-height: 1.25;
            color: #333;
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; min-height: 0; }
            .no-print, .cs-print-btns { display: none !important; }
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="absences.php">Retour</a>
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
            Billet d'Excuse
            <span class="cs-num">N° <?= h($billetNum) ?></span>
        </div>
    </div>

    <p class="cs-year">Année scolaire <?= h($annee) ?></p>

    <table class="cs-fields">
        <tr>
            <td>Élève</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($nomComplet) ?></strong></td>
        </tr>
        <tr>
            <td>N° Inscription</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h((string) $a['num_inscri']) ?></strong></td>
        </tr>
        <tr>
            <td>Classe</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($classe) ?></strong></td>
        </tr>
        <tr>
            <td>Date de l'absence</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($dateAbs) ?></strong></td>
        </tr>
        <tr>
            <td>Horaire</td>
            <td class="cs-sep">:</td>
            <td><?= h($horaire) ?></td>
        </tr>
        <tr>
            <td>Motif / justificatif</td>
            <td class="cs-sep">:</td>
            <td><?= h($motif) ?></td>
        </tr>
        <tr>
            <td>Statut</td>
            <td class="cs-sep">:</td>
            <td><strong><?= $justifie ? 'Justifiée' : 'Non justifiée' ?></strong></td>
        </tr>
    </table>

    <p class="cs-closing">
        L'élève désigné(e) ci-dessus est autorisé(e) à réintégrer sa classe.
    </p>

    <table class="cs-sign">
        <tr>
            <th>Parent / tuteur</th>
            <th>Surveillant général</th>
            <th>Cachet</th>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>
                <p style="margin:0;">Fait à <?= h($SCHOOL_CITY) ?> le&nbsp;: <?= h(date('d/m/Y')) ?></p>
            </td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <div class="cs-footer">
        <?= h($SCHOOL_ORG) ?> — Berrechid &nbsp;•&nbsp; Document officiel généré le <?= h(date('d/m/Y H:i')) ?>.
    </div>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
