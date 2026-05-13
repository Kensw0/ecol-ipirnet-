<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM stages WHERE id_stage = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Stage supprimé.');
        redirect('stages.php');
    }
    if (isset($_POST['save'])) {
        $ts = (string) ($_POST['type_stage'] ?? 'stage_entreprise');
        if (!in_array($ts, ['stage_entreprise', 'pfe'], true)) {
            $ts = 'stage_entreprise';
        }
        $su = trim((string) ($_POST['sujet'] ?? ''));
        $en = trim((string) ($_POST['entreprise'] ?? ''));
        $dd = ($_POST['date_debut'] ?? '') === '' ? null : (string) $_POST['date_debut'];
        $df = ($_POST['date_fin'] ?? '') === '' ? null : (string) $_POST['date_fin'];
        $ns = ($_POST['note_stage'] ?? '') === '' ? null : (float) str_replace(',', '.', (string) $_POST['note_stage']);
        $cu = trim((string) ($_POST['convention_url'] ?? ''));
        $ru = trim((string) ($_POST['rapport_url'] ?? ''));
        $ev = trim((string) ($_POST['evaluation_entreprise'] ?? ''));
        $ds = ($_POST['date_soutenance'] ?? '') === '' ? null : (string) $_POST['date_soutenance'];
        $ju = trim((string) ($_POST['jury'] ?? ''));
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        if ($sid <= 0) {
            flash_set('Stagiaire requis.');
            redirect('stages.php');
        }
        if (isset($_POST['id_stage']) && (int) $_POST['id_stage'] > 0) {
            $pdo->prepare('UPDATE stages SET type_stage=?, sujet=?, entreprise=?, date_debut=?, date_fin=?, note_stage=?, convention_url=?, rapport_url=?, evaluation_entreprise=?, date_soutenance=?, jury=?, id_stagiaire=? WHERE id_stage=?')
                ->execute([$ts, $su === '' ? null : $su, $en === '' ? null : $en, $dd, $df, $ns, $cu === '' ? null : $cu, $ru === '' ? null : $ru, $ev === '' ? null : $ev, $ds, $ju === '' ? null : $ju, $sid, (int) $_POST['id_stage']]);
            flash_set('Stage mis à jour.');
        } else {
            $pdo->prepare('INSERT INTO stages (type_stage, sujet, entreprise, date_debut, date_fin, note_stage, convention_url, rapport_url, evaluation_entreprise, date_soutenance, jury, id_stagiaire) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ts, $su === '' ? null : $su, $en === '' ? null : $en, $dd, $df, $ns, $cu === '' ? null : $cu, $ru === '' ? null : $ru, $ev === '' ? null : $ev, $ds, $ju === '' ? null : $ju, $sid]);
            flash_set('Stage ajouté.');
        }
        redirect('stages.php');
    }
}

$curPage = 'stages';
$pageTitle = 'Stages / PFE';
require __DIR__ . '/includes/header.php';

$stag = $pdo->query('SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, f.nom_filiere, c.nom_classe FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stages WHERE id_stage = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$rows = $pdo->query('SELECT st.*, s.matricule, s.nom, s.prenom, c.nom_classe, f.nom_filiere FROM stages st JOIN stagiaires s ON s.id_stagiaire=st.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY st.date_debut DESC')->fetchAll();
?>
<div class="card">
<form method="post" class="compact">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> un stage ou PFE (CDC §4.1 / page 8)</legend>
        <?php if ($edit): ?><input type="hidden" name="id_stage" value="<?= (int) $edit['id_stage'] ?>"><?php endif; ?>
        <label>Type
            <select name="type_stage">
                <option value="stage_entreprise" <?= (!$edit || $edit['type_stage'] === 'stage_entreprise') ? 'selected' : '' ?>>Stage entreprise</option>
                <option value="pfe" <?= ($edit && $edit['type_stage'] === 'pfe') ? 'selected' : '' ?>>PFE</option>
            </select>
        </label>
        <label>Sujet <input name="sujet" value="<?= h((string) ($edit['sujet'] ?? '')) ?>"></label>
        <label>Entreprise / organisme <input name="entreprise" value="<?= h((string) ($edit['entreprise'] ?? '')) ?>"></label>
        <label>Date début <input type="date" name="date_debut" value="<?= h((string) ($edit['date_debut'] ?? '')) ?>"></label>
        <label>Date fin <input type="date" name="date_fin" value="<?= h((string) ($edit['date_fin'] ?? '')) ?>"></label>
        <label>Date soutenance (PFE) <input type="date" name="date_soutenance" value="<?= h((string) ($edit['date_soutenance'] ?? '')) ?>"></label>
        <label>Jury / modalités <textarea name="jury" rows="2" cols="50"><?= h((string) ($edit['jury'] ?? '')) ?></textarea></label>
        <label>Note stage <input name="note_stage" type="number" step="0.01" value="<?= $edit && $edit['note_stage'] !== null ? h((string)$edit['note_stage']) : '' ?>"></label>
        <label>URL convention <input name="convention_url" value="<?= h((string) ($edit['convention_url'] ?? '')) ?>"></label>
        <label>URL rapport <input name="rapport_url" value="<?= h((string) ($edit['rapport_url'] ?? '')) ?>"></label>
        <label>Évaluation entreprise <input name="evaluation_entreprise" value="<?= h((string) ($edit['evaluation_entreprise'] ?? '')) ?>"></label>
        <label>Stagiaire
            <select name="id_stagiaire" required>
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ' / ' . (string) $s['nom_classe'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="stages.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<table class="data">
    <tr><th>ID</th><th>Type</th><th>Entreprise</th><th>Dates</th><th>Soutenance</th><th>Stagiaire</th><th>Classe / filière</th><th class="no-print"></th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) $r['id_stage'] ?></td>
            <td><?= h((string) $r['type_stage']) ?></td>
            <td><?= h((string) ($r['entreprise'] ?? '')) ?></td>
            <td><?= h(trim((string)($r['date_debut'] ?? '') . ' → ' . (string)($r['date_fin'] ?? ''))) ?></td>
            <td><?= h((string) ($r['date_soutenance'] ?? '—')) ?></td>
            <td><?= h((string) $r['matricule'] . ' ' . $r['nom'] . ' ' . ($r['prenom'] ?? '')) ?></td>
            <td><?= h((string) $r['nom_classe'] . ' / ' . gds_filiere_code((string) $r['nom_filiere'])) ?></td>
            <td class="link-row no-print">
                <a href="print_convention_stage.php?id=<?= (int) $r['id_stage'] ?>" target="_blank">Convention</a>
                <a href="stages.php?edit=<?= (int) $r['id_stage'] ?>">Modifier</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="delete_id" value="<?= (int) $r['id_stage'] ?>">
                    <button type="submit" class="btn danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
