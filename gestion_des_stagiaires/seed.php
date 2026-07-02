<?php
/**
 * IPIRNET — Script de peuplement de la base de données
 * ======================================================
 * Lance depuis le terminal XAMPP :
 *   php seed.php
 * Ou dépose ce fichier dans le dossier du projet et ouvre :
 *   http://localhost/gestion_des_stagiaires/seed.php
 * (SUPPRIME et RECRÉE toutes les données — ne pas utiliser en production)
 */

declare(strict_types=1);

// ── Connexion ──────────────────────────────────────────────────────────────
$host = getenv('GDS_DB_HOST') ?: '127.0.0.1';
$db   = getenv('GDS_DB_NAME') ?: 'gestion_des_stagiaires';
$user = getenv('GDS_DB_USER') ?: 'root';
$pass = getenv('GDS_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Connexion impossible : " . $e->getMessage() . "\n");
}

$cli = (php_sapi_name() === 'cli');
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function log_msg(string $msg): void {
    echo $msg . "\n";
    if (php_sapi_name() !== 'cli') flush();
}

// ── Données ────────────────────────────────────────────────────────────────
$PASS_HASH = password_hash('changeme', PASSWORD_DEFAULT);

$prenoms_m = ['Yassine','Mehdi','Anas','Hamza','Soufiane','Ayoub','Karim','Mohamed','Rachid','Omar',
              'Zakaria','Bilal','Ilias','Nabil','Reda','Adil','Hicham','Saad','Walid','Tarik',
              'Abdelhak','Fouad','Khalid','Mouad','Imrane','Oussama','Hatim','Brahim','Faissal','Achraf'];
$prenoms_f = ['Salma','Nadia','Sara','Fatima','Kenza','Laila','Zineb','Hajar','Amina','Meryem',
              'Dounia','Ghita','Loubna','Rania','Yasmine','Houda','Sanaa','Soukaina','Widad','Rim',
              'Hanane','Chaimae','Boutaina','Malak','Ikram','Oumaima','Nour','Ilham','Samira','Aya'];
$noms = ['El Amrani','Benali','Ouali','Tazi','Benhaddou','Cherkaoui','Alami','Benomar','El Fassi',
         'Hajji','Belkadi','Ziani','Lahlou','Berrada','El Idrissi','Moussaoui','Senhaji','Kadiri',
         'Boudali','Naciri','Rahmani','Fennich','El Ouazzani','Tahiri','Filali','Guessous','Regragui',
         'El Mansouri','Boukili','Akhdar','Drissi','Abjij','Mahfoud','El Khayat','Ouazzani','Benkirane'];
$cin_pre = ['WA','BH','BK','EE','JB','CN','GN','UD','SH','TK','FJ','AA'];
$villes = ['Rabat','Casablanca','Fès','Salé','Marrakech','Agadir','Meknès','Oujda','Kénitra','Tétouan',
           'Témara','Nador','El Jadida','Mohammedia','Béni Mellal','Khouribga','Safi','Settat'];
$tuteurs = ['Hamid El Amrani','Rachida Belhaj','Mohamed Ouali','Fatiha Tazi','Said Benhaddou',
            'Naima Cherkaoui','Khalid Alami','Zohra Benomar','Abderrahman El Fassi','Loubna Hajji',
            'Aziz Belkadi','Nour Ziani','Hassan Lahlou','Nadia Berrada','Abdellatif El Idrissi'];

// ── 1. FILIERES ────────────────────────────────────────────────────────────
$filieres = [
    [1, 'Développement Digital',            'Technicien Spécialisé', 30],
    [2, 'Infrastructure Digitale',           'Technicien Spécialisé', 30],
    [3, 'Administration Systèmes & Réseaux', 'Technicien Spécialisé', 30],
    [4, 'Technicien en Informatique',        'Technicien',            30],
];

