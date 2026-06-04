<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'Gestion des stagiaires';
}
$curPage = $curPage ?? '';
$isPublic = !empty($isPublic);

/** French long date for dashboard header */
$gdsFrenchDate = static function (): string {
    $d = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $jw = (int) $d->format('w');
    $m = (int) $d->format('n');
    return $jours[$jw] . ' ' . $d->format('j') . ' ' . $mois[$m] . ' ' . $d->format('Y');
};

// Compute dynamic notification dot badges
$notifDots = ['demandes' => 0, 'alertes' => 0];
if (!$isPublic && isset($pdo)) {
    try {
        $notifDots['demandes'] = (int) $pdo->query("SELECT COUNT(*) FROM demandes_inscription WHERE statut = 'en_attente'")->fetchColumn();
        
        $mc = date('Y-m');
        $stCotis = $pdo->prepare('SELECT COUNT(*) FROM stagiaires s LEFT JOIN mensualites m ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ? AND m.est_paye = 1 WHERE m.id_mensualite IS NULL');
        $stCotis->execute([$mc]);
        $sansCotis = (int) $stCotis->fetchColumn();
        
        $absN = (int) $pdo->query("SELECT COUNT(*) FROM absences WHERE est_justifiee=0 AND date_absence >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();
        $rapN = (int) $pdo->query("SELECT COUNT(*) FROM stages WHERE (rapport_url IS NULL OR rapport_url='') AND date_fin < CURDATE()")->fetchColumn();
        
        $notifDots['alertes'] = $sansCotis + $absN + $rapN;
    } catch(Throwable $e) {}
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> — IPIRNET</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=7">
    <script defer src="assets/js/filiere-filter.js?v=1"></script>
    <script defer src="assets/js/gds-table-filter.js?v=1"></script>
    <?php if ($isPublic): ?>
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
        .public-shell form.compact input:-webkit-autofill,
        .public-shell form.compact input:-webkit-autofill:hover,
        .public-shell form.compact input:-webkit-autofill:focus {
            -webkit-text-fill-color: #111111 !important;
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            caret-color: #111111 !important;
        }
    </style>
    <?php else: ?>
    <!-- ADMIN SIDEBAR SAAS OVERRIDE -->
    <style>
        .sidebar {
            width: 220px !important;
            background: #12121a !important; /* Premium dark depth bg */
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
            line-height:1;
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
            border: 1px solid rgba(168,85,247,0.3);
        }
        .sidebar-nav {
            padding: 0 0.75rem 2rem 0.75rem !important;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .nav-group {
            margin-bottom: 1.75rem !important;
        }
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

        .nav-item.active {
            background: #2d1f4e !important;
            border-left: 3px solid #a855f7 !important;
            color: #ffffff !important;
            box-shadow: inset 20px 0 30px -20px rgba(168,85,247,0.4);
        }
        .nav-item.active svg {
            stroke: #ffffff;
        }
        
        /* Logout specially styled */
        .sidebar-footer {
            padding: 1rem 0.75rem !important;
            border-top: 1px solid rgba(255,255,255,0.05) !important;
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
        .nav-logout svg { stroke: #ef4444; width:18px; height:18px; }

        /* Notification pulsing dots */
        @keyframes navPulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); transform: scale(1); }
            70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); transform: scale(1.1); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); transform: scale(1); }
        }
        .nav-dot {
            position: absolute; right: 1rem; top: 50%; margin-top: -3px; 
            width: 6px; height: 6px; border-radius: 50%; background: #ef4444;
            animation: navPulse 2s infinite;
        }
    </style>
    <?php endif; ?>
