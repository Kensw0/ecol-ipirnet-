<?php
declare(strict_types=1);

// ============================================================
//  login.php — Page de connexion au portail administratif
//
//  Gère deux comptes :
//    - Directeur  : username "admin"      — mot de passe via gds_admin_password()
//    - Secrétaire : username "secretaire" — vérifié d'abord dans la table users
//                   (hash bcrypt), puis repli sur gds_secretaire_password()
//
//  Après connexion réussie, redirige vers la page demandée ($cibleRedirection)
//  ou vers index.php par défaut.
// ============================================================

require __DIR__ . '/includes/bootstrap.php';

// Destination après connexion (paramètre GET "next", nettoyé contre les open redirects)
$cibleRedirection = gds_safe_next((string) ($_GET['next'] ?? 'index.php'));

// Déjà connecté : inutile d'afficher le formulaire, on redirige directement.
if (gds_admin_logged_in()) {
    redirect($cibleRedirection);
}


// ============================================================
//  SECTION 1 : Traitement du formulaire de connexion (POST)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motDePasse         = (string) ($_POST['password'] ?? '');
    $nomUtilisateur     = trim((string) ($_POST['username'] ?? ''));
    $cibleApresLogin    = gds_safe_next((string) ($_POST['next'] ?? $cibleRedirection));
    $nomUtilisateurMin  = strtolower($nomUtilisateur);

    // Rôle et nom d'affichage déterminés après vérification des identifiants.
    $role        = null;
    $nomAffiche  = null;

    if ($nomUtilisateur === '' || $nomUtilisateurMin === 'directeur' || $nomUtilisateurMin === 'admin') {
        // ── Compte Directeur ────────────────────────────────────────────────
        // hash_equals() compare en temps constant pour prévenir les attaques timing.
        if (hash_equals(gds_admin_password(), $motDePasse)) {
            $role       = 'directeur';
            $nomAffiche = 'Directeur';
        }
    } else {
        // ── Compte Secrétaire ────────────────────────────────────────────────
        // 1re tentative : vérification dans la table users (password bcrypt).
        $trouveDansBase = false;
        try {
            $requete = $pdo->prepare(
                "SELECT password_hash, role FROM users WHERE username = ? AND role = 'secretaire'"
            );
            $requete->execute([$nomUtilisateur]);
            $ligneUtilisateur = $requete->fetch();

            if ($ligneUtilisateur && password_verify($motDePasse, (string) $ligneUtilisateur['password_hash'])) {
                $trouveDansBase = true;
                $role           = 'secretaire';
                $nomAffiche     = $nomUtilisateur;
            }
        } catch (\PDOException $e) {
            // La table users peut ne pas exister en environnement frais — on ignore
            // silencieusement et on passe au repli ci-dessous.
        }

        // 2e tentative (repli) : identifiants codés en dur si la table ne correspond pas.
        if (!$trouveDansBase && $nomUtilisateurMin === 'secretaire' && hash_equals(gds_secretaire_password(), $motDePasse)) {
            $role       = 'secretaire';
            $nomAffiche = 'Secrétaire';
        }
    }

    if ($role !== null) {
        // ── Connexion réussie ────────────────────────────────────────────────
        // Régénère l'ID de session pour prévenir la fixation de session.
        session_regenerate_id(true);
        $_SESSION['gds_admin']    = true;
        $_SESSION['gds_role']     = $role;
        $_SESSION['gds_username'] = $nomAffiche;
        flash_set('Connexion réussie.');
        redirect($cibleApresLogin);
    }

    // Identifiants invalides : message d'erreur + retour au formulaire.
    flash_set('Identifiants incorrects.', 'error');
    redirect('login.php?next=' . rawurlencode($cibleApresLogin));
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

        /* Disposition côte-à-côte : panneau marque (gauche) + formulaire (droite) */
        .split-layout {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* ── PANNEAU GAUCHE — Marque & branding ─────────────────────────────── */
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

        /* Dégradé animé en arrière-plan du panneau gauche */
        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
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

        /* Grille de points subtile superposée au dégradé */
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

        /* Logo avec halo violet flou derrière */
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

        /* Badges de fonctionnalités en bas du panneau gauche */
        .feature-pills {
            position: absolute;
            bottom: 4rem;
            display: flex;
            gap: 1rem;
            z-index: 10;
        }
        .feature-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
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
            color: rgba(255, 255, 255, 0.2);
            font-size: 0.8rem;
            z-index: 10;
        }

        /* ── PANNEAU DROIT — Formulaire de connexion ────────────────────────── */
        .panel-right {
            width: 40%;
            background: #111118;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: -20px 0 50px rgba(0, 0, 0, 0.5);
            z-index: 20;
        }

        .login-card {
            background: #1a1a2e;
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            border: 1px solid #3d2a6e;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img { height: 32px; margin-bottom: 1rem; }
        .login-header h1  { font-size: 1.5rem; margin: 0 0 0.5rem 0; font-weight: 600; }
        .login-header p   { color: #a1a1aa; font-size: 0.9rem; margin: 0; }

        /* Bannière de retour (succès ou erreur) */
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

        /* Champs de formulaire */
        .form-group { margin-bottom: 1.5rem; }
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
            border: 1px solid rgba(255, 255, 255, 0.1);
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
        /* Neutralise la teinte bleue de l'autofill Chrome/Safari sur fond sombre */
        .input-wrapper input:-webkit-autofill,
        .input-wrapper input:-webkit-autofill:hover,
        .input-wrapper input:-webkit-autofill:focus,
        .input-wrapper input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #0d0d1a inset !important;
            -webkit-text-fill-color: #fff !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* Bouton œil — afficher/masquer le mot de passe */
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
        .toggle-password:hover { color: #a855f7; }

        /* Bouton de soumission avec état "chargement" */
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
        .btn-submit:hover  { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(156, 77, 255, 0.4); }
        .btn-submit:active { transform: scale(0.98); }

        /* Spinner affiché pendant la soumission du formulaire */
        .spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading span    { display: none; }

        /* Séparateur horizontal avec texte centré */
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
            background: rgba(255, 255, 255, 0.1);
        }
        .divider::before { margin-right: 1rem; }
        .divider::after  { margin-left: 1rem; }

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
        .btn-outline-purple:hover { background: rgba(168, 85, 247, 0.1); }

        .auth-footer-text {
            text-align: center;
            color: #71717a;
            font-size: 0.8rem;
            margin-top: 1.5rem;
            line-height: 1.5;
        }

        /* Responsive : masque le panneau de marque sur mobile */
        @media (max-width: 900px) {
            .split-layout { flex-direction: column-reverse; }
            .panel-left, .panel-right { width: 100%; height: 50vh; }
            .panel-left  { display: none; }
            .panel-right { height: 100vh; }
        }
    </style>
</head>
<body>

<div class="split-layout">

    <!-- ── PANNEAU GAUCHE — Branding ──────────────────────────────────────── -->
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

    <!-- ── PANNEAU DROIT — Formulaire de connexion ───────────────────────── -->
    <div class="panel-right">
        <div class="login-card">

            <div class="login-header">
                <img src="assets/img/logo.png" alt="Logo">
                <h1>Connexion</h1>
                <p>Accès au portail administratif</p>
            </div>

            <?php
            // Affiche le message flash (succès ou erreur) s'il en existe un.
            $flash = flash_get();
            if ($flash):
                $flashType = $flash['type'];
                $flashMsg  = $flash['msg'];
                // Considéré comme erreur si le type est 'error' ou si le message le signale.
                $estErreur = ($flashType === 'error') || str_contains($flashMsg, 'incorrect');
            ?>
            <div class="alert-pill <?= $estErreur ? 'error' : 'success' ?>">
                <i class="fa-solid <?= $estErreur ? 'fa-triangle-exclamation' : 'fa-check-circle' ?>"></i>
                <?= h($flashMsg) ?>
            </div>
            <?php endif; ?>

            <form method="post" id="login-form">
                <input type="hidden" name="next" value="<?= h($cibleRedirection) ?>">

                <div class="form-group">
                    <label for="username">
                        <i class="fa-solid fa-user" style="font-size:0.8rem; color:#71717a;"></i>
                        Nom d'utilisateur
                    </label>
                    <div class="input-wrapper">
                        <input id="username" name="username" type="text"
                               autocomplete="username"
                               placeholder="admin  ou  secretaire"
                               style="letter-spacing:normal;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fa-solid fa-lock" style="font-size:0.8rem; color:#71717a;"></i>
                        Mot de passe
                    </label>
                    <div class="input-wrapper">
                        <input id="password" name="password" type="password"
                               required autocomplete="current-password"
                               autofocus placeholder="••••••••">
                        <button type="button" class="toggle-password" id="togglePswd"
                                aria-label="Afficher le mot de passe">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span>Se connecter <i class="fa-solid fa-arrow-right" style="margin-left:0.25rem;"></i></span>
                </button>
            </form>

        </div>
    </div>
</div>

<script>
// ── Bascule afficher / masquer le mot de passe ────────────────────────────
const boutonOeil  = document.getElementById('togglePswd');
const champMotDePasse = document.getElementById('password');

boutonOeil.addEventListener('click', () => {
    const typeActuel = champMotDePasse.getAttribute('type') === 'password' ? 'text' : 'password';
    champMotDePasse.setAttribute('type', typeActuel);
    boutonOeil.innerHTML = typeActuel === 'password'
        ? '<i class="fa-regular fa-eye"></i>'
        : '<i class="fa-regular fa-eye-slash"></i>';
});

// ── État de chargement pendant la soumission ──────────────────────────────
// Affiche le spinner et masque le texte du bouton dès que le formulaire
// est soumis, pour signaler visuellement que la requête est en cours.
document.getElementById('login-form').addEventListener('submit', function () {
    document.getElementById('submitBtn').classList.add('loading');
});
</script>

</body>
</html>