// ── 2. CLASSES ─────────────────────────────────────────────────────────────
// [id, id_filiere, nom_classe, annee_scolaire, niveau, capacite]
$classes = [
    [1, 1, 'DD-1 (2024/2025)', '2024/2025', '1ère Année', 30],
    [2, 1, 'DD-2 (2024/2025)', '2024/2025', '2ème Année', 30],
    [3, 1, 'DD-1 (2025/2026)', '2025/2026', '1ère Année', 30],
    [4, 2, 'ID-1 (2024/2025)', '2024/2025', '1ère Année', 30],
    [5, 2, 'ID-1 (2025/2026)', '2025/2026', '1ère Année', 30],
    [6, 3, 'ASR-1 (2025/2026)','2025/2026', '1ère Année', 30],
    [7, 4, 'TI-1 (2024/2025)', '2024/2025', '1ère Année', 30],
    [8, 4, 'TI-1 (2025/2026)', '2025/2026', '1ère Année', 30],
];

// ── 3. MODULES ─────────────────────────────────────────────────────────────
// [id, id_filiere, nom, semestre, masse_horaire, coefficient, nb_controles]
$modules = [
    // Développement Digital (filiere 1)
    [ 1, 1, 'Développement Web — PHP & MySQL',       1, 120, 3, 2],
    [ 2, 1, 'JavaScript & Frameworks Front-End',     1,  90, 2, 2],
    [ 3, 1, 'Bases de Données Relationnelles',       2,  80, 2, 2],
    [ 4, 1, 'Python & Automatisation',               2,  60, 2, 1],
    [ 5, 1, 'UML & Conception Logicielle',           1,  50, 1, 1],
    // Infrastructure Digitale (filiere 2)
    [ 6, 2, 'Réseaux TCP/IP',                        1, 100, 3, 2],
    [ 7, 2, 'Administration Linux',                  1,  90, 2, 2],
    [ 8, 2, 'Windows Server & Active Directory',     2,  80, 2, 2],
    [ 9, 2, 'Sécurité des Réseaux',                  2,  70, 2, 1],
    [10, 2, 'Virtualisation (VMware / Hyper-V)',      2,  60, 2, 1],
    // Administration Systèmes (filiere 3)
    [11, 3, 'Administration Réseaux',                1, 100, 3, 2],
    [12, 3, 'Active Directory & GPO',                1,  80, 2, 2],
    [13, 3, 'Cisco IOS & Routage',                   2,  90, 3, 2],
    [14, 3, 'Support & Maintenance N2',              2,  60, 1, 1],
    // Technicien Informatique (filiere 4)
    [15, 4, 'Maintenance Matérielle',                1, 100, 3, 2],
    [16, 4, 'Systèmes d\'Exploitation',              1,  80, 2, 2],
    [17, 4, 'Réseaux Locaux',                        2,  70, 2, 2],
    [18, 4, 'Bureautique & Assistance Utilisateurs', 2,  50, 1, 1],
];

// filiere -> modules (id_module => nb_controles)
$filiere_mods = []; // [filiere_id => [[id_module, nb_controles], ...]]
foreach ($modules as [$mid, $fid, , , , , $nb]) {
    $filiere_mods[$fid][] = [$mid, $nb];
}

