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
    'TSDI' => 'Technicien Spécialisé en Développement Informatique',
    'TGI'  => 'Technicien en Informatique de Gestion',
    'TSGE' => 'Technicien Spécialisé en Gestion des Entreprises',
];

const GDS_MODULES_BY_CODE = [
    'TSDI' => [
        'Métier et formation',
        'L’entreprise et son environnement',
        'Notion de mathématique appliquée',
        'Gestion du temps',
        'Veille technologique',
        'Logiciel d’application',
        'Programmation événementielle',
        'Technique de programmation structurée',
        'Langage de programmation structurée',
        'Programmation orienté objet',
        'Concept et mod d’un system d’information',
        'Installation d’un poste informatique',
        'Communication en Anglais',
        'Assistant technique à la clientèle',
    ],
    'TGI' => [],
    'TSGE' => [],
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

function gds_dedupe_reference_data(PDO $pdo): void {}

function gds_sync_reference_data(PDO $pdo): void {}

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
