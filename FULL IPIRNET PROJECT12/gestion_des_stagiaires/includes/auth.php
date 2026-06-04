<?php
declare(strict_types=1);

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
 * Single shared admin password (school demo).
 * Set env GDS_ADMIN_PASSWORD in production; never commit a real secret.
 */
function gds_admin_password(): string
{
    $p = getenv('GDS_ADMIN_PASSWORD');
    if ($p !== false && $p !== '') {
        return $p;
    }
    return 'admin123';
}

function gds_admin_logged_in(): bool
{
    return !empty($_SESSION['gds_admin']);
}

/** Call from bootstrap: blocks all PHP entry points except login/logout. */
function gds_require_admin_session(): void
{
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (in_array($script, ['login.php', 'logout.php', 'inscription.php'], true)) {
        return;
    }
    if (gds_admin_logged_in()) {
        return;
    }
    $next = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}
