<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$listMoisNav = date('Y-m');
if (isset($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['mois'])) {
    $listMoisNav = (string) $_GET['mois'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_mensualite'])) {
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mois = (string) ($_POST['mois_ref'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = $listMoisNav;
        }
        if ($sid <= 0) {
            flash_set('Stagiaire invalide.');
            redirect('stagiaires.php?mois=' . urlencode($mois) . '#liste-stagiaires');
        }
        $toPaid = isset($_POST['to_paid']) && (string) ($_POST['to_paid'] ?? '') === '1';
        $pdo->prepare(
            'INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, marque_le) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE est_paye = VALUES(est_paye), marque_le = NOW()'
        )->execute([$sid, $mois, $toPaid ? 1 : 0]);
        flash_set($toPaid ? ('Cotisation marquée payée pour ' . $mois . '.') : ('Cotisation marquée non payée pour ' . $mois . '.'));
        redirect('stagiaires.php?mois=' . urlencode($mois) . '#liste-stagiaires');
    }
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM stagiaires WHERE id_stagiaire = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Stagiaire supprimé (lignes liées en cascade).');
        $lm = (string) ($_POST['list_mois'] ?? '');
        $redirMois = preg_match('/^\d{4}-\d{2}$/', $lm) ? $lm : $listMoisNav;
        redirect('stagiaires.php?mois=' . urlencode($redirMois) . '#liste-stagiaires');
    }
    if (isset($_POST['save'])) {
        $mat = trim((string) ($_POST['matricule'] ?? ''));
        $cin = trim((string) ($_POST['cin'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $dn = ($_POST['date_naissance'] ?? '') === '' ? null : (string) $_POST['date_naissance'];
        $adr = trim((string) ($_POST['adresse'] ?? ''));
        $em = trim((string) ($_POST['email'] ?? ''));
        $emNull = $em === '' ? null : $em;
        $tel = trim((string) ($_POST['telephone'] ?? ''));
        $telp = trim((string) ($_POST['telephone_parent'] ?? ''));
        $tuteur = trim((string) ($_POST['nom_tuteur'] ?? ''));
        $pw = (string) ($_POST['mot_de_passe'] ?? '');
        $photo = trim((string) ($_POST['photo'] ?? ''));
        $di = (string) ($_POST['date_inscription'] ?? '');
        $cid = (int) ($_POST['id_classe'] ?? 0);
        if ($nom === '' || $prenom === '' || $di === '' || $cid <= 0) {
            flash_set('Nom, prénom, date inscription et classe requis.');
            $lm = (string) ($_POST['list_mois'] ?? '');
            $redirMois = preg_match('/^\d{4}-\d{2}$/', $lm) ? $lm : $listMoisNav;
            redirect('stagiaires.php?mois=' . urlencode($redirMois));
        }
        $pwHash = $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null;
        $telpNull = $telp === '' ? null : $telp;
        $tuteurNull = $tuteur === '' ? null : $tuteur;

        if (isset($_POST['id_stagiaire']) && (int) $_POST['id_stagiaire'] > 0) {
            $id = (int) $_POST['id_stagiaire'];
            if ($mat === '') {
                $cur = $pdo->prepare('SELECT matricule FROM stagiaires WHERE id_stagiaire = ?');
                $cur->execute([$id]);
                $row = $cur->fetch();
                $mat = (string) ($row['matricule'] ?? '');
            }
            $sql = 'UPDATE stagiaires SET matricule=?, cin=?, nom=?, prenom=?, date_naissance=?, adresse=?, email=?, telephone=?, telephone_parent=?, nom_tuteur=?, photo=?, date_inscription=?, id_classe=?';
            $params = [$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $telpNull, $tuteurNull, $photo === '' ? null : $photo, $di, $cid];
            if ($pwHash) {
                $sql .= ', mot_de_passe=? WHERE id_stagiaire=?';
                $params[] = $pwHash;
                $params[] = $id;
            } else {
                $sql .= ' WHERE id_stagiaire=?';
                $params[] = $id;
            }
            $pdo->prepare($sql)->execute($params);
            flash_set('Stagiaire mis à jour.');
        } else {
            $hash = $pwHash ?? password_hash('changeme', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO stagiaires (matricule, cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $telpNull, $tuteurNull, $hash, $photo === '' ? null : $photo, $di, $cid]);
            flash_set('Stagiaire créé (matricule vide = auto). Mot de passe par défaut « changeme » si vide.');
        }
        $lm = (string) ($_POST['list_mois'] ?? '');
        $redirMois = preg_match('/^\d{4}-\d{2}$/', $lm) ? $lm : $listMoisNav;
        redirect('stagiaires.php?mois=' . urlencode($redirMois));
    }
}

$curPage = 'stagiaires';
$pageTitle = 'Stagiaires';
require __DIR__ . '/includes/header.php';

$classes = $pdo->query('SELECT c.id_classe, c.nom_classe, c.annee_scolaire, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY c.annee_scolaire, c.nom_classe')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stagiaires WHERE id_stagiaire = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$filieresList = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$rows = $pdo->query('SELECT v.*, c.id_filiere FROM v_stagiaires_detail v JOIN classes c ON c.id_classe = v.id_classe ORDER BY v.nom, v.prenom')->fetchAll();
$mensPaid = [];
if ($rows) {
    $ids = array_map(static fn ($r) => (int) $r['id_stagiaire'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id_stagiaire, est_paye FROM mensualites WHERE mois_ref = ? AND id_stagiaire IN ($placeholders)");
    $st->execute(array_merge([$listMoisNav], $ids));
    foreach ($st->fetchAll() as $m) {
        $mensPaid[(int) $m['id_stagiaire']] = (int) $m['est_paye'] === 1;
    }
}
?>
<div class="card">
<form method="post" class="compact">
    <fieldset>
        <legend><?= $edit ? 'Modifier' : 'Ajouter' ?> un stagiaire</legend>
        <input type="hidden" name="list_mois" value="<?= h($listMoisNav) ?>">
        <?php if ($edit): ?><input type="hidden" name="id_stagiaire" value="<?= (int) $edit['id_stagiaire'] ?>"><?php endif; ?>
        <label>Matricule (vide = auto) <input name="matricule" value="<?= h((string) ($edit['matricule'] ?? '')) ?>"></label>
        <label>CIN <input name="cin" value="<?= h((string) ($edit['cin'] ?? '')) ?>"></label>
        <label>Nom <input name="nom" required value="<?= h((string) ($edit['nom'] ?? '')) ?>"></label>
        <label>Prénom <input name="prenom" required value="<?= h((string) ($edit['prenom'] ?? '')) ?>"></label>
        <label>Date naissance <input type="date" name="date_naissance" value="<?= h((string) ($edit['date_naissance'] ?? '')) ?>"></label>
        <label>Adresse <input name="adresse" value="<?= h((string) ($edit['adresse'] ?? '')) ?>"></label>
        <label>Email <input name="email" type="email" value="<?= h((string) ($edit['email'] ?? '')) ?>"></label>
        <label>Téléphone stagiaire <input name="telephone" value="<?= h((string) ($edit['telephone'] ?? '')) ?>"></label>
        <label>Téléphone parent / tuteur <input name="telephone_parent" value="<?= h((string) ($edit['telephone_parent'] ?? '')) ?>"></label>
        <label>Nom du père ou tuteur <input name="nom_tuteur" value="<?= h((string) ($edit['nom_tuteur'] ?? '')) ?>"></label>
        <label>Mot de passe <?= $edit ? '(vide = inchangé)' : '(vide = changeme)' ?> <input name="mot_de_passe" type="password" autocomplete="new-password"></label>
        <label>Photo (chemin/URL) <input name="photo" value="<?= h((string) ($edit['photo'] ?? '')) ?>"></label>
        <label>Date inscription <input type="date" name="date_inscription" required value="<?= h((string) ($edit['date_inscription'] ?? date('Y-m-d'))) ?>"></label>
        <label>Classe
            <select name="id_classe" required>
                <option value=""></option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int) $c['id_classe'] ?>" <?= ($edit && (int)$edit['id_classe'] === (int)$c['id_classe']) ? 'selected' : '' ?>><?= h($c['nom_classe'] . ' — ' . $c['annee_scolaire'] . ' (' . $c['nom_filiere'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" name="save" value="1" class="btn">Enregistrer</button>
        <?php if ($edit): ?> <a class="btn secondary" href="stagiaires.php?mois=<?= urlencode($listMoisNav) ?>">Annuler</a><?php endif; ?>
    </fieldset>
</form>
</div>
<div class="card no-print">
    <form method="get" action="print_liste_stagiaires.php" target="_blank" class="compact">
        <fieldset>
            <legend>Imprimer la liste des stagiaires</legend>
            <label>Filière
                <select name="id_filiere">
                    <option value="">— Toutes —</option>
                    <?php foreach ($pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere') as $fp): ?>
                        <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fp['nom_filiere']) . ' — ' . gds_fix_text((string) $fp['nom_filiere'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Classe
                <select name="id_classe">
                    <option value="">— Toutes —</option>
                    <?php foreach ($classes as $cp): ?>
                        <option value="<?= (int) $cp['id_classe'] ?>"><?= h($cp['nom_classe'] . ' — ' . $cp['annee_scolaire'] . ' (' . gds_filiere_code((string) $cp['nom_filiere']) . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tri
                <select name="sort">
                    <option value="nom">Ordre alphabétique (Nom)</option>
                    <option value="matricule">Par matricule (Code)</option>
                    <option value="filiere">Par filière</option>
                    <option value="classe">Par classe</option>
                </select>
            </label>
            <button type="submit" class="btn">Imprimer la liste</button>
        </fieldset>
    </form>
</div>
<div class="card" id="liste-stagiaires">
<h2 class="no-print" style="margin:0 0 0.5rem;font-size:1.1rem;">Liste des stagiaires</h2>
<p class="no-print" style="margin:0 0 1rem;color:var(--muted);font-size:0.95rem;">
    Cotisation mensuelle (sans échéances Merise) : choisissez le mois à afficher, filtrez et triez la liste librement.
</p>
<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">Filtrer la liste</h3>
        <span class="gds-filter-bar__count">Affichés : <strong id="flt-stag-count"><?= count($rows) ?></strong> / <?= count($rows) ?></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>Filière</span>
            <select id="flt-stag-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieresList as $fp): ?>
                    <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fp['nom_filiere']) . ' — ' . gds_fix_text((string) $fp['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Classe</span>
            <select id="flt-stag-classe">
                <option value="">— Toutes —</option>
                <?php foreach ($classes as $cp): ?>
                    <option value="<?= (int) $cp['id_classe'] ?>"><?= h($cp['nom_classe'] . ' — ' . $cp['annee_scolaire'] . ' (' . gds_filiere_code((string) $cp['nom_filiere']) . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <input id="flt-stag-search" type="search" placeholder="Nom, prénom ou matricule…">
        </label>
        <form method="get" action="stagiaires.php#liste-stagiaires" class="gds-filter-bar__form">
            <label class="gds-filter-bar__field">
                <span>Mois affiché (cotisation)</span>
                <input type="month" name="mois" value="<?= h($listMoisNav) ?>" onchange="this.form.submit()">
            </label>
        </form>
        <label class="gds-filter-bar__field">
            <span>Tri</span>
            <select id="flt-stag-sort">
                <option value="nom"       data-sort-key="name">Ordre alphabétique (Nom)</option>
                <option value="matricule" data-sort-key="matricule">Par matricule (Code)</option>
                <option value="id"        data-sort-key="id" data-sort-num="1">Par ID</option>
                <option value="filiere"   data-sort-key="filierename">Par filière</option>
                <option value="classe"    data-sort-key="classename">Par classe</option>
            </select>
        </label>
    </div>
</section>
<table class="data" id="liste-stagiaires-table">
    <thead>
    <tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Classe</th><th>Filière</th><th>Cotisation <?= h($listMoisNav) ?></th><th class="no-print"></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
        $sid = (int) $r['id_stagiaire'];
        $paid = $mensPaid[$sid] ?? false;
        $rowName = (string) $r['nom'] . ' ' . (string) $r['prenom'];
        ?>
        <tr data-filterable="1"
            data-id="<?= $sid ?>"
            data-filiere="<?= (int) ($r['id_filiere'] ?? 0) ?>"
            data-filierename="<?= h((string) $r['nom_filiere']) ?>"
            data-classe="<?= (int) ($r['id_classe'] ?? 0) ?>"
            data-classename="<?= h((string) $r['nom_classe']) ?>"
            data-name="<?= h($rowName) ?>"
            data-matricule="<?= h((string) $r['matricule']) ?>">
            <td><?= $sid ?></td>
            <td><?= h((string) $r['matricule']) ?></td>
            <td><?= h($rowName) ?></td>
            <td><?= h((string) $r['nom_classe']) ?></td>
            <td><?= h((string) $r['nom_filiere']) ?></td>
            <td class="no-print">
                <form method="post" action="stagiaires.php?mois=<?= urlencode($listMoisNav) ?>#liste-stagiaires" class="inline">
                    <input type="hidden" name="toggle_mensualite" value="1">
                    <input type="hidden" name="id_stagiaire" value="<?= $sid ?>">
                    <input type="hidden" name="mois_ref" value="<?= h($listMoisNav) ?>">
                    <?php if (!$paid): ?>
                        <button type="submit" name="to_paid" value="1" class="btn">Payé ce mois</button>
                    <?php else: ?>
                        <span class="badge badge-ok" style="margin-right:0.5rem;">Payé</span>
                        <button type="submit" name="to_paid" value="0" class="btn secondary">Non payé</button>
                    <?php endif; ?>
                </form>
            </td>
            <td class="link-row no-print">
                <a href="stagiaires.php?edit=<?= $sid ?>&amp;mois=<?= urlencode($listMoisNav) ?>">Modifier</a>
                <a href="documents_officiels.php?id=<?= $sid ?>">Docs</a>
                <form class="inline" method="post" onsubmit="return confirm('Supprimer ce stagiaire ?');">
                    <input type="hidden" name="list_mois" value="<?= h($listMoisNav) ?>">
                    <input type="hidden" name="delete_id" value="<?= $sid ?>">
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
        table: '#liste-stagiaires-table',
        counter: '#flt-stag-count',
        controls: [
            { selector: '#flt-stag-filiere', field: 'filiere', type: 'equals' },
            { selector: '#flt-stag-classe',  field: 'classe',  type: 'equals' },
            { selector: '#flt-stag-search',  field: 'search',  type: 'contains', searchFields: ['name', 'matricule'] },
            { selector: '#flt-stag-sort',    field: 'sort',    type: 'sort' }
        ]
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
