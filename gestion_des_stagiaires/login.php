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
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/gds-php-blink-compat.css">
</head>
<body>
<div class="auth">
    <div class="auth__brand">
        <div>
            <img src="assets/img/logo.png" alt="IPIRNET" class="auth__brand-logo" width="200" height="64">
            <p class="eyebrow" style="color:#888;">Administration</p>
            <h2 style="color:#fff;font-size:clamp(1.5rem,3vw,2rem);margin:0.5rem 0 0;">IPIRNET — Gestion des stagiaires</h2>
            <p style="color:#cfcfcf;max-width:36ch;margin-top:1rem;line-height:1.6;">Accès réservé au personnel (CDC §4.1).</p>
        </div>
        <p style="color:#888;font-size:0.85rem;margin:0;">© <?= date('Y') ?> IPIRNET</p>
    </div>
    <div class="auth__form">
        <?php $f = flash_get(); if ($f): ?><div class="msg<?= str_contains($f, 'incorrect') ? ' err' : '' ?>"><?= h($f) ?></div><?php endif; ?>
        <h1 class="page-title" style="margin-bottom:1rem;">Connexion administrateur</h1>
        <div class="card">
            <p class="muted" style="margin-top:0;font-size:0.92rem;">Mot de passe par défaut : <code>admin123</code> — définissez <code>GDS_ADMIN_PASSWORD</code> sur la machine pour le changer.</p>
            <p style="font-size:0.9rem;margin:0 0 1rem;"><a href="inscription.php">Formulaire candidat (sans compte)</a> — demande d’inscription en attente de validation.</p>
            <form method="post" class="compact">
                <input type="hidden" name="next" value="<?= h($next) ?>">
                <label>Mot de passe <input name="password" type="password" required autocomplete="current-password" autofocus></label>
                <button type="submit" class="btn" style="margin-top:0.75rem;">Se connecter</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
