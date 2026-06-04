<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School / director constants (edit here to update across all certificates) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$DIRECTOR_NAME       = 'TOUIJER JILLALI';
$SCHOOL_CITY         = 'Berrechid';
$ADMIN_CITY          = 'Settat';
$SCHOOL_ADDRESS      = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL        = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";
$SCHOOL_AUTH_NUMBER  = '3/03/2/2003   Du: 19/02/2003';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
log_document_gen($pdo, 'certificat_scolarite', $id, (string) $s['num_inscri']);

// Derive a "01/22-23" style certificate number from the academic year + a sequence count.
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='certificat_scolarite'")->fetchColumn();
$annee = (string) $s['annee_scolaire'];
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$certNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

// Gender-aware wording.
$sexe = strtoupper((string) ($s['sexe'] ?? ''));
$isFem = ($sexe === 'F' || $sexe === 'FEMME' || $sexe === 'FEMININ');
$civilite = $isFem ? 'Mademoiselle' : 'Monsieur';
$accord1  = $isFem ? 'inscrite'   : 'inscrit';
$accord2  = $isFem ? "l'intéressée" : "l'intéressé";

$dateNaiss   = $s['date_naissance']   ?? '';
$dateInsc    = $s['date_inscription'] ?? '';
$inscriptionN = $s['num_inscri'] ?? '';
$nomComplet  = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$classe      = (string) ($s['nom_classe'] ?? '');
$filiere     = (string) ($s['nom_filiere'] ?? '');

$fmtFr = static function (?string $d): string {
    if (!$d) return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d/m/Y', $t);
};

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificat de Scolarité — <?= h($nomComplet) ?></title>
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

        /* ===== Letterhead 3-column ===== */
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
        .cs-head-logo {
            max-width: 90px;
            max-height: 90px;
            display: inline-block;
        }
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
        .cs-head-mid .cs-org {
            font-weight: 700;
            font-size: 1.6rem;
            letter-spacing: 0.03em;
        }
        .cs-head-mid .cs-tag {
            font-style: italic;
            font-size: .95rem;
            margin-top: 2px;
        }
        .cs-head-mid .cs-auth {
            font-size: .8rem;
            margin-top: 4px;
        }

        /* ===== Title in oval ===== */
        .cs-title-wrap {
            display: flex;
            justify-content: center;
            margin: 30px 0 20px;
        }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a;
            border-radius: 50%;
            padding: 18px 70px;
            min-width: 60%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 1.65rem;
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

        /* ===== Body ===== */
        .cs-body { padding: 0 14px; }
        .cs-body p { margin: 6px 0; line-height: 1.55; }
        .cs-body .cs-intro { margin-bottom: 12px; }
        .cs-body strong { font-weight: 700; }

        .cs-fields {
            width: 100%;
            margin: 8px 0 14px;
            border-collapse: collapse;
        }
        .cs-fields td {
            padding: 4px 6px;
            font-size: 12pt;
            line-height: 1.5;
            vertical-align: top;
        }
        .cs-fields td:first-child {
            width: 32%;
            white-space: nowrap;
        }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }

        /* ===== Signature 2-column box ===== */
        .cs-sign {
            margin: 22px 14px 18px;
            width: calc(100% - 28px);
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
        .cs-sign td { height: 280px; font-size: 11.5pt; }
        .cs-sign td p { margin: 6px 0; line-height: 1.5; }
        .cs-sign .cs-sign-label {
            font-weight: 700;
            text-decoration: underline;
        }
        .cs-sign .cs-script {
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            text-align: center;
            margin-top: 80px;
            font-size: 1.15rem;
            color: #111;
        }
        .cs-sign .cs-dots { letter-spacing: 0.04em; }

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
            .cs-doc {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
                max-width: none;
            }
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
            Certificat de Scolarité
            <span class="cs-num">N° <?= h($certNum) ?></span>
        </div>
    </div>

    <div class="cs-body">
        <p class="cs-intro">Je soussigné&nbsp;:&nbsp;&nbsp;<strong><?= h($DIRECTOR_NAME) ?></strong></p>
        <p class="cs-intro">Directeur de l'Etablissement&nbsp;<strong><?= h($SCHOOL_ORG) ?></strong></p>

        <p>Certifie que <?= h($civilite) ?>&nbsp;: <strong><?= h($nomComplet) ?></strong></p>

        <table class="cs-fields">
            <tr>
                <td>Née le</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($fmtFr($dateNaiss)) ?></strong></td>
            </tr>
            <tr>
                <td>Est <?= h($accord1) ?> le</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($fmtFr($dateInsc)) ?></strong>. Sous N° <strong><?= h((string) $inscriptionN) ?></strong></td>
            </tr>
            <tr>
                <td>Poursuit sa formation en classe</td>
                <td class="cs-sep">&nbsp;</td>
                <td><strong><?= h($classe) ?></strong></td>
            </tr>
            <tr>
                <td>Pour une durée de</td>
                <td class="cs-sep">:</td>
                <td><strong>Une année</strong></td>
            </tr>
            <tr>
                <td>Filière de Formation</td>
                <td class="cs-sep">:</td>
                <td><strong><?= h($filiere) ?></strong></td>
            </tr>
            <tr>
                <td>Niveau de formation</td>
                <td class="cs-sep">:</td>
                <td><strong>Qualification</strong></td>
            </tr>
        </table>

        <p>La présente Attestation est délivrée à <?= h($accord2) ?> pour servir et valoir ce que de droit&nbsp;:</p>
    </div>

    <table class="cs-sign">
        <tr>
            <th>Le Directeur de l'Etablissement</th>
            <th>Visa de l'Administration</th>
        </tr>
        <tr>
            <td>
                <p class="cs-sign-label">Fait à <?= h($SCHOOL_CITY) ?> le&nbsp;: <?= h(date('d/m/Y')) ?></p>
                <p class="cs-script">Signature</p>
            </td>
            <td>
                <p>Etablissement de Formation Professionnelle privé autorisé sous N°&nbsp;: <?= h($SCHOOL_AUTH_NUMBER) ?></p>
                <p>L'intéressé(e) est inscrit(e) sur la liste des stagiaires de l'Etablissement sous N°<span class="cs-dots">…………………………………………</span></p>
                <p>Année de formation&nbsp;: <span class="cs-dots">………………………………</span></p>
                <p>Fait à <?= h($ADMIN_CITY) ?>,&nbsp;&nbsp;&nbsp;Le<span class="cs-dots">………………………</span></p>
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
