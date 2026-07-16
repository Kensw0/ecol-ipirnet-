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

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Identifiant manquant.'); }

$st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire=?');
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); exit('Stagiaire introuvable.'); }

log_document_gen($pdo, 'rapport_individuel', $id, (string) $s['num_inscri']);

$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

$fmtFr = static function (?string $d): string {
    if (!$d) return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d/m/Y', $t);
};

$fmtNote = static function ($v): string {
    if ($v === null || $v === '') return '';
    return number_format((float) $v, 2, ',', '');
};

$annee      = (string) ($s['annee_scolaire'] ?? '');
$niveau     = (string) ($s['niveau'] ?? ($s['nom_classe'] ?? ''));
$nomComplet = trim((string) $s['nom'] . ' ' . (string) $s['prenom']);
$filiere    = mb_strtoupper((string) ($s['nom_filiere'] ?? ''), 'UTF-8');

// ── Fetch notes (via vue v_moyennes_par_module) ───────────────────────────────
$stNotes = $pdo->prepare(
    "SELECT v.note_controle, v.note_theorique, v.note_pratique,
            v.moyenne_module, v.nom_module, v.coefficient
     FROM v_moyennes_par_module v
     WHERE v.id_stagiaire = ?
     ORDER BY v.nom_module"
);
$stNotes->execute([$id]);
$notes = $stNotes->fetchAll();

$sumCoef = 0.0; $sumWeighted = 0.0;
foreach ($notes as &$n) {
    $moy = $n['moyenne_module'] !== null ? (float) $n['moyenne_module'] : null;
    $n['_moy'] = $moy;
    $c = (float) ($n['coefficient'] ?? 1);
    if ($moy !== null) { $sumCoef += $c; $sumWeighted += $moy * $c; }
}
unset($n);
$moyenneGen = $sumCoef > 0 ? round($sumWeighted / $sumCoef, 2) : null;
$decision   = $moyenneGen !== null ? ($moyenneGen >= 10 ? 'Admis(e)' : 'Ajourne(e)') : 'En attente';

// ── Fetch absences ───────────────────────────────────────────────────────────
$stAbs = $pdo->prepare(
    "SELECT a.date_absence, a.heure_debut, a.heure_fin, a.est_justifiee, a.justificatif,
            m.nom_module
     FROM absences a
     LEFT JOIN modules m ON m.id_module = a.id_module
     WHERE a.id_stagiaire = ?
     ORDER BY a.date_absence ASC, a.heure_debut ASC"
);
$stAbs->execute([$id]);
$absences  = $stAbs->fetchAll();
$absTotal  = count($absences);
$absJust   = (int) array_sum(array_column($absences, 'est_justifiee'));
$absInjust = $absTotal - $absJust;

// ── Fetch payments ───────────────────────────────────────────────────────────
$stPaie = $pdo->prepare(
    "SELECT mois_ref, montant_total, montant_paye, montant_restant, statut_paiement, date_paiement
     FROM mensualites
     WHERE id_stagiaire = ?
     ORDER BY mois_ref ASC"
);
$stPaie->execute([$id]);
$paiements    = $stPaie->fetchAll();
$totalDu      = array_sum(array_column($paiements, 'montant_paye'))
              + array_sum(array_column($paiements, 'montant_restant')); // net after remise
