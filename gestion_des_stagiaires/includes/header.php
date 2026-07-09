<?php
declare(strict_types=1);

// ============================================================
//  header.php — En-tête HTML commun à toutes les pages
//
//  Variables attendues de la page appelante :
//    $pageTitle (string)  — titre affiché dans <title> et en h1 (défaut : 'Gestion des stagiaires')
//    $curPage   (string)  — identifiant de la page active, ex. 'stagiaires' (pour surligner le lien nav)
//    $isPublic  (bool)    — true = shell public (formulaire pré-inscription), false = interface admin
//
//  Deux modes d'affichage :
//    - Mode public  : barre de nav minimaliste + fond sombre, sans sidebar
//    - Mode admin   : sidebar complète avec navigation, filtre d'année, badge notifications
// ============================================================


// ── Valeurs par défaut des variables de page ────────────────
$pageTitle = isset($pageTitle) ? $pageTitle : 'Gestion des stagiaires';
$curPage   = $curPage ?? '';
$isPublic  = !empty($isPublic);


// ============================================================
//  SECTION 1 : Filtre global d'année scolaire
//  Traitement avant tout envoi HTML (nécessite header() possible)
// ============================================================

// Si l'utilisateur change l'année dans le sélecteur de la sidebar,
// on mémorise le choix en session et on redirige vers la même page
// pour éviter le re-POST du formulaire.
if (!$isPublic && isset($_GET['set_global_annee']) && session_status() === PHP_SESSION_ACTIVE) {
    $__anneeSelectionnee = trim((string) ($_GET['global_annee_value'] ?? ''));
    // Valide le format attendu : "2024/2025"
    if (preg_match('/^\d{4}\/\d{4}$/', $__anneeSelectionnee)) {
        $_SESSION['global_annee_scolaire'] = $__anneeSelectionnee;
    }
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
}

// Charge la liste des années disponibles depuis la table classes.
// Si aucune année n'est encore sélectionnée en session, on prend la plus récente.
$__anneesDisponibles = [];
if (!$isPublic && isset($pdo)) {
    try {
        $__anneesDisponibles = $pdo->query(
            "SELECT DISTINCT annee_scolaire
               FROM classes
              WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
           ORDER BY annee_scolaire DESC"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!isset($_SESSION['global_annee_scolaire']) && !empty($__anneesDisponibles)) {
            $_SESSION['global_annee_scolaire'] = $__anneesDisponibles[0];
        }
    } catch (Throwable $e) {
        $__anneesDisponibles = [];
    }
}


// ============================================================
//  SECTION 2 : Utilitaires de rendu d'en-tête
// ============================================================

/**
 * Retourne la date du jour en français long format.
 * Exemple : "mercredi 18 juin 2025"
 * Défini comme closure statique pour éviter de polluer le scope global.
 */
$gdsFrenchDate = static function (): string {
    $date    = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
    $jours   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois    = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $numJour = (int) $date->format('w');
    $numMois = (int) $date->format('n');
    return $jours[$numJour] . ' ' . $date->format('j') . ' ' . $mois[$numMois] . ' ' . $date->format('Y');
};

/**
 * Affiche le message flash en session (s'il existe) puis l'efface.
 * Factorisé ici pour éviter la duplication entre la branche public et la branche admin.
 * Doit être appelé une seule fois par page.
 */
function gds_render_flash(): void
{
    $flash = flash_get();
    if (!$flash) {
        return;
    }

    // Styles CSS inline par type d'alerte (couleurs adaptées au fond sombre de l'app)
    $styleParType = [
        'success' => 'background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.45);color:#6ee7b7;',
        'warning' => 'background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.45);color:#fcd34d;',
        'error'   => 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.45);color:#fca5a5;',
        'info'    => 'background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.35);color:#7dd3fc;',
    ];
    // Icône Font Awesome correspondant à chaque niveau
    $iconeParType = [
        'success' => 'fa-circle-check',
        'warning' => 'fa-triangle-exclamation',
        'error'   => 'fa-circle-xmark',
        'info'    => 'fa-circle-info',
    ];

    $style = $styleParType[$flash['type']] ?? $styleParType['info'];
    $icone = $iconeParType[$flash['type']] ?? 'fa-circle-info';

    echo '<div class="msg" style="' . $style . ' display:flex;align-items:center;gap:.6rem;padding:.75rem 1.25rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem;">';
    echo '<i class="fa-solid ' . $icone . '" style="flex-shrink:0;"></i>';
    echo '<span>' . h($flash['msg']) . '</span>';
    echo '</div>';
    // Jeton discret consommé par nexus.js pour afficher un toast animé
    // en plus du message inline ci-dessus.
    echo '<div id="nx-flash-data" data-type="' . h($flash['type']) . '" data-msg="' . h($flash['msg']) . '" style="display:none;"></div>';
}


