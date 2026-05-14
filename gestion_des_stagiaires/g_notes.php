<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
gds_sync_reference_data($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM g_notes WHERE id_g_note = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Note de synthèse supprimée.');
        redirect('g_notes.php');
    }
    if (isset($_POST['save'])) {
        $lib = trim((string) ($_POST['libelle'] ?? ''));
        $an = trim((string) ($_POST['annee_scolaire'] ?? ''));
        $sem = ($_POST['semestre'] ?? '') === '' ? null : (int) $_POST['semestre'];
        $moy = ($_POST['moyenne_synthese'] ?? '') === '' ? null : (float) str_replace(',', '.', (string) $_POST['moyenne_synthese']);
        $com = trim((string) ($_POST['commentaire'] ?? ''));
        $de = (string) ($_POST['date_enregistrement'] ?? '');
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mid = ($_POST['id_module'] ?? '') === '' ? null : (int) $_POST['id_module'];
        if ($lib === '' || $an === '' || $de === '' || $sid <= 0) {
            flash_set('Libellé, année, date et stagiaire requis.');
            redirect('g_notes.php');
        }
        if (isset($_POST['id_g_note']) && (int) $_POST['id_g_note'] > 0) {
            $pdo->prepare('UPDATE g_notes SET libelle=?, annee_scolaire=?, semestre=?, moyenne_synthese=?, commentaire=?, date_enregistrement=?, id_stagiaire=?, id_module=? WHERE id_g_note=?')
                ->execute([$lib, $an, $sem, $moy, $com === '' ? null : $com, $de, $sid, $mid, (int) $_POST['id_g_note']]);
            flash_set('Note de synthèse mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO g_notes (libelle, annee_scolaire, semestre, moyenne_synthese, commentaire, date_enregistrement, id_stagiaire, id_module) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$lib, $an, $sem, $moy, $com === '' ? null : $com, $de, $sid, $mid]);
            flash_set('Note de synthèse créée.');
        }
        redirect('g_notes.php');
    }
}

