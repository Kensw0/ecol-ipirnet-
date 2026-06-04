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
            
            // Auto-generate num_inscri
            $year = date('Y', strtotime($di));
            $stGen = $pdo->prepare(
                "SELECT COUNT(*) FROM stagiaires
                 WHERE num_inscri LIKE ?
                   AND num_inscri REGEXP '^INS-[0-9]{4}-[0-9]{5}$'"
            );
            $stGen->execute(['INS-' . $year . '-%']);
            $count = (int) $stGen->fetchColumn();
            $newNumInscri = 'INS-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

            $ins = $pdo->prepare(
                'INSERT INTO stagiaires (num_inscri, cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $newNumInscri,
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
            $pdo->prepare(
                'UPDATE demandes_inscription SET statut = ?, date_decision = NOW() WHERE id_demande = ?'
            )->execute(['acceptee', $idDem]);
            $pdo->commit();
            flash_set('Demande acceptée — stagiaire créé.');
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

// Calculate Monthly Stats
$monthStart = date('Y-m-01 00:00:00');
$stAccepted = $pdo->prepare("SELECT COUNT(*) FROM demandes_inscription WHERE statut = 'acceptee' AND date_decision >= ?");
$stAccepted->execute([$monthStart]);
$accCeMois = (int) $stAccepted->fetchColumn();

$stRefused = $pdo->prepare("SELECT COUNT(*) FROM demandes_inscription WHERE statut = 'refusee' AND date_decision >= ?");
$stRefused->execute([$monthStart]);
$refCeMois = (int) $stRefused->fetchColumn();


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

// Date Formatters
function formatSaaSDate($dtStr) {
    if (!$dtStr) return '—';
    $ts = strtotime($dtStr);
    $months = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
    return date('d', $ts) . ' ' . $months[(int)date('m', $ts)-1] . ' ' . date('Y à H:i', $ts);
}

function timeAgo($dtStr) {
    $diff = time() - strtotime($dtStr);
    if ($diff < 60) return "il y a l'instant";
    if ($diff < 3600) return "il y a " . floor($diff / 60) . " min";
    if ($diff < 86400) return "il y a " . floor($diff / 3600) . " h";
    return "il y a " . floor($diff / 86400) . " j";
}

function diffTime($start, $end) {
    if (!$start || !$end) return '—';
    $diff = strtotime($end) - strtotime($start);
    if ($diff < 60) return "en " . $diff . " sec";
    if ($diff < 3600) return "en " . floor($diff / 60) . " min";
    if ($diff < 86400) return "en " . floor($diff / 3600) . " h";
    return "en " . floor($diff / 86400) . " j";
}

function getAvatarInitials($nom, $prenom) {
    return strtoupper(mb_substr($prenom ?? '', 0, 1) . mb_substr($nom ?? '', 0, 1));
}
?>

<style>
@keyframes pulseOrange {
    0% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(249, 115, 22, 0); }
    100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); }
}
.pulse-dot-orange {
    position: relative;
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 50%;
    background: #f97316;
    animation: pulseOrange 2s infinite;
}
.pulse-dot-badge {
    position:absolute; top:-5px; right:-10px; width:18px; height:18px; background:#f97316; color:#fff; border-radius:50%; font-size:0.7rem; font-weight:bold; display:flex; align-items:center; justify-content:center;
}
</style>

<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:2rem;">
    <div>
        <h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.5rem; margin-bottom: 0.25rem; display:flex; align-items:center; gap:0.75rem;">
            Candidatures
            <?php if($nbAttente > 0): ?>
                <span class="badge" style="background:#f97316; color:#fff; font-size:1rem; border-radius:8px; padding:0.2rem 0.6rem;"><?= $nbAttente ?></span>
            <?php endif; ?>
        </h1>
        <p style="color:var(--muted); font-size:0.95rem; margin:0;">Gérez les candidatures en attente de validation.</p>
    </div>
</div>

