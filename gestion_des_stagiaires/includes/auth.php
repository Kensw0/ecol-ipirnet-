<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Reduce open redirect: only same-site *.php targets. */
function gds_safe_next(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || str_contains($raw, '..') || str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
        return 'index.php';
    }
    $path = parse_url($raw, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $raw = $path;
    }
    $b = basename($raw);
    if ($b === '' || $b === 'login.php' || !str_ends_with($b, '.php')) {
        return 'index.php';
    }
    return $b;
}

/**
 * Directeur password. Override via GDS_ADMIN_PASSWORD env var in production.
 * Default: admin123 (username: admin)
 */
function gds_admin_password(): string
{
    $p = getenv('GDS_ADMIN_PASSWORD');
    if ($p !== false && $p !== '') {
        return $p;
    }
    return 'admin123';
}

/**
 * Secrétaire fallback password. Override via GDS_SECRETAIRE_PASSWORD env var in production.
 * Default: secretaire (username: secretaire)
 */
function gds_secretaire_password(): string
{
    $p = getenv('GDS_SECRETAIRE_PASSWORD');
    if ($p !== false && $p !== '') {
        return $p;
    }
    return 'secretaire';
}

function gds_admin_logged_in(): bool
{
    return !empty($_SESSION['gds_admin']);
}

/** Call from bootstrap: blocks all PHP entry points except login/logout. */
function gds_require_admin_session(): void
{
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (in_array($script, ['login.php', 'logout.php'], true)) {
        return;
    }
    if (gds_admin_logged_in()) {
        return;
    }
    $next = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

// ── CSRF protection ────────────────────────────────────────────────────────

/** Returns the current session CSRF token, generating one if absent. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Call at the top of every POST handler.
 * Accepts token in POST body (csrf_token) or HTTP header (X-CSRF-Token).
 */
function csrf_verify(): void
{
    $submitted = trim((string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    $expected  = (string)($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Requ\u00eate invalide (jeton de s\u00e9curit\u00e9 manquant ou incorrect). Rechargez la page et r\u00e9essayez.');
    }
}

/** Renders a hidden CSRF input for use inside HTML forms. */
function csrf_hidden(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ── Goal 9: Role helpers ───────────────────────────────────────────────────

/** Returns 'directeur' or 'secretaire'. Defaults to directeur for existing sessions. */
function gds_user_role(): string
{
    return (string)($_SESSION['gds_role'] ?? 'directeur');
}

function gds_is_directeur(): bool
{
    return gds_user_role() === 'directeur';
}

function gds_is_secretaire(): bool
{
    return gds_user_role() === 'secretaire';
}

/** Display name of the logged-in user. */
function gds_username(): string
{
    return (string)($_SESSION['gds_username'] ?? 'Directeur');
}
