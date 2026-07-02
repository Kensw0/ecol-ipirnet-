<?php
/**
 * IPIRNET — Script de peuplement de la base de données
 * ======================================================
 * Lance depuis le terminal XAMPP :
 *   php seed.php
 * Ou ouvre depuis le navigateur :
 *   http://localhost/gestion_des_stagiaires/seed.php
 *
 * ⚠  SUPPRIME et RECRÉE toutes les données — ne pas utiliser en production.
 */

declare(strict_types=1);

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

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}
function out(string $s): void { echo $s . "\n"; if (php_sapi_name() !== 'cli') flush(); }

// ── Mot de passe universel ─────────────────────────────────────────────────
$PASS = password_hash('changeme', PASSWORD_DEFAULT);

// ══════════════════════════════════════════════════════════════════════════
//  DONNÉES RÉELLES DE LA BASE IPIRNET
// ══════════════════════════════════════════════════════════════════════════

// ── Filières (IDs exacts de la BDD) ───────────────────────────────────────
// id_filiere | nom_filiere | niveau | capacite
$FILIERES = [
    [2, 'TSDI', null, 30],
    [3, 'TGI',  null, 30],
    [4, 'TSGE', null, 30],
];

// ── Classes (IDs exacts de la BDD) ────────────────────────────────────────
// id_classe | nom_classe | annee_scolaire | niveau | id_filiere | capacite
$CLASSES = [
    [ 1, '1A TSDI', '2025/2026', '1ère année', 2, 30],
    [ 2, '2A TSDI', '2025/2026', '2ème année', 2, 30],
    [ 3, '1A TGI',  '2025/2026', '1ère année', 3, 30],
    [ 4, '2A TGI',  '2025/2026', '2ème année', 3, 30],
    [ 5, '1A TSGE', '2025/2026', '1ère année', 4, 30],
    [ 6, '2A TSGE', '2025/2026', '2ème année', 4, 30],
    [ 9, '1A TSDI', '2024/2025', '1ère année', 2, 30],
    [10, '2A TSDI', '2024/2025', '2ème année', 2, 30],
    [11, '1A TGI',  '2024/2025', '1ère année', 3, 30],
    [12, '2A TGI',  '2024/2025', '2ème année', 3, 30],
    [13, '1A TSGE', '2024/2025', '1ère année', 4, 30],
    [14, '2A TSGE', '2024/2025', '2ème année', 4, 30],
];

// ── Modules (IDs exacts + nb_controles pour les notes) ────────────────────
// id_module | nom_module | masse_horaire | semestre | id_filiere | coefficient | nb_controles
$MODULES = [
    // TSDI (filière 2)
    [ 2, 'Algorithmique et Programmation',                    120,  1, 2, 5, 2],
    [ 3, 'Bases de données',                                   90,  0, 2, 1, 1],
    [ 4, 'Développement Web',                                 100,  2, 2, 1, 1],
    [19, 'Métier et Formation',                              null, null, 2, 1, 1],
    [20, "L'entreprise et son environnement",                null, null, 2, 1, 1],
    [21, 'Notion de mathématique appliquée',                 null, null, 2, 1, 1],
    [22, 'Gestion du temps',                                 null, null, 2, 1, 1],
    [23, 'Veille technologique',                             null, null, 2, 1, 1],
    [24, "Logiciel d'application",                           null, null, 2, 1, 1],
    [25, 'Programmation événementielle',                     null, null, 2, 5, 1],
    [26, 'Technique de programmation structurée',            null, null, 2, 5, 1],
    [27, 'Langage de programmation structurée',              null, null, 2, 5, 1],
    [28, 'Programmation orientée objet',                     null, null, 2, 5, 1],
    [29, "Concept et modélisation d'un système d'information", null, null, 2, 1, 1],
    [30, "Installation d'un poste informatique",             null, null, 2, 1, 1],
    [31, 'Communication en Anglais',                         null, null, 2, 1, 1],
    [32, "Assistant technique à la clientèle",               null, null, 2, 1, 1],
    [59, 'UML',                                                50,  2, 2, 5, 3],
    // TGI (filière 3)
    [40, 'Algorithm',                     50, 1, 3, 5, 2],
    [41, "Installation d'un poste",       30, 2, 3, 4, 2],
    [42, 'Bureautique',                   40, 2, 3, 3, 2],
    [43, 'Comptabilité générale',         50, 1, 3, 7, 3],
    [44, 'Statistique',                   35, 2, 3, 6, 3],
    // TSGE (filière 4)
    [33, 'Comptabilité générale',    null, null, 4, 1, 1],
    [34, 'Concept de base',          null, null, 4, 1, 1],
    [35, 'Traitement de salaire',    null, null, 4, 1, 1],
    [36, 'Charge de personnel',      null, null, 4, 1, 1],
    [37, 'Marketing',                null, null, 4, 1, 1],
    [38, 'Entreprise',               null, null, 4, 1, 1],
    [39, 'Statistique',              null, null, 4, 1, 1],
];

