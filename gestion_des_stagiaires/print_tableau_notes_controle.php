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

$idClasse   = (int)($_GET['id_classe']   ?? 0);
$idModule   = (int)($_GET['id_module']   ?? 0);
$controleNo = max(1, (int)($_GET['controle_no'] ?? 1));
$modeVierge = !empty($_GET['vierge']);   // fiche vierge — aucune donnée chargée

// ── Class info ────────────────────────────────────────────────────────────
$classeInfo = null;
if ($idClasse > 0) {
    $st = $pdo->prepare(
        "SELECT c.nom_classe, c.annee_scolaire, c.niveau, f.nom_filiere
         FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
         WHERE c.id_classe = ?"
    );
    $st->execute([$idClasse]);
    $classeInfo = $st->fetch();
}

// ── Module info + nb_controles ────────────────────────────────────────────
$moduleName   = '';
$nb_controles = 1;
if ($idModule > 0) {
    $st = $pdo->prepare("SELECT nom_module, nb_controles FROM modules WHERE id_module = ?");
    $st->execute([$idModule]);
    $mod = $st->fetch();
    if ($mod) {
        $moduleName   = (string)$mod['nom_module'];
        $nb_controles = max(1, (int)$mod['nb_controles']);
    }
}
// Clamp requested controle_no to what the module declares
$controleNo = max(1, min($nb_controles, $controleNo));
$controleType = "controle_$controleNo";

