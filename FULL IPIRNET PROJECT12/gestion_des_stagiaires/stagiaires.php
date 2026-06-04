<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$listMoisNav = date('Y-m');
if (isset($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['mois'])) {
    $listMoisNav = (string) $_GET['mois'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX Inline Note Saving
    if (isset($_POST['action']) && $_POST['action'] === 'save_inline_note') {
        header('Content-Type: application/json');
        try {
            $sid = (int)($_POST['sid'] ?? 0);
            $mid = (int)($_POST['mid'] ?? 0);
            $field = (string)($_POST['field'] ?? '');
            $val = trim((string)($_POST['val'] ?? ''));
            $val = ($val === '') ? null : (float)str_replace(',', '.', $val);
            
            $allowed = ['note_controle', 'note_theorique', 'note_pratique'];
            if ($sid > 0 && $mid > 0 && in_array($field, $allowed)) {
                $st = $pdo->prepare("INSERT INTO module_notes (id_stagiaire, id_module, $field) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)");
                $st->execute([$sid, $mid, $val]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid params']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if (isset($_POST['toggle_mensualite'])) {
        $sid = (int) ($_POST['id_stagiaire'] ?? 0);
        $mois = (string) ($_POST['mois_ref'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) $mois = $listMoisNav;
        if ($sid > 0) {
            $toPaid = isset($_POST['to_paid']) && (string) ($_POST['to_paid'] ?? '') === '1';
            $pdo->prepare('INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, marque_le) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE est_paye = VALUES(est_paye), marque_le = NOW()')->execute([$sid, $mois, $toPaid ? 1 : 0]);
            flash_set($toPaid ? "Cotisation payée ($mois)." : "Cotisation impayée ($mois).");
        }
        redirect('stagiaires.php?mois=' . urlencode($mois));
    }
    if (isset($_POST['delete_id'])) {
        $pdo->prepare('DELETE FROM stagiaires WHERE id_stagiaire = ?')->execute([(int) $_POST['delete_id']]);
        flash_set('Stagiaire supprimé (lignes liées en cascade).');
        redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
    }
    if (isset($_POST['save'])) {
        $mat = trim((string) ($_POST['num_inscri'] ?? ''));
        $cin = trim((string) ($_POST['cin'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $dn = ($_POST['date_naissance'] ?? '') === '' ? null : (string) $_POST['date_naissance'];
        $adr = trim((string) ($_POST['adresse'] ?? ''));
        $em = trim((string) ($_POST['email'] ?? ''));
        $emNull = $em === '' ? null : $em;
        $tel = trim((string) ($_POST['telephone'] ?? ''));
        $pw = (string) ($_POST['mot_de_passe'] ?? '');
        $photo = trim((string) ($_POST['photo'] ?? ''));
        $di = (string) ($_POST['date_inscription'] ?? '');
        $cid = (int) ($_POST['id_classe'] ?? 0);
        
        $errs = [];
        if ($nom === '' || $prenom === '' || $di === '' || $cid <= 0) $errs[] = 'Nom, prénom, date inscription et classe requis';
        if (preg_match('/[0-9]/', $nom) || preg_match('/[0-9]/', $prenom)) $errs[] = 'nom/prénom sans chiffres';
        if ($cin !== '' && !preg_match('/^[a-zA-Z]{2}[0-9]/', $cin)) $errs[] = 'CIN format 2 lettres + chiffres';
        if ($tel !== '' && preg_match('/[a-zA-ZÀ-ÿ]/', $tel)) $errs[] = 'téléphone sans lettres';
        if ($photo !== '' && !preg_match('/\.(png|jpg|jpeg|gif)$/i', $photo)) $errs[] = 'photo URL (doit finir par .png ou .jpg)';
        
        if ($errs) {
            flash_set('Erreur : ' . implode(', ', $errs) . '.');
            redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
        }

        $pwHash = $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null;

        if (isset($_POST['id_stagiaire']) && (int) $_POST['id_stagiaire'] > 0) {
            $id = (int) $_POST['id_stagiaire'];
            if ($mat === '') {
                $cur = $pdo->prepare('SELECT num_inscri FROM stagiaires WHERE id_stagiaire = ?');
                $cur->execute([$id]);
                $mat = (string) ($cur->fetchColumn() ?: ''); // Fallback
            }
            $sql = 'UPDATE stagiaires SET num_inscri=?, cin=?, nom=?, prenom=?, date_naissance=?, adresse=?, email=?, telephone=?, photo=?, date_inscription=?, id_classe=?';
            $params = [$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $photo === '' ? null : $photo, $di, $cid];
            if ($pwHash) {
                $sql .= ', mot_de_passe=? WHERE id_stagiaire=?';
                $params[] = $pwHash; $params[] = $id;
            } else {
                $sql .= ' WHERE id_stagiaire=?';
                $params[] = $id;
            }
            $pdo->prepare($sql)->execute($params);
            flash_set('Stagiaire mis à jour.');
        } else {
            if ($mat === '') {
                $year = date('Y', strtotime($di));
                $st = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE num_inscri LIKE ?");
                $st->execute(['INS-' . $year . '-%']);
                $count = (int) $st->fetchColumn();
                $mat = 'INS-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
            }
            $hash = $pwHash ?? password_hash('changeme', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO stagiaires (num_inscri, cin, nom, prenom, date_naissance, adresse, email, telephone, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $hash, $photo === '' ? null : $photo, $di, $cid]);
            flash_set('Stagiaire créé avec succès (N° Inscription: ' . $mat . ').');
        }
    }
    
    // QUICK SAVE ABSENCE
    if (isset($_POST['quick_save_absence'])) {
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $da = (string)($_POST['date_absence'] ?? '');
        $hd = ($_POST['heure_debut'] ?? '') === '' ? null : (string)$_POST['heure_debut'];
        $hf = ($_POST['heure_fin'] ?? '') === '' ? null : (string)$_POST['heure_fin'];
        $ju = trim((string)($_POST['justificatif'] ?? ''));
        $ej = isset($_POST['est_justifiee']) ? 1 : 0;
        $mid = ($_POST['id_module'] ?? '') === '' ? null : (int)$_POST['id_module'];
        
        if ($sid > 0 && $da !== '') {
            $pdo->prepare('INSERT INTO absences (date_absence, heure_debut, heure_fin, justificatif, est_justifiee, id_stagiaire, id_module) VALUES (?,?,?,?,?,?,?)')
                ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid]);
            flash_set('Absence enregistrée avec succès.');
        }
        redirect('stagiaires.php?id=' . $sid);
    }

    // QUICK SAVE NOTE
    if (isset($_POST['quick_save_note'])) {
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $mid = (int)($_POST['id_module'] ?? 0);
        $getF = function($v) { $v = trim((string)$v); return $v === '' ? null : (float)str_replace(',', '.', $v); };
        $nc = $getF($_POST['note_controle'] ?? '');
        $nt = $getF($_POST['note_theorique'] ?? '');
        $np = $getF($_POST['note_pratique'] ?? '');
        
        if ($sid > 0 && $mid > 0) {
            $pdo->prepare('INSERT INTO module_notes (id_stagiaire, id_module, note_controle, note_theorique, note_pratique) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE note_controle=VALUES(note_controle), note_theorique=VALUES(note_theorique), note_pratique=VALUES(note_pratique)')
                ->execute([$sid, $mid, $nc, $nt, $np]);
            flash_set('Notice de notes mise à jour.');
        }
        redirect('stagiaires.php?id=' . $sid);
    }

    // QUICK SAVE STAGE
    if (isset($_POST['quick_save_stage'])) {
        $sid  = (int)($_POST['id_stagiaire'] ?? 0);
        $ts   = (string)($_POST['type_stage'] ?? 'stage_entreprise');
        if (!in_array($ts, ['stage_entreprise', 'pfe'], true)) $ts = 'stage_entreprise';
        $su   = trim((string)($_POST['sujet'] ?? ''));
        $en   = trim((string)($_POST['entreprise'] ?? ''));
        $dd   = ($_POST['date_debut'] ?? '') === '' ? null : (string)$_POST['date_debut'];
        $df   = ($_POST['date_fin']   ?? '') === '' ? null : (string)$_POST['date_fin'];
        $ns   = ($_POST['note_stage'] ?? '') === '' ? null : (float)str_replace(',', '.', (string)$_POST['note_stage']);
        $cu   = trim((string)($_POST['convention_url'] ?? ''));
        $ru   = trim((string)($_POST['rapport_url'] ?? ''));
        $ev   = trim((string)($_POST['evaluation_entreprise'] ?? ''));
        $ds   = ($_POST['date_soutenance'] ?? '') === '' ? null : (string)$_POST['date_soutenance'];
        $ju   = trim((string)($_POST['jury'] ?? ''));
        if ($sid > 0) {
            $stg_id = (int)($_POST['id_stage'] ?? 0);
            if ($stg_id > 0) {
                $pdo->prepare('UPDATE stages SET type_stage=?,sujet=?,entreprise=?,date_debut=?,date_fin=?,note_stage=?,convention_url=?,rapport_url=?,evaluation_entreprise=?,date_soutenance=?,jury=? WHERE id_stage=? AND id_stagiaire=?')
                    ->execute([$ts,$su===''?null:$su,$en===''?null:$en,$dd,$df,$ns,$cu===''?null:$cu,$ru===''?null:$ru,$ev===''?null:$ev,$ds,$ju===''?null:$ju,$stg_id,$sid]);
                flash_set('Stage mis à jour.');
            } else {
                $pdo->prepare('INSERT INTO stages (type_stage,sujet,entreprise,date_debut,date_fin,note_stage,convention_url,rapport_url,evaluation_entreprise,date_soutenance,jury,id_stagiaire) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$ts,$su===''?null:$su,$en===''?null:$en,$dd,$df,$ns,$cu===''?null:$cu,$ru===''?null:$ru,$ev===''?null:$ev,$ds,$ju===''?null:$ju,$sid]);
                flash_set('Stage ajouté avec succès.');
            }
        }
        redirect('stagiaires.php?id=' . $sid . '&hub_tab=hub-stages');
    }

    // QUICK DELETE STAGE
    if (isset($_POST['quick_delete_stage'])) {
        $sid    = (int)($_POST['id_stagiaire'] ?? 0);
        $stg_id = (int)($_POST['id_stage'] ?? 0);
        if ($sid > 0 && $stg_id > 0) {
            $pdo->prepare('DELETE FROM stages WHERE id_stage = ? AND id_stagiaire = ?')->execute([$stg_id, $sid]);
            flash_set('Stage supprimé.');
        }
        redirect('stagiaires.php?id=' . $sid . '&hub_tab=hub-stages');
    }

    // QUICK DELETE ABSENCE
    if (isset($_POST['quick_delete_absence'])) {
        $sid    = (int)($_POST['id_stagiaire'] ?? 0);
        $abs_id = (int)($_POST['id_absence'] ?? 0);
        if ($sid > 0 && $abs_id > 0) {
            $pdo->prepare('DELETE FROM absences WHERE id_absence = ? AND id_stagiaire = ?')->execute([$abs_id, $sid]);
            flash_set('Absence supprimée.');
        }
        redirect('stagiaires.php?id=' . $sid . '&hub_tab=hub-absences');
    }

    // QUICK DELETE NOTE
    if (isset($_POST['quick_delete_note'])) {
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $mid = (int)($_POST['id_module'] ?? 0);
        if ($sid > 0 && $mid > 0) {
            $pdo->prepare('DELETE FROM module_notes WHERE id_stagiaire = ? AND id_module = ?')->execute([$sid, $mid]);
            flash_set('Notes supprimées.');
        }
        redirect('stagiaires.php?id=' . $sid . '&hub_tab=hub-bulletin');
    }

    redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
}

$curPage = 'stagiaires';
$pageTitle = 'Stagiaires';
require __DIR__ . '/includes/header.php';
?>
<style>
/* HUB V2 STYLES */
.hub-nav-stack { margin-bottom: 2rem; display: flex; justify-content: center; }
.hub-nav-group-v2 { 
    display: flex; gap: 10px; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.05); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
.nav-v2-btn { 
    width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #a1a1aa; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; text-decoration: none; 
}
.nav-v2-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: scale(1.1); }
.nav-v2-btn.close-btn { width: auto; padding: 0 25px; border-radius: 25px; font-weight: 700; font-size: 0.75rem; letter-spacing: 1px; color: #fff; background: rgba(255,255,255,0.05); }
.nav-v2-btn.close-btn:hover { background: #fff; color: #000; }

.hub-header { margin-bottom: 3rem; text-align: center; }
.hub-avatar-ring { 
    width: 140px; height: 140px; margin: 0 auto 1.5rem; border-radius: 50%; padding: 6px; background: linear-gradient(135deg, var(--primary), #a855f7); position: relative; box-shadow: 0 0 40px rgba(59, 130, 246, 0.3);
}
.hub-photo, .hub-photo-placeholder { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #09090b; }
.hub-photo-placeholder { display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: #fff; }

.hub-name-display { font-family: 'Instrument Serif', serif; font-size: 3.5rem; color: #fff; letter-spacing: -2px; margin-bottom: 1rem; }
.hub-meta-badges { display: flex; justify-content: center; gap: 15px; margin-bottom: 2rem; }
.hub-badge { background: rgba(255,255,255,0.05); padding: 6px 15px; border-radius: 30px; font-size: 0.85rem; color: #a1a1aa; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.03); }

.hub-actions-bar { display: flex; justify-content: center; gap: 15px; }
.btn-hub-action { padding: 12px 25px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; text-decoration: none; border: none; }
.btn-hub-action.primary { background: #fff; color: #000; }
.btn-hub-action.secondary { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
.btn-hub-action.small { padding: 8px 15px; font-size: 0.8rem; }

.hub-tabs { display: flex; justify-content: center; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 2rem; gap: 40px; }
.hub-tab-btn { background: none; border: none; padding: 15px 0; color: #71717a; font-weight: 600; cursor: pointer; position: relative; transition: 0.3s; font-size: 1rem; }
.hub-tab-btn.active { color: #fff; }
.hub-tab-btn.active::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 2px; background: var(--primary); box-shadow: 0 0 10px var(--primary); }

.hub-tab-pane { display: none; }
.hub-tab-pane.active { display: block; animation: fadeIn 0.4s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.hub-dashboard-grid-v2 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 2rem; }
.mini-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 18px; display: flex; align-items: center; gap: 20px; cursor: pointer; transition: 0.3s; }
.mini-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.15); }
.mini-card-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.mini-card.red .mini-card-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.mini-card.blue .mini-card-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.mini-card.green .mini-card-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.mini-card-info .label { display: block; font-size: 0.75rem; color: #71717a; text-transform: uppercase; letter-spacing: 1px; }
.mini-card-info .value { font-size: 1.4rem; font-weight: 700; color: #fff; }

.overview-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.info-list { list-style: none; padding: 0; margin-top: 1rem; }
.info-list li { padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; align-items: center; gap: 15px; color: #d4d4d8; font-size: 0.95rem; }
.info-list li i { color: var(--primary); width: 20px; }

.detailed-tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.detailed-tab-header h2 { font-family: 'Instrument Serif', serif; font-size: 1.8rem; color: #fff; }

.detail-table-card { padding: 0; overflow: hidden; border-radius: 15px; }
.detail-table { width: 100%; border-collapse: collapse; }
.detail-table th { background: rgba(255,255,255,0.02); padding: 15px; text-align: left; color: #71717a; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.detail-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); color: #e4e4e7; font-size: 0.95rem; }

.badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
.badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }

.inline-note-input { background: rgba(0,0,0,0.3); border: 1px solid transparent; color: #fff; width: 60px; padding: 5px; text-align: center; border-radius: 6px; transition: 0.3s; }
.inline-note-input:focus { outline: none; border-color: var(--primary); background: rgba(59, 130, 246, 0.1); }
.inline-note-input.saving { opacity: 0.5; pointer-events: none; }
.inline-note-input.success { border-color: #10b981; background: rgba(16, 185, 129, 0.13); }

.documents-grid-v2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.doc-v2-link { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; text-decoration: none; display: flex; flex-direction: column; align-items: center; transition: 0.3s; }
.doc-v2-link:hover { transform: scale(1.05); background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.15); }
.doc-v2-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px; }
.doc-v2-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.doc-v2-icon.purple { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
.doc-v2-icon.yellow { background: rgba(250, 204, 21, 0.1); color: #facc15; }
.doc-v2-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.doc-v2-icon.teal { background: rgba(20, 184, 166, 0.1); color: #14b8a6; }
.doc-v2-icon.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.doc-v2-icon.red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.doc-v2-icon.pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
.doc-v2-link span { color: #a1a1aa; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
/* HUB V3 SLIM UI - CENTERED */
.hub-header-v3 { margin-bottom: 2.5rem; padding-top: 1rem; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 2rem; text-align: center; }
.hub-identity-v3 { display: flex; flex-direction: column; align-items: center; max-width: 1300px; margin: 0 auto; gap: 20px; }
.hub-title-group { display: flex; flex-direction: column; align-items: center; }
.hub-name-v3 { font-family: 'Instrument Serif', serif; font-size: 3.5rem; color: #fff; letter-spacing: -2px; margin: 0 0 1rem 0; line-height: 1; }
.hub-meta-v3 { display: flex; gap: 12px; justify-content: center; }
.v3-badge { background: rgba(255,255,255,0.04); padding: 5px 12px; border-radius: 8px; font-size: 0.75rem; color: #a1a1aa; display: flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.03); font-weight: 600; white-space: nowrap; }

.hub-actions-v3 { display: flex; gap: 10px; justify-content: center; margin-top: 5px; }
.v3-btn { padding: 8px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; text-decoration: none; border: none; height: 40px; }
.v3-btn.secondary { background: #fff; color: #000; box-shadow: 0 4px 15px rgba(255,255,255,0.1); }
.v3-btn.secondary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,255,255,0.2); }
.v3-btn.ghost { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.08); }
.v3-btn.ghost:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }

.overview-details-v3 { max-width: 900px; margin: 0 auto; }
.v3-info-card { background: #fff; border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.25); }
.v3-card-header { padding: 22px 30px; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
.v3-card-header h3 { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; margin: 0; }
.v3-info-list { list-style: none; padding: 10px 20px 20px; margin: 0; }
.v3-info-list li { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 16px; color: #334155; font-size: 0.95rem; border-radius: 10px; transition: background 0.2s ease; }
.v3-info-list li:last-child { border-bottom: none; }
.v3-info-list li:hover { background: #f8fafc; }
.v3-info-list li strong { color: #1e293b; font-weight: 700; min-width: 100px; }
.v3-info-list li .info-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.v3-info-list li .info-icon.blue { background: rgba(59,130,246,0.1); color: #3b82f6; }
.v3-info-list li .info-icon.purple { background: rgba(139,92,246,0.1); color: #8b5cf6; }
.v3-info-list li .info-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.v3-info-list li .info-icon.amber { background: rgba(245,158,11,0.1); color: #f59e0b; }
.v3-info-list li .info-icon.rose { background: rgba(244,63,94,0.1); color: #f43f5e; }

/* DATA V2 - HIGH CONTRAST (BLACK TEXT) */
.data-v2 { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.data-v2 thead th { background: #f4f4f5; color: #18181b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px; font-weight: 800; border-bottom: 2px solid #e4e4e7; }
.data-v2 tbody td { padding: 16px 20px; border-bottom: 1px solid #f4f4f5; color: #000; font-size: 0.95rem; font-weight: 500; }
.data-v2 tbody tr:last-child td { border-bottom: none; }
.data-v2 tbody tr { transition: background 0.2s ease; cursor: default; background: #fff; }
.data-v2 tbody tr:hover { background: #f9fafb; }
.data-v2 tbody tr:hover td { color: #000; } 

.static-note { background: #f1f5f9; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; color: #000; font-weight: 700; min-width: 60px; display: inline-block; text-align: center; }
.detailed-tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.detailed-tab-header h2 { font-family: 'Instrument Serif', serif; font-size: 1.8rem; color: #fff; margin: 0; }
</style>
<?php

$classes = $pdo->query('SELECT c.id_classe, c.nom_classe, c.annee_scolaire, f.id_filiere, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY f.nom_filiere, c.annee_scolaire, c.nom_classe')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stagiaires WHERE id_stagiaire = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$filieresList = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$navFiliere = (int) ($_GET['f'] ?? 0);
$navClasse  = (int) ($_GET['c'] ?? 0);
$navSearch  = (string) ($_GET['s'] ?? '');
$navSort    = (string) ($_GET['o'] ?? 'nom');

$sqlNav = "SELECT v.*, c.id_filiere, s.date_naissance, s.adresse, s.photo, s.email, s.telephone, s.date_inscription, s.cin 
           FROM v_stagiaires_detail v 
           JOIN classes c ON c.id_classe = v.id_classe 
           LEFT JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire";
$whereNav = [];
$paramsNav = [];
if ($navFiliere > 0) { $whereNav[] = "c.id_filiere = ?"; $paramsNav[] = $navFiliere; }
if ($navClasse > 0)  { $whereNav[] = "v.id_classe = ?"; $paramsNav[] = $navClasse; }
if ($navSearch !== '') {
    $whereNav[] = "(v.nom LIKE ? OR v.prenom LIKE ? OR v.num_inscri LIKE ? OR s.cin LIKE ?)";
    $sterm = "%$navSearch%";
    $paramsNav = array_merge($paramsNav, [$sterm, $sterm, $sterm, $sterm]);
}

if ($whereNav) $sqlNav .= " WHERE " . implode(" AND ", $whereNav);
$sqlNav .= " ORDER BY " . ($navSort === 'num_inscri' ? 'v.num_inscri' : 'v.nom, v.prenom');

$stNav = $pdo->prepare($sqlNav);
$stNav->execute($paramsNav);
$rows = $stNav->fetchAll();

// Function to generate Avatar colors pseudo-randomly based on string
function getAvatarColor($str) {
    $hash = md5($str);
    return '#' . substr($hash, 0, 6);
}

// Student Hub Navigation & Detail Logic
$selectedStudent = null;
$prevStudent = null;
$nextStudent = null;

// Build filter params to carry URL params across hub navigation
$filterParams = '';
if (isset($_GET['f'])) $filterParams .= '&f=' . urlencode($_GET['f']);
if (isset($_GET['c'])) $filterParams .= '&c=' . urlencode($_GET['c']);
if (isset($_GET['s'])) $filterParams .= '&s=' . urlencode($_GET['s']);
if (isset($_GET['o'])) $filterParams .= '&o=' . urlencode($_GET['o']);

if (isset($_GET['id'])) {
    $targetId = (int) $_GET['id'];
    
    // FETCH SELECTED STUDENT (Independent of Nav Filters)
    $stSel = $pdo->prepare("SELECT v.*, c.id_filiere, s.date_naissance, s.adresse, s.photo, s.email, s.telephone, s.date_inscription, s.cin 
                            FROM v_stagiaires_detail v 
                            JOIN classes c ON c.id_classe = v.id_classe 
                            LEFT JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire
                            WHERE v.id_stagiaire = ?");
    $stSel->execute([$targetId]);
    $selectedStudent = $stSel->fetch();

    foreach ($rows as $index => $r) {
        if ((int)$r['id_stagiaire'] === $targetId) {
            $prevStudent = $rows[$index - 1] ?? null;
            $nextStudent = $rows[$index + 1] ?? null;
            break;
        }
    }

    if ($selectedStudent) {
        // FETCH DATA FOR HUB
        $stAbs = $pdo->prepare("SELECT a.*, m.nom_module FROM absences a LEFT JOIN modules m ON m.id_module = a.id_module WHERE a.id_stagiaire = ? ORDER BY a.date_absence DESC, a.heure_debut DESC");
        $stAbs->execute([$targetId]);
        $absences = $stAbs->fetchAll();
        
        $stNotes = $pdo->prepare("SELECT n.*, m.nom_module FROM module_notes n JOIN modules m ON m.id_module = n.id_module WHERE n.id_stagiaire = ? ORDER BY m.nom_module");
        $stNotes->execute([$targetId]);
        $notes = $stNotes->fetchAll();

        $stMod = $pdo->prepare('SELECT id_module, nom_module FROM modules WHERE id_filiere = ? ORDER BY nom_module');
        $stMod->execute([(int)$selectedStudent['id_filiere']]);
        $mods = $stMod->fetchAll();

        // FETCH STAGES for hub
        $stStages = $pdo->prepare('SELECT * FROM stages WHERE id_stagiaire = ? ORDER BY date_debut DESC');
        $stStages->execute([$targetId]);
        $hubStages = $stStages->fetchAll();

        // FETCH DOCUMENTS HISTORY for hub
        $stDocHist = $pdo->prepare('SELECT id_gen, type_document, reference, genere_le FROM documents_generes WHERE id_stagiaire = ? ORDER BY genere_le DESC LIMIT 15');
        $stDocHist->execute([$targetId]);
        $hubDocHistory = $stDocHist->fetchAll();

        // ACTIVE HUB TAB (from redirect after save)
        $activeHubTab = isset($_GET['hub_tab']) ? preg_replace('/[^a-z\-]/', '', (string)$_GET['hub_tab']) : 'hub-overview';
        $validHubTabs = ['hub-overview','hub-absences','hub-bulletin','hub-stages','hub-docs'];
        if (!in_array($activeHubTab, $validHubTabs)) $activeHubTab = 'hub-overview';
    }
}

// HUB LOGIC CONTINUED...
?>

<!-- MODAL SYSTEM -->
<div id="modal-overlay" class="modal-overlay" style="display:<?= $edit ? 'flex' : 'none' ?>; z-index:9999;">
    <div class="modal-card">
        <div class="modal-header">
            <h2 id="modal-title-heading"><?= $edit ? 'Modifier' : 'Ajouter' ?> un stagiaire</h2>
            <button type="button" class="icon-btn js-close-modal" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="post" class="modal-form" action="stagiaires.php" id="stagiaire-form">
                <input type="hidden" name="id_stagiaire" id="form-id-stagiaire" value="<?= $edit ? (int)$edit['id_stagiaire'] : '' ?>">
                
                <div class="modal-section-grid">
                    <fieldset class="modal-fieldset">
                        <legend><i class="fa-solid fa-id-card"></i> Identité</legend>
                        <div class="avatar-preview-container">
                            <img id="avatar-preview" src="<?= h((string) ($edit['photo'] ?? '')) ?>" style="display:<?= empty($edit['photo']) ? 'none' : 'block' ?>;">
                            <div id="avatar-initials" class="avatar-initials" style="display:<?= empty($edit['photo']) ? 'flex' : 'none' ?>;"><i class="fa-solid fa-user"></i></div>
                        </div>
                        <label>Nom * <input type="text" name="nom" required value="<?= h((string) ($edit['nom'] ?? '')) ?>"></label>
                        <label>Prénom * <input type="text" name="prenom" required value="<?= h((string) ($edit['prenom'] ?? '')) ?>"></label>
                        <label>N° Inscription <input type="text" name="num_inscri" placeholder="Auto si vide" value="<?= h((string) ($edit['num_inscri'] ?? '')) ?>"></label>
                        <label>CIN <input type="text" name="cin" placeholder="WA123456" value="<?= h((string) ($edit['cin'] ?? '')) ?>"></label>
                        <label>Photo URL <input type="text" name="photo" id="form-photo" value="<?= h((string) ($edit['photo'] ?? '')) ?>"></label>
                        <label>Date naissance <input type="date" name="date_naissance" value="<?= h((string) ($edit['date_naissance'] ?? '')) ?>"></label>
                    </fieldset>

                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-address-book"></i> Contact</legend>
                            <label>Email <input type="email" name="email" value="<?= h((string) ($edit['email'] ?? '')) ?>"></label>
                            <label>Téléphone <input type="tel" name="telephone" value="<?= h((string) ($edit['telephone'] ?? '')) ?>"></label>
                            <label style="grid-column: span 2;">Adresse <input type="text" name="adresse" value="<?= h((string) ($edit['adresse'] ?? '')) ?>"></label>
                        </fieldset>

                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-graduation-cap"></i> Scolarité</legend>
                            <label>Date inscription * <input type="date" name="date_inscription" required value="<?= h((string) ($edit['date_inscription'] ?? date('Y-m-d'))) ?>"></label>
                            <label>Filière
                                <select id="form-filiere-select">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($filieresList as $fp): ?>
                                        <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fp['nom_filiere'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label style="grid-column: span 2">Classe *
                                <select name="id_classe" id="form-classe-select" required
                                    data-all-classes='<?= htmlspecialchars(json_encode(array_map(fn($c) => ['id' => (int)$c['id_classe'], 'fid' => (int)$c['id_filiere'], 'label' => $c['nom_classe'] . ' — ' . $c['annee_scolaire'], 'selected' => ($edit && (int)$edit['id_classe'] === (int)$c['id_classe'])], $classes)), ENT_QUOTES) ?>'>
                                    <option value="">— Choisir classe —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= (int) $c['id_classe'] ?>" data-filiere="<?= (int) $c['id_filiere'] ?>" <?= ($edit && (int)$edit['id_classe'] === (int)$c['id_classe']) ? 'selected' : '' ?>>
                                            <?= h($c['nom_classe'] . ' — ' . $c['annee_scolaire']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label style="grid-column: span 2">Mot de passe <input name="mot_de_passe" type="password" placeholder="Laissez vide pour garder l'actuel"></label>
                        </fieldset>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn secondary js-close-modal">Annuler</button>
                    <button type="submit" name="save" id="form-submit-btn" value="1" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($selectedStudent): ?>
    <script>
    function triggerModifierHub() {
        const s = <?= json_encode($selectedStudent) ?>;
        const modal = document.getElementById('modal-overlay');
        const form = document.getElementById('stagiaire-form');
        if (!modal || !form || !s) return;

        form.reset();
        document.getElementById('form-id-stagiaire').value = s.id_stagiaire;

        const fields = ['nom', 'prenom', 'num_inscri', 'cin', 'photo', 'date_naissance', 'email', 'telephone', 'adresse', 'date_inscription'];
        fields.forEach(f => {
            const el = form.querySelector('[name="' + f + '"]');
            if (el) el.value = s[f] || '';
        });

        const cSelect = document.getElementById('form-classe-select');
        const fSelect = document.getElementById('form-filiere-select');
        
        if (cSelect && fSelect) {
            // 1. Set the Filiere first
            const classOpt = cSelect.querySelector('option[value="' + s.id_classe + '"]');
            const filId = classOpt ? classOpt.dataset.filiere : '';
            fSelect.value = filId;

            // 2. IMMEDIATELY Filter the classes before showing the modal
            const options = cSelect.querySelectorAll('option');
            options.forEach(opt => {
                if (!opt.value) return; // Skip "Choisir classe"
                if (opt.dataset.filiere == filId) {
                    opt.style.display = 'block';
                    opt.disabled = false;
                } else {
                    opt.style.display = 'none';
                    opt.disabled = true;
                }
            });

            // 3. Set the specific class
            cSelect.value = s.id_classe;
        }

        document.getElementById('modal-title-heading').textContent = 'Modifier un stagiaire';
        const submitBtn = document.getElementById('form-submit-btn');
        if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Mettre à jour';
        
        modal.style.display = 'flex';
    }
    </script>

    <div class="profile-hub centered-hub">
        <!-- REFINED HUB NAVIGATION -->
        <div class="hub-nav-center">
            <div class="hub-nav-stack">
                <div class="hub-nav-group-v2">
                    <?php if ($prevStudent): ?>
                        <a href="stagiaires.php?id=<?= $prevStudent['id_stagiaire'] ?><?= $filterParams ?>" class="nav-v2-btn" title="Précédent"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <a href="stagiaires.php" class="nav-v2-btn close-btn">RETOUR À LA LISTE</a>
                    <?php if ($nextStudent): ?>
                        <a href="stagiaires.php?id=<?= $nextStudent['id_stagiaire'] ?><?= $filterParams ?>" class="nav-v2-btn" title="Suivant"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="hub-header-v3">
            <div class="hub-identity-v3">
                <div class="hub-title-group">
                    <h1 class="hub-name-v3"><?= h($selectedStudent['nom'] . ' ' . $selectedStudent['prenom']) ?></h1>
                    <div class="hub-meta-v3">
                        <span class="v3-badge"><i class="fa-solid fa-hashtag"></i> <?= h($selectedStudent['num_inscri']) ?></span>
                        <span class="v3-badge"><i class="fa-solid fa-school"></i> <?= h($selectedStudent['nom_classe']) ?></span>
                    </div>
                </div>
                <div class="hub-actions-v3">
                    <button type="button" onclick="triggerModifierHub()" class="v3-btn secondary"><i class="fa-solid fa-pen-nib"></i> Modifier</button>
                    <a href="print_fiche_inscription.php?id=<?= $selectedStudent['id_stagiaire'] ?>&auto=1" target="_blank" class="v3-btn ghost"><i class="fa-solid fa-file-invoice"></i> Fiche</a>
                </div>
            </div>
        </div>

        <!-- HUB TAB NAVIGATION -->
        <div class="hub-tabs">
            <button class="hub-tab-btn <?= $activeHubTab === 'hub-overview' ? 'active' : '' ?>" onclick="switchHubTab(event, 'hub-overview')">Vue d'ensemble</button>
            <button class="hub-tab-btn <?= $activeHubTab === 'hub-absences' ? 'active' : '' ?>" onclick="switchHubTab(event, 'hub-absences')">Absences</button>
            <button class="hub-tab-btn <?= $activeHubTab === 'hub-bulletin' ? 'active' : '' ?>" onclick="switchHubTab(event, 'hub-bulletin')">Bulletin & Notes</button>
            <button class="hub-tab-btn <?= $activeHubTab === 'hub-stages' ? 'active' : '' ?>" onclick="switchHubTab(event, 'hub-stages')">Stages & PFE</button>
            <button class="hub-tab-btn <?= $activeHubTab === 'hub-docs' ? 'active' : '' ?>" onclick="switchHubTab(event, 'hub-docs')">Documents</button>
        </div>
        <!-- HUB TAB CONTENT -->
        <div class="hub-content" style="padding: 0 2rem 2rem;">
            <!-- TAB: OVERVIEW -->
            <div id="hub-overview" class="hub-tab-pane <?= $activeHubTab === 'hub-overview' ? 'active' : '' ?>" style="<?= $activeHubTab === 'hub-overview' ? 'display:block;' : 'display:none;' ?>">
                <div class="hub-dashboard-grid-v2">
                    <div class="mini-card red" onclick="switchHubTab(null, 'hub-absences')">
                        <div class="mini-card-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                        <div class="mini-card-info">
                            <span class="label">Absences</span>
                            <span class="value"><?= count($absences) ?></span>
                        </div>
                    </div>
                    <div class="mini-card blue" onclick="switchHubTab(null, 'hub-bulletin')">
                        <div class="mini-card-icon"><i class="fa-solid fa-star"></i></div>
                        <div class="mini-card-info">
                            <span class="label">Moyenne</span>
                            <span class="value">
                                <?php 
                                    $sum = 0; $cnt = 0;
                                    foreach($notes as $n) {
                                        $m = ($n['note_controle'] ?? 0)*0.4 + ($n['note_theorique'] ?? 0)*0.3 + ($n['note_pratique'] ?? 0)*0.3;
                                        $sum += $m; $cnt++;
                                    }
                                    echo $cnt > 0 ? number_format($sum/$cnt, 2, ',', '') : '—';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="mini-card green">
                        <div class="mini-card-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <div class="mini-card-info">
                            <span class="label">Statut Paiement</span>
                            <span class="value">À JOUR</span>
                        </div>
                    </div>
                </div>

                <div class="overview-details-v3">
                    <div class="v3-info-card">
                        <div class="v3-card-header">
                            <h3>Informations personnelles</h3>
                        </div>
                        <ul class="v3-info-list">
                            <li>
                                <div class="info-icon blue"><i class="fa-solid fa-id-card"></i></div>
                                <strong>CIN:</strong> <?= h((string)($selectedStudent['cin'] ?? 'N/A')) ?>
                            </li>
                            <li>
                                <div class="info-icon purple"><i class="fa-solid fa-envelope"></i></div>
                                <strong>Email:</strong> <?= h((string)($selectedStudent['email'] ?? 'N/A')) ?>
                            </li>
                            <li>
                                <div class="info-icon green"><i class="fa-solid fa-phone"></i></div>
                                <strong>Téléphone:</strong> <?= h((string)($selectedStudent['telephone'] ?? 'N/A')) ?>
                            </li>
                            <li>
                                <div class="info-icon amber"><i class="fa-solid fa-cake-candles"></i></div>
                                <strong>Naissance:</strong> <?= $selectedStudent['date_naissance'] ? date('d/m/Y', strtotime($selectedStudent['date_naissance'])) : 'N/A' ?>
                            </li>
                            <li>
                                <div class="info-icon rose"><i class="fa-solid fa-map-location-dot"></i></div>
                                <strong>Adresse:</strong> <?= h((string)($selectedStudent['adresse'] ?? 'Non renseignée')) ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- TAB: ABSENCES -->
            <div id="hub-absences" class="hub-tab-pane <?= $activeHubTab === 'hub-absences' ? 'active' : '' ?>" style="<?= $activeHubTab === 'hub-absences' ? 'display:block;' : 'display:none;' ?>">
                <div class="detailed-tab-header">
                    <h2>Registre des Absences</h2>
                    <button type="button" onclick="document.getElementById('modal-quick-absence').style.display='flex'" class="btn-hub-action primary small"><i class="fa-solid fa-plus"></i> Ajouter</button>
                </div>
                <div class="card detail-table-card">
                    <table class="data-v2">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Module</th>
                                <th style="text-align:center;">Horaires</th>
                                <th style="text-align:center;">Statut</th>
                                <th>Justificatif</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences as $abs): ?>
                                <tr>
                                    <td style="font-weight:700; color:#000;"><?= date('d/m/Y', strtotime($abs['date_absence'])) ?></td>
                                    <td style="color:#000;"><?= h($abs['nom_module'] ?: 'Hors module') ?></td>
                                    <td style="text-align:center; color:#71717a;"><?= substr($abs['heure_debut'] ?? '', 0, 5) ?> - <?= substr($abs['heure_fin'] ?? '', 0, 5) ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge <?= $abs['est_justifiee'] ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $abs['est_justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                                        </span>
                                    </td>
                                    <td style="color:#71717a; font-style:italic; font-size:0.85rem;"><?= h((string)($abs['justificatif'] ?? '—')) ?></td>
                                    <td style="text-align:center;">
                                        <form method="post" onsubmit="return confirm('Supprimer cette absence ?');" style="display:inline;">
                                            <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                                            <input type="hidden" name="id_absence" value="<?= (int)$abs['id_absence'] ?>">
                                            <button type="submit" name="quick_delete_absence" value="1" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:0.8rem;"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$absences): ?>
                                <tr><td colspan="6" style="text-align:center; padding:3rem; color:#71717a;">Aucune absence pour ce stagiaire.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: BULLETIN (Notes) -->
            <div id="hub-bulletin" class="hub-tab-pane <?= $activeHubTab === 'hub-bulletin' ? 'active' : '' ?>" style="<?= $activeHubTab === 'hub-bulletin' ? 'display:block;' : 'display:none;' ?>">
                <div class="detailed-tab-header">
                    <h2>Bulletin de Notes & Évaluations</h2>
                    <button type="button" onclick="document.getElementById('modal-quick-note').style.display='flex'" class="btn-hub-action primary small"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="card detail-table-card">
                    <table class="data-v2">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th style="text-align:center;">Contrôle (40%)</th>
                                <th style="text-align:center;">Théorique (30%)</th>
                                <th style="text-align:center;">Pratique (30%)</th>
                                <th style="text-align:center;">Moyenne</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notes)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--muted);">Aucune note enregistrée pour le moment.</td></tr>
                            <?php else: ?>
                                <?php 
                                    $sumMoy = 0; $cntMoy = 0;
                                    foreach ($notes as $n): 
                                        $m_note = ($n['note_controle'] ?? 0) * 0.4 + ($n['note_theorique'] ?? 0) * 0.3 + ($n['note_pratique'] ?? 0) * 0.3;
                                        $sumMoy += $m_note; $cntMoy++;
                                ?>
                                    <tr>
                                        <td style="font-weight:700; color:#000;"><?= h($n['nom_module']) ?></td>
                                        <td style="text-align:center;"><?= isset($n['note_controle']) ? number_format((float)$n['note_controle'], 2) : '—' ?></td>
                                        <td style="text-align:center;"><?= isset($n['note_theorique']) ? number_format((float)$n['note_theorique'], 2) : '—' ?></td>
                                        <td style="text-align:center;"><?= isset($n['note_pratique']) ? number_format((float)$n['note_pratique'], 2) : '—' ?></td>
                                        <td style="text-align:center; font-weight:bold; color:var(--primary);"><?= number_format($m_note, 2) ?></td>
                                        <td style="text-align:center;">
                                            <form method="post" onsubmit="return confirm('Supprimer les notes de ce module ?');" style="display:inline;">
                                                <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                                                <input type="hidden" name="id_module" value="<?= (int)$n['id_module'] ?>">
                                                <button type="submit" name="quick_delete_note" value="1" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:0.8rem;"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($cntMoy > 0): ?>
                                    <tr style="background:#f0fdf4;">
                                        <td colspan="4" style="font-weight:800; color:#166534; text-align:right; padding-right:2rem;">MOYENNE GÉNÉRALE</td>
                                        <td style="text-align:center; font-weight:900; color:#16a34a; font-size:1.1rem;"><?= number_format($sumMoy/$cntMoy, 2) ?> / 20</td>
                                        <td></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: DOCUMENTS -->
            <!-- TAB: STAGES & PFE -->
            <div id="hub-stages" class="hub-tab-pane <?= $activeHubTab === 'hub-stages' ? 'active' : '' ?>" style="<?= $activeHubTab === 'hub-stages' ? 'display:block;' : 'display:none;' ?>">
                <div class="detailed-tab-header">
                    <h2>Stages & PFE</h2>
                    <button type="button" onclick="openStageModal(null)" class="btn-hub-action primary small"><i class="fa-solid fa-plus"></i> Ajouter un stage</button>
                </div>
                <?php if (empty($hubStages)): ?>
                    <div style="text-align:center; padding:3rem; color:#71717a; background:rgba(255,255,255,0.02); border-radius:12px; border:1px dashed rgba(255,255,255,0.08);">
                        <i class="fa-solid fa-building-columns" style="font-size:2rem; margin-bottom:1rem; display:block; color:#3f3f46;"></i>
                        Aucun stage ou PFE enregistré pour ce stagiaire.
                    </div>
                <?php else: ?>
                <div class="card detail-table-card">
                    <table class="data-v2">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Entreprise / Sujet</th>
                                <th style="text-align:center;">Période</th>
                                <th style="text-align:center;">Note</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($hubStages as $stg):
                            $today_s = date('Y-m-d');
                            $dd_s = $stg['date_debut'] ?? '';
                            $df_s = $stg['date_fin']   ?? '';
                            $ds_s = $stg['date_soutenance'] ?? '';
                            if ($ds_s && $ds_s < $today_s) { $stageBadge = '<span class="badge" style="background:rgba(139,92,246,0.1);color:#8b5cf6;">Soutenu</span>'; }
                            elseif ($df_s && $today_s > $df_s) {
                                $stageBadge = empty($stg['rapport_url'])
                                    ? '<span class="badge badge-danger">Rapport manquant</span>'
                                    : '<span class="badge badge-success">Terminé</span>';
                            } elseif ($dd_s && $df_s && $today_s >= $dd_s && $today_s <= $df_s) {
                                $stageBadge = '<span class="badge" style="background:rgba(59,130,246,0.1);color:#3b82f6;">En cours</span>';
                            } else {
                                $stageBadge = '<span class="badge" style="background:rgba(250,204,21,0.1);color:#ca8a04;">Planifié</span>';
                            }
                        ?>
                            <tr>
                                <td><span class="badge" style="<?= $stg['type_stage']==='pfe'?'background:rgba(168,85,247,0.1);color:#a855f7;':'background:rgba(20,184,166,0.1);color:#14b8a6;' ?>">
                                    <?= $stg['type_stage'] === 'pfe' ? 'PFE' : 'Stage Entreprise' ?>
                                </span></td>
                                <td>
                                    <strong style="color:#000;"><?= h((string)($stg['entreprise'] ?? '—')) ?></strong>
                                    <?php if ($stg['sujet']): ?><br><small style="color:#71717a;"><?= h((string)$stg['sujet']) ?></small><?php endif; ?>
                                </td>
                                <td style="text-align:center; color:#71717a; font-size:0.85rem;">
                                    <?= $dd_s ? date('d/m/Y', strtotime($dd_s)) : '—' ?><br>
                                    <?= $df_s ? '→ '.date('d/m/Y', strtotime($df_s)) : '' ?>
                                </td>
                                <td style="text-align:center; font-weight:700; color:<?= $stg['note_stage'] !== null ? ($stg['note_stage'] >= 10 ? '#16a34a' : '#ef4444') : '#a1a1aa' ?>;">
                                    <?= $stg['note_stage'] !== null ? number_format((float)$stg['note_stage'], 2).' / 20' : '—' ?>
                                </td>
                                <td style="text-align:center;"><?= $stageBadge ?></td>
                                <td style="text-align:center;">
                                    <?php if ($stg['convention_url']): ?>
                                        <a href="<?= h((string)$stg['convention_url']) ?>" target="_blank" title="Convention" style="color:#3b82f6; margin-right:6px;"><i class="fa-solid fa-file-contract"></i></a>
                                    <?php endif; ?>
                                    <a href="print_convention_stage.php?id=<?= (int)$stg['id_stage'] ?>" target="_blank" title="Imprimer convention" style="color:#a855f7; margin-right:6px;"><i class="fa-solid fa-print"></i></a>
                                    <button type="button" onclick="openStageModal(<?= htmlspecialchars(json_encode($stg), ENT_QUOTES) ?>)" title="Modifier" style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.3);color:#3b82f6;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:0.8rem;margin-right:4px;"><i class="fa-solid fa-pen"></i></button>
                                    <form method="post" onsubmit="return confirm('Supprimer ce stage ?');" style="display:inline;">
                                        <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                                        <input type="hidden" name="id_stage" value="<?= (int)$stg['id_stage'] ?>">
                                        <button type="submit" name="quick_delete_stage" value="1" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:0.8rem;"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

                        <div id="hub-docs" class="hub-tab-pane <?= $activeHubTab === 'hub-docs' ? 'active' : '' ?>" style="<?= $activeHubTab === 'hub-docs' ? 'display:block;' : 'display:none;' ?>">
                <div class="detailed-tab-header">
                    <h2>Documents Administratifs</h2>
                </div>
                <div class="documents-grid-v2">
                    <?php $sid_doc = (int)$selectedStudent['id_stagiaire']; ?>
                    <a href="print_fiche_inscription.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon blue"><i class="fa-solid fa-user-plus"></i></div><span>Fiche Inscription</span></a>
                    <a href="print_certificat_scolarite.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon purple"><i class="fa-solid fa-graduation-cap"></i></div><span>Certificat Scolarité</span></a>
                    <a href="print_releve_notes.php?id=<?= $sid_doc ?>&mode=combined&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon yellow"><i class="fa-solid fa-table-list"></i></div><span>Relevé Complet</span></a>
                    <a href="print_releve_notes.php?id=<?= $sid_doc ?>&mode=controle&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon orange"><i class="fa-solid fa-list-check"></i></div><span>Relevé (Contrôles)</span></a>
                    <a href="print_releve_notes.php?id=<?= $sid_doc ?>&mode=examen&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon teal"><i class="fa-solid fa-clipboard-check"></i></div><span>Relevé (Examens)</span></a>
                    <a href="print_attestation_reussite.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon green"><i class="fa-solid fa-award"></i></div><span>Attestation Réussite</span></a>
                    <a href="print_etat_paiement.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon red"><i class="fa-solid fa-file-invoice-dollar"></i></div><span>État Cotisations</span></a>
                    <a href="print_recu_paiement.php?id=<?= $sid_doc ?>&mois=<?=h(date('Y-m'))?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon pink"><i class="fa-solid fa-receipt"></i></div><span>Reçu de Paiement</span></a>
                </div>

                <!-- DOCUMENTS HISTORY -->
                <?php if (!empty($hubDocHistory)): ?>
                <div style="margin-top:2rem;">
                    <div class="detailed-tab-header" style="margin-bottom:1rem;">
                        <h2 style="font-size:1.2rem;">Historique des documents édités</h2>
                    </div>
                    <div class="card detail-table-card">
                        <table class="data-v2">
                            <thead>
                                <tr>
                                    <th>Date & Heure</th>
                                    <th>Type de Document</th>
                                    <th>Référence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hubDocHistory as $dh): ?>
                                    <tr>
                                        <td style="color:#71717a; font-size:0.85rem;"><?= date('d/m/Y H:i', strtotime($dh['genere_le'])) ?></td>
                                        <td style="font-weight:700; color:#000;"><?= h((string)$dh['type_document']) ?></td>
                                        <td style="color:#71717a;"><?= h((string)($dh['reference'] ?? '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.document-link-btn {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.5rem;
    padding: 1rem 0.5rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.document-link-btn:hover {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.15);
    transform: translateY(-2px);
}
.document-link-btn i {
    font-size: 1.5rem;
}
.document-link-btn span {
    font-size: 0.75rem;
    font-weight: 600;
    color: #a1a1aa;
}
.document-link-btn:hover span {
    color: #fff;
}
</style>

<div id="classic-list-view" style="<?= $selectedStudent ? 'display:none;' : '' ?>">
<h1 class="page-title" style="font-family: 'Instrument Serif', serif;">Gestion des Stagiaires</h1>

<section class="gds-filter-bar no-print">
    <header class="gds-filter-bar__header">
        <h3 class="gds-filter-bar__title">SÉLECTIONNER LES STAGIAIRES</h3>
        <span class="gds-filter-bar__count">Résultats : <strong id="flt-stag-count">0</strong></span>
    </header>
    <div class="gds-filter-bar__grid">
        <label class="gds-filter-bar__field">
            <span>FILIÈRE</span>
            <select id="flt-stag-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieresList as $fp): ?>
                    <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_fix_text((string) $fp['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>CLASSE</span>
            <select id="flt-stag-classe" disabled
                data-all-classes='<?= htmlspecialchars(json_encode(array_map(fn($c) => ['id' => (int)$c['id_classe'], 'fid' => (int)$c['id_filiere'], 'label' => $c['nom_classe']], $classes)), ENT_QUOTES) ?>'>
                <option value="">— Toutes —</option>
                <?php foreach ($classes as $cp): ?>
                    <option value="<?= (int) $cp['id_classe'] ?>" data-filiere="<?= (int) $cp['id_filiere'] ?>"><?= h($cp['nom_classe']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>RECHERCHE</span>
            <input id="flt-stag-search" type="search" placeholder="Nom, prénom...">
        </label>
        <form method="get" class="gds-filter-bar__field">
            <span>MOIS COTIS.</span>
            <input type="month" name="mois" value="<?= h($listMoisNav) ?>" onchange="this.form.submit()">
        </form>
        <label class="gds-filter-bar__field">
            <span>TRI</span>
            <select id="flt-stag-sort">
                <option value="nom">Ordre alphabétique (Nom)</option>
                <option value="num_inscri">Par N° inscription</option>
            </select>
        </label>
        <div class="gds-filter-bar__field">
            <button id="btn-reset-filters" class="btn secondary" style="width:100%"><i class="fa-solid fa-rotate-left"></i> Reset</button>
        </div>
    </div>
</section>

<div id="blank-state" class="card" style="text-align:center; padding: 5rem 1rem;">
    <h3 style="color: #a1a1aa;">Veuillez choisir une filière ou une classe</h3>
</div>

<div id="empty-state" class="card" style="display:none; text-align:center; padding: 4rem 1rem;">
    <h3 style="color: #a1a1aa;">Aucun stagiaire trouvé</h3>
</div>

<div class="card table-container" id="liste-stagiaires" style="padding:0; display:none;">
    <table class="data" id="liste-stagiaires-table">
        <thead>
        <tr>
            <th style="width:140px;">N° INSCRI</th>
            <th>STAGIAIRE</th>
            <th style="width:120px;">CLASSE</th>
            <th style="width:100px;">STATUT</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $rowName = $r['nom'].' '.$r['prenom'];
            $color = getAvatarColor($rowName);
            ?>
            <tr data-filterable="1" data-id="<?= $r['id_stagiaire']?>" data-filiere="<?= $r['id_filiere']?>" data-classe="<?= $r['id_classe']?>" data-name="<?= h($rowName)?>" data-num_inscri="<?= h($r['num_inscri'])?>" class="clickable-row js-open-hub" style="cursor:pointer;">
                <td><?= h($r['num_inscri'])?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="avatar-circle" style="background-color: <?= $color ?>20; color: <?= $color ?>;"><?= strtoupper(mb_substr($r['prenom'],0,1).mb_substr($r['nom'],0,1))?></div>
                        <div style="font-weight:600; color:#e4e4e7;"><?= h($rowName)?></div>
                    </div>
                </td>
                <td><?= h($r['nom_classe'])?></td>
                <td><span class="badge" style="background:rgba(52,211,153,0.1); color:#34d399;">ACTIF</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<button id="fab-add" class="fab-button no-print" title="Ajouter" style="<?= $selectedStudent ? 'display:none;' : '' ?>">
    <i class="fa-solid fa-plus"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalOverlay = document.getElementById('modal-overlay');
    const fabButton = document.getElementById('fab-add');
    const closeBtns = document.querySelectorAll('.js-close-modal');
    const blankState = document.getElementById('blank-state');
    const emptyState = document.getElementById('empty-state');
    const tableContainer = document.getElementById('liste-stagiaires');
    const fltFiliere = document.getElementById('flt-stag-filiere');
    const fltClasse = document.getElementById('flt-stag-classe');
    const fltSort = document.getElementById('flt-stag-sort');
    const searchInput = document.getElementById('flt-stag-search');
    const countEl = document.getElementById('flt-stag-count');
    const resetBtn = document.getElementById('btn-reset-filters');

    function updateMainView() {
        if (blankState) blankState.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'block';
    }

    function applyFiliereRestriction(fSelect, cSelect) {
        if (!fSelect || !cSelect) return;
        const fid = fSelect.value;
        // Rebuild options from stored JSON — display:none doesn't work on <option> in Chrome/Edge
        const allClasses = JSON.parse(cSelect.dataset.allClasses || '[]');
        const currentVal = cSelect.value;
        const placeholderText = cSelect.querySelector('option[value=""]')?.textContent || '— Choisir classe —';
        // Clear all except the placeholder
        cSelect.innerHTML = '<option value="">' + placeholderText + '</option>';
        cSelect.disabled = (fid === '');
        allClasses.forEach(c => {
            if (fid === '' || String(c.fid) === String(fid)) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.dataset.filiere = c.fid;
                opt.textContent = c.label;
                if (String(c.id) === String(currentVal)) opt.selected = true;
                cSelect.appendChild(opt);
            }
        });
    }

    [fltFiliere, fltClasse, searchInput].forEach(el => el?.addEventListener('change', updateMainView));
    searchInput?.addEventListener('input', updateMainView);
    fltFiliere?.addEventListener('change', () => {
        // Reset classe selection when filière changes
        if (fltClasse) fltClasse.value = '';
        applyFiliereRestriction(fltFiliere, fltClasse);
        // Trigger gdsTableFilter to re-filter rows
        fltClasse?.dispatchEvent(new Event('change'));
        updateMainView();
    });
    fltClasse?.addEventListener('change', updateMainView);

    // Reset button
    if (resetBtn) {
        resetBtn.onclick = () => {
            if (fltFiliere) fltFiliere.value = '';
            if (fltClasse) { fltClasse.value = ''; fltClasse.disabled = true; }
            if (searchInput) searchInput.value = '';
            updateMainView();
            if (window.gdsTableFilter) {
                // Trigger re-filter
                fltFiliere?.dispatchEvent(new Event('change'));
            }
        };
    }

    if (countEl) {
        const observer = new MutationObserver(() => {
            const hasFilter = (fltFiliere?.value || '') !== '' || (fltClasse?.value || '') !== '' || (searchInput?.value.trim() || '') !== '';
            if (!hasFilter) return;
            const count = parseInt(countEl.innerText.trim(), 10) || 0;
            if (count === 0) { tableContainer.style.display = 'none'; emptyState.style.display = 'block'; }
            else { tableContainer.style.display = 'block'; emptyState.style.display = 'none'; }
        });
        observer.observe(countEl, { childList: true, characterData: true, subtree: true });
    }

    if (fabButton) {
        fabButton.onclick = () => {
            document.getElementById('stagiaire-form').reset();
            document.getElementById('modal-title-heading').textContent = 'Ajouter un stagiaire';
            modalOverlay.style.display = 'flex';
        };
    }
    closeBtns.forEach(btn => btn.onclick = () => modalOverlay.style.display = 'none');

    function sortTable() {
        const order = fltSort?.value || 'nom';
        const tbody = tableContainer.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr[data-filterable]'));

        rows.sort((a, b) => {
            let valA, valB;
            if (order === 'num_inscri') {
                valA = a.dataset.num_inscri || '';
                valB = b.dataset.num_inscri || '';
            } else {
                valA = a.dataset.name || '';
                valB = b.dataset.name || '';
            }
            return valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    if (window.gdsTableFilter) {
        window.gdsTableFilter({
            table: '#liste-stagiaires-table',
            counter: '#flt-stag-count',
            controls: [
                { selector: '#flt-stag-filiere', field: 'filiere', type: 'equals' },
                { selector: '#flt-stag-classe',  field: 'classe',  type: 'equals' },
                { selector: '#flt-stag-search',  field: 'search',  type: 'contains', searchFields: ['name', 'num_inscri'] }
            ]
        });
    }
    
    fltSort?.addEventListener('change', sortTable);
    sortTable(); // Initial sort

    function attachLinks() {
        const rows = document.querySelectorAll('.js-open-hub');
        rows.forEach(row => {
            row.onclick = () => {
                const f = fltFiliere?.value || '';
                const c = fltClasse?.value || '';
                const s = encodeURIComponent(searchInput?.value.trim() || '');
                const o = fltSort?.value || 'nom';
                window.location.href = `stagiaires.php?id=${row.dataset.id}&f=${f}&c=${c}&s=${s}&o=${o}`;
            };
        });
    }

    [fltFiliere, fltClasse, searchInput, fltSort].forEach(el => el?.addEventListener('change', attachLinks));
    searchInput?.addEventListener('input', attachLinks);
    attachLinks();

    const formFil = document.getElementById('form-filiere-select');
    const formCl  = document.getElementById('form-classe-select');
    formFil?.addEventListener('change', () => applyFiliereRestriction(formFil, formCl));

    updateMainView();
});

function switchHubTab(evt, tabId) {
    if (evt) evt.preventDefault();
    
    // Hide all panes
    const panes = document.querySelectorAll('.hub-tab-pane');
    panes.forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
    });
    
    // Deactivate all buttons
    const btns = document.querySelectorAll('.hub-tab-btn');
    btns.forEach(b => b.classList.remove('active'));
    
    // Show target pane
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
        target.style.display = 'block';
    } else {
        console.error('Tab target not found:', tabId);
    }
    
    // Activate button
    if (evt && evt.currentTarget && evt.currentTarget.classList.contains('hub-tab-btn')) {
        evt.currentTarget.classList.add('active');
    } else {
        // Find by tabId if clicked from outside
        const btn = document.querySelector(`.hub-tab-btn[onclick*="${tabId}"]`);
        if (btn) btn.classList.add('active');
    }
}

function saveInlineNote(sid, mid, field, val) {
    const input = event.target;
    input.classList.add('saving');
    
    const formData = new FormData();
    formData.append('action', 'save_inline_note');
    formData.append('sid', sid);
    formData.append('mid', mid);
    formData.append('field', field);
    formData.append('val', val);

    fetch('stagiaires.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        input.classList.remove('saving');
        if (data.success) {
            input.classList.add('success');
            setTimeout(() => input.classList.remove('success'), 1500);
        } else {
            alert('Erreur: ' + data.error);
        }
    })
    .catch(err => {
        input.classList.remove('saving');
        console.error(err);
    });
}

function openStageModal(stg) {
    const modal = document.getElementById('modal-quick-stage');
    if (!modal) return;
    const form = modal.querySelector('form');
    form.reset();
    const titleEl = modal.querySelector('.modal-header h2');
    if (stg) {
        titleEl.textContent = 'Modifier le stage';
        form.querySelector('[name="id_stage"]').value = stg.id_stage || '';
        form.querySelector('[name="type_stage"]').value = stg.type_stage || 'stage_entreprise';
        form.querySelector('[name="sujet"]').value = stg.sujet || '';
        form.querySelector('[name="entreprise"]').value = stg.entreprise || '';
        form.querySelector('[name="date_debut"]').value = stg.date_debut || '';
        form.querySelector('[name="date_fin"]').value = stg.date_fin || '';
        form.querySelector('[name="date_soutenance"]').value = stg.date_soutenance || '';
        form.querySelector('[name="note_stage"]').value = stg.note_stage !== null ? stg.note_stage : '';
        form.querySelector('[name="convention_url"]').value = stg.convention_url || '';
        form.querySelector('[name="rapport_url"]').value = stg.rapport_url || '';
        form.querySelector('[name="evaluation_entreprise"]').value = stg.evaluation_entreprise || '';
        form.querySelector('[name="jury"]').value = stg.jury || '';
    } else {
        titleEl.textContent = 'Ajouter un stage / PFE';
        form.querySelector('[name="id_stage"]').value = '';
    }
    modal.style.display = 'flex';
}
</script>

<?php if ($selectedStudent): ?>
    <!-- QUICK ABSENCE MODAL -->
    <div id="modal-quick-absence" class="modal-overlay" style="display:none; z-index:10000;">
        <div class="modal-card" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Nouvelle absence (Enregistrement rapide)</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('modal-quick-absence').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" class="modal-form">
                <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                <div class="modal-body">
                    <div style="background: rgba(239,68,68,0.1); padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center;">
                        <i class="fa-solid fa-clock-rotate-left" style="color:#ef4444; font-size:1.5rem;"></i>
                        <p style="font-size:0.9rem; color:#a1a1aa; margin:0;">
                            Saisie rapide pour <strong><?= h($selectedStudent['nom'] . ' ' . $selectedStudent['prenom']) ?></strong>.
                            L'absence sera automatiquement rattachée à son dossier.
                        </p>
                    </div>

                    <fieldset class="modal-fieldset" style="grid-template-columns: 1fr;">
                        <label>Date * <input type="date" name="date_absence" required value="<?= date('Y-m-d') ?>"></label>
                        <label>Module (Optionnel)
                            <select name="id_module">
                                <option value="">— Aucun module spécifique —</option>
                                <?php foreach ($mods as $m): ?>
                                    <option value="<?= (int)$m['id_module'] ?>"><?= h($m['nom_module']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                            <label>Heure de début <input type="time" name="heure_debut"></label>
                            <label>Heure de fin <input type="time" name="heure_fin"></label>
                        </div>
                        <label>Justificatif / Motif <input type="text" name="justificatif" placeholder="Ex: Absence autorisée, Certificat médical..."></label>
                        <label style="flex-direction:row; align-items:center; gap:0.75rem; margin-top:0.5rem; color:#e4e4e7; font-weight:bold; cursor:pointer;">
                            <input type="checkbox" name="est_justifiee" value="1" style="width:20px; height:20px;">
                            Cette absence est déjà justifiée
                        </label>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-quick-absence').style.display='none'">Annuler</button>
                    <button type="submit" name="quick_save_absence" value="1" class="btn" style="background:#ef4444; color:#fff;">
                        <i class="fa-solid fa-floppy-disk"></i> Enregistrer l'absence
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- QUICK NOTE MODAL -->
    <div id="modal-quick-note" class="modal-overlay" style="display:none; z-index:10000;">
        <div class="modal-card" style="max-width: 650px;">
            <div class="modal-header">
                <h2>Saisir les notes d'un module</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('modal-quick-note').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" class="modal-form">
                <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                <div class="modal-body">
                    <div style="background: rgba(59,130,246,0.1); padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center;">
                        <i class="fa-solid fa-circle-info" style="color:#3b82f6; font-size:1.5rem;"></i>
                        <p style="font-size:0.9rem; color:#a1a1aa; margin:0;">
                            Mise à jour des notes pour <strong><?= h($selectedStudent['nom'] . ' ' . $selectedStudent['prenom']) ?></strong>.
                            Laissez vide pour les notes non encore évaluées.
                        </p>
                    </div>

                    <fieldset class="modal-fieldset" style="grid-template-columns: 1fr;">
                        <label>Module *
                            <select name="id_module" required>
                                <option value="">— Choisir le module —</option>
                                <?php foreach ($mods as $m): ?>
                                    <option value="<?= (int)$m['id_module'] ?>"><?= h($m['nom_module']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </fieldset>

                    <h3 style="font-size:1.1rem; color:#fff; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem; margin:1.5rem 0 1rem; font-family:'Instrument Serif', serif;">Saisie des Notes (/ 20)</h3>
                    
                    <fieldset class="modal-fieldset" style="grid-template-columns: 1fr 1fr 1fr;">
                        <label>Contrôle Continu
                            <input name="note_controle" type="number" step="0.01" min="0" max="20" placeholder="Ex: 14.5">
                        </label>
                        <label>Examen Théorique
                            <input name="note_theorique" type="number" step="0.01" min="0" max="20" placeholder="Ex: 12">
                        </label>
                        <label>Examen Pratique
                            <input name="note_pratique" type="number" step="0.01" min="0" max="20" placeholder="Ex: 16">
                        </label>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-quick-note').style.display='none'">Annuler</button>
                    <button type="submit" name="quick_save_note" value="1" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Créer la fiche de notes
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php if ($selectedStudent): ?>
    <!-- STAGE MODAL -->
    <div id="modal-quick-stage" class="modal-overlay" style="display:none; z-index:10000;">
        <div class="modal-card" style="max-width:700px;">
            <div class="modal-header">
                <h2>Ajouter un stage / PFE</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('modal-quick-stage').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" class="modal-form">
                <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                <input type="hidden" name="id_stage" value="">
                <div class="modal-body">
                    <fieldset class="modal-fieldset" style="grid-template-columns:1fr 1fr;">
                        <label style="grid-column:span 2;">Type
                            <select name="type_stage">
                                <option value="stage_entreprise">Stage Entreprise</option>
                                <option value="pfe">PFE</option>
                            </select>
                        </label>
                        <label style="grid-column:span 2;">Entreprise / Organisme
                            <input type="text" name="entreprise" placeholder="Ex: Maroc Telecom">
                        </label>
                        <label style="grid-column:span 2;">Sujet / Mission
                            <input type="text" name="sujet" placeholder="Ex: Développement d'une application web">
                        </label>
                        <label>Date début <input type="date" name="date_debut"></label>
                        <label>Date fin <input type="date" name="date_fin"></label>
                        <label>Date soutenance (PFE) <input type="date" name="date_soutenance"></label>
                        <label>Note de stage (/20) <input type="number" name="note_stage" min="0" max="20" step="0.01" placeholder="Ex: 15.50"></label>
                        <label style="grid-column:span 2;">Jury / Modalités
                            <input type="text" name="jury" placeholder="Ex: M. Dupont, Mme Martin">
                        </label>
                        <label style="grid-column:span 2;">URL Convention
                            <input type="url" name="convention_url" placeholder="https://...">
                        </label>
                        <label style="grid-column:span 2;">URL Rapport PDF
                            <input type="url" name="rapport_url" placeholder="https://...">
                        </label>
                        <label style="grid-column:span 2;">Appréciation Entreprise
                            <input type="text" name="evaluation_entreprise" placeholder="Ex: Très bon stagiaire, motivé...">
                        </label>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-quick-stage').style.display='none'">Annuler</button>
                    <button type="submit" name="quick_save_stage" value="1" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

