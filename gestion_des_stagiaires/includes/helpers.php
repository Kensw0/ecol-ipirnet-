<?php
declare(strict_types=1);

// ============================================================
//  helpers.php — Fonctions utilitaires partagées
//  Chargé par bootstrap.php au démarrage de chaque page.
// ============================================================


// ============================================================
//  SECTION 1 : Correction d'encodage et échappement HTML
// ============================================================

/**
 * Corrige l'encodage d'une chaîne issue de la base de données.
 *
 * Contexte : la base MariaDB stocke parfois des données saisies
 * sous Windows-1252 (Latin-1) alors que l'application attend de
 * l'UTF-8. Cette fonction :
 *   1. Détecte si la chaîne n'est pas du bon UTF-8 et tente une
 *      conversion depuis Windows-1252.
 *   2. Applique un tableau de remplacement pour corriger les
 *      caractères accentués qui apparaissent sous forme de "?" —
 *      séquelle d'une ancienne double-conversion de charset.
 *   3. Répète jusqu'à 4 fois pour gérer les remplacements en cascade
 *      (ex. "Sp?cialis?e" → "Spécialisée" nécessite deux passes).
 */
function gds_fix_text(string $texte): string
{
    // Chaîne vide : rien à faire.
    if ($texte === '') {
        return $texte;
    }

    // --- Étape 1 : reconversion Windows-1252 → UTF-8 si nécessaire ---
    if (function_exists('mb_check_encoding') && !mb_check_encoding($texte, 'UTF-8')) {
        $textConverti = @mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        if (is_string($textConverti) && $textConverti !== '') {
            $texte = $textConverti;
        }
    }

    // Supprime le caractère de remplacement Unicode (U+FFFD) parasite.
    $texte = str_replace("\u{FFFD}", '?', $texte);

    // --- Étape 2 : table de correction des accentués mal encodés ---
    // Ces entrées correspondent à des caractères UTF-8 dont seul
    // l'octet de tête a survécu (affiché "?") lors de migrations
    // ou d'exports antérieurs.
    $corrections = [
        // Voyelles accentuées isolées
        "é" => "é",
        "è" => "è",
        "ê" => "ê",
        "ë" => "ë",

        // Mots complets fréquents dans les intitulés de filières / modules
        "Sp?cialis?e"        => "Spécialisée",
        "sp?cialis?e"        => "spécialisée",
        "Sp?cialis?"         => "Spécialisé",
        "sp?cialis?"         => "spécialisé",
        "Sp?cialis"          => "Spécialis",
        "sp?cialis"          => "spécialis",
        "T?l?communication"  => "Télécommunication",
        "t?l?communication"  => "télécommunication",
        "T?l?phone"          => "Téléphone",
        "t?l?phone"          => "téléphone",
        "D?veloppement"      => "Développement",
        "d?veloppement"      => "développement",
        "D?velopper"         => "Développer",
        "d?velopper"         => "développer",
        "S?curit?"           => "Sécurité",
        "s?curit?"           => "sécurité",
        "?l?ves"             => "élèves",
        "?l?ve"              => "élève",
        "?tablissement"      => "établissement",
        "g?n?rale"           => "générale",
        "G?n?rale"           => "Générale",
        "g?n?ral"            => "général",
        "G?n?ral"            => "Général",
        "p?riode"            => "période",
        "P?riode"            => "Période",
        "g?n?ration"         => "génération",
        "G?n?ration"         => "Génération",
        "?veloppement"       => "éveloppement",
        "?velopper"          => "évelopper",
        "Donn?es"            => "Données",
        "donn?es"            => "données",
        "R?seau"             => "Réseau",
        "r?seau"             => "réseau",
        "Spécialis?e"        => "Spécialisée",
        "spécialis?e"        => "spécialisée",
        "Spécialis?"         => "Spécialisé",
        "spécialis?"         => "spécialisé",
        "stagi?re"           => "stagiaire",
        "?cole"              => "école",
        "P?dagogie"          => "Pédagogie",
        "p?dagogie"          => "pédagogie",
        "1?re"               => "1ère",
        "2?me"               => "2ème",
        "3?me"               => "3ème",
        "Ann?e"              => "Année",
        "ann?e"              => "année",

        // Artefact de double-encodage : "Â" sans suite est parasite.
        "Â"                  => "",
    ];

    // Jusqu'à 4 passes : certains mots nécessitent plusieurs remplacements
    // successifs (ex. "Sp?cialis?e" → "Spécialis?e" → "Spécialisée").
    for ($passe = 0; $passe < 4; $passe++) {
        $resultat = strtr($texte, $corrections);
        if ($resultat === $texte) {
            break; // Aucun changement : inutile de continuer.
        }
        $texte = $resultat;
    }

    return $texte;
}

/**
 * Échappe une chaîne pour un affichage HTML sûr (prévient les XSS).
 * Applique également gds_fix_text() pour corriger l'encodage à la volée.
 *
 * Usage : echo h($variable);
 */
