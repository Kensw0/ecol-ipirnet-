<?php
declare(strict_types=1);

// ============================================================
//  index.php — Tableau de bord
//
//  Page d'accueil après connexion. Affiche :
//    - Quatre indicateurs clés (stagiaires, pré-inscriptions,
//      cotisations, absences)
//    - Un flux d'activité chronologique multi-types
//    - Un anneau de progression pour les stages
//    - Un histogramme d'absences par mois
//    - Un panneau latéral d'alertes et d'accès rapide
// ============================================================

require __DIR__ . '/includes/bootstrap.php';

$curPage   = 'index';
$pageTitle = 'Tableau de bord';
require __DIR__ . '/includes/header.php';


// ============================================================
//  SECTION 1 : Indicateurs clés (cartes de statistiques)
// ============================================================

$moisCourant = date('Y-m'); // Format "2025-06" utilisé pour filtrer les mensualités

// Nombre total de stagiaires inscrits
$nbStagiaires = (int) $pdo->query('SELECT COUNT(*) FROM stagiaires')->fetchColumn();

// Nombre de cotisations payées pour le mois en cours
$requetePaiements = $pdo->prepare('SELECT COUNT(*) FROM mensualites WHERE mois_ref = ? AND est_paye = 1');
$requetePaiements->execute([$moisCourant]);
$nbPayesCeMois = (int) $requetePaiements->fetchColumn();

// Stagiaires sans cotisation ce mois (max 0 pour éviter un chiffre négatif)
$nbImpayesCeMois = max(0, $nbStagiaires - $nbPayesCeMois);

// Pré-inscriptions en attente de traitement
$nbDemandesEnAttente = (int) $pdo->query(
    "SELECT COUNT(*) FROM pre_inscription WHERE statut = 'en_attente'"
)->fetchColumn();


// ============================================================
//  SECTION 2 : Indicateurs de tendance
// ============================================================

// Nouveaux stagiaires inscrits dans le mois courant
$requeteNouveauxStag = $pdo->prepare(
    "SELECT COUNT(*) FROM stagiaires WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?"
);
$requeteNouveauxStag->execute([$moisCourant]);
$nbNouveauxStagMois = (int) $requeteNouveauxStag->fetchColumn();

// Absences enregistrées dans les 30 derniers jours (toutes justifications confondues)
$nbAbsences30j = (int) $pdo->query(
    "SELECT COUNT(*) FROM absences WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
)->fetchColumn();

// Absences non justifiées (toutes périodes confondues)
$nbAbsencesNonJustifiees = (int) $pdo->query(
    "SELECT COUNT(*) FROM absences WHERE est_justifiee = 0"
)->fetchColumn();

// Taux d'assignation en stage : stagiaires ayant au moins un stage renseigné
$nbStagiairesEnStage = (int) $pdo->query(
    "SELECT COUNT(DISTINCT id_stagiaire) FROM stages"
)->fetchColumn();
$pourcentageStages = $nbStagiaires > 0
    ? round(($nbStagiairesEnStage / $nbStagiaires) * 100)
    : 0;


// ============================================================
//  SECTION 3 : Flux d'activité chronologique
//  Fusionne 4 types d'événements triés par horodatage décroissant.
// ============================================================

