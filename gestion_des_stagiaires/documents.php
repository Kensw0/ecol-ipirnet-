<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM documents WHERE id_doc = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Document supprimé.');
        redirect('documents.php');
    }
    if (isset($_POST['save'])) {
        $td = trim((string) ($_POST['type_doc'] ?? ''));
        $nf = trim((string) ($_POST['nom_fichier'] ?? ''));
        $ch = trim((string) ($_POST['chemin_acces'] ?? ''));
        $da = (string) ($_POST['date_ajout'] ?? '');
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        if ($td === '' || $nf === '' || $ch === '' || $da === '' || $sid <= 0) {
            flash_set('Tous les champs sont requis.');
            redirect('documents.php');
        }
        if (isset($_POST['id_doc']) && (int) $_POST['id_doc'] > 0) {
            $pdo->prepare('UPDATE documents SET type_doc=?, nom_fichier=?, chemin_acces=?, date_ajout=?, id_stagiaire=? WHERE id_doc=?')
                ->execute([$td, $nf, $ch, $da, $sid, (int) $_POST['id_doc']]);
            flash_set('Document mis à jour.');
        } else {
            $pdo->prepare('INSERT INTO documents (type_doc, nom_fichier, chemin_acces, date_ajout, id_stagiaire) VALUES (?,?,?,?,?)')
                ->execute([$td, $nf, $ch, $da, $sid]);
            flash_set('Document ajouté.');
        }
        redirect('documents.php');
    }
}

$curPage = 'documents';
$pageTitle = 'Documents administratifs';
require __DIR__ . '/includes/header.php';

$stag = $pdo->query('SELECT id_stagiaire, matricule, nom, prenom FROM stagiaires ORDER BY nom, prenom')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM documents WHERE id_doc = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT d.*, s.matricule, s.nom FROM documents d JOIN stagiaires s ON s.id_stagiaire=d.id_stagiaire ORDER BY d.date_ajout DESC')->fetchAll();
?>
<div class="card">
<form method="post" class="compact">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> un document</legend>
        <?php if ($edit): ?>
            <input type="hidden" name="id_doc" value="<?= (int) $edit['id_doc'] ?>">
        <?php endif; ?>
        <label>Type <input name="type_doc" required value="<?= h((string) ($edit['type_doc'] ?? '')) ?>"></label>
        <label>Nom fichier <input name="nom_fichier" required value="<?= h((string) ($edit['nom_fichier'] ?? '')) ?>"></label>
        <label>Chemin / URL <input name="chemin_acces" required size="70" value="<?= h((string) ($edit['chemin_acces'] ?? '')) ?>"></label>
        <label>Date ajout <input type="date" name="date_ajout" required value="<?= h((string) ($edit['date_ajout'] ?? date('Y-m-d'))) ?>"></label>
        <label>Stagiaire
            <select name="id_stagiaire" required>
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="documents.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<table class="data">
    <tr><th>ID</th><th>Type</th><th>Fichier</th><th>Chemin</th><th>Date</th><th>Stagiaire</th><th class="no-print"></th></tr>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) $r['id_doc'] ?></td>
            <td><?= h((string) $r['type_doc']) ?></td>
            <td><?= h((string) $r['nom_fichier']) ?></td>
            <td><?= h((string) $r['chemin_acces']) ?></td>
            <td><?= h((string) $r['date_ajout']) ?></td>
            <td><?= h((string) $r['matricule']) ?></td>
            <td class="link-row no-print">
                <a href="documents.php?edit=<?= (int) $r['id_doc'] ?>">Modifier</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="delete_id" value="<?= (int) $r['id_doc'] ?>">
                    <button type="submit" class="btn danger">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