// ── Truncate ───────────────────────────────────────────────────────────────
log_msg("🗑  Suppression des données existantes...");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['documents_generes','stagiaire_historique','module_notes','absences',
          'mensualites','stages','stagiaires','pre_inscription',
          'modules','classes','filieres','users','seq_inscription'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
log_msg("   ✓ Tables vidées\n");

// ── Insert filieres ────────────────────────────────────────────────────────
log_msg("📚 Insertion des filières...");
$stmtF = $pdo->prepare('INSERT INTO filieres (id_filiere,nom_filiere,niveau,capacite) VALUES (?,?,?,?)');
foreach ($filieres as $f) { $stmtF->execute($f); }
log_msg("   ✓ " . count($filieres) . " filières\n");

// ── Insert classes ─────────────────────────────────────────────────────────
log_msg("🏫 Insertion des classes...");
$stmtC = $pdo->prepare('INSERT INTO classes (id_classe,id_filiere,nom_classe,annee_scolaire,niveau,capacite) VALUES (?,?,?,?,?,?)');
foreach ($classes as $c) { $stmtC->execute($c); }
log_msg("   ✓ " . count($classes) . " classes\n");

// ── Insert modules ─────────────────────────────────────────────────────────
log_msg("📖 Insertion des modules...");
$stmtM = $pdo->prepare('INSERT INTO modules (id_module,id_filiere,nom_module,semestre,masse_horaire,coefficient,nb_controles) VALUES (?,?,?,?,?,?,?)');
foreach ($modules as $m) { $stmtM->execute($m); }
log_msg("   ✓ " . count($modules) . " modules\n");

// ── Insert users (secrétaires) ─────────────────────────────────────────────
log_msg("👤 Insertion des utilisateurs...");
$pdo->prepare('INSERT INTO users (id_user,username,password_hash,role) VALUES (?,?,?,?)')
    ->execute([1, 'secretaire1', $PASS_HASH, 'secretaire']);
$pdo->prepare('INSERT INTO users (id_user,username,password_hash,role) VALUES (?,?,?,?)')
    ->execute([2, 'secretaire2', $PASS_HASH, 'secretaire']);
log_msg("   ✓ 2 secrétaires (mot de passe: changeme)\n");

// ── Insert stagiaires + mensualites + absences + notes ────────────────────
log_msg("🎓 Insertion des stagiaires, mensualités, absences, notes...");
$stmtStag = $pdo->prepare(
    'INSERT INTO stagiaires (num_inscri,cin,nom,prenom,date_naissance,adresse,email,
     telephone,telephone_parent,nom_tuteur,mot_de_passe,photo,date_inscription,id_classe,remise_mensuelle)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,?,?,?)'
);
$stmtMens = $pdo->prepare(
    'INSERT INTO mensualites (id_stagiaire,mois_ref,est_paye,montant_total,remise,
     montant_paye,montant_restant,cumul_restant,statut_paiement,date_paiement,marque_le)
     VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
);
$stmtAbs = $pdo->prepare(
    'INSERT INTO absences (id_stagiaire,date_absence,heure_debut,heure_fin,
     est_justifiee,justificatif,id_module) VALUES (?,?,?,?,?,?,?)'
);
$stmtNote = $pdo->prepare(
    'INSERT INTO module_notes (id_stagiaire,id_module,note,type) VALUES (?,?,?,?)'
);

$tarif = 850.00;
$today = new DateTime('2026-07-02');
$id_stag = 1;
$total_stag = 0;
$total_mens = 0;
$total_abs  = 0;
$total_notes= 0;

// Justificatifs possibles
$justifs = ['Certificat médical','Convocation administrative','Raison familiale urgente',
            'Convocation officielle','Maladie confirmée','Accident de trajet'];

// Stages data
$entreprises = ['IPIRNET SARL','Maroc Telecom','OCP Group','Attijariwafa Bank','CIH Bank',
                'BMCE Bank','Lafarge Maroc','Lydec','RAM IT','INWI Technologies',
                'Soprogest','Data4u','Orange Maroc','Wafa Assurance','Caisse Dépôt et Gestion',
                'Al Mada','OceanSoft','IT2S Group','BMCI','Banque Populaire'];