// Index filiere → [(id_module, nb_controles), ...]
$MOD_BY_FIL = [];
foreach ($MODULES as $m) {
    $MOD_BY_FIL[$m[4]][] = [$m[0], $m[6]]; // [id_module, nb_controles]
}

// ══════════════════════════════════════════════════════════════════════════
//  DONNÉES POUR LA GÉNÉRATION
// ══════════════════════════════════════════════════════════════════════════

$prenoms_m = ['Yassine','Mehdi','Anas','Hamza','Soufiane','Ayoub','Karim','Mohamed','Rachid','Omar',
              'Zakaria','Bilal','Ilias','Nabil','Reda','Adil','Hicham','Saad','Walid','Tarik',
              'Abdelhak','Fouad','Khalid','Mouad','Imrane','Oussama','Hatim','Brahim','Faissal','Achraf'];
$prenoms_f = ['Salma','Nadia','Sara','Fatima','Kenza','Laila','Zineb','Hajar','Amina','Meryem',
              'Dounia','Ghita','Loubna','Rania','Yasmine','Houda','Sanaa','Soukaina','Widad','Rim',
              'Hanane','Chaimae','Boutaina','Malak','Ikram','Oumaima','Nour','Ilham','Samira','Aya'];
$noms = ['El Amrani','Benali','Ouali','Tazi','Benhaddou','Cherkaoui','Alami','Benomar','El Fassi',
         'Hajji','Belkadi','Ziani','Lahlou','Berrada','El Idrissi','Moussaoui','Senhaji','Kadiri',
         'Boudali','Naciri','Rahmani','Fennich','El Ouazzani','Tahiri','Filali','Guessous','Regragui',
         'El Mansouri','Boukili','Akhdar','Drissi','Mahfoud','El Khayat','Ouazzani','Benkirane','Mernissi',
         'Kettani','Skalli','Rahal','Bensouda','Chakroun','Lahrech','Belhaj','Bennani','Zeroual','Chraibi'];
$cin_pre   = ['WA','BH','BK','EE','JB','CN','GN','UD','SH','TK','FJ','AA','LC','MA'];
$villes    = ['Casablanca','Rabat','Salé','Fès','Marrakech','Agadir','Meknès','Oujda',
              'Kénitra','Tétouan','Témara','Nador','Mohammedia','Berrechid','El Jadida','Settat'];
$rues      = ['Bd Zerktouni','Av Hassan II','Rue Ibn Battouta','Av des FAR','Bd Mohammed V',
              'Rue Lalla Yacout','Bd Moulay Abdallah','Av Allal El Fassi','Rue Sebou',
              'Bd Yacoub El Mansour','Av Mohammed VI','Rue Omar El Khayyam','Bd Al Qods',
              'Av Fal Ould Oumeir','Rue Abdelmoumen','Bd Ibn Tofail','Rue Panorama'];
$tuteurs   = ['Hassan Benali','Rachida Belhaj','Mohamed Ouali','Fatiha Tazi','Said Benhaddou',
              'Naima Cherkaoui','Khalid Alami','Zohra Benomar','Abderrahman El Fassi','Loubna Hajji',
              'Aziz Belkadi','Nour Ziani','Hassan Lahlou','Nadia Berrada','Abdellatif El Idrissi',
              'Omar Moussaoui','Samira Senhaji','Brahim Kadiri','Amal Naciri','Younes Rahmani'];

