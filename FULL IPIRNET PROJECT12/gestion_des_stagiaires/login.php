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
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/app.css?v=3">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700;800&display=swap');
        
        body, html {
            margin: 0; padding: 0; height: 100%;
            font-family: 'Inter', 'Geist', sans-serif;
            background: #111118;
            color: #fff;
            overflow: hidden;
        }

        .split-layout {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* ── LEFT PANEL (BRANDING) ── */
        .panel-left {
            width: 60%;
            position: relative;
            background: #0a0a0f;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        /* Animated Gradient Background */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .anim-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(-45deg, #1a0533, #0d0d1a, #0a0a0f, #230847);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: 1;
        }

        /* Subtle Geometric Pattern */
        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.1;
            z-index: 2;
        }

        .brand-content {
            position: relative;
            z-index: 10;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-halo {
            position: relative;
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .logo-halo::after {
            content: '';
            position: absolute;
            width: 140px; height: 140px;
            background: #a855f7;
            filter: blur(50px);
            opacity: 0.3;
            border-radius: 50%;
            z-index: -1;
        }

        .brand-title {
            font-size: 3.5rem;
            font-family: 'Instrument Serif', serif;
            font-weight: bold;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }
        
        .brand-subtitle {
            font-size: 1.75rem;
            color: #a855f7;
            font-family: 'Instrument Serif', serif;
            margin: 0.25rem 0 1rem 0;
            font-style: italic;
        }

        .brand-tagline {
            color: #a1a1aa;
            font-size: 1rem;
            max-width: 400px;
        }

        .feature-pills {
            position: absolute;
            bottom: 4rem;
            display: flex;
            gap: 1rem;
            z-index: 10;
        }
        .feature-pill {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: #d4d4d8;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .left-footer {
            position: absolute;
            bottom: 1.5rem;
            left: 2rem;
            color: rgba(255,255,255,0.2);
            font-size: 0.8rem;
            z-index: 10;
        }


        /* ── RIGHT PANEL (FORM) ── */
        .panel-right {
            width: 40%;
            background: #111118;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: -20px 0 50px rgba(0,0,0,0.5);
            z-index: 20;
        }

        .login-card {
            background: #1a1a2e;
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            border: 1px solid #3d2a6e;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            height: 32px;
            margin-bottom: 1rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }

        .login-header p {
            color: #a1a1aa;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Success/Error Banner Pill */
        .alert-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-pill.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-pill.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #d4d4d8;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            background: #0d0d1a !important;
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-sizing: border-box;
            outline: none;
            letter-spacing: 0.1em;
            -webkit-text-fill-color: #fff !important;
        }
        
        .input-wrapper input:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
        }

        /* Webkit Autofill Override for Dark Mode */
        .input-wrapper input:-webkit-autofill,
        .input-wrapper input:-webkit-autofill:hover, 
        .input-wrapper input:-webkit-autofill:focus, 
        .input-wrapper input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #0d0d1a inset !important;
            -webkit-text-fill-color: #fff !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: #71717a;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #a855f7;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(90deg, #6c2bd9, #9c4dff);
            color: #fff;
            border: none;
            padding: 0.85rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(156, 77, 255, 0.4);
        }
        .btn-submit:active {
            transform: scale(0.98);
        }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading span { display: none; }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #71717a;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }
        .divider::before { margin-right: 1rem; }
        .divider::after { margin-left: 1rem; }

        .btn-outline-purple {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: #a855f7;
            border: 1px solid #a855f7;
            padding: 0.85rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-outline-purple:hover {
            background: rgba(168, 85, 247, 0.1);
        }

        .auth-footer-text {
            text-align: center;
            color: #71717a;
            font-size: 0.8rem;
            margin-top: 1.5rem;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .split-layout { flex-direction: column-reverse; }
            .panel-left, .panel-right { width: 100%; height: 50vh; }
            .panel-left { display: none; /* Hide branding on mobile for focus */ }
            .panel-right { height: 100vh; }
        }
    </style>
</head>
<body>

<div class="split-layout">
    <!-- LEFT PANEL (BRANDING) -->
    <div class="panel-left">
        <div class="anim-gradient"></div>
        <div class="grid-pattern"></div>
        
        <div class="brand-content">
            <div class="logo-halo">
                <img src="assets/img/logo.png" alt="IPIRNET Logo" width="180">
            </div>
            <h1 class="brand-title">IPIRNET</h1>
            <h2 class="brand-subtitle">Gestion des Stagiaires</h2>
            <p class="brand-tagline">Espace administratif sécurisé — Édition 2026</p>
        </div>

        <div class="feature-pills">
            <div class="feature-pill">📊 Suivi pédagogique</div>
            <div class="feature-pill">📋 Gestion des stagiaires</div>
            <div class="feature-pill">📄 Documents officiels</div>
        </div>

        <div class="left-footer">© <?= date('Y') ?> IPIRNET. Tous droits réservés.</div>
    </div>

    <!-- RIGHT PANEL (LOGIN FORM) -->
    <div class="panel-right">
        <div class="login-card">
            
            <div class="login-header">
                <img src="assets/img/logo.png" alt="Logo">
                <h1>Connexion</h1>
                <p>Accès au portail administratif</p>
            </div>

            <?php $f = flash_get(); if ($f): ?>
                <?php $isErr = str_contains($f, 'incorrect'); ?>
                <div class="alert-pill <?= $isErr ? 'error' : 'success' ?>">
                    <i class="fa-solid <?= $isErr ? 'fa-triangle-exclamation' : 'fa-check-circle' ?>"></i>
                    <?= h($f) ?>
                </div>
            <?php endif; ?>

            <form method="post" id="login-form">
                <input type="hidden" name="next" value="<?= h($next) ?>">
                
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock" style="font-size:0.8rem; color:#71717a;"></i> Mot de passe Administrateur</label>
                    <div class="input-wrapper">
                        <input id="password" name="password" type="password" required autocomplete="current-password" autofocus placeholder="••••••••">
                        <button type="button" class="toggle-password" id="togglePswd" aria-label="Afficher le mot de passe">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span>Se connecter <i class="fa-solid fa-arrow-right" style="margin-left:0.25rem;"></i></span>
                </button>
            </form>

            <div class="divider">ou</div>

            <a href="inscription.php" class="btn-outline-purple">
                <i class="fa-solid fa-user-plus" style="margin-right:0.5rem;"></i> Demande d'inscription
            </a>
            
            <div class="auth-footer-text">
                Pas encore de compte ? Soumettez une demande en attente de validation par la direction.
            </div>

        </div>
    </div>
</div>

<script>
    // Password visibility toggle
    const toggleBtn = document.getElementById('togglePswd');
    const pwdInput = document.getElementById('password');
    toggleBtn.addEventListener('click', () => {
        const type = pwdInput.getAttribute('type') === 'password' ? 'text' : 'password';
        pwdInput.setAttribute('type', type);
        toggleBtn.innerHTML = type === 'password' ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
    });

    // Loading state
    document.getElementById('login-form').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        // Let form submit naturally
    });
</script>

</body>
</html>
