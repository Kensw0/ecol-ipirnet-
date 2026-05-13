<?php
declare(strict_types=1);

function gds_fix_text(string $s): string
{
    if ($s === '') {
        return $s;
    }
    // If the string isn't valid UTF-8 (e.g. legacy Windows-1252 bytes from MySQL latin1 columns),
    // convert it so accented characters render properly instead of as '?'.
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        $conv = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        if (is_string($conv) && $conv !== '') {
            $s = $conv;
        }
    }
    $repl = [
        // double-encoded UTF-8 -> Latin-1 -> UTF-8 leftovers
        "Ã©" => "é", "Ã¨" => "è", "Ãª" => "ê", "Ã«" => "ë",
        "Ã " => "à", "Ã¢" => "â", "Ã§" => "ç",
        "Ã¹" => "ù", "Ã»" => "û", "Ã®" => "î", "Ã¯" => "ï",
        "Ã´" => "ô", "Ã" => "É", "Ã" => "À",
        "â€™" => "’", "â€“" => "–",
        "â€”" => "—",
        // Windows-1252 mojibake from MySQL latin1 mis-tagged columns
        "?veloppement" => "éveloppement",
        "?velopper"   => "évelopper",
        "Donn?es"     => "Données",
        "donn?es"     => "données",
        "R?seau"      => "Réseau",
        "r?seau"      => "réseau",
        "S?curit?"    => "Sécurité",
        "s?curit?"    => "sécurité",
        "Sp?cialis"   => "Spécialis",
        "Spécialis?" => "Spécialisé",  // already-correct é followed by broken é
        "spécialis?" => "spécialisé",
        "Spécialis?e" => "Spécialisée",
        "spécialis?e" => "spécialisée",
        "Technicien Spécialis? en" => "Technicien Spécialisé en",
        "stagi?re"     => "stagiaire",
        "?l?ve"        => "élève",
        "?cole"        => "école",
        "?tablissement"=> "établissement",
        "g?n?rale"     => "générale",
        "G?n?rale"     => "Générale",
        "g?n?ral"      => "général",
        "p?riode"      => "période",
        "P?riode"      => "Période",
        "g?n?ration"   => "génération",
        "sp?cialis"   => "spécialis",
        "P?dagogie"   => "Pédagogie",
        "p?dagogie"   => "pédagogie",
        "1?re"        => "1ère",
        "2?me"        => "2ème",
        "3?me"        => "3ème",
        "Ann?e"       => "Année",
        "ann?e"       => "année",
        "Â" => "",
    ];
    return strtr($s, $repl);
}

function h(string $s): string
{
    return htmlspecialchars(gds_fix_text($s), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        'certificat_scolarite', 'billet_excuse', 'etat_mensualites', 'fiche_inscription', 'recu_paiement',
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
