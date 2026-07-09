<?php
declare(strict_types=1);

// ============================================================
//  auth.php — Authentification, rôles et protection CSRF
//
//  Chargé en premier par bootstrap.php. Gère :
//    - La session PHP (démarrage si nécessaire)
//    - La vérification de connexion et la redirection vers login.php
//    - Les mots de passe par défaut (surchargeables via variables d'env)
//    - La protection CSRF sur tous les formulaires POST
//    - Les helpers de rôle (Directeur / Secrétaire)
// ============================================================

// Démarre la session si elle n'est pas encore active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ============================================================
//  SECTION 1 : Redirection sécurisée
// ============================================================

/**
 * Valide et nettoie la cible de redirection après connexion.
 *
 * Empêche les redirections ouvertes (open redirect) :
 * seules les pages PHP du même site sont autorisées.
 * Toute cible contenant "..", commençant par "http(s)://"
 * ou ne finissant pas par ".php" est remplacée par index.php.
 *
 * @param string $cibleBrute Valeur brute du paramètre GET "next".
 * @return string            Nom de fichier .php sûr (ex. "stagiaires.php").
 */
function gds_safe_next(string $cibleBrute): string
{
    $cibleBrute = trim($cibleBrute);

    // Rejette : vide, traversée de répertoire, URL absolue externe.
    if (
        $cibleBrute === ''
        || str_contains($cibleBrute, '..')
        || str_starts_with($cibleBrute, 'http://')
        || str_starts_with($cibleBrute, 'https://')
    ) {
        return 'index.php';
    }

    // N'utilise que la partie chemin (ignore query string, fragment, etc.).
    $cheminSeul = parse_url($cibleBrute, PHP_URL_PATH);
    if (is_string($cheminSeul) && $cheminSeul !== '') {
        $cibleBrute = $cheminSeul;
    }

    // N'autorise que le nom de fichier (pas de sous-dossiers).
    $nomFichier = basename($cibleBrute);

    if ($nomFichier === '' || $nomFichier === 'login.php' || !str_ends_with($nomFichier, '.php')) {
        return 'index.php';
    }

    return $nomFichier;
}


// ============================================================
//  SECTION 2 : Mots de passe des comptes intégrés
// ============================================================

/**
 * Lit un mot de passe depuis une variable d'environnement, avec valeur par défaut.
 * Factorisé pour éviter la duplication entre les deux comptes intégrés.
 *
 * @param string $nomVariable Variable d'environnement à lire.
 * @param string $defaut      Valeur utilisée si la variable est absente ou vide.
 */
function gds_env_password(string $nomVariable, string $defaut): string
{
    $valeurEnv = getenv($nomVariable);
    return ($valeurEnv !== false && $valeurEnv !== '') ? $valeurEnv : $defaut;
}

/**
 * Mot de passe du compte Directeur.
 * Compte : username "admin", mot de passe par défaut "admin123".
 * En production : définir la variable d'env GDS_ADMIN_PASSWORD.
 */
function gds_admin_password(): string
{
    return gds_env_password('GDS_ADMIN_PASSWORD', 'admin123');
}

/**
 * Mot de passe de repli du compte Secrétaire.
 * Utilisé si la table users ne contient pas encore de secrétaire.
 * Compte : username "secretaire", mot de passe par défaut "secretaire".
 * En production : définir la variable d'env GDS_SECRETAIRE_PASSWORD.
 */
function gds_secretaire_password(): string
{
    return gds_env_password('GDS_SECRETAIRE_PASSWORD', 'secretaire');
}


// ============================================================
//  SECTION 3 : Vérification de session et garde d'accès
// ============================================================

/**
 * Indique si un utilisateur est actuellement connecté (quelle que soit son rôle).
 */
function gds_admin_logged_in(): bool
{
    return !empty($_SESSION['gds_admin']);
}

/**
 * Point de garde appelé par bootstrap.php au chargement de chaque page.
 * Redirige vers login.php si l'utilisateur n'est pas connecté.
 * Les pages login.php et logout.php sont exemptées de cette vérification.
 */
function gds_require_admin_session(): void
{
    $pageCourante = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    // Pages exemptées : pas de vérification de session.
    if (in_array($pageCourante, ['login.php', 'logout.php'], true)) {
        return;
    }

    // Utilisateur connecté : accès autorisé.
    if (gds_admin_logged_in()) {
        return;
    }

    // Non connecté : redirige vers login.php en conservant la page demandée.
    $pageDemandee = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?next=' . rawurlencode($pageDemandee));
    exit;
}


// ============================================================
//  SECTION 4 : Protection CSRF
// ============================================================

/**
 * Retourne le jeton CSRF de la session, en en créant un si absent.
 * Le jeton est un nombre aléatoire de 64 caractères hexadécimaux.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * Vérifie le jeton CSRF au début de chaque handler POST.
 * Accepte le jeton dans le corps du formulaire (champ csrf_token)
 * ou dans l'en-tête HTTP X-CSRF-Token (pour les requêtes AJAX).
 * Arrête l'exécution avec HTTP 403 si le jeton est absent ou invalide.
 */
function csrf_verify(): void
{
    $jetonSoumis = trim((string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    $jetonAttendu = (string) ($_SESSION['csrf_token'] ?? '');

    // hash_equals() compare en temps constant pour éviter les attaques par timing.
    if ($jetonAttendu === '' || !hash_equals($jetonAttendu, $jetonSoumis)) {
        http_response_code(403);
        exit('Requ\u00eate invalide (jeton de s\u00e9curit\u00e9 manquant ou incorrect). Rechargez la page et r\u00e9essayez.');
    }
}

/**
 * Génère un champ <input type="hidden"> contenant le jeton CSRF.
 * À insérer dans chaque formulaire HTML soumis en POST.
 *
 * Usage : <?= csrf_hidden() ?>
 */
function csrf_hidden(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}


// ============================================================
//  SECTION 5 : Rôles utilisateur
// ============================================================

/**
 * Retourne le rôle de l'utilisateur connecté : 'directeur' ou 'secretaire'.
 * Par défaut 'directeur' pour les sessions créées avant l'introduction des rôles.
 */
function gds_user_role(): string
{
    return (string) ($_SESSION['gds_role'] ?? 'directeur');
}

/**
 * Indique si l'utilisateur connecté est Directeur.
 * Le Directeur a accès à toutes les fonctionnalités (CRUD complet, rapports, etc.).
 */
function gds_is_directeur(): bool
{
    return gds_user_role() === 'directeur';
}

/**
 * Indique si l'utilisateur connecté est Secrétaire.
 * La Secrétaire a un accès restreint : consultation et saisie uniquement,
 * sans droits de suppression ni d'acceptation/refus des inscriptions.
 */
function gds_is_secretaire(): bool
{
    return gds_user_role() === 'secretaire';
}

/**
 * Retourne le nom d'affichage de l'utilisateur connecté.
 * Utilisé dans le header pour afficher "Connecté en tant que : X".
 */
function gds_username(): string
{
    return (string) ($_SESSION['gds_username'] ?? 'Directeur');
}