$entreprises = ['IPIRNET SARL','Maroc Telecom','OCP Group','Attijariwafa Bank','CIH Bank',
                'BMCE Bank','Lafarge Maroc','Lydec','RAM IT','INWI Technologies',
                'Soprogest','Data4u','Orange Maroc','Wafa Assurance','CDG','Al Mada',
                'OceanSoft','IT2S Group','BMCI','Banque Populaire'];
$sujets_pfe = [
    "Développement d'une application de gestion RH",
    "Mise en place d'une solution ERP open-source",
    "Conception d'une plateforme de gestion des stagiaires",
    "Développement d'une API REST pour une application mobile",
    "Migration d'une infrastructure vers le cloud",
    "Automatisation des déploiements CI/CD",
    "Développement d'un système de ticketing helpdesk",
    "Création d'un tableau de bord analytique BI",
    "Mise en place d'un système de comptabilité analytique",
    "Développement d'une application de gestion commerciale",
];
$sujets_se = [
    "Assistance technique et support utilisateurs",
    "Administration du réseau local de l'entreprise",
    "Maintenance préventive des postes de travail",
    "Gestion et inventaire du parc informatique",
    "Support helpdesk et résolution d'incidents",
    "Saisie et traitement des données comptables",
    "Gestion de la paie et des déclarations sociales",
];
$jury_opts = [
    'M. Benhaddou, Mme. Alami',    'M. El Fassi, M. Cherkaoui',
    'Mme. Berrada, M. Tazi',       'M. Kadiri, Mme. Lahlou',
    'M. Ziani, M. Alami',          'Mme. Senhaji, M. Naciri',
    'M. Rahmani, Mme. El Ouazzani','M. Ouali, Mme. Bennani',
];
$evals  = ['Très Bien','Bien','Assez Bien','Satisfaisant'];
$justifs = ['Certificat médical','Convocation administrative','Raison familiale urgente',
            'Convocation officielle','Maladie confirmée','Accident de trajet','Démarche administrative'];

$tarif = 850.00; // MAD / mois
$today = new DateTime('2026-07-02');

// ══════════════════════════════════════════════════════════════════════════
//  TRUNCATE
// ══════════════════════════════════════════════════════════════════════════
out("🗑  Suppression des données existantes...");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['documents_generes','stagiaire_historique','module_notes','absences',
          'mensualites','stages','stagiaires','pre_inscription',
          'modules','classes','filieres','users','seq_inscription'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
out("   ✓ Tables vidées\n");

// ══════════════════════════════════════════════════════════════════════════
//  FILIERES, CLASSES, MODULES, USERS
// ══════════════════════════════════════════════════════════════════════════
out("📚 Insertion des filières...");
$s = $pdo->prepare('INSERT INTO filieres (id_filiere,nom_filiere,niveau,capacite) VALUES (?,?,?,?)');
foreach ($FILIERES as $f) { $s->execute($f); }
out("   ✓ " . count($FILIERES) . " filières\n");

out("🏫 Insertion des classes...");
$s = $pdo->prepare('INSERT INTO classes (id_classe,nom_classe,annee_scolaire,niveau,id_filiere,capacite) VALUES (?,?,?,?,?,?)');
foreach ($CLASSES as $c) { $s->execute($c); }
out("   ✓ " . count($CLASSES) . " classes\n");

out("📖 Insertion des modules...");
$s = $pdo->prepare('INSERT INTO modules (id_module,nom_module,masse_horaire,semestre,id_filiere,coefficient,nb_controles) VALUES (?,?,?,?,?,?,?)');
foreach ($MODULES as $m) { $s->execute($m); }
out("   ✓ " . count($MODULES) . " modules\n");

out("👤 Insertion des utilisateurs...");
$s = $pdo->prepare('INSERT INTO users (id,username,password_hash,role) VALUES (?,?,?,?)');
$s->execute([1, 'secretaire1', $PASS, 'secretaire']);
$s->execute([2, 'secretaire2', $PASS, 'secretaire']);
out("   ✓ 2 secrétaires (mot de passe: changeme)\n");

// ══════════════════════════════════════════════════════════════════════════
//  STAGIAIRES + MENSUALITÉS + ABSENCES + NOTES + STAGES
// ══════════════════════════════════════════════════════════════════════════
out("🎓 Génération des stagiaires, mensualités, absences, notes et stages...");

$stStag = $pdo->prepare(
    'INSERT INTO stagiaires
       (num_inscri,cin,nom,prenom,date_naissance,adresse,email,telephone,telephone_parent,
        nom_tuteur,mot_de_passe,photo,date_inscription,id_classe,remise_mensuelle)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,?,?,?)'
);
$stMens = $pdo->prepare(
    'INSERT INTO mensualites
       (id_stagiaire,mois_ref,est_paye,montant_total,remise,montant_paye,
        montant_restant,cumul_restant,statut_paiement,date_paiement,marque_le)
     VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
);
$stAbs = $pdo->prepare(
    'INSERT INTO absences
       (id_stagiaire,date_absence,heure_debut,heure_fin,est_justifiee,justificatif,id_module)
     VALUES (?,?,?,?,?,?,?)'
);
$stNote = $pdo->prepare(
    'INSERT INTO module_notes (id_stagiaire,id_module,note,type) VALUES (?,?,?,?)'
);
$stStage = $pdo->prepare(
    'INSERT INTO stages
       (id_stagiaire,type_stage,entreprise,sujet,annee_scolaire,date_debut,date_fin,
        date_soutenance,jury,note_stage,evaluation_entreprise,convention_url,rapport_url)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,NULL)'
);