<!-- STATS CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    
    <!-- En Attente Card -->
    <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid rgba(249,115,22,0.25); box-shadow: 0 10px 30px -10px rgba(249,115,22,0.15); background: linear-gradient(180deg, rgba(249,115,22,0.05) 0%, rgba(255,255,255,0.02) 100%);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249,115,22,0.15); color: #f97316; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <?php if($nbAttente > 0): ?>
                <span class="pulse-dot-orange"></span>
            <?php endif; ?>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: #f97316; line-height:1; margin-bottom:0.25rem;">
            <?= $nbAttente ?>
        </div>
        <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600;">En attente de décision</div>
    </div>

    <!-- Acceptées Card -->
    <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid rgba(16,185,129,0.25); box-shadow: 0 10px 30px -10px rgba(16,185,129,0.15); background: linear-gradient(180deg, rgba(16,185,129,0.05) 0%, rgba(255,255,255,0.02) 100%);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16,185,129,0.15); color: #10b981; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: #10b981; line-height:1; margin-bottom:0.25rem;">
            <?= $accCeMois ?>
        </div>
        <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600;">Acceptées (Ce mois)</div>
    </div>

    <!-- Refusées Card -->
    <div class="card" style="display:flex; flex-direction:column; padding: 1.5rem; border: 1px solid rgba(239,68,68,0.25); box-shadow: 0 10px 30px -10px rgba(239,68,68,0.15); background: linear-gradient(180deg, rgba(239,68,68,0.05) 0%, rgba(255,255,255,0.02) 100%);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239,68,68,0.15); color: #ef4444; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444; line-height:1; margin-bottom:0.25rem;">
            <?= $refCeMois ?>
        </div>
        <div style="font-size:0.85rem; color:#e4e4e7; font-weight:600;">Refusées (Ce mois)</div>
    </div>

</div>

