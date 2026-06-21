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

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT st.*, s.nom, s.prenom, s.num_inscri, s.cin, s.date_naissance, s.adresse,
            c.nom_classe, c.annee_scolaire, f.nom_filiere
       FROM stages st
       JOIN stagiaires s ON s.id_stagiaire = st.id_stagiaire
       JOIN classes c    ON c.id_classe   = s.id_classe
       JOIN filieres f   ON f.id_filiere  = c.id_filiere
      WHERE st.id_stage = ?'
);
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    http_response_code(404);
    exit('Stage introuvable');
}
log_document_gen($pdo, 'convention_stage', (int) $t['id_stagiaire'], 'ST-' . $id);

// Numbering like the cert: "01/25-26".
$seq = (int) $pdo->query("SELECT COUNT(*) FROM documents_generes WHERE type_document='convention_stage'")->fetchColumn();
$annee = (string) ($t['annee_scolaire'] ?? '');
$shortAnnee = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $shortAnnee = substr($mm[1], -2) . '-' . substr($mm[2], -2);
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $shortAnnee = substr((string) $y, -2) . '-' . substr((string) ($y + 1), -2);
}
$convNum = sprintf('%02d/%s', max($seq, 1), $shortAnnee);

$nomComplet = trim((string) $t['nom'] . ' ' . (string) $t['prenom']);
$classe     = (string) ($t['nom_classe'] ?? '');
$filiere    = gds_fix_text((string) ($t['nom_filiere'] ?? ''));
$isPFE      = (string) ($t['type_stage'] ?? '') === 'pfe';
$typeLabel  = $isPFE ? "Projet de Fin d'Études (PFE)" : 'Stage en entreprise';

$fmtDate = static function (?string $d): string {
    if ($d === null || $d === '' || $d === '0000-00-00') return '—';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $d, $mm)) {
        return sprintf('%s/%s/%s', $mm[3], $mm[2], $mm[1]);
    }
    return $d;
};

$dateDebut       = $fmtDate((string) ($t['date_debut'] ?? ''));
$dateFin         = $fmtDate((string) ($t['date_fin'] ?? ''));
$dateSoutenance  = $fmtDate((string) ($t['date_soutenance'] ?? ''));
$sujet           = trim((string) ($t['sujet'] ?? ''));
$entreprise      = trim((string) ($t['entreprise'] ?? ''));
$jury            = trim((string) ($t['jury'] ?? ''));
$adresse         = trim((string) ($t['adresse'] ?? ''));

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convention de Stage — <?= h($nomComplet) ?></title>
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

        /* ===== Section header band ===== */
        .cs-section-title {
            margin: 14px 0 4px;
            font-weight: 700;
            font-size: 1.02rem;
            color: #0b3b66;
            letter-spacing: 0.02em;
            border-bottom: 1.5px solid #0b3b66;
            padding-bottom: 2px;
        }

        /* ===== Body fields ===== */
        .cs-fields { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
        .cs-fields td { padding: 4px 6px; font-size: 12pt; line-height: 1.5; vertical-align: top; }
        .cs-fields td:first-child { width: 32%; white-space: nowrap; }
        .cs-fields .cs-sep { width: 1px; padding: 0 2px; }

        /* ===== Engagements ===== */
        .cs-engage { margin: 10px 0 6px; line-height: 1.6; text-align: justify; }

        /* ===== Signatures: three boxes side-by-side ===== */
        .cs-sign {
            margin: 14px 0 8px;
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
        .cs-sign td { height: 130px; font-size: 11.5pt; }

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
        <a href="stagiaires.php">Retour au Hub</a>
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
                <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:80px;height:80px;object-fit:contain;border-radius:50%;">
            </td>
        </tr>
    </table>

    <div class="cs-title-wrap">
        <div class="cs-title-oval">
            Convention de Stage
            <span class="cs-num">N° <?= h($convNum) ?></span>
        </div>
    </div>

    <p class="cs-year"><?= h($typeLabel) ?> — Année scolaire <?= h($annee) ?></p>

    <h2 class="cs-section-title">Stagiaire</h2>
    <table class="cs-fields">
        <tr>
            <td>Nom et prénom</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($nomComplet) ?></strong></td>
        </tr>
        <tr>
            <td>N° Inscription</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h((string) $t['num_inscri']) ?></strong></td>
        </tr>
        <tr>
            <td>CIN</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h((string) ($t['cin'] ?? '—')) ?></strong></td>
        </tr>
        <tr>
            <td>Classe / Filière</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($classe . ' — ' . $filiere) ?></strong></td>
        </tr>
        <tr>
            <td>Adresse</td>
            <td class="cs-sep">:</td>
            <td><?= h($adresse !== '' ? $adresse : '—') ?></td>
        </tr>
    </table>

    <h2 class="cs-section-title">Organisme d'accueil</h2>
    <table class="cs-fields">
        <tr>
            <td>Entreprise / organisme</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($entreprise !== '' ? $entreprise : '—') ?></strong></td>
        </tr>
        <tr>
            <td>Sujet du stage</td>
            <td class="cs-sep">:</td>
            <td><?= h($sujet !== '' ? $sujet : '—') ?></td>
        </tr>
    </table>

    <h2 class="cs-section-title">Période</h2>
    <table class="cs-fields">
        <tr>
            <td>Date de début</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($dateDebut) ?></strong></td>
        </tr>
        <tr>
            <td>Date de fin</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($dateFin) ?></strong></td>
        </tr>
        <?php if ($isPFE && $dateSoutenance !== '—'): ?>
        <tr>
            <td>Date de soutenance</td>
            <td class="cs-sep">:</td>
            <td><strong><?= h($dateSoutenance) ?></strong></td>
        </tr>
        <?php endif; ?>
        <?php if ($jury !== ''): ?>
        <tr>
            <td>Jury / modalités</td>
            <td class="cs-sep">:</td>
            <td><?= nl2br(h($jury)) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <h2 class="cs-section-title">Engagements</h2>
    <p class="cs-engage">
        Le stagiaire s'engage à respecter le règlement intérieur de l'organisme
        d'accueil et la confidentialité des informations auxquelles il aura accès.
        L'organisme d'accueil s'engage à assurer un encadrement pédagogique adéquat
        pendant toute la durée du stage.
    </p>
    <p class="cs-engage">
        Fait à <?= h($SCHOOL_CITY) ?>, le <?= h(date('d/m/Y')) ?>, en trois exemplaires originaux.
    </p>

    <table class="cs-sign">
        <tr>
            <th>L'établissement (<?= h($SCHOOL_ORG) ?>)</th>
            <th>L'entreprise d'accueil</th>
            <th>Le stagiaire</th>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
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