$hslots = [['08:30','10:30'],['10:30','12:30'],['14:00','16:00'],['16:00','18:00']];

// months in a school year
function school_months(string $annee): array {
    $y1 = (int)substr($annee, 0, 4);
    $months = [];
    for ($m = 9; $m <= 12; $m++) $months[] = [$y1,   $m];
    for ($m = 1; $m <= 6;  $m++) $months[] = [$y1+1, $m];
    return $months;
}

mt_srand(1337);

$cntStag = 0; $cntMens = 0; $cntAbs = 0; $cntNotes = 0; $cntStages = 0;

// Track num_inscri counters per year
$inscri_ctr = [];

foreach ($CLASSES as [$cid, $nom_classe, $annee, $niveau_cl, $fid, $cap]) {

    $y1     = (int)substr($annee, 0, 4);
    $d_insc = "$y1-09-01";
    $mois   = school_months($annee);
    $fil_mods = $MOD_BY_FIL[$fid] ?? [];

    // Build list of past school days for this annee
    $school_days = [];
    foreach ($mois as [$sy, $sm]) {
        $last = (int)(new DateTime("$sy-$sm-01"))->format('t');
        for ($d = 1; $d <= $last; $d++) {
            $dt = new DateTime(sprintf('%04d-%02d-%02d', $sy, $sm, $d));
            if ((int)$dt->format('N') < 6 && $dt <= $today) {
                $school_days[] = $dt->format('Y-m-d');
            }
        }
    }

    for ($i = 0; $i < 30; $i++) {
        $is_f  = ($i % 3 === 0);
        $prenom = ($is_f ? $prenoms_f : $prenoms_m)[$i % 30];
        $nom    = $noms[($cntStag + 5) % count($noms)];
        $age    = mt_rand(18, 25);
        $dob    = sprintf('%04d-%02d-%02d', $y1 - $age, mt_rand(1,12), mt_rand(1,28));
        $ville  = $villes[$cntStag % count($villes)];
        $rue    = $rues[$cntStag % count($rues)];
        $num    = mt_rand(1, 200);
        $adresse = "$num $rue, $ville";
        $email  = strtolower(preg_replace("/[' ]/", '', $prenom)) . '.'
                . strtolower(preg_replace("/[' ]/", '', explode(' ', $nom)[count(explode(' ', $nom))-1]))
                . ($cntStag + 1) . '@gmail.com';
        $cin    = substr($cin_pre[$cntStag % count($cin_pre)] . mt_rand(100000,999999), 0, 8);
        $tel    = '0' . mt_rand(6,7) . str_pad((string)mt_rand(0,99999999), 8, '0', STR_PAD_LEFT);
        $tel_p  = '0' . mt_rand(6,7) . str_pad((string)mt_rand(0,99999999), 8, '0', STR_PAD_LEFT);
        $tuteur = $tuteurs[$cntStag % count($tuteurs)];

        // Numéro d'inscription au format réel : INS-YYYY-NNNNN
        $inscri_ctr[$y1] = ($inscri_ctr[$y1] ?? 0) + 1;
        $num_inscri = sprintf('INS-%04d-%05d', $y1, $inscri_ctr[$y1]);

        // Remise : 20% des étudiants
        $r = mt_rand(0, 99);
        $remise = $r < 10 ? 200.00 : ($r < 22 ? 100.00 : 0.00);

        $stStag->execute([
            $num_inscri, $cin, $nom, $prenom, $dob, $adresse, $email,
            $tel, $tel_p, $tuteur, $PASS, $d_insc, $cid, $remise,
        ]);
        $sid = (int)$pdo->lastInsertId();
        $cntStag++;

        // ── Mensualités ──────────────────────────────────────────────────
        $effectif = $tarif - $remise;
        $cumul    = 0.0;
        $pr = mt_rand(0, 99);
        $pay_type = $pr < 55 ? 'bon' : ($pr < 80 ? 'partiel' : 'mauvais');

        foreach ($mois as [$sy, $sm]) {
            $mois_date = new DateTime("$sy-$sm-01");
            if ($mois_date > $today) continue;
            $mois_ref  = sprintf('%04d-%02d', $sy, $sm);

            $paid_chance = match($pay_type) { 'bon' => 88, 'partiel' => 58, 'mauvais' => 28 };
            $r2 = mt_rand(0, 99);

            if ($r2 < $paid_chance) {
                $statut = 'payé'; $est_paye = 1;
                $mpaye = $effectif; $mrest = 0.0;
                $dpay = sprintf('%04d-%02d-%02d', $sy, $sm, mt_rand(1,20));
            } elseif ($r2 < $paid_chance + 15) {
                $statut = 'partiel'; $est_paye = 0;
                $pct   = [30,40,50,60,70][mt_rand(0,4)];
                $mpaye = round($effectif * $pct / 100, 2);
                $mrest = round($effectif - $mpaye, 2);
                $dpay  = sprintf('%04d-%02d-%02d', $sy, $sm, mt_rand(1,15));
            } else {
                $statut = 'impayé'; $est_paye = 0;
                $mpaye = 0.0; $mrest = $effectif; $dpay = null;
            }

            $cumul += $mrest;
            $stMens->execute([
                $sid, $mois_ref, $est_paye, $tarif, $remise,
                $mpaye, $mrest, round($cumul, 2), $statut, $dpay,
            ]);
            $cntMens++;
        }

        // ── Absences ─────────────────────────────────────────────────────
        $nb_abs = mt_rand(0, 14);
        if ($nb_abs > 0 && count($school_days) > 0) {
            $idx_pool = array_rand($school_days, min($nb_abs, count($school_days)));
            foreach ((array)$idx_pool as $idx) {
                $slot  = $hslots[mt_rand(0,3)];
                $est_j = mt_rand(0,9) < 4 ? 1 : 0;
                $justif = $est_j ? $justifs[mt_rand(0, count($justifs)-1)] : null;
                $mod_id = (count($fil_mods) > 0 && mt_rand(0,1))
                          ? $fil_mods[mt_rand(0,count($fil_mods)-1)][0] : null;
                $stAbs->execute([$sid, $school_days[$idx], $slot[0], $slot[1], $est_j, $justif, $mod_id]);
                $cntAbs++;
            }
        }

        // ── Notes ────────────────────────────────────────────────────────
        foreach ($fil_mods as [$mid, $nb_ctrl]) {
            for ($c = 1; $c <= $nb_ctrl; $c++) {
                $stNote->execute([$sid, $mid, round(mt_rand(600,2000)/100, 2), "controle_$c"]);
                $cntNotes++;
            }
            if (mt_rand(0,99) < 85) {
                $stNote->execute([$sid, $mid, round(mt_rand(500,2000)/100, 2), 'theorique']);
                $cntNotes++;
            }
            if (mt_rand(0,99) < 75) {
                $stNote->execute([$sid, $mid, round(mt_rand(500,2000)/100, 2), 'pratique']);
                $cntNotes++;
            }
        }

        // ── Stage ────────────────────────────────────────────────────────
        // 2ème année + 2024/2025 → 50% ont un stage
        // 1ère année + tous → 20%
        $is_2a  = str_contains($niveau_cl, '2');
        $is_old = ($annee === '2024/2025');
        $chance = ($is_2a && $is_old) ? 55 : ($is_2a ? 30 : ($is_old ? 25 : 15));

        if (mt_rand(0,99) < $chance) {
            $type_stage = ($fid === 4)
                ? 'stage_entreprise'                                // TSGE → stage entreprise
                : (mt_rand(0,1) ? 'pfe' : 'stage_entreprise');     // TSDI/TGI → 50/50
            $sujets = ($type_stage === 'pfe') ? $sujets_pfe : $sujets_se;
            $entreprise = $entreprises[mt_rand(0,count($entreprises)-1)];
            $sujet      = $sujets[mt_rand(0,count($sujets)-1)];
            $deb_m = mt_rand(3, 5);
            $date_deb = new DateTime(sprintf('%04d-%02d-%02d', $y1+1, $deb_m, mt_rand(1,10)));
            $date_fin = (clone $date_deb)->modify('+' . mt_rand(45,90) . ' days');
            $date_sout = null; $jury = null; $note_st = null; $eval_v = null;

            if ($date_fin <= $today) {
                $date_sout = (clone $date_fin)->modify('+' . mt_rand(10,25) . ' days')->format('Y-m-d');
                $jury  = $jury_opts[mt_rand(0,count($jury_opts)-1)];
                $note_st = round(mt_rand(1100,1950)/100, 2);
                $eval_v  = $evals[mt_rand(0,count($evals)-1)];
            }

            $stStage->execute([
                $sid, $type_stage, $entreprise, substr($sujet,0,512), $annee,
                $date_deb->format('Y-m-d'), $date_fin->format('Y-m-d'),
                $date_sout, $jury, $note_st, $eval_v,
            ]);
            $cntStages++;
        }
    }

    out("   ✓ Classe '$nom_classe' ($annee) — 30 stagiaires");
}

