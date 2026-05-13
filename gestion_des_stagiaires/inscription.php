<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$isPublic = true;
$curPage = 'inscription';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $cin = trim((string) ($_POST['cin'] ?? ''));
    $dn = ($_POST['date_naissance'] ?? '') === '' ? null : (string) $_POST['date_naissance'];
    $adr = trim((string) ($_POST['adresse'] ?? ''));
    $em = trim((string) ($_POST['email'] ?? ''));
    $tel = trim((string) ($_POST['telephone'] ?? ''));
    $telp = trim((string) ($_POST['telephone_parent'] ?? ''));
    $tuteur = trim((string) ($_POST['nom_tuteur'] ?? ''));
    $cid = (int) ($_POST['id_classe'] ?? 0);
    if ($nom === '' || $prenom === '' || $cid <= 0) {
        flash_set('Nom, prénom et classe demandée sont obligatoires.');
        redirect('inscription.php');
    }
    $emNull = $em === '' ? null : $em;
    $pdo->prepare(
        'INSERT INTO demandes_inscription (cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $cin === '' ? null : $cin,
        $nom,
        $prenom,
        $dn,
        $adr === '' ? null : $adr,
        $emNull,
        $tel === '' ? null : $tel,
        $telp === '' ? null : $telp,
        $tuteur === '' ? null : $tuteur,
        $cid,
    ]);
    flash_set('Demande envoyée. Elle sera examinée par l’administration : vous n’apparaîtrez dans la liste des stagiaires qu’après validation.');
    redirect('inscription.php');
}

$pageTitle = 'Candidature en ligne';
require __DIR__ . '/includes/header.php';

$classes = $pdo->query('SELECT c.id_classe, c.nom_classe, c.annee_scolaire, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY c.annee_scolaire, c.nom_classe')->fetchAll();
?>
<div class="card public-card">
    <p style="color:var(--muted);margin-top:0;">Espace <strong>candidat</strong> (CDC §4.1 — interface d’inscription en ligne). Votre dossier reste <strong>en attente</strong> jusqu’à validation par la secrétaire ; vous ne recevrez un compte stagiaire dans l’application qu’après acceptation.</p>
    <form method="post" class="compact">
        <fieldset>
            <legend>Identité</legend>
            <label>Nom <input name="nom" required></label>
            <label>Prénom <input name="prenom" required></label>
            <label>CIN <input name="cin"></label>
            <label>Date de naissance <input type="date" name="date_naissance"></label>
            <label>Adresse <input name="adresse"></label>
            <label>Email <input name="email" type="email"></label>
            <label>Téléphone stagiaire <input name="telephone"></label>
            <label>Téléphone parent <input name="telephone_parent"></label>
            <label>Nom du père / tuteur <input name="nom_tuteur"></label>
            <label>Classe souhaitée
                <select name="id_classe" required>
                    <option value=""></option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int) $c['id_classe'] ?>"><?= h($c['nom_classe'] . ' — ' . $c['annee_scolaire'] . ' (' . $c['nom_filiere'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn" style="margin-top:0.75rem;">Envoyer la demande</button>
        </fieldset>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
