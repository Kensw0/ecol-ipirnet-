<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School / director constants (same source of truth) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique, Réseau et Nouvelles Etudes de Télécommunication";
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N°: 03/02/2003   Du : 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° :21/DFP/F0301/199   du : 29/11/2021";

$idClasse = (int) ($_GET['id_classe'] ?? 0);
if ($idClasse <= 0) {
    exit('Veuillez sélectionner une classe.');
}

// Get students for this class
$sql = "SELECT s.num_inscri, s.nom, s.prenom, c.nom_classe, c.annee_scolaire, f.nom_filiere
        FROM stagiaires s
        JOIN classes c ON c.id_classe = s.id_classe
        JOIN filieres f ON f.id_filiere = c.id_filiere
        WHERE s.id_classe = ?
        ORDER BY s.nom, s.prenom";
$stmt = $pdo->prepare($sql);
$stmt->execute([$idClasse]);
$rows = $stmt->fetchAll();

if (!$rows) {
    exit('Aucun stagiaire dans cette classe.');
}

$classeName    = (string) $rows[0]['nom_classe'];
$anneeScolaire = (string) $rows[0]['annee_scolaire'];
$filiereName   = (string) $rows[0]['nom_filiere'];
$niveau        = str_contains(strtolower($classeName), '1a') || str_contains(strtolower($classeName), '1ère') ? '1re Année' : '2ème Année';

// Fill in remaining empty rows so the paper has lines to the bottom
$minRows = 25;
$padRows = max(0, $minRows - count($rows));

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau de Notes de Contrôle</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        html, body { background: #f1f3f5; }
        body { margin: 0; padding: 18px 0 40px; font-family: "Cambria", "Times New Roman", "Liberation Serif", serif; color: #000; font-size: 11pt; }
        .cs-doc { max-width: 840px; margin: 0 auto; background: #fff; padding: 16px 20px 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); border: 1px solid #cdd0d4; min-height: 297mm; }
        .cs-print-btns { text-align: center; margin-bottom: 14px; }
        .cs-print-btns button, .cs-print-btns a { background: #f4f4f5; border: 1px solid #ccc; padding: .35rem .8rem; border-radius: 8px; font-size: .85rem; cursor: pointer; text-decoration: none; color: #111; margin: 0 4px; }
        
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-table td { vertical-align: middle; }
        .head-logo-left { width: 120px; text-align: center; }
        .head-logo-left img { max-width: 90px; }
        .head-center { text-align: left; padding-left: 20px; }
        .head-center .org { font-weight: bold; font-size: 14pt; margin-bottom: 2px; }
        .head-center .tag { font-size: 10pt; }
        .head-logo-right { width: 120px; text-align: center; }
        .accredite-stamp { display: inline-flex; align-items: center; justify-content: center; width: 75px; height: 75px; border-radius: 50%; border: 3px solid #666; font-size: 8pt; font-weight: bold; color: #666; text-align: center; padding: 2px; text-transform: uppercase; letter-spacing: 1px; }

        .doc-title { text-align: center; font-size: 16pt; font-weight: bold; margin: 20px 0 25px; letter-spacing: 0.5px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11pt; font-weight: bold; }
        .meta-table td { padding: 4px 0; vertical-align: bottom; }
        .meta-table .lbl { width: 80px; }
        .meta-table .dots { border-bottom: 1px dotted #000; display: inline-block; width: 100%; margin-left: 5px; height: 16px; }
        .col-right { width: 35%; padding-left: 20px !important; }
        .col-right .lbl { width: 100px; }

        .grid-table { width: 100%; border-collapse: collapse; border: 2px solid #000; }
        .grid-table th, .grid-table td { border: 1px solid #000; padding: 6px 8px; text-align: center; }
        .grid-table th { font-weight: bold; padding: 8px; font-size: 11pt; border-bottom: 2px solid #000; background: #fafafa; }
        .grid-table td.name { text-align: left; padding-left: 10px; font-weight: bold; }
        .grid-table .col-code { width: 18%; }
        .grid-table .col-name { width: 42%; }
        .grid-table .col-note { width: 15%; }
        .grid-table .col-obs { width: 25%; }
        
        .empty-row td { height: 26px; }

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
        <button type="button" onclick="window.print()">🖨 Imprimer</button>
        <button type="button" onclick="window.close()">Fermer</button>
    </div>

    <table class="header-table">
        <tr>
            <td class="head-logo-left"><img src="assets/img/logo.png" alt="Logo"></td>
            <td class="head-center">
                <div class="org"><?= h($SCHOOL_ORG) ?></div>
                <div class="tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
                <div class="tag" style="font-size: 9pt; margin-top:2px; font-weight:bold;"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
                <div class="tag" style="font-size: 9pt; font-weight:bold;"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
            </td>
            <td class="head-logo-right">
                <div class="accredite-stamp">ACCREDITÉ</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">Tableau de Notes de Contrôle</div>

    <table class="meta-table">
        <tr>
            <td class="lbl">Filiere :</td>
            <td><?= h($filiereName) ?></td>
            <td class="col-right lbl">Contrôle N° :</td>
            <td><span class="dots"></span></td>
        </tr>
        <tr>
            <td class="lbl">U.F. :</td>
            <td><span class="dots" style="width: 70%;"></span></td>
            <td class="col-right lbl">Niveau :</td>
            <td><?= h($niveau) ?></td>
        </tr>
        <tr>
            <td class="lbl">Année :</td>
            <td><?= h($anneeScolaire) ?></td>
            <td class="col-right lbl">Formateur :</td>
            <td><span class="dots"></span></td>
        </tr>
    </table>

    <table class="grid-table">
        <thead>
            <tr>
                <th class="col-code">Code</th>
                <th class="col-name">Prénom & Nom stagiaire</th>
                <th class="col-note">Note</th>
                <th class="col-obs">Observation</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h((string) $r['num_inscri']) ?></td>
                    <td class="name"><?= h(ucfirst(mb_strtolower((string)$r['prenom'], 'UTF-8')) . ' ' . strtoupper((string)$r['nom'])) ?></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
            
            <?php for ($i = 0; $i < $padRows; $i++): ?>
                <tr class="empty-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
