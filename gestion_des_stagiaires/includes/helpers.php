<?php
declare(strict_types=1);

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $msg): void
{
    $_SESSION['flash'] = $msg;
}

function flash_get(): ?string
{
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/** Log a generated official document (CDC §4.1 trace). */
function log_document_gen(PDO $pdo, string $type, int $idStagiaire, ?string $reference = null): void
{
    $allowed = [
        'certificat_scolarite', 'billet_excuse', 'etat_mensualites',
        'releve_notes', 'bulletin', 'attestation_reussite', 'convention_stage', 'autre',
    ];
    if (!in_array($type, $allowed, true)) {
        $type = 'autre';
    }
    $st = $pdo->prepare('INSERT INTO documents_generes (type_document, id_stagiaire, reference) VALUES (?,?,?)');
    $st->execute([$type, $idStagiaire, $reference]);
}

function nav_active(string $page, string $cur): string
{
    return $page === $cur ? ' is-active' : '';
}