$totalPaye    = array_sum(array_column($paiements, 'montant_paye'));
$totalRestant = array_sum(array_column($paiements, 'montant_restant'));
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapport Individuel — <?= h($nomComplet) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        @media print { .doc-wrapper { padding: 10mm !important; } }
        * { box-sizing: border-box; }
        html, body { background: #e5e7eb; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #000; font-size: 11pt; padding: 20px 0; }

        .cs-print-btns { text-align: center; margin: 0 auto 20px auto; max-width: 800px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .cs-print-btns button, .cs-print-btns a { background: #f4f4f5; border: 1px solid #ccc; padding: 8px 16px; border-radius: 6px; font-size: 14px; cursor: pointer; text-decoration: none; color: #111; margin: 0 5px; font-family: sans-serif; transition: all 0.2s; }
        .cs-print-btns a:hover, .cs-print-btns button:hover { background: #e4e4e7; }

        .doc-wrapper { max-width: 820px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }

        /* ===== En-tête 3 colonnes (identique aux autres pages d'impression) ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .cs-head td { border: 1px solid #111; padding: 8px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 18%; }
        .cs-head-logo { max-width: 90px; max-height: 90px; display: inline-block; }
        .cs-head-mid .cs-org { font-weight: 700; font-size: 1.6rem; letter-spacing: 0.03em; }
        .cs-head-mid .cs-tag { font-style: italic; font-size: .95rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .8rem; margin-top: 4px; }

        /* Document title */
        .eval-title { text-align: center; text-transform: uppercase; font-weight: bold; font-size: 15px; text-decoration: underline; margin-bottom: 20px; line-height: 1.5; }

        /* Identity info table */
        .info-table { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 20px; font-weight: bold; font-size: 13px; }
        .info-table td { border: 1px solid #000; padding: 6px 10px; }
        .info-table td:first-child { width: 200px; background: #f2f2f2; text-align: center; }
        .info-table td:nth-child(2) { width: 10px; text-align: center; border-left: none; border-right: none; }
        .info-table td:last-child { border-left: none; }

        /* Section heading */
        .section-heading { font-weight: bold; font-size: 13px; text-transform: uppercase; text-decoration: underline; margin: 22px 0 8px; }

        /* Main data tables — identical to grades-table in releve_notes */
        .grades-table { width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 12px; margin-bottom: 6px; }
        .grades-table th, .grades-table td { border: 1px solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; }
        .grades-table thead th { background: #e8e8e8; font-weight: bold; }
        .grades-table td.module-name { text-align: left; font-weight: bold; background: #f9f9f9; }
        .grades-table td.left-align { text-align: left; }
        .bottom-row { font-weight: bold; background: #e8e8e8; }

        /* Summary line */
        .summary-line { font-size: 12px; font-weight: bold; text-align: right; margin-bottom: 4px; }

        /* Empty state */
        .empty-state { font-style: italic; font-size: 12px; color: #555; margin-bottom: 12px; }

        /* ===== Pied de page ===== */
        .cs-footer { border-top: 1px solid #111; padding-top: 6px; margin-top: 24px; text-align: center; font-size: .82rem; line-height: 1.45; }

        @media print {
            html, body { background: #fff; margin: 0; padding: 0; }
            .doc-wrapper { box-shadow: none; padding: 10px 0; }
            .cs-print-btns { display: none; }
            .grades-table thead th { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .grades-table td.module-name { background: #f9f9f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-table td:first-child { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .bottom-row { background: #e8e8e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="cs-print-btns">
    <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    <a href="stagiaires.php?id=<?= $id ?>">Retour</a>
</div>

<div class="doc-wrapper">

    <!-- ===== En-tête officiel (partagé entre toutes les pages d'impression) ===== -->
    <?php require __DIR__ . '/includes/print_letterhead.php'; ?>

    <!-- ===== Document title ===== -->
    <div class="eval-title">
        Fiche Recapitulative Individuelle<br>
        <span style="font-size:13px; font-weight:normal;">Formation Continue — Annee <?= h($annee) ?> — Editee le <?= date('d/m/Y') ?></span>
    </div>

    <!-- ===== Identity ===== -->
    <table class="info-table">
        <tr><td>N° d'inscription</td><td>:</td><td><?= h((string)($s['num_inscri'] ?? '')) ?></td></tr>
        <tr><td>Nom et prenom</td><td>:</td><td><?= h(mb_strtoupper($nomComplet, 'UTF-8')) ?></td></tr>
        <tr><td>CIN</td><td>:</td><td><?= h((string)($s['cin'] ?? '')) ?></td></tr>
        <tr><td>Filiere</td><td>:</td><td><?= h($filiere) ?></td></tr>
        <tr><td>Classe</td><td>:</td><td><?= h((string)($s['nom_classe'] ?? '')) ?></td></tr>
        <tr><td>Niveau</td><td>:</td><td><?= h($niveau) ?></td></tr>
        <tr><td>Annee scolaire</td><td>:</td><td><?= h($annee) ?></td></tr>
    </table>

    <!-- ===== Section 1 : Notes ===== -->
    <div class="section-heading">Notes</div>

    <?php if (empty($notes)): ?>
        <p class="empty-state">Aucune note enregistree pour ce stagiaire.</p>
    <?php else: ?>
        <table class="grades-table">
            <thead>
                <tr>
                    <th colspan="2">Unites de formation et coefficient</th>
                    <th>Controles<br>Continus</th>
                    <th>Theorique</th>
                    <th>Pratique</th>
                    <th>Moyenne<br>U.F.</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $n):
                    $moy = $n['_moy'];
                ?>
                <tr>
                    <td class="module-name"><?= h((string)$n['nom_module']) ?></td>
                    <td><?= h((string)($n['coefficient'] ?? '')) ?></td>
                    <td><?= $fmtNote($n['note_controle']) ?></td>
                    <td><?= $fmtNote($n['note_theorique']) ?></td>
                    <td><?= $fmtNote($n['note_pratique']) ?></td>
                    <td><?= $fmtNote($moy) ?></td>
                    <td>
                        <?php if ($moy !== null): ?>
                            <?= $moy >= 10 ? 'Admis' : 'Non admis' ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bottom-row">
                    <td class="module-name" colspan="5" style="text-align:right;">Moyenne generale ponderee</td>
                    <td><?= $fmtNote($moyenneGen) ?></td>
                    <td><?= $moyenneGen !== null ? ($moyenneGen >= 10 ? 'Admis' : 'Non admis') : '' ?></td>
                </tr>
                <tr class="bottom-row">
                    <td class="module-name" colspan="6" style="text-align:right;">Decision du jury</td>
                    <td><?= h($decision) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- ===== Section 2 : Absences ===== -->
    <div class="section-heading">Absences</div>

    <?php if (empty($absences)): ?>
        <p class="empty-state">Aucune absence enregistree pour ce stagiaire.</p>
    <?php else: ?>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Debut</th>
                    <th>Fin</th>
                    <th>Module</th>
                    <th>Statut</th>
                    <th>Justificatif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($absences as $a): ?>
                <tr>
                    <td><?= h($fmtFr((string)($a['date_absence'] ?? ''))) ?></td>
                    <td><?= h(substr((string)($a['heure_debut'] ?? ''), 0, 5) ?: '—') ?></td>
                    <td><?= h(substr((string)($a['heure_fin']   ?? ''), 0, 5) ?: '—') ?></td>
                    <td class="left-align"><?= h((string)($a['nom_module'] ?? '—')) ?></td>
                    <td><?= (int)$a['est_justifiee'] ? 'Justifiee' : 'Injustifiee' ?></td>
                    <td class="left-align"><?= h((string)($a['justificatif'] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bottom-row">
                    <td colspan="4" style="text-align:right;">Total</td>
                    <td colspan="2"><?= $absTotal ?> absence<?= $absTotal > 1 ? 's' : '' ?> — <?= $absJust ?> justifiee<?= $absJust > 1 ? 's' : '' ?>, <?= $absInjust ?> injustifiee<?= $absInjust > 1 ? 's' : '' ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- ===== Section 3 : Paiements ===== -->
    <div class="section-heading">Paiements</div>

    <?php if (empty($paiements)): ?>
        <p class="empty-state">Aucun enregistrement de paiement pour ce stagiaire.</p>
    <?php else: ?>
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Du (MAD)</th>
                    <th>Paye (MAD)</th>
                    <th>Restant (MAD)</th>
                    <th>Statut</th>
                    <th>Date paiement</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paiements as $p):
                    $sp = (string)($p['statut_paiement'] ?? '');
                    $statutLabel = match($sp) {
                        'paye'    => 'Paye',
                        'payé'    => 'Paye',
                        'partiel' => 'Partiel',
                        default   => 'Impaye',
                    };
                ?>
                <tr>
                    <td><?= h((string)($p['mois_ref'] ?? '')) ?></td>
                    <td><?= $fmtNote($p['montant_total'])   ?></td>
                    <td><?= $fmtNote($p['montant_paye'])    ?></td>
                    <td><?= $fmtNote($p['montant_restant']) ?></td>
                    <td><?= h($statutLabel) ?></td>
                    <td><?= h($fmtFr((string)($p['date_paiement'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bottom-row">
                    <td style="text-align:right;">Totaux</td>
                    <td><?= $fmtNote($totalDu) ?></td>
                    <td><?= $fmtNote($totalPaye) ?></td>
                    <td><?= $fmtNote($totalRestant) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- ===== Pied de page ===== -->
    <?php require __DIR__ . '/includes/print_footer.php'; ?>

</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 200); });</script>
<?php endif; ?>
</body>
</html>
