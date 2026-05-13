<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
gds_sync_reference_data($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM evaluer WHERE id_eval = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Ligne évaluation supprimée.');
        redirect('evaluer.php');
    }
    if (isset($_POST['save'])) {
        $val = (float) str_replace(',', '.', (string) ($_POST['valeur_note'] ?? '0'));
        $type = trim((string) ($_POST['type_evaluation'] ?? ''));
        $de = (string) ($_POST['date_evaluation'] ?? '');
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid = (int) ($_POST['id_module'] ?? 0);
        $com = trim((string) ($_POST['commentaire'] ?? ''));
        if ($type === '' || $de === '' || $sid <= 0 || $mid <= 0) {
            flash_set('Champs requis manquants.');
            redirect('evaluer.php');
        }
        if (isset($_POST['id_eval']) && (int) $_POST['id_eval'] > 0) {
            $pdo->prepare('UPDATE evaluer SET valeur_note=?, type_evaluation=?, date_evaluation=?, id_stagiaire=?, id_module=?, commentaire=? WHERE id_eval=?')
                ->execute([$val, $type, $de, $sid, $mid, $com === '' ? null : $com, (int) $_POST['id_eval']]);
            flash_set('Évaluation mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO evaluer (valeur_note, type_evaluation, date_evaluation, id_stagiaire, id_module, commentaire) VALUES (?,?,?,?,?,?)')
                ->execute([$val, $type, $de, $sid, $mid, $com === '' ? null : $com]);
            flash_set('Évaluation ajoutée.');
        }
        redirect('evaluer.php');
    }
}

$curPage = 'evaluer';
$pageTitle = 'Notes';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag = $pdo->query('SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM evaluer WHERE id_eval = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT e.*, s.matricule, s.nom, s.prenom, m.nom_module, f.nom_filiere FROM evaluer e JOIN stagiaires s ON s.id_stagiaire=e.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere JOIN modules m ON m.id_module=e.id_module ORDER BY e.date_evaluation DESC')->fetchAll();
?>
<div class="card">
<form method="post" class="compact" data-filiere-form="true">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> une note</legend>
        <?php if ($edit): ?>
            <input type="hidden" name="id_eval" value="<?= (int) $edit['id_eval'] ?>">
        <?php endif; ?>
        <label>Filière (filtre)
            <select data-role="filiere-filter">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Valeur <input name="valeur_note" type="number" step="0.01" required value="<?= $edit ? h((string)$edit['valeur_note']) : '' ?>"></label>
        <label>Type (contrôle, examen, projet…) <input name="type_evaluation" required value="<?= h((string) ($edit['type_evaluation'] ?? 'controle')) ?>"></label>
        <label>Date <input type="date" name="date_evaluation" required value="<?= h((string) ($edit['date_evaluation'] ?? date('Y-m-d'))) ?>"></label>
        <label>Stagiaire
            <select name="id_stagiaire" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Module
            <select name="id_module" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($mods as $m): ?>
                    <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>" <?= ($edit && (int)$edit['id_module'] === (int)$m['id_module']) ? 'selected' : '' ?>><?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Commentaire <input name="commentaire" size="60" value="<?= h((string) ($edit['commentaire'] ?? '')) ?>"></label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="evaluer.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<table class="data">
    <tr><th>ID</th><th>Date</th><th>Note</th><th>Type</th><th>Stagiaire</th><th>Filière</th><th>Module</th><th class="no-print"></th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) $r['id_eval'] ?></td>
            <td><?= h((string) $r['date_evaluation']) ?></td>
            <td><?= h((string) $r['valeur_note']) ?></td>
            <td><?= h((string) $r['type_evaluation']) ?></td>
            <td><?= h((string) $r['matricule'] . ' ' . $r['nom']) ?></td>
            <td><?= h(gds_filiere_code((string) $r['nom_filiere'])) ?></td>
            <td><?= h(gds_module_label((string) $r['nom_module'])) ?></td>
            <td class="link-row no-print">
                <a href="evaluer.php?edit=<?= (int) $r['id_eval'] ?>">Modifier</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="delete_id" value="<?= (int) $r['id_eval'] ?>">
                    <button type="submit" class="btn danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