</head>
<body class="<?= $isPublic ? 'public-page' : '' ?>">
<?php if ($isPublic): ?>
<div class="public-shell">
    <style>
        .public-page { background-color: #0a0a0f !important; color: #fff; min-height: 100vh; position: relative;}
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
        .public-logo-text { color: #fff; font-size: 1.25rem; font-weight: 800; font-family: 'Inter', sans-serif; letter-spacing: 0.5px; margin: 0;}
    </style>
    <header class="public-shell__header">
        <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <a href="index.php" class="public-logo-container">
                <div class="public-logo-icon"></div>
                <h1 class="public-logo-text">IPIRNET</h1>
            </a>
            <nav style="display:flex;flex-wrap:wrap;align-items:center;">
                <a class="public-nav-link" href="inscription.php">Candidature en ligne</a>
                <a class="public-nav-btn" href="login.php">Connexion admin</a>
            </nav>
        </div>
    </header>
    <main class="public-shell__main" style="position:relative; z-index:10; min-height:85vh; padding-bottom:5rem;">
    <?php $f = flash_get(); if ($f): ?>
        <div class="msg"><?= h($f) ?></div>
    <?php endif; ?>

<?php else: ?>
<div class="admin-layout">
    <aside class="sidebar no-print">
        <div class="sidebar-header">
            <a href="index.php" class="brand-logo" style="text-decoration:none;color:inherit;">
                <div class="saas-logo-box"></div>
                <div class="brand-name">
                    <h2>IPIRNET</h2>
                    <span>ADMIN PORTAL</span>
                </div>
            </a>
        </div>
        <nav class="sidebar-nav" aria-label="Navigation principale">
            <div class="nav-group">
                <span class="nav-label">DOSSIER</span>
                <a href="index.php" class="nav-item<?= $curPage === 'index' ? ' active' : '' ?>">
                    <i data-lucide="layout-dashboard" width="18" height="18"></i> <span>Tableau de bord</span>
                </a>
                <a href="demandes_inscription.php" class="nav-item<?= $curPage === 'demandes' ? ' active' : '' ?>">
                    <i data-lucide="clipboard-list" width="18" height="18"></i> <span>Demandes d'inscription</span>
                    <?php if($notifDots['demandes'] > 0): ?><span class="nav-dot"></span><?php endif; ?>
                </a>
                <a href="stagiaires.php" class="nav-item<?= $curPage === 'stagiaires' ? ' active' : '' ?>">
                    <i data-lucide="users" width="18" height="18"></i> <span>Stagiaires</span>
                </a>
            </div>
            <div class="nav-group">
                <span class="nav-label">PÉDAGOGIE</span>
                <a href="moyennes.php" class="nav-item<?= $curPage === 'moyennes' ? ' active' : '' ?>">
                    <i data-lucide="trending-up" width="18" height="18"></i> <span>Moyennes</span>
                </a>
                <a href="evaluer.php" class="nav-item<?= $curPage === 'evaluer' ? ' active' : '' ?>">
                    <i data-lucide="file-text" width="18" height="18"></i> <span>Notes</span>
                </a>
                <a href="absences.php" class="nav-item<?= $curPage === 'absences' ? ' active' : '' ?>">
                    <i data-lucide="calendar-x" width="18" height="18"></i> <span>Absences</span>
                </a>
                <a href="stages.php" class="nav-item<?= $curPage === 'stages' ? ' active' : '' ?>">
                    <i data-lucide="briefcase" width="18" height="18"></i> <span>Stages / PFE</span>
                </a>
            </div>
            <div class="nav-group">
                <span class="nav-label">DOCUMENTS</span>
                <a href="documents_officiels.php" class="nav-item<?= $curPage === 'officiels' ? ' active' : '' ?>">
                    <i data-lucide="folder-open" width="18" height="18"></i> <span>Documents officiels</span>
                </a>
                <a href="alertes.php" class="nav-item<?= $curPage === 'alertes' ? ' active' : '' ?>">
                    <i data-lucide="bell" width="18" height="18"></i> <span>Alertes</span>
                    <?php if($notifDots['alertes'] > 0): ?><span class="nav-dot"></span><?php endif; ?>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-logout">
                <i data-lucide="log-out" width="18" height="18"></i> <span>Déconnexion</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div id="mouse-lighting-overlay" class="mouse-light-overlay"></div>
        <div class="page-container">
            <?php $f = flash_get(); if ($f): ?>
                <div class="msg"><?= h($f) ?></div>
            <?php endif; ?>
            <div class="dash-page-head no-print">
                <div>
                    <h1 class="page-title-dash"><?= h($pageTitle) ?></h1>
                    <p class="page-sub-dash">Espace administratif — Groupe IPIRNET</p>
                </div>
                <div class="dash-date"><?= h($gdsFrenchDate()) ?></div>
            </div>
<?php endif; ?>
<!-- Must run lucide icons initialization if available -->
<script>
    if(typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
