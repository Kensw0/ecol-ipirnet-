<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'moyennes';
$pageTitle = 'Moyennes par module (calcul automatique)';
require __DIR__ . '/includes/header.php';

// Moyenne par stagiaire et module, calculée depuis evaluer via v_moyennes_par_module.
$rows = $pdo->query(
    "SELECT s.matricule, s.nom, s.prenom, v.nom_module, v.moyenne, v.nb_evaluations
       FROM v_moyennes_par_module v
       JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire
      ORDER BY s.nom, v.nom_module"
)->fetchAll();
?>
<div class="card">
    <p style="margin:0 0 1rem;color:var(--muted);">Moyenne = AVG(<code>valeur_note</code>) par stagiaire et module, calculée directement sur la table <code>evaluer</code>.</p>
    <table class="data">
        <tr><th>Stagiaire</th><th>Matricule</th><th>Module</th><th>Moyenne</th><th>Nb évaluations</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
                <td><?= h((string) $r['matricule']) ?></td>
                <td><?= h(gds_module_label((string) $r['nom_module'])) ?></td>
                <td><strong><?= h((string) $r['moyenne']) ?></strong></td>
                <td><?= (int) $r['nb_evaluations'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="5"><em>Aucune évaluation enregistrée pour le moment.</em></td></tr>
        <?php endif; ?>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