$curPage = 'g_notes';
$pageTitle = 'Notes';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag = $pdo->query('SELECT s.id_stagiaire, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();
$mods = $pdo->query('SELECT m.id_module, m.nom_module, f.id_filiere, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM g_notes WHERE id_g_note = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}

$rows = $pdo->query('SELECT g.*, s.matricule, s.nom, s.prenom, f.id_filiere, f.nom_filiere, m.nom_module FROM g_notes g JOIN stagiaires s ON s.id_stagiaire=g.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere LEFT JOIN modules m ON m.id_module=g.id_module ORDER BY g.date_enregistrement DESC')->fetchAll();
?>
<div class="card">
<form method="post" class="compact" data-filiere-form="true">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> une note de synthèse</legend>
        <?php if ($edit): ?>
            <input type="hidden" name="id_g_note" value="<?= (int) $edit['id_g_note'] ?>">
        <?php endif; ?>
        <label>Filière (filtre)
            <select data-role="filiere-filter">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Libellé <input name="libelle" required size="50" value="<?= h((string) ($edit['libelle'] ?? '')) ?>"></label>
        <label>Année scolaire <input name="annee_scolaire" required value="<?= h((string) ($edit['annee_scolaire'] ?? '2025-2026')) ?>"></label>
        <label>Semestre <input name="semestre" type="number" min="0" value="<?= $edit && $edit['semestre'] !== null ? (int)$edit['semestre'] : '' ?>"></label>
        <label>Moyenne synthèse <input name="moyenne_synthese" type="number" step="0.01" value="<?= $edit && $edit['moyenne_synthese'] !== null ? h((string)$edit['moyenne_synthese']) : '' ?>"></label>
        <label>Commentaire <textarea name="commentaire" rows="2" cols="60"><?= h((string) ($edit['commentaire'] ?? '')) ?></textarea></label>
        <label>Date enregistrement <input type="date" name="date_enregistrement" required value="<?= h((string) ($edit['date_enregistrement'] ?? date('Y-m-d'))) ?>"></label>
        <label>Stagiaire
            <select name="id_stagiaire" required data-filiere-filter="true">
                <option value=""></option>
                <?php foreach ($stag as $s): ?>
                    <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['matricule'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Module (vide = synthèse globale)
            <select name="id_module" data-filiere-filter="true">
                <option value="">—</option>
                <?php foreach ($mods as $m): ?>
                    <option value="<?= (int) $m['id_module'] ?>" data-filiere-id="<?= (int) $m['id_filiere'] ?>" <?= ($edit && (int)($edit['id_module'] ?? 0) === (int)$m['id_module']) ? 'selected' : '' ?>><?= h(gds_filiere_code((string) $m['nom_filiere']) . ' — ' . gds_module_label((string) $m['nom_module'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="g_notes.php">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card">
<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Filtrer les notes</h3>
        <span class="gds-filter-bar__count">Affichées : <strong id="flt-not-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-not-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $fi): ?>
                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-not-search" type="search" placeholder="Nom, prénom, matricule ou libellé…">
        </label>
        <label class="gds-filter-bar__field">
            <span>Niveau de note</span>
            <select id="flt-not-level">
                <option value="">— Toutes —</option>
                <option value="0-9.99">Faible (&lt; 10)</option>
                <option value="10-13.99">Passable (10 – 13.99)</option>
                <option value="14-15.99">Bien (14 – 15.99)</option>
                <option value="16-20">Très bien (≥ 16)</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Tri</span>
            <select id="flt-not-sort">
                <option value="date_desc" data-sort-key="date" data-sort-dir="desc">Date d'enregistrement ↓</option>
                <option value="date_asc"  data-sort-key="date" data-sort-dir="asc">Date d'enregistrement ↑</option>
                <option value="name"      data-sort-key="name">Ordre alphabétique (Nom)</option>
                <option value="matricule" data-sort-key="matricule">Par matricule</option>
                <option value="note_desc" data-sort-key="note" data-sort-num="1" data-sort-dir="desc">Note ↓ (haut → bas)</option>
                <option value="note_asc"  data-sort-key="note" data-sort-num="1" data-sort-dir="asc">Note ↑ (bas → haut)</option>
            </select>
        </label>
    </div>
</section>
<table class="data" id="liste-not-table">
    <thead>
    <tr><th>ID</th><th>Libellé</th><th>Année</th><th>Moy.</th><th>Stagiaire</th><th>Module</th><th class="no-print"></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
        $rowName = (string) $r['nom'] . ' ' . (string) $r['prenom'];
        $noteVal = $r['moyenne_synthese'] !== null ? (float) $r['moyenne_synthese'] : null;
        ?>
        <tr data-filterable="1"
            data-id="<?= (int) $r['id_g_note'] ?>"
            data-filiere="<?= (int) ($r['id_filiere'] ?? 0) ?>"
            data-name="<?= h($rowName) ?>"
            data-matricule="<?= h((string) $r['matricule']) ?>"
            data-libelle="<?= h((string) $r['libelle']) ?>"
            data-note="<?= $noteVal !== null ? h(number_format($noteVal, 2, '.', '')) : '' ?>"
            data-level="<?= $noteVal !== null ? h(number_format($noteVal, 2, '.', '')) : '-1' ?>"
            data-date="<?= h((string) $r['date_enregistrement']) ?>">
            <td><?= (int) $r['id_g_note'] ?></td>
            <td><?= h((string) $r['libelle']) ?></td>
            <td><?= h((string) $r['annee_scolaire']) ?></td>
            <td><?= h((string) ($r['moyenne_synthese'] ?? '')) ?></td>
            <td><?= h((string) $r['matricule'] . ' — ' . $rowName) ?></td>
            <td><?= h(gds_module_label((string) ($r['nom_module'] ?? '—'))) ?></td>
            <td class="link-row no-print">
                <a href="g_notes.php?edit=<?= (int) $r['id_g_note'] ?>">Modifier</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ?');">
                    <input type="hidden" name="delete_id" value="<?= (int) $r['id_g_note'] ?>">
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
        table: '#liste-not-table',
        counter: '#flt-not-count',
        controls: [
            { selector: '#flt-not-filiere', field: 'filiere', type: 'equals' },
            { selector: '#flt-not-level',   field: 'level',   type: 'range' },
            { selector: '#flt-not-search',  field: 'search',  type: 'contains', searchFields: ['name', 'matricule', 'libelle'] },
            { selector: '#flt-not-sort',    field: 'sort',    type: 'sort' }
        ]
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
