<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$next = gds_safe_next((string) ($_GET['next'] ?? 'index.php'));

if (gds_admin_logged_in()) {
    redirect($next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string) ($_POST['password'] ?? '');
    $nextPost = gds_safe_next((string) ($_POST['next'] ?? $next));
    if (hash_equals(gds_admin_password(), $pw)) {
        session_regenerate_id(true);
        $_SESSION['gds_admin'] = true;
        flash_set('Connexion réussie.');
        redirect($nextPost);
    }
    flash_set('Mot de passe incorrect.');
    redirect('login.php?next=' . rawurlencode($nextPost));
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — IPIRNET</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css?v=3">
    <style>
        /* Inline fallback: guarantees the password field text is black
           regardless of browser dark mode, autofill, or cached stylesheets. */
        html { color-scheme: light; }
        .auth__form form.compact label {
            color: #111111 !important;
        }
        .auth__form form.compact input[type="password"],
        .auth__form form.compact input[type="text"],
        .auth__form form.compact input[type="email"] {
            background-color: #ffffff !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            caret-color: #111111 !important;
            border: 1px solid #d4d4d8 !important;
            forced-color-adjust: none !important;
        }
        .auth__form form.compact input:-webkit-autofill,
        .auth__form form.compact input:-webkit-autofill:hover,
        .auth__form form.compact input:-webkit-autofill:focus,
        .auth__form form.compact input:-webkit-autofill:active {
            -webkit-text-fill-color: #111111 !important;
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            caret-color: #111111 !important;
        }
    </style>
</head>
<body>
<div class="auth">
    <div class="auth__brand">
        <div>
            <img src="assets/img/logo.png" alt="IPIRNET" class="auth__brand-logo" width="200" height="64">
            <p class="eyebrow" style="color:#888;">Administration</p>
            <h2 style="color:#fff;font-size:clamp(1.5rem,3vw,2rem);margin:0.5rem 0 0;">IPIRNET — Gestion des stagiaires</h2>
            <p style="color:#cfcfcf;max-width:36ch;margin-top:1rem;line-height:1.6;">Accès réservé au personnel.</p>
        </div>
        <p style="color:#888;font-size:0.85rem;margin:0;">© <?= date('Y') ?> IPIRNET</p>
    </div>
    <div class="auth__form">
        <?php $f = flash_get(); if ($f): ?><div class="msg<?= str_contains($f, 'incorrect') ? ' err' : '' ?>"><?= h($f) ?></div><?php endif; ?>
        <h1 class="page-title" style="margin-bottom:1rem;">Connexion administrateur</h1>
        <div class="card">
            <p class="muted" style="margin-top:0;font-size:0.92rem;">Mot de passe par défaut : <code>admin123</code> — définissez <code>GDS_ADMIN_PASSWORD</code> sur la machine pour le changer.</p>
            <form method="post" class="compact">
                <input type="hidden" name="next" value="<?= h($next) ?>">
                <label>Mot de passe <input name="password" type="password" required autocomplete="current-password" autofocus style="color:#111;background:#fff;-webkit-text-fill-color:#111;caret-color:#111;"></label>
                <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;margin-top:0.85rem;">
                    <button type="submit" class="btn">Se connecter</button>
                    <a href="inscription.php" class="btn secondary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Demande d'inscription</a>
                </div>
                <p class="muted" style="margin:0.65rem 0 0;font-size:0.82rem;">Pas encore de compte ? Soumettez une demande d'inscription en attente de validation.</p>
            </form>
        </div>
    </div>
</div>
</body>
</html>