$sujets_pfe = [
    'Développement d\'une application de gestion des ressources humaines',
    'Mise en place d\'une solution ERP open-source',
    'Sécurisation d\'une infrastructure réseau d\'entreprise',
    'Implémentation d\'un système SIEM pour la supervision sécurité',
    'Développement d\'une plateforme e-learning interactive',
    'Migration vers une architecture cloud hybride (Azure/AWS)',
    'Automatisation des déploiements avec CI/CD (Jenkins/GitLab)',
    'Conception et développement d\'une API REST microservices',
    'Mise en place d\'une DMZ et filtrage applicatif',
    'Développement d\'un système de ticketing helpdesk',
];
$sujets_se = [
    'Assistance technique et support utilisateurs N1/N2',
    'Administration du réseau local de l\'entreprise',
    'Maintenance préventive et curative des postes de travail',
    'Gestion et inventaire du parc informatique',
    'Support helpdesk et résolution d\'incidents',
    'Configuration et supervision des équipements réseau',
];
$jury_options = [
    'M. Benhaddou, Mme. Alami','M. El Fassi, M. Cherkaoui',
    'Mme. Berrada, M. Tazi','M. Kadiri, Mme. Lahlou',
    'M. Ziani, M. Alami','Mme. Senhaji, M. Naciri',
];
$evals = ['Très Bien','Bien','Assez Bien','Satisfaisant'];

$stmtStage = $pdo->prepare(
    'INSERT INTO stages (id_stagiaire,type_stage,entreprise,sujet,annee_scolaire,
     date_debut,date_fin,date_soutenance,jury,note_stage,evaluation_entreprise,
     convention_url,rapport_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,NULL)'
);

mt_srand(42); // reproductible

