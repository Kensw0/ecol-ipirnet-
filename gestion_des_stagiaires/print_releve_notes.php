<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$SCHOOL_ORG          = 'Groupe IPIRNET';
$SCHOOL_TAGLINE_1    = "Institut Privé d'Informatique, Réseau et Nouvelles Etudes de Télécommunication";
$SCHOOL_TAGLINE_2    = '';
$SCHOOL_AUTH_LINE_1  = "Autorisé par l'Etat sous N°: 03/02/2003   Du : 19/02/2003";
$SCHOOL_AUTH_LINE_2  = "Accrédité par l'Etat sous N°: 21/DFP/F0301/199 du 21/11/2021";

$id   = (int) ($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'combined'; // 'controle', 'examen', 'combined'

$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    exit('Stagiaire introuvable');
}
log_document_gen($pdo, 'releve_notes', $id, (string) $s['num_inscri']);

$notesStmt = $pdo->prepare('SELECT * FROM v_moyennes_par_module WHERE id_stagiaire = ? ORDER BY nom_module');
$notesStmt->execute([$id]);
$rows = $notesStmt->fetchAll();

$sumCoef = 0;
$sumNotes = 0;
$totC = 0; $cntC = 0;
$totT = 0; $cntT = 0;
$totP = 0; $cntP = 0;

foreach ($rows as $r) {
    if ($r['note_controle'] !== null) { $totC += (float)$r['note_controle']; $cntC++; }
    if ($r['note_theorique'] !== null) { $totT += (float)$r['note_theorique']; $cntT++; }
    if ($r['note_pratique'] !== null) { $totP += (float)$r['note_pratique']; $cntP++; }

    if ($r['moyenne_module'] !== null) {
        $c = (int) $r['coefficient'];
        $sumCoef += $c;
        $sumNotes += ((float) $r['moyenne_module'] * $c);
    }
}
$avgC = $cntC > 0 ? $totC / $cntC : null;
$avgT = $cntT > 0 ? $totT / $cntT : null;
$avgP = $cntP > 0 ? $totP / $cntP : null;

$gm = $sumCoef > 0 ? round($sumNotes / $sumCoef, 2) : null;
$decision = $gm !== null ? ($gm >= 10 ? 'Admis(e)' : 'Ajourné(e)') : 'En attente';

// FIX: annee_scolaire = real academic year (e.g. "2025/2026")
//      niveau         = "1ère année" or "2ème année" — from classes.niveau column
$annee      = (string) ($s['annee_scolaire'] ?? ''); // e.g. "2025/2026"
$niveau     = (string) ($s['niveau'] ?? '');          // e.g. "1ère année" / "2ème année"

// Fallback if niveau is empty (for old data before migration)
if ($niveau === '') {
    $niveau = (string) ($s['nom_classe'] ?? '');
}

$nomComplet  = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$num_inscri  = (string) $s['num_inscri'];
$filiere     = mb_strtoupper((string) $s['nom_filiere'], 'UTF-8');

$fmtNote = static function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float) $v;
    return number_format($f, 2, ',', '');
};

$getObs = static function ($v): string {
    if ($v === null || $v === '') return '';
    $f = (float) $v;
    if ($f >= 16) return 'Très Bien';
    if ($f >= 14) return 'Bien';
    if ($f >= 12) return 'A.Bien';
    if ($f >= 10) return 'Passable';
    return 'Faible';
};

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

