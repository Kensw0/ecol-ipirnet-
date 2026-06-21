<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$SCHOOL_ORG   = 'Groupe IPIRNET';
$SCHOOL_TAG1  = "Institut Privé d'Informatique, Réseau et Nouvelles";
$SCHOOL_TAG2  = 'Etudes de Télécommunication';
$SCHOOL_AUTH1 = "Autorisé par l'Etat sous N°   : 3/03/2/2003     Du : 19/02/2003";
$SCHOOL_AUTH2 = "Décision de l'accréditation N°    21/ DFP /F0301/ 199    du 29/11/2021";
$SCHOOL_ADDR  = 'Bd Hassan II, Lot ESSAFI, Imm N° 1, Berrechid.  Tel : 0522.32.72.13';

$auto   = isset($_GET['auto']) && $_GET['auto'] === '1';
$filled = false;
$d      = null;

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $st = $pdo->prepare(
        'SELECT d.*, f.nom_filiere
           FROM pre_inscription d
           JOIN filieres f ON f.id_filiere = d.id_filiere
          WHERE d.id_demande = ?'
    );
    $st->execute([$id]);
    $d = $st->fetch();
    if ($d) { 
        $filled = true; 
        if (!empty($d['id_stagiaire_cree'])) {
            log_document_gen($pdo, 'fiche_preinscription', (int)$d['id_stagiaire_cree'], (string)$d['num_inscri']);
        }
    }
}

$e = fn($k) => $filled ? htmlspecialchars((string)($d[$k] ?? ''), ENT_QUOTES, 'UTF-8') : '';

$fmtDate = function(?string $s): string {
    if (!$s) return '';
    $t = strtotime($s);
    return $t ? date('d/m/Y', $t) : $s;
};

$jarr = function(?string $v): array {
    if (!$v) return [];
    $a = json_decode($v, true);
    return is_array($a) ? $a : [];
};

$savedSexe       = $filled ? ($d['sexe'] ?? '') : '';
$savedNiveaux    = $filled ? $jarr($d['niveaux']    ?? null) : [];
$savedDiplomes   = $filled ? $jarr($d['diplomes']   ?? null) : [];
$savedFormations = $filled ? $jarr($d['formations'] ?? null) : [];
$savedSources    = $filled ? $jarr($d['sources']    ?? null) : [];

$filiereRows = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres')->fetchAll();
$filiereById = [];
foreach ($filiereRows as $fr) { $filiereById[(int)$fr['id_filiere']] = strtoupper(trim($fr['nom_filiere'])); }

$dipOptions = [
    'TSGE' => "Technicien Spécialisé en Gestion d'entreprise",
    'TSDI' => 'Technicien Spécialisé en Développement Informatique',
    'TGI'  => 'Technicien en Gestion Informatisée (3AS ou plus)',
];

// Primary: use the selected filière (id_filiere) — the single source of truth since the select redesign
// Fallback: also include anything saved in the legacy diplomes JSON array (for old records)
$checkedDipCodes = [];
if ($filled) {
    // Always mark the chosen filière
    $mainCode = $filiereById[(int)($d['id_filiere'] ?? 0)] ?? null;
    if ($mainCode) $checkedDipCodes[] = $mainCode;
    // Also honour legacy diplomes array (pre-select-redesign records)
    foreach ($savedDiplomes as $fid) {
        $code = $filiereById[(int)$fid] ?? null;
        if ($code && !in_array($code, $checkedDipCodes, true)) $checkedDipCodes[] = $code;
    }
}

function chkEmpty(): string {
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:12px;height:12px;border:1.5px solid #555;border-radius:1px;vertical-align:middle;flex-shrink:0;background:#fff;"></span>';
}
function chkFilled(): string {
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:12px;height:12px;border:1.5px solid #111;border-radius:1px;vertical-align:middle;flex-shrink:0;background:#111;">'
         . '<svg viewBox="0 0 10 7" width="9" height="7" style="display:block;"><polyline points="1,3.5 4,6.5 9,1" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
}
function chk(bool $c): string { return $c ? chkFilled() : chkEmpty(); }

function radioEmpty(): string {
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:12px;height:12px;border:1.5px solid #555;border-radius:50%;vertical-align:middle;flex-shrink:0;background:#fff;"></span>';
}
function radioFilled(): string {
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:12px;height:12px;border:1.5px solid #111;border-radius:50%;vertical-align:middle;flex-shrink:0;background:#fff;"><span style="width:6px;height:6px;border-radius:50%;background:#111;display:block;"></span></span>';
}
function radio(bool $c): string { return $c ? radioFilled() : radioEmpty(); }

