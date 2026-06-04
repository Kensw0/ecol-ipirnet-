<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM stages WHERE id_stage = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Stage supprimé.');
        redirect('stages.php');
    }
    if (isset($_POST['save'])) {
        $ts = (string) ($_POST['type_stage'] ?? 'stage_entreprise');
        if (!in_array($ts, ['stage_entreprise', 'pfe'], true)) {
            $ts = 'stage_entreprise';
        }
        $su = trim((string) ($_POST['sujet'] ?? ''));
        $en = trim((string) ($_POST['entreprise'] ?? ''));
        $dd = ($_POST['date_debut'] ?? '') === '' ? null : (string) $_POST['date_debut'];
        $df = ($_POST['date_fin'] ?? '') === '' ? null : (string) $_POST['date_fin'];
        $ns = ($_POST['note_stage'] ?? '') === '' ? null : (float) str_replace(',', '.', (string) $_POST['note_stage']);
        $cu = trim((string) ($_POST['convention_url'] ?? ''));
        $ru = trim((string) ($_POST['rapport_url'] ?? ''));
        $ev = trim((string) ($_POST['evaluation_entreprise'] ?? ''));
        $ds = ($_POST['date_soutenance'] ?? '') === '' ? null : (string) $_POST['date_soutenance'];
        $ju = trim((string) ($_POST['jury'] ?? ''));
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        
        if ($sid <= 0) {
            flash_set('Stagiaire requis.');
            redirect('stages.php');
        }

        if (isset($_POST['id_stage']) && (int) $_POST['id_stage'] > 0) {
            $pdo->prepare('UPDATE stages SET type_stage=?, sujet=?, entreprise=?, date_debut=?, date_fin=?, note_stage=?, convention_url=?, rapport_url=?, evaluation_entreprise=?, date_soutenance=?, jury=?, id_stagiaire=? WHERE id_stage=?')
                ->execute([$ts, $su === '' ? null : $su, $en === '' ? null : $en, $dd, $df, $ns, $cu === '' ? null : $cu, $ru === '' ? null : $ru, $ev === '' ? null : $ev, $ds, $ju === '' ? null : $ju, $sid, (int) $_POST['id_stage']]);
            flash_set('Stage mis à jour.');
        } else {
            $pdo->prepare('INSERT INTO stages (type_stage, sujet, entreprise, date_debut, date_fin, note_stage, convention_url, rapport_url, evaluation_entreprise, date_soutenance, jury, id_stagiaire) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ts, $su === '' ? null : $su, $en === '' ? null : $en, $dd, $df, $ns, $cu === '' ? null : $cu, $ru === '' ? null : $ru, $ev === '' ? null : $ev, $ds, $ju === '' ? null : $ju, $sid]);
            flash_set('Stage ajouté.');
        }
        redirect('stages.php');
    }
}

$curPage = 'stages';
$pageTitle = 'Gestion des Stages et PFE';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$stag = $pdo->query('SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, f.id_filiere, f.nom_filiere, c.nom_classe FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY s.nom, s.prenom')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stages WHERE id_stage = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$rows = $pdo->query('SELECT st.*, s.num_inscri, s.nom, s.prenom, c.nom_classe, f.nom_filiere FROM stages st JOIN stagiaires s ON s.id_stagiaire=st.id_stagiaire JOIN classes c ON c.id_classe = s.id_classe JOIN filieres f ON f.id_filiere = c.id_filiere ORDER BY st.date_debut DESC')->fetchAll();

$today = date('Y-m-d');
$stagesEnCours = 0;
$rapportsRemis = 0;
$soutenancesAVenir = 0;

foreach($rows as $r) {
    $dd = $r['date_debut'] ?? '';
    $df = $r['date_fin'] ?? '';
    $ds = $r['date_soutenance'] ?? '';
    
    if ($dd && $df && $today >= $dd && $today <= $df) {
        $stagesEnCours++;
    }
    if (!empty($r['rapport_url'])) {
        $rapportsRemis++;
    }
    if ($ds && $ds >= $today) {
        $soutenancesAVenir++;
    }
}
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Suivi des Stages & PFE</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:2rem;">Administrez les stages en entreprise, l'état d'avancement des rapports et les soutenances.</p>

