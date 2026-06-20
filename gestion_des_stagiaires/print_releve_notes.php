<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$SCHOOL_ORG         = 'Groupe IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique, Réseau et Nouvelles Etudes de Télécommunication";
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N°: 03/02/2003   Du : 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N°: 21/DFP/F0301/199 du 21/11/2021";

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); exit('Stagiaire introuvable'); }

log_document_gen($pdo, 'releve_notes', $id, (string)$s['num_inscri']);

// ── Modules for this student's filière ───────────────────────────────────
$stModules = $pdo->prepare('
    SELECT id_module, nom_module, coefficient, nb_controles
    FROM modules
    WHERE id_filiere = ?
    ORDER BY nom_module
');
$stModules->execute([(int)$s['id_filiere']]);
$modules = $stModules->fetchAll();

// ── All notes for this student (keyed by module then type) ────────────────
$stNotes = $pdo->prepare('SELECT id_module, type, note FROM module_notes WHERE id_stagiaire = ?');
$stNotes->execute([$id]);
$notesByModule = [];
foreach ($stNotes->fetchAll() as $n) {
    $notesByModule[(int)$n['id_module']][$n['type']] = $n['note'] !== null ? (float)$n['note'] : null;
}

// ── Max number of controles across all modules (for column headers) ───────
$maxC = 1;
foreach ($modules as $m) { $maxC = max($maxC, (int)$m['nb_controles']); }

// ── Helpers ───────────────────────────────────────────────────────────────
$fmtNote = static function ($v): string {
    if ($v === null || $v === false || $v === '') return '';
    return number_format((float)$v, 2, ',', '');
};
$getObs = static function ($v): string {
    if ($v === null || $v === false || $v === '') return '';
    $f = (float)$v;
    if ($f >= 16) return 'Très Bien';
    if ($f >= 14) return 'Bien';
    if ($f >= 12) return 'A.Bien';
    if ($f >= 10) return 'Passable';
    return 'Faible';
};

// ── Build display rows + totals ───────────────────────────────────────────
$rows        = [];
$sumCoef     = 0;
$sumWeighted = 0;
// Column-level sums for footer averages
$colSumsC    = array_fill(1, $maxC, ['sum' => 0.0, 'cnt' => 0]);
$colSumT     = ['sum' => 0.0, 'cnt' => 0];
$colSumP     = ['sum' => 0.0, 'cnt' => 0];

foreach ($modules as $m) {
    $mid  = (int)$m['id_module'];
    $nbc  = (int)$m['nb_controles'];
    $coef = (int)$m['coefficient'];
    $notes = $notesByModule[$mid] ?? [];

    // Individual controles: false = N/A for this module, null = not entered
    $controles = [];
    for ($i = 1; $i <= $maxC; $i++) {
        if ($i <= $nbc) {
            $v = $notes["controle_$i"] ?? null;
            $controles[$i] = $v;
            if ($v !== null) {
                $colSumsC[$i]['sum'] += $v;
                $colSumsC[$i]['cnt']++;
            }
        } else {
            $controles[$i] = false; // not applicable
        }
    }

    $theorique = $notes['theorique'] ?? null;
    $pratique  = $notes['pratique']  ?? null;

    if ($theorique !== null) { $colSumT['sum'] += $theorique; $colSumT['cnt']++; }
    if ($pratique  !== null) { $colSumP['sum'] += $pratique;  $colSumP['cnt']++; }

    // Average of entered controles
    $validC = array_filter(
        array_slice($controles, 0, $nbc, true),
        fn($v) => $v !== null && $v !== false
    );
    $avgC = !empty($validC) ? (array_sum($validC) / count($validC)) : null;

    // Moyenne UF — same weight formula as the DB view
    $theo = $theorique;
    $prat = $pratique;
    if ($avgC !== null && $theo !== null && $prat !== null) {
        $moyenne = round($avgC * 0.40 + $theo * 0.30 + $prat * 0.30, 2);
    } elseif ($avgC !== null && ($theo !== null || $prat !== null)) {
        $moyenne = round($avgC * 0.40 + ($theo ?? 0) * 0.30 + ($prat ?? 0) * 0.30, 2);
    } elseif ($avgC !== null) {
        $moyenne = round($avgC, 2);
    } elseif ($theo !== null || $prat !== null) {
        $cnt = ($theo !== null ? 1 : 0) + ($prat !== null ? 1 : 0);
        $moyenne = round((($theo ?? 0) + ($prat ?? 0)) / $cnt, 2);
    } else {
        $moyenne = null;
    }

    if ($moyenne !== null) {
        $sumCoef     += $coef;
        $sumWeighted += $moyenne * $coef;
    }

    $rows[] = [
        'nom_module'  => (string)$m['nom_module'],
        'coefficient' => $coef,
        'nb_controles'=> $nbc,
        'controles'   => $controles,
        'theorique'   => $theorique,
        'pratique'    => $pratique,
        'moyenne'     => $moyenne,
    ];
}

$gm       = $sumCoef > 0 ? round($sumWeighted / $sumCoef, 2) : null;
$decision = $gm !== null ? ($gm >= 10 ? 'Admis(e)' : 'Ajourné(e)') : 'En attente';

// Footer column averages
$footC = [];
for ($i = 1; $i <= $maxC; $i++) {
    $footC[$i] = $colSumsC[$i]['cnt'] > 0 ? $colSumsC[$i]['sum'] / $colSumsC[$i]['cnt'] : null;
}
$footT = $colSumT['cnt'] > 0 ? $colSumT['sum'] / $colSumT['cnt'] : null;
$footP = $colSumP['cnt'] > 0 ? $colSumP['sum'] / $colSumP['cnt'] : null;

// Student info
$annee      = (string)($s['annee_scolaire'] ?? '');
$niveau     = (string)($s['niveau_classe']  ?? ($s['niveau'] ?? ''));
if ($niveau === '') $niveau = (string)($s['nom_classe'] ?? '');
$nomComplet = trim((string)$s['nom'] . ' ' . (string)$s['prenom']);
$num_inscri = (string)$s['num_inscri'];
$filiere    = mb_strtoupper((string)$s['nom_filiere'], 'UTF-8');

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

// Total columns: Module + Coef + maxC controles + Theorique + Pratique + Moyenne + Obs
$totalCols = 2 + $maxC + 2 + 2;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relevé de Notes — <?= h($nomComplet) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        html, body { background: #e5e7eb; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #000; font-size: 10.5pt; padding: 16px 0; }

        .cs-print-btns { text-align: center; margin: 0 auto 16px; max-width: 1000px; background: #fff; padding: 12px 16px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,.1); border: 1px solid #ddd; display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; }
        .cs-print-btns button, .cs-print-btns a { background: #f4f4f5; border: 1px solid #ccc; padding: 6px 14px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; color: #111; font-family: sans-serif; transition: all .2s; }
        .cs-print-btns button:hover, .cs-print-btns a:hover { background: #e4e4e7; }

        .doc-wrapper { max-width: 1000px; margin: 0 auto; background: #fff; padding: 24px 28px; box-shadow: 0 0 10px rgba(0,0,0,.1); }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header-table td { vertical-align: top; text-align: center; }
        .school-name { font-weight: bold; font-size: 18px; margin-bottom: 4px; }
        .school-desc { font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .school-auth { font-size: 11px; margin-bottom: 2px; }
        .logo-img { max-width: 80px; }

        .eval-title { text-align: center; text-transform: uppercase; font-weight: bold; font-size: 13px; text-decoration: underline; margin-bottom: 16px; line-height: 1.5; }

        .info-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 16px; font-weight: bold; font-size: 12px; }
        .info-table td { border: 1px solid #000; padding: 5px 8px; }
        .info-table td:first-child { width: 200px; background: #f2f2f2; text-align: center; }
        .info-table td:nth-child(2) { width: 10px; text-align: center; border-left: none; border-right: none; }
        .info-table td:last-child { border-left: none; }

        .grades-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 10pt; }
        .grades-table th, .grades-table td { border: 1px solid #000; padding: 4px 6px; text-align: center; vertical-align: middle; }
        .grades-table thead th { background: #e8e8e8; font-weight: bold; }
        .grades-table td.module-name { text-align: left; font-weight: bold; background: #f9f9f9; }
        .grades-table td.coeff { font-weight: bold; }
        .grades-table td.na { background: #f0f0f0; color: #aaa; font-size: 9pt; }
        .bottom-row { font-weight: bold; background: #e8e8e8; }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .signature-table td { width: 50%; vertical-align: top; padding: 0 16px; }
        .signature-box { border: 2px solid #000; height: 100px; padding: 8px; }
        .signature-box .title { text-transform: uppercase; font-size: 10px; text-decoration: underline; }

        @media print {
            html, body { background: #fff; margin: 0; padding: 0; }
            .doc-wrapper { box-shadow: none; padding: 0; max-width: none; }
            .cs-print-btns { display: none; }
            .grades-table thead th { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .grades-table td.module-name { background: #f9f9f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .grades-table td.na { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-table td:first-child { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .bottom-row { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="cs-print-btns">
    <strong style="font-family:sans-serif;font-size:14px;">Relevé complet — <?= h($nomComplet) ?></strong>
    <span style="border-left:1px solid #ccc;margin:0 8px;"></span>
    <button onclick="window.print()">🖨 Imprimer</button>
    <a href="stagiaires.php">← Retour</a>
</div>

<div class="doc-wrapper">

    <table class="header-table">
        <tr>
            <td style="width:18%;text-align:left;">
                <img src="assets/img/logo.png" alt="Logo IPIRNET" class="logo-img" onerror="this.style.display='none'">
            </td>
            <td style="width:64%;">
                <div class="school-name"><?= $SCHOOL_ORG ?></div>
                <div class="school-desc"><?= $SCHOOL_TAGLINE_1 ?></div>
                <div class="school-auth"><?= $SCHOOL_AUTH_LINE_1 ?></div>
                <div class="school-auth"><?= $SCHOOL_AUTH_LINE_2 ?></div>
            </td>
            <td style="width:18%;text-align:right;">
                <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:72px;height:72px;object-fit:contain;border-radius:50%;">
            </td>
        </tr>
    </table>

    <div class="eval-title">
        RELEVÉ DE NOTES — <?= h(strtoupper($niveau)) ?> DE FORMATION
    </div>

    <table class="info-table">
        <tr><td>N° d'inscription</td><td>:</td><td><?= h($num_inscri) ?></td></tr>
        <tr><td>Prénom et nom du stagiaire</td><td>:</td><td><?= h(mb_strtoupper($nomComplet, 'UTF-8')) ?></td></tr>
        <tr><td>Filière</td><td>:</td><td><?= h($filiere) ?></td></tr>
        <tr><td>Niveau</td><td>:</td><td><?= h($niveau) ?></td></tr>
        <tr><td>Année de Formation</td><td>:</td><td><?= h($annee) ?></td></tr>
    </table>

    <table class="grades-table">
        <thead>
            <tr>
                <th rowspan="2" style="text-align:left;width:28%;">Unité de Formation</th>
                <th rowspan="2" style="width:30px;">Coef</th>
                <?php if ($maxC > 1): ?>
                    <th colspan="<?= $maxC ?>">Contrôles Continus</th>
                <?php else: ?>
                    <th rowspan="2">Contrôle<br>Continu</th>
                <?php endif; ?>
                <th colspan="2">Examen Fin de Cursus</th>
                <th rowspan="2">Moyenne<br>U.F.</th>
                <th rowspan="2">Observations</th>
            </tr>
            <?php if ($maxC > 1): ?>
            <tr>
                <?php for ($i = 1; $i <= $maxC; $i++): ?>
                    <th>C<?= $i ?></th>
                <?php endfor; ?>
                <th>Théorique</th>
                <th>Pratique</th>
            </tr>
            <?php else: ?>
            <tr>
                <th>Théorique</th>
                <th>Pratique</th>
            </tr>
            <?php endif; ?>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="module-name"><?= h($r['nom_module']) ?></td>
                <td class="coeff"><?= $r['coefficient'] ?></td>
                <?php for ($i = 1; $i <= $maxC; $i++):
                    $cv = $r['controles'][$i]; ?>
                    <?php if ($cv === false): ?>
                        <td class="na">—</td>
                    <?php else: ?>
                        <td><?= $fmtNote($cv) ?></td>
                    <?php endif; ?>
                <?php endfor; ?>
                <td><?= $fmtNote($r['theorique']) ?></td>
                <td><?= $fmtNote($r['pratique']) ?></td>
                <td><?= $fmtNote($r['moyenne']) ?></td>
                <td><?= $getObs($r['moyenne']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="<?= $totalCols ?>" style="text-align:center;font-style:italic;padding:12px;">Aucun module trouvé pour cette filière.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="bottom-row">
                <td class="module-name" colspan="2">Moyennes</td>
                <?php for ($i = 1; $i <= $maxC; $i++): ?>
                    <td><?= $fmtNote($footC[$i]) ?></td>
                <?php endfor; ?>
                <td><?= $fmtNote($footT) ?></td>
                <td><?= $fmtNote($footP) ?></td>
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