foreach ($classes as [$cid, $fid, $nom_classe, $annee, $niveau_cl, $cap]) {
    $year_start = (int)substr($annee, 0, 4);
    $d_insc = "$year_start-09-01";

    // determine school months for this annee
    $school_months = [];
    for ($m = 9; $m <= 12; $m++) { $school_months[] = [$year_start, $m]; }
    for ($m = 1; $m <= 6; $m++)  { $school_months[] = [$year_start + 1, $m]; }

    // school days (Mon-Fri, not in future)
    $school_days = [];
    foreach ($school_months as [$sy, $sm]) {
        $last = (int)(new DateTime("$sy-$sm-01"))->format('t');
        for ($d = 1; $d <= $last; $d++) {
            $dt = new DateTime("$sy-$sm-" . str_pad((string)$d, 2, '0', STR_PAD_LEFT));
            if ($dt->format('N') < 6 && $dt <= $today) {
                $school_days[] = $dt->format('Y-m-d');
            }
        }
    }

    $heure_slots = [['08:30','10:30'],['10:30','12:30'],['14:00','16:00'],['16:00','18:00']];
    $fil_mods = $filiere_mods[$fid] ?? [];

    for ($i = 0; $i < 30; $i++) {
        $is_female = ($i % 3 === 0);
        $prenom = ($is_female ? $prenoms_f : $prenoms_m)[$i % 30];
        $nom    = $noms[($id_stag + 7) % count($noms)];
        $age    = mt_rand(18, 26);
        $dob_y  = $year_start - $age;
        $dob    = sprintf('%04d-%02d-%02d', $dob_y, mt_rand(1, 12), mt_rand(1, 28));
        $ville  = $villes[($id_stag + 3) % count($villes)];
        $adresse = mt_rand(1, 200) . ' Rue ' . explode(' ', $nom)[count(explode(' ', $nom)) - 1] . ', ' . $ville;
        $email  = strtolower(str_replace([' ', "'"], '', $prenom)) . '.'
                . strtolower(str_replace([' ', "'"], '', $nom))
                . (mt_rand(0, 2) === 0 ? mt_rand(10, 99) : '') . '@gmail.com';
        $email  = substr($email, 0, 255);
        $cin_p  = $cin_pre[($id_stag + 2) % count($cin_pre)];
        $cin    = substr($cin_p . mt_rand(100000, 999999), 0, 8);
        $tel    = '06' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $tel_p  = '06' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $tuteur = $tuteurs[$id_stag % count($tuteurs)];
        $num_inscri = sprintf('INS-%04d-%05d', $year_start, $id_stag);

        // remise: 20% students get a discount
        $remise_r = mt_rand(0, 99);
        $remise   = $remise_r < 12 ? 100.00 : ($remise_r < 20 ? 200.00 : 0.00);

        $stmtStag->execute([
            $num_inscri, $cin, $nom, $prenom, $dob, $adresse, $email,
            $tel, $tel_p, $tuteur, $PASS_HASH, $d_insc, $cid, $remise,
        ]);
        $sid = (int)$pdo->lastInsertId();
        $total_stag++;

        // ── Mensualités ──────────────────────────────────────────────────
        $effectif = $tarif - $remise;
        $cumul    = 0.0;
        $pattern  = mt_rand(0, 99);
        if      ($pattern < 55) $pay_type = 'bon';     // 55% bons payeurs
        elseif  ($pattern < 80) $pay_type = 'partiel'; // 25% partiels
        else                    $pay_type = 'mauvais'; // 20% mauvais

        foreach ($school_months as [$sy, $sm]) {
            $mois_date = new DateTime("$sy-$sm-01");
            if ($mois_date > $today) continue;
            $mois_ref = "$sy-" . str_pad((string)$sm, 2, '0', STR_PAD_LEFT);

            $paid_chance = match($pay_type) {
                'bon'     => 88,
                'partiel' => 60,
                'mauvais' => 30,
            };
            $r2 = mt_rand(0, 99);

            if ($r2 < $paid_chance) {
                // payé
                $statut = 'payé'; $est_paye = 1;
                $montant_paye = $effectif;
                $montant_restant = 0.0;
                $date_paiement = "$sy-" . str_pad((string)$sm, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)mt_rand(1, 20), 2, '0', STR_PAD_LEFT);
            } elseif ($r2 < $paid_chance + 15) {
                // partiel
                $statut = 'partiel'; $est_paye = 0;
                $frac   = [30, 40, 50, 60, 70][mt_rand(0, 4)];
                $montant_paye    = round($effectif * $frac / 100, 2);
                $montant_restant = round($effectif - $montant_paye, 2);
                $date_paiement   = "$sy-" . str_pad((string)$sm, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)mt_rand(1, 15), 2, '0', STR_PAD_LEFT);
            } else {
                // impayé
                $statut = 'impayé'; $est_paye = 0;
                $montant_paye = 0.0;
                $montant_restant = $effectif;
                $date_paiement = null;
            }

            $cumul += $montant_restant;
            $stmtMens->execute([
                $sid, $mois_ref, $est_paye, $tarif, $remise,
                $montant_paye, $montant_restant, round($cumul, 2),
                $statut, $date_paiement,
            ]);
            $total_mens++;
        }

        // ── Absences ─────────────────────────────────────────────────────
        $nb_abs = mt_rand(0, 14);
        if ($nb_abs > 0 && count($school_days) > 0) {
            $chosen_days = (array)array_rand(array_flip($school_days), min($nb_abs, count($school_days)));
            foreach ($chosen_days as $abs_date) {
                $slot   = $heure_slots[mt_rand(0, 3)];
                $est_j  = mt_rand(0, 9) < 4 ? 1 : 0;
                $justif = $est_j ? $justifs[mt_rand(0, count($justifs) - 1)] : null;
                $mod_id = (count($fil_mods) > 0 && mt_rand(0, 1))
                          ? $fil_mods[mt_rand(0, count($fil_mods) - 1)][0] : null;
                $stmtAbs->execute([$sid, $abs_date, $slot[0], $slot[1], $est_j, $justif, $mod_id]);
                $total_abs++;
            }
        }

        // ── Notes ────────────────────────────────────────────────────────
        foreach ($fil_mods as [$mid, $nb_ctrl]) {
            // Contrôles
            for ($ctrl = 1; $ctrl <= $nb_ctrl; $ctrl++) {
                $note = round(mt_rand(600, 2000) / 100, 2);
                $stmtNote->execute([$sid, $mid, $note, "controle_$ctrl"]);
                $total_notes++;
            }
            // Théorique (85% des cas)
            if (mt_rand(0, 99) < 85) {
                $stmtNote->execute([$sid, $mid, round(mt_rand(500, 2000) / 100, 2), 'theorique']);
                $total_notes++;
            }
            // Pratique (75% des cas)
            if (mt_rand(0, 99) < 75) {
                $stmtNote->execute([$sid, $mid, round(mt_rand(500, 2000) / 100, 2), 'pratique']);
                $total_notes++;
            }
        }

        // ── Stage ────────────────────────────────────────────────────────
        // 40% des étudiants des classes 2024/2025 ont un stage, 25% pour 2025/2026
        $stage_chance = ($annee === '2024/2025') ? 45 : 28;
        if (mt_rand(0, 99) < $stage_chance) {
            $type_stage = ($fid === 4) ? 'stage_entreprise'
                        : (mt_rand(0, 1) ? 'pfe' : 'stage_entreprise');
            $sujets = ($type_stage === 'pfe') ? $sujets_pfe : $sujets_se;
            $entreprise = $entreprises[mt_rand(0, count($entreprises) - 1)];
            $sujet      = $sujets[mt_rand(0, count($sujets) - 1)];
            $deb_m      = mt_rand(3, 5);
            $deb_d      = mt_rand(1, 10);
            $date_debut = sprintf('%04d-%02d-%02d', $year_start + 1, $deb_m, $deb_d);
            $date_fin_dt= (new DateTime($date_debut))->modify('+' . mt_rand(45, 90) . ' days');
            $date_fin   = $date_fin_dt->format('Y-m-d');
            $date_sout  = null; $jury_v = null; $note_st = null; $eval_v = null;
            if ($date_fin_dt <= $today) {
                $sout_dt = (clone $date_fin_dt)->modify('+' . mt_rand(10, 30) . ' days');
                $date_sout = $sout_dt->format('Y-m-d');
                $jury_v  = $jury_options[mt_rand(0, count($jury_options) - 1)];
                $note_st = round(mt_rand(1200, 1950) / 100, 2);
                $eval_v  = $evals[mt_rand(0, count($evals) - 1)];
            }
            $stmtStage->execute([
                $sid, $type_stage, $entreprise, substr($sujet, 0, 512), $annee,
                $date_debut, $date_fin, $date_sout, $jury_v, $note_st, $eval_v,
            ]);
        }

        $id_stag++;
    }
    log_msg("   ✓ Classe '$nom_classe' — 30 stagiaires insérés");
}