function h(string $texte): string
{
    return htmlspecialchars(gds_fix_text($texte), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// ============================================================
//  SECTION 2 : Filières et modules
// ============================================================

/**
 * Liste des filières disponibles dans l'établissement.
 * Clé = code court utilisé en base, valeur = libellé affiché.
 */
const GDS_FILIERE_OPTIONS = [
    'TSDI' => 'TSDI',
    'TGI'  => 'TGI',
    'TSGE' => 'TSGE',
];

/**
 * Supprime le préfixe normalisé d'un intitulé de module.
 *
 * Exemple : "M.F. 1.2 : Développement Web" → "Développement Web"
 * Les préfixes de type "M.F. X.Y : " sont générés automatiquement
 * et ne doivent pas apparaître dans les affichages utilisateur.
 */
function gds_module_label(string $nomModule): string
{
    $nomModule = gds_fix_text(trim($nomModule));
    return preg_replace('/^M\.F\.\s*\d+(?:\.\d+)?\s*:\s*/u', '', $nomModule) ?? $nomModule;
}

/**
 * Retourne le code court d'une filière à partir de son nom ou de son code.
 *
 * Exemples :
 *   "TSDI"  → "TSDI"
 *   "tsdi"  → "TSDI"
 *   "TGI"   → "TGI"
 *   "Autre" → "Autre" (inchangé si non reconnu)
 *
 * Utilise mb_strtolower quand mbstring est disponible (PHP 8 standard),
 * avec un repli sur strtolower pour les environnements sans mbstring.
 */
function gds_filiere_code(string $nomFiliere): string
{
    // Détermine la fonction de mise en minuscules selon les extensions dispo.
    $enMinuscules = function_exists('mb_strtolower')
        ? fn(string $valeur): string => mb_strtolower(gds_fix_text($valeur), 'UTF-8')
        : fn(string $valeur): string => strtolower(gds_fix_text($valeur));

    $aiguille = $enMinuscules(trim($nomFiliere));

    foreach (GDS_FILIERE_OPTIONS as $code => $intitule) {
        if ($aiguille === $enMinuscules($code) || $aiguille === $enMinuscules($intitule)) {
            return $code;
        }
    }

    // Filière non reconnue : retourne le nom tel quel.
    return $nomFiliere;
}


// ============================================================
//  SECTION 3 : Navigation et messages flash
// ============================================================

/**
 * Redirige vers une URL et arrête l'exécution du script.
 * Toujours utiliser cette fonction plutôt qu'un header() + exit brut
 * pour garantir l'uniformité des redirections.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Stocke un message flash en session pour l'afficher à la prochaine page.
 *
 * @param string $message Le texte à afficher à l'utilisateur.
 * @param string $type    Niveau d'alerte : 'info' | 'success' | 'warning' | 'error'
 */
function flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash']      = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Récupère et efface le message flash en session.
 *
 * Retourne un tableau ['msg' => string, 'type' => string],
 * ou null si aucun message n'est en attente.
 * Le message est supprimé de la session après lecture (one-shot).
 */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = [
        'msg'  => (string) $_SESSION['flash'],
        'type' => (string) ($_SESSION['flash_type'] ?? 'info'),
    ];

    unset($_SESSION['flash'], $_SESSION['flash_type']);

    return $flash;
}


// ============================================================
//  SECTION 4 : Journalisation des documents générés
// ============================================================

/**
 * Enregistre la génération d'un document dans la table documents_generes.
 *
 * Appelé depuis chaque page print_*.php après la préparation des données,
 * avant l'affichage HTML, pour tracer qui a imprimé quoi et quand.
 *
 * @param PDO         $pdo         Connexion à la base de données.
 * @param string      $type        Type de document (voir liste $typesAutorises).
 * @param int         $idStagiaire Identifiant du stagiaire concerné.
 * @param string|null $reference   Référence optionnelle (ex. numéro de reçu).
 */
function log_document_gen(PDO $pdo, string $type, int $idStagiaire, ?string $reference = null): void
{
    // Liste blanche des types autorisés pour éviter l'injection de valeurs arbitraires.
    $typesAutorises = [
        'certificat_scolarite',
        'billet_excuse',
        'etat_mensualites',
        'fiche_inscription',
        'recu_paiement',
        'releve_notes',
        'bulletin',
        'attestation_reussite',
        'convention_stage',
        'fiche_preinscription',
        'liste_stagiaires',
        'etat_paiement',
        'rapport_individuel',
        'etat_paiements_annuel',
        'autre',
    ];

    // Type inconnu : rabattre sur 'autre' plutôt que planter ou insérer une valeur invalide.
    if (!in_array($type, $typesAutorises, true)) {
        $type = 'autre';
    }

    $requete = $pdo->prepare(
        'INSERT INTO documents_generes (type_document, id_stagiaire, reference) VALUES (?, ?, ?)'
    );
    $requete->execute([$type, $idStagiaire, $reference]);
}