<!-- A TRAITER KANBAN LIST -->
<div class="card" style="padding:0; overflow:hidden; border: 1px solid rgba(249,115,22,0.3); margin-bottom: 2rem;">
    <div style="padding:1.5rem; border-bottom:1px solid rgba(255,255,255,0.05); background: linear-gradient(90deg, rgba(249,115,22,0.15) 0%, rgba(249,115,22,0.05) 100%); display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0; font-size:1.25rem; color:#f97316;"><i class="fa-solid fa-inbox" style="margin-right:0.5rem;"></i> À Traiter (Urgent)</h2>
        <div style="font-size:0.8rem; color:#f97316; font-family:monospace; display:flex; align-items:center; gap:0.5rem;">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Actualisation automatique...
        </div>
    </div>
    
    <div style="padding:1.5rem;">
        <?php if($attente): ?>
            <div class="stat-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                <?php foreach ($attente as $r): ?>
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.25rem; display:flex; flex-direction:column; gap:1rem; transition: transform 0.2s, background 0.2s; cursor:default;" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div style="width:40px; height:40px; border-radius:50%; background:rgba(56,189,248,0.15); color:#38bdf8; display:flex; align-items:center; justify-content:center; font-weight:bold; border:2px solid rgba(56,189,248,0.3);">
                                    <?= getAvatarInitials($r['nom'], $r['prenom']) ?>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.1rem; color:#fff;"><?= h((string)$r['nom'] . ' ' . (string)$r['prenom']) ?></h4>
                                    <span class="badge" style="background:rgba(255,255,255,0.1); color:#d4d4d8; font-size:0.7rem; padding:0.15rem 0.5rem;"><?= h((string)$r['nom_classe']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem; font-size:0.85rem; color:#a1a1aa; margin-bottom: 0.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <i class="fa-solid fa-envelope" style="width:16px;"></i>
                                <?= h((string) ($r['email'] ?? '—')) ?>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.5rem; color:#facc15;">
                                <i class="fa-regular fa-clock" style="width:16px;"></i>
                                Soumis <?= timeAgo((string)$r['date_soumission']) ?> (<?= formatSaaSDate((string)$r['date_soumission']) ?>)
                            </div>
                        </div>

                        <div style="margin-top:auto;">
                            <button type="button" class="btn btn-voir-details" style="width:100%; background:transparent; border:1px solid rgba(255,255,255,0.1); color:#fff; justify-content:center; margin-bottom:0.5rem;"
                                data-nom="<?= h((string)$r['nom']) ?>"
                                data-prenom="<?= h((string)$r['prenom']) ?>"
                                data-cin="<?= h((string)$r['cin']) ?>"
                                data-date_naissance="<?= h((string)$r['date_naissance']) ?>"
                                data-adresse="<?= h((string)$r['adresse']) ?>"
                                data-email="<?= h((string)$r['email']) ?>"
                                data-telephone="<?= h((string)$r['telephone']) ?>"
                                data-telephone_parent="<?= h((string)$r['telephone_parent']) ?>"
                                data-nom_tuteur="<?= h((string)$r['nom_tuteur']) ?>"
                                data-classe="<?= h((string)$r['nom_classe']) ?>"
                                data-filiere="<?= h((string)$r['nom_filiere']) ?>"
                                data-date_soumission="<?= formatSaaSDate((string)$r['date_soumission']) ?>">
                                <i class="fa-regular fa-eye"></i> Voir détails complets
                                                       <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem;">
                                <form method="post" data-confirm-custom="1" data-confirm-msg="Créer le stagiaire officiellement et accepter cette demande ?" style="margin:0;">
                                <input type="hidden" name="accepter_id" value="<?= (int) $r['id_demande'] ?>">
                                <button type="submit" class="btn" style="width:100%; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); justify-content:center;">
                                    <i class="fa-solid fa-check"></i> Accepter
                                </button>
                            </form>
                            <form method="post" data-confirm-custom="1" data-confirm-msg="Refuser cette demande de façon permanente ?" style="margin:0;">
                                <input type="hidden" name="refuser_id" value="<?= (int) $r['id_demande'] ?>">
                                <button type="submit" class="btn" style="width:100%; background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3); justify-content:center;">
                                    <i class="fa-solid fa-xmark"></i> Refuser
                                </button>
                            </form>
                            </div>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding: 4rem 2rem;">
                <div style="width:80px; height:80px; border-radius:50%; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:2.5rem; margin-bottom:1rem; box-shadow: 0 0 30px rgba(16,185,129,0.15);">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 style="margin:0; font-size:1.5rem; color:#e4e4e7; margin-bottom:0.5rem;">Aucune demande en attente ✅</h3>
                <p style="color:#a1a1aa;">La file de validation est parfaitement propre temporairement.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- DERNIERES DECISIONS -->
<div class="card" style="padding:0;">
    <div style="padding:1.5rem; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <h2 style="margin:0; font-size:1.25rem;"><i class="fa-solid fa-timeline" style="margin-right:0.5rem; color:#a1a1aa;"></i> Historique des Décisions</h2>
        
        <div style="position:relative; width:250px;">
            <i class="fa-solid fa-search" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#71717a;"></i>
            <input type="search" id="historique-search" placeholder="Rechercher nom, statut..." style="width:100%; border-radius:20px; padding:0.4rem 1rem 0.4rem 2.2rem; background:rgba(0,0,0,0.25); color:#fff; border:1px solid rgba(255,255,255,0.1); outline:none; font-size:0.85rem;">
        </div>
    </div>

    <div class="table-container" style="border:none; border-radius:0;">
        <table class="data" id="historique-table">
            <thead style="background:transparent;">
                <tr>
                    <th style="padding-left:1.5rem;">Stagiaire</th>
                    <th>Soumis le</th>
                    <th>Décision</th>
                    <th>Temps de Rép.</th>
                    <th>Statut</th>
                    <th style="text-align:right; padding-right:1.5rem;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($traitees as $r): ?>
                    <tr data-search="<?= h(strtolower($r['nom'] . ' ' . $r['prenom'] . ' ' . $r['statut'])) ?>" style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                        <td style="padding-left:1.5rem; display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:bold; color:#e4e4e7;">
                                <?= getAvatarInitials($r['nom'], $r['prenom']) ?>
                            </div>
                            <div>
                                <div style="font-weight:600; color:#fff; font-size:0.9rem;"><?= h((string)$r['nom'] . ' ' . (string)$r['prenom']) ?></div>
                            </div>
                        </td>
                        <td style="font-family:monospace; color:#a1a1aa; font-size:0.85rem;">
                            <?= formatSaaSDate((string)$r['date_soumission']) ?>
                        </td>
                        <td style="font-family:monospace; color:#d4d4d8; font-size:0.85rem;">
                            <?= formatSaaSDate((string)$r['date_decision']) ?>
                        </td>
                        <td style="color:#71717a; font-size:0.8rem; font-style:italic;">
                            <?= diffTime((string)$r['date_soumission'], (string)$r['date_decision']) ?>
                        </td>
                        <td>
                            <?php if($r['statut'] === 'acceptee'): ?>
                                <span class="badge" style="background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3);"><i class="fa-solid fa-check"></i> Acceptée</span>
                            <?php elseif($r['statut'] === 'refusee'): ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3);"><i class="fa-solid fa-xmark"></i> Refusée</span>
                            <?php else: ?>
                                <span class="badge"><?= h((string) $r['statut']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; padding-right:1.5rem;">
                                <span style="color:#71717a; font-size:0.8rem;">—</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$traitees): ?>
                    <tr><td colspan="6" style="text-align:center; padding:3rem; color:#71717a; font-style:italic;">Aucune décision enregistrée dans l'historique récent.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="demande-panel-overlay" class="slide-panel-overlay"></div>