log_msg("\n   Total stagiaires : $total_stag");
log_msg("   Total mensualités : $total_mens");
log_msg("   Total absences    : $total_abs");
log_msg("   Total notes       : $total_notes\n");

// ── Pré-inscriptions ──────────────────────────────────────────────────────
log_msg("📝 Insertion des pré-inscriptions...");
$stmtPI = $pdo->prepare(
    'INSERT INTO pre_inscription
     (id_demande,nom,prenom,cin,date_naissance,adresse,email,telephone,telephone_parent,
      nom_tuteur,id_filiere,annee_scolaire_visee,statut,date_soumission,date_decision,
      id_stagiaire_cree,sexe,niveaux,diplomes,formations,autre_formation,sources,source_autre,licences)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?,NULL,?,NULL,NULL)'
);
$pi_data = [
    // 10 en_attente
    [1,'Bennani','Karim','BK234512','2004-03-15','45 Av Hassan II, Casablanca','karim.bennani@gmail.com','0612345678','0698765432','Hassan Bennani',1,'2025/2026','en_attente','2025-06-10','M','Baccalauréat','Bac Sciences Math','Formation initiale','Bouche à oreille'],
    [2,'El Fassi','Soumia','WA876543','2005-07-22','12 Rue Patrice Lumumba, Rabat','soumia.elfassi@gmail.com','0614567890','0677654321','Latifa El Fassi',2,'2025/2026','en_attente','2025-06-12','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Réseaux sociaux'],
    [3,'Alami','Driss','EE345678','2003-11-08','78 Bd Mohammed V, Fès','driss.alami@gmail.com','0622334455','0655443322','Fatima Alami',3,'2025/2026','en_attente','2025-06-15','M','Baccalauréat','Bac STI','Formation initiale','Site web IPIRNET'],
    [4,'Ziani','Houda','JB123987','2004-05-30','23 Rue Ibn Battouta, Agadir','houda.ziani@gmail.com','0633445566','0644556677','Mohamed Ziani',4,'2025/2026','en_attente','2025-06-18','F','Baccalauréat','Bac STG','Formation initiale','Bouche à oreille'],
    [5,'Tahiri','Youssef','CN456789','2005-02-14','56 Av Allal El Fassi, Meknès','youssef.tahiri@gmail.com','0611223344','0688997766','Nadia Tahiri',1,'2025/2026','en_attente','2025-06-20','M','Baccalauréat','Bac Sciences Math','Formation initiale','Bouche à oreille'],
    [6,'Berrada','Imane','BH567890','2004-09-03','89 Rue Omar El Khayyam, Casablanca','imane.berrada@gmail.com','0644556677','0633223311','Khalid Berrada',2,'2025/2026','en_attente','2025-06-22','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Réseaux sociaux'],
    [7,'Naciri','Hamza','GN678901','2005-12-19','34 Bd Al Massira, Salé','hamza.naciri@gmail.com','0622113344','0655334455','Souad Naciri',1,'2026/2027','en_attente','2025-07-01','M','Baccalauréat','Bac Sciences Math','Formation initiale','Site web IPIRNET'],
    [8,'El Ouazzani','Sara','UD789012','2004-04-27','67 Rue Abdelmoumen, Rabat','sara.elouazzani@gmail.com','0633112244','0677889900','Abdelkrim El Ouazzani',3,'2026/2027','en_attente','2025-07-03','F','Baccalauréat','Bac STI','Formation initiale','Bouche à oreille'],
    [9,'Kadiri','Mouad','SH890123','2003-08-11','90 Av Mohammed VI, Marrakech','mouad.kadiri@gmail.com','0611009988','0699887766','Rajaa Kadiri',4,'2026/2027','en_attente','2025-07-05','M','Baccalauréat','Bac STG','Formation initiale','Bouche à oreille'],
    [10,'Lahlou','Nisrine','TK901234','2005-01-25','12 Rue El Amraoui Bouhmidi, Oujda','nisrine.lahlou@gmail.com','0622009900','0644110022','Brahim Lahlou',2,'2026/2027','en_attente','2025-07-08','F','Baccalauréat','Bac Sciences Math','Formation initiale','Réseaux sociaux'],
    // 3 convertis (déjà inscrits)
    [11,'Regragui','Amine','FJ012345','2003-06-17','45 Bd Ibn Tofail, Kénitra','amine.regragui@gmail.com','0633445500','0622334411','Hassan Regragui',1,'2024/2025','converti','2024-06-05','M','Baccalauréat','Bac Sciences Math','Formation initiale','Bouche à oreille'],
    [12,'El Mansouri','Zineb','AA123456','2004-03-08','78 Rue Sidi Blyout, Casablanca','zineb.elmansouri@gmail.com','0644556600','0611445533','Fatima El Mansouri',2,'2024/2025','converti','2024-06-10','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Site web IPIRNET'],
    [13,'Moussaoui','Othmane','BK345678','2003-10-22','23 Av Zerktouni, Fès','othmane.moussaoui@gmail.com','0622667788','0633778899','Naima Moussaoui',3,'2024/2025','converti','2024-06-15','M','Baccalauréat','Bac STI','Formation initiale','Bouche à oreille'],
    // 3 abandonnés
    [14,'Fennich','Khalid','EE456789','2004-07-14','56 Rue Ibn Khaldoun, Salé','khalid.fennich@gmail.com','0611556677','0699445566','Amina Fennich',4,'2024/2025','abandonne','2024-05-20','M','Baccalauréat','Bac STG','Formation initiale','Réseaux sociaux'],
    [15,'Boudali','Nadia','JB567890','2005-04-03','89 Bd Al Qods, Agadir','nadia.boudali@gmail.com','0633667788','0622556677','Said Boudali',1,'2024/2025','abandonne','2024-05-25','F','Baccalauréat','Bac Sciences Math','Formation initiale','Bouche à oreille'],
    [16,'Filali','Anas','CN678901','2004-12-18','34 Rue Abdelkrim El Khattabi, Meknès','anas.filali@gmail.com','0644778899','0633889900','Khadija Filali',2,'2024/2025','abandonne','2024-06-01','M','Baccalauréat','Bac Sciences Physiques','Formation initiale','Site web IPIRNET'],
];
foreach ($pi_data as $pi) {
    $stmtPI->execute($pi);
}
log_msg("   ✓ " . count($pi_data) . " pré-inscriptions (10 en attente, 3 converties, 3 abandonnées)\n");

