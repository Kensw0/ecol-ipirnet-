<?php
/**
 * gestion_classes.php — Administration des classes
 *
 * Permet au Directeur de :
 *   - Consulter la liste des classes avec filtres (filière, niveau, année, recherche)
 *   - Créer une nouvelle classe (modale AJAX, DOM mis à jour sans rechargement)
 *   - Modifier le nom et la capacité d'une classe existante (modale AJAX, DOM mis à jour sans rechargement)
 *   - Naviguer vers la liste des stagiaires d'une classe
 *
 * Actions POST (réponse JSON) :
 *   • add_classe       — création d'une nouvelle classe
 *   • edit_classe      — mise à jour du nom et de la capacité d'une classe
 *   • delete_classe    — suppression d'une classe (bloquée si des stagiaires y sont inscrits)
 *   • move_stagiaires  — déplacement en masse de tous les stagiaires d'une classe vers une autre
 *
 * Tables : classes, filieres, stagiaires
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Gestion des Classes';
$curPage   = 'classes';

// Seul le Directeur a accès à cette page
if (!gds_is_directeur()) {
    flash_set('Accès réservé au Directeur.', 'warning');
    redirect('index.php');
}

// S'assurer que la colonne capacite existe (migration non destructive)
try {
    $pdo->exec("ALTER TABLE classes ADD COLUMN IF NOT EXISTS capacite INT UNSIGNED NOT NULL DEFAULT 30");
} catch (\Throwable $ignoree) {}


// ============================================================
//  SECTION 1 : Handlers POST — réponses JSON
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    header('Content-Type: application/json');

    // ── Création d'une nouvelle classe ────────────────────────────────────────
    if (isset($_POST['add_classe'])) {
        $nomClasse      = trim((string)($_POST['nom_classe']     ?? ''));
        $idFiliere      = (int)($_POST['id_filiere']             ?? 0);
        $niveau         = trim((string)($_POST['niveau']         ?? ''));
        $anneeScolaire  = trim((string)($_POST['annee_scolaire'] ?? ''));
        $capacite       = max(1, (int)($_POST['capacite']        ?? 30));

        // Validation : tous les champs obligatoires doivent être renseignés
        if ($nomClasse === '' || $idFiliere <= 0 || $niveau === '' || $anneeScolaire === '') {
            echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis.']);
            exit;
        }

        // Vérification que la filière existe bien en base
        $reqVerifFiliere = $pdo->prepare('SELECT id_filiere FROM filieres WHERE id_filiere = ?');
        $reqVerifFiliere->execute([$idFiliere]);
        if (!$reqVerifFiliere->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Filière invalide.']);
            exit;
        }

        try {
            $pdo->prepare(
                'INSERT INTO classes (nom_classe, annee_scolaire, niveau, id_filiere, capacite)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$nomClasse, $anneeScolaire, $niveau, $idFiliere, $capacite]);

            $idNouvelleClasse = (int)$pdo->lastInsertId();

            // Récupérer le nom de la filière pour la mise à jour DOM côté client
            $reqNomFiliere = $pdo->prepare('SELECT nom_filiere FROM filieres WHERE id_filiere = ?');
            $reqNomFiliere->execute([$idFiliere]);
            $nomFiliere = (string)($reqNomFiliere->fetchColumn() ?: '');

            echo json_encode([
                'success'        => true,
                'msg'            => 'Classe créée avec succès.',
                'id_classe'      => $idNouvelleClasse,
                'nom_classe'     => $nomClasse,
                'annee_scolaire' => $anneeScolaire,
                'niveau'         => $niveau,
                'id_filiere'     => $idFiliere,
                'nom_filiere'    => $nomFiliere,
                'capacite'       => $capacite,
            ]);
        } catch (\Throwable $e) {
            error_log('[gestion_classes.php] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
        }
        exit;
    }

    // ── Modification d'une classe existante (nom + capacité) ──────────────────
    if (isset($_POST['edit_classe'])) {
        $idClasse  = (int)($_POST['id_classe']  ?? 0);
        $nomClasse = trim((string)($_POST['nom_classe'] ?? ''));
        $capacite  = max(1, (int)($_POST['capacite'] ?? 30));

        if ($idClasse <= 0 || $nomClasse === '') {
            echo json_encode(['success' => false, 'error' => 'Données invalides.']);
            exit;
        }

        try {
            $pdo->prepare('UPDATE classes SET nom_classe=?, capacite=? WHERE id_classe=?')
                ->execute([$nomClasse, $capacite, $idClasse]);
            echo json_encode([
                'success'    => true,
                'msg'        => 'Classe mise à jour.',
                'nom_classe' => $nomClasse,
                'capacite'   => $capacite,
            ]);
        } catch (\Throwable $e) {
            error_log('[gestion_classes.php] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
        }
        exit;
    }

    // ── Suppression d'une classe ──────────────────────────────────────────────
    if (isset($_POST['delete_classe'])) {
        $idClasse = (int)($_POST['id_classe'] ?? 0);

        if ($idClasse <= 0) {
            echo json_encode(['success' => false, 'error' => 'Identifiant invalide.']);
            exit;
        }

        // Bloquer la suppression si des stagiaires sont encore inscrits dans cette classe
        $reqEffectif = $pdo->prepare('SELECT COUNT(*) FROM stagiaires WHERE id_classe = ?');
        $reqEffectif->execute([$idClasse]);
        $nbStagiaires = (int)$reqEffectif->fetchColumn();

        if ($nbStagiaires > 0) {
            echo json_encode([
                'success' => false,
                'error'   => 'Impossible de supprimer : ' . $nbStagiaires . ' stagiaire(s) sont inscrits dans cette classe. Désaffectez-les d\'abord.',
            ]);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM classes WHERE id_classe = ?')->execute([$idClasse]);
            echo json_encode(['success' => true, 'msg' => 'Classe supprimée.']);
        } catch (\Throwable $e) {
            error_log('[gestion_classes.php] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
        }
        exit;
    }

    // ── Déplacement en masse des stagiaires d'une classe vers une autre ───────
    if (isset($_POST['move_stagiaires'])) {
        $idClasseSource = (int)($_POST['id_classe_source'] ?? 0);
        $idClasseCible  = (int)($_POST['id_classe_cible']  ?? 0);

        if ($idClasseSource <= 0 || $idClasseCible <= 0 || $idClasseSource === $idClasseCible) {
            echo json_encode(['success' => false, 'error' => 'Classes source et destination invalides.']);
            exit;
        }

        // Vérifier que les deux classes existent bien en base
        $reqVerifDeux = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE id_classe IN (?,?)');
        $reqVerifDeux->execute([$idClasseSource, $idClasseCible]);
        if ((int)$reqVerifDeux->fetchColumn() < 2) {
            echo json_encode(['success' => false, 'error' => 'Une ou les deux classes sont introuvables.']);
            exit;
        }

        // Vérifier la capacité disponible dans la classe cible
        $reqCapCible = $pdo->prepare(
            'SELECT COALESCE(c.capacite,30) - COUNT(s.id_stagiaire) AS places_libres,
                    COUNT(s.id_stagiaire) AS effectif_cible
               FROM classes c
               LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
              WHERE c.id_classe = ?
              GROUP BY c.id_classe'
        );
        $reqCapCible->execute([$idClasseCible]);
        $infoCible = $reqCapCible->fetch();

        // Compter les stagiaires à déplacer
        $reqNbSource = $pdo->prepare('SELECT COUNT(*) FROM stagiaires WHERE id_classe = ?');
        $reqNbSource->execute([$idClasseSource]);
        $nbADeplacer = (int)$reqNbSource->fetchColumn();

        if ($infoCible && (int)$infoCible['places_libres'] < $nbADeplacer) {
            echo json_encode([
                'success' => false,
                'error'   => 'La classe destination n\'a que ' . (int)$infoCible['places_libres']
                           . ' place(s) libre(s) pour ' . $nbADeplacer . ' stagiaire(s) à déplacer.',
            ]);
            exit;
        }

        try {
            $reqDeplacement = $pdo->prepare('UPDATE stagiaires SET id_classe = ? WHERE id_classe = ?');
            $reqDeplacement->execute([$idClasseCible, $idClasseSource]);
            $nbDeplaces = $reqDeplacement->rowCount();
            echo json_encode([
                'success'     => true,
                'msg'         => $nbDeplaces . ' stagiaire(s) déplacé(s) avec succès.',
                'nb_deplaces' => $nbDeplaces,
            ]);
        } catch (\Throwable $e) {
            error_log('[gestion_classes.php] ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    exit;
}


// ============================================================
//  SECTION 2 : Paramètres de filtrage (GET + session globale)
// ============================================================

// Listes de référence pour les sélecteurs de filtres
$toutesLesFilières = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere ASC')->fetchAll();
$anneesDisponibles = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes
      WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
      ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);

// L'année par défaut est celle de la session globale si aucun paramètre ?a= n'est passé
$filtreAnnee    = isset($_GET['a'])   ? trim((string)$_GET['a'])   : ($_SESSION['global_annee_scolaire'] ?? '');
$filtreFiliere  = isset($_GET['f'])   ? (int)$_GET['f']            : 0;
$filtreNiveau   = isset($_GET['niv']) ? trim((string)$_GET['niv']) : '';
$filtreRecherche = isset($_GET['q'])  ? trim((string)$_GET['q'])   : '';


// ============================================================
//  SECTION 3 : Requête principale avec filtres serveur
// ============================================================

$conditionsSql = ['1=1'];
$parametresSql = [];

if ($filtreAnnee !== '') {
    $conditionsSql[] = 'c.annee_scolaire = ?';
    $parametresSql[] = $filtreAnnee;
}
if ($filtreFiliere > 0) {
    $conditionsSql[] = 'c.id_filiere = ?';
    $parametresSql[] = $filtreFiliere;
}
if ($filtreNiveau !== '') {
    $conditionsSql[] = 'c.niveau = ?';
    $parametresSql[] = $filtreNiveau;
}
if ($filtreRecherche !== '') {
    $conditionsSql[] = 'c.nom_classe LIKE ?';
    $parametresSql[] = '%' . $filtreRecherche . '%';
}

$sqlClasses = "
    SELECT c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau,
           COALESCE(c.capacite, 30) AS capacite,
           f.nom_filiere, f.id_filiere,
           COUNT(s.id_stagiaire) AS effectif,
           GREATEST(0, COALESCE(c.capacite, 30) - COUNT(s.id_stagiaire)) AS places_libres
      FROM classes c
      JOIN filieres f ON f.id_filiere = c.id_filiere
      LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
     WHERE " . implode(' AND ', $conditionsSql) . "
     GROUP BY c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau, c.capacite,
              f.nom_filiere, f.id_filiere
     ORDER BY c.annee_scolaire DESC, f.nom_filiere ASC, c.niveau ASC
";

$reqClasses = $pdo->prepare($sqlClasses);
$reqClasses->execute($parametresSql);
$listeClasses = $reqClasses->fetchAll();

// Nombre total de classes toutes années confondues (pour l'affichage du compteur)
$totalToutesClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Liste complète des classes (toutes filières, tous filtres ignorés) pour le sélecteur de destination
$toutesClassesPourDeplacement = $pdo->query(
    "SELECT c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau,
            COALESCE(c.capacite,30) AS capacite,
            f.nom_filiere,
            COUNT(s.id_stagiaire) AS effectif,
            GREATEST(0, COALESCE(c.capacite,30) - COUNT(s.id_stagiaire)) AS places_libres
       FROM classes c
       JOIN filieres f ON f.id_filiere = c.id_filiere
       LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
      GROUP BY c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau, c.capacite, f.nom_filiere
      ORDER BY c.annee_scolaire DESC, f.nom_filiere ASC, c.niveau ASC"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<style>
/* ── Carte principale ── */
.gc-card { background:#12122a; border:1px solid rgba(168,85,247,0.15); border-radius:12px; overflow:hidden; }

/* ── Tableau des classes ── */
.gc-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.gc-table th {
    background:rgba(168,85,247,0.08); color:#a1a1aa; font-size:0.7rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.07em; padding:0.65rem 1rem;
    text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); white-space:nowrap;
}
.gc-table td { padding:0.75rem 1rem; border-bottom:1px solid rgba(255,255,255,0.05); color:#e4e4e7; vertical-align:middle; }
.gc-table tr:last-child td { border-bottom:none; }
.gc-table tr:hover td { background:rgba(168,85,247,0.04); }

/* ── Badges filière / niveau ── */
.gc-badge { display:inline-flex; align-items:center; padding:0.15rem 0.55rem; border-radius:6px; font-size:0.72rem; font-weight:700; white-space:nowrap; }
.gc-badge-filiere { background:rgba(168,85,247,0.12); color:#c4b5fd; border:1px solid rgba(168,85,247,0.25); }
.gc-badge-niveau  { background:rgba(99,102,241,0.12);  color:#a5b4fc; border:1px solid rgba(99,102,241,0.2); }

/* ── Barre de capacité ── */
.gc-cap-bar   { display:flex; align-items:center; gap:0.5rem; }
.gc-cap-track { height:6px; width:72px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; flex-shrink:0; }
.gc-cap-fill  { height:100%; border-radius:3px; }

/* ── Boutons d'action ── */
.gc-btn-edit { background:rgba(168,85,247,0.12); border:1px solid rgba(168,85,247,0.3); color:#c4b5fd; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; white-space:nowrap; }
.gc-btn-edit:hover { background:rgba(168,85,247,0.25); }
.gc-btn-voir { background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.25); color:#a5b4fc; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; white-space:nowrap; }
.gc-btn-voir:hover { background:rgba(99,102,241,0.22); }
.gc-btn-del { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.28); color:#fca5a5; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; white-space:nowrap; }
.gc-btn-del:hover { background:rgba(239,68,68,0.22); }
.gc-btn-move { background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.28); color:#fcd34d; border-radius:7px; padding:0.35rem 0.8rem; font-size:0.78rem; font-weight:600; cursor:pointer; transition:background .15s; white-space:nowrap; }
.gc-btn-move:hover { background:rgba(245,158,11,0.22); }

/* ── Modales (ajout / modification) ── */
.gc-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9000; align-items:center; justify-content:center; padding:1rem; }
.gc-modal-box { background:#1a1a2e; border-radius:14px; padding:1.75rem; width:100%; max-width:480px; }
.gc-field { margin-bottom:1rem; }
.gc-field label { display:block; font-size:0.78rem; color:#a1a1aa; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.4rem; }
.gc-field input, .gc-field select {
    width:100%; background:#111; border:1.5px solid rgba(255,255,255,0.1); border-radius:8px;
    color:#fff; padding:0.62rem 0.85rem; font-size:0.9rem; outline:none; box-sizing:border-box;
}
.gc-field input:focus, .gc-field select:focus { border-color:#a855f7; }
.gc-submit { width:100%; padding:0.7rem; border-radius:8px; background:rgba(168,85,247,0.15); border:1px solid rgba(168,85,247,0.4); color:#d8b4fe; font-weight:700; font-size:0.92rem; cursor:pointer; }
.gc-submit:hover { background:rgba(168,85,247,0.28); }
.gc-err-box { display:none; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:0.55rem 0.9rem; margin-bottom:0.85rem; font-size:0.83rem; color:#f87171; }

/* ── Notification toast ── */
.gc-toast {
    position:fixed; bottom:1.5rem; right:1.5rem; z-index:99999;
    background:#1e1e3f; border:1px solid rgba(168,85,247,0.4); border-radius:10px;
    padding:0.85rem 1.2rem; font-size:0.88rem; color:#e4e4e7;
    display:flex; align-items:center; gap:0.6rem;
    box-shadow:0 4px 24px rgba(0,0,0,0.4);
    opacity:0; transform:translateY(12px); transition:all .25s; pointer-events:none; max-width:380px;
}
.gc-toast.show { opacity:1; transform:translateY(0); pointer-events:auto; }

/* ── Barre de filtres ── */
.gc-filters {
    display:flex; flex-wrap:wrap; gap:0.55rem; align-items:center;
    background:#12122a; border:1px solid rgba(168,85,247,0.15);
    border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem;
}
.gc-filter-input, .gc-filter-sel {
    background:#0d0d1f; border:1.5px solid rgba(255,255,255,0.09); border-radius:7px;
    color:#e4e4e7; padding:0.45rem 0.75rem; font-size:0.83rem; outline:none;
    transition:border-color .15s;
}
.gc-filter-input { width:180px; }
.gc-filter-input:focus, .gc-filter-sel:focus { border-color:#a855f7; }
.gc-filter-input::placeholder { color:#52525b; }
.gc-filter-sel { cursor:pointer; }
.gc-filter-reset {
    background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
    color:#71717a; border-radius:7px; padding:0.45rem 0.8rem; font-size:0.8rem;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.gc-filter-reset:hover { background:rgba(255,255,255,0.1); color:#a1a1aa; }
.gc-filter-count { margin-left:auto; font-size:0.78rem; color:#71717a; white-space:nowrap; }
.gc-filter-count span { color:#a855f7; font-weight:700; }

/* ── Responsive : masquer colonnes secondaires sur petits écrans ── */
@media (max-width:680px) {
    .gc-table th:nth-child(4), .gc-table td:nth-child(4) { display:none; }
    .gc-table th:nth-child(6), .gc-table td:nth-child(6) { display:none; }
    .gc-filter-input { width:100%; }
    .gc-filters { gap:0.45rem; }
}
</style>

<div class="main-content">

<!-- ── En-tête de page ── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 style="margin:0 0 0.2rem;font-size:1.3rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:0.6rem;">
            <i class="fa-solid fa-chalkboard" style="color:#a855f7;"></i> Gestion des Classes
        </h1>
        <p style="margin:0;font-size:0.83rem;color:#71717a;" id="gc-subtitle">
            <?= count($listeClasses) ?> classe(s) enregistrée(s)
        </p>
    </div>
    <button type="button" onclick="ouvrirModaleAjout()"
            style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 1.1rem;border-radius:8px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.35);color:#6ee7b7;font-weight:700;font-size:0.88rem;cursor:pointer;">
        <i class="fa-solid fa-plus"></i> Ajouter une classe
    </button>
</div>

<?php if ($totalToutesClasses === 0): ?>
<!-- État vide : aucune classe en base ────────────────────────────────────────── -->
<div style="text-align:center;padding:4rem 1rem;color:#71717a;">
    <i class="fa-solid fa-chalkboard" style="font-size:2.5rem;margin-bottom:1rem;display:block;opacity:0.25;"></i>
    Aucune classe enregistrée. Cliquez sur "Ajouter une classe" pour commencer.
</div>
<?php else: ?>

<!-- ── Barre de filtres ── -->
<form method="get" action="gestion_classes.php" id="gc-filter-form" class="gc-filters">
    <input type="search" name="q" id="gc-search" class="gc-filter-input"
           placeholder="🔍 Rechercher une classe…"
           value="<?= h($filtreRecherche) ?>" autocomplete="off">

    <select name="f" class="gc-filter-sel" onchange="this.form.submit()">
        <option value="0">Toutes les filières</option>
        <?php foreach ($toutesLesFilières as $filiere): ?>
        <option value="<?= (int)$filiere['id_filiere'] ?>" <?= $filtreFiliere === (int)$filiere['id_filiere'] ? 'selected' : '' ?>>
            <?= h($filiere['nom_filiere']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="niv" class="gc-filter-sel" onchange="this.form.submit()">
        <option value="">Tous les niveaux</option>
        <option value="1ère année" <?= $filtreNiveau === '1ère année' ? 'selected' : '' ?>>1ère année</option>
        <option value="2ème année" <?= $filtreNiveau === '2ème année' ? 'selected' : '' ?>>2ème année</option>
    </select>

    <select name="a" class="gc-filter-sel" onchange="this.form.submit()">
        <option value="">Toutes les années</option>
        <?php foreach ($anneesDisponibles as $annee): ?>
        <option value="<?= h($annee) ?>" <?= $filtreAnnee === $annee ? 'selected' : '' ?>><?= h($annee) ?></option>
        <?php endforeach; ?>
    </select>

    <a href="gestion_classes.php" class="gc-filter-reset">
        <i class="fa-solid fa-rotate-left"></i> Réinitialiser
    </a>

    <div class="gc-filter-count" id="gc-filter-count">
        <span id="gc-count-visible"><?= count($listeClasses) ?></span> / <?= $totalToutesClasses ?> classe(s)
    </div>
</form>

<?php if (empty($listeClasses)): ?>
<!-- État vide : filtres ne correspondent à aucune classe ──────────────────── -->
<div style="text-align:center;padding:2.5rem 1rem;color:#71717a;font-size:0.9rem;">
    <i class="fa-solid fa-filter-circle-xmark" style="font-size:1.8rem;display:block;margin-bottom:0.75rem;opacity:0.3;"></i>
    Aucune classe ne correspond aux filtres.
    <br><a href="gestion_classes.php" style="margin-top:0.75rem;display:inline-block;color:#a855f7;font-size:0.85rem;text-decoration:underline;">Réinitialiser les filtres</a>
</div>
<?php else: ?>

<!-- ── Tableau des classes ── -->
<div class="gc-card">
    <div style="overflow-x:auto;">
    <table class="gc-table">
        <thead>
            <tr>
                <th><i class="fa-solid fa-chalkboard-user" style="margin-right:0.3rem;color:#a855f7;"></i> Classe</th>
                <th>Filière</th>
                <th>Niveau</th>
                <th>Année scolaire</th>
                <th>Capacité</th>
                <th>Occupancy</th>
                <th>Places libres</th>
                <th style="text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody id="gc-tbody">
        <?php foreach ($listeClasses as $classe): ?>
            <?php
                // Calcul des indicateurs de remplissage pour la barre de capacité
                $effectif       = (int)$classe['effectif'];
                $capacite       = (int)$classe['capacite'];
                $pourcentage    = $capacite > 0 ? min(100, (int)round($effectif / $capacite * 100)) : 0;
                $couleurBarre   = $pourcentage >= 100 ? '#ef4444' : ($pourcentage >= 80 ? '#fb923c' : '#10b981');
                $placesLibres   = max(0, $capacite - $effectif);
            ?>
            <tr class="gc-row" data-id="<?= (int)$classe['id_classe'] ?>">
                <td>
                    <span class="gc-nom-classe" style="font-weight:700;color:#fff;"><?= h($classe['nom_classe']) ?></span>
                </td>
                <td><span class="gc-badge gc-badge-filiere"><?= h($classe['nom_filiere']) ?></span></td>
                <td><span class="gc-badge gc-badge-niveau"><?= h($classe['niveau']) ?></span></td>
                <td style="color:#a1a1aa;font-size:0.85rem;"><?= h($classe['annee_scolaire']) ?></td>
                <td style="font-weight:600;color:#d8b4fe;" class="gc-cap-val"><?= $capacite ?></td>
                <td>
                    <div class="gc-cap-bar">
                        <span class="gc-eff-val" style="font-weight:600;color:<?= $couleurBarre ?>;min-width:24px;"><?= $effectif ?></span>
                        <div class="gc-cap-track">
                            <div class="gc-cap-fill" style="width:<?= $pourcentage ?>%;background:<?= $couleurBarre ?>;"></div>
                        </div>
                        <span class="gc-pct-val" style="font-size:0.7rem;color:#71717a;"><?= $pourcentage ?>%</span>
                    </div>
                </td>
                <td class="gc-libres-cell">
                    <?php if ($placesLibres === 0): ?>
                        <span style="font-weight:700;color:#ef4444;font-size:0.82rem;">
                            <i class="fa-solid fa-ban"></i> Pleine
                        </span>
                    <?php elseif ($placesLibres <= 5): ?>
                        <span style="font-weight:700;color:#fb923c;"><?= $placesLibres ?> place(s)</span>
                    <?php else: ?>
                        <span style="font-weight:700;color:#10b981;"><?= $placesLibres ?> place(s)</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <div style="display:flex;gap:0.4rem;justify-content:center;flex-wrap:wrap;">
                        <button class="gc-btn-edit"
                            onclick="ouvrirModaleModif(
                                <?= (int)$classe['id_classe'] ?>,
                                <?= htmlspecialchars(json_encode($classe['nom_classe']), ENT_QUOTES) ?>,
                                <?= $capacite ?>
                            )">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </button>
                        <a href="stagiaires.php?a=<?= urlencode((string)$classe['annee_scolaire']) ?>&f=<?= (int)$classe['id_filiere'] ?>&niv=<?= urlencode((string)$classe['niveau']) ?>&c=<?= (int)$classe['id_classe'] ?>"
                           class="gc-btn-voir">
                            <i class="fa-solid fa-users"></i> Étudiants
                        </a>
                        <?php if ($effectif > 0): ?>
                        <button class="gc-btn-move"
                            onclick="ouvrirModaleDeplacement(
                                <?= (int)$classe['id_classe'] ?>,
                                <?= htmlspecialchars(json_encode($classe['nom_classe']), ENT_QUOTES) ?>,
                                <?= $effectif ?>
                            )">
                            <i class="fa-solid fa-right-left"></i> Déplacer
                        </button>
                        <?php endif; ?>
                        <button class="gc-btn-del"
                            onclick="ouvrirModaleSupprimer(
                                <?= (int)$classe['id_classe'] ?>,
                                <?= htmlspecialchars(json_encode($classe['nom_classe']), ENT_QUOTES) ?>,
                                <?= $effectif ?>
                            )">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>


<!-- ── MODALE : Modification d'une classe ────────────────────────────────────── -->
<div id="gc-edit-modal" class="gc-modal">
    <div class="gc-modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#a855f7;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-pen"></i> Modifier la classe
            </h3>
            <button type="button" onclick="fermerModale('gc-edit-modal')" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-tag" style="color:#a855f7;margin-right:0.3rem;"></i> Nom de la classe</label>
            <input type="text" id="edit-nom" maxlength="128" placeholder="Ex: 1A TSDI">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-users" style="color:#a855f7;margin-right:0.3rem;"></i> Capacité (places)</label>
            <input type="number" id="edit-cap" min="1" max="500">
        </div>
        <div id="gc-edit-err" class="gc-err-box"></div>
        <button type="button" class="gc-submit" onclick="soumettreModification()">
            <i class="fa-solid fa-check" style="margin-right:0.4rem;"></i> Enregistrer les modifications
        </button>
    </div>
</div>


<!-- ── MODALE : Ajout d'une nouvelle classe ──────────────────────────────────── -->
<div id="gc-add-modal" class="gc-modal">
    <div class="gc-modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#10b981;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-plus"></i> Nouvelle classe
            </h3>
            <button type="button" onclick="fermerModale('gc-add-modal')" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-tag" style="color:#10b981;margin-right:0.3rem;"></i> Nom de la classe <span style="color:#ef4444;">*</span></label>
            <input type="text" id="add-nom" maxlength="128" placeholder="Ex: 1A TSDI">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-layer-group" style="color:#10b981;margin-right:0.3rem;"></i> Filière <span style="color:#ef4444;">*</span></label>
            <select id="add-filiere">
                <option value="">— Sélectionner —</option>
                <?php foreach ($toutesLesFilières as $filiere): ?>
                <option value="<?= (int)$filiere['id_filiere'] ?>"><?= h($filiere['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-stairs" style="color:#10b981;margin-right:0.3rem;"></i> Niveau <span style="color:#ef4444;">*</span></label>
            <select id="add-niveau">
                <option value="">— Sélectionner —</option>
                <option value="1ère année">1ère année</option>
                <option value="2ème année">2ème année</option>
            </select>
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-calendar" style="color:#10b981;margin-right:0.3rem;"></i> Année scolaire <span style="color:#ef4444;">*</span></label>
            <select id="add-annee" onchange="document.getElementById('add-annee-custom-wrap').style.display = this.value === '_custom' ? 'block' : 'none'">
                <option value="">— Sélectionner —</option>
                <?php foreach ($anneesDisponibles as $annee): ?>
                <option value="<?= h($annee) ?>"><?= h($annee) ?></option>
                <?php endforeach; ?>
                <option value="_custom">Autre (saisir manuellement)</option>
            </select>
        </div>
        <div class="gc-field" id="add-annee-custom-wrap" style="display:none;">
            <label>Saisir l'année (format AAAA/AAAA)</label>
            <input type="text" id="add-annee-custom" maxlength="9" placeholder="2026/2027">
        </div>
        <div class="gc-field">
            <label><i class="fa-solid fa-users" style="color:#10b981;margin-right:0.3rem;"></i> Capacité</label>
            <input type="number" id="add-cap" min="1" max="500" value="30">
        </div>
        <div id="gc-add-err" class="gc-err-box"></div>
        <button type="button" onclick="soumettreAjout()"
                style="width:100%;padding:0.7rem;border-radius:8px;background:rgba(16,185,129,0.14);border:1px solid rgba(16,185,129,0.4);color:#6ee7b7;font-weight:700;font-size:0.92rem;cursor:pointer;">
            <i class="fa-solid fa-plus" style="margin-right:0.4rem;"></i> Créer la classe
        </button>
    </div>
</div>

<!-- ── MODALE : Confirmation de suppression ───────────────────────────────────── -->
<div id="gc-del-modal" class="gc-modal">
    <div class="gc-modal-box" style="max-width:420px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#ef4444;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Supprimer la classe
            </h3>
            <button type="button" onclick="fermerModale('gc-del-modal')" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <!-- Avertissement affiché si la classe a des stagiaires — propose d'ouvrir la modale de déplacement -->
        <div id="gc-del-warning" style="display:none;background:rgba(251,146,60,0.1);border:1px solid rgba(251,146,60,0.3);border-radius:8px;padding:0.65rem 0.9rem;margin-bottom:1rem;font-size:0.83rem;color:#fb923c;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:0.35rem;"></i>
            <span id="gc-del-warning-text"></span>
            <br>
            <button type="button" onclick="ouvrirDeplacementDepuisSuppression()"
                    style="margin-top:0.5rem;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);color:#fcd34d;border-radius:6px;padding:0.3rem 0.75rem;font-size:0.8rem;font-weight:700;cursor:pointer;">
                <i class="fa-solid fa-right-left" style="margin-right:0.3rem;"></i> Déplacer les étudiants d'abord
            </button>
        </div>
        <p id="gc-del-msg" style="margin:0 0 1.25rem;font-size:0.9rem;color:#a1a1aa;line-height:1.55;"></p>
        <div id="gc-del-err" class="gc-err-box"></div>
        <div style="display:flex;gap:0.65rem;">
            <button type="button" onclick="fermerModale('gc-del-modal')"
                    style="flex:1;padding:0.65rem;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#a1a1aa;font-weight:600;font-size:0.88rem;cursor:pointer;">
                Annuler
            </button>
            <button type="button" id="gc-del-confirm-btn" onclick="soumettreSupprimer()"
                    style="flex:1;padding:0.65rem;border-radius:8px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#fca5a5;font-weight:700;font-size:0.88rem;cursor:pointer;">
                <i class="fa-solid fa-trash" style="margin-right:0.35rem;"></i> Confirmer la suppression
            </button>
        </div>
    </div>
</div>

<!-- ── MODALE : Déplacement des stagiaires vers une autre classe ──────────────── -->
<div id="gc-move-modal" class="gc-modal">
    <div class="gc-modal-box" style="max-width:460px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1rem;color:#f59e0b;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-right-left"></i> Déplacer les étudiants
            </h3>
            <button type="button" onclick="fermerModale('gc-move-modal')" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Résumé de la source -->
        <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:0.75rem 1rem;margin-bottom:1.1rem;font-size:0.85rem;color:#a1a1aa;">
            <i class="fa-solid fa-chalkboard" style="color:#f59e0b;margin-right:0.4rem;"></i>
            Déplacer <strong id="mv-effectif-label" style="color:#fcd34d;"></strong> depuis
            <strong id="mv-source-label" style="color:#fff;"></strong>
        </div>

        <!-- Sélecteur de classe destination -->
        <div class="gc-field">
            <label><i class="fa-solid fa-arrow-right" style="color:#f59e0b;margin-right:0.3rem;"></i> Classe destination <span style="color:#ef4444;">*</span></label>
            <select id="mv-cible" style="width:100%;background:#111;border:1.5px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;padding:0.62rem 0.85rem;font-size:0.88rem;outline:none;box-sizing:border-box;cursor:pointer;">
                <option value="">— Sélectionner une classe —</option>
            </select>
        </div>

        <!-- Info places disponibles dans la classe cible -->
        <div id="mv-cap-info" style="display:none;font-size:0.8rem;margin:-0.5rem 0 0.9rem;padding:0.45rem 0.75rem;border-radius:7px;"></div>

        <div id="gc-move-err" class="gc-err-box"></div>
        <div style="display:flex;gap:0.65rem;">
            <button type="button" onclick="fermerModale('gc-move-modal')"
                    style="flex:1;padding:0.65rem;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#a1a1aa;font-weight:600;font-size:0.88rem;cursor:pointer;">
                Annuler
            </button>
            <button type="button" onclick="soumettreDeplacement()"
                    style="flex:1;padding:0.65rem;border-radius:8px;background:rgba(245,158,11,0.14);border:1px solid rgba(245,158,11,0.4);color:#fcd34d;font-weight:700;font-size:0.88rem;cursor:pointer;">
                <i class="fa-solid fa-right-left" style="margin-right:0.35rem;"></i> Déplacer
            </button>
        </div>
    </div>
</div>

<!-- Notification toast (succès / erreur) -->
<div id="gc-toast" class="gc-toast"></div>

<script>
// ── Variables globales ─────────────────────────────────────────────────────────
var idClasseEnEdition  = 0;
var idClasseASupprimer = 0;
var idClasseSource     = 0;   // pour la modale de déplacement
var nomClasseSource    = '';
var effectifSource     = 0;
var jetonCsrf          = <?= json_encode(csrf_token()) ?>;

// Données de toutes les classes (chargées depuis PHP) pour alimenter le sélecteur de destination
var toutesClassesData  = <?= json_encode(array_values($toutesClassesPourDeplacement)) ?>;

// ── Soumission recherche avec délai (évite un rechargement par frappe) ────────
(function () {
    var champRecherche = document.getElementById('gc-search');
    var formulaireFiltres = document.getElementById('gc-filter-form');
    if (!champRecherche || !formulaireFiltres) return;
    var minuterie = null;
    champRecherche.addEventListener('input', function () {
        clearTimeout(minuterie);
        minuterie = setTimeout(function () { formulaireFiltres.submit(); }, 450);
    });
})();

// ── Toast de notification ──────────────────────────────────────────────────────
/**
 * Affiche un toast en bas à droite pendant 3,5 secondes.
 * @param {string} message - Texte à afficher.
 * @param {boolean} succes - true = icône verte, false = icône rouge.
 */
function afficherToast(message, succes) {
    var toastEl = document.getElementById('gc-toast');
    var icone   = succes ? 'circle-check' : 'triangle-exclamation';
    var couleur = succes ? '#10b981' : '#ef4444';
    toastEl.innerHTML = '<i class="fa-solid fa-' + icone + '" style="color:' + couleur + ';"></i> ' + message;
    toastEl.classList.add('show');
    setTimeout(function () { toastEl.classList.remove('show'); }, 3500);
}

// ── Gestion des modales ────────────────────────────────────────────────────────
/**
 * Ferme la modale dont l'identifiant est passé en paramètre.
 * Utilisé par les boutons ✕ et le clic sur le fond.
 * @param {string} idModale - Identifiant HTML de la modale.
 */
function fermerModale(idModale) {
    document.getElementById(idModale).style.display = 'none';
}

// Fermeture par clic sur le fond de la modale ou par touche Échap
['gc-edit-modal', 'gc-add-modal', 'gc-del-modal', 'gc-move-modal'].forEach(function (idModale) {
    document.getElementById(idModale).addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fermerModale('gc-edit-modal');
        fermerModale('gc-add-modal');
        fermerModale('gc-del-modal');
        fermerModale('gc-move-modal');
    }
});


// ============================================================
//  Modale : Modification d'une classe
// ============================================================

/**
 * Ouvre la modale de modification et pré-remplit les champs.
 * @param {number} idClasse  - Identifiant de la classe à modifier.
 * @param {string} nomActuel - Nom actuel de la classe.
 * @param {number} capActuel - Capacité actuelle.
 */
function ouvrirModaleModif(idClasse, nomActuel, capActuel) {
    idClasseEnEdition = idClasse;
    document.getElementById('edit-nom').value = nomActuel;
    document.getElementById('edit-cap').value = capActuel;
    document.getElementById('gc-edit-err').style.display = 'none';
    document.getElementById('gc-edit-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('edit-nom').focus(); }, 80);
}

/**
 * Soumet la modification d'une classe en AJAX et met à jour la ligne
 * du tableau directement, sans rechargement de page.
 */
function soumettreModification() {
    var nomClasse   = document.getElementById('edit-nom').value.trim();
    var capacite    = parseInt(document.getElementById('edit-cap').value, 10);
    var boiteErreur = document.getElementById('gc-edit-err');

    if (!nomClasse || isNaN(capacite) || capacite < 1) {
        boiteErreur.textContent = 'Nom et capacité requis (capacité ≥ 1).';
        boiteErreur.style.display = 'block';
        return;
    }
    boiteErreur.style.display = 'none';

    var donneesFormulaire = new FormData();
    donneesFormulaire.append('edit_classe', '1');
    donneesFormulaire.append('csrf_token',  jetonCsrf);
    donneesFormulaire.append('id_classe',   idClasseEnEdition);
    donneesFormulaire.append('nom_classe',  nomClasse);
    donneesFormulaire.append('capacite',    capacite);

    fetch('gestion_classes.php', { method: 'POST', body: donneesFormulaire })
        .then(function (reponse) { return reponse.json(); })
        .then(function (resultat) {
            if (resultat.success) {
                fermerModale('gc-edit-modal');
                afficherToast(resultat.msg, true);
                // Mise à jour DOM : retrouver la ligne par data-id et modifier les cellules
                var rangee = document.querySelector('.gc-row[data-id="' + idClasseEnEdition + '"]');
                if (rangee) {
                    rangee.querySelector('.gc-nom-classe').textContent = resultat.nom_classe;
                    // Mettre à jour la capacité affichée
                    var cellCapacite = rangee.querySelector('.gc-cap-val');
                    if (cellCapacite) cellCapacite.textContent = resultat.capacite;
                    // Recalculer le pourcentage d'occupation avec la nouvelle capacité
                    var effectifEl = rangee.querySelector('.gc-eff-val');
                    var effVal = effectifEl ? parseInt(effectifEl.textContent, 10) : 0;
                    var pct    = resultat.capacite > 0 ? Math.min(100, Math.round(effVal / resultat.capacite * 100)) : 0;
                    var couleur = pct >= 100 ? '#ef4444' : (pct >= 80 ? '#fb923c' : '#10b981');
                    if (effectifEl) effectifEl.style.color = couleur;
                    var barreFill = rangee.querySelector('.gc-cap-fill');
                    if (barreFill) { barreFill.style.width = pct + '%'; barreFill.style.background = couleur; }
                    var pctEl = rangee.querySelector('.gc-pct-val');
                    if (pctEl) pctEl.textContent = pct + '%';
                    // Mettre à jour les places libres
                    var libres = Math.max(0, resultat.capacite - effVal);
                    var cellLibres = rangee.querySelector('.gc-libres-cell');
                    if (cellLibres) {
                        if (libres === 0) {
                            cellLibres.innerHTML = '<span style="font-weight:700;color:#ef4444;font-size:0.82rem;"><i class="fa-solid fa-ban"></i> Pleine</span>';
                        } else if (libres <= 5) {
                            cellLibres.innerHTML = '<span style="font-weight:700;color:#fb923c;">' + libres + ' place(s)</span>';
                        } else {
                            cellLibres.innerHTML = '<span style="font-weight:700;color:#10b981;">' + libres + ' place(s)</span>';
                        }
                    }
                    // Mettre à jour l'argument onclick du bouton Modifier pour refléter les nouvelles valeurs
                    var btnModif = rangee.querySelector('.gc-btn-edit');
                    if (btnModif) {
                        btnModif.setAttribute('onclick',
                            'ouvrirModaleModif(' + idClasseEnEdition + ',' +
                            JSON.stringify(resultat.nom_classe) + ',' + resultat.capacite + ')'
                        );
                    }
                }
            } else {
                boiteErreur.textContent = resultat.error || 'Erreur inconnue.';
                boiteErreur.style.display = 'block';
            }
        })
        .catch(function () {
            boiteErreur.textContent = 'Erreur réseau.';
            boiteErreur.style.display = 'block';
        });
}


// ============================================================
//  Modale : Ajout d'une nouvelle classe
// ============================================================

/** Ouvre la modale d'ajout et réinitialise tous les champs. */
function ouvrirModaleAjout() {
    document.getElementById('add-nom').value    = '';
    document.getElementById('add-filiere').value = '';
    document.getElementById('add-niveau').value  = '';
    document.getElementById('add-annee').value   = '';
    document.getElementById('add-cap').value     = 30;
    document.getElementById('add-annee-custom-wrap').style.display = 'none';
    document.getElementById('gc-add-err').style.display = 'none';
    document.getElementById('gc-add-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('add-nom').focus(); }, 80);
}

/**
 * Soumet la création d'une classe en AJAX et insère la nouvelle ligne
 * en tête du tableau sans rechargement de page.
 */
function soumettreAjout() {
    var nomClasse   = document.getElementById('add-nom').value.trim();
    var idFiliere   = document.getElementById('add-filiere').value;
    var niveau      = document.getElementById('add-niveau').value;
    var valeurAnnee = document.getElementById('add-annee').value;
    var anneeScolaire = valeurAnnee === '_custom'
        ? document.getElementById('add-annee-custom').value.trim()
        : valeurAnnee;
    var capacite    = parseInt(document.getElementById('add-cap').value, 10);
    var boiteErreur = document.getElementById('gc-add-err');

    // Validation côté client
    if (!nomClasse || !idFiliere || !niveau || !anneeScolaire || isNaN(capacite) || capacite < 1) {
        boiteErreur.textContent = 'Tous les champs sont requis (capacité ≥ 1).';
        boiteErreur.style.display = 'block';
        return;
    }
    if (valeurAnnee === '_custom' && !/^\d{4}\/\d{4}$/.test(anneeScolaire)) {
        boiteErreur.textContent = 'Format année invalide — utilisez AAAA/AAAA (ex: 2026/2027).';
        boiteErreur.style.display = 'block';
        return;
    }
    boiteErreur.style.display = 'none';

    var donneesFormulaire = new FormData();
    donneesFormulaire.append('add_classe',     '1');
    donneesFormulaire.append('csrf_token',     jetonCsrf);
    donneesFormulaire.append('nom_classe',     nomClasse);
    donneesFormulaire.append('id_filiere',     idFiliere);
    donneesFormulaire.append('niveau',         niveau);
    donneesFormulaire.append('annee_scolaire', anneeScolaire);
    donneesFormulaire.append('capacite',       capacite);

    fetch('gestion_classes.php', { method: 'POST', body: donneesFormulaire })
        .then(function (reponse) { return reponse.json(); })
        .then(function (resultat) {
            if (resultat.success) {
                fermerModale('gc-add-modal');
                afficherToast(resultat.msg, true);
                // Mise à jour DOM : insérer la nouvelle ligne en tête du tbody
                var corps = document.getElementById('gc-tbody');
                if (corps) {
                    var lienEtudiants = 'stagiaires.php'
                        + '?a=' + encodeURIComponent(resultat.annee_scolaire)
                        + '&f=' + resultat.id_filiere
                        + '&niv=' + encodeURIComponent(resultat.niveau)
                        + '&c=' + resultat.id_classe;
                    var nouvelleRangee = document.createElement('tr');
                    nouvelleRangee.className = 'gc-row';
                    nouvelleRangee.setAttribute('data-id', resultat.id_classe);
                    nouvelleRangee.innerHTML =
                        '<td><span class="gc-nom-classe" style="font-weight:700;color:#fff;">'
                            + escHtml(resultat.nom_classe) + '</span></td>'
                        + '<td><span class="gc-badge gc-badge-filiere">' + escHtml(resultat.nom_filiere) + '</span></td>'
                        + '<td><span class="gc-badge gc-badge-niveau">' + escHtml(resultat.niveau) + '</span></td>'
                        + '<td style="color:#a1a1aa;font-size:0.85rem;">' + escHtml(resultat.annee_scolaire) + '</td>'
                        + '<td style="font-weight:600;color:#d8b4fe;" class="gc-cap-val">' + resultat.capacite + '</td>'
                        + '<td>'
                            + '<div class="gc-cap-bar">'
                            + '<span class="gc-eff-val" style="font-weight:600;color:#10b981;min-width:24px;">0</span>'
                            + '<div class="gc-cap-track"><div class="gc-cap-fill" style="width:0%;background:#10b981;"></div></div>'
                            + '<span class="gc-pct-val" style="font-size:0.7rem;color:#71717a;">0%</span>'
                            + '</div></td>'
                        + '<td class="gc-libres-cell">'
                            + '<span style="font-weight:700;color:#10b981;">' + resultat.capacite + ' place(s)</span>'
                        + '</td>'
                        + '<td style="text-align:center;">'
                            + '<div style="display:flex;gap:0.4rem;justify-content:center;flex-wrap:wrap;">'
                            + '<button class="gc-btn-edit" onclick="ouvrirModaleModif('
                                + resultat.id_classe + ','
                                + JSON.stringify(resultat.nom_classe) + ','
                                + resultat.capacite + ')">'
                                + '<i class="fa-solid fa-pen"></i> Modifier</button>'
                            + '<a href="' + lienEtudiants + '" class="gc-btn-voir">'
                                + '<i class="fa-solid fa-users"></i> Étudiants</a>'
                            + '</div></td>';
                    corps.insertBefore(nouvelleRangee, corps.firstChild);

                    // Mettre à jour le compteur affiché dans la barre de filtres
                    var compteurEl = document.getElementById('gc-count-visible');
                    if (compteurEl) compteurEl.textContent = parseInt(compteurEl.textContent, 10) + 1;
                }
            } else {
                boiteErreur.textContent = resultat.error || 'Erreur inconnue.';
                boiteErreur.style.display = 'block';
            }
        })
        .catch(function () {
            boiteErreur.textContent = 'Erreur réseau.';
            boiteErreur.style.display = 'block';
        });
}

// ============================================================
//  Modale : Déplacement des stagiaires vers une autre classe
// ============================================================

/**
 * Remplit le sélecteur de destination et affiche la modale de déplacement.
 * @param {number} idClasse  - ID de la classe source.
 * @param {string} nomClasse - Nom de la classe source.
 * @param {number} effectif  - Nombre de stagiaires à déplacer.
 */
function ouvrirModaleDeplacement(idClasse, nomClasse, effectif) {
    idClasseSource  = idClasse;
    nomClasseSource = nomClasse;
    effectifSource  = effectif;

    document.getElementById('mv-source-label').textContent  = nomClasse;
    document.getElementById('mv-effectif-label').textContent =
        effectif + ' stagiaire(s)';
    document.getElementById('gc-move-err').style.display    = 'none';
    document.getElementById('mv-cap-info').style.display    = 'none';

    // Construire dynamiquement les options du sélecteur de destination
    var sel = document.getElementById('mv-cible');
    sel.innerHTML = '<option value="">— Sélectionner une classe —</option>';
    toutesClassesData.forEach(function (cls) {
        if (parseInt(cls.id_classe, 10) === idClasse) return; // exclure la source
        var libres = parseInt(cls.places_libres, 10);
        var label  = cls.nom_classe + ' — ' + cls.nom_filiere + ' (' + cls.annee_scolaire + ')'
                   + ' · ' + libres + ' place(s) libre(s)';
        var opt = document.createElement('option');
        opt.value       = cls.id_classe;
        opt.textContent = label;
        opt.disabled    = libres < effectif; // grisé si capacité insuffisante
        if (opt.disabled) opt.textContent += ' ⚠ capacité insuffisante';
        sel.appendChild(opt);
    });

    document.getElementById('gc-move-modal').style.display = 'flex';
    setTimeout(function () { sel.focus(); }, 80);
}

/**
 * Raccourci : ferme la modale de suppression et ouvre directement
 * la modale de déplacement pour la même classe.
 * Utilisé par le bouton "Déplacer les étudiants d'abord" dans la modale de suppression.
 */
function ouvrirDeplacementDepuisSuppression() {
    fermerModale('gc-del-modal');
    // Récupérer les infos depuis les variables déjà remplies par ouvrirModaleSupprimer
    ouvrirModaleDeplacement(idClasseASupprimer,
        document.querySelector('.gc-row[data-id="' + idClasseASupprimer + '"] .gc-nom-classe')
            ? document.querySelector('.gc-row[data-id="' + idClasseASupprimer + '"] .gc-nom-classe').textContent
            : 'cette classe',
        effectifSource
    );
}

/**
 * Soumet le déplacement en AJAX.
 * En cas de succès :
 *   - Remet l'effectif de la ligne source à 0 (barre, %, places libres, boutons)
 *   - Met à jour la ligne de destination si elle est visible dans le tableau
 *   - Retire le bouton "Déplacer" de la ligne source
 */
function soumettreDeplacement() {
    var idCible     = document.getElementById('mv-cible').value;
    var boiteErreur = document.getElementById('gc-move-err');
    boiteErreur.style.display = 'none';

    if (!idCible) {
        boiteErreur.textContent = 'Veuillez sélectionner une classe destination.';
        boiteErreur.style.display = 'block';
        return;
    }

    var donneesFormulaire = new FormData();
    donneesFormulaire.append('move_stagiaires',  '1');
    donneesFormulaire.append('csrf_token',       jetonCsrf);
    donneesFormulaire.append('id_classe_source', idClasseSource);
    donneesFormulaire.append('id_classe_cible',  idCible);

    fetch('gestion_classes.php', { method: 'POST', body: donneesFormulaire })
        .then(function (reponse) { return reponse.json(); })
        .then(function (resultat) {
            if (resultat.success) {
                fermerModale('gc-move-modal');
                afficherToast(resultat.msg, true);

                // ── Mettre à jour la ligne SOURCE : effectif → 0 ──────────────
                var rangeeSource = document.querySelector('.gc-row[data-id="' + idClasseSource + '"]');
                if (rangeeSource) {
                    var capSrc = parseInt(rangeeSource.querySelector('.gc-cap-val').textContent, 10) || 0;
                    // Effectif
                    var effEl = rangeeSource.querySelector('.gc-eff-val');
                    if (effEl) { effEl.textContent = '0'; effEl.style.color = '#10b981'; }
                    // Barre
                    var fillEl = rangeeSource.querySelector('.gc-cap-fill');
                    if (fillEl) { fillEl.style.width = '0%'; fillEl.style.background = '#10b981'; }
                    // Pourcentage
                    var pctEl = rangeeSource.querySelector('.gc-pct-val');
                    if (pctEl) pctEl.textContent = '0%';
                    // Places libres
                    var libresEl = rangeeSource.querySelector('.gc-libres-cell');
                    if (libresEl) libresEl.innerHTML =
                        '<span style="font-weight:700;color:#10b981;">' + capSrc + ' place(s)</span>';
                    // Retirer le bouton Déplacer (plus d'effectif)
                    var btnMove = rangeeSource.querySelector('.gc-btn-move');
                    if (btnMove) btnMove.remove();
                    // Retirer l'avertissement dans la modale de suppression si réouverture
                    document.getElementById('gc-del-warning').style.display = 'none';
                }

                // ── Mettre à jour la ligne DESTINATION si visible ─────────────
                var rangeeCible = document.querySelector('.gc-row[data-id="' + idCible + '"]');
                if (rangeeCible) {
                    var capDst  = parseInt(rangeeCible.querySelector('.gc-cap-val').textContent, 10) || 0;
                    var effDst  = parseInt(rangeeCible.querySelector('.gc-eff-val').textContent, 10) || 0;
                    var nouvelEff = effDst + resultat.nb_deplaces;
                    var pct    = capDst > 0 ? Math.min(100, Math.round(nouvelEff / capDst * 100)) : 0;
                    var couleur = pct >= 100 ? '#ef4444' : (pct >= 80 ? '#fb923c' : '#10b981');
                    var libres  = Math.max(0, capDst - nouvelEff);

                    var effElD = rangeeCible.querySelector('.gc-eff-val');
                    if (effElD) { effElD.textContent = nouvelEff; effElD.style.color = couleur; }
                    var fillElD = rangeeCible.querySelector('.gc-cap-fill');
                    if (fillElD) { fillElD.style.width = pct + '%'; fillElD.style.background = couleur; }
                    var pctElD = rangeeCible.querySelector('.gc-pct-val');
                    if (pctElD) pctElD.textContent = pct + '%';
                    var libresElD = rangeeCible.querySelector('.gc-libres-cell');
                    if (libresElD) {
                        if (libres === 0) {
                            libresElD.innerHTML = '<span style="font-weight:700;color:#ef4444;font-size:0.82rem;"><i class="fa-solid fa-ban"></i> Pleine</span>';
                        } else if (libres <= 5) {
                            libresElD.innerHTML = '<span style="font-weight:700;color:#fb923c;">' + libres + ' place(s)</span>';
                        } else {
                            libresElD.innerHTML = '<span style="font-weight:700;color:#10b981;">' + libres + ' place(s)</span>';
                        }
                    }
                    // Ajouter le bouton Déplacer sur la destination si non présent
                    if (!rangeeCible.querySelector('.gc-btn-move')) {
                        var btnDel = rangeeCible.querySelector('.gc-btn-del');
                        if (btnDel) {
                            var newBtn = document.createElement('button');
                            newBtn.className = 'gc-btn-move';
                            var nomDst = rangeeCible.querySelector('.gc-nom-classe')
                                ? rangeeCible.querySelector('.gc-nom-classe').textContent : '';
                            newBtn.setAttribute('onclick',
                                'ouvrirModaleDeplacement(' + idCible + ',' +
                                JSON.stringify(nomDst) + ',' + nouvelEff + ')');
                            newBtn.innerHTML = '<i class="fa-solid fa-right-left"></i> Déplacer';
                            btnDel.parentNode.insertBefore(newBtn, btnDel);
                        }
                    }
                }

                // Mettre à jour toutesClassesData pour les ouvertures futures de la modale
                toutesClassesData.forEach(function (cls) {
                    var cid = parseInt(cls.id_classe, 10);
                    if (cid === idClasseSource) {
                        cls.effectif     = 0;
                        cls.places_libres = parseInt(cls.capacite, 10);
                    }
                    if (cid === parseInt(idCible, 10)) {
                        cls.effectif      = (parseInt(cls.effectif, 10) || 0) + resultat.nb_deplaces;
                        cls.places_libres = Math.max(0, parseInt(cls.capacite, 10) - cls.effectif);
                    }
                });
            } else {
                boiteErreur.textContent = resultat.error || 'Erreur inconnue.';
                boiteErreur.style.display = 'block';
            }
        })
        .catch(function () {
            boiteErreur.textContent = 'Erreur réseau.';
            boiteErreur.style.display = 'block';
        });
}

// ============================================================
//  Modale : Suppression d'une classe
// ============================================================

/**
 * Ouvre la modale de confirmation de suppression.
 * Affiche un avertissement orange si la classe a des stagiaires inscrits
 * (dans ce cas le bouton de confirmation reste désactivé côté serveur).
 * @param {number} idClasse  - Identifiant de la classe à supprimer.
 * @param {string} nomClasse - Nom affiché dans le message de confirmation.
 * @param {number} effectif  - Nombre de stagiaires actuellement inscrits.
 */
function ouvrirModaleSupprimer(idClasse, nomClasse, effectif) {
    idClasseASupprimer = idClasse;
    document.getElementById('gc-del-err').style.display = 'none';
    document.getElementById('gc-del-msg').innerHTML =
        'Vous êtes sur le point de supprimer définitivement la classe <strong style="color:#fff;">'
        + escHtml(nomClasse) + '</strong>. Cette action est irréversible.';

    // Afficher l'avertissement si des stagiaires sont présents
    var zoneAvertissement = document.getElementById('gc-del-warning');
    var texteAvertissement = document.getElementById('gc-del-warning-text');
    if (effectif > 0) {
        texteAvertissement.textContent =
            effectif + ' stagiaire(s) sont inscrits dans cette classe. '
            + 'Désaffectez-les d\'abord avant de pouvoir supprimer.';
        zoneAvertissement.style.display = 'block';
    } else {
        zoneAvertissement.style.display = 'none';
    }

    document.getElementById('gc-del-modal').style.display = 'flex';
}

/**
 * Soumet la suppression en AJAX et retire la ligne du tableau si réussi.
 * Le serveur bloque la suppression si des stagiaires sont encore dans la classe.
 */
function soumettreSupprimer() {
    var boiteErreur = document.getElementById('gc-del-err');
    boiteErreur.style.display = 'none';

    var donneesFormulaire = new FormData();
    donneesFormulaire.append('delete_classe', '1');
    donneesFormulaire.append('csrf_token',    jetonCsrf);
    donneesFormulaire.append('id_classe',     idClasseASupprimer);

    fetch('gestion_classes.php', { method: 'POST', body: donneesFormulaire })
        .then(function (reponse) { return reponse.json(); })
        .then(function (resultat) {
            if (resultat.success) {
                fermerModale('gc-del-modal');
                afficherToast(resultat.msg, true);
                // Retrait de la ligne du tableau sans rechargement
                var rangee = document.querySelector('.gc-row[data-id="' + idClasseASupprimer + '"]');
                if (rangee) {
                    rangee.style.transition = 'opacity .3s, transform .3s';
                    rangee.style.opacity    = '0';
                    rangee.style.transform  = 'translateX(20px)';
                    setTimeout(function () {
                        rangee.remove();
                        // Décrémenter le compteur visible
                        var compteurEl = document.getElementById('gc-count-visible');
                        if (compteurEl) {
                            var ancien = parseInt(compteurEl.textContent, 10);
                            if (!isNaN(ancien) && ancien > 0) compteurEl.textContent = ancien - 1;
                        }
                    }, 300);
                }
            } else {
                boiteErreur.textContent = resultat.error || 'Erreur inconnue.';
                boiteErreur.style.display = 'block';
            }
        })
        .catch(function () {
            boiteErreur.textContent = 'Erreur réseau.';
            boiteErreur.style.display = 'block';
        });
}

/**
 * Échappe les caractères HTML spéciaux pour une insertion DOM sécurisée.
 * @param {string} texte - Valeur brute à échapper.
 * @returns {string} Valeur sûre pour innerHTML.
 */
function escHtml(texte) {
    return String(texte)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
