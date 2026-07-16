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

// ── Parameters ──────────────────────────────────────────────────────────────
$idFiliere    = (int)   ($_GET['id_filiere']    ?? 0);
$idClasse     = (int)   ($_GET['id_classe']     ?? 0);
$filterAnnee  = trim((string)($_GET['annee_scolaire'] ?? ''));
$filterNiveau = trim((string)($_GET['niveau']         ?? ''));
$mois         = (string)($_GET['mois'] ?? date('Y-m'));
$auto         = isset($_GET['auto']) && (string)$_GET['auto'] === '1';

if (!preg_match('/^\d{4}-\d{2}$/', $mois)) $mois = date('Y-m');

$sortKey = (string)($_GET['sort'] ?? 'nom');
$allowedSort = ['nom', 'num_inscri', 'matricule', 'filiere', 'classe'];
if (!in_array($sortKey, $allowedSort, true)) $sortKey = 'nom';

$orderBy = match ($sortKey) {
    'num_inscri', 'matricule' => 's.num_inscri, s.nom, s.prenom',
    'filiere'                 => 'f.nom_filiere, c.nom_classe, s.nom, s.prenom',
    'classe'                  => 'c.nom_classe, s.nom, s.prenom',
    default                   => 's.nom, s.prenom',
};

// ── Month label ─────────────────────────────────────────────────────────────
$months = [1=>'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$dt = DateTime::createFromFormat('Y-m', $mois);
$moisLabel = $dt ? ($months[(int)$dt->format('n')] . ' ' . $dt->format('Y')) : $mois;

// ── Filière / Classe labels ─────────────────────────────────────────────────
$filiereName   = '';
$classeName    = '';

if ($idFiliere > 0) {
    $st = $pdo->prepare('SELECT nom_filiere FROM filieres WHERE id_filiere = ?');
    $st->execute([$idFiliere]);
    $filiereName = (string)($st->fetchColumn() ?: '');
}
if ($idClasse > 0) {
    $st = $pdo->prepare('SELECT c.nom_classe, c.annee_scolaire, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere WHERE c.id_classe = ?');
    $st->execute([$idClasse]);
    $row = $st->fetch();
    if ($row) {
        $classeName  = (string)$row['nom_classe'];
        if ($filiereName === '') $filiereName = (string)$row['nom_filiere'];
        if (!isset($anneeScolaire) || $anneeScolaire === '') $anneeScolaire = (string)($row['annee_scolaire'] ?? '');
    }
}
if (!isset($anneeScolaire)) $anneeScolaire = '';
if ($anneeScolaire === '' && $filterAnnee !== '') $anneeScolaire = $filterAnnee;

// ── Fetch unpaid students ───────────────────────────────────────────────────
$where  = ["(m.id_mensualite IS NULL OR (m.est_paye = 0 AND (m.statut_paiement IS NULL OR m.statut_paiement != 'payé')))"];
$params = [$mois];

if ($filterAnnee  !== '') { $where[] = 'c.annee_scolaire = ?'; $params[] = $filterAnnee;  }
if ($idFiliere    > 0)    { $where[] = 'c.id_filiere = ?';     $params[] = $idFiliere;    }
if ($filterNiveau !== '')  { $where[] = 'c.niveau = ?';         $params[] = $filterNiveau; }
if ($idClasse     > 0)    { $where[] = 's.id_classe = ?';      $params[] = $idClasse;     }

$sql = "SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
               c.nom_classe, c.id_filiere, f.nom_filiere,
               COALESCE(s.remise_mensuelle, 0) as remise_mensuelle,
               m.montant_restant, m.montant_total, m.remise
        FROM stagiaires s
        JOIN classes  c ON c.id_classe  = s.id_classe
        JOIN filieres f ON f.id_filiere = c.id_filiere
        LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ?
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Filière tarif defaults
$tarifsDefaut = [2 => 700.0, 3 => 600.0, 4 => 800.0];

$total        = count($rows);
$totalRestant = 0.0;
foreach ($rows as $r) {
    $remiseMens = max(0.0, (float)($r['remise_mensuelle'] ?? 0));
    $remisePmt  = max(0.0, (float)($r['remise'] ?? 0));
    $eRemise    = $remisePmt > 0 ? $remisePmt : $remiseMens;
    $tarifDef   = $tarifsDefaut[(int)($r['id_filiere'] ?? 0)] ?? 700.0;
    if ($r['montant_restant'] !== null) {
        // Record exists: restant is stored, but subtract remise if not already applied
        $totalRestant += max(0.0, (float)$r['montant_restant']);
    } elseif ($r['montant_total'] !== null) {
        $totalRestant += max(0.0, (float)$r['montant_total'] - $eRemise);
    } else {
        // No record at all: full effective tarif is owed
        $totalRestant += max(0.0, $tarifDef - $eRemise);
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des Retards de Paiement — <?= h($moisLabel) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        @media print { .cs-doc { padding: 12mm !important; } }
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
            border: 2px solid #111; color: #111;
            font-family: "Times New Roman", serif; font-weight: 700;
            font-size: .8rem; text-align: center;
            background: radial-gradient(circle, #fff 55%, transparent 56%);
        }
        .cs-head-mid .cs-org  { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag  { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* ===== Title in oval ===== */
        .cs-title-wrap { display: flex; justify-content: center; margin: 22px 0 16px; }
        .cs-title-oval {
            border: 1.5px solid #111;
            border-radius: 50%;
            padding: 12px 50px;
            min-width: 60%;
            text-align: center;
            font-family: "Monotype Corsiva", "Lucida Handwriting", cursive;
            font-style: italic;
            font-size: 1.6rem;
            color: #111;
            white-space: nowrap;
        }

        /* ===== Meta block ===== */
        .ls-meta { width: 100%; border-collapse: collapse; margin: 4px 0 12px; font-size: 11pt; }
        .ls-meta td { padding: 4px; }
        .ls-meta td.lbl { width: 18%; white-space: nowrap; }
        .ls-meta td.sep { width: 1px; padding: 0 2px; }

        /* ===== Table ===== */
        .ls-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ls-table th, .ls-table td { border: 1px solid #111; padding: 6px 8px; text-align: left; }
        .ls-table thead th { background: #f4f4f5; color: #111; font-weight: 700; text-align: center; }
        .ls-table .cnt  { text-align: center; width: 40px; }
        .ls-table .mat  { text-align: center; width: 120px; }
        .ls-table .cls  { text-align: center; width: 90px; }
        .ls-table .amt  { text-align: right; width: 110px; }
        .ls-empty { text-align: center; font-style: italic; padding: 20px; }

        /* ===== Footer ===== */
        .cs-footer { border-top: 1px solid #111; padding-top: 8px; margin-top: 20px; text-align: center; font-size: 0.8rem; }

        @media print {
            html, body { background: #fff; }
            .cs-doc { box-shadow: none; border: none; padding: 0; margin: 0; max-width: none; }
            .no-print { display: none !important; }
            .ls-table thead th { background: #f4f4f5 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="cs-doc">
    <div class="cs-print-btns no-print">
        <button type="button" onclick="window.print()">Imprimer la liste</button>
        <a href="cotisations.php">Retour</a>
    </div>

    <!-- En-tête officiel (partagé entre toutes les pages d'impression) -->
    <?php require __DIR__ . '/includes/print_letterhead.php'; ?>

    <!-- Title in oval -->
    <div class="cs-title-wrap">
        <div class="cs-title-oval">Liste des Retards de Cotisations</div>
    </div>

    <!-- Meta info -->
    <table class="ls-meta">
        <tr>
            <td class="lbl">Mois de référence</td><td class="sep">:</td>
            <td><strong><?= h($moisLabel) ?></strong></td>
            <td style="text-align:right; padding-right:4px;">
                <?php if ($filiereName): ?>Filière : <strong><?= h($filiereName) ?></strong><?php endif; ?>
                <?php if ($classeName): ?>  — Classe : <strong><?= h($classeName) ?></strong><?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="lbl">Concernés</td><td class="sep">:</td>
            <td colspan="2">
                <strong><?= $total ?></strong> stagiaire<?= $total > 1 ? 's' : '' ?>
                <?php if ($totalRestant > 0): ?> — Total restant dû : <strong><?= number_format($totalRestant, 2, ',', ' ') ?> MAD</strong><?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Students table -->
    <table class="ls-table">
        <thead>
            <tr>
                <th class="cnt">N°</th>
                <th class="mat">N° Inscription</th>
                <th>Nom &amp; Prénom</th>
                <th class="cls">Classe</th>
                <th>Filière</th>
                <th class="amt">Restant dû (MAD)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="ls-empty">Aucun retard de paiement détecté pour ce mois. ✅</td></tr>
            <?php else: ?>
                <?php $i = 0; foreach ($rows as $r): $i++;
                    $rRemiseMens = max(0.0, (float)($r['remise_mensuelle'] ?? 0));
                    $rRemisePmt  = max(0.0, (float)($r['remise'] ?? 0));
                    $rERemise    = $rRemisePmt > 0 ? $rRemisePmt : $rRemiseMens;
                    $rTarifDef   = $tarifsDefaut[(int)($r['id_filiere'] ?? 0)] ?? 700.0;
                    if ($r['montant_restant'] !== null) {
                        $rRestant = max(0.0, (float)$r['montant_restant']);
                    } elseif ($r['montant_total'] !== null) {
                        $rRestant = max(0.0, (float)$r['montant_total'] - $rERemise);
                    } else {
                        $rRestant = max(0.0, $rTarifDef - $rERemise);
                    }
                ?>
                <tr>
                    <td class="cnt"><?= $i ?></td>
                    <td class="mat"><?= h((string)($r['num_inscri'] ?? '')) ?></td>
                    <td><strong><?= h(strtoupper((string)($r['nom'] ?? ''))) ?></strong> <?= h(ucwords(mb_strtolower((string)($r['prenom'] ?? ''), 'UTF-8'))) ?></td>
                    <td class="cls"><?= h((string)($r['nom_classe'] ?? '')) ?></td>
                    <td style="font-size:0.9rem;"><?= h((string)($r['nom_filiere'] ?? '')) ?></td>
                    <td class="amt" style="font-weight:700; color:#b91c1c;"><?= number_format($rRestant, 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if ($rows): ?>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align:right; background:#f4f4f5; font-weight:700;">Total restant dû</th>
                <th class="amt" style="background:#f4f4f5; font-weight:700; color:#b91c1c;"><?= number_format($totalRestant, 2, ',', ' ') ?> MAD</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- Signature block -->
    <div style="margin-top:25px; display:flex; justify-content:flex-end;">
        <table style="border:1px solid #111; width:220px;">
            <tr><th style="border:1px solid #111; padding:5px; background:#f4f4f5; font-style:italic;">La Direction / Comptabilité</th></tr>
            <tr><td style="height:100px;"></td></tr>
        </table>
    </div>

    <?php require __DIR__ . '/includes/print_footer.php'; ?>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>