<!-- STATS CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-briefcase"></i>
        </div>
        <div>
            <div class="stat-label">Stages en cours</div>
            <div class="stat-value" style="color:#3b82f6;"><?= $stagesEnCours ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-file-pdf"></i>
        </div>
        <div>
            <div class="stat-label">Rapports remis</div>
            <div class="stat-value" style="color:#10b981;"><?= $rapportsRemis ?></div>
        </div>
    </div>

    <div class="stat-card" style="display:flex; flex-direction:row; align-items:center; gap:1.5rem;">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(217, 70, 239, 0.15); color: #d946ef; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">
            <i class="fa-solid fa-users-viewfinder"></i>
        </div>
        <div>
            <div class="stat-label">Soutenances à venir</div>
            <div class="stat-value" style="color:#d946ef;"><?= $soutenancesAVenir ?></div>
        </div>
    </div>
</div>

<!-- MAIN TABLE -->
<div class="card" style="padding:0;">
    <div class="table-container" style="margin:0; border:none; border-radius:12px;">
        <table class="data">
            <thead>
                <tr>
                    <th style="padding-left:1.5rem;">Stagiaire</th>
                    <th>Type & Entreprise</th>
                    <th>Progression du Stage</th>
                    <th>Statut</th>
                    <th style="text-align:center;">Documents</th>
                    <th class="no-print" style="text-align:right; padding-right:1.5rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php 
                        $dd = $r['date_debut'] ?? '';
                        $df = $r['date_fin'] ?? '';
                        $ds = $r['date_soutenance'] ?? '';
                        
                        // Calculate Status Badge
                        if ($ds && $ds < $today) {
                            $badge = '<span class="badge" style="background:rgba(217,70,239,0.15); color:#e879f9;">Soutenance Passée</span>';
                        } elseif ($df && $today > $df) {
                            if (empty($r['rapport_url'])) {
                                $badge = '<span class="badge" style="background:rgba(249,115,22,0.15); color:#fb923c;">Rapport Manquant</span>';
                            } else {
                                $badge = '<span class="badge" style="background:rgba(16,185,129,0.15); color:#34d399;">Terminé</span>';
                            }
                        } elseif ($dd && $df && $today >= $dd && $today <= $df) {
                            $badge = '<span class="badge" style="background:rgba(59,130,246,0.15); color:#60a5fa;">En Cours</span>';
                        } else {
                            $badge = '<span class="badge" style="background:rgba(161,161,170,0.15); color:#a1a1aa;">Planifié</span>';
                        }
                        
                        // Calculate Progress Timeline
                        $prog = 0;
                        if ($dd && $df) {
                            $start = strtotime($dd);
                            $end = strtotime($df);
                            $now = time();
                            if ($now >= $end) {
                                $prog = 100;
                            } elseif ($now <= $start) {
                                $prog = 0;
                            } else {
                                $prog = (($now - $start) / ($end - $start)) * 100;
                            }
                        }
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                        <td style="padding-left:1.5rem;">
                            <div style="font-weight: 600; color:#e4e4e7;"><?= h(strtoupper((string)$r['nom']) . ' ' . (string)$r['prenom']) ?></div>
                            <div style="font-size: 0.75rem; color:#a1a1aa; font-family:monospace;"><?= h((string)$r['num_inscri']) ?> — <?= h(gds_filiere_code((string)$r['nom_filiere'])) ?></div>
                        </td>
                        <td>
                            <?php if($r['type_stage'] === 'pfe'): ?>
                                <span class="badge" style="background:rgba(250,204,21,0.15); color:#facc15; margin-bottom:0.25rem;">PFE</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(45,212,191,0.15); color:#2dd4bf; margin-bottom:0.25rem;">Stage Entreprise</span>
                            <?php endif; ?>
                            <div style="font-size:0.9rem; color:#d4d4d8; margin-top:2px;"><?= h((string)($r['entreprise'] ?? '—')) ?></div>
                        </td>
                        <td>
                            <?php if ($dd && $df): ?>
                                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#a1a1aa; margin-bottom:4px;">
                                    <span><?= date('d/m/y', strtotime($dd)) ?></span>
                                    <span><?= date('d/m/y', strtotime($df)) ?></span>
                                </div>
                                <div style="width: 100px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                    <div style="height:100%; background: <?= $prog >= 100 ? '#10b981' : '#3b82f6' ?>; width: <?= $prog ?>%; border-radius: 4px;"></div>
                                </div>
                            <?php else: ?>
                                <span style="color:#71717a; font-size:0.8rem; font-style:italic;">Dates manquantes</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $badge ?></td>
                        <td style="text-align:center;">
                            <div style="display:flex; justify-content:center; gap:0.5rem;">
                                <?php if (!empty($r['convention_url'])): ?>
                                    <a href="<?= h((string)$r['convention_url']) ?>" target="_blank" class="icon-btn" title="Voir la convention" style="color:#64748b;"><i class="fa-solid fa-paperclip"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($r['rapport_url'])): ?>
                                    <a href="<?= h((string)$r['rapport_url']) ?>" target="_blank" class="icon-btn" title="Télécharger le rapport" style="color:#ef4444;"><i class="fa-solid fa-file-pdf"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="link-row no-print" style="text-align:right; padding-right:1.5rem; justify-content:flex-end;">
                            <a href="print_convention_stage.php?id=<?= (int) $r['id_stage'] ?>" target="_blank" class="icon-btn" title="Générer Convention" style="color:#60a5fa;"><i class="fa-solid fa-file-contract"></i></a>
                            <a href="stages.php?edit=<?= (int) $r['id_stage'] ?>" class="icon-btn" title="Modifier" style="color:#fbbf24;"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" style="display:inline;" data-confirm-custom="1" data-confirm-msg="Voulez-vous vraiment supprimer ce stage ?">
                                <input type="hidden" name="delete_id" value="<?= (int) $r['id_stage'] ?>">
                                <button type="submit" class="icon-btn danger-hover" title="Supprimer" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" style="text-align:center; padding:4rem; color:var(--muted);"><i class="fa-solid fa-person-chalkboard" style="font-size:3rem; opacity:0.2; margin-bottom:1rem; display:block;"></i><em>Aucun stage ou PFE enregistré actuellement.</em></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- FAB Trigger for Add Modal -->
