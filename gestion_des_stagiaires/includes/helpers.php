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
    // Normalize Unicode replacement char (U+FFFD) to literal "?" so strtr patterns match.
    $s = str_replace("�", '?', $s);

    $repl = [
        // double-encoded UTF-8 -> Latin-1 -> UTF-8 leftovers
        "é" => "é", "è" => "è", "ê" => "ê", "ë" => "ë",
        // Fully broken (both accents lost) - put first so longest-match wins
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
        // Partial mojibake (one accent already correct UTF-8, other still '?')
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
    // Multi-pass: each pass may expose new patterns. Cap iterations.
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
    'OPAD' => 'Opérateur Administratif',
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
    'OPAD' => [],
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

function gds_dedupe_reference_data(PDO $pdo): void
{
    // Step 1: collapse duplicate filiere rows that resolve to the same canonical code
    // (e.g. one row inserted as 'TSDI' and another as the full label).
    $rows = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY id_filiere')->fetchAll();
    $groups = [];
    foreach ($rows as $r) {
        $code = gds_filiere_code((string) $r['nom_filiere']);
        if (!isset(GDS_FILIERE_OPTIONS[$code])) {
            continue;
        }
        $groups[$code][] = [
            'id'   => (int) $r['id_filiere'],
            'name' => (string) $r['nom_filiere'],
        ];
    }

    foreach ($groups as $code => $group) {
        if (count($group) < 2) {
            continue;
        }
        $canonicalLabel = GDS_FILIERE_OPTIONS[$code];
        $canonical = null;
        foreach ($group as $g) {
            if ($g['name'] === $canonicalLabel) {
                $canonical = $g;
                break;
            }
        }
        if (!$canonical) {
            usort($group, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
            $canonical = $group[0];
        }
        $cid = (int) $canonical['id'];
        if ($canonical['name'] !== $canonicalLabel) {
            $pdo->prepare('UPDATE filieres SET nom_filiere = ? WHERE id_filiere = ?')
                ->execute([$canonicalLabel, $cid]);
        }
        foreach ($group as $g) {
            if ((int) $g['id'] === $cid) {
                continue;
            }
            $dupId = (int) $g['id'];
            $pdo->prepare('UPDATE classes SET id_filiere = ? WHERE id_filiere = ?')->execute([$cid, $dupId]);
            $pdo->prepare('UPDATE modules SET id_filiere = ? WHERE id_filiere = ?')->execute([$cid, $dupId]);
            $pdo->prepare('DELETE FROM filieres WHERE id_filiere = ?')->execute([$dupId]);
        }
    }

    // Step 2: collapse duplicate modules within the same filière
    // (e.g. 'M.F. 1.1 : Métier...' and 'Métier...' both attached to TSDI).
    $filRows = $pdo->query('SELECT id_filiere FROM filieres')->fetchAll();
    foreach ($filRows as $f) {
        $fid = (int) $f['id_filiere'];
        $modStmt = $pdo->prepare('SELECT id_module, nom_module FROM modules WHERE id_filiere = ? ORDER BY id_module');
        $modStmt->execute([$fid]);
        $modList = $modStmt->fetchAll();
        $byLabel = [];
        foreach ($modList as $m) {
            $label = gds_module_label((string) $m['nom_module']);
            $byLabel[$label][] = [
                'id'   => (int) $m['id_module'],
                'name' => (string) $m['nom_module'],
            ];
        }
        foreach ($byLabel as $label => $group) {
            if (count($group) < 2) {
                continue;
            }
            $canonical = null;
            foreach ($group as $g) {
                if ($g['name'] === $label) {
                    $canonical = $g;
                    break;
                }
            }
            if (!$canonical) {
                usort($group, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
                $canonical = $group[0];
            }
            $cid = (int) $canonical['id'];
            if ($canonical['name'] !== $label) {
                $pdo->prepare('UPDATE modules SET nom_module = ? WHERE id_module = ?')
                    ->execute([$label, $cid]);
            }
            foreach ($group as $g) {
                if ((int) $g['id'] === $cid) {
                    continue;
                }
                $dupId = (int) $g['id'];
                try { $pdo->prepare('UPDATE evaluer SET id_module = ? WHERE id_module = ?')->execute([$cid, $dupId]); } catch (\Throwable $e) {}
                try { $pdo->prepare('UPDATE absences SET id_module = ? WHERE id_module = ?')->execute([$cid, $dupId]); } catch (\Throwable $e) {}
                try { $pdo->prepare('UPDATE g_notes SET id_module = ? WHERE id_module = ?')->execute([$cid, $dupId]); } catch (\Throwable $e) {}
                $pdo->prepare('DELETE FROM modules WHERE id_module = ?')->execute([$dupId]);
            }
        }
    }
}

function gds_sync_reference_data(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        gds_dedupe_reference_data($pdo);
    } catch (\Throwable $e) {
        // Dedupe is best-effort: if a referenced table is missing on a
        // partially-installed DB we still want the rest of the sync to run.
    }

    $findFiliere = $pdo->prepare('SELECT id_filiere, nom_filiere FROM filieres WHERE nom_filiere IN (?, ?) LIMIT 1');
    $insertFiliere = $pdo->prepare('INSERT INTO filieres (nom_filiere) VALUES (?)');
    $findModule = $pdo->prepare('SELECT id_module, nom_module FROM modules WHERE id_filiere = ? AND nom_module IN (?, ?) LIMIT 1');
    $insertModule = $pdo->prepare('INSERT INTO modules (nom_module, id_filiere) VALUES (?, ?)');
    $renameModule = $pdo->prepare('UPDATE modules SET nom_module = ? WHERE id_module = ?');
    $renameFiliere = $pdo->prepare('UPDATE filieres SET nom_filiere = ? WHERE id_filiere = ?');

    foreach (GDS_FILIERE_OPTIONS as $code => $label) {
        $findFiliere->execute([$label, $code]);
        $fRow = $findFiliere->fetch();
        if (!$fRow) {
            $insertFiliere->execute([$label]);
            $fid = (int) $pdo->lastInsertId();
        } else {
            $fid = (int) $fRow['id_filiere'];
            if ((string) $fRow['nom_filiere'] !== $label) {
                $renameFiliere->execute([$label, $fid]);
            }
        }

        foreach (GDS_MODULES_BY_CODE[$code] as $moduleLabel) {
            $legacyLabel = 'M.F. ' . $moduleLabel;
            $findModule->execute([$fid, $moduleLabel, $legacyLabel]);
            $mRow = $findModule->fetch();
            if (!$mRow) {
                $insertModule->execute([$moduleLabel, $fid]);
                continue;
            }
            if ((string) $mRow['nom_module'] !== $moduleLabel) {
                $renameModule->execute([$moduleLabel, (int) $mRow['id_module']]);
            }
        }
    }

    $done = true;
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