// Calculate colspans
$totalCols = 1; // Module Name
$totalCols += 1; // Coefficient
if ($mode === 'controle' || $mode === 'combined') {
    $totalCols += 1;
}
if ($mode === 'examen' || $mode === 'combined') {
    $totalCols += 2; // Theo + Prac
}
$totalCols += 2; // Moyenne UF + Observations
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé de Notes — <?= h($nomComplet) ?></title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        html, body { background: #e5e7eb; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #000; font-size: 11pt; padding: 20px 0; }

        .cs-print-btns { text-align: center; margin: 0 auto 20px auto; max-width: 800px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border:1px solid #ddd; }
        .cs-print-btns button, .cs-print-btns a { background: #f4f4f5; border: 1px solid #ccc; padding: 8px 16px; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; color: #111; margin: 0 5px; font-family:sans-serif; transition:all 0.2s; }
        .cs-print-btns a:hover, .cs-print-btns button:hover { background: #e4e4e7; }

        .doc-wrapper { max-width: 820px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; text-align: center; }
        .school-name { font-weight: bold; font-size: 20px; margin-bottom: 5px; }
        .school-desc { font-weight: bold; font-size: 14px; margin-bottom: 5px; }
        .school-auth { font-size: 12px; margin-bottom: 2px; }
        .logo-img { max-width: 90px; }
        .accredite-img { width: 80px; height: 80px; border-radius: 50%; border: 2px solid #000; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:12px; }

        .eval-title { text-align: center; text-transform: uppercase; font-weight: bold; font-size: 15px; text-decoration: underline; margin-bottom: 20px; line-height: 1.5; }

        .info-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 20px; font-weight:bold; font-size:13px; }
        .info-table td { border: 1px solid #000; padding: 6px 10px; }
        .info-table td:first-child { width: 200px; background: #f2f2f2; text-align:center; }
        .info-table td:nth-child(2) { width: 10px; text-align:center; border-left:none; border-right:none; }
        .info-table td:last-child { border-left:none; }

        .grades-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 12px; }
        .grades-table th, .grades-table td { border: 1px solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; }
        .grades-table thead th { background: #e8e8e8; font-weight: bold; }
        .grades-table td.module-name { text-align: left; font-weight: bold; background: #f9f9f9; width:300px; }
        .grades-table td.coeff { font-weight: bold; width:30px; }

        .bottom-row { font-weight: bold; background: #e8e8e8; }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .signature-table td { width: 50%; vertical-align: top; padding: 0 20px; }
        .signature-box { border: 2px solid #000; height: 120px; padding: 10px; position:relative; }
        .signature-box .title { text-transform: uppercase; font-size: 11px; text-align: left; text-decoration: underline; }

        @media print {
            html,body{background:#fff; margin:0; padding:0;}
            .doc-wrapper{box-shadow:none; padding:10px 0;}
            .cs-print-btns{display:none;}
            .grades-table th { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; }
            .info-table td:first-child { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; }
            .bottom-row { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="cs-print-btns">
    <strong style="margin-right:10px; font-family:sans-serif; font-size:15px;">Affichage :</strong>
    <a href="?id=<?= $id ?>&mode=combined" style="<?= $mode === 'combined' ? 'background:#000; color:#fff; border-color:#000;' : '' ?>">Complet (Contrôles + Examens)</a>
    <a href="?id=<?= $id ?>&mode=controle" style="<?= $mode === 'controle' ? 'background:#000; color:#fff; border-color:#000;' : '' ?>">Contrôles Continus Uniquement</a>
    <a href="?id=<?= $id ?>&mode=examen" style="<?= $mode === 'examen' ? 'background:#000; color:#fff; border-color:#000;' : '' ?>">Examens de fin de Cursus Uniquement</a>
    <span style="border-left:1px solid #ccc; margin:0 15px;"></span>
    <button onclick="window.print()">🖨 Imprimer</button>
    <a href="stagiaires.php">← Retour</a>
</div>

<div class="doc-wrapper">

    <table class="header-table">
        <tr>
            <td style="width: 20%; text-align:left;">
                <img src="assets/img/logo.png" alt="Logo IPIRNET" class="logo-img" onerror="this.style.display='none'">
            </td>
            <td style="width: 60%;">
                <div class="school-name"><?= $SCHOOL_ORG ?></div>
                <div class="school-desc"><?= $SCHOOL_TAGLINE_1 ?></div>
                <div class="school-auth"><?= $SCHOOL_AUTH_LINE_1 ?></div>
                <div class="school-auth"><?= $SCHOOL_AUTH_LINE_2 ?></div>
            </td>
            <td style="width: 20%; text-align:right;">
                <div align="right">
                    <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:80px;height:80px;object-fit:contain;border-radius:50%;">
                </div>
            </td>
        </tr>
    </table>

    <div class="eval-title">
        SYSTEME D'EVALUATION EN <?= h(strtoupper($niveau)) ?> DE FORMATION<br>
        ET MODALITE DE PASSAGE EN ANNEE SUPERIEURE DE LA FILIERE DE FORMATION
    </div>

    <table class="info-table">
        <tr>
            <td>N° d'inscription</td><td>:</td><td><?= h($num_inscri) ?></td>
        </tr>
        <tr>
            <td>Prénom et nom du stagiaire</td><td>:</td><td><?= h(mb_strtoupper($nomComplet, 'UTF-8')) ?></td>
        </tr>
        <tr>
            <td>Filière</td><td>:</td><td><?= h($filiere) ?></td>
        </tr>
        <tr>
            <td>Niveau</td><td>:</td><td><?= h($niveau) ?></td>
        </tr>
        <tr>
            <td>Année de Formation</td><td>:</td><td><?= h($annee) ?></td>
        </tr>
    </table>

    <?php /* grades table — unchanged from original below this point */ ?>
    <table class="grades-table">
        <thead>
            <tr>
                <th colspan="2" rowspan="2">Unités de formation et coefficient</th>

                <?php if ($mode === 'controle' || $mode === 'combined'): ?>
                    <th rowspan="2">Contrôles<br>Continus</th>
                <?php endif; ?>

                <?php if ($mode === 'examen' || $mode === 'combined'): ?>
                    <th colspan="2">Examen de fin de<br>Cursus de formation</th>
                <?php endif; ?>

                <th rowspan="2">Moyenne<br>U.F.</th>
                <th rowspan="2">Observations</th>
            </tr>
            <?php if ($mode === 'examen' || $mode === 'combined'): ?>
            <tr>
                <th>Théorique</th>
                <th>Pratique</th>
            </tr>
            <?php endif; ?>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="module-name"><?= h((string) $r['nom_module']) ?></td>
                <td class="coeff"><?= h((string) $r['coefficient']) ?></td>
                <?php if ($mode === 'controle' || $mode === 'combined'): ?>
                    <td><?= $fmtNote($r['note_controle']) ?></td>
                <?php endif; ?>
                <?php if ($mode === 'examen' || $mode === 'combined'): ?>
                    <td><?= $fmtNote($r['note_theorique']) ?></td>
                    <td><?= $fmtNote($r['note_pratique']) ?></td>
                <?php endif; ?>
                <td><?= $fmtNote($r['moyenne_module']) ?></td>
                <td><?= $getObs($r['moyenne_module']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bottom-row">
                <td class="module-name" colspan="2">Moyennes</td>
                <?php if ($mode === 'controle' || $mode === 'combined'): ?>
                    <td><?= $fmtNote($avgC) ?></td>
                <?php endif; ?>
                <?php if ($mode === 'examen' || $mode === 'combined'): ?>
                    <td><?= $fmtNote($avgT) ?></td>
                    <td><?= $fmtNote($avgP) ?></td>
                <?php endif; ?>
                <td colspan="2"><?= $fmtNote($gm) ?></td>
            </tr>
            <tr class="bottom-row">
                <td class="module-name" colspan="<?= $totalCols - 1 ?>">Décision du jury</td>
                <td><?= h($decision) ?></td>
            </tr>
        </tfoot>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-box">
                    <div class="title">Le stagiaire</div>
                </div>
            </td>
            <td>
                <div class="signature-box">
                    <div class="title">Le directeur pédagogique</div>
                </div>
            </td>
        </tr>
    </table>

</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
