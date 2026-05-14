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

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag = $pdo->query('SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM absences WHERE id_absence = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$rows = $pdo->query('SELECT a.*, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere, m.nom_module FROM absences a JOIN stagiaires s ON s.id_stagiaire=a.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere LEFT JOIN modules m ON m.id_module=a.id_module ORDER BY a.date_absence DESC')->fetchAll();
// Count absences per stagiaire so each row can be tagged with a 'volume' level
$absCounts = [];
foreach ($pdo->query('SELECT id_stagiaire, COUNT(*) AS n FROM absences GROUP BY id_stagiaire') as $cr) {
    $absCounts[(int) $cr['id_stagiaire']] = (int) $cr['n'];
}
?>
<div class="card">
<form method="post" class="compact" data-filiere-form="true">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> une absence (pointage CDC §4.1)</legend>
        <?php if ($edit): ?><input type="hidden" name="id_absence" value="<?= (int) $edit['id_absence'] ?>"><?php endif; ?>
        <label>Filière (filtre)
            <select data-role="filiere-filter">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Date <input type="date" name="date_absence" required value="<?= h((string) ($edit['date_absence'] ?? date('Y-m-d'))) ?>"></label>
        <label>Heure début <input type="time" name="heure_debut" value="<?= h(substr((string)($edit['heure_debut'] ?? ''), 0, 5)) ?>"></label>
        <label>Heure fin <input type="time" name="heure_fin" value="<?= h(substr((string)($edit['heure_fin'] ?? ''), 0, 5)) ?>"></label>
        <label>Justificatif <input name="justificatif" value="<?= h((string) ($edit['justificatif'] ?? '')) ?>"></label>
        <label><input type="checkbox" name="est_justifiee" value="1" <?= ($edit && (int)$edit['est_justifiee']) ? 'checked' : '' ?>> Est justifiée</label>
        <label>Stagiaire
            <select name="id_stagiaire" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Module (optionnel — cours concerné)
            <select name="id_module" data-filiere-filter="true">
                <option value="">—</option>
                <?php foreach ($mods as $m): ?>
                    <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>" <?= ($edit && (int)($edit['id_module'] ?? 0) === (int)$m['id_module']) ? 'selected' : '' ?>><?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="absences.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Filtrer les absences</h3>
        <span class="gds-filter-bar__count">Affichées : <strong id="flt-abs-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-abs-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-abs-search" type="search" placeholder="Nom, prénom ou matricule…">
        </label>
        <label class="gds-filter-bar__field">
            <span>Statut</span>
            <select id="flt-abs-statut">
                <option value="">— Toutes —</option>
                <option value="1">Justifiées</option>
                <option value="0">Non justifiées</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Absentéisme</span>
            <select id="flt-abs-level">
                <option value="">— Tous —</option>
                <option value="0-2">Peu (&lt; 3)</option>
                <option value="3-6">Moyen (3 – 6)</option>
                <option value="7-99999">Beaucoup (&gt; 6)</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Tri</span>
            <select id="flt-abs-sort">
                <option value="date_desc"  data-sort-key="date" data-sort-dir="desc">Plus récentes d'abord</option>
                <option value="date_asc"   data-sort-key="date" data-sort-dir="asc">Plus anciennes d'abord</option>
                <option value="name"       data-sort-key="name">Nom du stagiaire</option>
                <option value="count_desc" data-sort-key="abscount" data-sort-num="1" data-sort-dir="desc">Volume d'absences ↓</option>
                <option value="count_asc"  data-sort-key="abscount" data-sort-num="1" data-sort-dir="asc">Volume d'absences ↑</option>
            </select>
        </label>
    </div>
</section>
<table class="data" id="liste-abs-table">
    <thead>
    <tr><th>ID</th><th>Date</th><th>Filière</th><th>Module</th><th>Justifiée</th><th>Stagiaire</th><th class="no-print"></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
        $rowSid = (int) $r['id_stagiaire'];
        $rowName = (string) $r['nom'] . ' ' . (string) $r['prenom'];
        $absN = $absCounts[$rowSid] ?? 0;
        ?>
        <tr data-filterable="1"
            data-id="<?= (int) $r['id_absence'] ?>"
            data-filiere="<?= (int) ($r['id_filiere'] ?? 0) ?>"
            data-statut="<?= (int) $r['est_justifiee'] ?>"
            data-level="<?= $absN ?>"
            data-abscount="<?= $absN ?>"
            data-date="<?= h((string) $r['date_absence']) ?>"
            data-name="<?= h($rowName) ?>"
            data-matricule="<?= h((string) $r['matricule']) ?>">
            <td><?= (int) $r['id_absence'] ?></td>
            <td><?= h((string) $r['date_absence']) ?></td>
            <td><?= h(gds_filiere_code((string) $r['nom_filiere'])) ?></td>
            <td><?= h((string) ($r['nom_module'] ?? '—')) ?></td>
            <td><?= (int) $r['est_justifiee'] ? 'oui' : 'non' ?></td>
            <td><?= h((string) $r['matricule'] . ' — ' . $rowName) ?></td>
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
    </tbody>
</table>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.gdsTableFilter) return;
    window.gdsTableFilter({
        table: '#liste-abs-table',
        counter: '#flt-abs-count',
        controls: [
            { selector: '#flt-abs-filiere', field: 'filiere', type: 'equals' },
            { selector: '#flt-abs-statut',  field: 'statut',  type: 'equals' },
            { selector: '#flt-abs-level',   field: 'level',   type: 'range' },
            { selector: '#flt-abs-search',  field: 'search',  type: 'contains', searchFields: ['name', 'matricule'] },
            { selector: '#flt-abs-sort',    field: 'sort',    type: 'sort' }
        ]
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