$fluxActivite = $pdo->query("
    (SELECT 'inscription' AS type,
            CONCAT(prenom,' ',nom) AS label,
            '' AS detail,
            DATE_FORMAT(date_inscription,'%Y-%m-%d %H:%i:%s') AS ts,
            id_stagiaire AS ref_id
     FROM stagiaires
     WHERE date_inscription IS NOT NULL
     ORDER BY id_stagiaire DESC LIMIT 8)

    UNION ALL

    (SELECT 'absence',
            CONCAT(s.prenom,' ',s.nom),
            IF(a.est_justifiee,'justifiée','injustifiée'),
            DATE_FORMAT(a.date_absence,'%Y-%m-%d 12:00:00'),
            a.id_stagiaire
     FROM absences a
     JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
     ORDER BY a.id_absence DESC LIMIT 8)

    UNION ALL

    (SELECT 'document',
            CONCAT(s.prenom,' ',s.nom),
            COALESCE(d.type_document,''),
            DATE_FORMAT(d.genere_le,'%Y-%m-%d %H:%i:%s'),
            d.id_stagiaire
     FROM documents_generes d
     JOIN stagiaires s ON s.id_stagiaire = d.id_stagiaire
     ORDER BY d.id_gen DESC LIMIT 8)

    UNION ALL

    (SELECT 'paiement',
            CONCAT(s.prenom,' ',s.nom),
            m.mois_ref,
            DATE_FORMAT(m.date_paiement,'%Y-%m-%d %H:%i:%s'),
            m.id_stagiaire
     FROM mensualites m
     JOIN stagiaires s ON s.id_stagiaire = m.id_stagiaire
     WHERE m.est_paye = 1 AND m.date_paiement IS NOT NULL
     ORDER BY m.id_mensualite DESC LIMIT 8)

    ORDER BY ts DESC LIMIT 14
")->fetchAll();

/**
 * Retourne une durée relative en français à partir d'un horodatage SQL.
 *
 * Exemples :
 *   "à l'instant"   (< 60 secondes)
 *   "il y a 5 min"  (< 1 heure)
 *   "il y a 3h"     (< 24 heures)
 *   "hier"          (< 48 heures)
 *   "il y a 4 jours" (< 7 jours)
 *   "18/06/2025"    (au-delà de 7 jours)
 *
 * @param string $horodatage Horodatage SQL (ex. "2025-06-18 14:30:00").
 */
function gds_time_ago(string $horodatage): string
{
    if (!$horodatage) {
        return '';
    }
    $ecartSecondes = time() - strtotime($horodatage);

    if ($ecartSecondes < 60)     return "à l'instant";
    if ($ecartSecondes < 3600)   return 'il y a ' . floor($ecartSecondes / 60) . ' min';
    if ($ecartSecondes < 86400)  return 'il y a ' . floor($ecartSecondes / 3600) . 'h';
    if ($ecartSecondes < 172800) return 'hier';
    if ($ecartSecondes < 604800) return 'il y a ' . floor($ecartSecondes / 86400) . ' jours';
    return date('d/m/Y', strtotime($horodatage));
}


// ============================================================
//  SECTION 4 : Données du panneau latéral d'alertes
// ============================================================

// Absences saisies aujourd'hui (indicateur temps réel)
$nbAbsencesAujourdhui = (int) $pdo->query(
    "SELECT COUNT(*) FROM absences WHERE date_absence = CURDATE()"
)->fetchColumn();

// Top 3 des stagiaires les plus absents sur les 30 derniers jours
$topAbsents = $pdo->query(
    "SELECT s.nom, s.prenom, COUNT(*) AS nb
       FROM absences a
       JOIN stagiaires s ON s.id_stagiaire = a.id_stagiaire
      WHERE a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY a.id_stagiaire
      ORDER BY nb DESC
      LIMIT 3"
)->fetchAll();

// Distribution des absences par mois (5 derniers mois) pour l'histogramme
$distributionAbsences = $pdo->query(
    "SELECT DATE_FORMAT(date_absence, '%b') AS m, COUNT(*) AS c
       FROM absences
      GROUP BY m
      ORDER BY MIN(date_absence) DESC
      LIMIT 5"
)->fetchAll();
// Ordre chronologique pour l'affichage gauche → droite
$distributionAbsences = array_reverse($distributionAbsences);

// Valeur maximale pour normaliser la hauteur des barres (minimum 1 pour éviter la division par zéro)
$maxAbsences = 1;
foreach ($distributionAbsences as $ligneAbs) {
    if ($ligneAbs['c'] > $maxAbsences) {
        $maxAbsences = $ligneAbs['c'];
    }
}

?>
<div class="dash-layout">
    <div class="dash-main">

        <!-- ── Bandeau de bienvenue ────────────────────────────────────────── -->
        <div class="dash-welcome card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.16), rgba(34, 211, 238, 0.05)); border-color: rgba(99, 102, 241, 0.25);">
            <h2 style="font-family: 'Instrument Serif', serif; font-size: 2.2rem; color: #fff; margin-bottom: 0.25rem; text-transform:none; letter-spacing:0;">Bienvenue sur le Tableau de Bord 👋</h2>
            <p style="margin:0;font-size:0.95rem;color:rgba(244,244,245,0.7);">Voici le résumé en temps réel de la gestion des stagiaires IPIRNET.</p>
        </div>

        <!-- ── Grille des 4 indicateurs clés ─────────────────────────────── -->
        <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem;">

            <!-- Stagiaires actifs -->
            <div class="stat-card stat-featured" style="border-top: 3px solid #60a5fa; background: linear-gradient(to bottom, rgba(96, 165, 250, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #60a5fa; font-size: 2.4rem; padding-bottom:0.25rem;" data-count="<?= $nbStagiaires ?>">0</div>
                        <div class="stat-label">Stagiaires Actifs</div>
                    </div>
                    <i class="fa-solid fa-users" style="color: rgba(96, 165, 250, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: #a1a1aa; margin-top:0.75rem;">
                    <span style="color:#60a5fa;font-weight:bold;">↑ +<?= $nbNouveauxStagMois ?></span> ce mois-ci
                </div>
            </div>

            <!-- Pré-inscriptions en attente -->
            <div class="stat-card" style="border-top: 3px solid #fbbf24; background: linear-gradient(to bottom, rgba(251, 191, 36, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #fbbf24; font-size: 2.4rem; padding-bottom:0.25rem;" data-count="<?= $nbDemandesEnAttente ?>">0</div>
                        <div class="stat-label">Pré-inscriptions</div>
                    </div>
                    <i class="fa-solid fa-clock" style="color: rgba(251, 191, 36, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: <?= $nbDemandesEnAttente > 0 ? '#fbbf24' : '#a1a1aa' ?>; margin-top:0.75rem;">
                    <?= $nbDemandesEnAttente > 0 ? 'À traiter rapidement' : 'Aucune en attente' ?>
                </div>
            </div>

            <!-- Cotisations payées ce mois -->
            <div class="stat-card" style="border-top: 3px solid #34d399; background: linear-gradient(to bottom, rgba(52, 211, 153, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #34d399; font-size: 2.4rem; padding-bottom:0.25rem;" data-count="<?= $nbPayesCeMois ?>">0</div>
                        <div class="stat-label">Cotisations payées</div>
                    </div>
                    <i class="fa-solid fa-money-check-dollar" style="color: rgba(52, 211, 153, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; margin-top:0.75rem;">
                    <?= $nbImpayesCeMois > 0
                        ? '<span style="color:#f87171;font-weight:bold;">' . $nbImpayesCeMois . ' impayés</span> pour ' . h($moisCourant)
                        : '<span style="color:#34d399;">100% payé ce mois</span>'
                    ?>
                </div>
            </div>

            <!-- Absences sur 30 jours -->
            <div class="stat-card" style="border-top: 3px solid #f87171; background: linear-gradient(to bottom, rgba(248, 113, 113, 0.08) 0%, rgba(255,255,255,0.03) 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="stat-value" style="color: #f87171; font-size: 2.4rem; padding-bottom:0.25rem;" data-count="<?= $nbAbsences30j ?>">0</div>
                        <div class="stat-label">Absences (30j)</div>
                    </div>
                    <i class="fa-solid fa-calendar-xmark" style="color: rgba(248, 113, 113, 0.4); font-size: 1.6rem;"></i>
                </div>
                <div style="font-size: 0.75rem; color: #a1a1aa; margin-top:0.75rem;">
                    Dont <span style="color:#fca5a5;"><?= $nbAbsencesNonJustifiees ?> non justifiées</span>
                </div>
            </div>
        </div>

        <!-- ── Ligne centrale : flux d'activité + graphiques ─────────────── -->
        <div class="grid-stats" style="grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom:1.5rem;">

            <!-- Flux d'activité multi-types (inscriptions, absences, documents, paiements) -->
            <div class="card" style="margin:0; display:flex; flex-direction:column; min-height:0;">
                <h2 style="margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.6rem; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fa-solid fa-bolt" style="color:#fbbf24;"></i> Flux d'activité récent
                    <span style="margin-left:auto; font-size:0.65rem; font-weight:500; color:#52525b; font-family:monospace;">LIVE</span>
                </h2>
                <div style="flex:1; display:flex; flex-direction:column; gap:0.5rem; overflow-y:auto; max-height:380px; padding-right:2px;">
                <?php if (empty($fluxActivite)): ?>
                    <p style="color:#52525b; font-size:0.88rem; font-style:italic; text-align:center; margin:2rem 0;">Aucune activité enregistrée.</p>
                <?php else: ?>
                    <?php
                    // Configuration visuelle par type d'événement
                    $configEvenements = [
                        'inscription' => ['icon' => 'fa-user-plus',   'bg' => 'rgba(96,165,250,0.15)',  'color' => '#60a5fa', 'verb' => 'Nouveau stagiaire'],
                        'absence'     => ['icon' => 'fa-user-clock',  'bg' => 'rgba(248,113,113,0.15)', 'color' => '#f87171', 'verb' => 'Absence enregistrée'],
                        'document'    => ['icon' => 'fa-file-pdf',    'bg' => 'rgba(232,121,249,0.15)', 'color' => '#e879f9', 'verb' => 'Document généré'],
                        'paiement'    => ['icon' => 'fa-circle-check','bg' => 'rgba(52,211,153,0.15)',  'color' => '#34d399', 'verb' => 'Paiement reçu'],
                    ];
                    $dernierJour   = '';
                    $dateAujourdhui = date('Y-m-d');
                    $dateHier       = date('Y-m-d', strtotime('-1 day'));

                    foreach ($fluxActivite as $evenement):
                        $configEv  = $configEvenements[$evenement['type']] ?? $configEvenements['inscription'];
                        $dateJour  = substr((string) $evenement['ts'], 0, 10);
                        $libelleJour = $dateJour === $dateAujourdhui
                            ? "Aujourd'hui"
                            : ($dateJour === $dateHier ? 'Hier' : date('d/m/Y', strtotime($dateJour)));

                        // Séparateur de journée (affiché une seule fois par groupe de dates)
                        if ($libelleJour !== $dernierJour):
                            $dernierJour = $libelleJour;
                    ?>
                    <div style="font-size:0.65rem; font-weight:700; color:#52525b; text-transform:uppercase; letter-spacing:0.1em; padding:0.5rem 0 0.15rem; margin-top:0.25rem;"><?= $libelleJour ?></div>
                    <?php endif; ?>

                    <a href="stagiaires.php?id=<?= (int) $evenement['ref_id'] ?>"
                       style="text-decoration:none; display:flex; align-items:center; gap:0.85rem; padding:0.55rem 0.75rem; background:rgba(255,255,255,0.015); border-radius:8px; border:1px solid rgba(255,255,255,0.04); transition:background 0.2s;"
                       onmouseover="this.style.background='rgba(255,255,255,0.04)'"
                       onmouseout="this.style.background='rgba(255,255,255,0.015)'">
                        <!-- Icône ronde colorée selon le type -->
                        <div style="min-width:30px; height:30px; border-radius:50%; background:<?= $configEv['bg'] ?>; color:<?= $configEv['color'] ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fa-solid <?= $configEv['icon'] ?>" style="font-size:0.75rem;"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:0.82rem; color:#e4e4e7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <span style="color:<?= $configEv['color'] ?>; font-weight:600;"><?= $configEv['verb'] ?></span>
                                — <?= h($evenement['label']) ?>
                                <?php if ($evenement['detail'] !== ''): ?>
                                    <span style="color:#71717a; font-size:0.75rem;">
                                    (<?php
                                        $detail = (string) $evenement['detail'];
                                        if ($evenement['type'] === 'absence') {
                                            // Couleur selon justification
                                            echo $detail === 'justifiée'
                                                ? '<span style="color:#34d399;">justifiée</span>'
                                                : '<span style="color:#f87171;">injustifiée</span>';
                                        } elseif ($evenement['type'] === 'document') {
                                            // Underscores → espaces pour un affichage lisible
                                            echo h(str_replace('_', ' ', $detail));
                                        } else {
                                            echo h($detail);
                                        }
                                    ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="font-size:0.68rem; color:#52525b; white-space:nowrap; flex-shrink:0;"><?= gds_time_ago((string) $evenement['ts']) ?></div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <div style="margin-top:0.85rem; padding-top:0.75rem; border-top:1px solid rgba(255,255,255,0.04); display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.72rem; color:#3f3f46;">Mis à jour à <?= date('H:i') ?></span>
                    <a href="historique_documents.php" style="color:#a855f7; font-size:0.78rem; font-weight:700; text-decoration:none;">
                        Historique complet <i class="fa-solid fa-arrow-right" style="font-size:0.7rem;"></i>
                    </a>
                </div>
            </div>

            <!-- Colonne droite : anneau de stages + histogramme d'absences -->
            <div style="display:flex; flex-direction:column; gap:1.5rem;">

                <!-- Anneau de progression — taux d'assignation en stage -->
                <div class="card" style="margin:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; flex:1;">
                    <h2 style="align-self:flex-start; margin-bottom:0.5rem;">
                        <i class="fa-solid fa-briefcase" style="color:#a855f7; margin-right:0.5rem;"></i> Assignations Stages / PFE
                    </h2>
                    <!-- SVG : cercle vide en fond + arc coloré proportionnel au pourcentage -->
                    <div style="position:relative; width:100px; height:100px; margin: 1rem auto 0.5rem auto;">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="10"></circle>
                            <circle cx="50" cy="50" r="44" fill="none" stroke="#a855f7" stroke-width="10"
                                    stroke-dasharray="276.46"
                                    stroke-dashoffset="<?= 276.46 - (276.46 * $pourcentageStages / 100) ?>"
                                    stroke-linecap="round"
                                    style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 1s ease;"></circle>
                        </svg>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; flex-direction:column;">
                            <span style="font-size:1.3rem; font-weight:700; color:#fff;"><?= $pourcentageStages ?>%</span>
                        </div>
                    </div>
                    <p style="font-size:0.75rem; color:#a1a1aa; margin:0; line-height:1.4;">
                        <?= $nbStagiairesEnStage ?> sur <?= $nbStagiaires ?> stagiaires<br>sont en stage.
                    </p>
                </div>

                <!-- Histogramme des absences par mois (5 derniers mois) -->
                <div class="card" style="margin:0; display:flex; flex-direction:column; flex:1;">
                    <h2 style="margin-bottom:1rem;">
                        <i class="fa-solid fa-chart-column" style="color:#38bdf8; margin-right:0.5rem;"></i> Absences / Mois
                    </h2>
                    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:0.5rem; height:80px; padding-top:0.5rem;">
                        <?php if (empty($distributionAbsences)): ?>
                            <p style="color:#71717a; font-size:0.8rem; text-align:center; width:100%;">Données indisponibles</p>
                        <?php else: ?>
                            <?php foreach ($distributionAbsences as $barreAbs): ?>
                                <?php $hauteurBarre = round(($barreAbs['c'] / $maxAbsences) * 100); ?>
                                <div style="display:flex; flex-direction:column; align-items:center; gap:0.25rem; flex:1; height:100%; justify-content:flex-end;">
                                    <span style="font-size:0.65rem; color:#a1a1aa; font-weight:bold;"><?= $barreAbs['c'] ?></span>
                                    <div style="width:100%; background:linear-gradient(to top, rgba(56, 189, 248, 0.2), #38bdf8); border-radius:4px 4px 0 0; min-height:4%; height:<?= $hauteurBarre ?>%; max-width:24px; transition: height 1s ease;"></div>
                                    <span style="font-size:0.6rem; color:#71717a; text-transform:uppercase;"><?= h($barreAbs['m']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Accès rapide ───────────────────────────────────────────────── -->
        <div class="card" style="margin-bottom:0;">
            <h2 style="margin-bottom:1rem;">Accès rapide</h2>
            <div class="gds-shortcut-row" style="display:flex; flex-wrap:wrap; gap:0.75rem;">
                <a class="btn secondary" href="demandes_inscription.php">
                    <i class="fa-solid fa-clock" style="color:#fbbf24;"></i> Pré-inscriptions
                </a>
                <a class="btn secondary" href="stagiaires.php">
                    <i class="fa-solid fa-users" style="color:#60a5fa;"></i> Liste des stagiaires
                </a>
            </div>
        </div>
    </div>

    <!-- ── Panneau latéral : actions rapides + alertes ────────────────────── -->
    <aside class="dash-aside no-print">

        <!-- Actions rapides -->
        <div class="card" style="border-top: 3px solid #c084fc;">
            <h2>Action Rapide</h2>
            <p style="font-size:0.85rem; color:#a1a1aa; margin:0 0 1rem 0;">Accès direct aux tâches fréquentes.</p>
            <a class="btn btn-accent" style="width:100%; justify-content:center; margin-bottom:0.75rem;" href="stagiaires.php">
                <i class="fa-solid fa-layout-dashboard"></i> Ouvrir hub stagiaires
            </a>
            <a class="btn btn-outline" style="width:100%; justify-content:center; margin-bottom:0.75rem;" href="demandes_inscription.php">
                <i class="fa-solid fa-clock"></i> Traiter demandes
                <?php if ($nbDemandesEnAttente > 0): ?>
                <span style="background:#fbbf24;color:#000;font-size:0.7rem;padding:1px 6px;border-radius:10px;margin-left:4px;font-weight:700;"><?= $nbDemandesEnAttente ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Panneau d'alertes en temps réel -->
        <div class="card" style="padding:1.1rem 1.2rem;">
            <h2 style="font-size:0.8rem; font-weight:700; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.85rem; display:flex; align-items:center; gap:0.4rem;">
                <i class="fa-solid fa-bell" style="color:#fbbf24;"></i> Alertes en cours
            </h2>
            <div style="display:flex; flex-direction:column; gap:0.55rem;">

                <!-- Pré-inscriptions en attente -->
                <a href="demandes_inscription.php"
                   style="text-decoration:none; display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.75rem; border-radius:8px; background:<?= $nbDemandesEnAttente > 0 ? 'rgba(251,191,36,0.08)' : 'rgba(255,255,255,0.02)' ?>; border:1px solid <?= $nbDemandesEnAttente > 0 ? 'rgba(251,191,36,0.25)' : 'rgba(255,255,255,0.05)' ?>; transition:background 0.2s;"
                   onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fa-solid fa-clock" style="color:<?= $nbDemandesEnAttente > 0 ? '#fbbf24' : '#3f3f46' ?>; font-size:0.9rem; width:16px; text-align:center;"></i>
                    <div style="flex:1;">
                        <div style="font-size:0.8rem; color:<?= $nbDemandesEnAttente > 0 ? '#fcd34d' : '#52525b' ?>; font-weight:600;">Pré-inscriptions en attente</div>
                    </div>
                    <span style="font-size:0.85rem; font-weight:800; color:<?= $nbDemandesEnAttente > 0 ? '#fbbf24' : '#3f3f46' ?>;"><?= $nbDemandesEnAttente ?></span>
                </a>

                <!-- Impayés ce mois -->
                <a href="cotisations.php"
                   style="text-decoration:none; display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.75rem; border-radius:8px; background:<?= $nbImpayesCeMois > 0 ? 'rgba(248,113,113,0.08)' : 'rgba(255,255,255,0.02)' ?>; border:1px solid <?= $nbImpayesCeMois > 0 ? 'rgba(248,113,113,0.25)' : 'rgba(255,255,255,0.05)' ?>; transition:background 0.2s;"
                   onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fa-solid fa-money-bill-wave" style="color:<?= $nbImpayesCeMois > 0 ? '#f87171' : '#3f3f46' ?>; font-size:0.9rem; width:16px; text-align:center;"></i>
                    <div style="flex:1;">
                        <div style="font-size:0.8rem; color:<?= $nbImpayesCeMois > 0 ? '#fca5a5' : '#52525b' ?>; font-weight:600;">Impayés ce mois</div>
                    </div>
                    <span style="font-size:0.85rem; font-weight:800; color:<?= $nbImpayesCeMois > 0 ? '#f87171' : '#3f3f46' ?>;"><?= $nbImpayesCeMois ?></span>
                </a>

                <!-- Absences du jour -->
                <a href="absences.php"
                   style="text-decoration:none; display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.75rem; border-radius:8px; background:<?= $nbAbsencesAujourdhui > 0 ? 'rgba(248,113,113,0.06)' : 'rgba(255,255,255,0.02)' ?>; border:1px solid <?= $nbAbsencesAujourdhui > 0 ? 'rgba(248,113,113,0.2)' : 'rgba(255,255,255,0.05)' ?>; transition:background 0.2s;"
                   onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <i class="fa-solid fa-calendar-xmark" style="color:<?= $nbAbsencesAujourdhui > 0 ? '#f87171' : '#3f3f46' ?>; font-size:0.9rem; width:16px; text-align:center;"></i>
                    <div style="flex:1;">
                        <div style="font-size:0.8rem; color:<?= $nbAbsencesAujourdhui > 0 ? '#fca5a5' : '#52525b' ?>; font-weight:600;">Absences aujourd'hui</div>
                    </div>
                    <span style="font-size:0.85rem; font-weight:800; color:<?= $nbAbsencesAujourdhui > 0 ? '#f87171' : '#3f3f46' ?>;"><?= $nbAbsencesAujourdhui ?></span>
                </a>

                <!-- Top 3 absents sur 30 jours -->
                <?php if (!empty($topAbsents)): ?>
                <div style="margin-top:0.4rem; padding-top:0.6rem; border-top:1px solid rgba(255,255,255,0.05);">
                    <div style="font-size:0.65rem; font-weight:700; color:#3f3f46; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.45rem;">Plus absents (30j)</div>
                    <?php foreach ($topAbsents as $absent): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.3rem 0; border-bottom:1px solid rgba(255,255,255,0.03);">
                        <span style="font-size:0.78rem; color:#a1a1aa;"><?= h($absent['prenom'] . ' ' . $absent['nom']) ?></span>
                        <span style="font-size:0.75rem; font-weight:700; color:#f87171; background:rgba(248,113,113,0.12); padding:1px 7px; border-radius:10px;"><?= $absent['nb'] ?>×</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <div style="padding:0.5rem 0.25rem; text-align:center;">
            <span style="font-size:0.68rem; color:#3f3f46;">Mis à jour le <?= date('d/m/Y') ?> à <?= date('H:i') ?></span>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
