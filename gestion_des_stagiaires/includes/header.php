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
$notifDots = ['demandes' => 0];
if (!$isPublic && isset($pdo)) {
    try {
        $notifDots['demandes'] = (int) $pdo->query("SELECT COUNT(*) FROM pre_inscription WHERE statut = 'en_attente'")->fetchColumn();
    } catch(Throwable $e) {}
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= h($pageTitle) ?> — IPIRNET</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=7">
    <script defer src="assets/js/filiere-filter.js?v=1"></script>
    <script defer src="assets/js/gds-table-filter.js?v=1"></script>
    <script defer src="assets/js/validation.js?v=1"></script>
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
                <a class="public-nav-btn" href="login.php">Connexion admin</a>
            </nav>
        </div>
    </header>
    <main class="public-shell__main" style="position:relative; z-index:10; min-height:85vh; padding-bottom:5rem;">
    <?php
    $__f = flash_get();
    if ($__f):
        $__fMsg  = $__f['msg'];
        $__fType = $__f['type'];
        $__fStyleMap = [
            'success' => 'background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.45);color:#6ee7b7;',
            'warning' => 'background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.45);color:#fcd34d;',
            'error'   => 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.45);color:#fca5a5;',
            'info'    => 'background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.35);color:#7dd3fc;',
        ];
        $__fIconMap = [
            'success'=>'fa-circle-check',
            'warning'=>'fa-triangle-exclamation',
            'error'  =>'fa-circle-xmark',
            'info'   =>'fa-circle-info',
        ];
        $__fStyle = $__fStyleMap[$__fType] ?? $__fStyleMap['info'];
        $__fIcon  = $__fIconMap[$__fType]  ?? 'fa-circle-info';
    ?>
    <div class="msg" style="<?= $__fStyle ?> display:flex;align-items:center;gap:.6rem;padding:.75rem 1.25rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem;">
        <i class="fa-solid <?= $__fIcon ?>" style="flex-shrink:0;"></i>
        <span><?= h($__fMsg) ?></span>
    </div>
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
                    <i data-lucide="clipboard-list" width="18" height="18"></i> <span>Pré-inscriptions</span>
                    <?php if($notifDots['demandes'] > 0): ?><span class="nav-dot"></span><?php endif; ?>
                </a>
                <a href="stagiaires.php" class="nav-item<?= $curPage === 'stagiaires' ? ' active' : '' ?>" title="Accéder au hub stagiaires pour les notes, absences, stages & classement">
                    <i data-lucide="layout-dashboard" width="18" height="18"></i> <span>Hub Stagiaires</span>
                </a>
                <a href="notes.php" class="nav-item<?= $curPage === 'notes' ? ' active' : '' ?>">
                    <i data-lucide="book-open" width="18" height="18"></i> <span>Gestion des notes</span>
                </a>
                <a href="absences.php" class="nav-item<?= $curPage === 'absences' ? ' active' : '' ?>">
                    <i data-lucide="user-x" width="18" height="18"></i> <span>Gestion des absences</span>
                </a>
                <a href="stages.php" class="nav-item<?= $curPage === 'stages' ? ' active' : '' ?>">
                    <i data-lucide="briefcase" width="18" height="18"></i> <span>Gestion des stages</span>
                </a>
                <a href="cotisations.php" class="nav-item<?= $curPage === 'cotisations' ? ' active' : '' ?>">
                    <i data-lucide="banknote" width="18" height="18"></i> <span>Gestion des cotisations</span>
                </a>
                <a href="historique_documents.php" class="nav-item<?= $curPage === 'historique_docs' ? ' active' : '' ?>">
                    <i data-lucide="scroll-text" width="18" height="18"></i> <span>Historique Documents</span>
                </a>
                <a href="audit_trail.php" class="nav-item<?= $curPage === 'audit_trail' ? ' active' : '' ?>">
                    <i data-lucide="clock" width="18" height="18"></i> <span>Journal des modifs</span>
                </a>
                <a href="rapports.php" class="nav-item<?= $curPage === 'rapports' ? ' active' : '' ?>">
                    <i data-lucide="bar-chart-2" width="18" height="18"></i> <span>Rapports & Exports</span>
                </a>
            </div>

        </nav>
        <div class="sidebar-footer">
            <?php
            $__role = gds_user_role();
            $__uname = gds_username();
            $__roleLabel = $__role === 'secretaire' ? 'Secrétaire' : 'Directeur';
            $__roleColor = $__role === 'secretaire' ? '#60a5fa' : '#a855f7';
            $__roleBg    = $__role === 'secretaire' ? 'rgba(96,165,250,0.12)' : 'rgba(168,85,247,0.12)';
            $__roleIcon  = $__role === 'secretaire' ? 'fa-user-tie' : 'fa-shield-halved';
            ?>
            <div style="display:flex; align-items:center; gap:0.6rem; padding:0.6rem 1rem 0.75rem; border-bottom:1px solid rgba(255,255,255,0.05); margin-bottom:0.5rem;">
                <div style="width:30px; height:30px; border-radius:8px; background:<?= $__roleBg ?>; border:1px solid <?= $__roleColor ?>33; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid <?= $__roleIcon ?>" style="font-size:0.75rem; color:<?= $__roleColor ?>;"></i>
                </div>
                <div style="min-width:0; flex:1;">
                    <div style="font-size:0.8rem; font-weight:700; color:#e4e4e7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($__uname) ?></div>
                    <div style="font-size:0.67rem; color:<?= $__roleColor ?>; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;"><?= $__roleLabel ?></div>
                </div>
            </div>
            <a href="logout.php" class="nav-logout">
                <i data-lucide="log-out" width="18" height="18"></i> <span>Déconnexion</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div id="mouse-lighting-overlay" class="mouse-light-overlay"></div>
        <div class="page-container">
            <?php
    $__f = flash_get();
    if ($__f):
        $__fMsg  = $__f['msg'];
        $__fType = $__f['type'];
        $__fStyleMap = [
            'success' => 'background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.45);color:#6ee7b7;',
            'warning' => 'background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.45);color:#fcd34d;',
            'error'   => 'background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.45);color:#fca5a5;',
            'info'    => 'background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.35);color:#7dd3fc;',
        ];
        $__fIconMap = [
            'success'=>'fa-circle-check',
            'warning'=>'fa-triangle-exclamation',
            'error'  =>'fa-circle-xmark',
            'info'   =>'fa-circle-info',
        ];
        $__fStyle = $__fStyleMap[$__fType] ?? $__fStyleMap['info'];
        $__fIcon  = $__fIconMap[$__fType]  ?? 'fa-circle-info';
    ?>
    <div class="msg" style="<?= $__fStyle ?> display:flex;align-items:center;gap:.6rem;padding:.75rem 1.25rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem;">
        <i class="fa-solid <?= $__fIcon ?>" style="flex-shrink:0;"></i>
        <span><?= h($__fMsg) ?></span>
    </div>
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



