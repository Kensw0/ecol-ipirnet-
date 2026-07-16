<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ── Constantes établissement (source unique de vérité pour toutes les pages d'impression) ──
$SCHOOL_ORG         = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2   = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_ADDRESS     = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL       = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";
$FORMATION_TYPE      = 'Formation Continue';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
log_document_gen($pdo, 'fiche_inscription', $id, (string) $s['num_inscri']);

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

$fmtFr = static function (?string $d): string {
    if (!$d) return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d/m/Y', $t);
};

// Build a /YYYY-YYYY-style "Année de Formation" string.
$annee = (string) $s['annee_scolaire'];
$anneeDisplay = $annee;
if (preg_match('/^(\d{4})[\/\-](\d{4})$/', $annee, $mm)) {
    $anneeDisplay = $mm[1] . '/' . $mm[2];
} elseif (preg_match('/^(\d{4})$/', $annee, $mm)) {
    $y = (int) $mm[1];
    $anneeDisplay = $y . '/' . ($y + 1);
}

$nomComplet  = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$cin         = (string) ($s['cin'] ?? '');
$dateInsc    = $s['date_inscription'] ?? '';
$dateNaiss   = $s['date_naissance'] ?? '';
$telephone   = (string) ($s['telephone'] ?? '');
$filiere     = (string) ($s['nom_filiere'] ?? '');
$photoFile   = (string) ($s['photo'] ?? '');

// Optional fields that may not exist in the schema — render blank dotted line if missing.
$niveau      = (string) ($s['niveau_scolaire'] ?? '');
$fonction    = (string) ($s['fonction']        ?? '');
$autre       = (string) ($s['autre']           ?? '');
$description = (string) ($s['description']     ?? '');
$horaire     = (string) ($s['horaire']         ?? '');
$mensualite  = (string) ($s['mensualite']      ?? '');

$photoUrl = '';
if ($photoFile !== '') {
    // Photos are typically uploaded to assets/img/photos/.
    $candidate = __DIR__ . '/assets/img/photos/' . $photoFile;
    if (is_file($candidate)) {
        $photoUrl = 'assets/img/photos/' . rawurlencode($photoFile);
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiche d'inscription — <?= h($nomComplet) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        @media print { .fi-doc { padding: 12mm 14mm !important; } }
        * { box-sizing: border-box; }
        html, body { background: #f1f3f5; }
        body {
            margin: 0;
            padding: 18px 0 40px;
            font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
            color: #111;
            font-size: 12pt;
        }
        .fi-doc {
            max-width: 880px;
            margin: 0 auto;
            background: #fff;
            padding: 18px 32px 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border: 1px solid #cdd0d4;
        }
        .fi-print-btns {
            text-align: center;
            margin-bottom: 14px;
        }
        .fi-print-btns button, .fi-print-btns a {
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
        .fi-head {
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            align-items: center;
            gap: 14px;
            padding-bottom: 6px;
            border-bottom: 1px solid #111;
        }
        .fi-head-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .fi-head-logo img { max-width: 95px; max-height: 95px; }
        .fi-head-mid { text-align: center; }
        .fi-head-mid .fi-org {
            font-weight: 700;
            font-size: 1.45rem;
            letter-spacing: 0.02em;
        }
        .fi-head-mid .fi-tag {
            font-style: italic;
            font-size: .92rem;
            line-height: 1.3;
            margin-top: 1px;
        }
        .fi-head-mid .fi-auth {
            font-size: .8rem;
            line-height: 1.35;
            margin-top: 6px;
        }
        .fi-head-stamp {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .fi-stamp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 92px;
            height: 92px;
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

        /* ===== Title block ===== */
        .fi-title-wrap {
            margin: 22px auto 18px;
            text-align: center;
            border-top: 1.5px solid #111;
            border-bottom: 1.5px solid #111;
            padding: 6px 0;
            width: 460px;
            max-width: 80%;
        }
        .fi-title-wrap .fi-title {
            font-weight: 700;
            font-size: 1.55rem;
            letter-spacing: 0.03em;
        }
        .fi-title-wrap .fi-subtitle {
            font-weight: 700;
            font-size: 1.05rem;
            margin-top: 2px;
        }
        .fi-title-wrap .fi-annee {
            font-weight: 700;
            font-size: 1rem;
            margin-top: 2px;
        }

        /* ===== Photo + top-right CIN/Date block ===== */
        .fi-photo-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 18px;
            align-items: start;
            margin-top: 6px;
        }
        .fi-photo {
            width: 110px;
            height: 130px;
            border: 1px solid #111;
            border-radius: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            color: #333;
            overflow: hidden;
            background: #fff;
        }
        .fi-photo img { max-width: 100%; max-height: 100%; object-fit: cover; }

        .fi-topright {
            padding-top: 14px;
            padding-left: 80px;
        }
        .fi-topright .fi-row {
            margin: 6px 0;
            font-size: 12pt;
            display: grid;
            grid-template-columns: 130px 1fr;
            align-items: end;
            column-gap: 6px;
        }
        .fi-topright .fi-row .fi-label {
            font-style: italic;
            text-align: right;
            white-space: nowrap;
        }
        .fi-topright .fi-row .fi-val {
            border-bottom: 1px dotted #1a1a1a;
            min-height: 1.3em;
            padding-left: 4px;
            padding-bottom: 1px;
        }

        /* ===== Main fields ===== */
        .fi-fields {
            margin-top: 18px;
        }
        .fi-fields .fi-line {
            display: grid;
            grid-template-columns: 165px 1fr;
            column-gap: 8px;
            margin: 6px 0;
            align-items: end;
        }
        .fi-fields .fi-line .fi-label {
            font-size: 12pt;
        }
        .fi-fields .fi-line .fi-val {
            border-bottom: 1px dotted #1a1a1a;
            min-height: 1.3em;
            padding-left: 4px;
            padding-bottom: 1px;
        }
        .fi-fields .fi-line .fi-val--double {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 10px;
            border: none;
            padding: 0;
        }
        .fi-fields .fi-line .fi-val--double > span {
            border-bottom: 1px dotted #1a1a1a;
            min-height: 1.3em;
            padding-left: 4px;
            padding-bottom: 1px;
        }
        .fi-fields .fi-blank {
            grid-column: 1 / -1;
            border-bottom: 1px dotted #1a1a1a;
            min-height: 1.3em;
            margin-top: 4px;
        }

        /* ===== Bold italic labels group (FormationContinue / Description / Horaire) ===== */
        .fi-block {
            margin-top: 16px;
        }
        .fi-block .fi-line {
            display: grid;
            grid-template-columns: 165px 1fr;
            column-gap: 8px;
            margin: 6px 0;
            align-items: end;
        }
        .fi-block .fi-label {
            font-weight: 700;
            font-style: italic;
        }
        .fi-block .fi-val {
            border-bottom: 1px dotted #1a1a1a;
            min-height: 1.3em;
            padding-left: 4px;
            padding-bottom: 1px;
        }

        /* ===== Statement paragraph ===== */
        .fi-engage {
            margin: 18px 30px 10px;
            font-size: 12pt;
            line-height: 1.5;
        }
        .fi-engage p { margin: 6px 0; text-indent: 18px; }
        .fi-engage .fi-engage-amount {
            text-indent: 18px;
        }
        .fi-engage .fi-engage-amount .fi-dots {
            display: inline-block;
            min-width: 220px;
            border-bottom: 1px dotted #1a1a1a;
            padding: 0 4px;
        }

        /* ===== Disclaimer box ===== */
        .fi-disclaim {
            margin: 16px 30px 0;
            border: 1px solid #111;
            padding: 8px 16px;
            text-align: center;
            font-weight: 700;
            font-style: italic;
            line-height: 1.4;
        }

        /* ===== Signature row ===== */
        .fi-signs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin: 28px 14px 0;
        }
        .fi-signs > div {
            min-height: 110px;
        }
        .fi-signs .fi-sign-label {
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
        }
        .fi-signs > div:last-child {
            text-align: left;
            padding-left: 50%;
        }

        /* ===== Footer ===== */
        .fi-footer {
            margin-top: 26px;
            border-top: 1px solid #111;
            padding-top: 6px;
            text-align: center;
            font-size: .85rem;
            line-height: 1.45;
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .fi-doc {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
                max-width: none;
            }
            .no-print, .fi-print-btns { display: none !important; }
        }
    </style>
</head>
<body>
<div class="fi-doc">
    <div class="fi-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="stagiaires.php">Retour</a>
    </div>

    <header class="fi-head">
        <div class="fi-head-logo">
            <img src="assets/img/logo.png" alt="">
        </div>
        <div class="fi-head-mid">
            <div class="fi-org"><?= h($SCHOOL_ORG) ?></div>
            <div class="fi-tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
            <div class="fi-tag"><?= h($SCHOOL_TAGLINE_2) ?></div>
            <div class="fi-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
            <div class="fi-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
        </div>
        <div class="fi-head-stamp">
            <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:80px;height:80px;object-fit:contain;border-radius:50%;">
        </div>
    </header>

    <div class="fi-title-wrap">
        <div class="fi-title">FICHE D'INSCRIPTION</div>
        <div class="fi-subtitle"><?= h($FORMATION_TYPE) ?></div>
        <div class="fi-annee">Année de Formation :<?= h($anneeDisplay) ?></div>
    </div>

    <div class="fi-photo-row">
        <div class="fi-photo">
            <?php if ($photoUrl !== ''): ?>
                <img src="<?= h($photoUrl) ?>" alt="">
            <?php else: ?>
                Photo
            <?php endif; ?>
        </div>
        <div class="fi-topright">
            <div class="fi-row">
                <span class="fi-label"><em>N° CIN :</em></span>
                <span class="fi-val"><?= h($cin) ?></span>
            </div>
            <div class="fi-row">
                <span class="fi-label"><em>Date Inscription :</em></span>
                <span class="fi-val"><?= h($fmtFr($dateInsc)) ?></span>
            </div>
        </div>
    </div>

    <div class="fi-fields">
        <div class="fi-line">
            <span class="fi-label">Nom et prénom :</span>
            <span class="fi-val"><?= h($nomComplet) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Date de naissance :</span>
            <span class="fi-val"><?= h($fmtFr($dateNaiss)) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Téléphone :</span>
            <span class="fi-val"><?= h($telephone) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Niveau Scolaire :</span>
            <span class="fi-val fi-val--double">
                <span><?= h($niveau) ?></span>
                <span></span>
            </span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Fonction :</span>
            <span class="fi-val"><?= h($fonction) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Autre :</span>
            <span class="fi-val"><?= h($autre) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-blank"></span>
        </div>
    </div>

    <div class="fi-block">
        <div class="fi-line">
            <span class="fi-label">FormationContinue en :</span>
            <span class="fi-val"><?= h($filiere) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Description :</span>
            <span class="fi-val"><?= h($description) ?></span>
        </div>
        <div class="fi-line">
            <span class="fi-label">&nbsp;</span>
            <span class="fi-val">&nbsp;</span>
        </div>
        <div class="fi-line">
            <span class="fi-label">&nbsp;</span>
            <span class="fi-val">&nbsp;</span>
        </div>
        <div class="fi-line">
            <span class="fi-label">Horaire :</span>
            <span class="fi-val"><?= h($horaire) ?></span>
        </div>
    </div>

    <div class="fi-engage">
        <p>Tout Formation continue dépassant trois mois de formation est sanctionnée par une Attestation de Formation.</p>
        <p class="fi-engage-amount">Je m'engage à régler le montant de <span class="fi-dots"><?= h($mensualite) ?></span>/ Mois</p>
    </div>

    <div class="fi-disclaim">
        Aucun remboursement ne saurait être justifié pour quelque<br>Raison que soit
    </div>

    <div class="fi-signs">
        <div>
            <span class="fi-sign-label">Signature du Candidat :</span>
        </div>
        <div>
            <span class="fi-sign-label">Direction :</span>
        </div>
    </div>

    <div class="fi-footer">
        <?= h($SCHOOL_ADDRESS) ?><br>
        <?= h($SCHOOL_LEGAL) ?>
    </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