out("");
out("   Stagiaires insérés  : $cntStag");
out("   Mensualités         : $cntMens");
out("   Absences            : $cntAbs");
out("   Notes               : $cntNotes");
out("   Stages              : $cntStages");

// ══════════════════════════════════════════════════════════════════════════
//  PRÉ-INSCRIPTIONS
// ══════════════════════════════════════════════════════════════════════════
out("\n📝 Insertion des pré-inscriptions...");
$stPI = $pdo->prepare(
    'INSERT INTO pre_inscription
       (id_demande,cin,nom,prenom,date_naissance,adresse,email,telephone,telephone_parent,
        nom_tuteur,id_filiere,annee_scolaire_visee,statut,date_soumission,date_decision,
        id_stagiaire_cree,sexe,niveaux,diplomes,formations,autre_formation,sources,source_autre,licences)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?,NULL,?,NULL,NULL)'
);

// [id, cin, nom, prenom, dob, adresse, email, tel, tel_p, tuteur, id_filiere, annee_visee,
//  statut, date_soumission, sexe, niveaux, diplomes, formations, sources]
$PI = [
    // ── en attente ────────────────────────────────────────────────────────
    [ 1,'BK234512','Bennani','Karim',       '2004-03-15','45 Bd Hassan II, Casablanca',     'karim.bennani1@gmail.com',    '0612345678','0698765432','Hassan Bennani',     2,'2026/2027','en_attente','2026-04-10','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Bouche à oreille'],
    [ 2,'WA876543','El Fassi','Soumia',     '2005-07-22','12 Rue Lalla Yacout, Rabat',      'soumia.elfassi1@gmail.com',   '0614567890','0677654321','Latifa El Fassi',    2,'2026/2027','en_attente','2026-04-15','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Réseaux sociaux'],
    [ 3,'EE345678','Alami','Driss',         '2003-11-08','78 Av des FAR, Fès',              'driss.alami1@gmail.com',      '0622334455','0655443322','Fatima Alami',       3,'2026/2027','en_attente','2026-04-20','M','Baccalauréat','Bac STI',              'Formation initiale','Site web IPIRNET'],
    [ 4,'JB123987','Ziani','Houda',         '2004-05-30','23 Rue Ibn Battouta, Agadir',     'houda.ziani1@gmail.com',      '0633445566','0644556677','Mohamed Ziani',      4,'2026/2027','en_attente','2026-05-02','F','Baccalauréat','Bac STG',              'Formation initiale','Bouche à oreille'],
    [ 5,'CN456789','Tahiri','Youssef',      '2005-02-14','56 Bd Ibn Tofail, Meknès',        'youssef.tahiri1@gmail.com',   '0611223344','0688997766','Nadia Tahiri',       2,'2026/2027','en_attente','2026-05-08','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Bouche à oreille'],
    [ 6,'BH567890','Berrada','Imane',       '2004-09-03','89 Av Allal El Fassi, Casablanca','imane.berrada1@gmail.com',    '0644556677','0633223311','Khalid Berrada',     2,'2026/2027','en_attente','2026-05-12','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Réseaux sociaux'],
    [ 7,'GN678901','Naciri','Hamza',        '2005-12-19','34 Bd Moulay Abdallah, Salé',     'hamza.naciri1@gmail.com',     '0622113344','0655334455','Souad Naciri',       3,'2026/2027','en_attente','2026-05-18','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Site web IPIRNET'],
    [ 8,'UD789012','El Ouazzani','Sara',    '2004-04-27','67 Rue Sebou, Rabat',             'sara.elouazzani1@gmail.com',  '0633112244','0677889900','Abdelkrim El Ouazzani',3,'2026/2027','en_attente','2026-05-22','F','Baccalauréat','Bac STI',            'Formation initiale','Bouche à oreille'],
    [ 9,'SH890123','Kadiri','Mouad',        '2003-08-11','90 Av Mohammed VI, Marrakech',    'mouad.kadiri1@gmail.com',     '0611009988','0699887766','Rajaa Kadiri',       4,'2026/2027','en_attente','2026-05-28','M','Baccalauréat','Bac STG',              'Formation initiale','Bouche à oreille'],
    [10,'TK901234','Lahlou','Nisrine',      '2005-01-25','12 Rue Panorama, Oujda',          'nisrine.lahlou1@gmail.com',   '0622009900','0644110022','Brahim Lahlou',      2,'2026/2027','en_attente','2026-06-02','F','Baccalauréat','Bac Sciences Math',    'Formation initiale','Réseaux sociaux'],
    [11,'FJ012345','Mernissi','Hicham',     '2004-06-17','45 Av Hassan II, Kénitra',        'hicham.mernissi1@gmail.com',  '0633445500','0622334411','Hassan Mernissi',    2,'2026/2027','en_attente','2026-06-10','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Bouche à oreille'],
    [12,'AA123456','Rahal','Zineb',         '2004-03-08','78 Rue Abdelmoumen, Casablanca',  'zineb.rahal1@gmail.com',      '0644556600','0611445533','Fatima Rahal',       4,'2026/2027','en_attente','2026-06-15','F','Baccalauréat','Bac STG',              'Formation initiale','Site web IPIRNET'],
    // ── convertis ─────────────────────────────────────────────────────────
    [13,'LC345678','Regragui','Amine',      '2003-06-17','45 Bd Yacoub El Mansour, Kénitra','amine.regragui1@gmail.com',   '0633445501','0622334412','Hassan Regragui',    2,'2025/2026','converti', '2025-06-05','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Bouche à oreille'],
    [14,'MA654321','El Mansouri','Zineb',   '2004-03-08','78 Rue Oued Ziz, Casablanca',     'zineb.elmansouri1@gmail.com', '0644556601','0611445534','Fatima El Mansouri', 3,'2025/2026','converti', '2025-06-10','F','Baccalauréat','Bac Sciences Physiques','Formation initiale','Site web IPIRNET'],
    [15,'BK987654','Moussaoui','Othmane',   '2003-10-22','23 Av des FAR, Fès',              'othmane.moussaoui1@gmail.com','0622667788','0633778899','Naima Moussaoui',    4,'2025/2026','converti', '2025-06-15','M','Baccalauréat','Bac STG',              'Formation initiale','Bouche à oreille'],
    // ── abandonnés ────────────────────────────────────────────────────────
    [16,'EE456789','Fennich','Khalid',      '2004-07-14','56 Bd Zerktouni, Salé',           'khalid.fennich1@gmail.com',   '0611556677','0699445566','Amina Fennich',      2,'2024/2025','abandonne','2024-05-20','M','Baccalauréat','Bac Sciences Math',    'Formation initiale','Réseaux sociaux'],
    [17,'JB567890','Boudali','Nadia',       '2005-04-03','89 Rue Lalla Yacout, Agadir',     'nadia.boudali1@gmail.com',    '0633667788','0622556677','Said Boudali',       3,'2024/2025','abandonne','2024-05-25','F','Baccalauréat','Bac STI',              'Formation initiale','Bouche à oreille'],
    [18,'CN678901','Filali','Anas',         '2004-12-18','34 Av Allal El Fassi, Meknès',    'anas.filali1@gmail.com',      '0644778899','0633889900','Khadija Filali',     4,'2024/2025','abandonne','2024-06-01','M','Baccalauréat','Bac STG',              'Formation initiale','Site web IPIRNET'],
];

