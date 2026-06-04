<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'officiels';
$pageTitle = 'Documents Officiels';
require __DIR__ . '/includes/header.php';

$sid = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Fetch all stagiaires for the grid
$stStag = $pdo->prepare('
    SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom, s.photo, f.nom_filiere, f.id_filiere 
    FROM stagiaires s 
    JOIN classes c ON c.id_classe = s.id_classe 
    JOIN filieres f ON f.id_filiere = c.id_filiere 
    ORDER BY s.nom, s.prenom
');
$stStag->execute();
$stag = $stStag->fetchAll();

// Get unique filieres for filter chips
$filieres = [];
foreach($stag as $s) {
    if(!isset($filieres[$s['id_filiere']])) {
        $filieres[$s['id_filiere']] = $s['nom_filiere'];
    }
}

function generateInitials($nom, $prenom) {
    return strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
}

$curMois = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_log_id'])) {
        $pdo->prepare('DELETE FROM documents_generes WHERE id_gen = ?')->execute([(int)$_POST['delete_log_id']]);
        flash_set('Entrée d\'historique supprimée.');
        redirect('documents_officiels.php?id=' . $sid);
    }
    if (isset($_POST['clear_all_logs'])) {
        $pdo->prepare('DELETE FROM documents_generes WHERE id_stagiaire = ?')->execute([$sid]);
        flash_set('Tout l\'historique a été effacé pour ce stagiaire.');
        redirect('documents_officiels.php?id=' . $sid);
    }
}
?>

<h1 class="page-title" style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; margin-bottom: 0.25rem;">Génération de Documents</h1>
<p style="color:var(--muted); font-size:0.95rem; margin-bottom:2rem;">Accédez aux attestations, certificats, conventions et reçus pour chaque stagiaire.</p>

<div class="card no-print" style="margin-bottom: 2rem;">
    <!-- FILTER CHIPS & SEARCH -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;" id="filter-chips">
            <button class="badge" style="background:#a855f7; color:#fff; border:none; cursor:pointer; padding:0.4rem 1rem;" data-filter="all">Toutes</button>
            <?php foreach($filieres as $fid => $fname): ?>
                <button class="badge" style="background:rgba(255,255,255,0.05); color:#a1a1aa; border:1px solid rgba(255,255,255,0.1); cursor:pointer; padding:0.4rem 1rem;" data-filter="<?= $fid ?>">
                    <?= h(gds_filiere_code((string)$fname)) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div style="position:relative; width:300px;">
            <i class="fa-solid fa-search" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#71717a;"></i>
            <input type="search" id="grid-search" placeholder="Nom, N° Inscription..." style="width:100%; border-radius:30px; padding:0.6rem 1rem 0.6rem 2.5rem; background:rgba(0,0,0,0.25); color:#fff; border:1px solid rgba(255,255,255,0.1); outline:none;">
        </div>
    </div>
</div>

<!-- STAGIAIRE GRID -->
<div class="stat-grid no-print" id="stagiaire-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
    <?php foreach ($stag as $s): ?>
        <div class="card stagiaire-card" data-filiere="<?= $s['id_filiere'] ?>" data-search="<?= h(strtolower($s['nom'].' '.$s['prenom'].' '.$s['num_inscri'])) ?>" style="display:flex; flex-direction:column; align-items:center; text-align:center; padding: 2rem 1rem; transition: transform 0.2s, box-shadow 0.2s;">
            
            <?php if (!empty($s['photo']) && filter_var($s['photo'], FILTER_VALIDATE_URL)): ?>
                <img src="<?= h((string)$s['photo']) ?>" alt="Photo" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.1); margin-bottom: 1rem;">
            <?php else: ?>
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(56,189,248,0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; border: 3px solid rgba(56,189,248,0.3); margin-bottom: 1rem;">
                    <?= generateInitials((string)$s['nom'], (string)$s['prenom']) ?>
                </div>
            <?php endif; ?>
            
            <div style="font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem;">
                <?= h(strtoupper((string) $s['nom']) . ' ' . ucfirst((string) $s['prenom'])) ?>
            </div>
            
            <div style="font-family: monospace; font-size: 0.8rem; color: #a1a1aa; margin-bottom: 0.75rem;">
                <?= h((string) $s['num_inscri']) ?>
            </div>

            <span class="badge" style="background:rgba(255,255,255,0.05); color:#d4d4d8; margin-bottom: 1.5rem;">
                <?= h(gds_filiere_code((string)$s['nom_filiere'])) ?>
            </span>
            
            <a href="documents_officiels.php?id=<?= $s['id_stagiaire'] ?>" class="btn" style="background: #2563eb; color:#fff; width: 100%; justify-content: center; border-radius: 8px;">
                <i class="fa-regular fa-folder-open"></i> Gérer les Documents
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- CLIENT SIDE FILTERING -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('grid-search');
    const filterBtns = document.querySelectorAll('#filter-chips button');
    const cards = document.querySelectorAll('.stagiaire-card');
    let currentFilter = 'all';

    function runFilters() {
        const q = searchInput.value.toLowerCase();
        cards.forEach(card => {
            const matchesSearch = card.dataset.search.includes(q);
            const matchesFilter = (currentFilter === 'all' || card.dataset.filiere === currentFilter);
            if(matchesSearch && matchesFilter) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', runFilters);

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => {
                b.style.background = 'rgba(255,255,255,0.05)';
                b.style.color = '#a1a1aa';
            });
            this.style.background = '#a855f7';
            this.style.color = '#fff';
            currentFilter = this.dataset.filter;
            runFilters();
        });
    });
});
</script>