// ── seq_inscription ────────────────────────────────────────────────────────
$pdo->exec("INSERT INTO seq_inscription (annee, last_num) VALUES (2024, 210), (2025, 210)
            ON DUPLICATE KEY UPDATE last_num = VALUES(last_num)");

// ── Historique d'audit ────────────────────────────────────────────────────
log_msg("📋 Insertion de quelques entrées d'audit...");
$stmtAudit = $pdo->prepare(
    'INSERT INTO stagiaire_historique (id_stagiaire,champ,ancien,nouveau,note) VALUES (?,?,?,?,?)'
);
// quelques changements de classe réalistes
$stmtAudit->execute([1, 'classe', 'DD-1 (2024/2025)', 'DD-2 (2024/2025)', 'Passage en 2ème année']);
$stmtAudit->execute([5, 'classe', 'TI-1 (2024/2025)', 'DD-1 (2024/2025)', 'Changement de filière — accord direction']);
$stmtAudit->execute([12,'annee_scolaire', '2024/2025', '2025/2026', 'Redoublement validé']);
$stmtAudit->execute([8, 'filiere', 'Technicien en Informatique', 'Infrastructure Digitale', 'Réorientation sur demande']);
$stmtAudit->execute([20,'classe', 'ID-1 (2024/2025)', 'ASR-1 (2025/2026)', 'Transfert inter-filière']);
log_msg("   ✓ 5 entrées d'audit\n");

// ── Résumé final ──────────────────────────────────────────────────────────
log_msg("═══════════════════════════════════════════════════════");
log_msg("✅ Seeding terminé avec succès !");
log_msg("═══════════════════════════════════════════════════════");
log_msg("   Filières        : " . count($filieres));
log_msg("   Classes         : " . count($classes));
log_msg("   Modules         : " . count($modules));
log_msg("   Stagiaires      : $total_stag  (30 par classe)");
log_msg("   Mensualités     : $total_mens");
log_msg("   Absences        : $total_abs");
log_msg("   Notes           : $total_notes");
log_msg("   Pré-inscriptions: " . count($pi_data));
log_msg("   Utilisateurs    : 2 secrétaires");
log_msg("───────────────────────────────────────────────────────");
log_msg("   Mot de passe stagiaires  : changeme");
log_msg("   Mot de passe secrétaires : changeme (login secretaire1 / secretaire2)");
log_msg("═══════════════════════════════════════════════════════");
