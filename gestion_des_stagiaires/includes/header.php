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
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> — IPIRNET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=7">
    <script defer src="assets/js/filiere-filter.js?v=1"></script>
    <script defer src="assets/js/gds-table-filter.js?v=1"></script>
    <?php if ($isPublic): ?>
    <style>
        /* Inline fallback for the public candidature form: black text on white inputs */
        html { color-scheme: light; }
        .public-shell form.compact label,
        .public-shell form.compact legend {
            color: #111111 !important;
        }
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
    <?php endif; ?>
</head>
<body class="<?= $isPublic ? 'public-page' : '' ?>">
<?php if ($isPublic): ?>
<div class="public-shell">
    <header class="public-shell__header">
        <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <a href="login.php" style="display:inline-flex;align-items:center;gap:.6rem;" title="Retour à la page de connexion">
                <img src="assets/img/logo.png" alt="IPIRNET — retour à l'accueil" class="brand-logo-img" width="160" height="48" style="max-height:48px;width:auto;height:auto;object-fit:contain;">
            </a>
            <nav style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                <a class="btn btn--sm btn--ghost" href="inscription.php">Candidature en ligne</a>
                <a class="btn btn--sm btn--primary" href="login.php">Connexion admin</a>
            </nav>
        </div>
    </header>
    <main class="public-shell__main">
    <?php $f = flash_get(); if ($f): ?>
        <div class="msg"><?= h($f) ?></div>
    <?php endif; ?>
    <h1 class="page-title" style="font-family:Instrument Serif,serif;font-size:1.75rem;margin-bottom:1.5rem;"><?= h($pageTitle) ?></h1>
<?php else: ?>
<div class="admin-layout">
    <aside class="sidebar no-print">
        <div class="sidebar-header">
            <a href="index.php" class="brand-logo" style="text-decoration:none;color:inherit;">
                <img src="assets/img/logo.png" alt="IPIRNET" class="brand-logo-sidebar-img">
                <div class="brand-name">
                    <h2>IPIRNET</h2>
                    <span>Admin Portal</span>
                </div>
            </a>
        </div>
        <nav class="sidebar-nav" aria-label="Navigation principale">
            <div class="nav-group">
                <span class="nav-label">Dossier</span>
                <a href="index.php" class="nav-item<?= $curPage === 'index' ? ' active' : '' ?>"><i class="fa-solid fa-gauge"></i> <span>Tableau de bord</span></a>
                <a href="demandes_inscription.php" class="nav-item<?= $curPage === 'demandes' ? ' active' : '' ?>"><i class="fa-solid fa-clock"></i> <span>Demandes d’inscription</span></a>
                <a href="stagiaires.php" class="nav-item<?= $curPage === 'stagiaires' ? ' active' : '' ?>"><i class="fa-solid fa-users"></i> <span>Stagiaires</span></a>
            </div>
            <div class="nav-group">
                <span class="nav-label">Pédagogie</span>
                <a href="moyennes.php" class="nav-item<?= $curPage === 'moyennes' ? ' active' : '' ?>"><i class="fa-solid fa-chart-line"></i> <span>Moyennes</span></a>
                <a href="g_notes.php" class="nav-item<?= $curPage === 'g_notes' ? ' active' : '' ?>"><i class="fa-solid fa-file-lines"></i> <span>Notes</span></a>
                <a href="absences.php" class="nav-item<?= $curPage === 'absences' ? ' active' : '' ?>"><i class="fa-solid fa-calendar-xmark"></i> <span>Absences</span></a>
                <a href="stages.php" class="nav-item<?= $curPage === 'stages' ? ' active' : '' ?>"><i class="fa-solid fa-briefcase"></i> <span>Stages / PFE</span></a>
            </div>
            <div class="nav-group">
                <span class="nav-label">Documents</span>
                <a href="documents_officiels.php" class="nav-item<?= $curPage === 'officiels' ? ' active' : '' ?>"><i class="fa-solid fa-file-contract"></i> <span>Documents officiels</span></a>
                <a href="alertes.php" class="nav-item<?= $curPage === 'alertes' ? ' active' : '' ?>"><i class="fa-solid fa-triangle-exclamation"></i> <span>Alertes</span></a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn btn-outline" style="width:100%;justify-content:center;"><i class="fa-solid fa-right-from-bracket"></i> <span>Déconnexion</span></a>
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
