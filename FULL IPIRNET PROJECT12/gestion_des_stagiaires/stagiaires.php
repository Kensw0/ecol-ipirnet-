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
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) $mois = $listMoisNav;
        if ($sid > 0) {
            $toPaid = isset($_POST['to_paid']) && (string) ($_POST['to_paid'] ?? '') === '1';
            $pdo->prepare('INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, marque_le) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE est_paye = VALUES(est_paye), marque_le = NOW()')->execute([$sid, $mois, $toPaid ? 1 : 0]);
            flash_set($toPaid ? "Cotisation payée ($mois)." : "Cotisation impayée ($mois).");
        }
        redirect('stagiaires.php?mois=' . urlencode($mois));
    }
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM stagiaires WHERE id_stagiaire = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Stagiaire supprimé (lignes liées en cascade).');
        redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
    }
    if (isset($_POST['save'])) {
        $mat = trim((string) ($_POST['num_inscri'] ?? ''));
        $cin = trim((string) ($_POST['cin'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $dn = ($_POST['date_naissance'] ?? '') === '' ? null : (string) $_POST['date_naissance'];
        $adr = trim((string) ($_POST['adresse'] ?? ''));
        $em = trim((string) ($_POST['email'] ?? ''));
        $emNull = $em === '' ? null : $em;
        $tel = trim((string) ($_POST['telephone'] ?? ''));
        $pw = (string) ($_POST['mot_de_passe'] ?? '');
        $photo = trim((string) ($_POST['photo'] ?? ''));
        $di = (string) ($_POST['date_inscription'] ?? '');
        $cid = (int) ($_POST['id_classe'] ?? 0);
        
        $errs = [];
        if ($nom === '' || $prenom === '' || $di === '' || $cid <= 0) $errs[] = 'Nom, prénom, date inscription et classe requis';
        if (preg_match('/[0-9]/', $nom) || preg_match('/[0-9]/', $prenom)) $errs[] = 'nom/prénom sans chiffres';
        if ($cin !== '' && !preg_match('/^[a-zA-Z]{2}[0-9]/', $cin)) $errs[] = 'CIN format 2 lettres + chiffres';
        if ($tel !== '' && preg_match('/[a-zA-ZÀ-ÿ]/', $tel)) $errs[] = 'téléphone sans lettres';
        if ($photo !== '' && !preg_match('/\.(png|jpg|jpeg|gif)$/i', $photo)) $errs[] = 'photo URL (doit finir par .png ou .jpg)';
        
        if ($errs) {
            flash_set('Erreur : ' . implode(', ', $errs) . '.');
            redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
        }

        $pwHash = $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null;

        if (isset($_POST['id_stagiaire']) && (int) $_POST['id_stagiaire'] > 0) {
            $id = (int) $_POST['id_stagiaire'];
            if ($mat === '') {
                $cur = $pdo->prepare('SELECT num_inscri FROM stagiaires WHERE id_stagiaire = ?');
                $cur->execute([$id]);
                $mat = (string) ($cur->fetchColumn() ?: ''); // Fallback
            }
            $sql = 'UPDATE stagiaires SET num_inscri=?, cin=?, nom=?, prenom=?, date_naissance=?, adresse=?, email=?, telephone=?, photo=?, date_inscription=?, id_classe=?';
            $params = [$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $photo === '' ? null : $photo, $di, $cid];
            if ($pwHash) {
                $sql .= ', mot_de_passe=? WHERE id_stagiaire=?';
                $params[] = $pwHash; $params[] = $id;
            } else {
                $sql .= ' WHERE id_stagiaire=?';
                $params[] = $id;
            }
            $pdo->prepare($sql)->execute($params);
            flash_set('Stagiaire mis à jour.');
        } else {
            if ($mat === '') {
                $year = date('Y', strtotime($di));
                $st = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE num_inscri LIKE ?");
                $st->execute(['INS-' . $year . '-%']);
                $count = (int) $st->fetchColumn();
                $mat = 'INS-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
            }
            $hash = $pwHash ?? password_hash('changeme', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO stagiaires (num_inscri, cin, nom, prenom, date_naissance, adresse, email, telephone, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $hash, $photo === '' ? null : $photo, $di, $cid]);
            flash_set('Stagiaire créé avec succès (N° Inscription: ' . $mat . ').');
        }
        redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
    }
}

$curPage = 'stagiaires';
$pageTitle = 'Stagiaires';
require __DIR__ . '/includes/header.php';

$classes = $pdo->query('SELECT c.id_classe, c.nom_classe, c.annee_scolaire, f.id_filiere, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY f.nom_filiere, c.annee_scolaire, c.nom_classe')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stagiaires WHERE id_stagiaire = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$filieresList = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$rows = $pdo->query('
    SELECT v.*, c.id_filiere, 
           s.date_naissance, s.adresse, s.photo, s.email, s.telephone, s.date_inscription, s.cin 
    FROM v_stagiaires_detail v 
    JOIN classes c ON c.id_classe = v.id_classe 
    LEFT JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire 
    ORDER BY v.nom, v.prenom
')->fetchAll();
$mensPaid = [];
if ($rows) {
    $ids = array_map(static fn ($r) => (int) $r['id_stagiaire'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id_stagiaire, est_paye FROM mensualites WHERE mois_ref = ? AND id_stagiaire IN ($placeholders)");
    $st->execute(array_merge([$listMoisNav], $ids));
    foreach ($st->fetchAll() as $m) $mensPaid[(int) $m['id_stagiaire']] = (int) $m['est_paye'] === 1;
}

$classesJson = json_encode(array_map(static fn($c) => [
    'id' => (int) $c['id_classe'],
    'nom' => $c['nom_classe'] . ' — ' . $c['annee_scolaire'],
    'filiere' => (int) $c['id_filiere'],
], $classes));

$editFiliereId = 0;
if ($edit) {
    foreach ($classes as $c) {
        if ((int)$c['id_classe'] === (int)$edit['id_classe']) { $editFiliereId = (int)$c['id_filiere']; break; }
    }
}

// Function to generate Avatar colors pseudo-randomly based on string
function getAvatarColor($str) {
    $hash = md5($str);
    return '#' . substr($hash, 0, 6);
}
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Gestion des Stagiaires</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:1.5rem;">Cœur du référentiel IPIRNET : Filtrez, consultez, et gérez l'ensemble des données étudiantes.</p>

<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header" style="display:flex; justify-content:space-between; align-items:center;">
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
                    <option value="<?= (int) $cp['id_classe'] ?>" data-filiere="<?= (int) $cp['id_filiere'] ?>">
                        <?= h($cp['nom_classe'] . ' — ' . $cp['annee_scolaire'] . ' (' . gds_filiere_code((string) $cp['nom_filiere']) . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>Recherche</span>
            <div style="position:relative;">
                <input id="flt-stag-search" type="search" placeholder="Nom, prénom, N° inscription ou CIN…">
            </div>
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
                <option value="nom" data-sort-key="name">Ordre alphabétique (Nom)</option>
                <option value="num_inscri" data-sort-key="num_inscri">Par N° inscription (Code)</option>
                <option value="id" data-sort-key="id" data-sort-num="1">Par ID</option>
                <option value="filiere" data-sort-key="filierename">Par filière</option>
                <option value="classe" data-sort-key="classename">Par classe</option>
            </select>
        </label>
        <div class="gds-filter-bar__field" style="justify-content: flex-end; align-items: flex-end; display: flex;">
            <button id="btn-reset-filters" class="btn secondary" style="width:100%;"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</button>
        </div>
    </div>
</section>

<!-- Empty State -->
<div id="empty-state" class="card" style="display:none; text-align:center; padding: 4rem 1rem;">
    <i class="fa-solid fa-users-slash" style="font-size: 3rem; color: rgba(255,255,255,0.1); margin-bottom: 1rem;"></i>
    <h3 style="color: #a1a1aa; font-size: 1.2rem; margin:0;">Aucun stagiaire trouvé</h3>
    <p style="color: #71717a; font-size: 0.9rem;">Modifiez vos filtres ou effectuez une nouvelle recherche.</p>
</div>

<div class="card table-container" id="liste-stagiaires" style="padding:0; overflow:hidden;">
    <table class="data" id="liste-stagiaires-table">
        <thead>
        <tr>
            <th>N° Inscription</th>
            <th>Stagiaire</th>
            <th>Classe</th>
            <th>Statut</th>
            <th>Cotisation <?= h($listMoisNav) ?></th>
            <th class="no-print" style="text-align:right;">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $sid = (int) $r['id_stagiaire'];
            $paid = $mensPaid[$sid] ?? false;
            $nom = (string) $r['nom'];
            $prenom = (string) $r['prenom'];
            $rowName = $nom . ' ' . $prenom;
            // Generate initials
            $initials = mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1);
            $color = getAvatarColor($rowName);
            $jsonRow = htmlspecialchars(json_encode([
                'id' => $sid, 
                'num_inscri' => $r['num_inscri'] ?? '', 
                'cin' => $r['cin'] ?? '', 
                'nom' => $nom, 
                'prenom' => $prenom,
                'date_naissance' => $r['date_naissance'] ?? '', 
                'adresse' => $r['adresse'] ?? '', 
                'email' => $r['email'] ?? '',
                'telephone' => $r['telephone'] ?? '', 
                'photo' => $r['photo'] ?? '', 
                'date_inscription' => $r['date_inscription'] ?? '',
                'classe' => $r['nom_classe'] ?? '', 
                'filiere' => $r['nom_filiere'] ?? ''
            ]), ENT_QUOTES, 'UTF-8');
            ?>
            <tr data-filterable="1"
                data-id="<?= $sid ?>"
                data-filiere="<?= (int) ($r['id_filiere'] ?? 0) ?>"
                data-filierename="<?= h((string) $r['nom_filiere']) ?>"
                data-classe="<?= (int) ($r['id_classe'] ?? 0) ?>"
                data-classename="<?= h((string) $r['nom_classe']) ?>"
                data-name="<?= h($rowName) ?>"
                data-num_inscri="<?= h((string) $r['num_inscri']) ?>"
                data-cin="<?= h((string) $r['cin']) ?>"
                class="clickable-row js-open-panel"
                data-json="<?= $jsonRow ?>"
                style="cursor: pointer;">
                <td><span style="font-family:monospace; color:#a1a1aa;"><?= h((string) $r['num_inscri']) ?></span></td>
                <td>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="avatar-circle" style="background-color: <?= $color ?>20; color: <?= $color ?>; border: 1px solid <?= $color ?>50;">
                            <?= strtoupper(h($initials)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600; color:#e4e4e7;"><?= h($rowName) ?></div>
                            <div style="font-size:0.75rem; color:#71717a;"><?= h((string) $r['nom_filiere']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge" style="background:rgba(255,255,255,0.08); color:#a1a1aa; border:1px solid rgba(255,255,255,0.1);"><?= h((string) $r['nom_classe']) ?></span></td>
                <td>
                    <span class="badge badge-ok">Actif</span>
                </td>
                <td class="no-print js-ignore-click">
                    <form method="post" action="stagiaires.php?mois=<?= urlencode($listMoisNav) ?>#liste-stagiaires" class="inline" onclick="event.stopPropagation();">
                        <input type="hidden" name="toggle_mensualite" value="1">
                        <input type="hidden" name="id_stagiaire" value="<?= $sid ?>">
                        <input type="hidden" name="mois_ref" value="<?= h($listMoisNav) ?>">
                        <?php if (!$paid): ?>
                            <button type="submit" name="to_paid" value="1" class="btn secondary btn--sm">Marquer Payé</button>
                        <?php else: ?>
                            <span class="badge" style="background:rgba(52,211,153,0.15); color:#34d399; margin-right:0.5rem;"><i class="fa-solid fa-check"></i> Payé</span>
                            <button type="submit" name="to_paid" value="0" class="btn secondary btn--sm" style="opacity:0.6;">Annuler</button>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="no-print js-ignore-click" style="text-align:right;">
                    <div style="display:flex; justify-content:flex-end; gap:0.25rem;">
                        <a href="stagiaires.php?edit=<?= $sid ?>&amp;mois=<?= urlencode($listMoisNav) ?>" class="icon-btn" title="Modifier" onclick="event.stopPropagation();">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="documents_officiels.php?id=<?= $sid ?>" class="icon-btn" title="Documents officiels" onclick="event.stopPropagation();" style="color:#60a5fa;">
                            <i class="fa-solid fa-file-lines"></i>
                        </a>
                        <form class="inline" method="post" data-confirm-custom="1" data-confirm-msg="Attention : suppression irréversible de (<?= h($rowName) ?>) ainsi que toutes ses notes, absences et données associées ! Continuer ?" onclick="event.stopPropagation();">
                            <input type="hidden" name="list_mois" value="<?= h($listMoisNav) ?>">
                            <input type="hidden" name="delete_id" value="<?= $sid ?>">
                            <button type="submit" class="icon-btn danger-hover" title="Supprimer" style="background:transparent; border:none; padding:0; box-shadow:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card no-print">
    <form method="get" action="print_liste_stagiaires.php" target="_blank" class="compact" id="print-form">
        <fieldset style="margin:0;">
            <legend><i class="fa-solid fa-print"></i> Imprimer la liste des stagiaires</legend>
            <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:0.5rem;">
                <label style="flex:1;">Filière
                    <select name="id_filiere" id="print-filiere-select">
                        <option value="">— Toutes —</option>
                        <?php foreach ($filieresList as $fp): ?>
                            <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fp['nom_filiere']) . ' — ' . gds_fix_text((string) $fp['nom_filiere'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="flex:1;">Classe
                    <select name="id_classe" id="print-classe-select">
                        <option value="">— Toutes —</option>
                        <?php foreach ($classes as $cp): ?>
                            <option value="<?= (int) $cp['id_classe'] ?>" data-filiere="<?= (int) $cp['id_filiere'] ?>">
                                <?= h($cp['nom_classe'] . ' — ' . $cp['annee_scolaire'] . ' (' . gds_filiere_code((string) $cp['nom_filiere']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="flex:1;">Tri
                    <select name="sort">
                        <option value="nom">Ordre alphabétique (Nom)</option>
                        <option value="num_inscri">Par N° inscription (Code)</option>
                        <option value="filiere">Par filière</option>
                        <option value="classe">Par classe</option>
                    </select>
                </label>
            </div>
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-list-ul"></i> Liste classique</button>
                <button type="submit" class="btn secondary" formaction="print_tableau_notes.php" title="Nécessite de sélectionner une classe spécifique"><i class="fa-solid fa-border-all"></i> Feuille de contrôle</button>
            </div>
        </fieldset>
    </form>
</div>

<!-- Floating Action Button -->
<button id="fab-add" class="fab-button no-print" title="Ajouter un nouveau stagiaire">
   <i class="fa-solid fa-plus"></i> <span style="margin-left:0.5rem;font-size:0.9rem;font-family:'Inter',sans-serif;font-weight:600;">Ajouter</span>
</button>

<!-- Modal Overlay -->
<div id="modal-overlay" class="modal-overlay" style="display:<?= $edit ? 'flex' : 'none' ?>;">
    <div class="modal-card">
        <div class="modal-header">
            <h2><?= $edit ? 'Modifier' : 'Ajouter' ?> un stagiaire</h2>
            <button class="icon-btn js-close-modal" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="post" class="modal-form" action="stagiaires.php" id="stagiaire-form">
                <input type="hidden" name="list_mois" value="<?= h($listMoisNav) ?>">
                <?php if ($edit): ?><input type="hidden" name="id_stagiaire" value="<?= (int) $edit['id_stagiaire'] ?>"><?php endif; ?>
                
                <div class="modal-section-grid">
                    <!-- Section IDENTITÉ -->
                    <fieldset class="modal-fieldset">
                        <legend><i class="fa-solid fa-id-card"></i> Identité</legend>
                        <div class="avatar-preview-container">
                            <img id="avatar-preview" src="<?= h((string) ($edit['photo'] ?? '')) ?>" style="display:<?= empty($edit['photo']) ? 'none' : 'block' ?>;" onerror="this.style.display='none'; document.getElementById('avatar-initials').style.display='flex';" alt="Preview">
                            <div id="avatar-initials" class="avatar-initials" style="display:<?= empty($edit['photo']) ? 'flex' : 'none' ?>;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        </div>
                        <label>Nom * <input type="text" name="nom" id="form-nom" required pattern="^[^0-9]+$" value="<?= h((string) ($edit['nom'] ?? '')) ?>"></label>
                        <label>Prénom * <input type="text" name="prenom" id="form-prenom" required pattern="^[^0-9]+$" value="<?= h((string) ($edit['prenom'] ?? '')) ?>"></label>
                        <label>N° Inscription <input type="text" name="num_inscri" placeholder="Auto si vide" value="<?= h((string) ($edit['num_inscri'] ?? '')) ?>"></label>
                        <label>CIN <input type="text" name="cin" placeholder="ex: WA123456" pattern="^[A-Za-z]{2}[0-9]{6}$" style="text-transform:uppercase" value="<?= h((string) ($edit['cin'] ?? '')) ?>"></label>
                        <label>Photo URL <input type="text" name="photo" id="form-photo" placeholder="https://..." value="<?= h((string) ($edit['photo'] ?? '')) ?>"></label>
                        <label>Date naissance <input type="date" name="date_naissance" value="<?= h((string) ($edit['date_naissance'] ?? '')) ?>"></label>
                    </fieldset>

                    <!-- Section CONTACT & SCOLARITÉ -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-address-book"></i> Contact</legend>
                            <label>Email <input type="email" name="email" value="<?= h((string) ($edit['email'] ?? '')) ?>"></label>
                            <label>Téléphone <input type="tel" name="telephone" pattern="^[0-9\s\+\-]+$" value="<?= h((string) ($edit['telephone'] ?? '')) ?>"></label>
                            <label style="grid-column: span 2;">Adresse <input type="text" name="adresse" value="<?= h((string) ($edit['adresse'] ?? '')) ?>"></label>
                        </fieldset>

                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-graduation-cap"></i> Scolarité</legend>
                            <label>Date inscription * <input type="date" name="date_inscription" required value="<?= h((string) ($edit['date_inscription'] ?? date('Y-m-d'))) ?>"></label>
                            <label>Filière
                                <select id="form-filiere-select">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($filieresList as $fp): ?>
                                        <option value="<?= (int) $fp['id_filiere'] ?>" <?= $editFiliereId === (int)$fp['id_filiere'] ? 'selected' : '' ?>><?= h(gds_filiere_code((string) $fp['nom_filiere'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label style="grid-column: span 2">Classe *
                                <select name="id_classe" id="form-classe-select" required>
                                    <option value="">— Choisir classe —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= (int) $c['id_classe'] ?>" data-filiere="<?= (int) $c['id_filiere'] ?>" <?= ($edit && (int)$edit['id_classe'] === (int)$c['id_classe']) ? 'selected' : '' ?>>
                                            <?= h($c['nom_classe'] . ' — ' . $c['annee_scolaire']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label style="grid-column: span 2">Mot de passe <?= $edit ? '(vide = inchangé)' : '(par défaut: changeme)' ?> <input name="mot_de_passe" type="password" autocomplete="new-password"></label>
                        </fieldset>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn secondary js-close-modal">Annuler</button>
                    <button type="submit" name="save" value="1" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Slide-in Panel -->
<div id="slide-panel-overlay" class="slide-panel-overlay"></div>
<div id="slide-panel" class="slide-panel">
    <div class="slide-panel-header">
        <h2 style="margin:0;font-size:1.4rem;">Dossier Stagiaire</h2>
        <button class="icon-btn js-close-panel"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="slide-panel-body" id="slide-panel-content">
        <!-- JS Injects info here -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- FAB & Modal Logic ----
    const modalOverlay = document.getElementById('modal-overlay');
    const fabButton = document.getElementById('fab-add');
    const closeBtns = document.querySelectorAll('.js-close-modal');
    
    if (fabButton) {
        fabButton.addEventListener('click', () => {
            // Reset form if opening fresh (not in edit mode)
            if (!<?= $edit ? 'true' : 'false' ?>) {
                document.getElementById('stagiaire-form').reset();
                document.querySelector('#stagiaire-form input[name="date_inscription"]').value = '<?= date("Y-m-d") ?>';
                document.getElementById('avatar-preview').style.display = 'none';
                document.getElementById('avatar-initials').style.display = 'flex';
                document.getElementById('avatar-initials').innerHTML = '<i class="fa-solid fa-user"></i>';
            }
            modalOverlay.style.display = 'flex';
        });
    }

    closeBtns.forEach(btn => btn.addEventListener('click', () => {
        // If we were in edit mode and canceled, redirect to base URL to clear ?edit
        if (<?= $edit ? 'true' : 'false' ?>) {
            window.location.href = 'stagiaires.php?mois=<?= urlencode($listMoisNav) ?>';
        } else {
            modalOverlay.style.display = 'none';
        }
    }));

    // Avatar preview logic
    const photoInput = document.getElementById('form-photo');
    const nomInput = document.getElementById('form-nom');
    const prenomInput = document.getElementById('form-prenom');
    const preview = document.getElementById('avatar-preview');
    const initials = document.getElementById('avatar-initials');

    function updateAvatar() {
        const url = photoInput.value.trim();
        if (url) {
            preview.src = url;
            preview.style.display = 'block';
            initials.style.display = 'none';
        } else {
            preview.style.display = 'none';
            initials.style.display = 'flex';
            let n = nomInput.value.trim().substring(0,1).toUpperCase();
            let p = prenomInput.value.trim().substring(0,1).toUpperCase();
            initials.innerText = (p || n) ? (p + n) : '👤';
        }
    }
    
    if (photoInput && nomInput && prenomInput) {
        photoInput.addEventListener('input', updateAvatar);
        nomInput.addEventListener('input', updateAvatar);
        prenomInput.addEventListener('input', updateAvatar);
    }

    // ---- Filters Logic ----
    var fltFiliere = document.getElementById('flt-stag-filiere');
    var fltClasse  = document.getElementById('flt-stag-classe');
    var allOpts = Array.from(fltClasse?.querySelectorAll('option[data-filiere]') || []);

    function applyFiliereRestriction(fSelect, cSelect, optionsArray) {
        if (!fSelect || !cSelect) return;
        var fid = fSelect.value;
        optionsArray.forEach(function (opt) {
            var match = fid === '' || opt.dataset.filiere === fid;
            opt.style.display = match ? '' : 'none';
            opt.disabled = !match;
        });
        if (fid !== '' && cSelect.querySelector('option[value="' + cSelect.value + '"]')?.disabled) {
            cSelect.value = '';
            cSelect.dispatchEvent(new Event('change'));
        }
    }

    if (fltFiliere) fltFiliere.addEventListener('change', () => applyFiliereRestriction(fltFiliere, fltClasse, allOpts));
    
    // Modal classes restriction
    var formFil = document.getElementById('form-filiere-select');
    var formCl = document.getElementById('form-classe-select');
    var formOpts = Array.from(formCl?.querySelectorAll('option[data-filiere]') || []);
    if (formFil) {
        formFil.addEventListener('change', () => applyFiliereRestriction(formFil, formCl, formOpts));
        applyFiliereRestriction(formFil, formCl, formOpts); // Init
    }

    // Print classes restriction
    var prtFil = document.getElementById('print-filiere-select');
    var prtCl = document.getElementById('print-classe-select');
    var prtOpts = Array.from(prtCl?.querySelectorAll('option[data-filiere]') || []);
    if (prtFil) {
        prtFil.addEventListener('change', () => applyFiliereRestriction(prtFil, prtCl, prtOpts));
        applyFiliereRestriction(prtFil, prtCl, prtOpts); // Init
    }

    // Reset Filters
    const resetBtn = document.getElementById('btn-reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (fltFiliere) fltFiliere.value = '';
            if (fltClasse) fltClasse.value = '';
            const search = document.getElementById('flt-stag-search');
            if (search) search.value = '';
            
            // Trigger change events so filters apply immediately
            if (fltFiliere) fltFiliere.dispatchEvent(new Event('change'));
            if (search) search.dispatchEvent(new Event('input'));
        });
    }

    // Live search - Bind keyup and input directly to trigger the table filter manually
    const searchInput = document.getElementById('flt-stag-search');
    if (searchInput) {
        ['keyup', 'input'].forEach(evt => 
            searchInput.addEventListener(evt, () => {
                if (window.gdsTableFilter) {
                    // gdsTableFilter hooks internally, but dispatching change guarantees it triggers
                    searchInput.dispatchEvent(new Event('change')); 
                }
            })
        );
    }

    // ---- Slide Panel Logic ----
    const panel = document.getElementById('slide-panel');
    const overlay = document.getElementById('slide-panel-overlay');
    const panelContent = document.getElementById('slide-panel-content');
    const closePanelBtns = document.querySelectorAll('.js-close-panel');

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('open');
    }

    closePanelBtns.forEach(b => b.addEventListener('click', closePanel));
    overlay.addEventListener('click', closePanel);

    document.querySelectorAll('.js-open-panel').forEach(row => {
        row.addEventListener('click', (e) => {
            // Check if user clicked a button or link instead of the row
            if (e.target.closest('form') || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            const data = JSON.parse(row.dataset.json || '{}');
            const imgHtml = data.photo ? `<img src="${data.photo}" alt="Photo" class="panel-photo" onerror="this.src='assets/img/logo.png'">` 
                                       : `<div class="panel-photo-placeholder"><i class="fa-solid fa-user"></i></div>`;
            
            const html = `
                <div class="panel-summary card">
                    ${imgHtml}
                    <div class="panel-name">${data.nom} ${data.prenom}</div>
                    <div class="panel-badge">${data.num_inscri}</div>
                </div>
                
                <h3 style="margin-top:1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem; font-size:1.1rem; color:#a855f7;"><i class="fa-solid fa-graduation-cap"></i> Informations Scolaires</h3>
                <div class="panel-grid">
                    <div class="p-label">Filière</div><div class="p-value">${data.filiere || '-'}</div>
                    <div class="p-label">Classe</div><div class="p-value">${data.classe || '-'}</div>
                    <div class="p-label">Inscription</div><div class="p-value">${data.date_inscription}</div>
                </div>

                <h3 style="margin-top:1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem; font-size:1.1rem; color:#38bdf8;"><i class="fa-solid fa-address-card"></i> Contact & Identité</h3>
                <div class="panel-grid">
                    <div class="p-label">CIN</div><div class="p-value">${data.cin || '-'}</div>
                    <div class="p-label">Date Nais.</div><div class="p-value">${data.date_naissance || '-'}</div>
                    <div class="p-label">Téléphone</div><div class="p-value">${data.telephone || '-'}</div>
                    <div class="p-label">Email</div><div class="p-value">${data.email || '-'}</div>
                    <div class="p-label" style="grid-column: span 2;">Adresse</div>
                    <div class="p-value" style="grid-column: span 2;">${data.adresse || '-'}</div>
                </div>
                
                <div style="margin-top:2rem; padding-top:1rem; border-top:1px dashed rgba(255,255,255,0.1); display:flex; gap:1rem;">
                    <a href="stagiaires.php?edit=${data.id}&mois=<?= urlencode($listMoisNav) ?>" class="btn btn-primary" style="flex:1; justify-content:center;"><i class="fa-solid fa-pen"></i> Modifier</a>
                    <a href="documents_officiels.php?id=${data.id}" class="btn secondary" style="flex:1; justify-content:center;"><i class="fa-solid fa-file"></i> Documents</a>
                </div>
            `;
            panelContent.innerHTML = html;
            panel.classList.add('open');
            overlay.classList.add('open');
        });
    });

    // Handle Empty state visibility (Hooking strictly to table display count isn't pure but works via interval or DOM mutation)
    const countEl = document.getElementById('flt-stag-count');
    const tableEl = document.getElementById('liste-stagiaires-table');
    const emptyState = document.getElementById('empty-state');
    if (countEl && emptyState && tableEl) {
        // Monitor DOM text changes in countEl to toggle table visibility
        const observer = new MutationObserver(mutations => {
            let countStr = countEl.innerText.trim();
            if (countStr === '0') {
                tableEl.style.display = 'none';
                emptyState.style.display = 'block';
            } else {
                tableEl.style.display = 'table';
                emptyState.style.display = 'none';
            }
        });
        observer.observe(countEl, { childList: true, characterData: true, subtree: true });
    }

    if (window.gdsTableFilter) {
        window.gdsTableFilter({
            table: '#liste-stagiaires-table',
            counter: '#flt-stag-count',
            controls: [
                { selector: '#flt-stag-filiere', field: 'filiere', type: 'equals' },
                { selector: '#flt-stag-classe',  field: 'classe',  type: 'equals' },
                { selector: '#flt-stag-search',  field: 'search',  type: 'contains', searchFields: ['name', 'num_inscri', 'cin'] },
                { selector: '#flt-stag-sort',    field: 'sort',    type: 'sort' }
            ]
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
