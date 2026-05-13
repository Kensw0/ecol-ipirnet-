<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

const GDS_FILIERE_OPTIONS = [
    'TSDI' => 'Technicien Spécialisé en Développement Informatique',
    'TGI'  => 'Technicien en Informatique de Gestion',
    'TSGE' => 'Technicien Spécialisé en Gestion des Entreprises',
    'OPAD' => 'Opérateur Administratif',
];

const GDS_MODULES_BY_CODE = [
    'TSDI' => [
        'M.F. 1.1 : Métier et formation',
        'M.F. 1.2 : L’entreprise et son environnement',
        'M.F. 1.3 : Notion de mathématique appliquée',
        'M.F. 1.4 : Gestion du temps',
        'M.F. 1.5 : Veille technologique',
        'M.F. 1.8 : Logiciel d’application',
        'M.F. 1.9 : Programmation événementielle',
        'M.F. 1.10 : Technique de programmation structurée',
        'M.F. 1.11 : Langage de programmation structurée',
        'M.F. 1.12 : Programmation orienté objet',
        'M.F. 1.13 : Concept et mod d’un system d’information',
        'M.F. 1.14 : Installation d’un poste informatique',
        'M.F. 1.15 : Communication en Anglais',
        'M.F. 1.16 : Assistant technique à la clientèle',
    ],
    'TGI' => [],
    'TSGE' => [],
    'OPAD' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_referentiel'])) {
    $addedFilieres = 0;
    $addedModules = 0;

    $pdo->beginTransaction();
    try {
        $findFiliere = $pdo->prepare('SELECT id_filiere FROM filieres WHERE nom_filiere = ? LIMIT 1');
        $insertFiliere = $pdo->prepare('INSERT INTO filieres (nom_filiere) VALUES (?)');
        $findModule = $pdo->prepare('SELECT id_module FROM modules WHERE id_filiere = ? AND nom_module = ? LIMIT 1');
        $insertModule = $pdo->prepare('INSERT INTO modules (nom_module, id_filiere) VALUES (?, ?)');

        foreach (GDS_FILIERE_OPTIONS as $code => $label) {
            $findFiliere->execute([$label]);
            $fid = $findFiliere->fetchColumn();
            if (!$fid) {
                $insertFiliere->execute([$label]);
                $fid = (int) $pdo->lastInsertId();
                $addedFilieres++;
            } else {
                $fid = (int) $fid;
            }

            foreach (GDS_MODULES_BY_CODE[$code] as $moduleName) {
                $findModule->execute([$fid, $moduleName]);
                if ($findModule->fetchColumn()) {
                    continue;
                }
                $insertModule->execute([$moduleName, $fid]);
                $addedModules++;
            }
        }

        $pdo->commit();
        flash_set("Référentiel mis à jour : {$addedFilieres} filière(s) ajoutée(s), {$addedModules} module(s) ajouté(s).");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_set('Erreur lors de la mise à jour du référentiel : ' . $e->getMessage());
    }

    redirect('referentiel.php');
}

$curPage = 'referentiel';
$pageTitle = 'Filières / modules';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$modules = $pdo->query('SELECT m.nom_module, f.nom_filiere FROM modules m JOIN filieres f ON f.id_filiere = m.id_filiere ORDER BY f.nom_filiere, m.nom_module')->fetchAll();

$byFiliere = [];
foreach ($modules as $row) {
    $byFiliere[(string) $row['nom_filiere']][] = (string) $row['nom_module'];
}
?>
<div class="card">
    <h2>Référentiel pédagogique</h2>
    <p style="margin:.5rem 0 1rem;color:var(--foreground);">
        Cette page reste dans le périmètre <strong>4.1 gestion des stagiaires</strong> : elle sert seulement à préparer les
        <strong>filières</strong> et <strong>modules</strong> utilisés par les notes, moyennes, bulletins et relevés.
    </p>
    <form method="post" class="compact" style="margin:0;">
        <fieldset>
            <legend>Initialiser les filières et modules utiles</legend>
            <p style="margin:0 0 1rem;color:var(--muted);">
                Ajoute de façon <strong>idempotente</strong> les filières <strong>TSDI / TGI / TSGE / OPAD</strong> et les modules TSDI fournis.
                Si vous cliquez plusieurs fois, il n'y aura pas de doublons.
            </p>
            <button type="submit" name="seed_referentiel" value="1" class="btn">Ajouter les filières / modules</button>
        </fieldset>
    </form>
</div>

<div class="card">
    <h2>Filières prévues</h2>
    <table class="data">
        <tr><th>Code</th><th>Libellé</th><th>Modules prévus dans cet écran</th></tr>
        <?php foreach (GDS_FILIERE_OPTIONS as $code => $label): ?>
            <tr>
                <td><?= h($code) ?></td>
                <td><?= h($label) ?></td>
                <td><?= count(GDS_MODULES_BY_CODE[$code]) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>Modules TSDI à injecter</h2>
    <table class="data">
        <tr><th>#</th><th>Module</th></tr>
        <?php foreach (GDS_MODULES_BY_CODE['TSDI'] as $idx => $moduleName): ?>
            <tr>
                <td><?= $idx + 1 ?></td>
                <td><?= h($moduleName) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2>État actuel en base</h2>
    <?php if (!$filieres): ?>
        <p><em>Aucune filière trouvée.</em></p>
    <?php else: ?>
        <?php foreach ($filieres as $f): ?>
            <section style="margin-bottom:1.25rem;">
                <h3 style="margin-bottom:.5rem;"><?= h((string) $f['nom_filiere']) ?></h3>
                <?php $list = $byFiliere[(string) $f['nom_filiere']] ?? []; ?>
                <?php if (!$list): ?>
                    <p style="margin:0;color:var(--muted);">Aucun module enregistré.</p>
                <?php else: ?>
                    <ul style="margin:0;padding-left:1.2rem;">
                        <?php foreach ($list as $moduleName): ?>
                            <li><?= h($moduleName) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
