<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM evaluer WHERE id_eval = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Évaluation supprimée.');
        redirect('evaluer.php');
    }
    if (isset($_POST['save'])) {
        $note     = ($_POST['valeur_note'] ?? '') === '' ? null : (float) str_replace(',', '.', (string) $_POST['valeur_note']);
        $type     = trim((string) ($_POST['type_evaluation'] ?? ''));
        $date     = (string) ($_POST['date_evaluation'] ?? '');
        $sid      = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid      = (int) ($_POST['id_module'] ?? 0);
        $com      = trim((string) ($_POST['commentaire'] ?? ''));

        if ($note === null || $type === '' || $date === '' || $sid <= 0 || $mid <= 0) {
            flash_set('Note, type, date, stagiaire et module sont requis.');
            redirect('evaluer.php');
        }
        if (isset($_POST['id_eval']) && (int) $_POST['id_eval'] > 0) {
            $pdo->prepare('UPDATE evaluer SET valeur_note=?, type_evaluation=?, date_evaluation=?, id_stagiaire=?, id_module=?, commentaire=? WHERE id_eval=?')
                ->execute([$note, $type, $date, $sid, $mid, $com === '' ? null : $com, (int) $_POST['id_eval']]);
            flash_set('Évaluation mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO evaluer (valeur_note, type_evaluation, date_evaluation, id_stagiaire, id_module, commentaire) VALUES (?,?,?,?,?,?)')
                ->execute([$note, $type, $date, $sid, $mid, $com === '' ? null : $com]);
            flash_set('Évaluation ajoutée.');
        }
        redirect('evaluer.php');
    }
}

$curPage   = 'evaluer';
$pageTitle = 'Évaluations (Notes)';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag     = $pdo->query('SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods     = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM evaluer WHERE id_eval = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query(
    'SELECT e.*, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere, m.nom_module
       FROM evaluer e
       JOIN stagiaires s ON s.id_stagiaire = e.id_stagiaire
       JOIN classes c    ON c.id_classe    = s.id_classe
       JOIN filieres f   ON f.id_filiere   = c.id_filiere
       JOIN modules m    ON m.id_module    = e.id_module
      ORDER BY e.date_evaluation DESC'
)->fetchAll();
?>
<div class="card">
<form method="post" class="compact" data-filiere-form="true">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> une évaluation</legend>
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
        <label>Stagiaire
            <select name="id_stagiaire" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>"
                        <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>>
                        <?= h($s['matricule'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Module
            <select name="id_module" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($mods as $m): ?>
                    <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>"
                        <?= ($edit && (int)$edit['id_module'] === (int)$m['id_module']) ? 'selected' : '' ?>>
                        <?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Type d'évaluation
            <select name="type_evaluation" required>
                <?php
                $types = ['controle', 'examen', 'projet', 'tp', 'oral', 'autre'];
                $selType = (string) ($edit['type_evaluation'] ?? 'controle');
                foreach ($types as $t):
                ?>
                    <option value="<?= h($t) ?>" <?= $selType === $t ? 'selected' : '' ?>><?= h(ucfirst($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Note / 20
            <input name="valeur_note" type="number" step="0.01" min="0" max="20" required
                   value="<?= $edit ? h((string) $edit['valeur_note']) : '' ?>">
        </label>
        <label>Date évaluation
            <input type="date" name="date_evaluation" required
                   value="<?= h((string) ($edit['date_evaluation'] ?? date('Y-m-d'))) ?>">
        </label>
        <label>Commentaire
            <textarea name="commentaire" rows="2" cols="60"><?= h((string) ($edit['commentaire'] ?? '')) ?></textarea>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="evaluer.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>

<div class="card">
<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Filtrer les évaluations</h3>
        <span class="gds-filter-bar__count">Affichées : <strong id="flt-ev-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-ev-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Type</span>
            <select id="flt-ev-type">
                <option value="">— Tous —</option>
                <option value="controle">Contrôle</option>
                <option value="examen">Examen</option>
                <option value="projet">Projet</option>
                <option value="tp">TP</option>
                <option value="oral">Oral</option>
                <option value="autre">Autre</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-ev-search" type="search" placeholder="Nom, prénom, matricule, module…">
        </label>
    </div>
</section>
<table class="data" id="evaluer-table">
    <thead>
        <tr>
            <th>Stagiaire</th>
            <th>Matricule</th>
            <th>Module</th>
            <th>Type</th>
            <th>Note / 20</th>
            <th>Date</th>
            <th>Commentaire</th>
            <th class="no-print">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
            <tr
                data-filiere="<?= (int) $r['id_filiere'] ?>"
                data-type="<?= h((string) $r['type_evaluation']) ?>"
                data-search="<?= h(strtolower($r['nom'] . ' ' . $r['prenom'] . ' ' . $r['matricule'] . ' ' . $r['nom_module'])) ?>">
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
                <td><?= h((string) $r['matricule']) ?></td>
                <td><?= h(gds_module_label((string) $r['nom_module'])) ?></td>
                <td><?= h(ucfirst((string) $r['type_evaluation'])) ?></td>
                <td><strong><?= h((string) $r['valeur_note']) ?></strong></td>
                <td><?= h((string) $r['date_evaluation']) ?></td>
                <td><?= h((string) ($r['commentaire'] ?? '')) ?></td>
                <td class="no-print">
                    <a class="btn btn--sm btn--ghost" href="evaluer.php?edit=<?= (int) $r['id_eval'] ?>">Modifier</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette évaluation ?')">
                        <input type="hidden" name="delete_id" value="<?= (int) $r['id_eval'] ?>">
                        <button type="submit" class="btn btn--sm btn--danger">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="8"><em>Aucune évaluation enregistrée.</em></td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<script>
(function() {
    var table   = document.getElementById('evaluer-table');
    var fFil    = document.getElementById('flt-ev-filiere');
    var fType   = document.getElementById('flt-ev-type');
    var fSearch = document.getElementById('flt-ev-search');
    var counter = document.getElementById('flt-ev-count');
    if (!table) return;
    function applyFilters() {
        var fil    = fFil    ? fFil.value    : '';
        var typ    = fType   ? fType.value   : '';
        var search = fSearch ? fSearch.value.toLowerCase() : '';
        var rows   = table.querySelectorAll('tbody tr[data-filiere]');
        var shown  = 0;
        rows.forEach(function(row) {
            var ok = true;
            if (fil    && row.dataset.filiere !== fil)                     ok = false;
            if (typ    && row.dataset.type    !== typ)                     ok = false;
            if (search && row.dataset.search.indexOf(search) === -1)       ok = false;
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (counter) counter.textContent = shown;
    }
    [fFil, fType, fSearch].forEach(function(el) { if (el) el.addEventListener('input', applyFilters); });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