<button type="button" class="fab-button" onclick="document.getElementById('modal-stage').style.display='flex'" style="background: #14b8a6; box-shadow: 0 10px 25px -5px rgba(20,184,166,0.5);">
    <i class="fa-solid fa-plus" style="margin-right:8px;"></i> Ajouter un stage
</button>

<!-- ADD / EDIT MODAL -->
<div id="modal-stage" class="modal-overlay" style="display: <?= $edit ? 'flex' : 'none' ?>;">
    <div class="modal-card" style="max-width: 800px; max-height: 95vh;">
        <div class="modal-header">
            <h2><?= $edit ? 'Modifier le stage' : 'Nouveau Stage / PFE' ?></h2>
            <button type="button" class="icon-btn" onclick="<?= $edit ? "window.location='stages.php'" : "document.getElementById('modal-stage').style.display='none'" ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" action="stages.php" class="modal-form" data-filiere-form="true" style="display:flex; flex-direction:column; height:100%;">
            <div class="modal-body">
                <?php if ($edit): ?><input type="hidden" name="id_stage" value="<?= (int) $edit['id_stage'] ?>"><?php endif; ?>
                
                <div class="modal-section-grid">
                    <!-- Column 1: Info Base -->
                    <fieldset class="modal-fieldset" style="grid-template-columns: 1fr;">
                        <legend><i class="fa-solid fa-briefcase"></i> Détails Généraux</legend>
                        <label>Filière (filtre)
                            <select data-role="filiere-filter">
                                <option value="">— Toutes —</option>
                                <?php foreach ($filieres as $fi): ?>
                                    <option value="<?= (int) $fi['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fi['nom_filiere']) . ' — ' . gds_fix_text((string) $fi['nom_filiere'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>Stagiaire *
                            <select name="id_stagiaire" required data-filiere-filter="true">
                                <option value=""></option>
                                <?php foreach ($stag as $s): ?>
                                    <option value="<?= (int) $s['id_stagiaire'] ?>" data-filiere-id="<?= (int) $s['id_filiere'] ?>" <?= ($edit && (int)$edit['id_stagiaire'] === (int)$s['id_stagiaire']) ? 'selected' : '' ?>><?= h($s['num_inscri'] . ' — ' . $s['nom'] . ' ' . $s['prenom'] . ' (' . gds_filiere_code((string) $s['nom_filiere']) . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        
                        <label>Type
                            <select name="type_stage">
                                <option value="stage_entreprise" <?= (!$edit || $edit['type_stage'] === 'stage_entreprise') ? 'selected' : '' ?>>Stage entreprise</option>
                                <option value="pfe" <?= ($edit && $edit['type_stage'] === 'pfe') ? 'selected' : '' ?>>PFE</option>
                            </select>
                        </label>
                        
                        <label>Sujet (Mission) <input type="text" name="sujet" placeholder="Titre du projet..." value="<?= h((string) ($edit['sujet'] ?? '')) ?>"></label>
                        <label>Entreprise / Organisme <input type="text" name="entreprise" placeholder="Nom de l'entreprise..." value="<?= h((string) ($edit['entreprise'] ?? '')) ?>"></label>
                    </fieldset>

                    <!-- Column 2: Dates, Docs & Eval -->
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <fieldset class="modal-fieldset" style="grid-template-columns: 1fr 1fr;">
                            <legend><i class="fa-solid fa-calendar-days"></i> Calendrier</legend>
                            <label>Date début <input type="date" name="date_debut" value="<?= h((string) ($edit['date_debut'] ?? '')) ?>"></label>
                            <label>Date fin <input type="date" name="date_fin" value="<?= h((string) ($edit['date_fin'] ?? '')) ?>"></label>
                            <label style="grid-column: span 2;">Date soutenance (PFE) <input type="date" name="date_soutenance" value="<?= h((string) ($edit['date_soutenance'] ?? '')) ?>"></label>
                            <label style="grid-column: span 2;">Jury / Modalités <input type="text" name="jury" placeholder="Membres du jury..." value="<?= h((string) ($edit['jury'] ?? '')) ?>"></label>
                        </fieldset>

                        <fieldset class="modal-fieldset" style="grid-template-columns: 1fr;">
                            <legend><i class="fa-solid fa-file-signature"></i> Suivi & Évaluation</legend>
                            <label>Note de stage (/20) <input type="number" step="0.01" min="0" max="20" name="note_stage" value="<?= $edit && $edit['note_stage'] !== null ? h((string)$edit['note_stage']) : '' ?>"></label>
                            <label>URL Convention <input type="url" name="convention_url" placeholder="https://..." value="<?= h((string) ($edit['convention_url'] ?? '')) ?>"></label>
                            <label>URL Rapport PDF <input type="url" name="rapport_url" placeholder="https://..." value="<?= h((string) ($edit['rapport_url'] ?? '')) ?>"></label>
                            <label>Appréciation Entreprise <input type="text" name="evaluation_entreprise" placeholder="Avis du tuteur..." value="<?= h((string) ($edit['evaluation_entreprise'] ?? '')) ?>"></label>
                        </fieldset>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $edit ? "window.location='stages.php'" : "document.getElementById('modal-stage').style.display='none'" ?>">Annuler</button>
                <button type="submit" name="save" value="1" class="btn" style="background:#14b8a6; color:#fff;">
                    <i class="fa-solid fa-floppy-disk"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
