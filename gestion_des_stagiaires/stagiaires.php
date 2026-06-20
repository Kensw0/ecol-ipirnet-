<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$listMoisNav = date('Y-m');
if (isset($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['mois'])) {
    $listMoisNav = (string) $_GET['mois'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // AJAX Inline Note Saving
    if (isset($_POST['action']) && $_POST['action'] === 'save_inline_note') {
        header('Content-Type: application/json');
        try {
            $sid = (int)($_POST['sid'] ?? 0);
            $mid = (int)($_POST['mid'] ?? 0);
            $field = (string)($_POST['field'] ?? '');
            $val = trim((string)($_POST['val'] ?? ''));
            $val = ($val === '') ? null : (float)str_replace(',', '.', $val);
            
            $typeMap = ['note_controle' => 'controle_1', 'note_theorique' => 'theorique', 'note_pratique' => 'pratique'];
            $allowed = array_keys($typeMap);
            if ($sid > 0 && $mid > 0 && in_array($field, $allowed)) {
                $type = $typeMap[$field];
                $st = $pdo->prepare("INSERT INTO module_notes (id_stagiaire, id_module, note, type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)");
                $st->execute([$sid, $mid, $val, $type]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid params']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if (isset($_POST['delete_mensualite'])) {
        header('Content-Type: application/json');
        if (!gds_is_directeur()) {
            echo json_encode(['success' => false, 'error' => 'Accès refusé.']); exit;
        }
        $sid   = (int)($_POST['id_stagiaire'] ?? 0);
        $mois  = (string)($_POST['mois_ref'] ?? '');
        if ($sid <= 0 || !preg_match('/^\d{4}-\d{2}$/', $mois)) {
            echo json_encode(['success'=>false,'error'=>'Données invalides']); exit;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM mensualites WHERE id_stagiaire=? AND mois_ref=?")->execute([$sid, $mois]);
            // Recompute cumul_restant for remaining months
            $allMens = $pdo->prepare("SELECT id_mensualite, montant_restant FROM mensualites WHERE id_stagiaire=? ORDER BY mois_ref ASC");
            $allMens->execute([$sid]);
            $running = 0.0;
            foreach ($allMens->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $running += (float)($row['montant_restant'] ?? 0);
                $pdo->prepare("UPDATE mensualites SET cumul_restant=? WHERE id_mensualite=?")->execute([$running, $row['id_mensualite']]);
            }
            $pdo->commit();
            echo json_encode(['success'=>true,'msg'=>'Cotisation supprimée.','mois_ref'=>$mois]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'error'=>'Erreur lors de la suppression.']);
        }
        exit;
    }
    if (isset($_POST['save_mensualite'])) {
        header('Content-Type: application/json');
        if (!gds_is_directeur()) {
            echo json_encode(['success' => false, 'error' => 'Accès refusé.']); exit;
        }
        $sid       = (int)($_POST['id_stagiaire'] ?? 0);
        $mois      = (string)($_POST['mois_ref'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) { echo json_encode(['success'=>false,'error'=>'Mois invalide']); exit; }
        if ($sid <= 0) { echo json_encode(['success'=>false,'error'=>'Stagiaire invalide']); exit; }

        // ── tarifs par défaut par filière ──────────────────────────────
        $tarifs = [2 => 700.0, 3 => 600.0, 4 => 800.0]; // TSDI=700, TGI=600, TSGE=800
        // Fetch stagiaire's filiere
        $stRow = $pdo->prepare("SELECT c.id_filiere FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe WHERE s.id_stagiaire=?");
        $stRow->execute([$sid]);
        $stRow = $stRow->fetch(PDO::FETCH_ASSOC);
        $fidStag = (int)($stRow['id_filiere'] ?? 0);
        $montantDu = $tarifs[$fidStag] ?? 700.0;

        $datePaie  = ($_POST['date_paiement'] ?? '') !== '' ? (string)$_POST['date_paiement'] : null;
        // ── LIMIT: montant_total is always the filière's standard tarif — ignore any tampered POST value ──
        $montantTotal = $montantDu; // use server-side filière tarif, not client POST
        $modeAjout = isset($_POST['mode_ajout']) && (int)$_POST['mode_ajout'] === 1;

        // MODE AJOUT: accumulate on top of existing montant_paye
        $montantPaye = null;
        $statut = 'impayé';
        if ($modeAjout) {
            $ancienPaye    = (float)($_POST['ancien_montant_paye'] ?? 0);
            $nouveauVers   = (float)($_POST['nouveau_versement'] ?? 0);
            // Cap: total paid can never exceed montant_total
            $montantPaye   = min($ancienPaye + $nouveauVers, $montantTotal);
            // Auto-determine statut
            if ($montantPaye >= $montantTotal) {
                $montantPaye = $montantTotal; // cap at total
                $statut      = 'payé';
            } elseif ($montantPaye > 0) {
                $statut = 'partiel';
            } else {
                $statut = 'impayé';
            }
        } else {
            $statut      = (string)($_POST['statut_paiement'] ?? 'impayé');
            $montantPaye = ($_POST['montant_paye'] ?? '') !== '' ? (float)$_POST['montant_paye'] : null;
            // ── LIMIT: montant_paye cannot exceed the filière's standard tarif ──
            if ($montantPaye !== null && $montantPaye > $montantTotal) {
                $montantPaye = $montantTotal;
            }
        }

        // Auto-compute montant_restant
        $montantRestant = null;
        if ($statut === 'payé') {
            $montantPaye    = $montantTotal;
            $montantRestant = 0.0;
        } elseif ($statut === 'partiel' && $montantPaye !== null) {
            $montantRestant = max(0.0, $montantTotal - $montantPaye);
        } elseif ($statut === 'impayé') {
            $montantPaye    = 0.0;
            $montantRestant = $montantTotal;
        }
        $estPaye = ($statut === 'payé') ? 1 : 0;

        // Compute cumul_restant = sum of all previous montant_restant + this one
        $stCumul = $pdo->prepare("SELECT COALESCE(SUM(montant_restant),0) FROM mensualites WHERE id_stagiaire=? AND mois_ref < ?");
        $stCumul->execute([$sid,$mois]);
        $prevCumul = (float)$stCumul->fetchColumn();
        $cumulRestantVal = $prevCumul + ($montantRestant ?? 0);

        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO mensualites (id_stagiaire, mois_ref, est_paye, montant_total, montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                VALUES (?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE
                    est_paye=VALUES(est_paye), montant_total=VALUES(montant_total), montant_paye=VALUES(montant_paye),
                    montant_restant=VALUES(montant_restant), cumul_restant=VALUES(cumul_restant),
                    statut_paiement=VALUES(statut_paiement), date_paiement=VALUES(date_paiement), marque_le=NOW()"
            )->execute([$sid, $mois, $estPaye, $montantTotal, $montantPaye, $montantRestant, $cumulRestantVal, $statut, $datePaie]);

            // After insert/update, recompute cumul_restant for all subsequent months (running total)
            $allMens = $pdo->prepare("SELECT id_mensualite, mois_ref, montant_restant FROM mensualites WHERE id_stagiaire=? ORDER BY mois_ref ASC");
            $allMens->execute([$sid]);
            $allMens = $allMens->fetchAll(PDO::FETCH_ASSOC);
            $running = 0.0;
            foreach ($allMens as $row) {
                $running += (float)($row['montant_restant'] ?? 0);
                $pdo->prepare("UPDATE mensualites SET cumul_restant=? WHERE id_mensualite=?")->execute([$running, $row['id_mensualite']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'error'=>"Erreur lors de l'enregistrement."]);
            exit;
        }

        // Return full row data so JS can update DOM without reload
        $moisLabelPHP = date('M Y', strtotime($mois.'-01'));
        $isPaye   = ($statut === 'payé');
        $isPartiel= ($statut === 'partiel');
        echo json_encode([
            'success'       => true,
            'msg'           => 'Cotisation enregistrée.',
            'row' => [
                'id_stagiaire'    => $sid,
                'mois_ref'        => $mois,
                'mois_label'      => $moisLabelPHP,
                'statut'          => $statut,
                'est_paye'        => $estPaye,
                'montant_total'   => $montantTotal,
                'montant_paye'    => $montantPaye ?? 0,
                'montant_restant' => $montantRestant ?? 0,
                'cumul_restant'   => $cumulRestantVal,
                'date_paiement'   => $datePaie,
            ]
        ]);
        exit;
    }
    if (isset($_POST['delete_id'])) {
        if (!gds_is_directeur()) {
            flash_set('Accès refusé.', 'error');
            redirect('stagiaires.php?mois=' . urlencode($listMoisNav));
        }
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
        if ($dn === null) {
            flash_set('La date de naissance est obligatoire.', 'error');
            redirect('stagiaires.php' . (isset($_POST['id_stagiaire']) && (int)$_POST['id_stagiaire'] > 0 ? '?id=' . (int)$_POST['id_stagiaire'] . '&edit=1' : '?new=1'));
        }
        $adr = trim((string) ($_POST['adresse'] ?? ''));
        $em = trim((string) ($_POST['email'] ?? ''));
        $emNull = $em === '' ? null : $em;
        $tel = trim((string) ($_POST['telephone'] ?? ''));
        $telp = trim((string) ($_POST['telephone_parent'] ?? ''));
        $tuteur = trim((string) ($_POST['nom_tuteur'] ?? ''));
        $pw = (string) ($_POST['mot_de_passe'] ?? '');
        $photo = trim((string) ($_POST['photo'] ?? ''));
        $di = (string) ($_POST['date_inscription'] ?? '');
        $cid = (int) ($_POST['id_classe'] ?? 0);

        // ── Goal 9: Secretaries cannot change structural fields ────────────
        // Always fetch the real id_classe from DB for secretary (modal opens via JS,
        // so the hidden input may be 0). Silently enforce — UI already shows lock block.
        if (gds_is_secretaire() && isset($_POST['id_stagiaire']) && (int)$_POST['id_stagiaire'] > 0) {
            $_secId = (int)$_POST['id_stagiaire'];
            try {
                $_curRow = $pdo->prepare('SELECT id_classe FROM stagiaires WHERE id_stagiaire = ?');
                $_curRow->execute([$_secId]);
                $_curCid = (int)($_curRow->fetchColumn() ?: 0);
                if ($_curCid > 0) {
                    $cid = $_curCid; // always enforce original class, ignore submitted value
                }
            } catch (\PDOException $e) {}
        }

        $errs = [];
        if ($nom === '' || $prenom === '') $errs[] = 'Nom et prénom requis';
        if ($di === '')  $errs[] = 'Date d\'inscription requise';
        if ($cid <= 0)   $errs[] = 'Classe requise — veuillez sélectionner une filière, une année scolaire et une classe';
        if (preg_match('/[0-9]/', $nom) || preg_match('/[0-9]/', $prenom)) $errs[] = 'nom/prénom sans chiffres';
        // CIN: required, must be exactly 2 letters + 6 digits (WA123456)
        if ($cin === '') {
            $errs[] = 'CIN obligatoire';
        } elseif (!preg_match('/^[a-zA-Z]{2}[0-9]{6}$/', $cin)) {
            $errs[] = 'CIN invalide — 2 lettres + 6 chiffres (ex: WA123456)';
        }
        if ($tel !== '' && !preg_match('/^(\+?212|0)[5-7][0-9]{8}$/', preg_replace('/\s/', '', $tel))) $errs[] = 'Téléphone invalide — ex: 0612345678 ou +212612345678';
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
            // ── CIN duplicate check: block if this CIN already belongs to another stagiaire ──
            if ($cin !== '') {
                $cinChk = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE cin = ? AND id_stagiaire != ?');
                $cinChk->execute([$cin, $id]);
                $cinOwner = $cinChk->fetch();
                if ($cinOwner) {
                    flash_set('⚠️ Ce CIN est déjà utilisé par ' . trim((string)$cinOwner['prenom'] . ' ' . (string)$cinOwner['nom']) . ' (N° ' . (string)$cinOwner['num_inscri'] . '). Veuillez corriger le CIN.', 'error');
                    redirect('stagiaires.php?id=' . $id);
                }
            }
            $sql = 'UPDATE stagiaires SET num_inscri=?, cin=?, nom=?, prenom=?, date_naissance=?, adresse=?, email=?, telephone=?, telephone_parent=?, nom_tuteur=?, photo=?, date_inscription=?, id_classe=?';
            $params = [$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $telp === '' ? null : $telp, $tuteur === '' ? null : $tuteur, $photo === '' ? null : $photo, $di, $cid];
            if ($pwHash) {
                $sql .= ', mot_de_passe=? WHERE id_stagiaire=?';
                $params[] = $pwHash; $params[] = $id;
            } else {
                $sql .= ' WHERE id_stagiaire=?';
                $params[] = $id;
            }
            // ── AUDIT TRAIL: snapshot structural fields BEFORE update ──────────
            try {
                $_oldSnap = $pdo->prepare(
                    'SELECT s.id_classe, c.nom_classe, f.id_filiere, f.nom_filiere, c.annee_scolaire
                     FROM stagiaires s
                     JOIN classes c ON c.id_classe = s.id_classe
                     JOIN filieres f ON f.id_filiere = c.id_filiere
                     WHERE s.id_stagiaire = ?'
                );
                $_oldSnap->execute([$id]);
                $_oldAudit = $_oldSnap->fetch() ?: [];

                $_newSnap = $pdo->prepare(
                    'SELECT c.nom_classe, f.id_filiere, f.nom_filiere, c.annee_scolaire
                     FROM classes c JOIN filieres f ON f.id_filiere = c.id_filiere
                     WHERE c.id_classe = ?'
                );
                $_newSnap->execute([$cid]);
                $_newAudit = $_newSnap->fetch() ?: [];

                $_auditMap = [
                    'classe'         => ['ancien' => $_oldAudit['nom_classe']     ?? null, 'nouveau' => $_newAudit['nom_classe']     ?? null],
                    'filiere'        => ['ancien' => $_oldAudit['nom_filiere']    ?? null, 'nouveau' => $_newAudit['nom_filiere']    ?? null],
                    'annee_scolaire' => ['ancien' => $_oldAudit['annee_scolaire'] ?? null, 'nouveau' => $_newAudit['annee_scolaire'] ?? null],
                ];
                $_auditNote = trim((string)($_POST['audit_note'] ?? ''));
                $_auditNote = $_auditNote !== '' ? $_auditNote : null;
                $_auditIns = $pdo->prepare('INSERT INTO stagiaire_historique (id_stagiaire, champ, ancien, nouveau, note) VALUES (?,?,?,?,?)');
                foreach ($_auditMap as $_af => $_av) {
                    if (!empty($_oldAudit) && (string)$_av['ancien'] !== (string)$_av['nouveau']) {
                        $_auditIns->execute([$id, $_af, $_av['ancien'], $_av['nouveau'], $_auditNote]);
                    }
                }
            } catch (\PDOException $e) { /* table may not exist yet — silently skip */ }
            // ── END AUDIT TRAIL ───────────────────────────────────────────────────

            $pdo->prepare($sql)->execute($params);
            flash_set('Stagiaire mis à jour.', 'success');
            // PRG: redirect back to the student hub, preserving the year filter
            $_stAnnee = $pdo->prepare("SELECT c.annee_scolaire FROM stagiaires s JOIN classes c ON c.id_classe=s.id_classe WHERE s.id_stagiaire=? LIMIT 1"); $_stAnnee->execute([$id]); $_savedAnnee = (string)($_stAnnee->fetchColumn() ?: '');
            redirect('stagiaires.php?id=' . $id . ($_savedAnnee !== '' ? '&a=' . urlencode($_savedAnnee) : ''));
        } else {
            if ($mat === '') {
                $year = date('Y', strtotime($di));
                $st = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE num_inscri LIKE ?");
                $st->execute(['INS-' . $year . '-%']);
                $count = (int) $st->fetchColumn();
                $mat = 'INS-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
            }
            $hash = $pwHash ?? password_hash('changeme', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO stagiaires (num_inscri, cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, mot_de_passe, photo, date_inscription, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$mat, $cin === '' ? null : $cin, $nom, $prenom, $dn, $adr === '' ? null : $adr, $emNull, $tel === '' ? null : $tel, $telp === '' ? null : $telp, $tuteur === '' ? null : $tuteur, $hash, $photo === '' ? null : $photo, $di, $cid]);
            $_newId = (int)$pdo->lastInsertId();
            flash_set('Stagiaire créé avec succès (N° Inscription: ' . $mat . ').', 'success');
            // PRG: redirect to the new student's hub page — no year filter so smart default applies
            // (default picks the most recent school year)
            redirect('stagiaires.php?id=' . $_newId);
        }
    }
    
    // QUICK SAVE ABSENCE (AJAX)
    if (isset($_POST['quick_save_absence'])) {
        header('Content-Type: application/json');
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $da = (string)($_POST['date_absence'] ?? '');
        $hd = ($_POST['heure_debut'] ?? '') === '' ? null : (string)$_POST['heure_debut'];
        $hf = ($_POST['heure_fin'] ?? '') === '' ? null : (string)$_POST['heure_fin'];
        $ju = trim((string)($_POST['justificatif'] ?? ''));
        $ej = isset($_POST['est_justifiee']) ? 1 : 0;
        $mid = ($_POST['id_module'] ?? '') === '' ? null : (int)$_POST['id_module'];
        $editId = (int)($_POST['id_absence_edit'] ?? 0); // 0 = new, >0 = edit
        
        if ($sid > 0 && $da !== '') {
            if ($editId > 0) {
                // UPDATE existing absence
                $pdo->prepare('UPDATE absences SET date_absence=?, heure_debut=?, heure_fin=?, justificatif=?, est_justifiee=?, id_module=? WHERE id_absence=? AND id_stagiaire=?')
                    ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $mid, $editId, $sid]);
                $absId = $editId;
            } else {
                // INSERT new absence
                $pdo->prepare('INSERT INTO absences (date_absence, heure_debut, heure_fin, justificatif, est_justifiee, id_stagiaire, id_module) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$da, $hd, $hf, $ju === '' ? null : $ju, $ej, $sid, $mid]);
                $absId = (int)$pdo->lastInsertId();
            }
            // Fetch module name for JS row injection
            $modName = 'Hors module';
            if ($mid !== null) {
                $modRow = $pdo->prepare('SELECT nom_module FROM modules WHERE id_module = ?');
                $modRow->execute([$mid]);
                $modName = (string)($modRow->fetchColumn() ?: 'Hors module');
            }
            echo json_encode([
                'success' => true,
                'msg' => 'Absence enregistrée avec succès.',
                'row' => [
                    'id_absence'    => $absId,
                    'date_absence'  => $da,
                    'nom_module'    => $modName,
                    'heure_debut'   => $hd ?? '',
                    'heure_fin'     => $hf ?? '',
                    'est_justifiee' => $ej,
                    'justificatif'  => $ju === '' ? '—' : $ju,
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
        }
        exit;
    }

    // QUICK SAVE NOTE (AJAX)
    // QUICK SAVE STAGE (AJAX)
    if (isset($_POST['quick_save_stage'])) {
        header('Content-Type: application/json');
        $sid   = (int)($_POST['id_stagiaire'] ?? 0);
        $ts    = in_array((string)($_POST['type_stage'] ?? ''), ['stage_entreprise','pfe'], true) ? (string)$_POST['type_stage'] : 'stage_entreprise';
        $su    = trim((string)($_POST['sujet'] ?? ''));
        $en    = trim((string)($_POST['entreprise'] ?? ''));
        $dd    = ($_POST['date_debut'] ?? '') === '' ? null : (string)$_POST['date_debut'];
        $df    = ($_POST['date_fin']   ?? '') === '' ? null : (string)$_POST['date_fin'];
        $ns    = ($_POST['note_stage'] ?? '') === '' ? null : (float)str_replace(',', '.', (string)$_POST['note_stage']);
        $cu    = trim((string)($_POST['convention_url'] ?? ''));
        $ru    = trim((string)($_POST['rapport_url'] ?? ''));
        $ev    = trim((string)($_POST['evaluation_entreprise'] ?? ''));
        $ds    = ($_POST['date_soutenance'] ?? '') === '' ? null : (string)$_POST['date_soutenance'];
        $ju    = trim((string)($_POST['jury'] ?? ''));
        $as    = trim((string)($_POST['annee_scolaire'] ?? ''));
        $edit_id = (int)($_POST['id_stage'] ?? 0);

        if ($sid > 0) {
            if ($edit_id > 0) {
                $pdo->prepare('UPDATE stages SET type_stage=?,sujet=?,entreprise=?,date_debut=?,date_fin=?,note_stage=?,convention_url=?,rapport_url=?,evaluation_entreprise=?,date_soutenance=?,jury=?,annee_scolaire=? WHERE id_stage=? AND id_stagiaire=?')
                    ->execute([$ts, $su===''?null:$su, $en===''?null:$en, $dd, $df, $ns, $cu===''?null:$cu, $ru===''?null:$ru, $ev===''?null:$ev, $ds, $ju===''?null:$ju, $as, $edit_id, $sid]);
                $stageMsg = 'Stage mis à jour.';
            } else {
                $pdo->prepare('INSERT INTO stages (type_stage,sujet,entreprise,date_debut,date_fin,note_stage,convention_url,rapport_url,evaluation_entreprise,date_soutenance,jury,id_stagiaire,annee_scolaire) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$ts, $su===''?null:$su, $en===''?null:$en, $dd, $df, $ns, $cu===''?null:$cu, $ru===''?null:$ru, $ev===''?null:$ev, $ds, $ju===''?null:$ju, $sid, $as]);
                $stageMsg = 'Stage / PFE ajouté.';
            }
            // Fetch back the saved stage for JS card injection
            $newStageId = $edit_id > 0 ? $edit_id : (int)$pdo->lastInsertId();
            $stRow = $pdo->prepare('SELECT * FROM stages WHERE id_stage = ?');
            $stRow->execute([$newStageId]);
            $stageRow = $stRow->fetch(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'msg' => $stageMsg, 'stage' => $stageRow, 'is_edit' => ($edit_id > 0)]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
        }
        exit;
    }

    // QUICK DELETE STAGE (AJAX)
    if (isset($_POST['quick_delete_stage'])) {
        header('Content-Type: application/json');
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $stid = (int)($_POST['id_stage'] ?? 0);
        if ($sid > 0 && $stid > 0) {
            $pdo->prepare('DELETE FROM stages WHERE id_stage = ? AND id_stagiaire = ?')->execute([$stid, $sid]);
            echo json_encode(['success' => true, 'msg' => 'Stage supprimé.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
        }
        exit;
    }

    // QUICK DELETE ABSENCE (AJAX)
    if (isset($_POST['quick_delete_absence'])) {
        header('Content-Type: application/json');
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        $aid = (int)($_POST['id_absence'] ?? 0);
        if ($aid > 0) {
            $pdo->prepare('DELETE FROM absences WHERE id_absence = ?')->execute([$aid]);
            echo json_encode(['success' => true, 'msg' => 'Absence supprimée.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
        }
        exit;
    }

    // QUICK DELETE NOTE (AJAX) - clears notes but keeps module row
    // note entry removed — managed via notes.php

    // CLEAR DOC HISTORY (AJAX)
    if (isset($_POST['clear_doc_history'])) {
        header('Content-Type: application/json');
        $sid = (int)($_POST['id_stagiaire'] ?? 0);
        if ($sid > 0) {
            $pdo->prepare('DELETE FROM documents_generes WHERE id_stagiaire = ?')->execute([$sid]);
            echo json_encode(['success' => true, 'msg' => 'Historique effacé.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Données invalides.']);
        }
        exit;
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

.hub-dashboard-grid-v2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 2rem; }
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
.v3-info-list li { padding: 14px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 16px; color: #d4d4d8; font-size: 0.95rem; border-radius: 10px; transition: background 0.2s ease; }
.v3-info-list li:last-child { border-bottom: none; }
.v3-info-list li:hover { background: rgba(168,85,247,0.06); }
.v3-info-list li strong { color: #e4e4e7; font-weight: 700; min-width: 100px; }
.v3-info-list li .info-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.v3-info-list li .info-icon.blue { background: rgba(59,130,246,0.1); color: #3b82f6; }
.v3-info-list li .info-icon.purple { background: rgba(139,92,246,0.1); color: #8b5cf6; }
.v3-info-list li .info-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.v3-info-list li .info-icon.amber { background: rgba(245,158,11,0.1); color: #f59e0b; }
.v3-info-list li .info-icon.rose { background: rgba(244,63,94,0.1); color: #f43f5e; }

/* DATA V2 - HIGH CONTRAST (BLACK TEXT) */
.data-v2 { width: 100%; border-collapse: collapse; margin-top: 10px; background: rgba(255,255,255,0.02); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.data-v2 thead th { background: rgba(255,255,255,0.04); color: #a1a1aa; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.08); }
.data-v2 tbody td { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #d4d4d8; font-size: 0.95rem; font-weight: 500; }
.data-v2 tbody tr:last-child td { border-bottom: none; }
.data-v2 tbody tr { transition: background 0.2s ease; cursor: default; background: transparent; }
.data-v2 tbody tr:hover { background: rgba(168,85,247,0.07); }


.static-note { background: rgba(168,85,247,0.12); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(168,85,247,0.3); color: #e4e4e7; font-weight: 700; min-width: 60px; display: inline-block; text-align: center; }
.detailed-tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.detailed-tab-header h2 { font-family: 'Instrument Serif', serif; font-size: 1.8rem; color: #fff; margin: 0; }

/* Small icon action buttons in hub tables */
.btn-icon-sm { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; font-size:0.8rem; background:rgba(255,255,255,0.05); color:#a1a1aa; transition:0.2s; text-decoration:none; }
.btn-icon-sm:hover { background:rgba(255,255,255,0.12); color:#fff; transform:scale(1.1); }
.btn-icon-sm.danger { background:rgba(239,68,68,0.1); color:#ef4444; }
.btn-icon-sm.danger:hover { background:rgba(239,68,68,0.25); }
.mini-card.orange { background:linear-gradient(135deg, rgba(245,158,11,0.15), rgba(234,88,12,0.1)); border-color:rgba(245,158,11,0.2); }
.mini-card.orange .mini-card-icon { color:#f59e0b; }
</style>
<?php

$_allClasses = $pdo->query('SELECT c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau, f.id_filiere, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere ORDER BY f.nom_filiere, c.annee_scolaire, c.nom_classe')->fetchAll();
// Only expose classes with valid YYYY/YYYY school years to the form
$classes = array_values(array_filter($_allClasses, fn($c) => (bool) preg_match('/^\d{4}\/\d{4}$/', (string)$c['annee_scolaire'])));
// Most recent year for the form default (used in Ajouter/Modifier modal)
$allYearsSorted = array_unique(array_column($classes, 'annee_scolaire'));
rsort($allYearsSorted); // DESC string sort — 2026/2027 > 2025/2026
$defaultFormAnnee = !empty($allYearsSorted) ? $allYearsSorted[0] : '';
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM stagiaires WHERE id_stagiaire = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch();
}
$filieresList = $pdo->query('SELECT DISTINCT f.id_filiere, f.nom_filiere FROM filieres f INNER JOIN classes c ON c.id_filiere = f.id_filiere ORDER BY f.nom_filiere')->fetchAll();

// ── Année scolaire filter ──────────────────────────────────────────────────
$_rawAnneesList = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes ORDER BY annee_scolaire DESC"
)->fetchAll(PDO::FETCH_COLUMN);
// Only keep properly formatted school years (YYYY/YYYY) — filters out garbage test values
$anneesList = array_values(array_filter($_rawAnneesList, fn($y) => (bool) preg_match('/^\d{4}\/\d{4}$/', (string)$y)));
// Re-sort DESC numerically (string DESC already works for YYYY/YYYY but be explicit)
usort($anneesList, fn($a, $b) => strcmp((string)$b, (string)$a));

// Default = most recent school year (first item in $anneesList which is sorted DESC).
// Falls back to empty string if no valid years exist.
$currentAnnee = $_SESSION['global_annee_scolaire'] ?? (!empty($anneesList) ? $anneesList[0] : '');
$navAnnee = (string) ($_GET['a'] ?? $currentAnnee);
// Validate — must be a known valid year or empty (= all years)
if ($navAnnee !== '' && !in_array($navAnnee, $anneesList, true)) {
    $navAnnee = $currentAnnee;
}
// ──────────────────────────────────────────────────────────────────────────

$navFiliere = (int) ($_GET['f'] ?? 0);
$navNiveau  = (string) ($_GET['niv'] ?? '');
$navClasse  = (int) ($_GET['c'] ?? 0);
$navSearch  = (string) ($_GET['s'] ?? '');
$navSort    = (string) ($_GET['o'] ?? 'nom');

$sqlNav = "SELECT v.*, s.date_naissance, s.adresse, s.photo, s.email, s.telephone, s.date_inscription, s.cin, c.niveau 
           FROM v_stagiaires_detail v 
           LEFT JOIN stagiaires s ON s.id_stagiaire = v.id_stagiaire
           LEFT JOIN classes c ON c.id_classe = v.id_classe";
$whereNav = [];
$paramsNav = [];
// NOTE: année is filtered CLIENT-SIDE by gdsTableFilter (so the user can switch years
// without a page reload). Only filière/classe are applied server-side.
if ($navFiliere > 0) { $whereNav[] = "v.id_filiere = ?"; $paramsNav[] = $navFiliere; }
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
if ($navAnnee !== '') $filterParams .= '&a=' . urlencode($navAnnee);
if (isset($_GET['f']))   $filterParams .= '&f='   . urlencode($_GET['f']);
if (isset($_GET['niv'])) $filterParams .= '&niv=' . urlencode($_GET['niv']);
if (isset($_GET['c']))   $filterParams .= '&c='   . urlencode($_GET['c']);
if (isset($_GET['s'])) $filterParams .= '&s=' . urlencode($_GET['s']);
if (isset($_GET['o'])) $filterParams .= '&o=' . urlencode($_GET['o']);

if (isset($_GET['id'])) {
    $targetId = (int) $_GET['id'];
    
    // FETCH SELECTED STUDENT (Independent of Nav Filters)
    $stSel = $pdo->prepare("SELECT v.*, s.date_naissance, s.adresse, s.photo, s.email, s.telephone, s.date_inscription, s.cin 
                            FROM v_stagiaires_detail v 
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
        
        $stNotes = $pdo->prepare("SELECT * FROM v_moyennes_par_module WHERE id_stagiaire = ? ORDER BY nom_module");
        $stNotes->execute([$targetId]);
        $notes = $stNotes->fetchAll();

        $stMod = $pdo->prepare('SELECT id_module, nom_module FROM modules WHERE id_filiere = ? ORDER BY nom_module');
        $stMod->execute([(int)$selectedStudent['id_filiere']]);
        $mods = $stMod->fetchAll();

        // FETCH STAGES FOR HUB
        $stStages = $pdo->prepare("SELECT * FROM stages WHERE id_stagiaire = ? ORDER BY date_debut DESC");
        $stStages->execute([$targetId]);
        $hubStages = $stStages->fetchAll();

        // FETCH DOCUMENT HISTORY FOR HUB
        $stDocHist = $pdo->prepare("SELECT id_gen, type_document, reference, genere_le FROM documents_generes WHERE id_stagiaire = ? ORDER BY genere_le DESC LIMIT 10");
        $stDocHist->execute([$targetId]);
        $hubDocHistory = $stDocHist->fetchAll();

        // FETCH AUDIT TRAIL FOR HUB
        $hubAuditTrail = [];
        try {
            $stAudit = $pdo->prepare("SELECT id, champ, ancien, nouveau, note, change_le FROM stagiaire_historique WHERE id_stagiaire = ? ORDER BY change_le DESC");
            $stAudit->execute([$targetId]);
            $hubAuditTrail = $stAudit->fetchAll();
        } catch (\PDOException $e) { /* table may not exist yet */ }

        // FETCH MENSUALITES FOR HUB
        $stMens = $pdo->prepare("SELECT * FROM mensualites WHERE id_stagiaire = ? ORDER BY mois_ref DESC");
        $stMens->execute([$targetId]);
        $hubMensualites = $stMens->fetchAll();

        // ── Tarifs par défaut par filière ──
        $tarifsDefaut = [2 => 700.0, 3 => 600.0, 4 => 800.0];
        $fidHub = (int)($selectedStudent['id_filiere'] ?? 0);
        $montantDuHub = $tarifsDefaut[$fidHub] ?? 700.0;
        $isExceptionTarif = false;

        // Compute global payment status for this student
        $mensCurrentMois = date('Y-m');
        $totalMens = count($hubMensualites);
        $payeCount = 0; $partielCount = 0; $impayeCount = 0;
        $totalDu = 0.0; $totalPaye = 0.0; $totalRestant = 0.0; $latestCumulRestant = 0.0;
        $currentMoisRecord = null;
        foreach ($hubMensualites as $men) {
            $sp = (string)($men['statut_paiement'] ?? '');
            if ((int)$men['est_paye'] === 1 || $sp === 'payé') $payeCount++;
            elseif ($sp === 'partiel') $partielCount++;
            else $impayeCount++;
            $totalDu      += (float)($men['montant_total'] ?? $montantDuHub);
            $totalPaye    += (float)($men['montant_paye'] ?? 0);
            $totalRestant += (float)($men['montant_restant'] ?? 0);
            if ((float)($men['cumul_restant'] ?? 0) > $latestCumulRestant) $latestCumulRestant = (float)$men['cumul_restant'];
            if ($men['mois_ref'] === $mensCurrentMois) $currentMoisRecord = $men;
        }
        $cumulRestant = $latestCumulRestant;
        // Overall status badge
        if ($totalMens === 0) {
            $globalPayStatus = 'aucun';
        } elseif ($impayeCount === 0 && $partielCount === 0) {
            $globalPayStatus = 'paye';
        } elseif ($impayeCount > 0 && $payeCount === 0 && $partielCount === 0) {
            $globalPayStatus = 'impaye';
        } else {
            $globalPayStatus = 'partiel';
        }
        // Current month status
        if (!$currentMoisRecord) {
            $currentMoisStatus = 'aucun';
        } elseif ((int)$currentMoisRecord['est_paye'] === 1 || $currentMoisRecord['statut_paiement'] === 'payé') {
            $currentMoisStatus = 'paye';
        } elseif ($currentMoisRecord['statut_paiement'] === 'partiel') {
            $currentMoisStatus = 'partiel';
        } else {
            $currentMoisStatus = 'impaye';
        }
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
                <?= csrf_hidden() ?>
                
                <div class="modal-section-grid">
                    <fieldset class="modal-fieldset">
                        <legend><i class="fa-solid fa-id-card"></i> Identité</legend>
                        <div class="avatar-preview-container">
                            <img id="avatar-preview" src="<?= h((string) ($edit['photo'] ?? '')) ?>" style="display:<?= empty($edit['photo']) ? 'none' : 'block' ?>;">
                            <div id="avatar-initials" class="avatar-initials" style="display:<?= empty($edit['photo']) ? 'flex' : 'none' ?>;"><i class="fa-solid fa-user"></i></div>
                        </div>
                        <label>Nom * <input class="gds-validate" type="text" name="nom" required value="<?= h((string) ($edit['nom'] ?? '')) ?>"></label>
                        <label>Prénom * <input class="gds-validate" type="text" name="prenom" required value="<?= h((string) ($edit['prenom'] ?? '')) ?>"></label>
                        <label>N° Inscription <input type="text" name="num_inscri" placeholder="Auto si vide" value="<?= h((string) ($edit['num_inscri'] ?? '')) ?>"></label>
                        <label>CIN * <input class="gds-validate" type="text" name="cin" maxlength="8" placeholder="WA123456" required value="<?= h((string) ($edit['cin'] ?? '')) ?>"></label>
                        <label>Photo URL <input type="text" name="photo" id="form-photo" value="<?= h((string) ($edit['photo'] ?? '')) ?>"></label>
                        <label>Date naissance <span style="color:#ef4444;">*</span> <input type="date" name="date_naissance" required value="<?= h((string) ($edit['date_naissance'] ?? '')) ?>"></label>
                    </fieldset>

                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-address-book"></i> Contact</legend>
                            <label>Email <input class="gds-validate" type="email" name="email" value="<?= h((string) ($edit['email'] ?? '')) ?>"></label>
                            <label>Téléphone <input class="gds-validate" type="tel" name="telephone" value="<?= h((string) ($edit['telephone'] ?? '')) ?>"></label>
                            <label>Tél. Parent / Tuteur <input class="gds-validate" type="tel" name="telephone_parent" placeholder="Ex: 0600000000" value="<?= h((string) ($edit['telephone_parent'] ?? '')) ?>"></label>
                            <label>Nom Tuteur / Père <input class="gds-validate" type="text" name="nom_tuteur" placeholder="Ex: Mohamed Alami" value="<?= h((string) ($edit['nom_tuteur'] ?? '')) ?>"></label>
                            <label style="grid-column: span 2;">Adresse <input type="text" name="adresse" value="<?= h((string) ($edit['adresse'] ?? '')) ?>"></label>
                        </fieldset>

                        <fieldset class="modal-fieldset">
                            <legend><i class="fa-solid fa-graduation-cap"></i> Scolarité</legend>
                            <label>Date inscription * <input type="date" name="date_inscription" required value="<?= h((string) ($edit['date_inscription'] ?? date('Y-m-d'))) ?>"></label>
                            <?php
                                // Lock structural fields for secretary ONLY when editing an existing student.
                                // When creating a new student, secretary must be able to assign a class.
                                $_secIsEditing = gds_is_secretaire() && ($edit !== null || $selectedStudent !== null);
                                if ($_secIsEditing):
                                    $_secDispClasse  = '';
                                    $_secDispFiliere = '';
                                    $_secDispAnnee   = '';
                                    // In hub mode $edit is null — fall back to $selectedStudent
                                    $_secCid = $edit
                                        ? (int)$edit['id_classe']
                                        : ($selectedStudent ? (int)$selectedStudent['id_classe'] : 0);
                                    foreach ($classes as $_c) {
                                        if ((int)$_c['id_classe'] === $_secCid) {
                                            $_secDispClasse  = (string)$_c['nom_classe'];
                                            $_secDispFiliere = gds_filiere_code((string)$_c['nom_filiere']);
                                            $_secDispAnnee   = (string)$_c['annee_scolaire'];
                                            break;
                                        }
                                    }
                            ?>
                            <input type="hidden" name="id_classe" value="<?= $_secCid ?>">
                            <input type="hidden" name="form_annee_scolaire" value="<?= h($_secDispAnnee) ?>">
                            <div style="grid-column:span 2; background:rgba(248,113,113,0.06); border:1px solid rgba(248,113,113,0.2); border-radius:10px; padding:0.7rem 1rem; display:flex; align-items:flex-start; gap:0.6rem;">
                                <i class="fa-solid fa-lock" style="color:#f87171; margin-top:2px; font-size:0.8rem; flex-shrink:0;"></i>
                                <div>
                                    <div style="font-size:0.78rem; font-weight:700; color:#f87171; margin-bottom:0.3rem;">Champs réservés au Directeur</div>
                                    <div id="sec-lock-labels" style="font-size:0.82rem; color:#a1a1aa; line-height:1.6;">
                                        Filière : <strong id="sec-lock-filiere" style="color:#e4e4e7;"><?= h($_secDispFiliere) ?></strong> &nbsp;·&nbsp;
                                        Année : <strong id="sec-lock-annee" style="color:#e4e4e7;"><?= h($_secDispAnnee) ?></strong> &nbsp;·&nbsp;
                                        Classe : <strong id="sec-lock-classe" style="color:#e4e4e7;"><?= h($_secDispClasse) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <label>Filière
                                <select id="form-filiere-select">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($filieresList as $fp): ?>
                                        <option value="<?= (int) $fp['id_filiere'] ?>"><?= h(gds_filiere_code((string) $fp['nom_filiere'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Année scolaire *
                                <select id="form-annee-select"
                                    name="form_annee_scolaire"
                                    required
                                    aria-required="true"
                                    data-default-annee="<?= h($defaultFormAnnee) ?>">
                                    <option value="" disabled>— Choisir —</option>
                                    <?php
                                    // Detect the year of the stagiaire being edited
                                    $editAnnee = '';
                                    if ($edit) {
                                        foreach ($classes as $_c) {
                                            if ((int)$_c['id_classe'] === (int)$edit['id_classe']) {
                                                $editAnnee = (string)$_c['annee_scolaire'];
                                                break;
                                            }
                                        }
                                    }
                                    $selectAnnee = $editAnnee !== '' ? $editAnnee : $defaultFormAnnee;
                                    foreach ($allYearsSorted as $ay):
                                    ?>
                                        <option value="<?= h($ay) ?>" <?= ($ay === $selectAnnee) ? 'selected' : '' ?>><?= h($ay) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label style="grid-column: span 2">Classe *
                                <select name="id_classe" id="form-classe-select" required disabled
                                    data-all-classes='<?= htmlspecialchars(json_encode(array_map(fn($c) => ['id' => (int)$c['id_classe'], 'fid' => (int)$c['id_filiere'], 'annee' => (string)$c['annee_scolaire'], 'niveau' => (string)($c['niveau'] ?? ''), 'label' => $c['nom_classe'], 'selected' => ($edit && (int)$edit['id_classe'] === (int)$c['id_classe'])], $classes)), ENT_QUOTES) ?>'>
                                    <option value="">— Choisir filière d'abord —</option>
                                </select>
                            </label>
                            <?php endif; ?>
                            <label style="grid-column: span 2">Mot de passe <input name="mot_de_passe" type="password" placeholder="Laissez vide pour garder l'actuel"></label>
                            <?php if (!gds_is_secretaire()): ?>
                            <label style="grid-column: span 2; display:none;" id="form-raison-wrap">
                                Raison du changement <span style="color:#71717a; font-weight:400; font-size:0.8rem;">(optionnel — affiché dans l'historique)</span>
                                <input type="text" name="audit_note" id="form-audit-note" maxlength="500"
                                    placeholder="ex: Redoublement, Transfert de filière, Correction…"
                                    style="margin-top:0.35rem;">
                            </label>
                            <?php endif; ?>
                        </fieldset>

                    </div>
                </div>
                
            </form>
        </div>
        <?php if (gds_is_secretaire()): ?>
        <div style="background:rgba(96,165,250,0.07); border-top:1px solid rgba(96,165,250,0.18); padding:0.65rem 1.4rem; display:flex; align-items:center; gap:0.55rem;">
            <i class="fa-solid fa-circle-info" style="color:#60a5fa; font-size:0.8rem; flex-shrink:0;"></i>
            <span style="font-size:0.78rem; color:#93c5fd;">Vous pouvez modifier les coordonnées et l'identité. Les champs <strong>Classe, Filière et Année scolaire</strong> sont réservés au Directeur.</span>
        </div>
        <?php endif; ?>
        <div class="modal-footer">
            <button type="button" class="btn secondary js-close-modal">Annuler</button>
            <button type="submit" form="stagiaire-form" name="save" id="form-submit-btn" value="1" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?></button>
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

        const fields = ['nom', 'prenom', 'num_inscri', 'cin', 'photo', 'date_naissance', 'email', 'telephone', 'telephone_parent', 'nom_tuteur', 'adresse', 'date_inscription'];
        fields.forEach(f => {
            const el = form.querySelector('[name="' + f + '"]');
            if (el) el.value = s[f] || '';
        });

        const cSelect    = document.getElementById('form-classe-select');
        const fSelect    = document.getElementById('form-filiere-select');
        const aSelect    = document.getElementById('form-annee-select');

        if (cSelect && fSelect) {
            // 1. Find the annee + filiere of the edited stagiaire from data-all-classes
            const allClasses = JSON.parse(cSelect.dataset.allClasses || '[]');
            const classData  = allClasses.find(c => String(c.id) === String(s.id_classe));
            const filId   = classData ? String(classData.fid)   : '';
            const anneeId = classData ? String(classData.annee) : (aSelect?.dataset.defaultAnnee || '');

            // 2. Set Filière + Année selects
            if (fSelect) fSelect.value = filId;
            if (aSelect) aSelect.value = anneeId;

            // 3. Rebuild the Classe dropdown filtered by filière + année
            rebuildFormClasses();

            // 4. Set the specific class
            cSelect.value = s.id_classe;
        }

        // ── Raison field: store originals, show on structural change ──────────
        var _origClasseId = String(s.id_classe);
        var _origFiliereId = String(cSelect && fSelect ? (function(){
            const allC = JSON.parse(cSelect.dataset.allClasses || '[]');
            const cd   = allC.find(function(c){ return String(c.id) === String(s.id_classe); });
            return cd ? String(cd.fid) : '';
        })() : '');
        var raisonWrap = document.getElementById('form-raison-wrap');
        var raisonInput = document.getElementById('form-audit-note');
        if (raisonInput) raisonInput.value = '';
        if (raisonWrap) raisonWrap.style.display = 'none';

        function _checkRaisonVisibility() {
            if (!raisonWrap) return;
            var classeChanged  = cSelect && String(cSelect.value) !== _origClasseId;
            var filiereChanged = fSelect && String(fSelect.value) !== _origFiliereId;
            raisonWrap.style.display = (classeChanged || filiereChanged) ? '' : 'none';
        }
        if (cSelect) cSelect.addEventListener('change', _checkRaisonVisibility);
        if (fSelect) fSelect.addEventListener('change', _checkRaisonVisibility);
        // ── End raison field ──────────────────────────────────────────────

        document.getElementById('modal-title-heading').textContent = 'Modifier un stagiaire';
        const submitBtn = document.getElementById('form-submit-btn');
        if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Mettre à jour';

        // Update lock block labels from JS class data (in case PHP rendered empty)
        const lockFil = document.getElementById('sec-lock-filiere');
        const lockAnn = document.getElementById('sec-lock-annee');
        const lockCls = document.getElementById('sec-lock-classe');
        if (lockFil || lockAnn || lockCls) {
            const allCls = document.getElementById('form-classe-select')
                ? JSON.parse(document.getElementById('form-classe-select').dataset.allClasses || '[]')
                : (window._allClassesData || []);
            const cd = allCls.find(c => String(c.id) === String(s.id_classe));
            if (cd) {
                if (lockFil) lockFil.textContent = cd.fname || cd.fid || '';
                if (lockAnn) lockAnn.textContent = cd.annee || '';
                if (lockCls) lockCls.textContent = cd.label || '';
            }
        }

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
                    <a href="stagiaires.php<?= $filterParams !== '' ? '?' . ltrim($filterParams, '&') : '' ?>" class="nav-v2-btn close-btn">RETOUR À LA LISTE</a>
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
            <button class="hub-tab-btn active" onclick="switchHubTab(event, 'hub-overview')">Vue d'ensemble</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-absences')">Absences</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-bulletin')">Bulletin &amp; Notes</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-stages')">Stages / PFE</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-cotisations')">💰 Cotisations</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-docs')">Documents</button>
            <button class="hub-tab-btn" onclick="switchHubTab(event, 'hub-parcours')"><i class="fa-solid fa-timeline" style="font-size:0.85em;"></i> Parcours</button>

        </div>
        <!-- HUB TAB CONTENT -->
        <div class="hub-content" style="padding: 0 2rem 2rem;">
            <!-- TAB: OVERVIEW -->
            <div id="hub-overview" class="hub-tab-pane active" style="display:block;">
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
                                        if ($n['note_controle'] === null && $n['note_theorique'] === null && $n['note_pratique'] === null) continue;
                                        $m = ($n['note_controle'] ?? 0)*0.4 + ($n['note_theorique'] ?? 0)*0.3 + ($n['note_pratique'] ?? 0)*0.3;
                                        $sum += $m; $cnt++;
                                    }
                                    echo $cnt > 0 ? number_format($sum/$cnt, 2, ',', '') : '—';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="mini-card orange" onclick="switchHubTab(null, 'hub-stages')" style="cursor:pointer;">
                        <div class="mini-card-icon"><i class="fa-solid fa-briefcase"></i></div>
                        <div class="mini-card-info">
                            <span class="label">Stages / PFE</span>
                            <span class="value"><?= count($hubStages) ?></span>
                        </div>
                    </div>
                    <div class="mini-card <?= $globalPayStatus === 'paye' ? 'green' : ($globalPayStatus === 'partiel' ? 'orange' : ($globalPayStatus === 'impaye' ? 'red' : 'gray')) ?>" onclick="switchHubTab(null, 'hub-cotisations')" style="cursor:pointer;">
                        <div class="mini-card-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <div class="mini-card-info">
                            <span class="label">Cotisations (<?= date('m/Y') ?>)</span>
                            <span class="value">
                                <?php
                                    if ($currentMoisStatus === 'paye') echo '✅ Payé';
                                    elseif ($currentMoisStatus === 'partiel') echo '⚠️ Partiel';
                                    elseif ($currentMoisStatus === 'impaye') echo '❌ Impayé';
                                    else echo '— Aucun';
                                ?>
                            </span>
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
                            <?php if (!empty($selectedStudent['telephone_parent']) || !empty($selectedStudent['nom_tuteur'])): ?>
                            <li>
                                <div class="info-icon amber"><i class="fa-solid fa-phone-volume"></i></div>
                                <strong>Tél. Parent:</strong> <?= h((string)($selectedStudent['telephone_parent'] ?? '—')) ?>
                            </li>
                            <li>
                                <div class="info-icon purple"><i class="fa-solid fa-user-tie"></i></div>
                                <strong>Tuteur:</strong> <?= h((string)($selectedStudent['nom_tuteur'] ?? '—')) ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- TAB: ABSENCES -->
            <div id="hub-absences" class="hub-tab-pane">
                <div class="detailed-tab-header">
                    <h2>Registre des Absences</h2>
                    <?php
                    $absLink = 'absences.php?' . http_build_query([
                        'annee'        => $selectedStudent['annee_scolaire'] ?? '',
                        'id_filiere'   => $selectedStudent['id_filiere']     ?? '',
                        'niveau'       => $selectedStudent['niveau_classe']  ?? '',
                        'id_classe'    => $selectedStudent['id_classe']      ?? '',
                        'highlight_sid'=> $selectedStudent['id_stagiaire']   ?? '',
                    ]);
                    ?>
                    <a href="<?= h($absLink) ?>" class="btn-hub-action ghost small" style="border-color:rgba(168,85,247,0.4);color:#c084fc;"><i class="fa-solid fa-layer-group"></i> Gestion centralisée</a>
                </div>
                <!-- Absence filter bar -->
                <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;padding:0.75rem 1rem;background:rgba(168,85,247,0.05);border:1px solid rgba(168,85,247,0.15);border-radius:10px;">
                    <i class="fa-solid fa-filter" style="color:#a855f7;font-size:0.85rem;"></i>
                    <label style="color:#a1a1aa;font-size:0.85rem;white-space:nowrap;">Date :</label>
                    <input type="date" id="abs-date-filter"
                           style="background:#1e1e2e;border:1px solid rgba(168,85,247,0.3);color:#e4e4e7;border-radius:8px;padding:0.35rem 0.7rem;font-size:0.85rem;"
                           onchange="applyAbsFilters()">
                    <select id="abs-status-filter"
                            style="background:#1e1e2e;border:1px solid rgba(168,85,247,0.3);color:#e4e4e7;border-radius:8px;padding:0.35rem 0.7rem;font-size:0.85rem;"
                            onchange="applyAbsFilters()">
                        <option value="">Tous les statuts</option>
                        <option value="Non">Non justifiée</option>
                        <option value="Justifi">Justifiée</option>
                    </select>
                    <button type="button" onclick="clearAbsFilters()"
                            style="background:transparent;border:1px solid rgba(255,255,255,0.1);color:#71717a;border-radius:8px;padding:0.35rem 0.7rem;font-size:0.82rem;cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i> Effacer
                    </button>
                    <span id="abs-filter-count" style="color:#a855f7;font-size:0.82rem;font-weight:600;"></span>
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
                                <th style="text-align:center;">Voir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences as $abs): ?>
                                <tr>
                                    <td style="font-weight:700; color:#e4e4e7;"><?= date('d/m/Y', strtotime($abs['date_absence'])) ?></td>
                                    <td style="color:#d4d4d8;"><?= h($abs['nom_module'] ?: 'Hors module') ?></td>
                                    <td style="text-align:center; color:#71717a;"><?= substr($abs['heure_debut'], 0, 5) ?> - <?= substr($abs['heure_fin'], 0, 5) ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge <?= $abs['est_justifiee'] ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $abs['est_justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                                        </span>
                                    </td>
                                    <td style="color:#71717a; font-style:italic; font-size:0.85rem;"><?= h((string)($abs['justificatif'] ?? '—')) ?></td>
                                <td style="text-align:center;">
                                    <a href="<?= h($absLink) ?>&highlight=<?= (int)$abs['id_absence'] ?>"
                                       class="btn-icon-sm"
                                       title="Ouvrir dans Gestion des Absences"
                                       style="color:#a855f7;background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.3);">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
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
            <div id="hub-bulletin" class="hub-tab-pane">
                <div class="detailed-tab-header">
                    <h2>Bulletin de Notes</h2>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                        <a href="print_releve_notes.php?id=<?= (int)$selectedStudent['id_stagiaire'] ?>&mode=combined&auto=1" target="_blank" class="btn-hub-action ghost small"><i class="fa-solid fa-print"></i> Relevé complet</a>
                        <a href="print_releve_notes.php?id=<?= (int)$selectedStudent['id_stagiaire'] ?>&mode=controle&auto=1" target="_blank" class="btn-hub-action ghost small"><i class="fa-solid fa-file-lines"></i> Contrôle</a>
                        <a href="print_releve_notes.php?id=<?= (int)$selectedStudent['id_stagiaire'] ?>&mode=examen&auto=1" target="_blank" class="btn-hub-action ghost small"><i class="fa-solid fa-file-lines"></i> Examen</a>
                        <?php
                        $notesLink = 'notes.php?' . http_build_query([
                            'annee'      => $selectedStudent['annee_scolaire'] ?? '',
                            'id_filiere' => $selectedStudent['id_filiere'] ?? '',
                            'niveau'     => $selectedStudent['niveau_classe'] ?? '',
                            'id_classe'  => $selectedStudent['id_classe']  ?? '',
                            'highlight'  => $selectedStudent['id_stagiaire'] ?? '',
                        ]);
                        ?>
                        <a href="<?= h($notesLink) ?>" class="btn-hub-action ghost small" style="border-color:rgba(168,85,247,0.4);color:#c084fc;"><i class="fa-solid fa-pen-to-square"></i> Gérer les notes</a>
                    </div>
                </div>
                <?php
                // Build a lookup of notes by module id
                $notesMap = [];
                foreach ($notes as $n) { $notesMap[(int)$n['id_module']] = $n; }
                // Moyenne générale calculation
                $gmSum = 0; $gmCnt = 0;
                foreach ($mods as $mod) {
                    $n = $notesMap[(int)$mod['id_module']] ?? null;
                    if (!$n) continue;
                    $nc_h = $n['note_controle']  !== null ? (float)$n['note_controle']  : null;
                    $nt_h = $n['note_theorique'] !== null ? (float)$n['note_theorique'] : null;
                    $np_h = $n['note_pratique']  !== null ? (float)$n['note_pratique']  : null;
                    if ($nc_h !== null && $nt_h !== null && $np_h !== null) {
                        $mn = $nc_h*0.4 + $nt_h*0.3 + $np_h*0.3;
                    } elseif ($nc_h !== null && ($nt_h !== null || $np_h !== null)) {
                        $mn = $nc_h*0.4 + ($nt_h ?? 0.0)*0.3 + ($np_h ?? 0.0)*0.3;
                    } elseif ($nc_h !== null) {
                        $mn = $nc_h;
                    } else {
                        continue;
                    }
                    $gmSum += $mn; $gmCnt++;
                }
                $moyenneGenerale = $gmCnt > 0 ? round($gmSum / $gmCnt, 2) : null;
                $modulesWithNotes = 0;
                foreach ($mods as $mod) {
                    $n = $notesMap[(int)$mod['id_module']] ?? null;
                    if ($n && ($n['note_controle'] !== null || $n['note_theorique'] !== null || $n['note_pratique'] !== null)) $modulesWithNotes++;
                }
                ?>

                <?php if (empty($mods)): ?>
                <div style="text-align:center; padding:3rem; color:var(--muted);">
                    <i class="fa-solid fa-book-open" style="font-size:2rem; margin-bottom:0.75rem; display:block; color:#3f3f46;"></i>
                    Aucun module pour cette filière.
                </div>
                <?php else: ?>

                <?php if ($moyenneGenerale !== null): ?>
                <!-- Moyenne générale banner -->
                <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap; background:rgba(168,85,247,0.07); border:1px solid rgba(168,85,247,0.2); border-radius:12px; padding:1rem 1.4rem; margin-bottom:1rem;">
                    <div style="display:flex; flex-direction:column; gap:0.15rem;">
                        <span style="color:#71717a; font-size:0.72rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700;">Moyenne Générale</span>
                        <span style="font-size:2rem; font-weight:800; color:<?= $moyenneGenerale >= 10 ? '#a855f7' : '#ef4444' ?>; line-height:1;"><?= number_format($moyenneGenerale, 2, ',', '') ?><span style="font-size:1rem; color:#71717a;">/20</span></span>
                    </div>
                    <div style="height:40px; width:1px; background:rgba(255,255,255,0.08);"></div>
                    <div>
                        <span style="font-size:0.9rem; font-weight:700; padding:4px 14px; border-radius:20px;
                            color:<?= $moyenneGenerale >= 10 ? '#34d399' : '#f87171' ?>;
                            background:<?= $moyenneGenerale >= 10 ? 'rgba(52,211,153,0.12)' : 'rgba(248,113,113,0.12)' ?>;
                            border:1px solid <?= $moyenneGenerale >= 10 ? 'rgba(52,211,153,0.3)' : 'rgba(248,113,113,0.3)' ?>;">
                            <?= $moyenneGenerale >= 10 ? 'Admis(e)' : 'Ajourné(e)' ?>
                        </span>
                    </div>
                    <div style="margin-left:auto; color:#71717a; font-size:0.82rem;">
                        <?= $modulesWithNotes ?> / <?= count($mods) ?> modules notés
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes table — read-only -->
                <div class="card detail-table-card" style="padding:0; overflow:hidden;">
                    <table class="data-v2" id="bulletin-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th style="text-align:center;">Moy. Contrôles</th>
                                <th style="text-align:center;">Examen Théorique</th>
                                <th style="text-align:center;">Examen Pratique</th>
                                <th style="text-align:center;">Moyenne</th>
                                <th style="text-align:center;">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mods as $mod):
                                $n        = $notesMap[(int)$mod['id_module']] ?? null;
                                $hasNotes = $n && ($n['note_controle'] !== null || $n['note_theorique'] !== null || $n['note_pratique'] !== null);
                                if (!$hasNotes) {
                                    $m_note = null;
                                } elseif ($n['note_controle'] !== null && $n['note_theorique'] !== null && $n['note_pratique'] !== null) {
                                    $m_note = round((float)$n['note_controle']*0.4 + (float)$n['note_theorique']*0.3 + (float)$n['note_pratique']*0.3, 2);
                                } elseif ($n['note_controle'] !== null && ($n['note_theorique'] !== null || $n['note_pratique'] !== null)) {
                                    $m_note = round((float)$n['note_controle']*0.4 + ((float)($n['note_theorique'] ?? 0))*0.3 + ((float)($n['note_pratique'] ?? 0))*0.3, 2);
                                } elseif ($n['note_controle'] !== null) {
                                    $m_note = round((float)$n['note_controle'], 2);
                                } else {
                                    $m_note = null;
                                }
                            ?>
                            <tr data-mid="<?= (int)$mod['id_module'] ?>">
                                <td style="font-weight:600; color:#e4e4e7;"><?= h($mod['nom_module']) ?></td>
                                <td style="text-align:center;" class="note-cell-controle">
                                    <?= ($n && $n['note_controle'] !== null) ? '<span style="color:#e4e4e7;">'.number_format((float)$n['note_controle'], 2).'</span>' : '<span style="color:#3f3f46;">—</span>' ?>
                                </td>
                                <td style="text-align:center;" class="note-cell-theorique">
                                    <?= ($n && $n['note_theorique'] !== null) ? '<span style="color:#e4e4e7;">'.number_format((float)$n['note_theorique'], 2).'</span>' : '<span style="color:#3f3f46;">—</span>' ?>
                                </td>
                                <td style="text-align:center;" class="note-cell-pratique">
                                    <?= ($n && $n['note_pratique'] !== null) ? '<span style="color:#e4e4e7;">'.number_format((float)$n['note_pratique'], 2).'</span>' : '<span style="color:#3f3f46;">—</span>' ?>
                                </td>
                                <td style="text-align:center; font-weight:700;" class="note-cell-moyenne">
                                    <?php if ($m_note !== null): ?>
                                        <span style="color:<?= $m_note >= 10 ? '#34d399' : '#f87171' ?>;"><?= number_format($m_note, 2, ',', '') ?></span>
                                    <?php else: ?>
                                        <span style="color:#3f3f46;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($m_note !== null): ?>
                                        <span style="font-size:0.75rem; font-weight:700; padding:2px 10px; border-radius:20px;
                                            color:<?= $m_note >= 10 ? '#34d399' : '#f87171' ?>;
                                            background:<?= $m_note >= 10 ? 'rgba(52,211,153,0.1)' : 'rgba(248,113,113,0.1)' ?>;
                                            border:1px solid <?= $m_note >= 10 ? 'rgba(52,211,153,0.25)' : 'rgba(248,113,113,0.25)' ?>;">
                                            <?= $m_note >= 10 ? 'Validé' : 'Ajourné' ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#52525b; font-size:0.75rem;">Non noté</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: STAGES / PFE -->
            <div id="hub-stages" class="hub-tab-pane">
                <div class="detailed-tab-header">
                    <h2>Stages &amp; PFE</h2>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <?php
                        $stagesLink = 'stages.php?' . http_build_query([
                            'annee'      => $selectedStudent['annee_scolaire'] ?? '',
                            'id_filiere' => $selectedStudent['id_filiere'] ?? '',
                            'niveau'     => $selectedStudent['niveau_classe'] ?? '',
                            'id_classe'  => $selectedStudent['id_classe']  ?? '',
                            'highlight'  => $selectedStudent['id_stagiaire'] ?? '',
                        ]);
                        ?>
                        <a href="<?= h($stagesLink) ?>" class="btn-hub-action ghost small" style="border-color:rgba(168,85,247,0.4);color:#c084fc;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Gérer les stages
                        </a>
                    </div>
                </div>
                <?php if (empty($hubStages)): ?>
                    <div style="text-align:center; padding:3rem; color:#71717a; background:rgba(255,255,255,0.02); border-radius:15px; border:1px dashed rgba(255,255,255,0.08);">
                        <i class="fa-solid fa-briefcase" style="font-size:2.5rem; opacity:0.2; display:block; margin-bottom:1rem;"></i>
                        Aucun stage ou PFE enregistré pour ce stagiaire.
                    </div>
                <?php else: ?>
                    <?php foreach($hubStages as $stg):
                        $today = date('Y-m-d');
                        $dd = $stg['date_debut'] ?? ''; $df = $stg['date_fin'] ?? ''; $ds = $stg['date_soutenance'] ?? '';
                        $prog = 0;
                        if ($dd && $df) {
                            $start = strtotime($dd); $end = strtotime($df); $now = time();
                            $prog = $now >= $end ? 100 : ($now <= $start ? 0 : round((($now-$start)/($end-$start))*100));
                        }
                        if ($ds && $today > $ds) $badge = '<span class="badge badge-success">Soutenance passée</span>';
                        elseif ($df && $today > $df && empty($stg['rapport_url'])) $badge = '<span class="badge badge-danger">Rapport manquant</span>';
                        elseif ($df && $today > $df) $badge = '<span class="badge badge-success">Terminé</span>';
                        elseif ($dd && $df && $today >= $dd && $today <= $df) $badge = '<span class="badge" style="background:rgba(59,130,246,0.1);color:#3b82f6;">En cours</span>';
                        else $badge = '<span class="badge" style="background:rgba(250,204,21,0.1);color:#facc15;">Planifié</span>';
                    ?>
                    <div data-stage-id="<?= (int)$stg['id_stage'] ?>" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:24px; margin-bottom:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                                    <span style="background:rgba(59,130,246,0.1); color:#3b82f6; padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:700; text-transform:uppercase;">
                                        <?= $stg['type_stage'] === 'pfe' ? 'PFE' : 'Stage Entreprise' ?>
                                    </span>
                                    <?= $badge ?>
                                </div>
                                <h3 style="color:#fff; font-size:1.1rem; margin:0 0 4px 0;"><?= h($stg['sujet'] ?: 'Sujet non défini') ?></h3>
                                <p style="color:#71717a; margin:0; font-size:0.9rem;"><i class="fa-solid fa-building" style="margin-right:6px;"></i><?= h($stg['entreprise'] ?: 'Entreprise non renseignée') ?></p>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <?php if (!empty($stg['convention_url'])): ?>
                                    <a href="<?= h($stg['convention_url']) ?>" target="_blank" class="btn-icon-sm" title="Convention"><i class="fa-solid fa-file-contract"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($stg['rapport_url'])): ?>
                                    <a href="<?= h($stg['rapport_url']) ?>" target="_blank" class="btn-icon-sm" title="Rapport"><i class="fa-solid fa-file-pdf"></i></a>
                                <?php endif; ?>
                                <a href="print_convention_stage.php?id=<?= (int)$stg['id_stage'] ?>&auto=1" target="_blank" class="btn-icon-sm" title="Imprimer convention"><i class="fa-solid fa-print"></i></a>
                                <!-- Edit button removed: management moved to Stages tab -->

                                <form method="post" style="display:inline;" class="hub-ajax-form" onsubmit="return gdsConfirmDelete(event, 'Supprimer ce stage ?');">
                                    <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                                    <input type="hidden" name="id_stage" value="<?= (int)$stg['id_stage'] ?>">
                                    <button type="submit" name="quick_delete_stage" value="1" class="btn-icon-sm danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php if ($dd || $df): ?>
                        <div style="margin-top:16px;">
                            <div style="display:flex; justify-content:space-between; color:#71717a; font-size:0.8rem; margin-bottom:6px;">
                                <span><i class="fa-regular fa-calendar"></i> <?= $dd ? date('d/m/Y', strtotime($dd)) : '—' ?></span>
                                <span style="font-weight:700; color:#a1a1aa;"><?= $prog ?>%</span>
                                <span><i class="fa-regular fa-calendar-check"></i> <?= $df ? date('d/m/Y', strtotime($df)) : '—' ?></span>
                            </div>
                            <div style="background:rgba(255,255,255,0.05); border-radius:50px; height:6px; overflow:hidden;">
                                <div style="width:<?= $prog ?>%; height:100%; background:linear-gradient(90deg, #3b82f6, #a855f7); border-radius:50px; transition:width 0.5s ease;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($stg['note_stage'] !== null || $stg['jury'] || $stg['evaluation_entreprise']): ?>
                        <div style="margin-top:12px; display:flex; gap:16px; flex-wrap:wrap; color:#a1a1aa; font-size:0.85rem;">
                            <?php if ($stg['note_stage'] !== null): ?>
                                <span><i class="fa-solid fa-star" style="color:#facc15;"></i> Note: <strong style="color:#fff;"><?= number_format((float)$stg['note_stage'], 2) ?>/20</strong></span>
                            <?php endif; ?>
                            <?php if ($stg['date_soutenance']): ?>
                                <span><i class="fa-solid fa-podium" style="color:#a855f7;"></i> Soutenance: <strong style="color:#fff;"><?= date('d/m/Y', strtotime($stg['date_soutenance'])) ?></strong></span>
                            <?php endif; ?>
                            <?php if ($stg['jury']): ?>
                                <span><i class="fa-solid fa-users" style="color:#3b82f6;"></i> Jury: <strong style="color:#fff;"><?= h($stg['jury']) ?></strong></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- TAB: DOCUMENTS -->
            <!-- TAB: COTISATIONS -->
            <div id="hub-cotisations" class="hub-tab-pane">
                <div class="detailed-tab-header">
                    <h2>💰 Cotisations & Paiements</h2>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <?php
                        $cotisLink = 'cotisations.php?' . http_build_query([
                            'annee'      => $selectedStudent['annee_scolaire'] ?? '',
                            'id_filiere' => $selectedStudent['id_filiere']     ?? '',
                            'niveau'     => $selectedStudent['niveau_classe']  ?? '',
                            'id_classe'  => $selectedStudent['id_classe']      ?? '',
                            'mois'       => date('Y-m'),
                            'highlight'  => $selectedStudent['id_stagiaire']   ?? '',
                        ]);
                        ?>
                        <a href="<?= h($cotisLink) ?>" class="btn-hub-action ghost small" style="border-color:rgba(168,85,247,0.4);color:#c084fc;"><i class="fa-solid fa-layer-group"></i> Gestion centralisée</a>
                    </div>
                </div>

                <?php
                // Filière label
                $nomFilLabel = 'N/A';
                foreach($filieresList as $fl){ if((int)$fl['id_filiere']===$fidHub){ $nomFilLabel=$fl['nom_filiere']; break; } }
                ?>

                <!-- Tarif banner -->
                <div style="background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.25); border-radius:12px; padding:0.85rem 1.2rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <i class="fa-solid fa-tag" style="color:#818cf8;"></i>
                    <span style="color:#a1a1aa; font-size:0.85rem;">Tarif mensuel appliqué :</span>
                    <strong style="color:#e4e4e7; font-size:1rem;"><?= number_format($montantDuHub,2) ?> MAD</strong>
                    <?php if($isExceptionTarif): ?>
                        <span style="background:rgba(251,191,36,0.15); color:#fbbf24; border:1px solid rgba(251,191,36,0.3); border-radius:20px; padding:2px 10px; font-size:0.75rem; font-weight:700;">⚡ Tarif exceptionnel</span>
                    <?php else: ?>
                        <span style="color:#71717a; font-size:0.8rem;">(tarif standard <?= h($nomFilLabel) ?>)</span>
                    <?php endif; ?>
                </div>

                <!-- KPI Summary cards -->
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.5rem;">
                    <!-- Statut global -->
                    <div style="flex:1; min-width:130px; background:rgba(<?= $globalPayStatus==='paye'?'52,211,153':($globalPayStatus==='partiel'?'251,146,60':($globalPayStatus==='impaye'?'248,113,113':'161,161,170')) ?>,0.12); border:1px solid rgba(<?= $globalPayStatus==='paye'?'52,211,153':($globalPayStatus==='partiel'?'251,146,60':($globalPayStatus==='impaye'?'248,113,113':'161,161,170')) ?>,0.3); border-radius:12px; padding:0.9rem 1rem;">
                        <div style="font-size:0.7rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px;">Statut global</div>
                        <div style="font-size:1.05rem; font-weight:700; color:<?= $globalPayStatus==='paye'?'#34d399':($globalPayStatus==='partiel'?'#fb923c':($globalPayStatus==='impaye'?'#f87171':'#a1a1aa')) ?>;">
                            <?php if($globalPayStatus==='paye') echo '✅ À jour'; elseif($globalPayStatus==='partiel') echo '⚠️ Partiel'; elseif($globalPayStatus==='impaye') echo '❌ Impayé'; else echo '— Aucun'; ?>
                        </div>
                    </div>
                    <!-- Mois enregistrés -->
                    <div style="flex:1; min-width:110px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:0.9rem 1rem;">
                        <div style="font-size:0.7rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px;">Mois enregistrés</div>
                        <div class="mini-kpi-mois" style="font-size:1.05rem; font-weight:700; color:#e4e4e7;"><?= $totalMens ?></div>
                    </div>
                    <!-- Total payé -->
                    <div style="flex:1; min-width:110px; background:rgba(52,211,153,0.07); border:1px solid rgba(52,211,153,0.2); border-radius:12px; padding:0.9rem 1rem;">
                        <div style="font-size:0.7rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px;">Total payé</div>
                        <div class="mini-kpi-total-paye" style="font-size:1.05rem; font-weight:700; color:#34d399;"><?= number_format($totalPaye,2) ?> MAD</div>
                    </div>

                    <?php if($totalRestant > 0): ?>
                    <!-- Cumul restant -->
                    <div style="flex:1; min-width:130px; background:rgba(248,113,113,0.08); border:1px solid rgba(248,113,113,0.25); border-radius:12px; padding:0.9rem 1rem;">
                        <div style="font-size:0.7rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px;">Cumul restant</div>
                        <div style="font-size:1.05rem; font-weight:700; color:#f87171;"><?= number_format($totalRestant,2) ?> MAD</div>
                    </div>
                    <?php endif; ?>
                    <!-- Mois payés / partiels / impayés -->
                    <div style="flex:1; min-width:130px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:12px; padding:0.9rem 1rem;">
                        <div style="font-size:0.7rem; color:#a1a1aa; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;">Répartition</div>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <span class="mini-kpi-repartition" style="background:rgba(52,211,153,0.15); color:#34d399; border-radius:20px; padding:2px 8px; font-size:0.78rem; font-weight:700;">✅ <?= $payeCount ?></span>
                            <span class="mini-kpi-repartition" style="background:rgba(251,146,60,0.15); color:#fb923c; border-radius:20px; padding:2px 8px; font-size:0.78rem; font-weight:700;">⚠️ <?= $partielCount ?></span>
                            <span class="mini-kpi-repartition" style="background:rgba(248,113,113,0.15); color:#f87171; border-radius:20px; padding:2px 8px; font-size:0.78rem; font-weight:700;">❌ <?= $impayeCount ?></span>
                        </div>
                    </div>
                </div>

                <!-- Cotisations filter bar -->
                <div style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;padding:0.75rem 1rem;background:rgba(168,85,247,0.05);border:1px solid rgba(168,85,247,0.15);border-radius:10px;">
                    <i class="fa-solid fa-filter" style="color:#a855f7;font-size:0.85rem;align-self:center;"></i>
                    <div style="display:flex;flex-direction:column;gap:0.25rem;">
                        <label style="font-size:0.7rem;color:#71717a;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Mois</label>
                        <input type="month" id="cotis-filter-month" onchange="applyCotisFilters()" style="background:#0d0d14;border:1px solid rgba(168,85,247,0.3);color:#e4e4e7;border-radius:7px;padding:0.35rem 0.6rem;font-size:0.82rem;color-scheme:dark;">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.25rem;">
                        <label style="font-size:0.7rem;color:#71717a;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Statut</label>
                        <select id="cotis-filter-status" onchange="applyCotisFilters()" style="background:#0d0d14;border:1px solid rgba(168,85,247,0.3);color:#e4e4e7;border-radius:7px;padding:0.35rem 0.6rem;font-size:0.82rem;">
                            <option value="">Tous les statuts</option>
                            <option value="paye">✅ Payé</option>
                            <option value="partiel">⚠️ Partiel</option>
                            <option value="impaye">❌ Impayé</option>
                        </select>
                    </div>
                    <button type="button" onclick="clearCotisFilters()" style="background:transparent;border:1px solid rgba(255,255,255,.1);color:#71717a;border-radius:7px;padding:0.35rem 0.7rem;font-size:0.78rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i> Effacer</button>
                    <span id="cotis-filter-count" style="color:#a855f7;font-size:0.82rem;font-weight:600;margin-left:auto;align-self:center;"></span>
                </div>

                <!-- Mensualités table -->
                <div class="card detail-table-card" style="overflow-x:auto;">
                    <table class="data-v2" id="cotis-table">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th style="text-align:center;">Statut</th>
                                <th style="text-align:right;">Montant dû</th>
                                <th style="text-align:right;">Payé</th>
                                <th style="text-align:right;">Restant</th>
                                <th style="text-align:right;">Cumul restant</th>
                                <th>Date paiement</th>
                                <th style="text-align:center;">Voir</th>
                            </tr>
                        </thead>
                        <tbody id="cotis-tbody">
                        <?php if (empty($hubMensualites)): ?>
                            <tr id="cotis-empty-row">
                                <td colspan="8" style="text-align:center; color:#71717a; padding:2.5rem 1rem;">
                                    <i class="fa-solid fa-calendar-xmark" style="font-size:2rem; margin-bottom:0.75rem; display:block; color:#3f3f46;"></i>
                                    Aucune cotisation enregistrée.<br>
                                    <span style="font-size:0.85rem;">Utilisez la page <strong>Gestion centralisée</strong> pour enregistrer des paiements.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($hubMensualites as $men):
                            $sp = (string)($men['statut_paiement'] ?? '');
                            $isPaye   = (int)$men['est_paye'] === 1 || $sp === 'payé';
                            $isPartiel= $sp === 'partiel';
                            $isImpaye = !$isPaye && !$isPartiel;
                            $statusLabel = $isPaye ? 'Payé' : ($isPartiel ? 'Partiel' : 'Impayé');
                            $statusColor = $isPaye ? '#34d399' : ($isPartiel ? '#fb923c' : '#f87171');
                            $statusBg    = $isPaye ? 'rgba(52,211,153,0.12)' : ($isPartiel ? 'rgba(251,146,60,0.12)' : 'rgba(248,113,113,0.12)');
                            $moisLabel   = date('M Y', strtotime($men['mois_ref'].'-01'));
                            $restant     = (float)($men['montant_restant'] ?? 0);
                            $cumul       = (float)($men['cumul_restant'] ?? 0);
                            $mTotal      = $men['montant_total'] !== null ? (float)$men['montant_total'] : $montantDuHub;
                            $mPaye       = $men['montant_paye'] !== null ? (float)$men['montant_paye'] : 0;
                            // encode row data for edit button
                            $rowJson = htmlspecialchars(json_encode([
                                'mois_ref'        => $men['mois_ref'],
                                'statut'          => $sp ?: ($isPaye ? 'payé' : 'impayé'),
                                'montant_total'   => $mTotal,
                                'montant_paye'    => $men['montant_paye'],
                                'montant_restant' => $men['montant_restant'],
                                'date_paiement'   => $men['date_paiement'] ? substr($men['date_paiement'],0,10) : '',
                            ]), ENT_QUOTES);
                        ?>
                            <tr data-mois-ref="<?= h($men['mois_ref']) ?>" data-status="<?= $isPaye ? 'paye' : ($isPartiel ? 'partiel' : 'impaye') ?>">
                                <td style="font-weight:600; white-space:nowrap;"><?= h($moisLabel) ?></td>
                                <td style="text-align:center;">
                                    <span style="background:<?= $statusBg ?>; color:<?= $statusColor ?>; border:1px solid <?= $statusColor ?>40; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; white-space:nowrap;">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td style="text-align:right; color:#a1a1aa; white-space:nowrap;"><?= number_format($mTotal,2) ?> MAD</td>
                                <td style="text-align:right; color:#34d399; white-space:nowrap;"><?= number_format($mPaye,2) ?> MAD</td>
                                <td style="text-align:right; white-space:nowrap; font-weight:<?= $restant>0?'700':'400' ?>; color:<?= $restant>0?'#f87171':'#71717a' ?>;"><?= number_format($restant,2) ?> MAD</td>
                                <td style="text-align:right; white-space:nowrap; color:<?= $cumul>0?'#f87171':'#71717a' ?>; font-size:0.85rem;"><?= number_format($cumul,2) ?> MAD</td>
                                <td style="color:#71717a; font-size:0.85rem; white-space:nowrap;"><?= $men['date_paiement'] ? date('d/m/Y', strtotime($men['date_paiement'])) : '—' ?></td>
                                <td style="text-align:center; white-space:nowrap;">
                                    <a href="print_recu_paiement.php?id=<?= (int)$selectedStudent['id_stagiaire'] ?>&mois=<?= h($men['mois_ref']) ?>&auto=1" target="_blank" class="btn-hub-action ghost small" title="Imprimer reçu de paiement" style="text-decoration:none;">
                                        <i class="fa-solid fa-receipt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.03);">
                                <td colspan="2" style="font-weight:700; color:#e4e4e7; padding:0.6rem 0.75rem;">TOTAUX</td>
                                <td></td>
                                <td style="text-align:right; font-weight:700; color:#34d399;"><?= number_format($totalPaye,2) ?> MAD</td>
                                <td style="text-align:right; font-weight:700; color:<?= $totalRestant>0?'#f87171':'#71717a' ?>;"><?= number_format($totalRestant,2) ?> MAD</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div id="hub-docs" class="hub-tab-pane">
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
                    
                    <a href="print_recu_paiement.php?id=<?= $sid_doc ?>&mois=<?=h(date('Y-m'))?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon pink"><i class="fa-solid fa-receipt"></i></div><span>Reçu de Paiement</span></a>
                    <a href="print_etat_paiement.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon" style="background:rgba(16,185,129,0.15);color:#10b981;"><i class="fa-solid fa-file-invoice-dollar"></i></div><span>État Général des Paiements</span></a>
                    <a href="print_rapport_individuel.php?id=<?= $sid_doc ?>&auto=1" target="_blank" class="doc-v2-link"><div class="doc-v2-icon" style="background:rgba(168,85,247,0.15);color:#a855f7;"><i class="fa-solid fa-file-lines"></i></div><span>Rapport Individuel</span></a>
                    <?php foreach($hubStages as $stgDoc): ?>
                        <a href="print_convention_stage.php?id=<?= (int)$stgDoc['id_stage'] ?>&auto=1" target="_blank" class="doc-v2-link">
                            <div class="doc-v2-icon blue"><i class="fa-solid fa-file-contract"></i></div>
                            <span>Convention <?= $stgDoc['type_stage'] === 'pfe' ? 'PFE' : 'Stage' ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach(array_filter($absences, fn($a) => (int)$a['est_justifiee']) as $absDoc): ?>
                        <a href="print_billet_excuse.php?id=<?= (int)$absDoc['id_absence'] ?>&auto=1" target="_blank" class="doc-v2-link">
                            <div class="doc-v2-icon red"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                            <span>Billet Excuse <?= date('d/m', strtotime($absDoc['date_absence'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($hubDocHistory)): ?>
                <div class="detailed-tab-header" style="margin-top:2.5rem;">
                    <h2>Historique des Impressions</h2>
                    <form method="post" class="hub-ajax-form" data-confirm="Effacer tout l'historique des impressions ?">
                        <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                        <button type="submit" name="clear_doc_history" value="1" class="btn-hub-action ghost small"><i class="fa-solid fa-broom"></i> Tout effacer</button>
                    </form>
                </div>
                <div class="card detail-table-card">
                    <table class="data-v2">
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Type de document</th>
                                <th>Référence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hubDocHistory as $dh): ?>
                            <tr>
                                <td style="color:#71717a; font-size:0.85rem;"><?= date('d/m/Y H:i', strtotime($dh['genere_le'])) ?></td>
                                <td style="font-weight:600; color:#e4e4e7;"><?= h($dh['type_document']) ?></td>
                                <td style="color:#71717a;"><?= h($dh['reference'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div>

            <!-- TAB: PARCOURS (Audit Trail) -->
            <div id="hub-parcours" class="hub-tab-pane">
                <div class="detailed-tab-header">
                    <h2>Parcours &amp; Historique des changements</h2>
                    <a href="audit_trail.php?q=<?= urlencode((string)($selectedStudent['num_inscri'] ?? '')) ?>" style="color:#a855f7; font-size:0.82rem; font-weight:700; text-decoration:none;">
                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i> Journal global
                    </a>
                </div>
                <?php if (empty($hubAuditTrail)): ?>
                    <div style="text-align:center; padding:3rem 1rem; color:#52525b;">
                        <i class="fa-solid fa-timeline" style="font-size:2rem; display:block; margin-bottom:0.75rem; color:#3f3f46;"></i>
                        <p style="margin:0; font-size:0.9rem;">Aucun changement structurel enregistré pour ce stagiaire.</p>
                        <p style="margin:0.4rem 0 0; font-size:0.78rem; color:#3f3f46;">Les changements de classe, filière ou année scolaire apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                <div class="parcours-timeline">
                    <?php
                    $champLabels = [
                        'classe'         => ['Classe',          'fa-chalkboard-user', '#a855f7'],
                        'filiere'        => ['Filière',         'fa-sitemap',         '#60a5fa'],
                        'annee_scolaire' => ['Année scolaire',  'fa-calendar-days',   '#fbbf24'],
                    ];
                    foreach ($hubAuditTrail as $ht):
                        $champ = (string)$ht['champ'];
                        $cl    = $champLabels[$champ] ?? [ucfirst(str_replace('_',' ',$champ)), 'fa-pen', '#71717a'];
                    ?>
                    <div class="parcours-item">
                        <div class="parcours-dot" style="background:<?= $cl[2] ?>22; color:<?= $cl[2] ?>; border-color:<?= $cl[2] ?>55;">
                            <i class="fa-solid <?= $cl[1] ?>" style="font-size:0.75rem;"></i>
                        </div>
                        <div class="parcours-body">
                            <div class="parcours-label" style="color:<?= $cl[2] ?>;"><?= h($cl[0]) ?></div>
                            <div class="parcours-change">
                                <span class="parcours-val old"><?= h((string)($ht['ancien'] ?? '—')) ?></span>
                                <i class="fa-solid fa-arrow-right" style="font-size:0.7rem; color:#52525b;"></i>
                                <span class="parcours-val new"><?= h((string)($ht['nouveau'] ?? '—')) ?></span>
                            </div>
                            <?php if (!empty($ht['note'])): ?>
                            <div class="parcours-note"><i class="fa-solid fa-note-sticky" style="font-size:0.7rem; margin-right:0.3rem;"></i><?= h((string)$ht['note']) ?></div>
                            <?php endif; ?>
                            <div class="parcours-date"><?= date('d/m/Y à H:i', strtotime((string)$ht['change_le'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
<?php endif; ?>

<style>
.parcours-timeline { display:flex; flex-direction:column; gap:0; padding:0.5rem 0; }
.parcours-item     { display:flex; gap:1.25rem; align-items:flex-start; position:relative; padding-bottom:1.5rem; }
.parcours-item:not(:last-child)::before {
    content:''; position:absolute; left:19px; top:40px; bottom:0;
    width:1px; background:rgba(255,255,255,0.06);
}
.parcours-dot {
    min-width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    border:1px solid; flex-shrink:0; margin-top:2px;
}
.parcours-body  { flex:1; padding-top:6px; }
.parcours-label { font-size:0.7rem; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:0.4rem; }
.parcours-change{ display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.3rem; }
.parcours-val   { font-size:0.88rem; font-weight:600; padding:0.2rem 0.6rem; border-radius:6px; }
.parcours-val.old  { background:rgba(248,113,113,0.1);  color:#f87171; text-decoration:line-through; opacity:0.8; }
.parcours-val.new  { background:rgba(74,222,128,0.1);   color:#4ade80; }
.parcours-note  { font-size:0.78rem; color:#a1a1aa; font-style:italic; margin-bottom:0.25rem; }
.parcours-date  { font-size:0.72rem; color:#52525b; }

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
            <span>ANNÉE SCOLAIRE</span>
            <select id="flt-stag-annee" data-default-annee="<?= h($currentAnnee) ?>">
                <option value="">— Toutes —</option>
                <?php foreach ($anneesList as $an): ?>
                    <option value="<?= h($an) ?>"
                        <?= ($an === $navAnnee) ? 'selected' : '' ?>>
                        <?= h($an) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>FILIÈRE</span>
            <select id="flt-stag-filiere">
                <option value="">— Toutes —</option>
                <?php foreach ($filieresList as $fp): ?>
                    <option value="<?= (int) $fp['id_filiere'] ?>" <?= ($navFiliere === (int)$fp['id_filiere']) ? 'selected' : '' ?>><?= h(gds_fix_text((string) $fp['nom_filiere'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>NIVEAU</span>
            <select id="flt-stag-niveau" disabled
                data-selected-niveau="<?= h($navNiveau) ?>">
                <option value="">— Tous —</option>
            </select>
        </label>
        <label class="gds-filter-bar__field">
            <span>CLASSE</span>
            <select id="flt-stag-classe" disabled
                data-selected-classe="<?= $navClasse ?>"
                data-all-classes='<?= htmlspecialchars(json_encode(array_map(fn($c) => ['id' => (int)$c['id_classe'], 'fid' => (int)$c['id_filiere'], 'annee' => (string)$c['annee_scolaire'], 'niveau' => (string)($c['niveau'] ?? ''), 'label' => $c['nom_classe']], $classes)), ENT_QUOTES) ?>'>
                <option value="">— Toutes —</option>
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
        <div class="gds-filter-bar__field">
            <button id="btn-print-liste" class="btn" style="width:100%; background:rgba(168,85,247,0.15); color:#d8b4fe; border:1px solid rgba(168,85,247,0.3);" onclick="gdsOpenPrintListe()"><i class="fa-solid fa-print"></i> Imprimer liste</button>
        </div>
        <div class="gds-filter-bar__field">
            <button id="btn-print-impaye" class="btn" style="width:100%; background:rgba(248,113,113,0.15); color:#f87171; border:1px solid rgba(248,113,113,0.3);" onclick="gdsOpenPrintImpaye()"><i class="fa-solid fa-triangle-exclamation"></i> Imprimer impayés</button>
        </div>
    </div>
</section>

<div id="blank-state" class="card" style="display:none; text-align:center; padding: 5rem 1rem;">
    <h3 style="color: #a1a1aa;">Veuillez choisir une filière ou une classe</h3>
</div>

<div id="empty-state" class="card" style="display:none; text-align:center; padding: 4rem 1rem;">
    <h3 style="color: #a1a1aa;">Aucun stagiaire trouvé</h3>
</div>

<div class="card table-container" id="liste-stagiaires" style="padding:0;">
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
            <tr data-filterable="1" data-id="<?= $r['id_stagiaire']?>" data-filiere="<?= $r['id_filiere']?>" data-classe="<?= $r['id_classe']?>" data-annee="<?= h((string)$r['annee_scolaire'])?>" data-niveau="<?= h((string)($r['niveau'] ?? ''))?>" data-name="<?= h($rowName)?>" data-num_inscri="<?= h($r['num_inscri'])?>" class="clickable-row js-open-hub" style="cursor:pointer;">
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
// ── Global: Filière+Année → Classe cascade (used on list page AND hub page) ──
function rebuildFormClasses() {
    const formCl    = document.getElementById('form-classe-select');
    const formFil   = document.getElementById('form-filiere-select');
    const formAnnee = document.getElementById('form-annee-select');
    if (!formCl) return;
    const fid   = formFil  ? formFil.value  : '';
    const annee = formAnnee ? formAnnee.value : '';
    const allClasses = JSON.parse(formCl.dataset.allClasses || '[]');
    formCl.innerHTML = '<option value="">— Choisir classe —</option>';
    // No filière selected → keep classe disabled and empty
    if (fid === '') { formCl.disabled = true; return; }
    const filtered = allClasses.filter(function(c) {
        var anneeOk = (annee === '' || String(c.annee) === String(annee));
        return anneeOk && String(c.fid) === String(fid);
    });
    if (filtered.length === 0) { formCl.disabled = true; return; }
    filtered.forEach(function(c) {
        var opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.label;
        formCl.appendChild(opt);
    });
    formCl.disabled = false;
}
// ────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    const modalOverlay = document.getElementById('modal-overlay');
    const fabButton = document.getElementById('fab-add');
    const closeBtns = document.querySelectorAll('.js-close-modal');
    const blankState = document.getElementById('blank-state');
    const emptyState = document.getElementById('empty-state');
    const tableContainer = document.getElementById('liste-stagiaires');
    const fltAnnee   = document.getElementById('flt-stag-annee');
    const fltFiliere = document.getElementById('flt-stag-filiere');
    const fltNiveau  = document.getElementById('flt-stag-niveau');
    const fltClasse  = document.getElementById('flt-stag-classe');
    const fltSort    = document.getElementById('flt-stag-sort');
    const searchInput = document.getElementById('flt-stag-search');
    const countEl    = document.getElementById('flt-stag-count');
    const resetBtn   = document.getElementById('btn-reset-filters');

    // ── Sync table/emptyState visibility based on visible row count ──────────
    function syncEmptyState() {
        if (!countEl || !tableContainer || !emptyState) return;
        const count = parseInt(countEl.textContent.trim(), 10) || 0;
        tableContainer.style.display = count === 0 ? 'none' : 'block';
        emptyState.style.display     = count === 0 ? 'block' : 'none';
        if (blankState) blankState.style.display = 'none';
    }

    if (countEl) {
        const observer = new MutationObserver(syncEmptyState);
        observer.observe(countEl, { childList: true, characterData: true, subtree: true });
    }

    // ── Rebuild the FILTER BAR niveau dropdown (annee + filiere aware) ────────
    function rebuildFilterNiveaux() {
        if (!fltNiveau) return;
        const annee = fltAnnee   ? fltAnnee.value   : '';
        const fid   = fltFiliere ? fltFiliere.value  : '';
        const allClasses = JSON.parse(fltClasse ? fltClasse.dataset.allClasses || '[]' : '[]');
        const currentVal = fltNiveau.value;
        const niveauxSeen = new Set();
        allClasses.forEach(function(c) {
            const anneeOk = (annee === '' || String(c.annee) === String(annee));
            const filOk   = (fid   === '' || String(c.fid)  === String(fid));
            if (anneeOk && filOk && c.niveau) niveauxSeen.add(String(c.niveau));
        });
        fltNiveau.innerHTML = '<option value="">— Tous —</option>';
        fltNiveau.disabled = (fid === '');
        Array.from(niveauxSeen).sort().forEach(function(nv) {
            const opt = document.createElement('option');
            opt.value = nv;
            opt.textContent = nv;
            if (nv === currentVal) opt.selected = true;
            fltNiveau.appendChild(opt);
        });
    }

    // ── Rebuild the FILTER BAR classe dropdown (annee + filiere + niveau aware) ─
    function rebuildFilterClasses() {
        if (!fltClasse) return;
        const annee  = fltAnnee   ? fltAnnee.value   : '';
        const fid    = fltFiliere ? fltFiliere.value  : '';
        const niveau = fltNiveau  ? fltNiveau.value   : '';
        const allClasses = JSON.parse(fltClasse.dataset.allClasses || '[]');
        const currentVal = fltClasse.value;
        fltClasse.innerHTML = '<option value="">— Toutes —</option>';
        // Classe requires at minimum a filière to be selected
        fltClasse.disabled = (fid === '');
        allClasses.forEach(function(c) {
            const anneeOk  = (annee  === '' || String(c.annee)  === String(annee));
            const filOk    = (fid    === '' || String(c.fid)    === String(fid));
            const niveauOk = (niveau === '' || String(c.niveau) === String(niveau));
            if (anneeOk && filOk && niveauOk) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.dataset.filiere = c.fid;
                opt.dataset.annee   = c.annee;
                opt.dataset.niveau  = c.niveau;
                opt.textContent = c.label;
                if (String(c.id) === String(currentVal)) opt.selected = true;
                fltClasse.appendChild(opt);
            }
        });
    }

    // ── Rebuild the FILTER BAR année dropdown (filière-aware) ────────────────
    // Shows only school years that have at least one class in the selected filière.
    function rebuildFilterAnnees() {
        if (!fltAnnee) return;
        const fid = fltFiliere ? fltFiliere.value : '';
        const allClasses = JSON.parse(fltClasse ? fltClasse.dataset.allClasses || '[]' : '[]');
        const anneesSeen = new Set();
        allClasses.forEach(function(c) {
            if (fid === '' || String(c.fid) === String(fid)) {
                if (c.annee) anneesSeen.add(String(c.annee));
            }
        });
        const currentVal = fltAnnee.value;
        Array.from(fltAnnee.options).forEach(function(opt) {
            if (!opt.value) return; // keep "— Toutes —"
            const visible = (fid === '' || anneesSeen.has(opt.value));
            opt.hidden   = !visible;
            opt.disabled = !visible;
        });
        // If the selected year is no longer valid for this filière, clear it
        if (currentVal && fid !== '' && !anneesSeen.has(currentVal)) {
            fltAnnee.value = '';
        }
    }

    // ── Sort helper ──────────────────────────────────────────────────────────
    function sortTable() {
        if (!tableContainer) return;
        const order = fltSort ? fltSort.value : 'nom';
        const tbody = tableContainer.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-filterable]'));
        rows.sort(function(a, b) {
            const va = (order === 'num_inscri' ? a.dataset.num_inscri : a.dataset.name) || '';
            const vb = (order === 'num_inscri' ? b.dataset.num_inscri : b.dataset.name) || '';
            return va.localeCompare(vb, undefined, { numeric: true, sensitivity: 'base' });
        });
        rows.forEach(function(r) { tbody.appendChild(r); });
    }

    // ── Row-click links (carry filter state in URL) ──────────────────────────
    function attachLinks() {
        document.querySelectorAll('.js-open-hub').forEach(function(row) {
            row.onclick = function() {
                const a = fltAnnee   ? encodeURIComponent(fltAnnee.value)   : '';
                const f = fltFiliere ? fltFiliere.value : '';
                const c = fltClasse  ? fltClasse.value  : '';
                const s = searchInput ? encodeURIComponent(searchInput.value.trim()) : '';
                const o = fltSort    ? fltSort.value    : 'nom';
                const niv = fltNiveau ? encodeURIComponent(fltNiveau.value) : '';
                window.location.href = 'stagiaires.php?id=' + row.dataset.id +
                    '&a=' + a + '&f=' + f + '&niv=' + niv + '&c=' + c + '&s=' + s + '&o=' + o;
            };
        });
    }

    // ── Wire filter controls — cascade: Année → Filière → Niveau → Classe ──────
    // IMPORTANT: cascade listeners are registered BEFORE gdsTableFilter so they fire
    // first on 'change' events and correct all downstream values before gdsTableFilter
    // reads them. This eliminates the race condition where gdsTableFilter could rerender
    // with stale niveau/classe values and show 0 results.

    // Année changes → reset filière, niveau, classe; rebuild dropdowns
    fltAnnee && fltAnnee.addEventListener('change', function() {
        if (fltFiliere) fltFiliere.value = '';
        if (fltNiveau)  { fltNiveau.value = ''; fltNiveau.disabled = true; }
        if (fltClasse)  { fltClasse.value = ''; fltClasse.disabled = true; }
        rebuildFilterNiveaux();
        rebuildFilterClasses();
        // gdsTableFilter's own 'change' listener fires after us and reads all correct values
        syncEmptyState();
        attachLinks();
    });

    // Filière changes → reset niveau + classe; rebuild dropdowns
    fltFiliere && fltFiliere.addEventListener('change', function() {
        if (fltNiveau) { fltNiveau.value = ''; fltNiveau.disabled = true; }
        if (fltClasse) { fltClasse.value = ''; fltClasse.disabled = true; }
        rebuildFilterAnnees();   // hide years that have no classes for this filière
        rebuildFilterNiveaux();
        rebuildFilterClasses();
        // gdsTableFilter fires after us → reads filiere=NEW, niveau='', classe='' correctly
        syncEmptyState();
        attachLinks();
    });

    // Niveau changes → reset classe; rebuild classe dropdown
    fltNiveau && fltNiveau.addEventListener('change', function() {
        if (fltClasse) { fltClasse.value = ''; }
        rebuildFilterClasses();
        // gdsTableFilter fires after us → reads niveau=NEW, classe='' correctly
        syncEmptyState();
        attachLinks();
    });

    // Classe / search changes → just sync and reattach links
    fltClasse  && fltClasse.addEventListener('change',  function() { syncEmptyState(); attachLinks(); });
    searchInput && searchInput.addEventListener('input',  function() { syncEmptyState(); attachLinks(); });
    searchInput && searchInput.addEventListener('change', function() { syncEmptyState(); attachLinks(); });
    fltSort    && fltSort.addEventListener('change', function() { sortTable(); attachLinks(); });

    // ── Reset button ─────────────────────────────────────────────────────────
    if (resetBtn) {
        resetBtn.onclick = function() {
            if (fltAnnee)    fltAnnee.value = fltAnnee.dataset.defaultAnnee || '';
            if (fltFiliere)  fltFiliere.value = '';
            if (fltNiveau)   { fltNiveau.value = ''; fltNiveau.disabled = true; fltNiveau.innerHTML = '<option value="">— Tous —</option>'; }
            if (fltClasse)   { fltClasse.value = ''; fltClasse.disabled = true; }
            if (searchInput) searchInput.value = '';
            rebuildFilterNiveaux();
            rebuildFilterClasses();
            // Dispatch 'input' (not 'change') on every gdsTableFilter control so it
            // rerenders without triggering cascade 'change' listeners again.
            [fltAnnee, fltFiliere, fltNiveau, fltClasse, searchInput].forEach(function(el) {
                if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
            });
            syncEmptyState();
            attachLinks();
        };
    }

    // ── Initialise gdsTableFilter — registered AFTER cascade listeners ────────
    // This guarantees that on any user 'change' event, our cascade runs first and
    // corrects downstream values before gdsTableFilter rerenders the table.
    if (window.gdsTableFilter) {
        window.gdsTableFilter({
            table:   '#liste-stagiaires-table',
            counter: '#flt-stag-count',
            controls: [
                { selector: '#flt-stag-annee',   field: 'annee',   type: 'equals' },
                { selector: '#flt-stag-filiere', field: 'filiere', type: 'equals' },
                { selector: '#flt-stag-niveau',  field: 'niveau',  type: 'equals' },
                { selector: '#flt-stag-classe',  field: 'classe',  type: 'equals' },
                { selector: '#flt-stag-search',  field: 'search',  type: 'contains', searchFields: ['name', 'num_inscri'] }
            ]
        });
    }

    // ── FAB (Add stagiaire) ──────────────────────────────────────────────────
    if (fabButton) {
        fabButton.onclick = function() {
            document.getElementById('stagiaire-form').reset();
            document.getElementById('modal-title-heading').textContent = 'Ajouter un stagiaire';
            if (formAnnee) { formAnnee.value = formAnnee.dataset.defaultAnnee || ''; formAnnee.style.outline = ''; }
            if (formFil)   { formFil.value = ''; }
            rebuildFormClasses();
            const prev = document.getElementById('form-annee-error');
            if (prev) prev.remove();
            modalOverlay.style.display = 'flex';
        };
    }

    closeBtns.forEach(function(btn) {
        btn.onclick = function() {
            modalOverlay.style.display = 'none';
            const anneeErr = document.getElementById('form-annee-error');
            if (anneeErr) anneeErr.remove();
            if (formAnnee) formAnnee.style.outline = '';
        };
    });

    // ── Initial render ───────────────────────────────────────────────────────
    sortTable();
    // Step 1: rebuild niveau options based on pre-selected année + filière (from URL ?a= ?f=)
    rebuildFilterNiveaux();
    // Step 2: restore pre-selected niveau from URL (?niv=), stored in data-selected-niveau by PHP
    const _savedNiveau = fltNiveau ? fltNiveau.dataset.selectedNiveau : '';
    if (_savedNiveau && fltNiveau) { fltNiveau.value = _savedNiveau; }
    // Step 3: rebuild classe options based on pre-selected année + filière + niveau
    rebuildFilterClasses();
    // Step 4: restore pre-selected classe from URL (?c=), stored in data-selected-classe by PHP
    const _savedClasse = fltClasse ? fltClasse.dataset.selectedClasse : '';
    if (_savedClasse && fltClasse) { fltClasse.value = _savedClasse; }
    // Step 5: trigger gdsTableFilter with all pre-selected values.
    // Use 'input' (not 'change') so our cascade listeners do NOT fire and reset each other.
    if (fltAnnee)   fltAnnee.dispatchEvent(new Event('input', { bubbles: true }));
    if (fltFiliere) fltFiliere.dispatchEvent(new Event('input', { bubbles: true }));
    if (fltNiveau)  fltNiveau.dispatchEvent(new Event('input', { bubbles: true }));
    if (fltClasse)  fltClasse.dispatchEvent(new Event('input', { bubbles: true }));
    syncEmptyState();
    attachLinks();

    // ── Form: Filière → Année → Classe cascade ──────────────────────────────
    // Note: rebuildFormClasses() is defined globally below so triggerModifierHub() can call it too

    const formFil    = document.getElementById('form-filiere-select');
    const formAnnee  = document.getElementById('form-annee-select');
    const formCl     = document.getElementById('form-classe-select');

    formFil?.addEventListener('change', () => {
        rebuildFormClasses();
    });

    formAnnee?.addEventListener('change', () => {
        const err = document.getElementById('form-annee-error');
        if (err) err.remove();
        const anneeEl = document.getElementById('form-annee-select');
        if (anneeEl) anneeEl.style.outline = '';
        rebuildFormClasses();
    });

    // ── Stagiaire form: enforce Filière + Année + Classe before submit ──────
    const stagForm = document.getElementById('stagiaire-form');
    if (stagForm) {
        stagForm.addEventListener('submit', function(e) {
            // Clear previous inline errors
            ['form-filiere-error','form-annee-error','form-classe-error'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.remove();
            });
            ['form-filiere-select','form-annee-select','form-classe-select'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.style.outline = '';
            });

            let blocked = false;

            function showErr(selectId, errId, msg) {
                const el = document.getElementById(selectId);
                if (!el) return;
                el.style.outline = '2px solid #ef4444';
                el.style.borderRadius = '6px';
                const span = document.createElement('span');
                span.id = errId;
                span.style.cssText = 'color:#ef4444;font-size:0.78rem;display:block;margin-top:4px;';
                span.textContent = msg;
                el.parentElement.appendChild(span);
            }

            const filEl   = document.getElementById('form-filiere-select');
            const anneeEl = document.getElementById('form-annee-select');
            const classeEl= document.getElementById('form-classe-select');

            // Secretary mode: structural selects are replaced by a read-only lock block.
            // If none of the selects exist in the DOM, skip structural validation entirely.
            const hasStructuralFields = filEl !== null || anneeEl !== null || classeEl !== null;
            if (hasStructuralFields) {
                if (!filEl || filEl.value === '') {
                    showErr('form-filiere-select', 'form-filiere-error', 'Veuillez sélectionner une filière.');
                    blocked = true;
                }
                if (!anneeEl || anneeEl.value === '') {
                    showErr('form-annee-select', 'form-annee-error', 'Veuillez sélectionner une année scolaire.');
                    blocked = true;
                }
                if (!classeEl || classeEl.value === '' || classeEl.disabled) {
                    showErr('form-classe-select', 'form-classe-error', 'Veuillez sélectionner une classe.');
                    blocked = true;
                }
            }

            if (blocked) {
                e.preventDefault();
                // Scroll to first error
                const firstErr = stagForm.querySelector('[style*="outline"]');
                if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
    // ────────────────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────

    syncEmptyState();
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

// Auto-restore correct tab from URL param _tab or hash on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('_tab') || window.location.hash.replace('#', '');
    if (tabParam && document.getElementById(tabParam)) {
        switchHubTab(null, tabParam);
    }
});

// ============================================================
// GDS HUB UTILITY FUNCTIONS
// ============================================================

// ── Absence filter helpers ──────────────────────────────────────────────────
function applyAbsFilters() {
    const dateVal   = document.getElementById('abs-date-filter')   ? document.getElementById('abs-date-filter').value   : '';
    const statusVal = document.getElementById('abs-status-filter') ? document.getElementById('abs-status-filter').value : '';
    const rows      = document.querySelectorAll('#hub-absences tbody tr');
    let shown = 0;
    rows.forEach(function(tr) {
        if (tr.cells.length < 2) { tr.style.display = ''; return; }
        const dateTxt   = tr.cells[0] ? tr.cells[0].textContent.trim() : '';
        const statusTxt = tr.cells[3] ? tr.cells[3].textContent.trim() : '';
        let dateMatch = true;
        if (dateVal) {
            const p = dateVal.split('-');
            const fmt = p[2] + '/' + p[1] + '/' + p[0]; // yyyy-mm-dd -> dd/mm/yyyy
            dateMatch = dateTxt === fmt;
        }
        const statusMatch = !statusVal || statusTxt.indexOf(statusVal) !== -1;
        const visible = dateMatch && statusMatch;
        tr.style.display = visible ? '' : 'none';
        if (visible) shown++;
    });
    const countEl = document.getElementById('abs-filter-count');
    if (countEl) {
        countEl.textContent = (dateVal || statusVal)
            ? shown + ' absence' + (shown !== 1 ? 's' : '')
            : '';
    }
}
function clearAbsFilters() {
    const df = document.getElementById('abs-date-filter');
    const sf = document.getElementById('abs-status-filter');
    if (df) df.value = '';
    if (sf) sf.value = '';
    document.querySelectorAll('#hub-absences tbody tr').forEach(function(tr) { tr.style.display = ''; });
    const c = document.getElementById('abs-filter-count');
    if (c) c.textContent = '';
}

function applyCotisFilters() {
    const month  = document.getElementById('cotis-filter-month')  ? document.getElementById('cotis-filter-month').value  : '';
    const status = document.getElementById('cotis-filter-status') ? document.getElementById('cotis-filter-status').value : '';
    const rows   = document.querySelectorAll('#cotis-tbody tr[data-mois-ref]');
    let shown = 0;
    rows.forEach(function(tr) {
        const mMatch = !month  || tr.dataset.moisRef === month;
        const sMatch = !status || tr.dataset.status  === status;
        tr.style.display = (mMatch && sMatch) ? '' : 'none';
        if (mMatch && sMatch) shown++;
    });
    const countEl = document.getElementById('cotis-filter-count');
    if (countEl) countEl.textContent = (month || status) ? shown + ' mois affiché' + (shown !== 1 ? 's' : '') : '';
    // Keep empty-state row visible only when all data rows are hidden
    const emptyRow = document.getElementById('cotis-empty-row');
    if (emptyRow) emptyRow.style.display = (rows.length === 0) ? '' : 'none';
}

function clearCotisFilters() {
    const mf = document.getElementById('cotis-filter-month');
    const sf = document.getElementById('cotis-filter-status');
    if (mf) mf.value = '';
    if (sf) sf.value = '';
    document.querySelectorAll('#cotis-tbody tr[data-mois-ref]').forEach(function(tr) { tr.style.display = ''; });
    const c = document.getElementById('cotis-filter-count');
    if (c) c.textContent = '';
}

// Fix 1: Custom in-page confirm dialog (replaces browser confirm())
function gdsConfirmDelete(evt, msg) {
    evt.preventDefault();
    const form = evt.currentTarget || evt.target;
    const existing = document.getElementById('gds-confirm-overlay');
    if (existing) existing.remove();
    const overlay = document.createElement('div');
    overlay.id = 'gds-confirm-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = `
        <div style="background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:2rem;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
            <div style="width:48px;height:48px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:1.3rem;"></i>
            </div>
            <p style="color:#e4e4e7;font-size:1rem;margin:0 0 1.5rem;font-weight:500;">${msg}</p>
            <div style="display:flex;gap:0.75rem;justify-content:center;">
                <button id="gds-confirm-cancel" style="padding:0.6rem 1.5rem;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:transparent;color:#a1a1aa;cursor:pointer;font-size:0.9rem;">Annuler</button>
                <button id="gds-confirm-ok" style="padding:0.6rem 1.5rem;border-radius:8px;border:none;background:#ef4444;color:#fff;cursor:pointer;font-size:0.9rem;font-weight:600;">Supprimer</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    const submitter = evt.submitter || null; // capture the clicked submit button
    document.getElementById('gds-confirm-cancel').onclick = () => overlay.remove();
    document.getElementById('gds-confirm-ok').onclick = () => {
        overlay.remove();
        gdsAjaxForm(form, submitter); // pass it so PHP knows which action to run
    };
    return false;
}

var GDS_CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Fix 1+4: AJAX form submitter with toast notification
// submitter param = the button element that triggered the submit (may be null).
// new FormData(form) does NOT include submit button values when called programmatically,
// so we must append them manually — otherwise PHP never sees quick_save_*/quick_delete_*
// and falls through to redirect(), returning HTML instead of JSON -> "Erreur réseau."
function gdsAjaxForm(form, submitter) {
    const fd = new FormData(form);
    // Manually append the submit button name/value (not included by FormData when no real click)
    if (submitter && submitter.name) {
        fd.append(submitter.name, submitter.value !== undefined ? submitter.value : '1');
    }
    fd.append('csrf_token', GDS_CSRF);
    const submitBtn = submitter || form.querySelector('[type=submit]');
    if (submitBtn) submitBtn.disabled = true;
    fetch('stagiaires.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (submitBtn && submitBtn.disabled !== undefined) submitBtn.disabled = false;
            gdsToast(data.msg || (data.success ? 'Succès.' : 'Erreur.'), data.success ? 'success' : 'error');
            if (data.success) {
                if (fd.has('quick_delete_absence') && fd.get('quick_delete_absence') === '1') {
                    const row = form.closest('tr');
                    if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
                    gdsUpdateOverviewCount('absences', -1);

                } else if (fd.has('quick_delete_stage') && fd.get('quick_delete_stage') === '1') {
                    const card = form.closest('[data-stage-id]');
                    if (card) { card.style.transition = 'opacity 0.3s'; card.style.opacity = '0'; setTimeout(() => card.remove(), 300); }
                    gdsUpdateOverviewCount('stages', -1);
                    // Show empty state if no more stages
                    setTimeout(() => {
                        const pane = document.getElementById('hub-stages');
                        if (pane && !pane.querySelector('[data-stage-id]')) {
                            const hdr = pane.querySelector('.detailed-tab-header');
                            let empty = pane.querySelector('.stages-empty-state');
                            if (!empty) {
                                empty = document.createElement('div');
                                empty.className = 'stages-empty-state';
                                empty.style.cssText = 'text-align:center;padding:3rem;color:#71717a;background:rgba(255,255,255,0.02);border-radius:15px;border:1px dashed rgba(255,255,255,0.08);';
                                empty.innerHTML = '<i class="fa-solid fa-briefcase" style="font-size:2.5rem;opacity:0.2;display:block;margin-bottom:1rem;"></i>Aucun stage ou PFE enregistré pour ce stagiaire.';
                                if (hdr) hdr.insertAdjacentElement('afterend', empty);
                                else pane.appendChild(empty);
                            }
                        }
                    }, 350);

                } else if (fd.has('delete_mensualite') && fd.get('delete_mensualite') === '1') {
                    // Remove the row from cotisations table and reload to recompute totals
                    const row = form.closest('tr');
                    if (row) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(() => { row.remove(); window.location.reload(); }, 400); }
                    else { setTimeout(() => window.location.reload(), 400); }

                } else if (fd.has('clear_doc_history') && fd.get('clear_doc_history') === '1') {
                    // Remove the entire history block (header + table)
                    const docsPane = document.getElementById('hub-docs');
                    if (docsPane) {
                        // Find the "Historique" header (the second detailed-tab-header)
                        const headers = docsPane.querySelectorAll('.detailed-tab-header');
                        const histHeader = headers.length > 1 ? headers[headers.length-1] : null;
                        const histTable  = docsPane.querySelector('.card.detail-table-card');
                        const histForm   = form.closest('form') || form;
                        // Find the wrapping history section — walk up from the form
                        const histSection = form.closest('.detailed-tab-header') || histHeader;
                        [histHeader, histTable].forEach(el => {
                            if (el) { el.style.transition='opacity 0.3s'; el.style.opacity='0'; setTimeout(()=>el.remove(),310); }
                        });
                    }
                    gdsToast('Historique effacé.', 'success');

                } else if (fd.has('quick_save_absence') && fd.get('quick_save_absence') === '1') {
                    document.getElementById('modal-quick-absence').style.display = 'none';
                    const isEdit = fd.get('id_absence_edit') && parseInt(fd.get('id_absence_edit')) > 0;
                    // Inject new absence row OR update existing row
                    if (data.row) {
                        const r = data.row;
                        const tbody = document.querySelector('#hub-absences tbody');
                        if (tbody) {
                            const date = r.date_absence ? r.date_absence.split('-').reverse().join('/') : '—';
                            const hd = r.heure_debut ? r.heure_debut.substring(0,5) : '--:--';
                            const hf = r.heure_fin   ? r.heure_fin.substring(0,5)   : '--:--';
                            const badge = r.est_justifiee
                                ? '<span class="badge badge-success">Justifiée</span>'
                                : '<span class="badge badge-danger">Non justifiée</span>';
                            const absData = JSON.stringify(r).replace(/'/g, "&#39;");
                            const rowHtml = `
                                <td style="font-weight:700;color:#e4e4e7;">${date}</td>
                                <td style="color:#d4d4d8;">${r.nom_module || 'Hors module'}</td>
                                <td style="text-align:center;color:#71717a;">${hd} - ${hf}</td>
                                <td style="text-align:center;">${badge}</td>
                                <td style="color:#71717a;font-style:italic;font-size:0.85rem;">${r.justificatif || '—'}</td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn-icon-sm" title="Modifier" style="margin-right:4px;"
                                        onclick='openEditAbsence(${absData})'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" style="display:inline;" class="hub-ajax-form" onsubmit="return gdsConfirmDelete(event,'Supprimer cette absence ?');" data-confirm-handled>
                                        <input type="hidden" name="id_stagiaire" value="${fd.get('id_stagiaire')}">
                                        <input type="hidden" name="id_absence" value="${r.id_absence}">
                                        <button type="submit" name="quick_delete_absence" value="1" class="btn-icon-sm danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                    ${r.est_justifiee ? '<a href="print_billet_excuse.php?id='+r.id_absence+'&auto=1" target="_blank" class="btn-icon-sm" title="Imprimer billet d\'excuse" style="margin-left:4px;"><i class="fa-solid fa-print"></i></a>' : ''}
                                </td>`;

                            if (isEdit) {
                                // Update existing row in place
                                let found = false;
                                tbody.querySelectorAll('tr').forEach(row => {
                                    const inp = row.querySelector('[name="id_absence"]');
                                    if (inp && parseInt(inp.value) === r.id_absence) {
                                        row.innerHTML = rowHtml;
                                        row.style.transition = 'background 0.4s';
                                        row.style.background = 'rgba(168,85,247,0.08)';
                                        setTimeout(() => { row.style.background = ''; }, 1200);
                                        found = true;
                                    }
                                });
                                if (!found) {
                                    // Row not found (shouldn't happen) — just insert as new
                                    const newRow = document.createElement('tr');
                                    newRow.innerHTML = rowHtml;
                                    tbody.insertBefore(newRow, tbody.firstChild);
                                }
                            } else {
                                // New row — remove empty placeholder first
                                const empty = tbody.querySelector('td[colspan]');
                                if (empty) empty.closest('tr').remove();
                                const newRow = document.createElement('tr');
                                newRow.style.opacity = '0';
                                newRow.innerHTML = rowHtml;
                                tbody.insertBefore(newRow, tbody.firstChild);
                                requestAnimationFrame(() => { newRow.style.transition='opacity 0.4s'; newRow.style.opacity='1'; });
                                gdsUpdateOverviewCount('absences', 1);
                            }
                            // Reset form
                            form.reset();
                            form.querySelector('[name="date_absence"]').value = new Date().toISOString().split('T')[0];
                            document.getElementById('absence-edit-id').value = '';
                        }
                    }
                    // Re-apply filters so new/edited row respects current filter state
                    if (typeof applyAbsFilters === 'function') applyAbsFilters();

                } else if (fd.has('quick_save_stage') && fd.get('quick_save_stage') === '1') {
                    document.getElementById('modal-quick-stage').style.display = 'none';
                    // Inject or update stage card instantly
                    if (data.stage) {
                        const stg = data.stage;
                        const pane = document.getElementById('hub-stages');
                        if (pane) {
                            // Remove empty state placeholder
                            const emptyDiv = pane.querySelector('.stages-empty-state');
                            if (emptyDiv) emptyDiv.remove();

                            const today = new Date().toISOString().split('T')[0];
                            const typeLabel = stg.type_stage === 'pfe' ? 'PFE' : 'Stage Entreprise';
                            let badge = '';
                            if (stg.date_soutenance && today > stg.date_soutenance) {
                                badge = '<span class="badge badge-success">Soutenance passée</span>';
                            } else if (stg.date_fin && today > stg.date_fin && !stg.rapport_url) {
                                badge = '<span class="badge badge-danger">Rapport manquant</span>';
                            } else if (stg.date_fin && today > stg.date_fin) {
                                badge = '<span class="badge badge-success">Terminé</span>';
                            } else if (stg.date_debut && stg.date_fin && today >= stg.date_debut && today <= stg.date_fin) {
                                badge = '<span class="badge" style="background:rgba(59,130,246,0.1);color:#3b82f6;">En cours</span>';
                            } else {
                                badge = '<span class="badge" style="background:rgba(250,204,21,0.1);color:#facc15;">Planifié</span>';
                            }
                            const fmtDate = d => d ? d.split('-').reverse().join('/') : '—';
                            const sid = fd.get('id_stagiaire');

                            if (data.is_edit) {
                                // Replace the existing card
                                const existing = pane.querySelector(`[data-stage-id="${stg.id_stage}"]`);
                                if (existing) existing.remove();
                            }

                            const card = document.createElement('div');
                            card.setAttribute('data-stage-id', stg.id_stage);
                            card.style.cssText = 'background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:24px;margin-bottom:16px;opacity:0;transition:opacity 0.4s;';
                            // Store stage data on the card element for re-edit (avoids JSON-in-onclick issues)
                            card.dataset.stageJson = JSON.stringify(stg);

                            const convBtn  = stg.convention_url ? `<a href="${stg.convention_url}" target="_blank" class="btn-icon-sm" title="Convention"><i class="fa-solid fa-file-contract"></i></a>` : '';
                            const rapBtn   = stg.rapport_url    ? `<a href="${stg.rapport_url}"    target="_blank" class="btn-icon-sm" title="Rapport"><i class="fa-solid fa-file-pdf"></i></a>` : '';
                            const printBtn = `<a href="print_convention_stage.php?id=${stg.id_stage}&auto=1" target="_blank" class="btn-icon-sm" title="Imprimer convention"><i class="fa-solid fa-print"></i></a>`;

                            // Date progress bar
                            let progHtml = '';
                            if (stg.date_debut && stg.date_fin) {
                                const start = new Date(stg.date_debut).getTime();
                                const end   = new Date(stg.date_fin).getTime();
                                const now   = Date.now();
                                const prog  = now >= end ? 100 : (now <= start ? 0 : Math.round(((now-start)/(end-start))*100));
                                progHtml = `<div style="margin-top:16px;">
                                    <div style="display:flex;justify-content:space-between;color:#71717a;font-size:0.8rem;margin-bottom:6px;">
                                        <span><i class="fa-regular fa-calendar"></i> ${fmtDate(stg.date_debut)}</span>
                                        <span style="font-weight:700;color:#a1a1aa;">${prog}%</span>
                                        <span><i class="fa-regular fa-calendar-check"></i> ${fmtDate(stg.date_fin)}</span>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.05);border-radius:50px;height:6px;overflow:hidden;">
                                        <div style="width:${prog}%;height:100%;background:linear-gradient(90deg,#3b82f6,#a855f7);border-radius:50px;transition:width 0.5s ease;"></div>
                                    </div>
                                </div>`;
                            }

                            // Extra info row (note, soutenance, jury)
                            let extraHtml = '';
                            const extraParts = [];
                            if (stg.note_stage !== null && stg.note_stage !== undefined && stg.note_stage !== '') {
                                extraParts.push(`<span><i class="fa-solid fa-star" style="color:#facc15;"></i> Note: <strong style="color:#fff;">${parseFloat(stg.note_stage).toFixed(2)}/20</strong></span>`);
                            }
                            if (stg.date_soutenance) {
                                extraParts.push(`<span><i class="fa-solid fa-podium" style="color:#a855f7;"></i> Soutenance: <strong style="color:#fff;">${fmtDate(stg.date_soutenance)}</strong></span>`);
                            }
                            if (stg.jury) {
                                extraParts.push(`<span><i class="fa-solid fa-users" style="color:#3b82f6;"></i> Jury: <strong style="color:#fff;">${stg.jury}</strong></span>`);
                            }
                            if (extraParts.length) {
                                extraHtml = `<div style="margin-top:12px;display:flex;gap:16px;flex-wrap:wrap;color:#a1a1aa;font-size:0.85rem;">${extraParts.join('')}</div>`;
                            }

                            card.innerHTML = `
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                                    <div>
                                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                            <span style="background:rgba(59,130,246,0.1);color:#3b82f6;padding:4px 10px;border-radius:6px;font-size:0.75rem;font-weight:700;text-transform:uppercase;">${typeLabel}</span>
                                            ${badge}
                                        </div>
                                        <h3 style="color:#fff;font-size:1.1rem;margin:0 0 4px 0;">${stg.sujet || 'Sujet non défini'}</h3>
                                        <p style="color:#71717a;margin:0;font-size:0.9rem;"><i class="fa-solid fa-building" style="margin-right:6px;"></i>${stg.entreprise || 'Entreprise non renseignée'}</p>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        ${convBtn}${rapBtn}${printBtn}
                                        <button type="button" class="btn-icon-sm btn-edit-stage" title="Modifier"><i class="fa-solid fa-pen"></i></button>
                                        <form method="post" style="display:inline;" class="hub-ajax-form" onsubmit="return gdsConfirmDelete(event,'Supprimer ce stage ?');" data-confirm-handled>
                                            <input type="hidden" name="id_stagiaire" value="${sid}">
                                            <input type="hidden" name="id_stage" value="${stg.id_stage}">
                                            <button type="submit" name="quick_delete_stage" value="1" class="btn-icon-sm danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                ${progHtml}
                                ${extraHtml}`;

                            // Insert at top of stages list (after header)
                            const hdr = pane.querySelector('.detailed-tab-header');
                            if (hdr) hdr.insertAdjacentElement('afterend', card);
                            else pane.prepend(card);

                            requestAnimationFrame(() => { card.style.opacity = '1'; });
                            if (!data.is_edit) gdsUpdateOverviewCount('stages', 1);
                            // Reset stage form
                            form.reset();
                        }
                    }
                }
            }
        })
        .catch(err => {
            if (submitBtn && submitBtn.disabled !== undefined) submitBtn.disabled = false;
            gdsToast('Erreur réseau.', 'error');
        });
}

// gdsReloadTab is kept for compatibility but no longer called for save actions.
// Save actions now do instant DOM injection without any page reload.
function gdsReloadTab(tabId) {
    // Intentionally empty — instant DOM updates replace page reloads.
}

// When a document link is clicked, add a row to the print history table instantly
document.addEventListener('click', function(e) {
    const link = e.target.closest('a.doc-v2-link');
    if (!link) return;
    const label = link.querySelector('span')?.textContent?.trim() || 'Document';
    const now = new Date();
    const pad = n => String(n).padStart(2,'0');
    const dateStr = pad(now.getDate())+'/'+pad(now.getMonth()+1)+'/'+now.getFullYear()+' '+pad(now.getHours())+':'+pad(now.getMinutes());

    const tbody = document.querySelector('#hub-docs .card.detail-table-card tbody');
    const histHeader = [...document.querySelectorAll('#hub-docs .detailed-tab-header')].find(h => h.querySelector('h2')?.textContent?.includes('Historique'));

    // If no history section exists yet, create it
    if (!histHeader) {
        const docsPane = document.getElementById('hub-docs');
        if (!docsPane) return;
        const hdr = document.createElement('div');
        hdr.className = 'detailed-tab-header';
        hdr.style.marginTop = '2.5rem';
        hdr.innerHTML = '<h2>Historique des Impressions</h2>';
        const card = document.createElement('div');
        card.className = 'card detail-table-card';
        card.innerHTML = `<table class="data-v2"><thead><tr><th>Date & Heure</th><th>Type de document</th><th>Référence</th></tr></thead><tbody></tbody></table>`;
        docsPane.querySelector('.documents-grid-v2')?.insertAdjacentElement('afterend', hdr);
        hdr.insertAdjacentElement('afterend', card);
    }

    const tb = document.querySelector('#hub-docs .card.detail-table-card tbody');
    if (!tb) return;
    const newRow = document.createElement('tr');
    newRow.style.opacity = '0';
    newRow.innerHTML = `<td style="color:#71717a;font-size:0.85rem;">${dateStr}</td><td style="font-weight:600;color:#e4e4e7;">${label}</td><td style="color:#71717a;">—</td>`;
    tb.insertBefore(newRow, tb.firstChild);
    requestAnimationFrame(() => { newRow.style.transition='opacity 0.4s'; newRow.style.opacity='1'; });
});

// Update mini-card counts in overview tab
function gdsUpdateOverviewCount(type, delta) {
    const cards = document.querySelectorAll('.mini-card');
    cards.forEach(card => {
        const label = card.querySelector('.label');
        if (!label) return;
        if ((type === 'absences' && label.textContent.trim() === 'Absences') ||
            (type === 'stages' && label.textContent.trim() === 'Stages / PFE')) {
            const val = card.querySelector('.value');
            if (val) val.textContent = Math.max(0, (parseInt(val.textContent) || 0) + delta);
        }
    });
}


// Fix 1: Toast notification system
function gdsToast(msg, type) {
    const existing = document.getElementById('gds-toast');
    if (existing) existing.remove();
    const t = document.createElement('div');
    t.id = 'gds-toast';
    const bg = type === 'success' ? 'rgba(52,211,153,0.15)' : 'rgba(239,68,68,0.15)';
    const border = type === 'success' ? 'rgba(52,211,153,0.4)' : 'rgba(239,68,68,0.4)';
    const color = type === 'success' ? '#34d399' : '#ef4444';
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
    t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;background:${bg};border:1px solid ${border};color:${color};padding:0.85rem 1.25rem;border-radius:12px;display:flex;align-items:center;gap:0.6rem;font-weight:600;font-size:0.9rem;box-shadow:0 8px 30px rgba(0,0,0,0.4);transition:opacity 0.4s;max-width:360px;`;
    t.innerHTML = `<i class="fa-solid ${icon}" style="font-size:1.1rem;"></i> <span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3500);
}

// Fix 2: Open print list with current filters
function gdsOpenPrintListe() {
    const fid    = document.getElementById('flt-stag-filiere')?.value || '';
    const niv    = document.getElementById('flt-stag-niveau')?.value  || '';
    const cid    = document.getElementById('flt-stag-classe')?.value  || '';
    const annee  = document.getElementById('flt-stag-annee')?.value   || '';
    const sort   = document.getElementById('flt-stag-sort')?.value    || 'nom';
    const mois   = document.querySelector('input[name="mois"]')?.value || '';
    const params = new URLSearchParams();
    if (annee) params.set('annee_scolaire', annee);
    if (fid)   params.set('id_filiere', fid);
    if (niv)   params.set('niveau', niv);
    if (cid)   params.set('id_classe', cid);
    if (mois)  params.set('mois', mois);
    params.set('sort', sort);
    params.set('auto', '1');
    window.open('print_liste_stagiaires.php?' + params.toString(), '_blank');
}

function gdsOpenPrintImpaye() {
    const fid    = document.getElementById('flt-stag-filiere')?.value || '';
    const niv    = document.getElementById('flt-stag-niveau')?.value  || '';
    const cid    = document.getElementById('flt-stag-classe')?.value  || '';
    const annee  = document.getElementById('flt-stag-annee')?.value   || '';
    const sort   = document.getElementById('flt-stag-sort')?.value    || 'nom';
    const mois   = document.querySelector('input[name="mois"]')?.value || '';
    const params = new URLSearchParams();
    if (annee) params.set('annee_scolaire', annee);
    if (fid)   params.set('id_filiere', fid);
    if (niv)   params.set('niveau', niv);
    if (cid)   params.set('id_classe', cid);
    if (mois)  params.set('mois', mois);
    params.set('sort', sort);
    params.set('impaye', '1');
    params.set('auto', '1');
    window.open('print_liste_impayes.php?' + params.toString(), '_blank');
}

// Fix 4 (CORRECTED): Use event delegation on document so modal forms rendered AFTER
// this script block are also intercepted. The old querySelectorAll at DOMContentLoaded
// ran before the modal HTML was inserted into the DOM, so it never found those forms.
document.addEventListener('DOMContentLoaded', function() {
    // Fix 2: Show/hide print button based on filter state
    const fltAnnee  = document.getElementById('flt-stag-annee');
    const fltFiliere = document.getElementById('flt-stag-filiere');
    const fltClasse = document.getElementById('flt-stag-classe');
    const printBtn = document.getElementById('btn-print-liste');
    function updatePrintBtn() {
        // Print button is always visible — filters affect what gets printed
    }
    fltFiliere?.addEventListener('change', updatePrintBtn);
    fltClasse?.addEventListener('change', updatePrintBtn);
});

// Event delegation: catches .hub-ajax-form submits regardless of when the element was added.
// Forms with onsubmit= (delete confirms) call gdsAjaxForm() directly after user confirms —
// we skip them here to avoid double-submit.
// Event delegation for dynamically-injected stage edit buttons
document.addEventListener('click', function(e) {
    const editBtn = e.target.closest('.btn-edit-stage');
    if (editBtn) {
        const card = editBtn.closest('[data-stage-id]');
        if (card && card.dataset.stageJson) {
            try { openStageModal(JSON.parse(card.dataset.stageJson)); }
            catch(err) { console.error('Stage JSON parse error', err); }
        }
    }
});

document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!form.classList.contains('hub-ajax-form')) return;
    if (form.hasAttribute('onsubmit')) return; // handled by gdsConfirmDelete inline
    e.preventDefault();

    // (stagiaire-form validation is handled by its own submit listener — see below)
    // Handle data-confirm attribute as an inline confirm dialog
    if (form.dataset.confirm) {
        const submitter = e.submitter;
        const existing = document.getElementById('gds-confirm-overlay');
        if (existing) existing.remove();
        const overlay = document.createElement('div');
        overlay.id = 'gds-confirm-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;';
        overlay.innerHTML = `<div style="background:#18181b;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:2rem;max-width:380px;width:90%;text-align:center;">
            <p style="color:#fff;font-size:1rem;margin-bottom:1.5rem;">${form.dataset.confirm}</p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button id="gds-confirm-yes" style="background:#ef4444;color:#fff;border:none;padding:10px 24px;border-radius:8px;cursor:pointer;font-weight:600;">Confirmer</button>
                <button id="gds-confirm-no" style="background:rgba(255,255,255,0.1);color:#fff;border:none;padding:10px 24px;border-radius:8px;cursor:pointer;">Annuler</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        document.getElementById('gds-confirm-no').onclick = () => overlay.remove();
        document.getElementById('gds-confirm-yes').onclick = () => {
            overlay.remove();
            gdsAjaxForm(form, submitter);
        };
        return;
    }
    gdsAjaxForm(form, e.submitter); // pass submitter so PHP knows which action field to check
});

function saveInlineNote(sid, mid, field, val) {
    const input = event.target;
    input.classList.add('saving');
    
    const formData = new FormData();
    formData.append('action', 'save_inline_note');
    formData.append('sid', sid);
    formData.append('mid', mid);
    formData.append('field', field);
    formData.append('val', val);
    formData.append('csrf_token', GDS_CSRF);

    fetch('stagiaires.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
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
    });
}
</script>

<?php if ($selectedStudent): ?>
    <!-- STAGE MODAL REMOVED: Managed in stages.php -->

    <!-- COTISATION MODAL (full) -->
    <div id="modal-cotis" class="modal-overlay" style="display:none; z-index:9999;">
        <div class="modal-card" style="max-width:540px;">
            <div class="modal-header">
                <h2><i class="fa-solid fa-money-bill-transfer"></i> <span id="cotis-modal-title">Enregistrer un paiement</span></h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('modal-cotis').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="gap:1rem; display:flex; flex-direction:column; overflow-y:auto; flex:1; min-height:0;">
                <!-- Info banner -->
                <div id="cotis-info-banner" style="background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.25); border-radius:10px; padding:0.75rem 1rem; font-size:0.85rem; color:#a1a1aa; display:flex; gap:0.75rem; align-items:center;">
                    <i class="fa-solid fa-circle-info" style="color:#818cf8;"></i>
                    <span>Montant standard : <strong id="cotis-tarif-info" style="color:#e4e4e7;"><?= number_format($montantDuHub,2) ?> MAD</strong></span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa; grid-column:span 1;">
                        Mois de référence *
                        <input type="month" id="cotis-mois" required value="<?= date('Y-m') ?>"
                            style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#e4e4e7; padding:8px 12px; border-radius:8px;">
                    </label>
                    <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                        Statut *
                        <select id="cotis-statut" required onchange="cotisUpdateFields()"
                            style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#e4e4e7; padding:8px 12px; border-radius:8px;">
                            <option value="impayé">❌ Impayé</option>
                            <option value="partiel">⚠️ Partiel (paiement incomplet)</option>
                            <option value="payé">✅ Payé (complet)</option>
                        </select>
                    </label>
                </div>

                <!-- MODE AJOUT: shown only when editing a partiel record -->
                <div id="cotis-ajout-section" style="display:none; flex-direction:column; gap:0.75rem; background:rgba(99,102,241,0.07); border:1px solid rgba(99,102,241,0.2); border-radius:10px; padding:1rem;">
                    <div style="font-size:0.82rem; color:#a1a1aa; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fa-solid fa-layer-group" style="color:#818cf8;"></i>
                        <span>Mode <strong style="color:#e4e4e7;">versement partiel</strong> — ce montant s'ajoute au déjà payé</span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                            Déjà payé (MAD)
                            <input type="number" id="cotis-ancien-paye" readonly min="0" step="0.01" value="0"
                                style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); color:#71717a; padding:8px 12px; border-radius:8px; cursor:not-allowed;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                            Restant dû (MAD)
                            <input type="number" id="cotis-restant-affiché" readonly min="0" step="0.01" value="0"
                                style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); color:#f87171; padding:8px 12px; border-radius:8px; cursor:not-allowed;">
                        </label>
                    </div>
                    <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                        Nouveau versement (MAD) *
                        <input type="number" id="cotis-nouveau-versement" min="0" step="0.01" placeholder="Ex: 100"
                            oninput="cotisCapVersement(); cotisAjoutPreview(); cotisCap(this)"
                            style="background:rgba(255,255,255,0.06); border:1px solid rgba(99,102,241,0.4); color:#e4e4e7; padding:8px 12px; border-radius:8px; font-size:1rem;">
                        <span style="font-size:0.76rem; color:#71717a; margin-top:2px;">Limité au restant dû (max : <?= number_format($montantDuHub,2) ?> MAD)</span>
                    </label>
                    <!-- ajout preview -->
                    <div id="cotis-ajout-preview" style="display:none; background:rgba(0,0,0,0.2); border-radius:8px; padding:0.65rem 1rem; font-size:0.82rem; color:#a1a1aa;">
                        Après ce versement : <span id="ajout-new-paye" style="color:#34d399; font-weight:700;"></span> payés,
                        <span id="ajout-new-restant" style="font-weight:700;"></span> restants
                        — <span id="ajout-new-statut" style="font-weight:700;"></span>
                    </div>
                </div>

                <div id="cotis-normal-section" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                        Montant total dû (MAD)
                        <input type="number" id="cotis-montant-total" min="0" step="0.01"
                            value="<?= $montantDuHub ?>"
                            readonly
                            style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); color:#71717a; padding:8px 12px; border-radius:8px; cursor:not-allowed;">
                    </label>
                    <label id="cotis-paye-wrap" style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;">
                        Montant payé (MAD) *
                        <input type="number" id="cotis-montant-paye" min="0" step="0.01" placeholder="0"
                            max="<?= $montantDuHub ?>"
                            oninput="cotisCap(this)"
                            style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#e4e4e7; padding:8px 12px; border-radius:8px;">
                        <span style="font-size:0.76rem; color:#71717a; margin-top:2px;">Max : <?= number_format($montantDuHub,2) ?> MAD (tarif filière)</span>
                    </label>
                </div>

                <!-- Live preview -->
                <div id="cotis-preview" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:0.75rem 1rem; font-size:0.85rem; display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem; display:none;">
                    <div style="text-align:center;">
                        <div style="color:#a1a1aa; font-size:0.72rem; margin-bottom:3px;">DÛ</div>
                        <div id="prev-du" style="font-weight:700; color:#e4e4e7;">—</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="color:#a1a1aa; font-size:0.72rem; margin-bottom:3px;">PAYÉ</div>
                        <div id="prev-paye" style="font-weight:700; color:#34d399;">—</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="color:#a1a1aa; font-size:0.72rem; margin-bottom:3px;">RESTANT</div>
                        <div id="prev-restant" style="font-weight:700; color:#f87171;">—</div>
                    </div>
                </div>

                <label style="display:flex; flex-direction:column; gap:4px; font-size:0.88rem; color:#a1a1aa;" id="cotis-date-wrap">
                    Date de paiement
                    <input type="date" id="cotis-date" value="<?= date('Y-m-d') ?>"
                        style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#e4e4e7; padding:8px 12px; border-radius:8px;">
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" onclick="document.getElementById('modal-cotis').style.display='none'">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="submitCotis()"><i class="fa-solid fa-save"></i> Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
    const cotisStandardMontant = <?= $montantDuHub ?>;
    const cotisSid = <?= (int)$selectedStudent['id_stagiaire'] ?>;

    // ── Live warning: turn input border red when over limit (doesn't clamp — submitCotis blocks instead) ──
    function cotisCap(input) {
        const max = parseFloat(input.max) || cotisStandardMontant;
        const val = parseFloat(input.value) || 0;
        if (val > max) {
            input.style.borderColor = '#ef4444';
            input.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.2)';
        } else {
            input.style.borderColor = '';
            input.style.boxShadow = '';
        }
        cotisUpdatePreview();
    }

    // ── Over-limit: shake the input, highlight it red, show toast ──
    function cotisShowOverLimit(entered, max, inputId) {
        const input = document.getElementById(inputId);
        // Red border flash
        if (input) {
            input.style.borderColor = '#ef4444';
            input.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.25)';
            input.focus();
            setTimeout(() => {
                input.style.borderColor = '';
                input.style.boxShadow = '';
            }, 2500);
        }
        // Toast notification
        gdsToast(
            '⛔ Montant dépassé ! Vous avez saisi ' + parseFloat(entered).toFixed(2) +
            ' MAD — le maximum autorisé pour cette filière est ' + parseFloat(max).toFixed(2) + ' MAD.',
            'error'
        );
    }

    // Tracks whether we're in "add versement" mode
    let cotisIsAjoutMode = false;
    let cotisAncienPaye  = 0;

    function openCotisModal(existingData) {
        // If called with null (new payment button), check if current month already has a partial
        if (!existingData) {
            const currentMois = '<?= date('Y-m') ?>';
            const tbody = document.querySelector('#hub-cotisations table tbody');
            if (tbody) {
                const existingRow = tbody.querySelector(`tr[data-mois-ref="${currentMois}"]`);
                if (existingRow) {
                    // Row found for current month — read its JSON from the edit button
                    const editBtn = existingRow.querySelector('button[onclick*="openCotisModal"]');
                    if (editBtn) {
                        const match = editBtn.getAttribute('onclick').match(/openCotisModal\((.+)\)/);
                        if (match) {
                            try {
                                existingData = JSON.parse(match[1]);
                            } catch(e) {}
                        }
                    }
                }
            }
        }

        const modal = document.getElementById('modal-cotis');
        // Reset everything
        document.getElementById('cotis-montant-total').value = cotisStandardMontant;
        document.getElementById('cotis-montant-paye').value = '';
        document.getElementById('cotis-date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('cotis-statut').value = 'impayé';
        document.getElementById('cotis-mois').value = '<?= date('Y-m') ?>';
        document.getElementById('cotis-modal-title').textContent = 'Enregistrer un paiement';
        document.getElementById('cotis-mois').readOnly = false;
        document.getElementById('cotis-statut').disabled = false;
        cotisIsAjoutMode = false;
        cotisAncienPaye  = 0;

        // Show normal section, hide ajout section
        document.getElementById('cotis-ajout-section').style.display = 'none';
        document.getElementById('cotis-normal-section').style.display = 'grid';
        document.getElementById('cotis-ajout-preview').style.display = 'none';
        if (document.getElementById('cotis-nouveau-versement'))
            document.getElementById('cotis-nouveau-versement').value = '';

        if (existingData) {
            document.getElementById('cotis-mois').value = existingData.mois_ref || '<?= date('Y-m') ?>';
            document.getElementById('cotis-statut').value = existingData.statut || 'impayé';

            // PARTIEL EDIT → switch to "add versement" mode
            if (existingData.statut === 'partiel' &&
                existingData.montant_restant !== null &&
                existingData.montant_restant !== undefined &&
                parseFloat(existingData.montant_restant) > 0) {

                cotisIsAjoutMode = true;
                cotisAncienPaye  = parseFloat(existingData.montant_paye) || 0;
                const restant    = parseFloat(existingData.montant_restant) || 0;

                // Lock mois + statut (will be auto-computed)
                document.getElementById('cotis-mois').readOnly = true;
                document.getElementById('cotis-statut').disabled = true;

                // Fill "déjà payé" and "restant" displays
                document.getElementById('cotis-ancien-paye').value = cotisAncienPaye.toFixed(2);
                document.getElementById('cotis-restant-affiché').value = restant.toFixed(2);
                document.getElementById('cotis-nouveau-versement').value = restant.toFixed(2);

                // Show ajout section, hide normal montant grid
                document.getElementById('cotis-ajout-section').style.display = 'flex';
                document.getElementById('cotis-normal-section').style.display = 'none';

                document.getElementById('cotis-modal-title').textContent = 'Ajouter un versement — ' + existingData.mois_ref;
                cotisAjoutPreview();
            } else {
                // Normal edit (impayé or payé)
                if (existingData.montant_paye !== null && existingData.montant_paye !== undefined && existingData.montant_paye !== '') {
                    document.getElementById('cotis-montant-paye').value = existingData.montant_paye;
                }
                if (existingData.date_paiement) document.getElementById('cotis-date').value = existingData.date_paiement;
                document.getElementById('cotis-modal-title').textContent = 'Modifier cotisation — ' + existingData.mois_ref;
            }
        }

        cotisUpdateFields();
        modal.style.display = 'flex';
    }

    function cotisCapVersement() {
        const el      = document.getElementById('cotis-nouveau-versement');
        const restant = parseFloat(document.getElementById('cotis-restant-affiché').value) || 0;
        const val     = parseFloat(el.value) || 0;
        // Visual warning only — blocking happens in submitCotis
        if (val > restant + 0.001) {
            el.style.borderColor = '#ef4444';
            el.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.2)';
        } else {
            el.style.borderColor = 'rgba(99,102,241,0.4)';
            el.style.boxShadow   = '';
        }
    }

    function cotisAjoutPreview() {
        const nouveau  = parseFloat(document.getElementById('cotis-nouveau-versement').value) || 0;
        const total    = cotisStandardMontant; // always use server-side filière tarif
        const newPaye  = Math.min(cotisAncienPaye + nouveau, total);
        const newRest  = Math.max(0, total - newPaye);
        const preview  = document.getElementById('cotis-ajout-preview');
        const isPayé   = newPaye >= total;

        document.getElementById('ajout-new-paye').textContent    = newPaye.toFixed(2) + ' MAD';
        document.getElementById('ajout-new-restant').textContent = newRest.toFixed(2) + ' MAD';
        document.getElementById('ajout-new-restant').style.color = newRest > 0 ? '#f87171' : '#34d399';
        document.getElementById('ajout-new-statut').textContent  = isPayé ? '✅ Payé' : '⚠️ Partiel';
        document.getElementById('ajout-new-statut').style.color  = isPayé ? '#34d399' : '#fb923c';

        preview.style.display = nouveau > 0 ? 'block' : 'none';
    }

    function cotisUpdateFields() {
        const statut = document.getElementById('cotis-statut').value;
        const payeWrap = document.getElementById('cotis-paye-wrap');
        const dateWrap = document.getElementById('cotis-date-wrap');
        const preview  = document.getElementById('cotis-preview');

        if (statut === 'payé') {
            payeWrap.style.opacity = '0.4';
            payeWrap.style.pointerEvents = 'none';
            dateWrap.style.display = 'flex';
        } else if (statut === 'impayé') {
            payeWrap.style.opacity = '0.4';
            payeWrap.style.pointerEvents = 'none';
            dateWrap.style.display = 'none';
        } else {
            payeWrap.style.opacity = '1';
            payeWrap.style.pointerEvents = '';
            dateWrap.style.display = 'flex';
        }
        cotisUpdatePreview();
    }

    function cotisUpdatePreview() {
        const statut = document.getElementById('cotis-statut').value;
        const mTotal = parseFloat(document.getElementById('cotis-montant-total').value) || cotisStandardMontant;
        const mPaye  = parseFloat(document.getElementById('cotis-montant-paye').value) || 0;
        const preview = document.getElementById('cotis-preview');
        let restant = 0;

        if (statut === 'payé') {
            restant = 0;
            preview.style.display = 'grid';
            document.getElementById('prev-du').textContent = mTotal.toFixed(2) + ' MAD';
            document.getElementById('prev-paye').textContent = mTotal.toFixed(2) + ' MAD';
            document.getElementById('prev-restant').textContent = '0.00 MAD';
            document.getElementById('prev-restant').style.color = '#71717a';
        } else if (statut === 'partiel') {
            restant = Math.max(0, mTotal - mPaye);
            preview.style.display = 'grid';
            document.getElementById('prev-du').textContent = mTotal.toFixed(2) + ' MAD';
            document.getElementById('prev-paye').textContent = mPaye.toFixed(2) + ' MAD';
            document.getElementById('prev-restant').textContent = restant.toFixed(2) + ' MAD';
            document.getElementById('prev-restant').style.color = restant > 0 ? '#f87171' : '#34d399';
        } else {
            restant = mTotal;
            preview.style.display = 'grid';
            document.getElementById('prev-du').textContent = mTotal.toFixed(2) + ' MAD';
            document.getElementById('prev-paye').textContent = '0.00 MAD';
            document.getElementById('prev-restant').textContent = mTotal.toFixed(2) + ' MAD';
            document.getElementById('prev-restant').style.color = '#f87171';
        }
    }

    // Live update preview as user types
    document.addEventListener('DOMContentLoaded', function() {
        ['cotis-montant-total','cotis-montant-paye'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', cotisUpdatePreview);
        });
    });

    function submitCotis() {
        const mois     = document.getElementById('cotis-mois').value;
        const mTotal   = document.getElementById('cotis-montant-total').value;
        const datePaie = document.getElementById('cotis-date').value;

        if (!mois) { alert('Veuillez sélectionner un mois.'); return; }

        const fd = new FormData();
        fd.append('save_mensualite', '1');
        fd.append('id_stagiaire', cotisSid);
        fd.append('mois_ref', mois);
        fd.append('montant_total', mTotal || cotisStandardMontant);
        if (datePaie) fd.append('date_paiement', datePaie);

        if (cotisIsAjoutMode) {
            // MODE AJOUT: send ancien_montant_paye + nouveau_versement
            const nouveau = parseFloat(document.getElementById('cotis-nouveau-versement').value) || 0;
            if (nouveau <= 0) { gdsToast('Veuillez entrer un montant de versement.', 'error'); return; }
            // ── Block if versement would push total paid above filière tarif ──
            const restant = parseFloat(document.getElementById('cotis-restant-affiché').value) || 0;
            const projectedTotal = cotisAncienPaye + nouveau;
            if (projectedTotal > cotisStandardMontant + 0.001) {
                cotisShowOverLimit(projectedTotal, cotisStandardMontant, 'cotis-nouveau-versement');
                return;
            }
            if (nouveau > restant + 0.001) {
                cotisShowOverLimit(nouveau, restant, 'cotis-nouveau-versement');
                return;
            }
            fd.append('mode_ajout', '1');
            fd.append('ancien_montant_paye', cotisAncienPaye);
            fd.append('nouveau_versement', nouveau);
        } else {
            // NORMAL MODE
            const statut = document.getElementById('cotis-statut').value;
            const mPaye  = document.getElementById('cotis-montant-paye').value;
            if (statut === 'partiel' && (!mPaye || parseFloat(mPaye) <= 0)) {
                gdsToast('Pour un paiement partiel, veuillez entrer le montant payé.', 'error');
                return;
            }
            // ── Block if montant_paye exceeds filière tarif ──
            if (statut === 'partiel' && parseFloat(mPaye) > cotisStandardMontant + 0.001) {
                cotisShowOverLimit(parseFloat(mPaye), cotisStandardMontant, 'cotis-montant-paye');
                return;
            }
            fd.append('statut_paiement', statut);
            if (statut === 'partiel') fd.append('montant_paye', mPaye);
        }
        fd.append('csrf_token', GDS_CSRF);

        fetch('stagiaires.php?id=' + cotisSid, { method: 'POST', body: fd })
            .then(r => r.text())
            .then(text => {
                let res;
                try { res = JSON.parse(text); }
                catch(e) {
                    // PHP returned non-JSON (error/warning mixed in output)
                    // Extract just the JSON part if possible
                    const jsonMatch = text.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        try { res = JSON.parse(jsonMatch[0]); }
                        catch(e2) { res = null; }
                    }
                }
                if (res && res.success) {
                    document.getElementById('modal-cotis').style.display = 'none';
                    if (res.row) gdsUpdateCotisRow(res.row);
                    gdsToast(res.msg || 'Cotisation enregistrée.', 'success');
                } else if (res && !res.success) {
                    gdsToast('Erreur : ' + (res.error || res.msg || 'Inconnue'), 'error');
                } else {
                    // Could not parse — but save may have worked. Reload cotisations.
                    document.getElementById('modal-cotis').style.display = 'none';
                    gdsToast('Cotisation enregistrée (rechargement…)', 'success');
                    setTimeout(() => location.reload(), 1200);
                }
            })
            .catch(() => {
                gdsToast('Erreur réseau — vérifiez votre connexion.', 'error');
            });
    }

    // Instantly update or insert a cotisation row in the table without reload
    function gdsUpdateCotisRow(r) {
        const tbody = document.getElementById('cotis-tbody') || document.querySelector('#hub-cotisations tbody');
        if (!tbody) return;
        // Remove empty-state row if present
        const emptyRow = document.getElementById('cotis-empty-row');
        if (emptyRow) emptyRow.remove();

        const isPaye    = r.statut === 'payé';
        const isPartiel = r.statut === 'partiel';
        const sLabel    = isPaye ? 'Payé' : (isPartiel ? 'Partiel' : 'Impayé');
        const sColor    = isPaye ? '#34d399' : (isPartiel ? '#fb923c' : '#f87171');
        const sBg       = isPaye ? 'rgba(52,211,153,0.12)' : (isPartiel ? 'rgba(251,146,60,0.12)' : 'rgba(248,113,113,0.12)');
        const fmtMad    = v => parseFloat(v||0).toFixed(2);
        const fmtDate   = d => d ? d.split('-').reverse().join('/') : '—';
        const restant   = parseFloat(r.montant_restant||0);
        const cumul     = parseFloat(r.cumul_restant||0);
        const mTotal    = parseFloat(r.montant_total||0);
        const mPaye     = parseFloat(r.montant_paye||0);
        const rowJson   = JSON.stringify({
            mois_ref: r.mois_ref, statut: r.statut,
            montant_total: mTotal, montant_paye: mPaye,
            montant_restant: restant, date_paiement: r.date_paiement || ''
        }).replace(/'/g, "&#39;");

        const rowHtml = `
            <td style="font-weight:600;white-space:nowrap;">${r.mois_label || r.mois_ref}</td>
            <td style="text-align:center;">
                <span style="background:${sBg};color:${sColor};border:1px solid ${sColor}40;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;white-space:nowrap;">${sLabel}</span>
            </td>
            <td style="text-align:right;color:#a1a1aa;white-space:nowrap;">${fmtMad(mTotal)} MAD</td>
            <td style="text-align:right;color:#34d399;white-space:nowrap;">${fmtMad(mPaye)} MAD</td>
            <td style="text-align:right;white-space:nowrap;font-weight:${restant>0?'700':'400'};color:${restant>0?'#f87171':'#71717a'};">${fmtMad(restant)} MAD</td>
            <td style="text-align:right;white-space:nowrap;color:${cumul>0?'#f87171':'#71717a'};font-size:0.85rem;">${fmtMad(cumul)} MAD</td>
            <td style="color:#71717a;font-size:0.85rem;white-space:nowrap;">${fmtDate(r.date_paiement)}</td>
            <td style="text-align:center;white-space:nowrap;">
                <button type="button" class="btn-hub-action ghost small" onclick='openCotisModal(${rowJson})' title="Modifier ce mois">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <a href="print_recu_paiement.php?id=${r.id_stagiaire}&mois=${r.mois_ref}&auto=1" target="_blank" class="btn-hub-action ghost small" title="Imprimer reçu de paiement" style="text-decoration:none;">
                    <i class="fa-solid fa-receipt"></i>
                </a>
                <form method="post" style="display:inline;" class="hub-ajax-form" data-confirm="Supprimer la cotisation de ${r.mois_label || r.mois_ref} ?">
                    <input type="hidden" name="id_stagiaire" value="${r.id_stagiaire}">
                    <input type="hidden" name="mois_ref" value="${r.mois_ref}">
                    <button type="submit" name="delete_mensualite" value="1" class="btn-hub-action ghost small" title="Supprimer ce mois" style="color:#f87171;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </td>`;

        // Find existing row using data-mois-ref (reliable, not text-based)
        let existingRow = tbody.querySelector(`tr[data-mois-ref="${r.mois_ref}"]`) || null;

        // (empty-state row already removed above by id)

        if (existingRow) {
            existingRow.setAttribute('data-mois-ref', r.mois_ref);
            existingRow.innerHTML = rowHtml;
            existingRow.style.transition = 'background 0.4s';
            existingRow.style.background = 'rgba(168,85,247,0.1)';
            setTimeout(() => { existingRow.style.background = ''; }, 1200);
        } else {
            const newRow = document.createElement('tr');
            newRow.setAttribute('data-mois-ref', r.mois_ref);
            newRow.innerHTML = rowHtml;
            newRow.style.opacity = '0';
            tbody.appendChild(newRow);
            requestAnimationFrame(() => { newRow.style.transition='opacity 0.4s'; newRow.style.opacity='1'; });
        }

        // Update KPI summary cards
        const allRows = [...tbody.querySelectorAll('tr')];
        let totalPaye = 0, totalRestant = 0, payeC = 0, partielC = 0, impayeC = 0;
        allRows.forEach(tr => {
            if (!tr.cells || tr.cells.length < 5) return;
            const pVal = parseFloat(tr.cells[3]?.textContent) || 0;
            const rVal = parseFloat(tr.cells[4]?.textContent) || 0;
            const badge = tr.cells[1]?.querySelector('span')?.textContent?.trim() || '';
            totalPaye    += pVal;
            totalRestant += rVal;
            if (badge === 'Payé') payeC++;
            else if (badge === 'Partiel') partielC++;
            else impayeC++;
        });

        // Update tfoot totals
        const tfoot = document.querySelector('#hub-cotisations tfoot');
        if (tfoot) {
            const cells = tfoot.querySelectorAll('td');
            if (cells[1]) { cells[1].textContent = totalPaye.toFixed(2) + ' MAD'; cells[1].style.color='#34d399'; }
            if (cells[2]) { cells[2].textContent = totalRestant.toFixed(2) + ' MAD'; cells[2].style.color=totalRestant>0?'#f87171':'#71717a'; }
            // Update KPI summary cards
            const allRows = tbody.querySelectorAll('tr');
            let kpiPaye=0, kpiPartiel=0, kpiImpaye=0, kpiTotalPaye=0, kpiTotalRestant=0;
            allRows.forEach(row => {
                const badge = row.querySelector('td:nth-child(2) span');
                const payeCell = row.querySelector('td:nth-child(4)');
                const restCell = row.querySelector('td:nth-child(5)');
                if (!badge) return;
                const txt = badge.textContent.trim();
                if (txt === 'Payé') kpiPaye++;
                else if (txt === 'Partiel') kpiPartiel++;
                else kpiImpaye++;
                kpiTotalPaye    += parseFloat(payeCell?.textContent) || 0;
                kpiTotalRestant += parseFloat(restCell?.textContent) || 0;
            });
            // Update répartition badges
            const repartSpans = document.querySelectorAll('#hub-cotisations .mini-kpi-repartition span');
            if (repartSpans.length >= 3) { repartSpans[0].textContent='✅ '+kpiPaye; repartSpans[1].textContent='⚠️ '+kpiPartiel; repartSpans[2].textContent='❌ '+kpiImpaye; }
            // Update mois enregistrés
            const moisEl = document.querySelector('#hub-cotisations .mini-kpi-mois');
            if (moisEl) moisEl.textContent = allRows.length;
            // Update total payé KPI
            const totalPayeEl = document.querySelector('#hub-cotisations .mini-kpi-total-paye');
            if (totalPayeEl) totalPayeEl.textContent = totalPaye.toFixed(2) + ' MAD';
        }
    }
    </script>

    <script>
    function openEditAbsence(data) {
        // Populate modal with existing data
        document.getElementById('absence-edit-id').value = data.id_absence;
        document.getElementById('absence-modal-title').textContent = 'Modifier une absence';
        document.getElementById('absence-modal-desc').innerHTML = 'Modification de l\'absence du <strong>' + (data.date_absence ? data.date_absence.split('-').reverse().join('/') : '—') + '</strong>.';

        const form = document.getElementById('absence-form');
        form.querySelector('[name="date_absence"]').value = data.date_absence || '';
        form.querySelector('[name="heure_debut"]').value = data.heure_debut ? data.heure_debut.substring(0,5) : '';
        form.querySelector('[name="heure_fin"]').value = data.heure_fin ? data.heure_fin.substring(0,5) : '';
        form.querySelector('[name="justificatif"]').value = (data.justificatif && data.justificatif !== '—') ? data.justificatif : '';
        form.querySelector('[name="id_module"]').value = data.id_module || '';
        form.querySelector('[name="est_justifiee"]').checked = parseInt(data.est_justifiee) === 1;

        document.getElementById('modal-quick-absence').style.display = 'flex';
    }

    // Reset absence modal to "new" state when opened via the Add button
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('[onclick*="modal-quick-absence"]')?.addEventListener('click', function() {
            document.getElementById('absence-edit-id').value = '';
            document.getElementById('absence-modal-title').textContent = 'Nouvelle absence (Enregistrement rapide)';
            document.getElementById('absence-modal-desc').innerHTML = 'Saisie rapide pour <strong><?= h($selectedStudent['nom'] . ' ' . $selectedStudent['prenom']) ?></strong>. L\'absence sera automatiquement rattachée à son dossier.';
            document.getElementById('absence-form').reset();
            document.querySelector('[name="date_absence"]').value = new Date().toISOString().split('T')[0];
        });
    });
    </script>

    <!-- QUICK ABSENCE MODAL -->
    <div id="modal-quick-absence" class="modal-overlay" style="display:none; z-index:10000;">
        <div class="modal-card" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="absence-modal-title">Nouvelle absence (Enregistrement rapide)</h2>
                <button type="button" class="icon-btn" onclick="document.getElementById('modal-quick-absence').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" class="modal-form hub-ajax-form" id="absence-form">
                <input type="hidden" name="id_stagiaire" value="<?= (int)$selectedStudent['id_stagiaire'] ?>">
                <input type="hidden" name="id_absence_edit" id="absence-edit-id" value="">
                <div class="modal-body">
                    <div style="background: rgba(239,68,68,0.1); padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center;">
                        <i class="fa-solid fa-clock-rotate-left" style="color:#ef4444; font-size:1.5rem;"></i>
                        <p style="font-size:0.9rem; color:#a1a1aa; margin:0;" id="absence-modal-desc">
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

    
<?php endif; ?>


<?php
/* ── Highlight a specific stagiaire row if redirected from pre-inscription duplicate check ── */
$highlightId = (int)($_GET['highlight'] ?? 0);
if ($highlightId > 0): ?>
<style>
@keyframes gds-highlight-pulse {
    0%   { box-shadow: inset 0 0 0 2px #f59e0b, 0 0 0 0 rgba(245,158,11,0.5); background: rgba(245,158,11,0.12); }
    50%  { box-shadow: inset 0 0 0 2px #f59e0b, 0 0 0 10px rgba(245,158,11,0); background: rgba(245,158,11,0.08); }
    100% { box-shadow: inset 0 0 0 2px rgba(245,158,11,0.4), none; background: rgba(245,158,11,0.05); }
}
tr.gds-highlighted {
    animation: gds-highlight-pulse 1.2s ease 0.3s 3;
    border-radius: 8px;
}
</style>
<script>
(function() {
    var targetId = <?= $highlightId ?>;
    function doHighlight() {
        var row = document.querySelector('tr[data-id="' + targetId + '"]');
        if (row) {
            row.classList.add('gds-highlighted');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Also open the detail panel if there's a click trigger
            var btn = row.querySelector('[data-open-detail], .stag-row-name, [onclick]');
            if (btn && typeof btn.click === 'function') {
                setTimeout(function() { btn.click(); }, 800);
            }
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', doHighlight);
    } else {
        setTimeout(doHighlight, 200);
    }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>







