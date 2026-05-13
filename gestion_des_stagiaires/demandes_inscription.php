<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accepter_id'])) {
        $idDem = (int) $_POST['accepter_id'];
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM demandes_inscription WHERE id_demande = ? AND statut = ? FOR UPDATE');
            $st->execute([$idDem, 'en_attente']);
            $d = $st->fetch();
            if (!$d) {
                $pdo->rollBack();
                flash_set('Demande introuvable ou déjà traitée.');
                redirect('demandes_inscription.php');
            }
            $em = trim((string) ($d['email'] ?? ''));
            if ($em !== '') {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM stagiaires WHERE email = ?');
                $chk->execute([$em]);
                if ((int) $chk->fetchColumn() > 0) {
                    $pdo->rollBack();
                    flash_set('Impossible d’accepter : un stagiaire existe déjà avec cet email.');
                    redirect('demandes_inscription.php');
                }
            }
            $hash = password_hash('changeme', PASSWORD_DEFAULT);
            $di = date('Y-m-d');
            $ins = $pdo->prepare(
                'INSERT INTO stagiaires (matricule, cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                '',
                $d['cin'] ?: null,
                $d['nom'],
                $d['prenom'],
                $d['date_naissance'] ?: null,
                $d['adresse'] ?: null,
                $d['email'] ?: null,
                $d['telephone'] ?: null,
                $d['telephone_parent'] ?: null,
                $d['nom_tuteur'] ?: null,
                $hash,
                null,
                $di,
                (int) $d['id_classe'],
            ]);
            $sid = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE demandes_inscription SET statut = ?, date_decision = NOW(), id_stagiaire_cree = ? WHERE id_demande = ?'
            )->execute(['acceptee', $sid, $idDem]);
            $pdo->commit();
            flash_set('Demande acceptée — stagiaire créé (matricule auto, mot de passe provisoire « changeme »).');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('Erreur à l’acceptation (doublon email ou autre). Réessayez ou corrigez la demande.');
        }
        redirect('demandes_inscription.php');
    }
    if (isset($_POST['refuser_id'])) {
        $idDem = (int) $_POST['refuser_id'];
        $u = $pdo->prepare(
            'UPDATE demandes_inscription SET statut = ?, date_decision = NOW() WHERE id_demande = ? AND statut = ?'
        );
        $u->execute(['refusee', $idDem, 'en_attente']);
        if ($u->rowCount() > 0) {
            flash_set('Demande refusée.');
        } else {
            flash_set('Demande introuvable ou déjà traitée.');
        }
        redirect('demandes_inscription.php');
    }
}

$curPage = 'demandes';
$pageTitle = 'Demandes d’inscription';
require __DIR__ . '/includes/header.php';

$nbAttente = (int) $pdo->query("SELECT COUNT(*) FROM demandes_inscription WHERE statut = 'en_attente'")->fetchColumn();
$attente = $pdo->query(
    'SELECT d.*, c.nom_classe, c.annee_scolaire, f.nom_filiere
     FROM demandes_inscription d
     JOIN classes c ON c.id_classe = d.id_classe
     JOIN filieres f ON f.id_filiere = c.id_filiere
     WHERE d.statut = \'en_attente\'
     ORDER BY d.date_soumission ASC'
)->fetchAll();
$traitees = $pdo->query(
    'SELECT d.*, c.nom_classe, f.nom_filiere
     FROM demandes_inscription d
     JOIN classes c ON c.id_classe = d.id_classe
     JOIN filieres f ON f.id_filiere = c.id_filiere
     WHERE d.statut != \'en_attente\'
     ORDER BY d.date_decision DESC LIMIT 40'
)->fetchAll();
?>
<div class="card">
    <p style="margin:0;color:var(--muted);font-size:0.95rem;">Les candidatures envoyées depuis <a href="inscription.php">inscription.php</a> (sans compte admin) restent ici jusqu’à <strong>Accepter</strong> (crée le stagiaire) ou <strong>Refuser</strong>.</p>
    <p style="margin:0.5rem 0 0;"><strong>En attente :</strong> <?= (string) $nbAttente ?></p>
</div>
<div class="card">
    <h2>À traiter</h2>
    <table class="data">
        <tr><th>Date</th><th>Nom</th><th>Classe</th><th>Email</th><th class="no-print"></th></tr>
        <?php foreach ($attente as $r): ?>
            <tr>
                <td><?= h((string) $r['date_soumission']) ?></td>
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
                <td><?= h((string) $r['nom_classe'] . ' — ' . (string) $r['nom_filiere']) ?></td>
                <td><?= h((string) ($r['email'] ?? '')) ?></td>
                <td class="no-print link-row">
                    <form method="post" class="inline" onsubmit="return confirm('Créer le stagiaire et accepter cette demande ?');">
                        <input type="hidden" name="accepter_id" value="<?= (int) $r['id_demande'] ?>">
                        <button type="submit" class="btn">Accepter</button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('Refuser cette demande ?');">
                        <input type="hidden" name="refuser_id" value="<?= (int) $r['id_demande'] ?>">
                        <button type="submit" class="btn secondary">Refuser</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$attente): ?>
            <tr><td colspan="5">Aucune demande en attente.</td></tr>
        <?php endif; ?>
    </table>
</div>
<div class="card">
    <h2>Dernières décisions</h2>
    <table class="data">
        <tr><th>Soumission</th><th>Décision</th><th>Nom</th><th>Statut</th><th>Stagiaire</th></tr>
        <?php foreach ($traitees as $r): ?>
            <tr>
                <td><?= h((string) $r['date_soumission']) ?></td>
                <td><?= h((string) ($r['date_decision'] ?? '')) ?></td>
                <td><?= h((string) $r['nom'] . ' ' . (string) $r['prenom']) ?></td>
                <td><?= h((string) $r['statut']) ?></td>
                <td><?= $r['id_stagiaire_cree'] ? '<a href="stagiaires.php?edit=' . (int) $r['id_stagiaire_cree'] . '">#' . (int) $r['id_stagiaire_cree'] . '</a>' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$traitees): ?>
            <tr><td colspan="5">Aucun historique.</td></tr>
        <?php endif; ?>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
