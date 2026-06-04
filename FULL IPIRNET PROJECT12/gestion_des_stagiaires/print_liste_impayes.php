<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School / director constants ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_ADDRESS      = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL        = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

$moisCourant = date('Y-m');
$nomMois = [
    '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', '05' => 'Mai', '06' => 'Juin',
    '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
];
$moisTexte = $nomMois[date('m')] . ' ' . date('Y');

// Query for stagiaires who haven't paid for the current month
$sql = "SELECT s.num_inscri, s.nom, s.prenom, c.nom_classe, f.nom_filiere
        FROM stagiaires s
        JOIN classes c ON c.id_classe = s.id_classe
        JOIN filieres f ON f.id_filiere = c.id_filiere
        LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1
        WHERE m.id_mensualite IS NULL
        ORDER BY c.nom_classe, s.nom, s.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute([$moisCourant]);
$rows = $stmt->fetchAll();

$total = count($rows);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des Retards de Paiement — <?= h($moisTexte) ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body { background: #f1f3f5; }
        body {
            margin: 0;
            padding: 18px 0 40px;
            font-family: "Cambria", "Times New Roman", "Liberation Serif", serif;
            color: #111;
            font-size: 11pt;
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

        /* ===== Letterhead ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 90px; max-height: 90px; display: inline-block; }
        .cs-stamp {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 88px; height: 88px; border-radius: 50%;
            border: 2px solid #ef4444; color: #ef4444;
            font-family: "Times New Roman", serif; font-weight: 700;
            font-size: .8rem; text-align: center;
            background: radial-gradient(circle, #fff 55%, transparent 56%);
        }

        .cs-head-mid .cs-org { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* ===== Title ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 16px; }
        .cs-title-oval {
            border: 1.5px solid #ef4444;
            border-radius: 50%;
            padding: 12px 50px;
            min-width: 60%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
            font-style: italic;
            font-size: 1.6rem;
            color: #ef4444;
            white-space: nowrap;
        }

        .ls-meta { width: 100%; margin: 4px 0 12px; font-size: 11pt; border-collapse: collapse; }
        .ls-meta td { padding: 4px; }
        
        /* ===== Table ===== */
        .ls-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ls-table th, .ls-table td { border: 1px solid #111; padding: 6px 8px; text-align: left; }
        .ls-table thead th { background: #fee2e2; color: #b91c1c; font-weight: 700; text-align: center; }
        .ls-table .cnt { text-align: center; width: 40px; }
        .ls-table .mat { text-align: center; width: 130px; }
        .ls-table .cls { text-align: center; width: 100px; }
        .ls-empty { text-align: center; font-style: italic; padding: 20px; }

        .cs-footer { border-top: 1px solid #111; padding-top: 8px; margin-top: 20px; text-align: center; font-size: 0.8rem; }

        @media print {
            html, body { background: #fff; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer la liste</button>
        <a href="alertes.php">Retour aux Alertes</a>
    </div>

    <table class="cs-head">
        <tr>
            <td class="cs-head-left"><img src="assets/img/logo.png" alt="" class="cs-head-logo"></td>
            <td class="cs-head-mid">
                <div class="cs-org"><?= h($SCHOOL_ORG) ?></div>
                <div class="cs-tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
                <div class="cs-tag"><?= h($SCHOOL_TAGLINE_2) ?></div>
                <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
                <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
            </td>
            <td class="cs-head-right"><div class="cs-stamp">RETARDS<br>PAIEMENT</div></td>
        </tr>
    </table>

    <div class="cs-title-wrap">
        <div class="cs-title-oval">Liste des Retards de Cotisations</div>
    </div>

    <table class="ls-meta">
        <tr>
            <td>Mois de référence : <strong><?= h($moisTexte) ?></strong></td>
            <td style="text-align:right;">Nombre de stagiaires concernés : <strong><?= $total ?></strong></td>
        </tr>
    </table>

    <table class="ls-table">
        <thead>
            <tr>
                <th class="cnt">N°</th>
                <th class="mat">N° Inscription</th>
                <th>Nom &amp; Prénom</th>
                <th class="cls">Classe</th>
                <th>Filière</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="ls-empty">Aucun retard de paiement détecté pour ce mois. ✅</td></tr>
            <?php else: ?>
                <?php $i=0; foreach ($rows as $r): $i++; ?>
                <tr>
                    <td class="cnt"><?= $i ?></td>
                    <td class="mat"><?= h((string)$r['num_inscri']) ?></td>
                    <td><strong><?= h(strtoupper((string)$r['nom'])) ?></strong> <?= h(ucwords(strtolower((string)$r['prenom']))) ?></td>
                    <td class="cls"><?= h((string)$r['nom_classe']) ?></td>
                    <td style="font-size:0.9rem;"><?= h((string)$r['nom_filiere']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:25px; display:flex; justify-content:flex-end;">
        <table style="border:1px solid #111; width:220px;">
            <tr><th style="border:1px solid #111; padding:5px; background:#f4f4f5; font-style:italic;">La Direction / Comptabilité</th></tr>
            <tr><td style="height:100px;"></td></tr>
        </table>
    </div>

    <div class="cs-footer">
        <?= h($SCHOOL_ADDRESS) ?><br><?= h($SCHOOL_LEGAL) ?>
    </div>
</div>
</body>
</html>