// ── Stagiaires + note for the requested controle ──────────────────────────
$stagiaires = [];
if ($idClasse > 0) {
    $sql = 'SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, ev.note'
         . ' FROM stagiaires s'
         . ' LEFT JOIN module_notes ev'
         . '   ON ev.id_stagiaire = s.id_stagiaire'
         . '  AND ev.id_module = ?'
         . '  AND ev.type = ?'
         . ' WHERE s.id_classe = ?'
         . ' ORDER BY s.nom, s.prenom';
    $st = $pdo->prepare($sql);
    $st->execute([$idModule ?: 0, $controleType, $idClasse]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $stagiaires[] = [
            'num_inscri' => $row['num_inscri'],
            'full_name'  => strtoupper(trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''))),
            'note'       => $row['note'],
        ];
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tableau de Notes de Contrôle <?= $controleNo > 1 ? "N°$controleNo" : '' ?> — IPIRNET</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: #f1f3f5; }
        body { padding: 18px 0 40px; font-family: "Arial", "Helvetica", sans-serif; color: #111; font-size: 11pt; }
        .doc { max-width: 800px; margin: 0 auto; background: #fff; padding: 22px 28px 24px; box-shadow: 0 4px 14px rgba(0,0,0,.08); border: 1px solid #cdd0d4; }

        .print-btns { text-align: center; margin-bottom: 14px; display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .print-btns button, .print-btns a {
            background: #f4f4f5; border: 1px solid #ccc;
            padding: .35rem .9rem; border-radius: 8px;
            font-size: .85rem; cursor: pointer;
            text-decoration: none; color: #111;
        }
        .print-btns button:hover, .print-btns a:hover { background: #e4e4e7; }

        /* ===== En-tête 3 colonnes (identique aux autres pages d'impression) ===== */
        .cs-head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cs-head td { border: 1px solid #111; padding: 7px 10px; vertical-align: middle; text-align: center; }
        .cs-head .cs-head-left, .cs-head .cs-head-right { width: 16%; }
        .cs-head-logo { max-width: 80px; max-height: 80px; }
        .cs-head-mid .cs-org { font-weight: 700; font-size: 1.3rem; letter-spacing: .03em; }
        .cs-head-mid .cs-tag { font-size: .82rem; margin-top: 2px; }
        .cs-head-mid .cs-auth { font-size: .75rem; margin-top: 3px; }

        /* ===== Pied de page ===== */
        .cs-footer { border-top: 1px solid #111; padding-top: 6px; margin-top: 16px; text-align: center; font-size: .78rem; line-height: 1.4; }

        .doc-title { text-align: center; font-size: 1.2rem; font-weight: 700; margin: 14px 0 4px; letter-spacing: .02em; text-decoration: underline; text-underline-offset: 3px; }
        .doc-subtitle { text-align: center; font-size: .95rem; margin-bottom: 10px; color: #333; }

        .controle-tabs { display: flex; gap: 6px; justify-content: flex-end; margin-bottom: 8px; flex-wrap: wrap; }
        .controle-tab { border: 1px solid #bbb; border-radius: 6px; padding: .28rem .75rem; font-size: .82rem; text-decoration: none; color: #444; background: #f6f6f6; }
        .controle-tab.active { background: #111; color: #fff; border-color: #111; font-weight: 700; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: .92rem; }
        .meta-table td { padding: 3px 4px; vertical-align: middle; white-space: nowrap; }
        .meta-table .lbl { font-weight: 700; padding-right: 4px; }
        .meta-table .val { border-bottom: 1px solid #111; min-width: 160px; padding: 0 4px 1px; }
        .meta-table .val.wide { min-width: 220px; }
        .meta-table .spacer { width: 30px; }

        .notes-table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 10.5pt; }
        .notes-table th, .notes-table td { border: 1px solid #111; padding: 4px 8px; vertical-align: middle; }
        .notes-table thead th { background: #f0f0f0; font-weight: 700; text-align: center; font-size: 10pt; }
        .notes-table td.code-col { text-align: center; width: 130px; font-size: 9.5pt; }
        .notes-table td.name-col { font-weight: 600; }
        .notes-table td.note-col { text-align: center; width: 80px; }
        .notes-table td.obs-col  { width: 35%; }
        .notes-table tbody tr { height: 22px; }

        .sign-table { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .sign-table th { border: 1px solid #111; padding: 5px 10px; text-align: center; font-weight: 400; font-style: italic; font-size: .92rem; background: #fafafa; width: 50%; }
        .sign-table td { border: 1px solid #111; height: 80px; width: 50%; }

        @media print {
            html, body { background: #fff; padding: 0; }
            .doc { box-shadow: none; border: none; padding: 0; max-width: none; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="doc">

    <!-- Print buttons -->
    <div class="print-btns no-print">
        <?php if ($nb_controles > 1): ?>
            <?php for ($i = 1; $i <= $nb_controles; $i++): ?>
            <a href="?id_classe=<?= $idClasse ?>&id_module=<?= $idModule ?>&controle_no=<?= $i ?>"
               class="controle-tab <?= $i === $controleNo ? 'active' : '' ?>">
               Contrôle <?= $i ?>
            </a>
            <?php endfor; ?>
            <span style="border-left:1px solid #ccc; margin:0 4px;"></span>
        <?php endif; ?>
        <button onclick="window.print()">🖨️ Imprimer</button>
        <a href="javascript:history.back()">← Retour</a>
    </div>

    <!-- ===== En-tête officiel (partagé entre toutes les pages d'impression) ===== -->
    <?php require __DIR__ . '/includes/print_letterhead.php'; ?>

    <div class="doc-title">Tableau de Notes de Contrôle</div>
    <div class="doc-subtitle">Contrôle N° <?= $controleNo ?></div>

    <!-- Meta -->
    <table class="meta-table">
        <tr>
            <td class="lbl">Filière :</td>
            <td class="val wide"><?= h((string)($classeInfo['nom_filiere'] ?? '')) ?></td>
            <td class="spacer"></td>
            <td class="lbl">Niveau</td>
            <td class="val"><?= h((string)($classeInfo['niveau'] ?? '')) ?></td>
        </tr>
        <tr style="height:6px;"></tr>
        <tr>
            <td class="lbl">U.F. :</td>
            <td class="val wide"><?= h($moduleName) ?></td>
            <td class="spacer"></td>
            <td class="lbl">Formateur :</td>
            <td class="val"></td>
        </tr>
        <tr style="height:6px;"></tr>
        <tr>
            <td class="lbl">Année:</td>
            <td colspan="4"><?= h((string)($classeInfo['annee_scolaire'] ?? '')) ?></td>
        </tr>
    </table>

    <!-- Notes table -->
    <table class="notes-table">
        <thead>
            <tr>
                <th style="width:130px;">Code</th>
                <th>Prénom &amp; Nom stagiaire</th>
                <th style="width:80px;">Note</th>
                <th>Observation</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($stagiaires)): ?>
            <tr><td colspan="4" style="text-align:center;font-style:italic;padding:14px;">Aucun stagiaire.</td></tr>
        <?php else: ?>
            <?php foreach ($stagiaires as $s):
                $note = (!$modeVierge && $s['note'] !== null) ? number_format((float)$s['note'], 2) : '';
            ?>
            <tr>
                <td class="code-col"><?= h((string)($s['num_inscri'] ?? '')) ?></td>
                <td class="name-col"><?= h(trim((string)($s['full_name'] ?? ''))) ?></td>
                <td class="note-col"><?= h($note) ?></td>
                <td class="obs-col"></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < max(0, 30 - count($stagiaires)); $i++): ?>
            <tr>
                <td class="code-col">&nbsp;</td>
                <td class="name-col">&nbsp;</td>
                <td class="note-col">&nbsp;</td>
                <td class="obs-col">&nbsp;</td>
            </tr>
            <?php endfor; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature -->
    <table class="sign-table">
        <thead>
            <tr>
                <th>Signature du formateur</th>
                <th>Signature Président de jury</th>
            </tr>
        </thead>
        <tbody>
            <tr><td></td><td></td></tr>
        </tbody>
    </table>

    <!-- ===== Pied de page ===== -->
    <?php require __DIR__ . '/includes/print_footer.php'; ?>

</div>
</body>
</html>
