<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$SCHOOL_ORG         = 'Groupe IPIRNET';
$SCHOOL_TAGLINE_1   = "Institut Privé d'Informatique, Réseau et Nouvelles Etudes de Télécommunication";
$SCHOOL_AUTH_LINE_1 = "Autorisé par l'Etat sous N°: 03/02/2003  Du : 19/02/2003";
$SCHOOL_AUTH_LINE_2 = "Accrédité par l'Etat sous N° : 21/DFP/F0301/199  du : 29/11/2021";

$idClasse   = (int)($_GET['id_classe']   ?? 0);
$idModule   = (int)($_GET['id_module']   ?? 0);
$controleNo = max(1, (int)($_GET['controle_no'] ?? 1));

// ── Class info ─────────────────────────────────────────────────────────────
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

// ── Module info + nb_controles ─────────────────────────────────────────────
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
$controleNo   = max(1, min($nb_controles, $controleNo));
$controleType = "controle_$controleNo";

// ── Stagiaires + contrôle + théorique + pratique ───────────────────────────
$stagiaires = [];
if ($idClasse > 0) {
    $sql = '
        SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
               ev_c.note  AS note_controle,
               ev_t.note  AS note_theorique,
               ev_p.note  AS note_pratique
        FROM stagiaires s
        LEFT JOIN module_notes ev_c
               ON ev_c.id_stagiaire = s.id_stagiaire
              AND ev_c.id_module    = ?
              AND ev_c.type         = ?
        LEFT JOIN module_notes ev_t
               ON ev_t.id_stagiaire = s.id_stagiaire
              AND ev_t.id_module    = ?
              AND ev_t.type         = \'theorique\'
        LEFT JOIN module_notes ev_p
               ON ev_p.id_stagiaire = s.id_stagiaire
              AND ev_p.id_module    = ?
              AND ev_p.type         = \'pratique\'
        WHERE s.id_classe = ?
        ORDER BY s.nom, s.prenom
    ';
    $st = $pdo->prepare($sql);
    $st->execute([$idModule ?: 0, $controleType, $idModule ?: 0, $idModule ?: 0, $idClasse]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stagiaires[] = [
            'num_inscri'     => $row['num_inscri'],
            'full_name'      => strtoupper(trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''))),
            'note_controle'  => $row['note_controle'],
            'note_theorique' => $row['note_theorique'],
            'note_pratique'  => $row['note_pratique'],
        ];
    }
}