<div id="demande-panel" class="slide-panel">
    <div class="slide-panel-header">
        <h2 style="margin:0; font-size:1.1rem; color:#fff;"><i class="fa-regular fa-address-card" style="margin-right:0.5rem; color:#a855f7;"></i> Dossier de candidature</h2>
        <button id="close-demande-panel" class="icon-btn"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="slide-panel-body" style="padding:0;">
        <div class="panel-summary" style="border-bottom:1px solid rgba(255,255,255,0.05); margin-bottom:0;">
            <div id="dp-avatar" class="avatar-initials" style="margin-bottom:1rem; width:70px; height:70px; font-size:2rem; background:rgba(168,85,247,0.15); color:#a855f7; border:3px solid rgba(168,85,247,0.3);">XX</div>
            <div id="dp-name" class="panel-name">Prénom Nom</div>
            <div id="dp-classe" class="panel-badge" style="margin-top:0.5rem;">Classe</div>
        </div>
        <div style="padding:1.5rem;">
            <h3 style="font-size:0.8rem; text-transform:uppercase; color:#a1a1aa; letter-spacing:0.1em; margin-bottom:1rem;">Informations personnelles</h3>
            <div class="panel-grid" style="margin-top:0; margin-bottom:2rem;">
                <div class="p-label">CIN</div><div class="p-value" id="dp-cin"></div>
                <div class="p-label">Naissance</div><div class="p-value" id="dp-dn"></div>
                <div class="p-label">Adresse</div><div class="p-value" id="dp-adr"></div>
                <div class="p-label">Email</div><div class="p-value" id="dp-email"></div>
            </div>
            
            <h3 style="font-size:0.8rem; text-transform:uppercase; color:#a1a1aa; letter-spacing:0.1em; margin-bottom:1rem;">Contacts d'urgence</h3>
            <div class="panel-grid" style="margin-top:0; margin-bottom:2rem;">
                <div class="p-label">Tél Candidat</div><div class="p-value" id="dp-tel"></div>
                <div class="p-label">Tél Parent</div><div class="p-value" id="dp-telp"></div>
                <div class="p-label">Tuteur / Père</div><div class="p-value" id="dp-tut"></div>
            </div>

            <div style="text-align:center; color:#71717a; font-size:0.8rem; font-style:italic;">
                Soumis le <span id="dp-date"></span>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh the page every 30 seconds to fetch new requests
window.refreshTimeout = setTimeout(() => {
    window.location.reload();
}, 30000);

// Client-side search logic for history table
document.getElementById('historique-search').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#historique-table tbody tr[data-search]');
    rows.forEach(row => {
        if(row.dataset.search.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// View Details Modal Logic
document.querySelectorAll('.btn-voir-details').forEach(btn => {
    btn.addEventListener('click', function() {
        if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
        
        document.getElementById('dp-avatar').textContent = 
            (this.dataset.prenom.charAt(0) + this.dataset.nom.charAt(0)).toUpperCase();
        document.getElementById('dp-name').textContent = this.dataset.prenom + ' ' + this.dataset.nom;
        document.getElementById('dp-classe').textContent = this.dataset.classe + ' — ' + this.dataset.filiere;
        
        document.getElementById('dp-cin').textContent = this.dataset.cin || '—';
        document.getElementById('dp-dn').textContent = this.dataset.date_naissance || '—';
        document.getElementById('dp-adr').textContent = this.dataset.adresse || '—';
        document.getElementById('dp-email').textContent = this.dataset.email || '—';
        document.getElementById('dp-tel').textContent = this.dataset.telephone || '—';
        document.getElementById('dp-telp').textContent = this.dataset.telephone_parent || '—';
        document.getElementById('dp-tut').textContent = this.dataset.nom_tuteur || '—';
        document.getElementById('dp-date').textContent = this.dataset.date_soumission;

        document.getElementById('demande-panel-overlay').classList.add('open');
        document.getElementById('demande-panel').classList.add('open');
    });
});

function closePanel() {
    document.getElementById('demande-panel-overlay').classList.remove('open');
    document.getElementById('demande-panel').classList.remove('open');
    window.refreshTimeout = setTimeout(() => window.location.reload(), 30000);
}

document.getElementById('close-demande-panel').addEventListener('click', closePanel);
document.getElementById('demande-panel-overlay').addEventListener('click', closePanel);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