<!-- MODAL OVERLAY (Activates if ID is passed in URL) -->
<?php if ($sid > 0): 
    $st = $pdo->prepare('SELECT * FROM v_stagiaires_detail WHERE id_stagiaire = ?');
    $st->execute([$sid]);
    $stgData = $st->fetch();
    
    if($stgData):
        $absList = $pdo->prepare('SELECT id_absence, date_absence FROM absences WHERE id_stagiaire = ? ORDER BY date_absence DESC LIMIT 5');
        $absList->execute([$sid]);
        $absRows = $absList->fetchAll();
        
        $stgList = $pdo->prepare('SELECT id_stage, type_stage, entreprise, date_debut FROM stages WHERE id_stagiaire = ? ORDER BY date_debut DESC');
        $stgList->execute([$sid]);
        $stgRows = $stgList->fetchAll();
?>
    <div id="docs-modal" class="modal-overlay" style="display:flex;">
        <div class="modal-card" style="max-width: 900px; max-height: 90vh;">
            <div class="modal-header" style="background: rgba(0,0,0,0.2);">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(56,189,248,0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700; border: 2px solid rgba(56,189,248,0.3);">
                        <?= generateInitials((string)$stgData['nom'], (string)$stgData['prenom']) ?>
                    </div>
                    <div>
                        <h2 style="margin:0; font-size:1.4rem; font-weight:700; color:#fff;"><?= h((string)$stgData['nom'] . ' ' . $stgData['prenom']) ?></h2>
                        <div style="font-size:0.85rem; color:#a1a1aa; font-family:monospace;"><?= h((string)$stgData['num_inscri']) ?> — <?= h((string)$stgData['nom_filiere']) ?></div>
                    </div>
                </div>
                <a href="documents_officiels.php" class="icon-btn" title="Fermer"><i class="fa-solid fa-xmark"></i></a>
            </div>
            
            <div class="modal-body" style="background:#18181b;">
                <h3 style="color:#e4e4e7; font-size:1rem; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1);"><i class="fa-solid fa-file-contract"></i> Documents Standard</h3>
                <div class="stat-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom:2rem;">
                    
                    <a href="print_fiche_inscription.php?id=<?= $sid ?>&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-user-plus" style="font-size:1.5rem; color:#60a5fa;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Fiche Inscription</span>
                    </a>
                    
                    <a href="print_certificat_scolarite.php?id=<?= $sid ?>&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-graduation-cap" style="font-size:1.5rem; color:#a855f7;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Certificat de Scolarité</span>
                    </a>

                    <a href="print_releve_notes.php?id=<?= $sid ?>&mode=combined&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-table-list" style="font-size:1.5rem; color:#facc15;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Relevé (Complet)</span>
                    </a>
                    
                    <a href="print_releve_notes.php?id=<?= $sid ?>&mode=controle&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-list-check" style="font-size:1.5rem; color:#f59e0b;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Relevé (Contrôles)</span>
                    </a>

                    <a href="print_releve_notes.php?id=<?= $sid ?>&mode=examen&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-clipboard-check" style="font-size:1.5rem; color:#d97706;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Relevé (Examens)</span>
                    </a>

                    <a href="print_attestation_reussite.php?id=<?= $sid ?>&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-award" style="font-size:1.5rem; color:#10b981;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Attestation de Réussite</span>
                    </a>

                    <a href="print_etat_paiement.php?id=<?= $sid ?>&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-file-invoice-dollar" style="font-size:1.5rem; color:#f97316;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">État des cotisations</span>
                    </a>

                    <a href="print_recu_paiement.php?id=<?= $sid ?>&mois=<?=h($curMois)?>&auto=1" target="_blank" class="card" style="display:flex; align-items:center; gap:0.75rem; padding:1rem; text-decoration:none; background:rgba(255,255,255,0.02); transition:transform 0.2s;">
                        <i class="fa-solid fa-receipt" style="font-size:1.5rem; color:#ec4899;"></i>
                        <span style="font-weight:600; font-size:0.9rem; color:#fff;">Reçu (Mois Courant)</span>
                    </a>

                </div>

                <!-- Absences & Stages Specific Docs -->
                <!-- Absences & Stages Specific Docs -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
                    <div>
                        <h3 style="color:#e4e4e7; font-size:0.95rem; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1);"><i class="fa-solid fa-user-clock"></i> Billets d'Excuse</h3>
                        <?php if($absRows): ?>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <?php foreach($absRows as $a): ?>
                                    <a href="print_billet_excuse.php?id=<?= $a['id_absence'] ?>&auto=1" target="_blank" style="display:flex; align-items:center; justify-content:space-between; text-decoration:none; color:#a1a1aa; padding:0.75rem; background:rgba(255,255,255,0.03); border-radius:8px;">
                                        <span>Absence du <?= h($a['date_absence']) ?></span>
                                        <i class="fa-solid fa-print" style="color:#ef4444;"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding:1rem; text-align:center; color:#71717a; background:rgba(255,255,255,0.02); border-radius:8px; font-style:italic;">Aucune absence requérant un justificatif.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 style="color:#e4e4e7; font-size:0.95rem; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1);"><i class="fa-solid fa-handshake"></i> Conventions</h3>
                        <?php if($stgRows): ?>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <?php foreach($stgRows as $sg): ?>
                                    <a href="print_convention_stage.php?id=<?= $sg['id_stage'] ?>&auto=1" target="_blank" style="display:flex; align-items:center; justify-content:space-between; text-decoration:none; color:#a1a1aa; padding:0.75rem; background:rgba(255,255,255,0.03); border-radius:8px;">
                                        <span><?= $sg['type_stage'] === 'pfe' ? 'PFE' : 'Stage' ?> - <?= h($sg['entreprise'] ?? 'N/A') ?></span>
                                        <i class="fa-solid fa-print" style="color:#14b8a6;"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding:1rem; text-align:center; color:#71717a; background:rgba(255,255,255,0.02); border-radius:8px; font-style:italic;">Aucun stage assigné.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- NEW: HISTORY LOGS -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1);">
                    <h3 style="color:#e4e4e7; font-size:0.95rem; margin:0;"><i class="fa-solid fa-clock-rotate-left"></i> Historique des Documents Édités</h3>
                    <?php
                    $histSt = $pdo->prepare('SELECT id_gen, type_document, reference, genere_le FROM documents_generes WHERE id_stagiaire = ? ORDER BY genere_le DESC LIMIT 10');
                    $histSt->execute([$sid]);
                    $history = $histSt->fetchAll();
                    if ($history):
                    ?>
                        <form method="post" data-confirm-custom="1" data-confirm-msg="Voulez-vous vraiment effacer TOUT l'historique de ce stagiaire ?">
                            <input type="hidden" name="clear_all_logs" value="1">
                            <button type="submit" class="btn secondary" style="font-size:0.75rem; padding:0.3rem 0.6rem; border-color:rgba(239,68,68,0.2); color:#fca5a5;">
                                <i class="fa-solid fa-trash-sweep"></i> Tout effacer
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($history): ?>
                    <div style="background:rgba(0,0,0,0.2); border-radius:8px; overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.85rem; color:#a1a1aa;">
                            <thead style="background:rgba(255,255,255,0.02);">
                                <tr>
                                    <th style="padding:0.75rem; text-align:left;">Date & Heure</th>
                                    <th style="padding:0.75rem; text-align:left;">Type de document</th>
                                    <th style="padding:0.75rem; text-align:left;">Référence</th>
                                    <th style="padding:0.75rem; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($history as $hRow): ?>
                                    <tr style="border-top:1px solid rgba(255,255,255,0.05);">
                                        <td style="padding:0.75rem;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($hRow['genere_le']))) ?></td>
                                        <td style="padding:0.75rem; color:#fff;"><?= h(ucfirst(str_replace('_', ' ', (string)$hRow['type_document']))) ?></td>
                                        <td style="padding:0.75rem;"><span style="font-family:monospace;"><?= h((string)$hRow['reference']) ?></span></td>
                                        <td style="padding:0.75rem; text-align:right;">
                                            <form method="post" style="display:inline;" data-confirm-custom="1" data-confirm-msg="Supprimer cette entrée de l'historique ?">
                                                <input type="hidden" name="delete_log_id" value="<?= $hRow['id_gen'] ?>">
                                                <button type="submit" style="background:none; border:none; color:#71717a; cursor:pointer;" class="danger-hover">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: 
                    // Need to check if history was empty even before the fetch in the header div
                ?>
                    <div style="padding:1rem; text-align:center; color:#71717a; background:rgba(255,255,255,0.02); border-radius:8px; font-style:italic;">Aucun document n'a encore été édité pour ce stagiaire.</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
<?php 
    endif;
endif;
?>

<?php require __DIR__ . '/includes/footer.php'; ?>
