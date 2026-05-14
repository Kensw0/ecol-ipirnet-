<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ---- School / director constants (same source of truth as the certificat) ----
$SCHOOL_ORG          = 'GROUPE IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique Réseau et Nouvelles";
$SCHOOL_TAGLINE_2    = 'Etude de Télécommunication';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N: 3/03/2/2003   Du: 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N° 21/ DFP/ F0301/199   du 29/11/2021";
$SCHOOL_ADDRESS      = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13  //  mobile 06 27 61 21 79';
$SCHOOL_LEGAL        = "Email : ipirnet.fp@gmail.com,  R.C : 6693,  Patente N° : 40724575,  IF : 14374293";

$idFiliere = (int) ($_GET['id_filiere'] ?? 0);
$idClasse  = (int) ($_GET['id_classe'] ?? 0);
$sortKey   = (string) ($_GET['sort'] ?? 'nom');
$allowedSort = ['nom', 'matricule', 'filiere', 'classe'];
if (!in_array($sortKey, $allowedSort, true)) {
    $sortKey = 'nom';
}

$orderBy = match ($sortKey) {
    'matricule' => 's.matricule, s.nom, s.prenom',
    'filiere'   => 'f.nom_filiere, c.nom_classe, s.nom, s.prenom',
    'classe'    => 'c.nom_classe, s.nom, s.prenom',
    default     => 's.nom, s.prenom',
};

$where = [];
$params = [];
if ($idFiliere > 0) {
    $where[] = 'f.id_filiere = ?';
    $params[] = $idFiliere;
}
if ($idClasse > 0) {
    $where[] = 's.id_classe = ?';
    $params[] = $idClasse;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, s.date_inscription,
               c.nom_classe, c.annee_scolaire,
               f.id_filiere, f.nom_filiere
        FROM stagiaires s
        JOIN classes c ON c.id_classe = s.id_classe
        JOIN filieres f ON f.id_filiere = c.id_filiere
        $whereSql
        ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filiereName = '';
$classeName  = '';
$anneeScolaire = '';
if ($idFiliere > 0) {
    $st = $pdo->prepare('SELECT nom_filiere FROM filieres WHERE id_filiere = ?');
    $st->execute([$idFiliere]);
    $filiereName = (string) ($st->fetchColumn() ?: '');
}
if ($idClasse > 0) {
    $st = $pdo->prepare('SELECT c.nom_classe, c.annee_scolaire, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere WHERE c.id_classe = ?');
    $st->execute([$idClasse]);
    $row = $st->fetch();
    if ($row) {
        $classeName    = (string) $row['nom_classe'];
        $anneeScolaire = (string) $row['annee_scolaire'];
        if ($filiereName === '') {
            $filiereName = (string) $row['nom_filiere'];
        }
    }
}
if ($anneeScolaire === '' && $rows) {
    $anneeScolaire = (string) ($rows[0]['annee_scolaire'] ?? '');
}

$total = count($rows);
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des Stagiaires — IPIRNET</title>
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

        /* ===== Title in oval ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 16px; }
        .cs-title-oval {
            border: 1.5px solid #1a1a1a;
            border-radius: 50%;
            padding: 14px 60px;
            min-width: 50%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-style: italic;
            font-size: 1.6rem;
            color: #0b3b66;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        /* ===== Filter context block ===== */
        .ls-meta {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 12px;
            font-size: 11.5pt;
        }
        .ls-meta td { padding: 3px 6px; vertical-align: top; line-height: 1.5; }
        .ls-meta td.lbl { width: 18%; white-space: nowrap; }
        .ls-meta td.sep { width: 1px; padding: 0 2px; }
        .ls-meta td strong { font-weight: 700; }

        /* ===== Stagiaires table ===== */
        .ls-table {
            width: calc(100% - 8px);
            margin: 6px 4px 8px;
            border-collapse: collapse;
            font-size: 11pt;
        }
        .ls-table th, .ls-table td {
            border: 1px solid #111;
            padding: 5px 8px;
            text-align: left;
            vertical-align: middle;
        }
        .ls-table thead th {
            background: #f4f4f5;
            font-weight: 700;
            text-align: center;
        }
        .ls-table td.code, .ls-table th.code { width: 110px; text-align: center; }
        .ls-table td.classe, .ls-table th.classe { width: 90px; text-align: center; }
        .ls-table td.filiere, .ls-table th.filiere { width: 90px; text-align: center; }
        .ls-table td.obs, .ls-table th.obs { width: 32%; }
        .ls-empty { text-align: center; font-style: italic; padding: 14px; }

        /* ===== Signature 2-column box (like the picture) ===== */
        .ls-sign {
            margin: 22px 4px 18px;
            width: calc(100% - 8px);
            border-collapse: collapse;
        }
        .ls-sign th, .ls-sign td {
            border: 1px solid #111;
            padding: 12px 14px;
            vertical-align: top;
        }
        .ls-sign th {
            font-weight: 400;
            font-style: italic;
            font-family: "Monotype Corsiva", "Lucida Handwriting", "Brush Script MT", cursive;
            font-size: 1.2rem;
            color: #0b3b66;
            text-align: center;
            background: #fafafa;
        }
        .ls-sign td { height: 120px; }

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
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer</button>
        <a href="stagiaires.php">Retour</a>
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
        <div class="cs-title-oval">Liste des Stagiaires</div>
    </div>

    <table class="ls-meta">
        <tr>
            <td class="lbl">Filière</td><td class="sep">:</td>
            <td><strong><?= h($filiereName !== '' ? $filiereName : 'Toutes filières') ?></strong></td>
            <td class="lbl">Année scolaire</td><td class="sep">:</td>
            <td><strong><?= h($anneeScolaire !== '' ? $anneeScolaire : '—') ?></strong></td>
        </tr>
        <tr>
            <td class="lbl">Classe</td><td class="sep">:</td>
            <td><strong><?= h($classeName !== '' ? $classeName : 'Toutes classes') ?></strong></td>
            <td class="lbl">Effectif</td><td class="sep">:</td>
            <td><strong><?= (int) $total ?></strong> stagiaire<?= $total > 1 ? 's' : '' ?></td>
        </tr>
    </table>

    <table class="ls-table">
        <thead>
            <tr>
                <th class="code">Code</th>
                <th>Nom &amp; Prénom du stagiaire</th>
                <th class="classe">Classe</th>
                <th class="filiere">Filière</th>
                <th class="obs">Observation</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="ls-empty">Aucun stagiaire ne correspond aux filtres choisis.</td></tr>
            <?php else: ?>
                <?php $i = 0; foreach ($rows as $r): $i++; ?>
                    <tr>
                        <td class="code"><?= h((string) ($r['matricule'] ?? sprintf('%02d', $i))) ?></td>
                        <td><?= h(strtoupper((string) ($r['nom'] ?? '')) . ' ' . ucfirst(mb_strtolower((string) ($r['prenom'] ?? ''), 'UTF-8'))) ?></td>
                        <td class="classe"><?= h((string) ($r['nom_classe'] ?? '')) ?></td>
                        <td class="filiere"><?= h(gds_filiere_code((string) ($r['nom_filiere'] ?? ''))) ?></td>
                        <td class="obs">&nbsp;</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="ls-sign">
        <tr>
            <th>Signature du formateur</th>
            <th>Signature Président de jury</th>
        </tr>
        <tr>
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
