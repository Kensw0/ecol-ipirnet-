<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$curPage = 'index';
$pageTitle = 'Tableau de bord';
require __DIR__ . '/includes/header.php';

$moisCourant = date('Y-m');
$nbStag = (int) $pdo->query('SELECT COUNT(*) FROM stagiaires')->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM mensualites WHERE mois_ref = ? AND est_paye = 1');
$st->execute([$moisCourant]);
$nbPayeCeMois = (int) $st->fetchColumn();
$nbSansCotisation = max(0, $nbStag - $nbPayeCeMois);
$nbDemandes = (int) $pdo->query("SELECT COUNT(*) FROM pre_inscription WHERE statut = 'en_attente'")->fetchColumn();

// --- NEW TREND QUERIES ---
$stStagThisMonth = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?");
$stStagThisMonth->execute([$moisCourant]);
$nbStagThisMonth = (int) $stStagThisMonth->fetchColumn();
$nbAbsences30j = (int) $pdo->query("SELECT COUNT(*) FROM absences WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$nbAbsencesNonJustifiees = (int) $pdo->query("SELECT COUNT(*) FROM absences WHERE est_justifiee = 0")->fetchColumn();

$nbStagiairesAvecStage = (int) $pdo->query("SELECT COUNT(DISTINCT id_stagiaire) FROM stages")->fetchColumn();
$pctStages = $nbStag > 0 ? round(($nbStagiairesAvecStage / $nbStag) * 100) : 0;

$recentStags = $pdo->query("SELECT nom, prenom, date_inscription FROM stagiaires ORDER BY id_stagiaire DESC LIMIT 3")->fetchAll();
$recentAbs = $pdo->query("SELECT s.nom, s.prenom, a.date_absence, a.est_justifiee FROM absences a JOIN stagiaires s ON a.id_stagiaire = s.id_stagiaire ORDER BY a.id_absence DESC LIMIT 3")->fetchAll();
$recentDocs = $pdo->query("SELECT s.nom, s.prenom, d.type_document, d.genere_le FROM documents_generes d JOIN stagiaires s ON d.id_stagiaire = s.id_stagiaire ORDER BY d.id_gen DESC LIMIT 3")->fetchAll();

$absDist = $pdo->query("SELECT DATE_FORMAT(date_absence, '%b') as m, COUNT(*) as c FROM absences GROUP BY m ORDER BY MIN(date_absence) DESC LIMIT 5")->fetchAll();
$absDist = array_reverse($absDist);
$maxAbs = 1;
foreach($absDist as $a) if($a['c'] > $maxAbs) $maxAbs = $a['c'];

?>
<div class="dash-layout">
    <div class="dash-main">
        <!-- Welcome Header -->
        <div class="dash-welcome card" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(236, 72, 153, 0.05)); border-color: rgba(236, 72, 153, 0.2);">
            <h2 style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; color: #fff; margin-bottom: 0.25rem; text-transform:none; letter-spacing:0;">Bienvenue sur le Tableau de Bord 👋</h2>
            <p style="margin:0;font-size:0.95rem;color:rgba(244,244,245,0.7);">Voici le résumé en temps réel de la gestion des stagiaires IPIRNET.</p>
        </div>

        <!-- Colored Stats Grid -->
        <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem;">
            <!-- Stagiaires -->
            <div class="stat-card stat-featured" style="border-top: 3px solid #60a5fa; background: linear-gradient(to bottom, rgba(96, 165, 250, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #60a5fa; font-size: 2.4rem; padding-bottom:0.25rem;"><?= $nbStag ?></div>
                        <div class="stat-label">Stagiaires Actifs</div>
                    </div>
                    <i class="fa-solid fa-users" style="color: rgba(96, 165, 250, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: #a1a1aa; margin-top:0.75rem;"><span style="color:#60a5fa;font-weight:bold;">↑ +<?= $nbStagThisMonth ?></span> ce mois-ci</div>
            </div>

            <!-- Demandes -->
            <div class="stat-card" style="border-top: 3px solid #fbbf24; background: linear-gradient(to bottom, rgba(251, 191, 36, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #fbbf24; font-size: 2.4rem; padding-bottom:0.25rem;"><?= $nbDemandes ?></div>
                        <div class="stat-label">Pré-inscriptions</div>
                    </div>
                    <i class="fa-solid fa-clock" style="color: rgba(251, 191, 36, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: <?php echo $nbDemandes > 0 ? '#fbbf24' : '#a1a1aa'; ?>; margin-top:0.75rem;"><?php echo $nbDemandes > 0 ? 'À traiter rapidement' : 'Aucune en attente'; ?></div>
            </div>

            <!-- Paiements -->
            <div class="stat-card" style="border-top: 3px solid #34d399; background: linear-gradient(to bottom, rgba(52, 211, 153, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #34d399; font-size: 2.4rem; padding-bottom:0.25rem;"><?= $nbPayeCeMois ?></div>
                        <div class="stat-label">Cotisations payées</div>
                    </div>
                    <i class="fa-solid fa-money-check-dollar" style="color: rgba(52, 211, 153, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; margin-top:0.75rem;"><?= $nbSansCotisation > 0 ? '<span style="color:#f87171;font-weight:bold;">' . $nbSansCotisation . ' impayés</span> pour ' . h($moisCourant) : '<span style="color:#34d399;">100% payé ce mois</span>' ?></div>
            </div>

            <!-- Absences -->
            <div class="stat-card" style="border-top: 3px solid #f87171; background: linear-gradient(to bottom, rgba(248, 113, 113, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #f87171; font-size: 2.4rem; padding-bottom:0.25rem;"><?= $nbAbsences30j ?></div>
                        <div class="stat-label">Absences (30j)</div>
                    </div>
                    <i class="fa-solid fa-calendar-xmark" style="color: rgba(248, 113, 113, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: #a1a1aa; margin-top:0.75rem;">Dont <span style="color:#fca5a5;"><?= $nbAbsencesNonJustifiees ?> non justifiées</span></div>
            </div>
        </div>

        <!-- Charts & Feed Row -->
        <div class="grid-stats" style="grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom:1.5rem;">

            <!-- Activity Feed -->
            <div class="card" style="margin:0; display:flex; flex-direction:column;">
                <h2 style="margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;"><i class="fa-solid fa-bolt" style="color:#fbbf24; margin-right:0.5rem;"></i> Flux d'activité récent</h2>
                <div style="flex:1; display:flex; flex-direction:column; gap:0.65rem;">
                    <?php if (!$recentStags && !$recentAbs): ?>
                        <p style="color:#71717a; font-size:0.9rem; font-style:italic;">Aucune activité récente.</p>
                    <?php endif; ?>

                    <?php foreach ($recentStags as $stag): ?>
                    <div style="display:flex; align-items:center; gap:1rem; padding:0.6rem 0.85rem; background:rgba(255,255,255,0.015); border-radius:8px; border:1px solid rgba(255,255,255,0.04); transition:background 0.2s;">
                        <div style="min-width:32px; height:32px; border-radius:50%; background:rgba(96,165,250,0.15); color:#60a5fa; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-user-plus" style="font-size:0.8rem;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.85rem; color:#e4e4e7;"><strong><?= htmlspecialchars($stag['prenom'].' '.$stag['nom']) ?></strong> a été ajouté(e).</div>
                            <div style="font-size:0.7rem; color:#71717a;"><?= htmlspecialchars(date('d/m/Y', strtotime($stag['date_inscription']))) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($recentAbs as $abs): ?>
                    <div style="display:flex; align-items:center; gap:1rem; padding:0.6rem 0.85rem; background:rgba(255,255,255,0.015); border-radius:8px; border:1px solid rgba(255,255,255,0.04); transition:background 0.2s;">
                        <div style="min-width:32px; height:32px; border-radius:50%; background:rgba(248,113,113,0.15); color:#f87171; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-user-clock" style="font-size:0.8rem;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.85rem; color:#e4e4e7;">Absence de <strong><?= htmlspecialchars($abs['prenom'].' '.$abs['nom']) ?></strong>.</div>
                            <div style="font-size:0.7rem; color:#71717a;"><?= htmlspecialchars(date('d/m/Y', strtotime($abs['date_absence']))) ?> — <?= $abs['est_justifiee'] ? '<span style="color:#34d399;">Justifiée</span>' : '<span style="color:#f87171;">Injustifiée</span>' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($recentDocs as $doc): ?>
                    <div style="display:flex; align-items:center; gap:1rem; padding:0.6rem 0.85rem; background:rgba(255,255,255,0.015); border-radius:8px; border:1px solid rgba(255,255,255,0.04); transition:background 0.2s;">
                        <div style="min-width:32px; height:32px; border-radius:50%; background:rgba(232,121,249,0.15); color:#e879f9; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-file-pdf" style="font-size:0.8rem;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.85rem; color:#e4e4e7;">Document <strong><?= h(str_replace('_', ' ', (string)$doc['type_document'])) ?></strong> généré pour <strong><?= h($doc['prenom'].' '.$doc['nom']) ?></strong>.</div>
                            <div style="font-size:0.7rem; color:#71717a;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($doc['genere_le']))) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:0.85rem; padding-top:0.75rem; border-top:1px solid rgba(255,255,255,0.04); text-align:right;">
                    <a href="historique_documents.php" style="color:#a855f7; font-size:0.8rem; font-weight:700; text-decoration:none;">
                        Voir tout l'historique <i class="fa-solid fa-arrow-right" style="font-size:0.7rem;"></i>
                    </a>
                </div>
            </div>

            <!-- Side Block: Rings and Charts -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Stages Progress -->
                <div class="card" style="margin:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; flex:1;">
                    <h2 style="align-self:flex-start; margin-bottom:0.5rem;"><i class="fa-solid fa-briefcase" style="color:#a855f7; margin-right:0.5rem;"></i> Assignations Stages / PFE</h2>
                    <div style="position:relative; width:100px; height:100px; margin: 1rem auto 0.5rem auto;">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="10"></circle>
                            <circle cx="50" cy="50" r="44" fill="none" stroke="#a855f7" stroke-width="10"
                                    stroke-dasharray="276.46"
                                    stroke-dashoffset="<?= 276.46 - (276.46 * $pctStages / 100) ?>"
                                    stroke-linecap="round" style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1s ease;"></circle>
                        </svg>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; flex-direction:column;">
                            <span style="font-size:1.3rem; font-weight:700; color:#fff;"><?= $pctStages ?>%</span>
                        </div>
                    </div>
                    <p style="font-size:0.75rem; color:#a1a1aa; margin:0; line-height:1.4;"><?= $nbStagiairesAvecStage ?> sur <?= $nbStag ?> stagiaires<br>sont en stage.</p>
                </div>

                <!-- Absences Bar Chart -->
                <div class="card" style="margin:0; display:flex; flex-direction:column; flex:1;">
                    <h2 style="margin-bottom:1rem;"><i class="fa-solid fa-chart-column" style="color:#38bdf8; margin-right:0.5rem;"></i> Absences / Mois</h2>
                    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:0.5rem; height:80px; padding-top:0.5rem;">
                        <?php if (empty($absDist)): ?>
                            <p style="color:#71717a; font-size:0.8rem; text-align:center; width:100%;">Données indisponibles</p>
                        <?php else: ?>
                            <?php foreach($absDist as $ab): ?>
                                <?php $barHeight = round(($ab['c'] / $maxAbs) * 100); ?>
                                <div style="display:flex; flex-direction:column; align-items:center; gap:0.25rem; flex:1; height:100%; justify-content:flex-end;">
                                    <span style="font-size:0.65rem; color:#a1a1aa; font-weight:bold;"><?= $ab['c'] ?></span>
                                    <div style="width:100%; background:linear-gradient(to top, rgba(56, 189, 248, 0.2), #38bdf8); border-radius:4px 4px 0 0; min-height:4%; height:<?= $barHeight ?>%; max-width:24px; transition: height 1s ease;"></div>
                                    <span style="font-size:0.6rem; color:#71717a; text-transform:uppercase;"><?= h($ab['m']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Shortcuts -->
        <div class="card" style="margin-bottom:0;">
            <h2 style="margin-bottom:1rem;">Accès rapide</h2>
            <div class="gds-shortcut-row" style="display:flex; flex-wrap:wrap; gap:0.75rem;">
                <a class="btn secondary" href="demandes_inscription.php"><i class="fa-solid fa-clock" style="color:#fbbf24;"></i> Pré-inscriptions</a>
                <a class="btn secondary" href="stagiaires.php"><i class="fa-solid fa-users" style="color:#60a5fa;"></i> Liste des stagiaires</a>
            </div>
        </div>
    </div>

    <aside class="dash-aside no-print">
        <div class="card" style="border-top: 3px solid #c084fc;">
            <h2>Action Rapide</h2>
            <p style="font-size:0.85rem; color:#a1a1aa; margin:0 0 1rem 0;">Accès direct aux tâches fréquentes.</p>
            <a class="btn btn-accent" style="width:100%; justify-content:center; margin-bottom:0.75rem;" href="stagiaires.php"><i class="fa-solid fa-layout-dashboard"></i> Ouvrir hub stagiaires</a>
            <a class="btn btn-outline" style="width:100%; justify-content:center; margin-bottom:0.75rem;" href="demandes_inscription.php"><i class="fa-solid fa-clock"></i> Traiter demandes <?php if($nbDemandes>0): ?><span style="background:#fbbf24;color:#000;font-size:0.7rem;padding:1px 6px;border-radius:10px;margin-left:4px;font-weight:700;"><?= $nbDemandes ?></span><?php endif; ?></a>
        </div>

        <?php if (false): ?>
        <?php else: ?>
        <div class="card" style="border: 1px solid rgba(52,211,153,0.3); background:rgba(52,211,153,0.06);">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                <i class="fa-solid fa-check-circle" style="color:#34d399; font-size:1.2rem;"></i>
                <h2 style="color:#6ee7b7; margin:0; font-size:0.9rem;">Système stable</h2>
            </div>
            <p style="margin:0;font-size:0.85rem;color:#34d399;">Les absences sont à jour et régularisées.</p>
        </div>
        <?php endif; ?>

        <div class="card" style="border: 1px dashed rgba(255,255,255,0.15); background: transparent;">
            <h2 style="color:#a1a1aa; font-size:0.75rem;"><i class="fa-solid fa-info-circle"></i> Info</h2>
            <p style="margin:0; font-size:0.8rem; color:#71717a;">Généré le <?= date('d/m/Y H:i') ?></p>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>