// ============================================================
//  SECTION 3 : Badge de notifications (sidebar admin)
// ============================================================

// Compte les pré-inscriptions en attente pour afficher le badge rouge dans la nav.
$notifDots = ['demandes' => 0];
if (!$isPublic && isset($pdo)) {
    try {
        $notifDots['demandes'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM pre_inscription WHERE statut = 'en_attente'"
        )->fetchColumn();
    } catch (Throwable $e) {
        // En cas d'erreur, on affiche 0 (pas de badge) — non critique.
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Jeton CSRF accessible aux scripts AJAX via la balise meta -->
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= h($pageTitle) ?> — IPIRNET</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Bibliothèques d'icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=JetBrains+Mono:wght@400;600;800&display=swap">

    <!-- Feuilles de style de l'application -->
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=7">
    <link rel="stylesheet" href="assets/css/nexus.css?v=2">

    <!-- Scripts différés (ne bloquent pas le rendu de la page) -->
    <script defer src="assets/js/filiere-filter.js?v=1"></script>
    <script defer src="assets/js/gds-table-filter.js?v=1"></script>
    <script defer src="assets/js/validation.js?v=1"></script>
    <script defer src="assets/js/nexus.js?v=2"></script>

<?php if ($isPublic): ?>
    <!-- ── Styles spécifiques au shell public (formulaire pré-inscription) ── -->
    <!-- Force les champs de formulaire en thème clair sur fond sombre,
         y compris les champs auto-remplis par le navigateur (autofill). -->
    <style>
        html { color-scheme: light; }
        .public-shell form.compact label,
        .public-shell form.compact legend { color: #111111 !important; }
        .public-shell form.compact input,
        .public-shell form.compact select,
        .public-shell form.compact textarea {
            background-color: #ffffff !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            caret-color: #111111 !important;
            border: 1px solid #d4d4d8 !important;
            forced-color-adjust: none !important;
        }
        /* Neutralise la teinte bleue imposée par Chrome/Safari sur les champs autofill */
        .public-shell form.compact input:-webkit-autofill,
        .public-shell form.compact input:-webkit-autofill:hover,
        .public-shell form.compact input:-webkit-autofill:focus {
            -webkit-text-fill-color: #111111 !important;
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            caret-color: #111111 !important;
        }
    </style>

<?php else: ?>
    <!-- ── Styles de la sidebar admin (surcharge de app.css) ── -->
    <style>
        .sidebar {
            width: 220px !important;
            background: #12121a !important;
            border-right: 1px solid rgba(255, 255, 255, 0.03) !important;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 2rem 1.25rem 1.5rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            margin-bottom: 0.5rem;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem !important;
        }
        /* Logo avec halo violet derrière */
        .saas-logo-box {
            width: 32px; height: 32px;
            background: url('assets/img/logo.png') no-repeat center center;
            background-size: contain;
            border-radius: 8px;
            position: relative;
        }
        .saas-logo-box::after {
            content: ''; position: absolute; inset: -4px;
            background: #a855f7; filter: blur(12px); opacity: 0.4; z-index: -1;
        }
        .brand-name h2 {
            font-size: 1.15rem !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: 800 !important;
            color: #fff;
            letter-spacing: 0.5px;
            margin-bottom: 0px !important;
            line-height: 1;
        }
        .brand-name span {
            background: rgba(168, 85, 247, 0.2);
            color: #d8b4fe;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.6rem !important;
            font-weight: 700;
            letter-spacing: 0.05em;
            display: inline-block;
            margin-top: 0.35rem;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }
        .sidebar-nav {
            padding: 0 0.75rem 2rem 0.75rem !important;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .nav-group {
            margin-bottom: 1.75rem !important;
        }
        /* Séparateur de section avec lignes latérales */
        .nav-label {
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.6rem !important;
            color: rgba(168, 85, 247, 0.5) !important;
            font-size: 10px !important;
            letter-spacing: 0.15em !important;
            text-transform: uppercase;
        }
        .nav-label::before, .nav-label::after {
            content: '';
            height: 1px;
            background: rgba(168, 85, 247, 0.2);
            flex-grow: 1;
            margin: 0 0.5rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem !important;
            padding: 10px 16px !important;
            color: #a1a1aa !important;
            border-radius: 6px !important;
            font-size: 0.85rem !important;
            font-weight: 500;
            border-left: 3px solid transparent !important;
            transition: all 0.2s ease !important;
            margin-bottom: 2px !important;
            background: transparent !important;
            position: relative;
        }
        .nav-item svg {
            stroke: #a1a1aa;
            stroke-width: 2px;
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            background: #1e1e2e !important;
            color: #ffffff !important;
        }
        .nav-item:hover svg {
            stroke: #a855f7;
        }
        /* Lien actif : bordure gauche violette + fond légèrement teinté */
        .nav-item.active {
            background: #2d1f4e !important;
            border-left: 3px solid #a855f7 !important;
            color: #ffffff !important;
            box-shadow: inset 20px 0 30px -20px rgba(168, 85, 247, 0.4);
        }
        .nav-item.active svg {
            stroke: #ffffff;
        }
        .sidebar-footer {
            padding: 1rem 0.75rem !important;
            border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        .nav-logout {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 10px 16px; border-radius: 6px;
            color: #a1a1aa; font-size: 0.85rem; font-weight: 500;
            text-decoration: none; border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .nav-logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 3px solid #ef4444;
        }
        .nav-logout svg { stroke: #ef4444; width: 18px; height: 18px; }

        /* Badge de notification pulsant sur le lien Pré-inscriptions */
        @keyframes navPulse {
            0%   { box-shadow: 0 0 0 0   rgba(239, 68, 68, 0.7); }
            70%  { box-shadow: 0 0 0 5px rgba(239, 68, 68, 0);   }
            100% { box-shadow: 0 0 0 0   rgba(239, 68, 68, 0);   }
        }
        .nav-badge {
            position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%);
            min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 9px; background: #ef4444; color: #fff;
            font-size: 0.62rem; font-weight: 800; letter-spacing: 0;
            display: flex; align-items: center; justify-content: center;
            animation: navPulse 2s infinite;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25);
        }
    </style>
<?php endif; ?>
</head>
<body class="<?= $isPublic ? 'public-page' : '' ?>">
<canvas id="nx-matrix" aria-hidden="true"></canvas>
<div id="nx-scanlines" aria-hidden="true"></div>
<div id="nx-crosshair" aria-hidden="true"></div>
<div id="nx-glitch-wave" aria-hidden="true">
    <div class="nx-glitch-layer l1"></div>
    <div class="nx-glitch-layer l2"></div>
    <div class="nx-glitch-layer l3"></div>
</div>

<?php if ($isPublic): ?>
<!-- ============================================================
     BRANCHE PUBLIC — Shell minimal pour le formulaire de pré-inscription
     ============================================================ -->
<div class="public-shell">
    <style>
        .public-page { background-color: #0a0a0f !important; color: #fff; min-height: 100vh; position: relative; }
        .public-shell__header {
            background-color: #0f0f0f;
            border-bottom: 1px solid rgba(168, 85, 247, 0.3);
            padding: 1rem 2rem;
            position: sticky; top: 0; z-index: 100;
        }
        .public-nav-link {
            color: #fff; text-decoration: none; font-weight: 500; font-size: 0.95rem; margin-right: 1.5rem; transition: color 0.2s;
        }
        .public-nav-link:hover { color: #a855f7; }
        .public-nav-btn {
            background: transparent; color: #a855f7; border: 1px solid #a855f7;
            padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s;
        }
        .public-nav-btn:hover { background: rgba(168, 85, 247, 0.15); }
        .public-logo-container {
            display: flex; align-items: center; gap: 0.75rem; text-decoration: none;
        }
        .public-logo-icon {
            width: 36px; height: 36px; background: url('assets/img/logo.png') no-repeat center center; background-size: contain;
            border-radius: 8px; position: relative;
        }
        .public-logo-icon::after {
            content: ''; position: absolute; inset: -4px; background: #a855f7; filter: blur(10px); opacity: 0.5; z-index: -1;
        }
        .public-logo-text { color: #fff; font-size: 1.25rem; font-weight: 800; font-family: 'Inter', sans-serif; letter-spacing: 0.5px; margin: 0; }
    </style>
    <header class="public-shell__header">
        <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <a href="index.php" class="public-logo-container">
                <div class="public-logo-icon"></div>
                <h1 class="public-logo-text">IPIRNET</h1>
            </a>
            <nav style="display:flex;flex-wrap:wrap;align-items:center;">
                <a class="public-nav-btn" href="login.php">Connexion admin</a>
            </nav>
        </div>
    </header>
    <main class="public-shell__main" style="position:relative; z-index:10; min-height:85vh; padding-bottom:5rem;">
    <?php gds_render_flash(); ?>

<?php else: ?>
<!-- ============================================================
     BRANCHE ADMIN — Interface complète avec sidebar
     ============================================================ -->
<div class="admin-layout" data-sidebar-collapsed="false">
    <aside class="sidebar no-print">

        <!-- En-tête de la sidebar : logo + nom de l'app -->
        <div class="sidebar-header">
            <a href="index.php" class="brand-logo" style="text-decoration:none;color:inherit;">
                <div class="saas-logo-box"></div>
                <div class="brand-name">
                    <h2>IPIRNET</h2>
                    <span>ADMIN PORTAL</span>
                </div>
            </a>
        </div>

        <!-- Navigation principale -->
        <nav class="sidebar-nav" aria-label="Navigation principale">
            <div class="nav-group">
                <span class="nav-label">DOSSIER</span>

                <a href="index.php" class="nav-item<?= $curPage === 'index' ? ' active' : '' ?>">
                    <i data-lucide="layout-dashboard" width="18" height="18"></i>
                    <span>Tableau de bord</span>
                </a>

                <!-- Badge rouge si des pré-inscriptions sont en attente de traitement -->
                <a href="demandes_inscription.php" class="nav-item<?= $curPage === 'demandes' ? ' active' : '' ?>">
                    <i data-lucide="clipboard-list" width="18" height="18"></i>
                    <span>Pré-inscriptions</span>
                    <?php if ($notifDots['demandes'] > 0): ?>
                    <span class="nav-badge"><?= $notifDots['demandes'] > 99 ? '99+' : $notifDots['demandes'] ?></span>
                    <?php endif; ?>
                </a>

                <a href="stagiaires.php" class="nav-item<?= $curPage === 'stagiaires' ? ' active' : '' ?>"
                   title="Accéder au hub stagiaires pour les notes, absences, stages & classement">
                    <i data-lucide="layout-dashboard" width="18" height="18"></i>
                    <span>Hub Stagiaires</span>
                </a>

                <a href="notes.php" class="nav-item<?= $curPage === 'notes' ? ' active' : '' ?>">
                    <i data-lucide="book-open" width="18" height="18"></i>
                    <span>Gestion des notes</span>
                </a>

                <a href="absences.php" class="nav-item<?= $curPage === 'absences' ? ' active' : '' ?>">
                    <i data-lucide="user-x" width="18" height="18"></i>
                    <span>Gestion des absences</span>
                </a>

                <a href="stages.php" class="nav-item<?= $curPage === 'stages' ? ' active' : '' ?>">
                    <i data-lucide="briefcase" width="18" height="18"></i>
                    <span>Gestion des stages</span>
                </a>

                <a href="cotisations.php" class="nav-item<?= $curPage === 'cotisations' ? ' active' : '' ?>">
                    <i data-lucide="banknote" width="18" height="18"></i>
                    <span>Gestion des paiements</span>
                </a>

                <a href="gestion_modules.php" class="nav-item<?= $curPage === 'modules' ? ' active' : '' ?>">
                    <i data-lucide="layers" width="18" height="18"></i>
                    <span>Gestion des modules</span>
                </a>

                <!-- Accès réservé au Directeur -->
                <?php if (gds_is_directeur()): ?>
                <a href="gestion_classes.php" class="nav-item<?= $curPage === 'classes' ? ' active' : '' ?>">
                    <i data-lucide="school" width="18" height="18"></i>
                    <span>Gestion des classes</span>
                </a>
                <?php endif; ?>

                <a href="historique_documents.php" class="nav-item<?= $curPage === 'historique_docs' ? ' active' : '' ?>">
                    <i data-lucide="scroll-text" width="18" height="18"></i>
                    <span>Historique Documents</span>
                </a>

                <a href="audit_trail.php" class="nav-item<?= $curPage === 'audit_trail' ? ' active' : '' ?>">
                    <i data-lucide="clock" width="18" height="18"></i>
                    <span>Journal des modifs</span>
                </a>

                <a href="rapports.php" class="nav-item<?= $curPage === 'rapports' ? ' active' : '' ?>">
                    <i data-lucide="bar-chart-2" width="18" height="18"></i>
                    <span>Rapports & Exports</span>
                </a>
            </div>
        </nav>

        <!-- Sélecteur d'année scolaire (affiché uniquement si plusieurs années existent) -->
        <?php if (!empty($__anneesDisponibles)): ?>
        <div class="gds-year-selector no-print"
             style="padding:.6rem .75rem .5rem;border-top:1px solid rgba(255,255,255,0.05);">
            <div style="font-size:.65rem;color:rgba(168,85,247,.6);text-transform:uppercase;letter-spacing:.12em;font-weight:700;margin-bottom:.4rem;">
                Année scolaire
            </div>
            <form method="get" action="<?= h(basename($_SERVER['PHP_SELF'])) ?>">
                <input type="hidden" name="set_global_annee" value="1">
                <select name="global_annee_value" onchange="this.form.submit()"
                        style="width:100%;background:#0f0f1a;color:#e4e4e7;border:1px solid rgba(168,85,247,.4);border-radius:6px;padding:.3rem .5rem;font-size:.82rem;cursor:pointer;outline:none;">
                    <?php foreach ($__anneesDisponibles as $__annee): ?>
                    <option value="<?= h($__annee) ?>"
                        <?= (isset($_SESSION['global_annee_scolaire']) && $_SESSION['global_annee_scolaire'] === $__annee) ? 'selected' : '' ?>>
                        <?= h($__annee) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- Pied de sidebar : avatar utilisateur + bouton déconnexion -->
        <div class="sidebar-footer">
            <?php
            // Couleurs et icône adaptées au rôle de l'utilisateur connecté
            $__roleUtilisateur = gds_user_role();
            $__nomUtilisateur  = gds_username();
            $__libelleRole     = $__roleUtilisateur === 'secretaire' ? 'Secrétaire'              : 'Directeur';
            $__couleurRole     = $__roleUtilisateur === 'secretaire' ? '#60a5fa'                  : '#a855f7';
            $__fondRole        = $__roleUtilisateur === 'secretaire' ? 'rgba(96,165,250,0.12)'    : 'rgba(168,85,247,0.12)';
            $__iconeRole       = $__roleUtilisateur === 'secretaire' ? 'fa-user-tie'              : 'fa-shield-halved';
            ?>
            <div style="display:flex; align-items:center; gap:0.6rem; padding:0.6rem 1rem 0.75rem; border-bottom:1px solid rgba(255,255,255,0.05); margin-bottom:0.5rem;">
                <div style="width:30px; height:30px; border-radius:8px; background:<?= $__fondRole ?>; border:1px solid <?= $__couleurRole ?>33; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid <?= $__iconeRole ?>" style="font-size:0.75rem; color:<?= $__couleurRole ?>;"></i>
                </div>
                <div style="min-width:0; flex:1;">
                    <div style="font-size:0.8rem; font-weight:700; color:#e4e4e7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($__nomUtilisateur) ?></div>
                    <div style="font-size:0.67rem; color:<?= $__couleurRole ?>; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;"><?= $__libelleRole ?></div>
                </div>
            </div>
            <a href="logout.php" class="nav-logout">
                <i data-lucide="log-out" width="18" height="18"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </aside>

    <!-- Zone de contenu principale -->
    <main class="main-content">
        <!-- Topbar : toggle sidebar + fil d'ariane + horloge -->
        <div class="nx-topbar no-print">
            <div class="nx-topbar-left">
                <button type="button" id="nx-sidebar-toggle" class="nx-sidebar-toggle" aria-label="Réduire la barre latérale">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="nx-breadcrumb">IPIRNET <i class="fa-solid fa-angle-right" style="font-size:.65rem;margin:0 .35rem;opacity:.5;"></i> <b><?= h($pageTitle) ?></b></span>
            </div>
            <div class="nx-topbar-right">
                <div class="nx-clock-pill">
                    <i class="fa-regular fa-clock"></i>
                    <span id="nx-clock">--:--</span>
                </div>
            </div>
        </div>
        <!-- Overlay utilisé pour l'effet de lumière suivant la souris (voir footer.php) -->
        <div id="mouse-lighting-overlay" class="mouse-light-overlay"></div>
        <div class="page-container">

            <?php gds_render_flash(); ?>

            <!-- En-tête de page : titre + date du jour -->
            <div class="dash-page-head no-print">
                <div>
                    <h1 class="page-title-dash"><?= h($pageTitle) ?></h1>
                    <p class="page-sub-dash">Espace administratif — Groupe IPIRNET</p>
                </div>
                <div class="dash-date"><?= h($gdsFrenchDate()) ?></div>
            </div>
<?php endif; ?>

<!-- Initialise les icônes Lucide après leur chargement via CDN -->
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
