<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'moyennes';
$pageTitle = 'Moyennes par module (calcul automatique)';
require __DIR__ . '/includes/header.php';

// Moyenne par stagiaire et module, calculée directement sur la table g_notes
// (source unique des notes depuis la consolidation « Notes » → g_notes).
$rows = $pdo->query(
    "SELECT s.matricule, s.nom, s.prenom, m.nom_module,\n"
    . "        ROUND(AVG(g.moyenne_synthese), 2) AS moyenne,\n"
    . "        COUNT(g.id_g_note) AS nb_evaluations\n"
    . "   FROM g_notes g\n"
    . "   JOIN stagiaires s ON s.id_stagiaire = g.id_stagiaire\n"
    . "   JOIN modules    m ON m.id_module    = g.id_module\n"
    . "  WHERE g.moyenne_synthese IS NOT NULL\n"
    . "  GROUP BY s.id_stagiaire, m.id_module\n"
    . "  ORDER BY s.nom, m.nom_module"
)->fetchAll();
?>
<div class="card">
    <p style="margin:0 0 1rem;color:var(--muted);">Moyenne = AVG(<code>moyenne_synthese</code>) par stagiaire et module, calculée sur la table <code>g_notes</code> (CDC : calcul automatique des moyennes).</p>
    <table class="data">
        <tr><th>Stagiaire</th><th>Matricule</th><th>Module</th><th>Moyenne</th><th>Nb notes</th></tr>
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
            <tr><td colspan="5"><em>Aucune note de synthèse enregistrée pour le moment.</em></td></tr>
        <?php endif; ?>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
