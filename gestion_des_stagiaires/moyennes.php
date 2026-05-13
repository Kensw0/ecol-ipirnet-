<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'moyennes';
$pageTitle = 'Moyennes par module (calcul automatique)';
require __DIR__ . '/includes/header.php';

$rows = $pdo->query('SELECT v.*, s.matricule, s.nom, s.prenom FROM v_moyennes_par_module v JOIN stagiaires s ON s.id_stagiaire=v.id_stagiaire ORDER BY s.nom, v.nom_module')->fetchAll();
?>
<div class="card">
    <p style="margin:0 0 1rem;color:var(--muted);">Vue SQL <code>v_moyennes_par_module</code> — moyenne = AVG(<code>valeur_note</code>) par stagiaire et module (CDC : calcul automatique des moyennes).</p>
    <table class="data">
        <tr><th>Stagiaire</th><th>Matricule</th><th>Module</th><th>Moyenne</th><th>Nb évals</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
                <td><?= h((string) $r['matricule']) ?></td>
                <td><?= h((string) $r['nom_module']) ?></td>
                <td><strong><?= h((string) $r['moyenne']) ?></strong></td>
                <td><?= (int) $r['nb_evaluations'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