$fmtNote = static function ($v): string {
    if ($v === null) return '';
    return number_format((float)$v, 2, '.', '');
};
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tableau de Notes — Contrôle <?= $controleNo ?> — IPIRNET</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: #f1f3f5; }
        body { padding: 18px 0 40px; font-family: "Arial", "Helvetica", sans-serif; color: #111; font-size: 10.5pt; }
        .doc { max-width: 800px; margin: 0 auto; background: #fff; padding: 22px 28px 24px; box-shadow: 0 4px 14px rgba(0,0,0,.08); border: 1px solid #cdd0d4; }

        .print-btns { text-align: center; margin-bottom: 14px; display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .print-btns button, .print-btns a {
            background: #f4f4f5; border: 1px solid #ccc;
            padding: .35rem .9rem; border-radius: 8px;
            font-size: .85rem; cursor: pointer;
            text-decoration: none; color: #111;
        }
        .print-btns button:hover, .print-btns a:hover { background: #e4e4e7; }

        .controle-tab { border: 1px solid #bbb; border-radius: 6px; padding: .28rem .75rem; font-size: .82rem; text-decoration: none; color: #444; background: #f6f6f6; }
        .controle-tab.active { background: #111; color: #fff; border-color: #111; font-weight: 700; }

        .lh-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .lh-table td { border: 1px solid #111; padding: 7px 10px; vertical-align: middle; }
        .lh-left, .lh-right { width: 16%; text-align: center; }
        .lh-mid  { text-align: center; }
        .lh-logo { max-width: 80px; max-height: 80px; }
        .lh-org  { font-weight: 700; font-size: 1.2rem; letter-spacing: .03em; }
        .lh-tag  { font-size: .8rem; margin-top: 2px; }
        .lh-auth { font-size: .73rem; margin-top: 3px; }

        .doc-title    { text-align: center; font-size: 1.15rem; font-weight: 700; margin: 12px 0 3px; letter-spacing: .02em; text-decoration: underline; text-underline-offset: 3px; }
        .doc-subtitle { text-align: center; font-size: .9rem; margin-bottom: 10px; color: #333; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: .9rem; }
        .meta-table td { padding: 3px 4px; vertical-align: middle; white-space: nowrap; }
        .meta-table .lbl { font-weight: 700; padding-right: 4px; }
        .meta-table .val { border-bottom: 1px solid #111; min-width: 140px; padding: 0 4px 1px; }
        .meta-table .val.wide { min-width: 200px; }
        .meta-table .spacer { width: 30px; }

        .notes-table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 10pt; }
        .notes-table th, .notes-table td { border: 1px solid #111; padding: 4px 6px; vertical-align: middle; }
        .notes-table thead th { background: #f0f0f0; font-weight: 700; text-align: center; font-size: 9.5pt; }
        .notes-table td.code-col { text-align: center; width: 110px; font-size: 9pt; }
        .notes-table td.name-col { font-weight: 600; }
        .notes-table td.note-col { text-align: center; width: 72px; }
        .notes-table td.obs-col  { width: 26%; }
        .notes-table tbody tr { height: 22px; }

        .sign-table { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .sign-table th { border: 1px solid #111; padding: 5px 10px; text-align: center; font-weight: 400; font-style: italic; font-size: .9rem; background: #fafafa; width: 50%; }
        .sign-table td { border: 1px solid #111; height: 80px; width: 50%; }

        @media print {
            html, body { background: #fff; padding: 0; }
            .doc { box-shadow: none; border: none; padding: 0; max-width: none; margin: 0; }
            .no-print { display: none !important; }
            .notes-table thead th { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="doc">

    <!-- Print / nav buttons -->
    <div class="print-btns no-print">
        <?php if ($nb_controles > 1): ?>
            <?php for ($i = 1; $i <= $nb_controles; $i++): ?>
            <a href="?id_classe=<?= $idClasse ?>&id_module=<?= $idModule ?>&controle_no=<?= $i ?>"
               class="controle-tab <?= $i === $controleNo ? 'active' : '' ?>">
               Contrôle <?= $i ?>
            </a>
            <?php endfor; ?>
            <span style="border-left:1px solid #ccc;margin:0 4px;"></span>
        <?php endif; ?>
        <button onclick="window.print()">🖨️ Imprimer</button>
        <a href="javascript:history.back()">← Retour</a>
    </div>

    <!-- Letterhead -->
    <table class="lh-table">
        <tr>
            <td class="lh-left">
                <img src="assets/img/logo.png" alt="IPIRNET" class="lh-logo">
            </td>
            <td class="lh-mid">
                <div class="lh-org"><?= h($SCHOOL_ORG) ?></div>
                <div class="lh-tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
                <div class="lh-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
                <div class="lh-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
            </td>
            <td class="lh-right">
                <img src="assets/img/stamp_accredite.jpg" alt="Accrédité" style="width:76px;height:76px;object-fit:contain;border-radius:50%;">
            </td>
        </tr>
    </table>

    <div class="doc-title">Tableau de Notes de Contrôle</div>
    <div class="doc-subtitle">Contrôle N° <?= $controleNo ?></div>

    <!-- Meta -->
    <table class="meta-table">
        <tr>
            <td class="lbl">Filière :</td>
            <td class="val wide"><?= h((string)($classeInfo['nom_filiere'] ?? '')) ?></td>
            <td class="spacer"></td>
            <td class="lbl">Niveau :</td>
            <td class="val"><?= h((string)($classeInfo['niveau'] ?? '')) ?></td>
        </tr>
        <tr style="height:5px;"></tr>
        <tr>
            <td class="lbl">U.F. :</td>
            <td class="val wide"><?= h($moduleName) ?></td>
            <td class="spacer"></td>
            <td class="lbl">Formateur :</td>
            <td class="val"></td>
        </tr>
        <tr style="height:5px;"></tr>
        <tr>
            <td class="lbl">Classe :</td>
            <td class="val"><?= h((string)($classeInfo['nom_classe'] ?? '')) ?></td>
            <td class="spacer"></td>
            <td class="lbl">Année :</td>
            <td class="val"><?= h((string)($classeInfo['annee_scolaire'] ?? '')) ?></td>
        </tr>
    </table>

    <!-- Notes table -->
    <table class="notes-table">
        <thead>
            <tr>
                <th style="width:110px;">Code</th>
                <th>Prénom &amp; Nom Stagiaire</th>
                <th style="width:72px;">Contrôle <?= $controleNo ?></th>
                <th style="width:72px;">Théorique</th>
                <th style="width:72px;">Pratique</th>
                <th>Observation</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($stagiaires)): ?>
            <tr><td colspan="6" style="text-align:center;font-style:italic;padding:14px;">Aucun stagiaire.</td></tr>
        <?php else: ?>
            <?php foreach ($stagiaires as $s): ?>
            <tr>
                <td class="code-col"><?= h((string)($s['num_inscri'] ?? '')) ?></td>
                <td class="name-col"><?= h(trim((string)($s['full_name'] ?? ''))) ?></td>
                <td class="note-col"><?= $fmtNote($s['note_controle']) ?></td>
                <td class="note-col"><?= $fmtNote($s['note_theorique']) ?></td>
                <td class="note-col"><?= $fmtNote($s['note_pratique']) ?></td>
                <td class="obs-col"></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < max(0, 28 - count($stagiaires)); $i++): ?>
            <tr>
                <td class="code-col">&nbsp;</td>
                <td class="name-col">&nbsp;</td>
                <td class="note-col">&nbsp;</td>
                <td class="note-col">&nbsp;</td>
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

</div>
</body>
</html>
