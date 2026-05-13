<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM absences WHERE id_absence = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Absence supprimée.');
        redirect('absences.php');
    }
    if (isset($_POST['save'])) {
        $da = (string) ($_POST['date_absence'] ?? '');
        $hd = ($_POST['heure_debut'] ?? '') === '' ? null : (string) $_POST['heure_debut'];
        $hf = ($_POST['heure_fin'] ?? '') === '' ? null : (string) $_POST['heure_fin'];
        $ju = trim((string) ($_POST['justificatif'] ?? ''));
        $ej = isset($_POST['est_justifiee']) ? 1 : 0;
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid = ($_POST['id_module'] ?? '') === '' ? null : (int) $_POST['id_module'];
        if ($da === '' || $sid <= 0) {
            flash_set('Date et stagiaire requis.');
            redirect('absences.php');
        }
        if (isset($_POST['id_absence']) && (int) $_POST['id_absence'] > 0) {
            $pdo->prepare('UPDATE absences SET date_absence=?, heure_debut=?, heure_fin=?, justificatif=?, est_justifiee=?, id_stagiaire=?, id_module=? WHERE id_absence=?')
                ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid, (int) $_POST['id_absence']]);
            flash_set('Absence mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO absences (date_absence, heure_debut, heure_fin, justificatif, est_justifiee, id_stagiaire, id_module) VALUES (?,?,?,?,?,?,?)')
                ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid]);
            flash_set('Absence ajoutée.');
        }
        redirect('absences.php');
    }
}

$curPage = 'absences';
$pageTitle = 'Absences';
require __DIR__ . '/includes/header.php';

$stag = $pdo->query('SELECT id_stagiaire, matricule, nom, prenom FROM stagiaires ORDER BY nom, prenom')->fetchAll();
$mods = $pdo->query('SELECT id_module, nom_module FROM modules ORDER BY nom_module')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM absences WHERE id_absence = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$rows = $pdo->query('SELECT a.*, s.matricule, s.nom, m.nom_module FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire LEFT JOIN modules m ON m.id_module=a.id_module ORDER BY a.date_absence DESC')->fetchAll();
?>
<div class="card">
<form method="post" class="compact">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> une absence (pointage CDC §4.1)</legend>
        <?php if ($edit): ?><input type="hidden" name="id_absence" value="<?= (int) $edit['id_absence'] ?>"><?php endif; ?>
        <label>Date <input type="date" name="date_absence" required value="<?= h((string) ($edit['date_absence'] ?? date('Y-m-d'))) ?>"></label>
        <label>Heure début <input type="time" name="heure_debut" value="<?= h(substr((string)($edit['heure_debut'] ?? ''), 0, 5)) ?>"></label>
        <label>Heure fin <input type="time" name="heure_fin" value="<?= h(substr((string)($edit['heure_fin'] ?? ''), 0, 5)) ?>"></label>
        <label>Justificatif <input name="justificatif" value="<?= h((string) ($edit['justificatif'] ?? '')) ?>"></label>
        <label><input type="checkbox" name="est_justifiee" value="1" <?= ($edit && (int)$edit['est_justifiee']) ? 'checked' : '' ?>> Est justifiée</label>
        <label>Stagiaire
            <select name="id_stagiaire" required>
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Module (optionnel — cours concerné)
            <select name="id_module">
                <option value="">—</option>
                <?php foreach ($mods as $m): ?>
                    <option value="<?= (int) $m['id_module'] ?>" <?= ($edit && (int)($edit['id_module'] ?? 0) === (int)$m['id_module']) ? 'selected' : '' ?>><?= h((string)$m['nom_module']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="absences.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<table class="data">
    <tr><th>ID</th><th>Date</th><th>Module</th><th>Justifiée</th><th>Stagiaire</th><th class="no-print"></th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) $r['id_absence'] ?></td>
            <td><?= h((string) $r['date_absence']) ?></td>
            <td><?= h((string) ($r['nom_module'] ?? '—')) ?></td>
            <td><?= (int) $r['est_justifiee'] ? 'oui' : 'non' ?></td>
            <td><?= h((string) $r['matricule']) ?></td>
            <td class="link-row no-print">
                <a href="print_billet_excuse.php?id=<?= (int) $r['id_absence'] ?>" target="_blank">Billet d’excuse</a>
                <a href="absences.php?edit=<?= (int) $r['id_absence'] ?>">Modifier</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="delete_id" value="<?= (int) $r['id_absence'] ?>">
                    <button type="submit" class="btn danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
