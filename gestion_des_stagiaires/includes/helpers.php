<?php
declare(strict_types=1);

function gds_fix_text(string $s): string
{
    if ($s === '') {
        return $s;
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        $conv = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        if (is_string($conv) && $conv !== '') {
            $s = $conv;
        }
    }
    $s = str_replace("", '?', $s);

    $repl = [
        "é" => "é", "è" => "è", "ê" => "ê", "ë" => "ë",
        "Sp?cialis?e"  => "Spécialisée",
        "sp?cialis?e"  => "spécialisée",
        "Sp?cialis?"   => "Spécialisé",
        "sp?cialis?"   => "spécialisé",
        "T?l?communication" => "Télécommunication",
        "t?l?communication" => "télécommunication",
        "T?l?phone"    => "Téléphone",
        "t?l?phone"    => "téléphone",
        "D?veloppement" => "Développement",
        "d?veloppement" => "développement",
        "D?velopper"   => "Développer",
        "d?velopper"   => "développer",
        "S?curit?"     => "Sécurité",
        "s?curit?"     => "sécurité",
        "?l?ves"       => "élèves",
        "?l?ve"        => "élève",
        "?tablissement"=> "établissement",
        "g?n?rale"     => "générale",
        "G?n?rale"     => "Générale",
        "g?n?ral"      => "général",
        "G?n?ral"      => "Général",
        "p?riode"      => "période",
        "P?riode"      => "Période",
        "g?n?ration"   => "génération",
        "G?n?ration"   => "Génération",
        "?veloppement" => "éveloppement",
        "?velopper"    => "évelopper",
        "Donn?es"      => "Données",
        "donn?es"      => "données",
        "R?seau"       => "Réseau",
        "r?seau"       => "réseau",
        "Spécialis?e" => "Spécialisée",
        "spécialis?e" => "spécialisée",
        "Spécialis?"  => "Spécialisé",
        "spécialis?"  => "spécialisé",
        "Sp?cialis"    => "Spécialis",
        "sp?cialis"    => "spécialis",
        "stagi?re"     => "stagiaire",
        "?cole"        => "école",
        "P?dagogie"    => "Pédagogie",
        "p?dagogie"    => "pédagogie",
        "1?re"         => "1ère",
        "2?me"         => "2ème",
        "3?me"         => "3ème",
        "Ann?e"        => "Année",
        "ann?e"        => "année",
        "Â" => "",
    ];
    for ($i = 0; $i < 4; $i++) {
        $next = strtr($s, $repl);
        if ($next === $s) {
            break;
        }
        $s = $next;
    }
    return $s;
}

function h(string $s): string
{
    return htmlspecialchars(gds_fix_text($s), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

const GDS_FILIERE_OPTIONS = [
    'TSDI' => 'TSDI',
    'TGI'  => 'TGI',
    'TSGE' => 'TSGE',
];


function gds_module_label(string $moduleName): string
{
    $moduleName = gds_fix_text(trim($moduleName));
    return preg_replace('/^M\.F\.\s*\d+(?:\.\d+)?\s*:\s*/u', '', $moduleName) ?? $moduleName;
}

function gds_filiere_code(string $name): string
{
    if (function_exists('mb_strtolower')) {
        $needle = mb_strtolower(gds_fix_text(trim($name)), 'UTF-8');
        foreach (GDS_FILIERE_OPTIONS as $code => $label) {
            $full = mb_strtolower(gds_fix_text($label), 'UTF-8');
            if ($needle === mb_strtolower($code, 'UTF-8') || $needle === $full) {
                return $code;
            }
        }
    } else {
        $needle = strtolower(gds_fix_text(trim($name)));
        foreach (GDS_FILIERE_OPTIONS as $code => $label) {
            if ($needle === strtolower($code) || $needle === strtolower(gds_fix_text($label))) {
                return $code;
            }
        }
    }
    return $name;
}



function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * @param string $type  'info' | 'success' | 'warning' | 'error'
 */
function flash_set(string $msg, string $type = 'info'): void
{
    $_SESSION['flash']      = $msg;
    $_SESSION['flash_type'] = $type;
}

/**
 * Returns ['msg'=>string,'type'=>string] or null.
 */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $data = [
        'msg'  => (string)$_SESSION['flash'],
        'type' => (string)($_SESSION['flash_type'] ?? 'info'),
    ];
    unset($_SESSION['flash'], $_SESSION['flash_type']);
    return $data;
}

function log_document_gen(PDO $pdo, string $type, int $idStagiaire, ?string $reference = null): void
{
    $allowed = [
        'certificat_scolarite', 'billet_excuse', 'etat_mensualites', 'fiche_inscription', 'recu_paiement',
        'releve_notes', 'bulletin', 'attestation_reussite', 'convention_stage',
        'fiche_preinscription', 'liste_stagiaires', 'etat_paiement', 'rapport_individuel',
        'etat_paiements_annuel', 'autre',
    ];
    if (!in_array($type, $allowed, true)) {
        $type = 'autre';
    }
    $st = $pdo->prepare('INSERT INTO documents_generes (type_document, id_stagiaire, reference) VALUES (?,?,?)');
    $st->execute([$type, $idStagiaire, $reference]);
}