$inArr = fn(array $arr, $val) => in_array((string)$val, array_map('strval', $arr), true);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= $filled ? 'Fiche — '.htmlspecialchars(($d['prenom']??'').' '.($d['nom']??'')) : 'Fiche de Pré-inscription vierge' ?></title>
<style>
@page{size:A4 portrait;margin:10mm 14mm 12mm;}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{background:#f1f3f5;}
body{padding:18px 0 40px;font-family:"Times New Roman","Liberation Serif",serif;color:#111;font-size:11.5pt;}
.doc{max-width:860px;margin:0 auto;background:#fff;padding:16px 28px 20px;box-shadow:0 4px 14px rgba(0,0,0,.08);border:1px solid #cdd0d4;}
.no-print{text-align:center;margin-bottom:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;}
.no-print button,.no-print a{background:#f4f4f5;border:1px solid #ccc;padding:.4rem 1rem;border-radius:8px;font-size:.85rem;cursor:pointer;text-decoration:none;color:#111;}
.no-print .primary{background:#1d4ed8;color:#fff;border-color:#1d4ed8;font-weight:600;}
@media print{html,body{background:#fff;}.no-print{display:none!important;}.banner{display:none!important;}.doc{box-shadow:none;border:none;padding:0;margin:0;max-width:100%;}} @page{margin:1cm;size:A4 portrait;}
* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
.lh{display:grid;grid-template-columns:110px 1fr 110px;align-items:center;gap:10px;padding-bottom:5px;border-bottom:1.5px solid #111;}
.lh-logo{display:flex;justify-content:center;}
.lh-logo img{max-width:90px;max-height:90px;}
.lh-mid{text-align:center;}
.org{font-weight:700;font-size:1.4rem;letter-spacing:.03em;text-transform:uppercase;}
.tag{font-style:italic;font-size:.88rem;line-height:1.35;margin-top:2px;}
.auth{font-size:.75rem;line-height:1.4;margin-top:5px;border-top:.5px solid #777;padding-top:4px;}
.stamp-wrap{display:flex;justify-content:center;}
.stamp{width:88px;height:88px;border-radius:50%;border:3px solid #8b6914;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#8b6914;font-size:.52rem;font-weight:700;letter-spacing:.04em;line-height:1.3;padding:6px;background:radial-gradient(circle,#fff 50%,#f9f4e8 100%);}
.st{font-size:.7rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;line-height:1.2;}
.ss{font-size:.5rem;margin-top:2px;line-height:1.3;}
.doc-title{margin:14px auto 12px;text-align:center;}
.doc-title-box{display:inline-block;border:1.5px solid #111;padding:5px 40px;}
.doc-title-txt{font-weight:700;font-size:1.45rem;letter-spacing:.08em;text-transform:uppercase;}
.banner{background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;padding:4px 10px;margin-bottom:8px;font-size:9.5pt;color:#2e7d32;}
.date-right{text-align:right;margin-bottom:8px;font-size:11pt;}
.dv{display:inline-block;min-width:150px;border-bottom:1px solid #444;padding-left:4px;}
.dv.fv{font-weight:600;border-bottom-color:#111;}
.fr{display:flex;align-items:flex-end;gap:6px;margin:6px 0;font-size:11pt;}
.fl{white-space:nowrap;}
.fval{flex:1;border-bottom:1px solid #444;min-height:1.25em;padding-left:4px;padding-bottom:1px;}
.fval.fv{font-weight:600;border-bottom-color:#111;}
.fval-half{width:180px;flex:none;border-bottom:1px solid #444;min-height:1.25em;padding-left:4px;}
.fval-half.fv{font-weight:600;border-bottom-color:#111;}
.cr{display:flex;align-items:center;flex-wrap:wrap;gap:6px 18px;margin:6px 0;font-size:11pt;}
.ci{display:inline-flex;align-items:center;gap:5px;}
.ci.bck{font-weight:700;}
.sec{margin:12px 0 4px;font-size:11.5pt;}
.sec span{text-decoration:underline;font-weight:700;}
.cl{margin:4px 0 4px 22px;}
.cli{display:flex;align-items:flex-start;gap:6px;margin:4px 0;font-size:11pt;}
.cli.bck{font-weight:700;}
.c2{margin:4px 0 4px 22px;display:grid;grid-template-columns:1fr 1fr;gap:3px 10px;}
.ar{margin:4px 0 4px 22px;display:flex;align-items:flex-end;gap:5px;font-size:11pt;}
.ar .fval{flex:1;}
.xl{margin:3px 0 3px 22px;border-bottom:1px solid #444;min-height:1.2em;}
.obs-t{margin:12px 0 4px;font-weight:700;font-size:11.5pt;text-decoration:underline;}
.obs-q{margin:4px 0;font-size:11pt;}
.merci{margin:14px 0 4px;font-style:italic;font-size:11pt;}
.footer-doc{margin-top:14px;border-top:1.5px solid #111;padding-top:4px;text-align:center;font-size:9.5pt;}
</style>
</head>
<body>
<div class="no-print">
    <button class="primary" onclick="var t=document.title;document.title=' ';window.print();document.title=t;">🖨️ Imprimer</button>
    <a href="demandes_inscription.php">← Retour</a>
    <?php if($filled): ?>
    <span style="background:#e8f5e9;border:1px solid #4caf50;padding:.3rem .7rem;border-radius:8px;font-size:.78rem;color:#2e7d32;">✅ <?= htmlspecialchars(($d['prenom']??'').' '.($d['nom']??'')) ?></span>
    <?php endif; ?>
</div>
<div class="doc">

<?php if($filled): ?>
<div class="banner no-print">✅ Fiche de pré-inscription remplie — soumise le <?= $fmtDate((string)($d['date_soumission']??'')) ?></div>
<?php endif; ?>

<div class="lh">
    <div class="lh-logo"><img src="assets/img/logo.png" alt="" onerror="this.style.display='none'"></div>
    <div class="lh-mid">
        <div class="org"><?= htmlspecialchars($SCHOOL_ORG) ?></div>
        <div class="tag"><?= htmlspecialchars($SCHOOL_TAG1) ?><br><?= htmlspecialchars($SCHOOL_TAG2) ?></div>
        <div class="auth"><?= htmlspecialchars($SCHOOL_AUTH1) ?><br><?= htmlspecialchars($SCHOOL_AUTH2) ?></div>
    </div>
    <div class="stamp-wrap"><div class="stamp"><div class="st">ACCRÉDITÉ</div><div class="ss">FORMATION<br>PROFESSIONNELLE<br>PRIVÉE</div></div></div>
</div>

<div class="doc-title"><div class="doc-title-box"><div class="doc-title-txt">Fiche &nbsp; de &nbsp; Preinscription</div></div></div>

<div class="date-right"><strong>Date Visite :</strong> <span class="dv<?= $filled?' fv':'' ?>">&nbsp;<?= $fmtDate((string)($d['date_soumission']??'')) ?>&nbsp;</span></div>

<!-- ══ IDENTITY FIELDS — ALL AT TOP ══ -->
<div class="fr"><span class="fl">Nom et Prénom :</span><span class="fval<?= $filled?' fv':'' ?>"><?= $filled ? htmlspecialchars(trim(($d['prenom']??'').' '.($d['nom']??''))) : '' ?></span></div>
<div class="fr"><span class="fl">CIN :</span><span class="fval<?= ($filled&&!empty($d['cin']))?' fv':'' ?>"><?= $e('cin') ?></span></div>
<div class="fr"><span class="fl">Date de naissance :</span><span class="fval-half<?= ($filled&&!empty($d['date_naissance']))?' fv':'' ?>"><?= $fmtDate((string)($d['date_naissance']??'')) ?></span></div>
<div class="fr">
    <span class="fl">Tél. :</span><span class="fval<?= ($filled&&!empty($d['telephone']))?' fv':'' ?>"><?= $e('telephone') ?></span>
    &nbsp;&nbsp;
    <span class="fl">Tél. Parent :</span><span class="fval<?= ($filled&&!empty($d['telephone_parent']))?' fv':'' ?>"><?= $e('telephone_parent') ?></span>
</div>
<div class="fr"><span class="fl">Adresse :</span><span class="fval<?= ($filled&&!empty($d['adresse']))?' fv':'' ?>"><?= $e('adresse') ?></span></div>
<div class="fr"><span class="fl">Email :</span><span class="fval<?= ($filled&&!empty($d['email']))?' fv':'' ?>"><?= $e('email') ?></span></div>
<div class="fr"><span class="fl">Nom du père / tuteur :</span><span class="fval<?= ($filled&&!empty($d['nom_tuteur']))?' fv':'' ?>"><?= $e('nom_tuteur') ?></span></div>

<!-- SEXE -->
<div class="cr">
    <span>Sexe :</span>
    <span class="ci<?= ($savedSexe==='F')?' bck':'' ?>"><?= radio($savedSexe==='F') ?> Féminin</span>
    <span class="ci<?= ($savedSexe==='M')?' bck':'' ?>"><?= radio($savedSexe==='M') ?> Masculin</span>
</div>

<!-- NIVEAU -->
<div class="cr">
    <span>Niveau :</span>
    <?php foreach(['Licence','Bac +2','Technicien','Bachelier(e)'] as $nv): $nc=$inArr($savedNiveaux,$nv); ?>
    <span class="ci<?= $nc?' bck':'' ?>"><?= chk($nc) ?> <?= htmlspecialchars($nv) ?></span>
    <?php endforeach; ?>
</div>
<div class="cr" style="margin-left:52px;">
    <?php foreach(['9ème AF','Tronc Commun','1ère Bac','2ème Bac'] as $nv): $nc=$inArr($savedNiveaux,$nv); ?>
    <span class="ci<?= $nc?' bck':'' ?>"><?= chk($nc) ?> <?= htmlspecialchars($nv) ?></span>
    <?php endforeach; ?>
</div>

<!-- DIPLÔME -->
<div class="sec"><span>➤ </span><span>Diplôme :</span></div>
<div class="cl">
    <?php foreach($dipOptions as $code => $label): $ck=in_array($code,$checkedDipCodes,true); ?>
    <div class="cli<?= $ck?' bck':'' ?>"><?= chk($ck) ?> <span><?= htmlspecialchars($label) ?></span></div>
    <?php endforeach; ?>
</div>

<!-- LICENCE -->
<div class="sec"><span>➤ </span><span>Licence professionnelle :</span></div>
<div class="cl">
    <?php foreach(['MANAGEMENT ET RESSOURCE HUMAINE','FINANCE ET COMPTABILITE','LOGISTIQUE INTERNATIONALE','INFORMATIQUE'] as $l): ?>
    <div class="cli"><?= chkEmpty() ?> <span><?= htmlspecialchars($l) ?> :</span></div>
    <?php endforeach; ?>
</div>

<!-- FORMATION CONTINUE -->
<div class="sec"><span>➤ </span><span>Formation continue : (Attestation)</span></div>
<div class="c2">
    <?php foreach(['Bureautique','Programmation','Comptabilité','Réseau'] as $fo): $fc=$inArr($savedFormations,$fo); ?>
    <div class="cli<?= $fc?' bck':'' ?>"><?= chk($fc) ?> <span><?= htmlspecialchars($fo) ?></span></div>
    <?php endforeach; ?>
</div>
<div class="ar"><?php $hasAF = $filled && !empty($d['autre_formation'] ?? ''); ?><span><?= chk($hasAF) ?> Autre :</span><span class="fval<?= $hasAF?' fv':'' ?>"><?= $filled ? htmlspecialchars((string)($d['autre_formation'] ?? '')) : '' ?></span></div>
<div class="xl"></div><div class="xl"></div>

<!-- OBSERVATION -->
<div class="obs-t">Observation Particulière :</div>
<div class="obs-q">Comment avez-vous connu notre Etablissement ?</div>
<div class="cr">
    <?php foreach(['Publicité','Relation'] as $s): $sc=$inArr($savedSources,$s); ?>
    <span class="ci<?= $sc?' bck':'' ?>"><?= chk($sc) ?> <?= htmlspecialchars($s) ?></span>
    <span style="width:30px;display:inline-block;"></span>
    <?php endforeach; ?>
</div>
<div class="ar"><?php $hasSA = $filled && !empty($d['source_autre'] ?? ''); ?><span><?= chk($hasSA) ?> Autre :</span><span class="fval<?= $hasSA?' fv':'' ?>"><?= $filled ? htmlspecialchars((string)($d['source_autre'] ?? '')) : '' ?></span></div>
<div class="xl"></div>

<div class="merci">Merci de nous rendre visite.</div>
<div class="footer-doc"><?= htmlspecialchars($SCHOOL_ADDR) ?></div>
</div>

<?php if($auto): ?><script>window.addEventListener('load',function(){setTimeout(function(){var t=document.title;document.title=' ';window.print();document.title=t;},400);});</script><?php endif; ?>
</body>
</html>