foreach ($PI as $pi) { $stPI->execute($pi); }
out("   ✓ " . count($PI) . " pré-inscriptions (12 en attente · 3 converties · 3 abandonnées)\n");

// ══════════════════════════════════════════════════════════════════════════
//  SEQ_INSCRIPTION
// ══════════════════════════════════════════════════════════════════════════
$pdo->exec(
    "INSERT INTO seq_inscription (annee, last_num) VALUES
       (2024, {$inscri_ctr[2024]}),
       (2025, {$inscri_ctr[2025]})
     ON DUPLICATE KEY UPDATE last_num = VALUES(last_num)"
);

// ══════════════════════════════════════════════════════════════════════════
//  AUDIT TRAIL
// ══════════════════════════════════════════════════════════════════════════
$stAudit = $pdo->prepare(
    'INSERT INTO stagiaire_historique (id_stagiaire,champ,ancien,nouveau,note) VALUES (?,?,?,?,?)'
);
// Quelques changements réalistes (IDs des premiers stagiaires insérés)
$stAudit->execute([1,  'classe',        '1A TSDI (2024/2025)', '2A TSDI (2024/2025)', 'Avancement de classe']);
$stAudit->execute([5,  'classe',        '1A TGI (2025/2026)',  '1A TSDI (2025/2026)', 'Changement de filière — accord direction']);
$stAudit->execute([12, 'annee_scolaire','2024/2025',           '2025/2026',           'Redoublement validé']);
$stAudit->execute([18, 'classe',        '1A TSGE (2024/2025)', '2A TSGE (2024/2025)', 'Passage de classe']);

// ══════════════════════════════════════════════════════════════════════════
//  RÉSUMÉ
// ══════════════════════════════════════════════════════════════════════════
out("═══════════════════════════════════════════════════════════════");
out("✅ Seeding IPIRNET terminé avec succès !");
out("═══════════════════════════════════════════════════════════════");
out("   Filières        : " . count($FILIERES) . "  (TSDI · TGI · TSGE)");
out("   Classes         : " . count($CLASSES)  . "  (2024/2025 + 2025/2026)");
out("   Modules         : " . count($MODULES));
out("   Stagiaires      : $cntStag  (30 par classe)");
out("   Mensualités     : $cntMens");
out("   Absences        : $cntAbs");
out("   Notes           : $cntNotes");
out("   Stages/PFE      : $cntStages");
out("   Pré-inscriptions: " . count($PI));
out("   Secrétaires     : 2  (secretaire1 / secretaire2)");
out("───────────────────────────────────────────────────────────────");
out("   Mot de passe universel : changeme");
out("═══════════════════════════════════════════════════════════════");
