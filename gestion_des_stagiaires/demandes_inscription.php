<?php
/**
 * demandes_inscription.php – Gestion des pré-inscriptions
 *
 * Permet de :
 *   - Créer une nouvelle pré-inscription (secrétaire ou directeur)
 *   - Modifier une demande existante
 *   - Accepter (convertir en stagiaire) ou refuser une demande
 *   - Traitement groupé (bulk accept/refuse) avec contrôle des capacités
 *
 * Contrôles d'unicité appliqués partout :
 *   - CIN déjà utilisé par un stagiaire inscrit → redirection vers sa fiche
 *   - CIN déjà présent dans une demande en attente → erreur flash
 *   - Email déjà utilisé par un stagiaire → erreur flash
 *   - Email déjà présent dans une demande en attente → erreur flash
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

/* ──────────────────────────────────────────────────────────────
   TRAITEMENT DES FORMULAIRES POST
────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    /* ── MODIFICATION D'UNE PRÉ-INSCRIPTION EXISTANTE ───────────
       Validations : format CIN, unicité CIN/email vs stagiaires
       et vs autres demandes en attente (hors demande courante).
    ── */
    if (!empty($_POST['modifier_id']) && (int)$_POST['modifier_id'] > 0) {
        $idDemandeModifiee = (int)$_POST['modifier_id']; // Identifiant de la demande à modifier
        $nom    = trim((string)($_POST['nom']    ?? ''));
        $prenom = trim((string)($_POST['prenom'] ?? ''));
        $cin    = strtoupper(trim((string)($_POST['cin'] ?? '')));
        $dn     = ($_POST['date_naissance'] ?? '') !== '' ? (string)$_POST['date_naissance'] : null;
        $adr    = trim((string)($_POST['adresse']  ?? '')) ?: null;
        $em     = trim((string)($_POST['email']    ?? '')) ?: null;
        $tel    = trim((string)($_POST['telephone'] ?? '')) ?: null;
        $telp   = trim((string)($_POST['telephone_parent'] ?? '')) ?: null;
        $tuteur = trim((string)($_POST['nom_tuteur'] ?? '')) ?: null;
        $sexe   = in_array($_POST['sexe'] ?? '', ['M','F']) ? (string)$_POST['sexe'] : null;
        $niveaux      = !empty($_POST['niveaux'])    ? json_encode(array_values(array_filter((array)$_POST['niveaux'])))    : null;
        $diplomesFils = $niveaux; // niveaux[] checkboxes ARE the diplôme level; mirror into diplomes column
        $formations   = !empty($_POST['formations']) ? json_encode(array_values(array_filter((array)$_POST['formations']))) : null;
        $sources      = !empty($_POST['sources'])    ? json_encode(array_values(array_filter((array)$_POST['sources'])))    : null;
        $autreFormation = trim((string)($_POST['autre_formation'] ?? '')) ?: null;
        $sourceAutre    = trim((string)($_POST['source_autre']    ?? '')) ?: null;
        $licencesProEdit = !empty($_POST['licences']) ? json_encode(array_values(array_filter((array)$_POST['licences']))) : null;
        $anneeViseeEdit = trim((string)($_POST['annee_scolaire_visee'] ?? '')) ?: null;
        if ($nom === '' || $prenom === '') { flash_set('Nom et prénom requis.'); redirect('demandes_inscription.php'); }
        if (($_POST['date_naissance'] ?? '') === '') { flash_set('La date de naissance est obligatoire.'); redirect('demandes_inscription.php'); }
        if ($cin === '') {
            flash_set('Le CIN est obligatoire.');
            redirect('demandes_inscription.php');
        }
        if (!preg_match('/^[a-zA-Z]{2}[0-9]{6}$/', $cin)) {
            flash_set('CIN invalide — format requis: 2 lettres + 6 chiffres (ex: WA123456).');
            redirect('demandes_inscription.php');
        }

        // ── CIN duplicate check against stagiaires (same logic as validation flow) ──
        if ($cin !== '') {
            $chkCinEdit = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE cin = ?');
            $chkCinEdit->execute([$cin]);
            $existStagEdit = $chkCinEdit->fetch();
            if ($existStagEdit) {
                $nomEx  = h($existStagEdit['prenom'] . ' ' . $existStagEdit['nom']);
                $numEx  = h($existStagEdit['num_inscri']);
                flash_set(
                    "⚠️ Ce CIN est déjà utilisé par le stagiaire {$nomEx} (N° {$numEx}). " .
                    "Veuillez mettre à jour son dossier.",
                    'warning'
                );
                redirect('stagiaires.php?highlight=' . (int)$existStagEdit['id_stagiaire']);
            }
        }

        // ── Vérification doublon email vs stagiaires (modification) ───────────────
        if ($em !== null) {
            $requeteEmailStagEdit = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE email = ?');
            $requeteEmailStagEdit->execute([$em]);
            $stagEmailEdit = $requeteEmailStagEdit->fetch();
            if ($stagEmailEdit) {
                flash_set(
                    '⚠️ Cet email est déjà utilisé par le stagiaire ' .
                    h($stagEmailEdit['prenom'] . ' ' . $stagEmailEdit['nom']) .
                    ' (N° ' . h($stagEmailEdit['num_inscri']) . '). Veuillez corriger l\'email.',
                    'warning'
                );
                redirect('demandes_inscription.php');
            }
        }
        // ── Vérification doublon CIN vs demandes en attente (hors demande courante) ─
        if ($cin !== '') {
            $requeteCinAutresDemandes = $pdo->prepare(
                "SELECT COUNT(*) FROM pre_inscription WHERE cin = ? AND statut = 'en_attente' AND id_demande != ?"
            );
            $requeteCinAutresDemandes->execute([$cin, $idDemandeModifiee]);
            if ((int)$requeteCinAutresDemandes->fetchColumn() > 0) {
                flash_set('⚠️ Une autre demande en attente utilise déjà ce CIN. Veuillez corriger.', 'warning');
                redirect('demandes_inscription.php');
            }
        }
        // ── Vérification doublon email vs demandes en attente (hors demande courante) ─
        if ($em !== null) {
            $requeteEmailAutresDemandes = $pdo->prepare(
                "SELECT COUNT(*) FROM pre_inscription WHERE email = ? AND statut = 'en_attente' AND id_demande != ?"
            );
            $requeteEmailAutresDemandes->execute([$em, $idDemandeModifiee]);
            if ((int)$requeteEmailAutresDemandes->fetchColumn() > 0) {
                flash_set('⚠️ Une autre demande en attente utilise déjà cet email. Veuillez corriger.', 'warning');
                redirect('demandes_inscription.php');
            }
        }
        $filIdEdit = (int)($_POST['id_filiere'] ?? 0);
        $pdo->prepare(
            'UPDATE pre_inscription SET nom=?, prenom=?, cin=?, date_naissance=?, adresse=?, email=?,
             telephone=?, telephone_parent=?, nom_tuteur=?, sexe=?, niveaux=?, diplomes=?,
             formations=?, autre_formation=?, sources=?, source_autre=?, annee_scolaire_visee=?, licences=?,
             id_filiere=? WHERE id_demande=?'
        )->execute([$nom, $prenom, $cin ?: null, $dn, $adr, $em, $tel, $telp, $tuteur, $sexe,
                    $niveaux, $diplomesFils, $formations, $autreFormation, $sources, $sourceAutre, $anneeViseeEdit, $licencesProEdit,
                    $filIdEdit ?: null, $idDemandeModifiee]);
        flash_set('Pré-inscription mise à jour avec succès.', 'success');
        redirect('demandes_inscription.php');
    }

    /* ── NOUVELLE PRÉ-INSCRIPTION (saisie par la secrétaire ou le directeur) ──
       Validations : champs obligatoires, format CIN, format téléphone,
       unicité CIN/email vs stagiaires et vs demandes en attente.
    ── */
    if (isset($_POST['nouvelle_preinscription'])) {
        $nom    = trim((string)($_POST['nom'] ?? ''));
        $prenom = trim((string)($_POST['prenom'] ?? ''));
        $cin    = trim((string)($_POST['cin'] ?? ''));
        $dateNaissance  = ($_POST['date_naissance'] ?? '') !== '' ? (string)$_POST['date_naissance'] : null;
        $adresse        = trim((string)($_POST['adresse'] ?? ''));
        $email          = trim((string)($_POST['email'] ?? ''));
        $telephone      = trim((string)($_POST['telephone'] ?? ''));
        $telephoneParent = trim((string)($_POST['telephone_parent'] ?? ''));
        $tuteur         = trim((string)($_POST['nom_tuteur'] ?? ''));

        // Filière choisie : le candidat exprime un intérêt, la classe est assignée à l'acceptation
        $idFiliere = (int)($_POST['id_filiere'] ?? 0);
        // Repli : dériver de la première case diplôme cochée
        if ($idFiliere === 0 && !empty($_POST['diplomes_filieres'])) {
            $idFiliere = (int)(array_values((array)$_POST['diplomes_filieres'])[0] ?? 0);
        }

        if ($nom === '' || $prenom === '') {
            flash_set('Nom et prénom sont obligatoires.');
            redirect('demandes_inscription.php');
        }
        if (($_POST['date_naissance'] ?? '') === '') {
            flash_set('La date de naissance est obligatoire.');
            redirect('demandes_inscription.php');
        }
        if ($idFiliere <= 0) {
              flash_set('Veuillez sélectionner au moins une filière (cochez un diplôme).');
              redirect('demandes_inscription.php');
          }
          // CIN : obligatoire, format 2 lettres + 6 chiffres (ex: WA123456)
          if ($cin === '') {
              flash_set('Le CIN est obligatoire.');
              redirect('demandes_inscription.php');
          }
          if (!preg_match('/^[a-zA-Z]{2}[0-9]{6}$/', strtoupper($cin))) {
              flash_set('CIN invalide — format requis: 2 lettres + 6 chiffres (ex: WA123456).');
              redirect('demandes_inscription.php');
          }
          $cin = strtoupper($cin);
          // Téléphone : validation du format marocain si renseigné
          if ($telephone !== '' && !preg_match('/^(\+?212|0)[5-7][0-9]{8}$/', preg_replace('/\s/', '', $telephone))) {
              flash_set('Numéro de téléphone invalide — ex: 0612345678.');
              redirect('demandes_inscription.php');
          }

          // ── Collecte des champs à choix multiples (checkboxes) ──────────────────────
          $sexe           = in_array($_POST['sexe'] ?? '', ['M','F']) ? (string)$_POST['sexe'] : null;
          $niveaux        = !empty($_POST['niveaux'])    ? json_encode(array_values(array_filter((array)$_POST['niveaux'])))    : null;
          $diplomesFils   = $niveaux; // niveaux[] correspond aux niveaux de diplôme → mirroir dans la colonne diplomes
          $formations     = !empty($_POST['formations']) ? json_encode(array_values(array_filter((array)$_POST['formations']))) : null;
          $autreFormation = trim((string)($_POST['autre_formation'] ?? '')) ?: null;
          $sources        = !empty($_POST['sources'])    ? json_encode(array_values(array_filter((array)$_POST['sources'])))    : null;
          $sourceAutre    = trim((string)($_POST['source_autre'] ?? '')) ?: null;
          $licencesPro    = !empty($_POST['licences'])   ? json_encode(array_values(array_filter((array)$_POST['licences'])))   : null;
          $anneeVisee     = trim((string)($_POST['annee_scolaire_visee'] ?? '')) ?: null;
          $emailNull      = $email !== '' ? $email : null;

          // ── Vérification doublon CIN vs stagiaires inscrits ──────────────────────────
          if ($cin !== '') {
              $requeteCinStagNew = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE cin = ?');
              $requeteCinStagNew->execute([$cin]);
              $stagCinExistant = $requeteCinStagNew->fetch();
              if ($stagCinExistant) {
                  flash_set(
                      '⚠️ Ce CIN est déjà utilisé par le stagiaire ' .
                      h($stagCinExistant['prenom'] . ' ' . $stagCinExistant['nom']) .
                      ' (N° ' . h($stagCinExistant['num_inscri']) . '). Vous avez été redirigé vers sa fiche.',
                      'warning'
                  );
                  redirect('stagiaires.php?id=' . (int)$stagCinExistant['id_stagiaire'] . '&highlight=1');
              }
          }
          // ── Vérification doublon CIN vs demandes en attente ──────────────────────────
          if ($cin !== '') {
              $requeteCinDemandeNew = $pdo->prepare(
                  "SELECT COUNT(*) FROM pre_inscription WHERE cin = ? AND statut = 'en_attente'"
              );
              $requeteCinDemandeNew->execute([$cin]);
              if ((int)$requeteCinDemandeNew->fetchColumn() > 0) {
                  flash_set('⚠️ Une demande en attente avec ce CIN existe déjà. Veuillez corriger.', 'warning');
                  redirect('demandes_inscription.php');
              }
          }
          // ── Vérification doublon email vs stagiaires inscrits ────────────────────────
          if ($emailNull !== null) {
              $requeteEmailStagNew = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE email = ?');
              $requeteEmailStagNew->execute([$emailNull]);
              $stagEmailExistant = $requeteEmailStagNew->fetch();
              if ($stagEmailExistant) {
                  flash_set(
                      '⚠️ Cet email est déjà utilisé par le stagiaire ' .
                      h($stagEmailExistant['prenom'] . ' ' . $stagEmailExistant['nom']) .
                      ' (N° ' . h($stagEmailExistant['num_inscri']) . '). Veuillez corriger l\'email.',
                      'warning'
                  );
                  redirect('demandes_inscription.php');
              }
          }
          // ── Vérification doublon email vs demandes en attente ────────────────────────
          if ($emailNull !== null) {
              $requeteEmailDemandeNew = $pdo->prepare(
                  "SELECT COUNT(*) FROM pre_inscription WHERE email = ? AND statut = 'en_attente'"
              );
              $requeteEmailDemandeNew->execute([$emailNull]);
              if ((int)$requeteEmailDemandeNew->fetchColumn() > 0) {
                  flash_set('⚠️ Une demande en attente avec cet email existe déjà. Veuillez corriger.', 'warning');
                  redirect('demandes_inscription.php');
              }
          }

          // ── Insertion de la nouvelle pré-inscription ─────────────────────────────────
          $pdo->prepare(
              'INSERT INTO pre_inscription
                 (cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, id_filiere,
                  sexe, niveaux, diplomes, formations, autre_formation, sources, source_autre, statut, annee_scolaire_visee, licences)
               VALUES (?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?)'
          )->execute([
              $cin === '' ? null : $cin,
              $nom, $prenom, $dateNaissance,
              $adresse  !== '' ? $adresse  : null,
              $emailNull,
              $telephone !== '' ? $telephone : null,
              $telephoneParent !== '' ? $telephoneParent : null,
              $tuteur !== '' ? $tuteur : null,
              $idFiliere,
              $sexe, $niveaux, $diplomesFils, $formations, $autreFormation, $sources, $sourceAutre,
              'en_attente',
              $anneeVisee,
              $licencesPro,
          ]);
          flash_set('Pré-inscription enregistrée avec succès.', 'success');
        redirect('demandes_inscription.php');
    }

    /* ── ACCEPTATION D'UNE DEMANDE (Directeur uniquement) ─────────────────────
       Vérifie CIN/email uniques, génère le numéro d'inscription,
       crée le stagiaire et marque la demande comme 'converti'.
    ── */
    if (isset($_POST['accepter_id'])) {
        if (!gds_is_directeur()) {
            flash_set('Accès réservé au directeur.', 'danger');
            redirect('demandes_inscription.php');
        }
        $idDemande = (int)$_POST['accepter_id']; // Identifiant de la demande à valider
        $pdo->beginTransaction();
        try {
            // Verrouillage de la ligne pour éviter les traitements simultanés (race condition)
            $requeteDemande = $pdo->prepare('SELECT * FROM pre_inscription WHERE id_demande = ? AND statut = ? FOR UPDATE');
            $requeteDemande->execute([$idDemande, 'en_attente']);
            $demande = $requeteDemande->fetch();
            if (!$demande) {
                $pdo->rollBack();
                flash_set('Pré-inscription introuvable ou déjà traitée.');
                redirect('demandes_inscription.php');
            }

            /* ── CIN DUPLICATE CHECK (smart business rule) ─────────────────
               If a stagiaire already exists with this CIN, do NOT create a duplicate.
               Instead: link the pre-inscription to the existing stagiaire, mark it
               as 'converti', and send the secretary to update that dossier.
            ──────────────────────────────────────────────────────────────── */
            // ── Vérification doublon CIN : pas de conversion si un stagiaire a déjà ce CIN ────
            $cinNormalise = strtoupper(trim((string)($demande['cin'] ?? '')));
            if ($cinNormalise !== '') {
                $requeteCinAccept = $pdo->prepare('SELECT id_stagiaire, nom, prenom, num_inscri FROM stagiaires WHERE cin = ?');
                $requeteCinAccept->execute([$cinNormalise]);
                $stagiaireExistant = $requeteCinAccept->fetch();
                if ($stagiaireExistant) {
                    // Le CIN appartient déjà à un stagiaire : pas de doublon, la demande reste en attente
                    $pdo->rollBack();
                    $nomExistant = h($stagiaireExistant['prenom'] . ' ' . $stagiaireExistant['nom']);
                    $numExistant = h($stagiaireExistant['num_inscri']);
                    flash_set(
                        "⚠️ Ce CIN appartient déjà au stagiaire {$nomExistant} (N° {$numExistant}). " .
                        "La pré-inscription reste en attente — veuillez corriger le CIN.",
                        'warning'
                    );
                    redirect('stagiaires.php?highlight=' . (int)$stagiaireExistant['id_stagiaire']);
                }
            }

            /* ── Vérification doublon email ─────────────────────────────────────── */
            $emailDemande = trim((string)($demande['email'] ?? ''));
            if ($emailDemande !== '') {
                $requeteEmailAccept = $pdo->prepare('SELECT COUNT(*) FROM stagiaires WHERE email = ?');
                $requeteEmailAccept->execute([$emailDemande]);
                if ((int)$requeteEmailAccept->fetchColumn() > 0) {
                    $pdo->rollBack();
                    flash_set('Impossible d\'accepter : un stagiaire existe déjà avec cet email.');
                    redirect('demandes_inscription.php');
                }
            }
            $motDePasseHash  = password_hash('changeme', PASSWORD_DEFAULT); // Mot de passe provisoire
            $dateInscription = date('Y-m-d');                                // Date du jour = date d'inscription
            $anneeInscription = date('Y', strtotime($dateInscription));
            // Calcul du prochain numéro d'inscription séquentiel pour l'année en cours
            $requeteMaxNumInscri = $pdo->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(num_inscri, '-', -1) AS UNSIGNED)), 0)
                   FROM stagiaires
                  WHERE num_inscri LIKE ?
                    AND num_inscri REGEXP '^INS-[0-9]{4}-[0-9]{5}$'"
            );
            $requeteMaxNumInscri->execute(['INS-' . $anneeInscription . '-%']);
            $dernierNumero    = (int)$requeteMaxNumInscri->fetchColumn();
            $nouveauNumInscri = 'INS-' . $anneeInscription . '-' . str_pad((string)($dernierNumero + 1), 5, '0', STR_PAD_LEFT);
            // La classe doit être explicitement choisie par la secrétaire — pas de repli automatique
            $idClasseFinale = isset($_POST['override_id_classe']) && (int)$_POST['override_id_classe'] > 0
                ? (int)$_POST['override_id_classe']
                : 0;
            if ($idClasseFinale <= 0) {
                $pdo->rollBack();
                flash_set("Veuillez sélectionner une classe avant de valider l'inscription.");
                redirect('demandes_inscription.php');
            }
            // Vérification que la classe sélectionnée appartient bien à la filière de la demande
            $requeteValidationClasse = $pdo->prepare('SELECT id_classe FROM classes WHERE id_classe = ? AND id_filiere = ?');
            $requeteValidationClasse->execute([$idClasseFinale, (int)$demande['id_filiere']]);
            if (!$requeteValidationClasse->fetchColumn()) {
                $pdo->rollBack();
                flash_set('Classe invalide pour cette filière.');
                redirect('demandes_inscription.php');
            }
            // ── Création du stagiaire et mise à jour du statut de la demande ────────
            $requeteInsertion = $pdo->prepare(
                'INSERT INTO stagiaires (num_inscri, cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, mot_de_passe, photo, date_inscription, id_classe, sexe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $requeteInsertion->execute([
                $nouveauNumInscri,
                $demande['cin'] ?: null, $demande['nom'], $demande['prenom'],
                $demande['date_naissance'] ?: null, $demande['adresse'] ?: null,
                $demande['email'] ?: null, $demande['telephone'] ?: null,
                $demande['telephone_parent'] ?: null, $demande['nom_tuteur'] ?: null,
                $motDePasseHash, null, $dateInscription, $idClasseFinale,
                in_array($demande['sexe'] ?? '', ['M', 'F']) ? $demande['sexe'] : 'M',
            ]);
            $idNouveauStagiaire = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE pre_inscription SET statut = ?, date_decision = NOW() WHERE id_demande = ?')
                ->execute(['converti', $idDemande]);
            $pdo->commit();
            flash_set('Pré-inscription acceptée — stagiaire créé (' . $nouveauNumInscri . ').');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $dbMsg = $e->getMessage();
            error_log('[demandes_inscription.php] ' . $dbMsg);
            if (str_contains($dbMsg, 'email'))          flash_set('Erreur : cet email est déjà utilisé.');
            elseif (str_contains($dbMsg, 'cin'))         flash_set('Erreur : ce CIN est déjà utilisé.');
            elseif (str_contains($dbMsg, 'num_inscri'))  flash_set('Erreur : conflit de numéro, réessayez.');
            else flash_set('Une erreur est survenue. Veuillez réessayer.');
        }
        redirect('demandes_inscription.php');
    }

    /* ── REFUS D'UNE DEMANDE (Directeur uniquement) ────────────────────────── */
    if (isset($_POST['refuser_id'])) {
        if (!gds_is_directeur()) {
            flash_set('Accès réservé au directeur.', 'danger');
            redirect('demandes_inscription.php');
        }
        $idDemande      = (int)$_POST['refuser_id'];
        $requeteRefus   = $pdo->prepare('UPDATE pre_inscription SET statut = ?, date_decision = NOW() WHERE id_demande = ? AND statut = ?');
        $requeteRefus->execute(['abandonne', $idDemande, 'en_attente']);
        flash_set($requeteRefus->rowCount() > 0 ? 'Pré-inscription refusée.' : 'Introuvable ou déjà traitée.');
        redirect('demandes_inscription.php');
    }

    /* ── REFUS GROUPÉ (Directeur uniquement) ────────────────────────────────── */
    if (isset($_POST['bulk_refuser'])) {
        if (!gds_is_directeur()) {
            flash_set('Accès réservé au directeur.', 'danger');
            redirect('demandes_inscription.php');
        }
        $listeIds = array_values(array_filter(array_map('intval', (array)($_POST['bulk_ids'] ?? []))));
        if (!empty($listeIds)) {
            $marqueurs  = implode(',', array_fill(0, count($listeIds), '?'));
            $parametres = $listeIds;
            $parametres[] = 'en_attente';
            $requeteBulkRefus = $pdo->prepare(
                "UPDATE pre_inscription SET statut = 'abandonne', date_decision = NOW()
                 WHERE id_demande IN ($marqueurs) AND statut = ?"
            );
            $requeteBulkRefus->execute($parametres);
            $nbRefuses = $requeteBulkRefus->rowCount();
            flash_set($nbRefuses . ' pré-inscription(s) marquée(s) comme abandonnée(s).',
                      $nbRefuses > 0 ? 'success' : 'warning');
        }
        redirect('demandes_inscription.php');
    }

    /* ── CONTRÔLE CAPACITÉ AVANT ACCEPTATION GROUPÉE (AJAX) ─────────────────── */
    if (isset($_POST['bulk_preflight'])) {
        header('Content-Type: application/json');
        if (!gds_is_directeur()) {
            echo json_encode(['success' => false, 'msg' => 'Accès réservé au directeur.']);
            exit;
        }
        // Ensure capacite column exists (soft-cap support)
        try { $pdo->exec("ALTER TABLE classes ADD COLUMN IF NOT EXISTS capacite INT UNSIGNED NOT NULL DEFAULT 30"); } catch (\Throwable $ignored) {}
        $listeIds = array_values(array_filter(array_map('intval', (array)($_POST['bulk_ids'] ?? []))));
        if (empty($listeIds)) { echo json_encode(['groups' => []]); exit; }
        $marqueurs = implode(',', array_fill(0, count($listeIds), '?'));
        // Récupère les demandes sélectionnées avec leur filière et année visée
        $requeteDemandes = $pdo->prepare(
            "SELECT d.id_demande, d.id_filiere, COALESCE(d.annee_scolaire_visee,'') AS annee_scolaire_visee,
                    d.nom, d.prenom, f.nom_filiere
             FROM pre_inscription d JOIN filieres f ON f.id_filiere = d.id_filiere
             WHERE d.id_demande IN ($marqueurs) AND d.statut = 'en_attente'"
        );
        $requeteDemandes->execute($listeIds);
        $lignesBrutes = $requeteDemandes->fetchAll();
        $groupes = [];
        // Regroupement par filière + année scolaire visée
        foreach ($lignesBrutes as $r) {
            $cleGroupe = (int)$r['id_filiere'] . '|||' . $r['annee_scolaire_visee'];
            if (!isset($groupes[$cleGroupe])) {
                $groupes[$cleGroupe] = [
                    'id_filiere'  => (int)$r['id_filiere'],
                    'nom_filiere' => $r['nom_filiere'],
                    'annee'       => $r['annee_scolaire_visee'],
                    'ids'         => [],
                ];
            }
            $groupes[$cleGroupe]['ids'][] = (int)$r['id_demande'];
        }
        $resultats = [];
        foreach ($groupes as $groupe) {
            $nbDemandes = count($groupe['ids']);
            // Récupère uniquement les classes de cette filière pour l'année scolaire visée dans la pré-inscription
            $requeteClasses = $pdo->prepare(
                "SELECT c.id_classe, c.nom_classe, c.annee_scolaire, c.niveau, COALESCE(c.capacite, 30) AS capacite,
                        COUNT(s.id_stagiaire) AS effectif
                 FROM classes c LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
                 WHERE c.id_filiere = ? AND c.annee_scolaire = ?
                 GROUP BY c.id_classe ORDER BY c.niveau ASC"
            );
            $requeteClasses->execute([$groupe['id_filiere'], $groupe['annee']]);
            $classesAnnee = $requeteClasses->fetchAll();

            if (empty($classesAnnee)) {
                $resultats[] = [
                    'id_filiere'  => $groupe['id_filiere'],
                    'nom_filiere' => $groupe['nom_filiere'],
                    'annee'       => $groupe['annee'],
                    'id_classe'   => 0,
                    'nom_classe'  => null,
                    'niveau'      => null,
                    'count'       => $nbDemandes,
                    'effectif'    => 0,
                    'capacite'    => 30,
                    'status'      => 'no_class',
                    'ids'         => $groupe['ids'],
                    'classes'     => [],
                ];
            } else {
                // Construit la liste des classes pour l'année visée avec leur statut de capacité
                $classesDispos = [];
                foreach ($classesAnnee as $cls) {
                    $eff = (int)$cls['effectif'];
                    $cap = (int)$cls['capacite'];
                    $classesDispos[] = [
                        'id_classe'      => (int)$cls['id_classe'],
                        'nom_classe'     => $cls['nom_classe'],
                        'annee_scolaire' => $cls['annee_scolaire'],
                        'niveau'         => $cls['niveau'],
                        'effectif'       => $eff,
                        'capacite'       => $cap,
                        'status'         => ($eff + $nbDemandes) <= $cap ? 'ok' : 'full',
                    ];
                }
                $classeDefaut = $classesDispos[0];
                $resultats[] = [
                    'id_filiere'  => $groupe['id_filiere'],
                    'nom_filiere' => $groupe['nom_filiere'],
                    'annee'       => $groupe['annee'],
                    'id_classe'   => $classeDefaut['id_classe'],
                    'nom_classe'  => $classeDefaut['nom_classe'],
                    'niveau'      => $classeDefaut['niveau'],
                    'count'       => $nbDemandes,
                    'effectif'    => $classeDefaut['effectif'],
                    'capacite'    => $classeDefaut['capacite'],
                    'status'      => $classeDefaut['status'],
                    'ids'         => $groupe['ids'],
                    'classes'     => $classesDispos,
                ];
            }
        }
        echo json_encode(['groups' => array_values($resultats)]);
        exit;
    }

    /* ── ACCEPTATION GROUPÉE (auto-affectation par filière, Directeur uniquement) ─ */
    if (isset($_POST['bulk_accepter'])) {
        if (!gds_is_directeur()) {
            flash_set('Accès réservé au directeur.', 'danger');
            redirect('demandes_inscription.php');
        }
        // Données de groupes envoyées par le JS du pré-vol (bulk_preflight)
        $donneesGroupesJson = trim((string)($_POST['bulk_groups'] ?? ''));
        $groupes = [];
        try { $groupes = json_decode($donneesGroupesJson, true) ?: []; } catch (\Throwable $e) {}
        if (empty($groupes)) {
            flash_set('Aucune donnée de groupe reçue.');
            redirect('demandes_inscription.php');
        }
        $di   = date('Y-m-d');
        $year = date('Y');
        $hash = password_hash('changeme', PASSWORD_DEFAULT);
        $ok   = 0; $skip = 0;
        $pdo->beginTransaction();
        try {
            foreach ($groupes as $group) {
                $classeId = (int)($group['id_classe'] ?? 0);
                $override = !empty($group['override']);
                $ids      = array_values(array_filter(array_map('intval', (array)($group['ids'] ?? []))));
                if ($classeId <= 0 || empty($ids)) { $skip += count((array)($group['ids'] ?? [])); continue; }
                // Current occupancy
                $capQ = $pdo->prepare(
                    "SELECT COALESCE(c.capacite,30) AS cap, COUNT(s.id_stagiaire) AS eff
                     FROM classes c LEFT JOIN stagiaires s ON s.id_classe = c.id_classe
                     WHERE c.id_classe = ? GROUP BY c.id_classe"
                );
                $capQ->execute([$classeId]);
                $capRow = $capQ->fetch();
                $cap    = $capRow ? (int)$capRow['cap'] : 30;
                $eff    = $capRow ? (int)$capRow['eff'] : 0;
                foreach ($ids as $idDem) {
                    if (!$override && $eff >= $cap) { $skip++; continue; }
                    $st = $pdo->prepare('SELECT * FROM pre_inscription WHERE id_demande = ? AND statut = ?');
                    $st->execute([$idDem, 'en_attente']);
                    $d = $st->fetch();
                    if (!$d) { $skip++; continue; }
                    $cin_val = strtoupper(trim((string)($d['cin'] ?? '')));
                    if ($cin_val !== '') {
                        $chk = $pdo->prepare('SELECT id_stagiaire FROM stagiaires WHERE cin = ?');
                        $chk->execute([$cin_val]);
                        if ($chk->fetch()) { $skip++; continue; }
                    }
                    $em = trim((string)($d['email'] ?? ''));
                    if ($em !== '') {
                        $chkE = $pdo->prepare('SELECT COUNT(*) FROM stagiaires WHERE email = ?');
                        $chkE->execute([$em]);
                        if ((int)$chkE->fetchColumn() > 0) { $skip++; continue; }
                    }
                    $stGen = $pdo->prepare(
                        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(num_inscri,'-',-1) AS UNSIGNED)),0)
                           FROM stagiaires WHERE num_inscri LIKE ?
                            AND num_inscri REGEXP '^INS-[0-9]{4}-[0-9]{5}$'"
                    );
                    $stGen->execute(['INS-' . $year . '-%']);
                    $maxNum = (int)$stGen->fetchColumn();
                    $newNum = 'INS-' . $year . '-' . str_pad((string)($maxNum + 1), 5, '0', STR_PAD_LEFT);
                    $pdo->prepare(
                        'INSERT INTO stagiaires (num_inscri,cin,nom,prenom,date_naissance,adresse,email,telephone,telephone_parent,nom_tuteur,mot_de_passe,photo,date_inscription,id_classe,sexe)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $newNum, $cin_val ?: null, $d['nom'], $d['prenom'],
                        $d['date_naissance'] ?: null, $d['adresse'] ?: null,
                        $em ?: null, $d['telephone'] ?: null,
                        $d['telephone_parent'] ?: null, $d['nom_tuteur'] ?: null,
                        $hash, null, $di, $classeId,
                        in_array($d['sexe'] ?? '', ['M', 'F']) ? $d['sexe'] : 'M',
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE pre_inscription SET statut=?,date_decision=NOW() WHERE id_demande=?')
                        ->execute(['converti', $idDem]);
                    $ok++; $eff++;
                }
            }
            $pdo->commit();
            $msg = $ok . ' stagiaire(s) créé(s) avec succès.';
            if ($skip > 0) $msg .= ' ' . $skip . ' ignoré(s) (classe pleine, doublon ou déjà traité).';
            flash_set($msg, 'success');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_set('Erreur lors de l\'inscription groupée : ' . $e->getMessage());
        }
        redirect('demandes_inscription.php');
    }
}

/* ──────────────────────────────────────────────────────────────
   PAGE SETUP
────────────────────────────────────────────────────────────── */
$curPage   = 'demandes';
$pageTitle = 'Pré-inscriptions';
require __DIR__ . '/includes/header.php';

$nbAttente = (int)$pdo->query("SELECT COUNT(*) FROM pre_inscription WHERE statut = 'en_attente'")->fetchColumn();

$monthStart = date('Y-m-01 00:00:00');
$stAcc = $pdo->prepare("SELECT COUNT(*) FROM pre_inscription WHERE statut = 'converti' AND date_decision >= ?");
$stAcc->execute([$monthStart]);
$accCeMois = (int)$stAcc->fetchColumn();

$stRef = $pdo->prepare("SELECT COUNT(*) FROM pre_inscription WHERE statut = 'abandonne' AND date_decision >= ?");
$stRef->execute([$monthStart]);
$refCeMois = (int)$stRef->fetchColumn();

$attente = $pdo->query(
    'SELECT d.*, f.nom_filiere
     FROM pre_inscription d
     JOIN filieres f ON f.id_filiere = d.id_filiere
     WHERE d.statut = \'en_attente\'
     ORDER BY d.date_soumission ASC'
)->fetchAll();

$traitees = $pdo->query(
    'SELECT d.*, f.nom_filiere
     FROM pre_inscription d
     JOIN filieres f ON f.id_filiere = d.id_filiere
     WHERE d.statut != \'en_attente\'
     ORDER BY d.date_decision DESC LIMIT 50'
)->fetchAll();

// Filieres list for the pre-inscription form checkboxes
$filieres_list = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere ASC')->fetchAll();

// Classes grouped by filière — used by the validation modal class selector
$classes_by_filiere = [];
$classes_by_filiere_year = []; // grouped by [filiere_id][annee_scolaire]
foreach ($pdo->query('SELECT id_classe, nom_classe, niveau, annee_scolaire, id_filiere FROM classes ORDER BY id_filiere, annee_scolaire DESC, niveau ASC')->fetchAll() as $cls) {
    $classes_by_filiere[(int)$cls['id_filiere']][] = $cls;
    $classes_by_filiere_year[(int)$cls['id_filiere']][$cls['annee_scolaire']][] = $cls;
}

// Distinct school years that appear in pre_inscriptions
$pi_years = $pdo->query(
    "SELECT DISTINCT annee_scolaire_visee FROM pre_inscription
      WHERE annee_scolaire_visee IS NOT NULL AND annee_scolaire_visee != ''
      ORDER BY annee_scolaire_visee DESC"
)->fetchAll(\PDO::FETCH_COLUMN);

// Available years for the form — loaded from classes table (same source as stagiaires page)
$years_available = $pdo->query(
    "SELECT DISTINCT annee_scolaire FROM classes ORDER BY annee_scolaire DESC"
)->fetchAll(\PDO::FETCH_COLUMN);
$default_year = !empty($years_available) ? $years_available[0] : '';

function formatSaaSDate($dtStr) {
    if (!$dtStr) return '—';
    $ts = strtotime($dtStr);
    $months = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
    return date('d', $ts) . ' ' . $months[(int)date('m', $ts)-1] . ' ' . date('Y à H:i', $ts);
}
function timeAgo($dtStr) {
    $diff = time() - strtotime($dtStr);
    if ($diff < 60) return "il y a l'instant";
    if ($diff < 3600) return "il y a " . floor($diff/60) . " min";
    if ($diff < 86400) return "il y a " . floor($diff/3600) . " h";
    return "il y a " . floor($diff/86400) . " j";
}
function getAvatarInitials($nom, $prenom) {
    return strtoupper(mb_substr($prenom ?? '', 0, 1) . mb_substr($nom ?? '', 0, 1));
}
?>

<style>
@keyframes pulseOrange {
    0% { box-shadow: 0 0 0 0 rgba(249,115,22,0.4); }
    70% { box-shadow: 0 0 0 6px rgba(249,115,22,0); }
    100% { box-shadow: 0 0 0 0 rgba(249,115,22,0); }
}
.pulse-dot-orange { position:relative; display:inline-block; width:12px; height:12px; border-radius:50%; background:#f97316; animation:pulseOrange 2s infinite; }

/* Modal nouvelle pré-inscription */
.pi-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7);
    z-index:9000; align-items:center; justify-content:center;
}
.pi-modal-overlay.open { display:flex; }
.pi-modal {
    background:#1a1a2e; border:1px solid rgba(168,85,247,0.3); border-radius:16px;
    padding:2rem; width:100%; max-width:620px; max-height:90vh; overflow-y:auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.5);
}
.pi-modal h3 { font-size:1.25rem; margin-bottom:1.5rem; color:#fff; }
.pi-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.pi-form-grid .full { grid-column:1/-1; }
.pi-form-group { display:flex; flex-direction:column; gap:0.4rem; }
.pi-form-group label { font-size:0.8rem; color:#a1a1aa; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; }
.pi-form-group input, .pi-form-group select {
    background:#111; border:1px solid rgba(255,255,255,0.1); border-radius:8px;
    color:#fff; padding:0.6rem 0.8rem; font-size:0.9rem; outline:none;
    transition: border-color 0.2s;
}
.pi-form-group input:focus, .pi-form-group select:focus { border-color:#a855f7; }
.pi-form-group select option { background:#111; }
/* PRÉ-INSCRIPTION ROW DESIGN */
.pi-row {
    display:grid;
    grid-template-columns: 28px 240px 1fr auto;
    align-items:center;
    gap:1.25rem;
    padding:0.9rem 1rem;
    border-radius:12px;
    border:1px solid rgba(255,255,255,0.06);
    background:rgba(255,255,255,0.015);
    margin-bottom:0.55rem;
    transition: background 0.18s, border-color 0.18s;
}
.pi-row:last-child { margin-bottom:0; }
.pi-row:hover { background:rgba(249,115,22,0.04); border-color:rgba(249,115,22,0.2); }
.pi-row-left { display:flex; align-items:center; gap:0.75rem; min-width:0; }
.pi-row-avatar {
    width:40px; height:40px; border-radius:50%; flex-shrink:0;
    background:rgba(56,189,248,0.12); color:#38bdf8;
    border:2px solid rgba(56,189,248,0.22);
    display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:0.88rem; letter-spacing:0.03em;
}
.pi-row-identity { min-width:0; }
.pi-row-name { font-weight:700; font-size:0.92rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:0.25rem; }
.pi-row-meta { display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap; }
.pi-row-badge { background:rgba(168,85,247,0.15); color:#d8b4fe; border:1px solid rgba(168,85,247,0.25); font-size:0.68rem; font-weight:700; padding:0.12rem 0.5rem; border-radius:5px; white-space:nowrap; }
.pi-row-center { display:flex; flex-direction:column; gap:0.28rem; min-width:0; }
.pi-row-info { display:flex; align-items:center; gap:0.42rem; font-size:0.8rem; color:#a1a1aa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pi-row-info i { width:13px; flex-shrink:0; color:#71717a; font-size:0.75rem; }
.pi-row-time { color:#facc15 !important; }
.pi-row-time i { color:#facc15 !important; }
.pi-row-actions { display:flex; align-items:center; gap:0.4rem; flex-shrink:0; }
.pi-action-btn {
    display:inline-flex; align-items:center; gap:0.38rem;
    padding:0.42rem 0.8rem; border-radius:8px; font-size:0.78rem;
    font-weight:600; cursor:pointer; border:1px solid transparent;
    text-decoration:none; white-space:nowrap; transition:all 0.18s;
    background:none; font-family:inherit; line-height:1.3;
}
.pi-action-print { background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.12); color:#d4d4d8; }
.pi-action-print:hover { background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.28); }
.pi-action-accept { background:rgba(16,185,129,0.1); border-color:rgba(16,185,129,0.28); color:#10b981; }
.pi-action-accept:hover { background:rgba(16,185,129,0.22); border-color:#10b981; color:#fff; }
.pi-action-info { background:rgba(99,102,241,0.1); border-color:rgba(99,102,241,0.28); color:#818cf8; }
.pi-action-info:hover { background:rgba(99,102,241,0.22); border-color:#6366f1; color:#fff; }
.pi-action-cancel { background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.22); color:#f87171; }
.pi-action-cancel:hover { background:rgba(239,68,68,0.18); border-color:#ef4444; color:#fff; }
@media (max-width:960px) {
    .pi-row { grid-template-columns:1fr; }
    .pi-row-actions { flex-wrap:wrap; }
}

/* ── Bulk select checkbox ─────────────────────────────────────────────────── */
.pi-row-checkbox {
    display:flex; align-items:center; flex-shrink:0;
}
.pi-select-cb {
    width:18px; height:18px; border-radius:4px; border:1.5px solid rgba(255,255,255,0.25);
    background:transparent; cursor:pointer; appearance:none; -webkit-appearance:none;
    flex-shrink:0; transition:all 0.15s; position:relative;
}
.pi-select-cb:checked {
    background:#a855f7; border-color:#a855f7;
}
.pi-select-cb:checked::after {
    content:''; position:absolute; top:2px; left:5px;
    width:6px; height:9px;
    border-right:2px solid #fff; border-bottom:2px solid #fff;
    transform:rotate(45deg);
}
.pi-row.pi-row-selected {
    background:rgba(168,85,247,0.08) !important;
    border-color:rgba(168,85,247,0.35) !important;
}

/* ── Bulk action bar ──────────────────────────────────────────────────────── */
#pi-bulk-bar {
    display:none; align-items:center; gap:0.75rem; flex-wrap:wrap;
    padding:0.7rem 1.25rem;
    background:rgba(168,85,247,0.12);
    border-bottom:1px solid rgba(168,85,247,0.3);
}
#pi-bulk-bar.visible { display:flex; }
.pi-bulk-count {
    font-size:0.85rem; font-weight:700; color:#d8b4fe;
    margin-right:0.25rem;
}
.pi-bulk-btn {
    display:inline-flex; align-items:center; gap:0.4rem;
    padding:0.4rem 0.9rem; border-radius:7px; font-size:0.8rem;
    font-weight:600; cursor:pointer; border:1px solid transparent;
    transition:all 0.18s; font-family:inherit;
}
.pi-bulk-accept { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.3); color:#10b981; }
.pi-bulk-accept:hover { background:rgba(16,185,129,0.25); border-color:#10b981; }
.pi-bulk-refuse { background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.25); color:#f87171; }
.pi-bulk-refuse:hover { background:rgba(239,68,68,0.22); border-color:#ef4444; }
.pi-bulk-deselect { background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.12); color:#a1a1aa; }
.pi-bulk-deselect:hover { background:rgba(255,255,255,0.1); color:#fff; }

/* ── Days-waiting badge ───────────────────────────────────────────────────── */
.pi-days-badge {
    display:inline-flex; align-items:center; gap:0.3rem;
    padding:0.18rem 0.55rem; border-radius:5px;
    font-size:0.72rem; font-weight:700; white-space:nowrap;
}
.pi-days-fresh   { background:rgba(16,185,129,0.12); color:#10b981; border:1px solid rgba(16,185,129,0.25); }
.pi-days-warn    { background:rgba(249,115,22,0.12); color:#f97316; border:1px solid rgba(249,115,22,0.3); }
.pi-days-urgent  { background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,0.3); }

/* ─────────────────────────────────────────────────────────────
   CHECKBOX & RADIO — pill-card design
───────────────────────────────────────────────────────────── */
.pi-chk-input, .pi-radio { display:none; }

/* Base pill — all checkboxes share this */
.pi-chk-label {
    display:inline-flex; align-items:center; gap:0.55rem;
    cursor:pointer; font-size:0.86rem; color:#a1a1aa; user-select:none;
    background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1);
    border-radius:8px; padding:0.42rem 0.85rem;
    transition: all 0.18s ease;
    line-height:1.3;
}
.pi-chk-label:hover {
    color:#e4e4e7; background:rgba(168,85,247,0.08);
    border-color:rgba(168,85,247,0.35);
}

/* Custom checkbox box */
.pi-chk-box {
    width:15px; height:15px;
    border:1.5px solid rgba(255,255,255,0.22); border-radius:3px;
    flex-shrink:0; display:inline-flex; align-items:center; justify-content:center;
    transition: all 0.15s; background:transparent;
}
.pi-chk-input:checked + .pi-chk-box {
    background:#a855f7; border-color:#a855f7;
}
.pi-chk-input:checked + .pi-chk-box::after {
    content:''; display:block; width:8px; height:4px;
    border-left:2px solid #fff; border-bottom:2px solid #fff;
    transform:rotate(-45deg) translateY(-1px);
}
/* Whole label highlight when checked */
.pi-chk-label:has(.pi-chk-input:checked) {
    color:#e9d5ff; background:rgba(168,85,247,0.14);
    border-color:rgba(168,85,247,0.55);
}

/* Filière pill — rounded, more prominent */
.pi-pill-filiere {
    border-radius:100px; padding:0.5rem 1.2rem;
    font-size:0.9rem; font-weight:600;
}
.pi-pill-filiere:has(.pi-chk-input:checked) {
    color:#f3e8ff; background:rgba(168,85,247,0.22);
    border-color:#a855f7;
    box-shadow: 0 0 14px rgba(168,85,247,0.3);
}

/* Full-width rows (diplôme, licence) */
.pi-chk-full {
    width:100%; border-radius:10px; padding:0.52rem 0.9rem;
}
.pi-chk-full:has(.pi-chk-input:checked),
.pi-chk-full:has(.pi-radio:checked) {
    color:#e9d5ff; background:rgba(168,85,247,0.14);
    border-color:rgba(168,85,247,0.55);
    box-shadow: 0 0 10px rgba(168,85,247,0.12);
}

/* Radio (sexe) */
.pi-radio-label {
    border-radius:100px; padding:0.5rem 1.25rem; font-size:0.9rem;
}
.pi-radio-label:has(.pi-radio:checked) {
    color:#f3e8ff; background:rgba(168,85,247,0.2);
    border-color:#a855f7;
    box-shadow: 0 0 10px rgba(168,85,247,0.25);
}
.pi-chk-box-radio {
    width:15px; height:15px;
    border:1.5px solid rgba(255,255,255,0.22); border-radius:50%;
    flex-shrink:0; display:inline-flex; align-items:center; justify-content:center;
    transition: all 0.15s; background:transparent;
}
.pi-radio:checked + .pi-chk-box-radio {
    background:#a855f7; border-color:#a855f7;
}
.pi-radio:checked + .pi-chk-box-radio::after {
    content:''; display:block; width:5px; height:5px;
    border-radius:50%; background:#fff;
}
.pi-radio-label:has(.pi-radio:checked) .pi-chk-box-radio {
    background:#a855f7; border-color:#a855f7;
}

/* Section dividers in modal */
.pi-section-label {
    font-size:0.68rem; text-transform:uppercase; letter-spacing:0.13em;
    color:#a855f7; font-weight:700;
    margin-bottom:0.7rem; padding-bottom:0.4rem;
    border-bottom:1px solid rgba(168,85,247,0.2);
    display:flex; align-items:center; gap:0.4rem;
}

</style>

<!-- ── PAGE HEADER ─────────────────────────────────────────── -->
<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 class="page-title" style="font-family:'Instrument Serif',serif; font-size:2.5rem; margin-bottom:0.25rem; display:flex; align-items:center; gap:0.75rem;">
            Pré-inscriptions
            <?php if($nbAttente > 0): ?>
                <span class="badge" style="background:#f97316; color:#fff; font-size:1rem; border-radius:8px; padding:0.2rem 0.6rem;"><?= $nbAttente ?></span>
            <?php endif; ?>
        </h1>
        <p style="color:var(--muted); font-size:0.95rem; margin:0;">Gérez les fiches de pré-inscription et leur validation.</p>
    </div>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <!-- Print blank form -->
        <a href="print_fiche_preinscription.php" target="_blank"
           style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.6rem 1.2rem; border-radius:8px; font-size:0.9rem; font-weight:600; text-decoration:none; transition:background 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
            <i class="fa-solid fa-print"></i> Imprimer fiche vierge
        </a>
        <!-- Add new -->
        <button onclick="openNewModal()"
                class="btn" style="background:rgba(168,85,247,0.15); color:#a855f7; border:1px solid rgba(168,85,247,0.3); padding:0.6rem 1.2rem;">
            <i class="fa-solid fa-plus"></i> Nouvelle pré-inscription
        </button>
    </div>
</div>



<!-- ── STATS ─────────────────────────────────────────────── -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="card" style="display:flex;flex-direction:column;padding:1.5rem;border:1px solid rgba(249,115,22,0.25);background:linear-gradient(180deg,rgba(249,115,22,0.05) 0%,rgba(255,255,255,0.02) 100%);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(249,115,22,0.15);color:#f97316;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <?php if($nbAttente > 0): ?><span class="pulse-dot-orange"></span><?php endif; ?>
        </div>
        <div style="font-size:2.5rem;font-weight:800;color:#f97316;line-height:1;margin-bottom:0.25rem;"><?= $nbAttente ?></div>
        <div style="font-size:0.85rem;color:#e4e4e7;font-weight:600;">En attente de décision</div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;padding:1.5rem;border:1px solid rgba(16,185,129,0.25);background:linear-gradient(180deg,rgba(16,185,129,0.05) 0%,rgba(255,255,255,0.02) 100%);">
        <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.15);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div style="font-size:2.5rem;font-weight:800;color:#10b981;line-height:1;margin-bottom:0.25rem;"><?= $accCeMois ?></div>
        <div style="font-size:0.85rem;color:#e4e4e7;font-weight:600;">Acceptées (Ce mois)</div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;padding:1.5rem;border:1px solid rgba(239,68,68,0.25);background:linear-gradient(180deg,rgba(239,68,68,0.05) 0%,rgba(255,255,255,0.02) 100%);">
        <div style="width:48px;height:48px;border-radius:12px;background:rgba(239,68,68,0.15);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">
            <i class="fa-solid fa-user-xmark"></i>
        </div>
        <div style="font-size:2.5rem;font-weight:800;color:#ef4444;line-height:1;margin-bottom:0.25rem;"><?= $refCeMois ?></div>
        <div style="font-size:0.85rem;color:#e4e4e7;font-weight:600;">Refusées (Ce mois)</div>
    </div>
</div>

<!-- ── À TRAITER ──────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;border:1px solid rgba(249,115,22,0.3);margin-bottom:2rem;">
    <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.05);background:linear-gradient(90deg,rgba(249,115,22,0.12) 0%,rgba(249,115,22,0.03) 100%);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <?php if($nbAttente > 0): ?>
            <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;" title="Tout sélectionner">
                <input type="checkbox" id="pi-select-all" class="pi-select-cb">
            </label>
            <?php endif; ?>
            <h2 style="margin:0;font-size:1.1rem;color:#f97316;display:flex;align-items:center;gap:0.6rem;">
                <i class="fa-solid fa-inbox"></i> À Traiter
                <?php if($nbAttente > 0): ?>
                <span style="background:#f97316;color:#fff;font-size:0.72rem;font-weight:700;padding:0.15rem 0.55rem;border-radius:20px;"><?= $nbAttente ?></span>
                <?php endif; ?>
            </h2>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <button type="button" id="pi-group-toggle"
                onclick="toggleGroupByFiliere()"
                style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.3rem 0.85rem;border-radius:20px;border:1px solid rgba(168,85,247,0.3);background:transparent;color:#a855f7;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.18s;"
                onmouseover="this.style.background='rgba(168,85,247,0.12)'" onmouseout="this.style.background='transparent'">
                <i class="fa-solid fa-layer-group"></i> Grouper par filière
            </button>
            <span style="font-size:0.78rem;color:rgba(249,115,22,0.7);display:flex;align-items:center;gap:0.4rem;">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Actualisation auto...
            </span>
        </div>
    </div>
    <!-- Bulk action bar — appears when rows are checked -->
    <div id="pi-bulk-bar">
        <span class="pi-bulk-count"><span id="pi-bulk-n">0</span> sélectionné(s)</span>
        <?php if (gds_is_directeur()): ?>
        <button type="button" class="pi-bulk-btn pi-bulk-accept" onclick="bulkAccept()">
            <i class="fa-solid fa-user-check"></i> Valider la sélection
        </button>
        <button type="button" class="pi-bulk-btn pi-bulk-refuse" onclick="bulkRefuse()">
            <i class="fa-solid fa-ban"></i> Refuser la sélection
        </button>
        <?php endif; ?>
        <button type="button" class="pi-bulk-btn pi-bulk-deselect" onclick="clearBulkSelection()">
            <i class="fa-solid fa-xmark"></i> Désélectionner
        </button>
    </div>

    <?php if($attente): ?>
    <div style="padding:0.75rem 1.25rem 0.5rem;">
        <!-- Year filter pills — always shown, sourced from classes table -->
        <?php if(!empty($years_available)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.75rem;align-items:center;">
            <span style="font-size:0.75rem;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:0.25rem;"><i class="fa-solid fa-calendar-days"></i></span>
            <button type="button" class="pi-year-btn pi-year-btn-traiter active" data-year="" style="padding:0.25rem 0.75rem;border-radius:20px;border:1px solid rgba(249,115,22,0.4);background:rgba(249,115,22,0.15);color:#f97316;font-size:0.78rem;font-weight:600;cursor:pointer;">Toutes</button>
            <?php foreach($years_available as $yr): ?>
            <button type="button" class="pi-year-btn pi-year-btn-traiter" data-year="<?= h($yr) ?>" style="padding:0.25rem 0.75rem;border-radius:20px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#a1a1aa;font-size:0.78rem;font-weight:600;cursor:pointer;"><?= h($yr) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="position:relative;margin-bottom:1rem;">
            <i class="fa-solid fa-search" style="position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:#71717a;font-size:0.8rem;"></i>
            <input type="text" id="traiter-search" placeholder="Rechercher par nom ou CIN..." style="width:100%;background:#111;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;padding:0.55rem 0.8rem 0.55rem 2.3rem;font-size:0.85rem;box-sizing:border-box;">
        </div>
    </div>
    <div id="pi-rows-container" style="padding:0 1.25rem 1.25rem;">
        <?php foreach ($attente as $r): ?>
        <?php
            $did      = (int)$r['id_demande'];
            $fullName = h(trim((string)$r['nom'].' '.(string)$r['prenom']));
            $initials = getAvatarInitials($r['nom'], $r['prenom']);
            $filiere  = h((string)$r['nom_filiere']);
        ?>
        <?php
            $daysWaiting = max(0, (int)floor((time() - strtotime((string)$r['date_soumission'])) / 86400));
            $daysBadgeClass = $daysWaiting <= 2 ? 'pi-days-fresh' : ($daysWaiting <= 7 ? 'pi-days-warn' : 'pi-days-urgent');
            $daysLabel = $daysWaiting === 0 ? "Aujourd'hui" : ($daysWaiting === 1 ? 'Hier' : 'Depuis ' . $daysWaiting . ' j');
        ?>
        <div class="pi-row" id="pi-row-<?= $did ?>"
             data-pisearch="<?= htmlspecialchars(strtolower(trim($r['nom'].' '.$r['prenom'].' '.($r['cin'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>"
             data-nom="<?= h((string)$r['nom']) ?>"
             data-prenom="<?= h((string)$r['prenom']) ?>"
             data-filiere="<?= h((string)$r['nom_filiere']) ?>"
             data-id_filiere="<?= (int)$r['id_filiere'] ?>"
             data-annee="<?= h((string)($r['annee_scolaire_visee'] ?? '')) ?>">

            <!-- CHECKBOX -->
            <div class="pi-row-checkbox">
                <input type="checkbox" class="pi-select-cb pi-row-cb" data-id="<?= $did ?>">
            </div>

            <!-- LEFT: Avatar + Identity -->
            <div class="pi-row-left">
                <div class="pi-row-avatar"><?= $initials ?></div>
                <div class="pi-row-identity">
                    <div class="pi-row-name"><?= $fullName ?></div>
                    <div class="pi-row-meta">
                        <span class="pi-row-badge"><?= $filiere ?></span>
                        <?php if($r['cin']): ?>
                        <span style="color:#71717a;font-size:0.75rem;"><?= h((string)$r['cin']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CENTER: Contact + Time -->
            <div class="pi-row-center">
                <?php if($r['email']): ?>
                <div class="pi-row-info"><i class="fa-solid fa-envelope"></i><?= h((string)$r['email']) ?></div>
                <?php endif; ?>
                <?php if($r['telephone']): ?>
                <div class="pi-row-info"><i class="fa-solid fa-phone"></i><?= h((string)$r['telephone']) ?></div>
                <?php endif; ?>
                <div class="pi-row-info pi-row-time"><i class="fa-regular fa-clock"></i>Soumis <?= timeAgo((string)$r['date_soumission']) ?></div>
                <div><span class="pi-days-badge <?= $daysBadgeClass ?>"><i class="fa-regular fa-calendar-clock"></i><?= $daysLabel ?></span></div>
            </div>

            <!-- RIGHT: Action buttons -->
            <div class="pi-row-actions">
                <button type="button" class="pi-action-btn pi-action-info btn-voir-details"
                    data-id_demande="<?= $did ?>"
                    data-prenom="<?= h((string)$r['prenom']) ?>"
                    data-nom="<?= h((string)$r['nom']) ?>"
                    data-cin="<?= h((string)($r['cin'] ?? '')) ?>"
                    data-date_naissance="<?= h((string)($r['date_naissance'] ?? '')) ?>"
                    data-adresse="<?= h((string)($r['adresse'] ?? '')) ?>"
                    data-email="<?= h((string)($r['email'] ?? '')) ?>"
                    data-telephone="<?= h((string)($r['telephone'] ?? '')) ?>"
                    data-telephone_parent="<?= h((string)($r['telephone_parent'] ?? '')) ?>"
                    data-nom_tuteur="<?= h((string)($r['nom_tuteur'] ?? '')) ?>"
                    data-sexe="<?= h((string)($r['sexe'] ?? '')) ?>"
                    data-niveaux="<?= h((string)($r['niveaux'] ?? '[]')) ?>"
                    data-diplomes="<?= h((string)($r['diplomes'] ?? '[]')) ?>"
                    data-formations="<?= h((string)($r['formations'] ?? '[]')) ?>"
                    data-autre_formation="<?= h((string)($r['autre_formation'] ?? '')) ?>"
                    data-sources="<?= h((string)($r['sources'] ?? '[]')) ?>"
                    data-source_autre="<?= h((string)($r['source_autre'] ?? '')) ?>"
                    data-id_filiere="<?= (int)($r['id_filiere'] ?? 0) ?>"
                    data-classe=""
                    data-filiere="<?= h((string)$r['nom_filiere']) ?>"
                    data-annee_scolaire_visee="<?= h((string)($r['annee_scolaire_visee'] ?? '')) ?>"
                    data-licences="<?= h((string)($r['licences'] ?? '[]')) ?>"
                    data-date_soumission="<?= h((string)$r['date_soumission']) ?>">
                    <i class="fa-solid fa-eye"></i>
                    <span>Voir les infos</span>
                </button>
                <a href="print_fiche_preinscription.php?id=<?= $did ?>" target="_blank"
                   class="pi-action-btn pi-action-print">
                    <i class="fa-solid fa-print"></i>
                    <span>Imprimer la fiche</span>
                </a>
                <?php if (gds_is_directeur()): ?>
                <form method="post" id="form-accept-<?= $did ?>" style="margin:0;display:contents;">
                    <?= csrf_hidden() ?>
                    <input type="hidden" name="accepter_id" value="<?= $did ?>">
                    <button type="button" class="pi-action-btn pi-action-accept"
                            onclick="confirmAccept(<?= $did ?>)">
                        <i class="fa-solid fa-user-check"></i>
                        <span>Valider l'inscription</span>
                    </button>
                </form>
                <form method="post" id="form-refuse-<?= $did ?>" style="margin:0;display:contents;">
                    <?= csrf_hidden() ?>
                    <input type="hidden" name="refuser_id" value="<?= $did ?>">
                    <button type="button" class="pi-action-btn pi-action-cancel"
                            onclick="confirmRefuse(<?= $did ?>)">
                        <i class="fa-solid fa-ban"></i>
                        <span>Marquer comme abandonnée</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:3.5rem 2rem;">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(16,185,129,0.1);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1rem;box-shadow:0 0 24px rgba(16,185,129,0.15);">
            <i class="fa-solid fa-check"></i>
        </div>
        <h3 style="color:#e4e4e7;margin-bottom:0.4rem;font-size:1.1rem;">Aucune pré-inscription en attente</h3>
        <p style="color:#71717a;font-size:0.88rem;margin:0;">La file de validation est propre.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ── HISTORIQUE ─────────────────────────────────────────── -->
<div class="card" style="padding:0;">
    <div style="padding:1.5rem;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <h2 style="margin:0;font-size:1.25rem;"><i class="fa-solid fa-timeline" style="margin-right:0.5rem;color:#a1a1aa;"></i> Historique complet</h2>
            <?php if(!empty($years_available)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
                <span style="font-size:0.75rem;color:#71717a;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-right:0.25rem;"><i class="fa-solid fa-calendar-days"></i></span>
                <button type="button" class="pi-year-btn pi-year-btn-hist active" data-year="" style="padding:0.25rem 0.75rem;border-radius:20px;border:1px solid rgba(161,161,170,0.4);background:rgba(161,161,170,0.15);color:#a1a1aa;font-size:0.78rem;font-weight:600;cursor:pointer;">Toutes</button>
                <?php foreach($years_available as $yr): ?>
                <button type="button" class="pi-year-btn pi-year-btn-hist" data-year="<?= h($yr) ?>" style="padding:0.25rem 0.75rem;border-radius:20px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#a1a1aa;font-size:0.78rem;font-weight:600;cursor:pointer;"><?= h($yr) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="position:relative;width:250px;">
            <i class="fa-solid fa-search" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#71717a;"></i>
            <input type="text" id="historique-search" placeholder="Rechercher..." style="width:100%;background:#111;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;padding:0.55rem 0.8rem 0.55rem 2.5rem;font-size:0.85rem;">
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table" id="historique-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Candidat</th>
                    <th>Filière</th>
                    <th>Contact</th>
                    <th>Soumis le</th>
                    <th>Statut</th>
                    <th>Décision</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($traitees as $r):
                    $search = strtolower(
                        ($r['nom']??'') . ' ' . ($r['prenom']??'') . ' ' .
                        ($r['cin']??'') . ' ' .
                        ($r['statut']??'') . ' ' . ($r['email']??'')
                    );
                    $statusColor = $r['statut'] === 'converti' ? '#10b981' : '#ef4444';
                    $statusBg    = $r['statut'] === 'converti' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
                    $statusLabel = $r['statut'] === 'converti' ? 'Converti' : 'Abandonné';
                    $statusIcon  = $r['statut'] === 'converti' ? 'fa-check' : 'fa-xmark';
                ?>
                <tr data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" data-annee="<?= h((string)($r['annee_scolaire_visee'] ?? '')) ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <div style="width:34px;height:34px;border-radius:50%;background:rgba(168,85,247,0.1);color:#a855f7;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;border:1.5px solid rgba(168,85,247,0.2);">
                                <?= getAvatarInitials($r['nom'], $r['prenom']) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?= h((string)$r['nom'] . ' ' . (string)$r['prenom']) ?></div>
                                <div style="font-size:0.75rem;color:#71717a;"><?= h((string)($r['cin']??'')) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.85rem;"><?= h((string)$r['nom_filiere']) ?></div>
                    </td>
                    <td style="font-size:0.82rem;color:#a1a1aa;">
                        <?= h((string)($r['email']??'—')) ?><br>
                        <?= h((string)($r['telephone']??'—')) ?>
                    </td>
                    <td style="font-size:0.82rem;color:#a1a1aa;"><?= formatSaaSDate((string)$r['date_soumission']) ?></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:0.35rem;background:<?= $statusBg ?>;color:<?= $statusColor ?>;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.8rem;font-weight:600;">
                            <i class="fa-solid <?= $statusIcon ?>"></i> <?= $statusLabel ?>
                        </span>
                    </td>
                    <td style="font-size:0.82rem;color:#a1a1aa;"><?= formatSaaSDate((string)($r['date_decision']??'')) ?></td>
                    <td>
                        <button type="button"
                            class="pi-action-btn pi-action-info btn-voir-details-hist"
                            style="font-size:0.8rem;padding:0.3rem 0.7rem;"
                            data-id_demande="<?= (int)$r['id_demande'] ?>"
                            data-prenom="<?= h((string)$r['prenom']) ?>"
                            data-nom="<?= h((string)$r['nom']) ?>"
                            data-cin="<?= h((string)($r['cin'] ?? '')) ?>"
                            data-date_naissance="<?= h((string)($r['date_naissance'] ?? '')) ?>"
                            data-adresse="<?= h((string)($r['adresse'] ?? '')) ?>"
                            data-email="<?= h((string)($r['email'] ?? '')) ?>"
                            data-telephone="<?= h((string)($r['telephone'] ?? '')) ?>"
                            data-telephone_parent="<?= h((string)($r['telephone_parent'] ?? '')) ?>"
                            data-nom_tuteur="<?= h((string)($r['nom_tuteur'] ?? '')) ?>"
                            data-sexe="<?= h((string)($r['sexe'] ?? '')) ?>"
                            data-niveaux="<?= h((string)($r['niveaux'] ?? '[]')) ?>"
                            data-diplomes="<?= h((string)($r['diplomes'] ?? '[]')) ?>"
                            data-formations="<?= h((string)($r['formations'] ?? '[]')) ?>"
                            data-autre_formation="<?= h((string)($r['autre_formation'] ?? '')) ?>"
                            data-sources="<?= h((string)($r['sources'] ?? '[]')) ?>"
                            data-source_autre="<?= h((string)($r['source_autre'] ?? '')) ?>"
                            data-id_filiere="<?= (int)($r['id_filiere'] ?? 0) ?>"
                            data-filiere="<?= h((string)$r['nom_filiere']) ?>"
                            data-annee_scolaire_visee="<?= h((string)($r['annee_scolaire_visee'] ?? '')) ?>"
                            data-licences="<?= h((string)($r['licences'] ?? '[]')) ?>"
                            data-date_soumission="<?= h((string)$r['date_soumission']) ?>">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(!$traitees): ?>
                <tr><td colspan="6" style="text-align:center;color:#71717a;padding:2rem;">Aucun historique pour le moment.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL — Nouvelle pré-inscription (secrétaire)
═══════════════════════════════════════════════════════════ -->
<div id="pi-add-modal" class="pi-modal-overlay">
    <div class="pi-modal" style="max-width:680px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="margin:0;" id="pi-modal-title"><i class="fa-solid fa-user-plus" style="color:#a855f7;margin-right:0.5rem;" id="pi-modal-title-icon"></i> <span id="pi-modal-title-text">Nouvelle pré-inscription</span></h3>
            <button onclick="closePiModal()" style="background:none;border:none;color:#71717a;cursor:pointer;font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrf_hidden() ?>
            <input type="hidden" name="nouvelle_preinscription" id="pi-modal-nouvelle" value="1">
            <?php if (gds_is_directeur()): ?>
            <input type="hidden" name="accepter_id" id="pi-modal-accepter-id" value="">
            <input type="hidden" name="refuser_id"  id="pi-modal-refuser-id"  value="">
            <?php else: ?>
            <input type="hidden" id="pi-modal-accepter-id" value="">
            <input type="hidden" id="pi-modal-refuser-id"  value="">
            <?php endif; ?>
            <input type="hidden" name="action" id="pi-modal-action" value="ajouter_demande">
            <input type="hidden" name="modifier_id" id="pi-modal-modifier-id" value="">

            <!-- ── Section: Identité ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-user" style="margin-right:0.4rem;"></i> Informations personnelles
            </div>
            <div class="pi-form-grid" style="margin-bottom:1.25rem;">
                <div class="pi-form-group">
                    <label>Nom *</label>
                    <input class="gds-validate" type="text" name="nom" maxlength="100" required placeholder="Ex: Bergam">
                </div>
                <div class="pi-form-group">
                    <label>Prénom *</label>
                    <input class="gds-validate" type="text" name="prenom" maxlength="100" required placeholder="Ex: El Mehdi">
                </div>
                <div class="pi-form-group">
                    <label>CIN <span style="color:#ef4444;">*</span></label>
                    <input class="gds-validate" type="text" name="cin" placeholder="Ex: WA123456" maxlength="8" required>
                </div>
                <div class="pi-form-group">
                    <label>Date de naissance <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="date_naissance" required>
                </div>
                <div class="pi-form-group">
                    <label>Téléphone</label>
                    <input class="gds-validate" type="text" name="telephone" maxlength="15" placeholder="0612345678">
                </div>
                <div class="pi-form-group">
                    <label>Tél. Parent</label>
                    <input class="gds-validate" type="text" name="telephone_parent" maxlength="15" placeholder="0612345678">
                </div>
                <div class="pi-form-group full">
                    <label>Email</label>
                    <input class="gds-validate" type="email" name="email" maxlength="255" placeholder="exemple@gmail.com">
                </div>
                <div class="pi-form-group full">
                    <label>Adresse</label>
                    <input class="gds-validate" type="text" name="adresse" maxlength="512" placeholder="Rue, quartier, ville">
                </div>
                <div class="pi-form-group full">
                    <label>Nom du tuteur / père</label>
                    <input class="gds-validate" type="text" name="nom_tuteur" maxlength="255" placeholder="Nom complet du tuteur">
                </div>
            </div>

            <!-- ── Section: Sexe ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-venus-mars" style="margin-right:0.4rem;"></i> Sexe
            </div>
            <div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;">
                <label class="pi-chk-label pi-radio-label">
                    <input type="radio" name="sexe" value="F" class="pi-radio">
                    <span class="pi-chk-box-radio"></span>
                    <span>Féminin</span>
                </label>
                <label class="pi-chk-label pi-radio-label">
                    <input type="radio" name="sexe" value="M" class="pi-radio">
                    <span class="pi-chk-box-radio"></span>
                    <span>Masculin</span>
                </label>
            </div>

            <!-- ── Section: Niveau ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-graduation-cap" style="margin-right:0.4rem;"></i> Niveau scolaire
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.6rem 1.5rem;margin-bottom:0.5rem;">
                <?php
                $niveaux = ['Licence','Bac +2','Technicien','Bachelier(e)','9ème AF','Tronc Commun','1ère Bac','2ème Bac'];
                foreach ($niveaux as $niv): ?>
                <label class="pi-chk-label">
                    <input type="checkbox" name="niveaux[]" value="<?= htmlspecialchars($niv) ?>" class="pi-chk-input">
                    <span class="pi-chk-box"></span>
                    <span><?= htmlspecialchars($niv) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-bottom:1.25rem;"></div>

            <!-- ── Section: Filière visée ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-certificate" style="margin-right:0.4rem;"></i> Filière visée <span style="color:#ef4444;">*</span>
            </div>
            <div style="margin-bottom:1.25rem;">
                <select name="id_filiere" id="pi-id-filiere-hidden" required
                        style="width:100%; background:#111; border:1.5px solid rgba(255,255,255,0.12); border-radius:8px; color:#fff; padding:0.7rem 0.9rem; font-size:0.95rem; outline:none; cursor:pointer; transition:border-color 0.2s;"
                        onchange="this.style.borderColor=this.value?'#a855f7':'rgba(255,255,255,0.12)';">
                    <option value="">— Choisir une filière —</option>
                    <?php foreach ($filieres_list as $f): ?>
                    <option value="<?= (int)$f['id_filiere'] ?>"><?= h($f['nom_filiere']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Section: Année scolaire visée ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-calendar-days" style="margin-right:0.4rem;"></i> Année scolaire visée <span style="color:#ef4444;">*</span>
            </div>
            <div style="margin-bottom:1.25rem;">
                <select name="annee_scolaire_visee" id="pi-annee-visee" required
                        style="width:100%; background:#111; border:1.5px solid rgba(255,255,255,0.12); border-radius:8px; color:#fff; padding:0.7rem 0.9rem; font-size:0.95rem; outline:none; cursor:pointer; transition:border-color 0.2s;"
                        onchange="this.style.borderColor=this.value?'#a855f7':'rgba(255,255,255,0.12)';">
                    <option value="">— Choisir une année —</option>
                    <?php foreach ($years_available as $yr): ?>
                    <option value="<?= h($yr) ?>"<?= $yr === $default_year ? ' selected' : '' ?>><?= h($yr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Section: Licence professionnelle ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-book-open" style="margin-right:0.4rem;"></i> Licence professionnelle
            </div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1.25rem;">
                <?php
                $licences = [
                    'Management et Ressource Humaine',
                    'Finance et Comptabilité',
                    'Logistique Internationale',
                    'Informatique',
                ];
                foreach ($licences as $lic): ?>
                <label class="pi-chk-label pi-chk-full">
                    <input type="checkbox" name="licences[]" value="<?= htmlspecialchars($lic) ?>" class="pi-chk-input">
                    <span class="pi-chk-box"></span>
                    <span><?= htmlspecialchars($lic) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- ── Section: Formation continue ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-chalkboard-teacher" style="margin-right:0.4rem;"></i> Formation continue (Attestation)
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 2rem;margin-bottom:0.75rem;">
                <?php
                $formations = ['Bureautique','Programmation','Comptabilité','Réseau'];
                foreach ($formations as $fo): ?>
                <label class="pi-chk-label">
                    <input type="checkbox" name="formations[]" value="<?= htmlspecialchars($fo) ?>" class="pi-chk-input">
                    <span class="pi-chk-box"></span>
                    <span><?= htmlspecialchars($fo) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="pi-form-group full" style="margin-bottom:1.25rem;">
                <label>Autre formation</label>
                <input type="text" name="autre_formation" maxlength="255" placeholder="Préciser si autre...">
            </div>

                        <div style="margin-bottom:0.25rem;"></div>

            <!-- ── Section: Comment avez-vous connu l'établissement ── -->
            <div class="pi-section-label">
                <i class="fa-solid fa-bullhorn" style="margin-right:0.4rem;"></i> Comment avez-vous connu l'établissement ?
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.6rem 2rem;margin-bottom:0.5rem;">
                <?php foreach (['Publicité','Relation'] as $src): ?>
                <label class="pi-chk-label">
                    <input type="checkbox" name="sources[]" value="<?= htmlspecialchars($src) ?>" class="pi-chk-input">
                    <span class="pi-chk-box"></span>
                    <span><?= htmlspecialchars($src) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="pi-form-group full" style="margin-bottom:1.5rem;">
                <label>Autre</label>
                <input type="text" name="source_autre" maxlength="255" placeholder="Préciser...">
            </div>

            <!-- ── Buttons ── -->
            <div id="pi-modal-footer" style="display:flex;gap:0.75rem;justify-content:flex-end;border-top:1px solid rgba(255,255,255,0.06);padding-top:1.25rem;">
                <button type="button" onclick="closePiModal()"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#fff;padding:0.6rem 1.2rem;border-radius:8px;cursor:pointer;font-size:0.9rem;">
                    <i class="fa-solid fa-xmark"></i> Annuler
                </button>
                <!-- NEW mode -->
                <button type="submit" id="pi-submit-btn" class="btn" style="background:rgba(168,85,247,0.2);color:#a855f7;border:1px solid rgba(168,85,247,0.4);padding:0.6rem 1.5rem;">
                    <i class="fa-solid fa-save"></i> Enregistrer
                </button>
                <!-- VIEW mode — Director only -->
                <?php if (gds_is_directeur()): ?>
                <button type="button" id="pi-modal-refuse-btn" class="btn"
                        style="display:none;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);padding:0.6rem 1.2rem;"
                        onclick="confirmRefuse(document.getElementById('pi-modal-refuser-id').value)">
                    <i class="fa-solid fa-ban"></i> Marquer comme abandonnée
                </button>
                <button type="button" id="pi-modal-accept-btn" class="btn"
                        style="display:none;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);padding:0.6rem 1.5rem;"
                        onclick="confirmAccept(document.getElementById('pi-modal-accepter-id').value)">
                    <i class="fa-solid fa-user-check"></i> Valider l'inscription
                </button>
                <?php else: ?>
                <button type="button" id="pi-modal-refuse-btn" style="display:none;" disabled></button>
                <button type="button" id="pi-modal-accept-btn" style="display:none;" disabled></button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ── VALIDATION MODAL — Class selection required ──────────────────────── -->
<div id="gds-valider-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:4000; align-items:center; justify-content:center; padding:1rem;">
    <div style="background:#1a1a2e; border:1px solid rgba(16,185,129,0.3); border-radius:16px; padding:2rem; width:100%; max-width:460px; box-shadow:0 25px 60px rgba(0,0,0,0.6); animation: slideUp 0.25s cubic-bezier(0.16,1,0.3,1);">
        <!-- Header -->
        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem;">
            <div style="width:44px; height:44px; border-radius:12px; background:rgba(16,185,129,0.15); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0;">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:1.1rem; color:#fff;">Valider l'inscription</h3>
                <p style="margin:0; font-size:0.82rem; color:#71717a;">Assignez une classe pour créer le stagiaire</p>
            </div>
            <button onclick="document.getElementById('gds-valider-modal').style.display='none';" style="margin-left:auto; background:none; border:none; color:#71717a; font-size:1.2rem; cursor:pointer; padding:0.25rem; border-radius:6px; line-height:1;" title="Fermer">&times;</button>
        </div>

        <!-- Candidate info -->
        <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:0.9rem 1.1rem; margin-bottom:1.5rem;">
            <div style="font-size:0.75rem; color:#71717a; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.3rem;">Candidat</div>
            <div style="font-weight:700; color:#fff; font-size:1rem;" id="gds-valider-name"></div>
            <div style="font-size:0.82rem; color:#a855f7; margin-top:0.2rem;">Filière : <span id="gds-valider-filiere-name"></span></div>
            <div style="font-size:0.82rem; color:#60a5fa; margin-top:0.2rem;" id="gds-valider-annee-wrap">Année scolaire : <span id="gds-valider-annee-name"></span></div>
        </div>

        <!-- Class selector -->
        <div style="margin-bottom:1rem;">
            <label style="display:block; font-size:0.8rem; color:#a1a1aa; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.5rem;">
                <i class="fa-solid fa-chalkboard-user" style="color:#10b981; margin-right:0.3rem;"></i>
                Classe <span style="color:#ef4444;">*</span>
            </label>
            <select id="gds-valider-classe-select"
                    style="width:100%; background:#111; border:1.5px solid rgba(255,255,255,0.12); border-radius:8px; color:#fff; padding:0.7rem 0.9rem; font-size:0.95rem; outline:none; cursor:pointer; transition:border-color 0.2s;"
                    onchange="document.getElementById('gds-valider-error').style.display='none'; this.style.borderColor=this.value?'#10b981':'rgba(255,255,255,0.12)';">
                <option value="">— Choisir une classe —</option>
            </select>
        </div>

        <!-- Error message -->
        <div id="gds-valider-error" style="display:none; align-items:center; gap:0.5rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:8px; padding:0.6rem 0.9rem; margin-bottom:1rem; font-size:0.85rem; color:#f87171;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Veuillez sélectionner une classe avant de valider.</span>
        </div>

        <!-- Actions -->
        <div style="display:flex; gap:0.75rem; margin-top:0.5rem;">
            <button id="gds-valider-confirm-btn" class="btn" style="flex:1; background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.35); border-radius:8px; font-size:0.95rem; font-weight:700; padding:0.7rem;">
                <i class="fa-solid fa-user-check"></i> Créer le stagiaire
            </button>
            <button type="button" onclick="document.getElementById('gds-valider-modal').style.display='none';" class="btn secondary" style="flex:0.5; border-radius:8px; padding:0.7rem;">
                Annuler
            </button>
        </div>
    </div>
</div>

<!-- slide panel removed — voir-details now uses pi-add-modal -->

<script>
// ── Classes data (server-side, grouped by filière) ───────────────────────────
var GDS_CLASSES_BY_FILIERE      = <?= json_encode($classes_by_filiere, JSON_HEX_TAG) ?>;
var GDS_CLASSES_BY_FILIERE_YEAR = <?= json_encode($classes_by_filiere_year, JSON_HEX_TAG) ?>;

function confirmAccept(demId) {
    // Get the row to read filière + year info
    var row   = document.getElementById('pi-row-' + demId);
    var filId = row ? parseInt(row.dataset.id_filiere || 0) : 0;
    var annee = row ? (row.dataset.annee || '') : '';

    // Filter classes by filière AND year (Step 7.4)
    var classes = [];
    if (annee && GDS_CLASSES_BY_FILIERE_YEAR[filId] && GDS_CLASSES_BY_FILIERE_YEAR[filId][annee]) {
        classes = GDS_CLASSES_BY_FILIERE_YEAR[filId][annee];
    } else {
        // Fallback: show all classes for this filière if no year stored
        classes = GDS_CLASSES_BY_FILIERE[filId] || [];
    }

    // Build class options — no year suffix needed since they're already filtered
    var optionsHtml = '<option value="">— Choisir une classe —</option>';
    classes.forEach(function(cls) {
        optionsHtml += '<option value="' + cls.id_classe + '">'
            + cls.nom_classe + ' — ' + cls.niveau
            + '</option>';
    });

    // Populate and show the validation modal
    document.getElementById('gds-valider-filiere-name').textContent = row ? (row.dataset.filiere || '') : '';
    document.getElementById('gds-valider-name').textContent         = row ? ((row.dataset.prenom || '') + ' ' + (row.dataset.nom || '')) : '';
    // Show year in candidate box
    var anneeWrap = document.getElementById('gds-valider-annee-wrap');
    document.getElementById('gds-valider-annee-name').textContent = annee || '—';
    if (anneeWrap) anneeWrap.style.display = annee ? '' : 'none';
    document.getElementById('gds-valider-classe-select').innerHTML  = optionsHtml;
    document.getElementById('gds-valider-classe-select').value      = '';
    document.getElementById('gds-valider-error').style.display      = 'none';

    document.getElementById('gds-valider-confirm-btn').onclick = function() {
        var clsId = document.getElementById('gds-valider-classe-select').value;
        if (!clsId) {
            document.getElementById('gds-valider-error').style.display = 'flex';
            document.getElementById('gds-valider-classe-select').focus();
            return;
        }
        // Inject override_id_classe into the hidden form and submit
        var form = document.getElementById('form-accept-' + demId);
        var input = form.querySelector('input[name="override_id_classe"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'override_id_classe';
            form.appendChild(input);
        }
        input.value = clsId;
        form.dataset.confirmed = 'true';
        document.getElementById('gds-valider-modal').style.display = 'none';
        form.submit();
    };

    document.getElementById('gds-valider-modal').style.display = 'flex';
    setTimeout(function(){ document.getElementById('gds-valider-classe-select').focus(); }, 80);
}
function confirmRefuse(demId) {
    showGdsConfirm('Marquer cette demande comme abandonnée ?', function() {
        var form = document.getElementById('form-refuse-' + demId);
        if (form) { form.dataset.confirmed = 'true'; form.submit(); }
    });
}
function confirmAcceptPanel() { confirmAccept(document.getElementById('pi-modal-accepter-id').value); }
function confirmRefusePanel()  { confirmRefuse(document.getElementById('pi-modal-refuser-id').value); }

// Auto-reload — paused while modal is open
function startRefreshTimer() {
    if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
    // Only schedule if the add-modal is NOT open
    if (!document.getElementById('pi-add-modal').classList.contains('open')) {
        window.refreshTimeout = setTimeout(() => window.location.reload(), 30000);
    }
}
startRefreshTimer();

// Pause auto-reload when user opens the new-preinscription modal

// Resume when modal is closed via Annuler or X

// ── Year filter state ────────────────────────────────────────────────────────
var activeYearTraiter = '';
var activeYearHist    = '';

function applyTraiterFilters() {
    var term = document.getElementById('traiter-search').value.toLowerCase().trim();
    document.querySelectorAll('.pi-row[data-pisearch]').forEach(function(row) {
        var matchSearch = row.dataset.pisearch.includes(term);
        var rowYear     = (row.dataset.annee || '').trim();
        // Rows with no year stored are shown under every year filter
        var matchYear   = !activeYearTraiter || rowYear === '' || rowYear === activeYearTraiter;
        row.style.display = (matchSearch && matchYear) ? '' : 'none';
    });
    if (typeof refreshGroupHeaders === 'function') refreshGroupHeaders();
}

function applyHistFilters() {
    var term = document.getElementById('historique-search').value.toLowerCase().trim();
    document.querySelectorAll('#historique-table tbody tr[data-search]').forEach(function(row) {
        var matchSearch = row.dataset.search.includes(term);
        var rowYear     = (row.dataset.annee || '').trim();
        // Rows with no year stored are shown under every year filter
        var matchYear   = !activeYearHist || rowYear === '' || rowYear === activeYearHist;
        row.style.display = (matchSearch && matchYear) ? '' : 'none';
    });
}

var traiterSearchEl = document.getElementById('traiter-search');
if (traiterSearchEl) {
    traiterSearchEl.addEventListener('input', applyTraiterFilters);
    applyTraiterFilters();
}
document.getElementById('historique-search').addEventListener('input', applyHistFilters);
// Run hist filter once on load so cards are visible with correct state
applyHistFilters();

// Year pill buttons — À Traiter
document.querySelectorAll('.pi-year-btn-traiter').forEach(function(btn) {
    btn.addEventListener('click', function() {
        activeYearTraiter = this.dataset.year;
        document.querySelectorAll('.pi-year-btn-traiter').forEach(function(b) {
            var isActive = b.dataset.year === activeYearTraiter;
            b.style.background    = isActive ? 'rgba(249,115,22,0.15)' : 'transparent';
            b.style.color         = isActive ? '#f97316' : '#a1a1aa';
            b.style.borderColor   = isActive ? 'rgba(249,115,22,0.4)' : 'rgba(255,255,255,0.12)';
            b.classList.toggle('active', isActive);
        });
        applyTraiterFilters();
    });
});

// Year pill buttons — Historique
document.querySelectorAll('.pi-year-btn-hist').forEach(function(btn) {
    btn.addEventListener('click', function() {
        activeYearHist = this.dataset.year;
        document.querySelectorAll('.pi-year-btn-hist').forEach(function(b) {
            var isActive = b.dataset.year === activeYearHist;
            b.style.background    = isActive ? 'rgba(161,161,170,0.2)' : 'transparent';
            b.style.color         = isActive ? '#e4e4e7' : '#a1a1aa';
            b.style.borderColor   = isActive ? 'rgba(161,161,170,0.5)' : 'rgba(255,255,255,0.12)';
            b.classList.toggle('active', isActive);
        });
        applyHistFilters();
    });
});

// ── Helper: open modal in READ-ONLY mode (historique — already treated) ────────
function openViewModalReadOnly(btn) {
    if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
    var d = btn.dataset;
    document.getElementById('pi-modal-title-icon').className = 'fa-solid fa-eye';
    document.getElementById('pi-modal-title-text').textContent = 'Fiche de pré-inscription (archivée)';
    // Hide all action buttons — read only
    document.getElementById('pi-submit-btn').style.display        = 'none';
    document.getElementById('pi-modal-refuse-btn').style.display  = 'none';
    document.getElementById('pi-modal-accept-btn').style.display  = 'none';
    document.getElementById('pi-modal-modifier-id').value = '';
    document.getElementById('pi-modal-accepter-id').value = '';
    document.getElementById('pi-modal-refuser-id').value  = '';
    document.getElementById('pi-modal-nouvelle').value    = '';

    var form = document.querySelector('#pi-add-modal form');
    form.elements['nom'].value              = d.nom || '';
    form.elements['prenom'].value           = d.prenom || '';
    form.elements['cin'].value              = d.cin || '';
    form.elements['date_naissance'].value   = d.date_naissance || '';
    form.elements['telephone'].value        = d.telephone || '';
    form.elements['telephone_parent'].value = d.telephone_parent || '';
    form.elements['email'].value            = d.email || '';
    form.elements['adresse'].value          = d.adresse || '';
    form.elements['nom_tuteur'].value       = d.nom_tuteur || '';
    form.elements['source_autre'].value     = d.source_autre || '';
    if (form.elements['autre_formation']) form.elements['autre_formation'].value = d.autre_formation || '';

    form.querySelectorAll('input[name="sexe"]').forEach(function(r) { r.checked = (r.value === d.sexe); });
    var niveaux = []; try { niveaux = JSON.parse(d.niveaux || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="niveaux[]"]').forEach(function(c) { c.checked = niveaux.indexOf(c.value) !== -1; });
    var filSelect = document.getElementById('pi-id-filiere-hidden');
    filSelect.value = d.id_filiere || '';
    var anneeSelect = document.getElementById('pi-annee-visee');
    if (anneeSelect) anneeSelect.value = d.annee_scolaire_visee || '';
    var formations = []; try { formations = JSON.parse(d.formations || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="formations[]"]').forEach(function(c) { c.checked = formations.indexOf(c.value) !== -1; });
    var sources = []; try { sources = JSON.parse(d.sources || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="sources[]"]').forEach(function(c) { c.checked = sources.indexOf(c.value) !== -1; });
    var licencesPro = []; try { licencesPro = JSON.parse(d.licences || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="licences[]"]').forEach(function(c) { c.checked = licencesPro.indexOf(c.value) !== -1; });

    document.getElementById('pi-add-modal').classList.add('open');
}

// ── Helper: open modal in VIEW mode pre-filled ───────────────────────────────
function openViewModal(btn) {
    if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
    var d = btn.dataset;

    // Switch modal to VIEW mode
    document.getElementById('pi-modal-title-icon').className = 'fa-solid fa-eye';
    document.getElementById('pi-modal-title-text').textContent = 'Fiche de pré-inscription';
    document.getElementById('pi-submit-btn').style.display   = '';
    document.getElementById('pi-modal-refuse-btn').style.display = 'none';
    document.getElementById('pi-modal-accept-btn').style.display = 'none';
    // Switch to edit action
    document.getElementById('pi-modal-action').value = 'modifier_demande';
    document.getElementById('pi-modal-nouvelle').value = '';

    // Set accept/refuse IDs
    var demId = d.id_demande || '';
    document.getElementById('pi-modal-accepter-id').value = demId;
    document.getElementById('pi-modal-refuser-id').value  = demId;
    document.getElementById('pi-modal-modifier-id').value = demId;

    // Fill text fields
    var form = document.querySelector('#pi-add-modal form');
    form.elements['nom'].value              = d.nom || '';
    form.elements['prenom'].value           = d.prenom || '';
    form.elements['cin'].value              = d.cin || '';
    form.elements['date_naissance'].value   = d.date_naissance || '';
    form.elements['telephone'].value        = d.telephone || '';
    form.elements['telephone_parent'].value = d.telephone_parent || '';
    form.elements['email'].value            = d.email || '';
    form.elements['adresse'].value          = d.adresse || '';
    form.elements['nom_tuteur'].value       = d.nom_tuteur || '';
    form.elements['source_autre'].value     = d.source_autre || '';
    if (form.elements['autre_formation']) {
        form.elements['autre_formation'].value = d.autre_formation || '';
    }

    // Sexe radio
    form.querySelectorAll('input[name="sexe"]').forEach(function(r) {
        r.checked = (r.value === d.sexe);
    });

    // Niveaux checkboxes
    var niveaux = [];
    try { niveaux = JSON.parse(d.niveaux || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="niveaux[]"]').forEach(function(c) {
        c.checked = niveaux.indexOf(c.value) !== -1;
    });

    // Set filière select to the saved value
    var filSelect = document.getElementById('pi-id-filiere-hidden');
    filSelect.value = d.id_filiere || '';
    filSelect.style.borderColor = filSelect.value ? '#a855f7' : 'rgba(255,255,255,0.12)';

    // Set année scolaire visée select to the saved value
    var anneeSelect = document.getElementById('pi-annee-visee');
    if (anneeSelect) {
        anneeSelect.value = d.annee_scolaire_visee || '';
        anneeSelect.style.borderColor = anneeSelect.value ? '#a855f7' : 'rgba(255,255,255,0.12)';
    }

    // Formations checkboxes
    var formations = [];
    try { formations = JSON.parse(d.formations || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="formations[]"]').forEach(function(c) {
        c.checked = formations.indexOf(c.value) !== -1;
    });

    // Sources checkboxes
    var sources = [];
    try { sources = JSON.parse(d.sources || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="sources[]"]').forEach(function(c) {
        c.checked = sources.indexOf(c.value) !== -1;
    });

    // Licences professionnelles checkboxes
    var licencesPro = [];
    try { licencesPro = JSON.parse(d.licences || '[]'); } catch(e) {}
    form.querySelectorAll('input[name="licences[]"]').forEach(function(c) {
        c.checked = licencesPro.indexOf(c.value) !== -1;
    });

    document.getElementById('pi-add-modal').classList.add('open');
}

// ── Helper: open modal in NEW mode (reset) ────────────────────────────────────
function openNewModal() {
    if (window.refreshTimeout) clearTimeout(window.refreshTimeout);
    document.getElementById('pi-modal-title-icon').className = 'fa-solid fa-user-plus';
    document.getElementById('pi-modal-title-text').textContent = 'Nouvelle pré-inscription';
    document.getElementById('pi-modal-action').value = 'ajouter_demande';
    document.getElementById('pi-modal-nouvelle').value = '1';
    document.getElementById('pi-modal-modifier-id').value = '';
    document.getElementById('pi-submit-btn').style.display   = '';
    document.getElementById('pi-modal-refuse-btn').style.display = 'none';
    document.getElementById('pi-modal-accept-btn').style.display = 'none';
    document.getElementById('pi-modal-accepter-id').value = '';
    document.getElementById('pi-modal-refuser-id').value  = '';
    document.querySelector('#pi-add-modal form').reset();
    document.getElementById('pi-id-filiere-hidden').value = '';
    document.getElementById('pi-id-filiere-hidden').style.borderColor = 'rgba(255,255,255,0.12)';
    // Reset year select — keep the default (most recent) year pre-selected
    var anneeNew = document.getElementById('pi-annee-visee');
    if (anneeNew) {
        anneeNew.selectedIndex = anneeNew.options.length > 1 ? 1 : 0;
        anneeNew.style.borderColor = anneeNew.value ? '#a855f7' : 'rgba(255,255,255,0.12)';
    }
    document.getElementById('pi-add-modal').classList.add('open');
}

function closePiModal() {
    document.getElementById('pi-add-modal').classList.remove('open');
    setTimeout(startRefreshTimer, 300);
}

document.querySelectorAll('.btn-voir-details').forEach(function(btn) {
    btn.addEventListener('click', function() { openViewModal(this); });
});
document.querySelectorAll('.btn-voir-details-hist').forEach(function(btn) {
    btn.addEventListener('click', function() { openViewModalReadOnly(this); });
});

// Close modal on overlay click
document.getElementById('pi-add-modal').addEventListener('click', function(e) {
    if (e.target === this) { closePiModal(); }
});

// ── Diplôme checkbox → set hidden id_filiere from FIRST checked ──────
// id_filiere is now a <select> — no JS needed to sync it
</script>

<!-- ── BULK ACCEPT MODAL (Pre-flight Summary) ─────────────────── -->
<div id="pi-bulk-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.78);z-index:10500;align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem;">
    <div style="background:#13132a;border:1px solid rgba(168,85,247,0.3);border-radius:16px;padding:1.75rem 1.75rem 1.5rem;width:100%;max-width:700px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.05rem;color:#a855f7;display:flex;align-items:center;gap:0.5rem;">
                <i class="fa-solid fa-clipboard-check"></i> Vérification avant validation groupée
            </h3>
            <button type="button" onclick="closeBulkModal()" style="background:none;border:none;color:#71717a;font-size:1.2rem;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <!-- Loading state -->
        <div id="pi-bulk-modal-loading" style="text-align:center;padding:2rem 1rem;color:#a1a1aa;">
            <i class="fa-solid fa-circle-notch fa-spin" style="font-size:1.5rem;color:#a855f7;margin-bottom:0.75rem;display:block;"></i>
            Analyse en cours…
        </div>
        <!-- Groups body -->
        <div id="pi-bulk-modal-body" style="display:none;">
            <p style="font-size:0.85rem;color:#a1a1aa;margin:0 0 1rem;">Candidats regroupés par filière. Vérifiez les capacités avant de confirmer.</p>
            <div id="pi-bulk-groups-table"></div>
            <div id="pi-bulk-modal-err" style="display:none;align-items:center;gap:0.5rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:0.55rem 0.9rem;margin:.75rem 0 0;font-size:0.85rem;color:#f87171;">
                <i class="fa-solid fa-triangle-exclamation"></i> <span id="pi-bulk-err-msg"></span>
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1.25rem;">
                <button type="button" onclick="submitBulkAccept()" style="flex:1;padding:0.65rem;border-radius:8px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.35);color:#10b981;font-weight:700;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;">
                    <i class="fa-solid fa-user-check"></i> Confirmer l'inscription
                </button>
                <button type="button" onclick="closeBulkModal()" style="padding:0.65rem 1rem;border-radius:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);color:#a1a1aa;font-weight:600;font-size:0.9rem;cursor:pointer;">
                    Annuler
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for bulk operations -->
<form id="pi-bulk-form" method="post" style="display:none;">
    <?= csrf_hidden() ?>
    <div id="pi-bulk-ids-container"></div>
    <input type="hidden" name="bulk_groups" id="pi-bulk-form-groups">
</form>

<script>
// ── Bulk selection ────────────────────────────────────────────────────────────
var _bulkSelected = new Set();

function updateBulkBar() {
    var n = _bulkSelected.size;
    var bar = document.getElementById('pi-bulk-bar');
    var countEl = document.getElementById('pi-bulk-n');
    if (bar) { bar.classList.toggle('visible', n > 0); }
    if (countEl) countEl.textContent = n;
}

function clearBulkSelection() {
    _bulkSelected.clear();
    document.querySelectorAll('.pi-row-cb').forEach(function(cb) {
        cb.checked = false;
        var row = cb.closest('.pi-row');
        if (row) row.classList.remove('pi-row-selected');
    });
    var selectAll = document.getElementById('pi-select-all');
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
}

document.querySelectorAll('.pi-row-cb').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var id = this.dataset.id;
        var row = this.closest('.pi-row');
        if (this.checked) {
            _bulkSelected.add(id);
            if (row) row.classList.add('pi-row-selected');
        } else {
            _bulkSelected.delete(id);
            if (row) row.classList.remove('pi-row-selected');
        }
        // Update select-all state
        var allCbs = document.querySelectorAll('.pi-row-cb');
        var checkedCount = document.querySelectorAll('.pi-row-cb:checked').length;
        var selectAll = document.getElementById('pi-select-all');
        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === allCbs.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < allCbs.length;
        }
        updateBulkBar();
    });
});

var selectAllEl = document.getElementById('pi-select-all');
if (selectAllEl) {
    selectAllEl.addEventListener('change', function() {
        var checked = this.checked;
        document.querySelectorAll('.pi-row-cb').forEach(function(cb) {
            // Only select visible rows
            var row = cb.closest('.pi-row');
            if (row && row.style.display !== 'none') {
                cb.checked = checked;
                var id = cb.dataset.id;
                if (checked) {
                    _bulkSelected.add(id);
                    if (row) row.classList.add('pi-row-selected');
                } else {
                    _bulkSelected.delete(id);
                    if (row) row.classList.remove('pi-row-selected');
                }
            }
        });
        updateBulkBar();
    });
}

function bulkRefuse() {
    if (_bulkSelected.size === 0) return;
    var n = _bulkSelected.size;
    showGdsConfirm(
        'Marquer ' + n + ' pré-inscription(s) comme abandonnée(s) ?',
        function() {
            var form = document.getElementById('pi-bulk-form');
            document.getElementById('pi-bulk-ids-container').innerHTML = '';
            _bulkSelected.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'bulk_ids[]'; inp.value = id;
                document.getElementById('pi-bulk-ids-container').appendChild(inp);
            });
            var actionInp = document.createElement('input');
            actionInp.type = 'hidden'; actionInp.name = 'bulk_refuser'; actionInp.value = '1';
            document.getElementById('pi-bulk-ids-container').appendChild(actionInp);
            form.submit();
        }
    );
}

// ── Group-by-filière toggle ───────────────────────────────────────────────────
var _groupByFiliere = false;

function toggleGroupByFiliere() {
    _groupByFiliere = !_groupByFiliere;
    var btn = document.getElementById('pi-group-toggle');
    if (btn) {
        btn.innerHTML = _groupByFiliere
            ? '<i class="fa-solid fa-list"></i> Vue liste'
            : '<i class="fa-solid fa-layer-group"></i> Grouper par filière';
        btn.style.background = _groupByFiliere ? 'rgba(168,85,247,0.18)' : 'transparent';
        btn.style.borderColor = _groupByFiliere ? '#a855f7' : 'rgba(168,85,247,0.3)';
        btn.onmouseover = function(){ this.style.background = _groupByFiliere ? 'rgba(168,85,247,0.25)' : 'rgba(168,85,247,0.12)'; };
        btn.onmouseout  = function(){ this.style.background = _groupByFiliere ? 'rgba(168,85,247,0.18)' : 'transparent'; };
    }
    renderPiList();
}

function renderPiList() {
    var container = document.getElementById('pi-rows-container');
    if (!container) return;

    // Collect all real pi-rows (not group headers we inserted)
    var allRows = Array.from(container.querySelectorAll('.pi-row[id^="pi-row-"]'));

    // Remove any previously inserted group headers
    container.querySelectorAll('.pi-filiere-header').forEach(function(h) { h.remove(); });

    if (!_groupByFiliere) {
        // Flat list — restore original order (just ensure all rows are back as siblings)
        allRows.forEach(function(row) { container.appendChild(row); });
        return;
    }

    // Group by filière — collect filière ordering from rows
    var groups = {};
    var filiereOrder = [];
    allRows.forEach(function(row) {
        var filiere = row.dataset.filiere || '—';
        var filiereId = row.dataset.id_filiere || '0';
        var key = filiereId + '||' + filiere;
        if (!groups[key]) {
            groups[key] = { filiere: filiere, rows: [] };
            filiereOrder.push(key);
        }
        groups[key].rows.push(row);
    });

    // Clear container, then re-insert in grouped order
    container.innerHTML = '';
    filiereOrder.forEach(function(key) {
        var g = groups[key];
        var visible = g.rows.filter(function(r) { return r.style.display !== 'none'; });
        var total   = g.rows.length;
        var visibleN = visible.length;

        // Filière header
        var header = document.createElement('div');
        header.className = 'pi-filiere-header';
        header.style.cssText = 'display:flex;align-items:center;gap:0.6rem;padding:0.55rem 0.25rem 0.35rem;margin-top:0.6rem;border-bottom:1px solid rgba(168,85,247,0.18);';
        header.innerHTML = '<i class="fa-solid fa-folder-open" style="color:#a855f7;font-size:0.85rem;"></i>'
            + '<span style="font-weight:700;font-size:0.88rem;color:#d8b4fe;">' + htmlEsc(g.filiere) + '</span>'
            + '<span id="pi-fh-count-' + key.replace(/\W/g,'_') + '" style="background:rgba(168,85,247,0.15);color:#d8b4fe;border:1px solid rgba(168,85,247,0.25);border-radius:12px;font-size:0.7rem;font-weight:700;padding:0.1rem 0.5rem;">' + visibleN + ' / ' + total + '</span>';
        container.appendChild(header);

        g.rows.forEach(function(row) { container.appendChild(row); });
    });
}

// Re-apply group view after year-filter or search changes visibility
function refreshGroupHeaders() {
    if (!_groupByFiliere) return;
    var container = document.getElementById('pi-rows-container');
    if (!container) return;
    container.querySelectorAll('.pi-filiere-header').forEach(function(h) {
        var key = null;
        // Find next sibling rows to count visible
        var countEl = h.querySelector('[id^="pi-fh-count-"]');
        var visible = 0, total = 0;
        var next = h.nextElementSibling;
        while (next && next.classList.contains('pi-row')) {
            total++;
            if (next.style.display !== 'none') visible++;
            next = next.nextElementSibling;
        }
        if (countEl) countEl.textContent = visible + ' / ' + total;
        // Hide header if all rows in group are hidden
        h.style.display = (total === 0 || visible === 0) ? 'none' : 'flex';
    });
}

var _bulkPreflight = [];

function closeBulkModal() {
    document.getElementById('pi-bulk-modal').style.display = 'none';
    document.getElementById('pi-bulk-modal-loading').style.display = 'block';
    document.getElementById('pi-bulk-modal-body').style.display = 'none';
    _bulkPreflight = [];
}

function bulkAccept() {
    if (_bulkSelected.size === 0) return;

    // Show modal with loading spinner
    document.getElementById('pi-bulk-modal').style.display = 'flex';
    document.getElementById('pi-bulk-modal-loading').style.display = 'block';
    document.getElementById('pi-bulk-modal-body').style.display = 'none';
    document.getElementById('pi-bulk-modal-err').style.display = 'none';

    var fd = new FormData();
    fd.append('bulk_preflight', '1');
    fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);
    _bulkSelected.forEach(function(id) { fd.append('bulk_ids[]', id); });

    fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _bulkPreflight = data.groups || [];
            renderBulkGroups(_bulkPreflight);
        })
        .catch(function() {
            document.getElementById('pi-bulk-modal-loading').innerHTML =
                '<i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>Erreur lors de la vérification.';
        });
}

function htmlEsc(s) {
    if (!s) return '—';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderBulkGroups(groups) {
    var tbl = document.getElementById('pi-bulk-groups-table');
    tbl.innerHTML = '';

    if (!groups || groups.length === 0) {
        tbl.innerHTML = '<p style="color:#a1a1aa;font-size:0.88rem;text-align:center;padding:1rem 0;">Aucun candidat en attente dans la sélection.</p>';
        document.getElementById('pi-bulk-modal-loading').style.display = 'none';
        document.getElementById('pi-bulk-modal-body').style.display = 'block';
        return;
    }

    var html = '<div style="display:flex;flex-direction:column;gap:0.75rem;">';

    groups.forEach(function(g, idx) {
        var isNoClass = g.status === 'no_class';

        var borderColor = isNoClass ? 'rgba(251,146,60,0.4)' : 'rgba(168,85,247,0.25)';
        var bgColor     = isNoClass ? 'rgba(251,146,60,0.06)' : 'rgba(168,85,247,0.04)';

        var skipNote = isNoClass
            ? '<div style="margin-top:0.5rem;font-size:0.78rem;color:#fb923c;"><i class="fa-solid fa-triangle-exclamation"></i> Ces candidats seront ignorés — aucune classe trouvée pour cette filière.</div>'
            : '';

        var classSelectHtml = '';
        if (!isNoClass && g.classes && g.classes.length > 0) {
            var opts = '';
            g.classes.forEach(function(cls) {
                var label = cls.nom_classe + ' (' + (cls.annee_scolaire || '—') + ') · ' + (cls.niveau || '—') + ' — ' + cls.effectif + '/' + cls.capacite + ' places';
                var selected = cls.id_classe === g.id_classe ? ' selected' : '';
                opts += '<option value="' + cls.id_classe + '" data-effectif="' + cls.effectif + '" data-capacite="' + cls.capacite + '" data-status="' + cls.status + '"' + selected + '>' + htmlEsc(label) + '</option>';
            });
            classSelectHtml = '<div style="margin-top:0.6rem;">'
                + '<label style="font-size:0.78rem;color:#a1a1aa;display:block;margin-bottom:0.3rem;">Classe cible</label>'
                + '<select id="bulk-cls-' + idx + '" onchange="onBulkClassChange(' + idx + ',' + g.count + ')"'
                + ' style="width:100%;background:#1e1e3a;border:1px solid rgba(168,85,247,0.35);border-radius:7px;color:#e2e8f0;font-size:0.85rem;padding:0.45rem 0.6rem;cursor:pointer;outline:none;">'
                + opts
                + '</select>'
                + '</div>';
        }

        var capacityBarHtml = '';
        if (!isNoClass) {
            var after  = g.effectif + g.count;
            var cap    = g.capacite;
            var capPct = cap > 0 ? Math.min(100, Math.round((after / cap) * 100)) : 100;
            var barCol = capPct >= 100 ? '#ef4444' : (capPct >= 80 ? '#fb923c' : '#10b981');
            capacityBarHtml = '<div id="bulk-capbar-' + idx + '" style="margin-top:0.55rem;">'
                + '<div style="display:flex;justify-content:space-between;font-size:0.75rem;color:#a1a1aa;margin-bottom:0.22rem;">'
                + '<span>Après inscription</span>'
                + '<span id="bulk-caplabel-' + idx + '" style="font-weight:700;color:' + barCol + ';">' + after + ' / ' + cap + '</span>'
                + '</div>'
                + '<div style="height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;">'
                + '<div id="bulk-capfill-' + idx + '" style="height:100%;width:' + capPct + '%;background:' + barCol + ';border-radius:3px;transition:width 0.25s,background 0.25s;"></div>'
                + '</div>'
                + '<div id="bulk-capstatus-' + idx + '" style="margin-top:0.35rem;"></div>'
                + '</div>';
        }

        var overrideHtml = (!isNoClass && g.status === 'full')
            ? '<label id="bulk-override-wrap-' + idx + '" style="display:flex;align-items:center;gap:0.5rem;margin-top:0.55rem;font-size:0.82rem;color:#f87171;cursor:pointer;">'
              + '<input type="checkbox" id="override-' + idx + '" style="accent-color:#ef4444;cursor:pointer;width:15px;height:15px;">'
              + 'Inscrire quand même (dépasser la capacité)</label>'
            : '<span id="bulk-override-wrap-' + idx + '"></span>';

        html += '<div style="border:1px solid ' + borderColor + ';background:' + bgColor + ';border-radius:10px;padding:0.9rem 1rem;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;">'
            + '<div style="min-width:0;">'
            + '<div style="font-weight:700;font-size:0.93rem;color:#fff;margin-bottom:0.15rem;">'
            + htmlEsc(g.nom_filiere)
            + '</div>'
            + '<div style="font-size:0.78rem;color:#a1a1aa;">Année visée : ' + htmlEsc(g.annee || '—') + '</div>'
            + '</div>'
            + '<span style="background:rgba(168,85,247,0.12);color:#d8b4fe;border:1px solid rgba(168,85,247,0.25);border-radius:6px;font-size:0.74rem;font-weight:700;padding:0.15rem 0.55rem;white-space:nowrap;">' + g.count + ' candidat(s)</span>'
            + '</div>'
            + classSelectHtml
            + capacityBarHtml
            + overrideHtml
            + skipNote
            + '</div>';
    });

    html += '</div>';
    tbl.innerHTML = html;

    document.getElementById('pi-bulk-modal-loading').style.display = 'none';
    document.getElementById('pi-bulk-modal-body').style.display = 'block';
}

function onBulkClassChange(idx, count) {
    var sel = document.getElementById('bulk-cls-' + idx);
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var eff    = parseInt(opt.getAttribute('data-effectif')) || 0;
    var cap    = parseInt(opt.getAttribute('data-capacite')) || 30;
    var status = opt.getAttribute('data-status') || 'ok';
    var after  = eff + count;
    var capPct = cap > 0 ? Math.min(100, Math.round((after / cap) * 100)) : 100;
    var barCol = capPct >= 100 ? '#ef4444' : (capPct >= 80 ? '#fb923c' : '#10b981');

    var labelEl = document.getElementById('bulk-caplabel-' + idx);
    var fillEl  = document.getElementById('bulk-capfill-'  + idx);
    if (labelEl) { labelEl.textContent = after + ' / ' + cap; labelEl.style.color = barCol; }
    if (fillEl)  { fillEl.style.width = capPct + '%'; fillEl.style.background = barCol; }

    var wrapEl = document.getElementById('bulk-override-wrap-' + idx);
    if (wrapEl) {
        if (status === 'full') {
            wrapEl.innerHTML = '<label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.55rem;font-size:0.82rem;color:#f87171;cursor:pointer;">'
                + '<input type="checkbox" id="override-' + idx + '" style="accent-color:#ef4444;cursor:pointer;width:15px;height:15px;">'
                + 'Inscrire quand même (dépasser la capacité)</label>';
        } else {
            wrapEl.innerHTML = '';
        }
    }
}

function submitBulkAccept() {
    if (!_bulkPreflight || _bulkPreflight.length === 0) return;

    var groups = [];
    var hasActionable = false;

    _bulkPreflight.forEach(function(g, idx) {
        if (g.status === 'no_class') return; // auto-skip, no class exists for this filière

        // Read the class chosen in the dropdown (may differ from preflight default)
        var sel = document.getElementById('bulk-cls-' + idx);
        var classeId = sel ? parseInt(sel.value) : g.id_classe;
        if (!classeId || classeId <= 0) return;

        // Determine status for the chosen class
        var chosenStatus = g.status;
        if (sel) {
            var opt = sel.options[sel.selectedIndex];
            chosenStatus = opt ? (opt.getAttribute('data-status') || 'ok') : g.status;
        }

        var override = false;
        var cb = document.getElementById('override-' + idx);
        if (cb) override = cb.checked;

        if (!override && chosenStatus === 'full') return; // full and no override — skip

        groups.push({ id_classe: classeId, override: override, ids: g.ids });
        hasActionable = true;
    });

    if (!hasActionable) {
        document.getElementById('pi-bulk-err-msg').textContent =
            'Aucun candidat à inscrire. Cochez "Inscrire quand même" pour les classes pleines, ou ajoutez des classes manquantes via "Gestion des classes".';
        document.getElementById('pi-bulk-modal-err').style.display = 'flex';
        return;
    }

    var idsContainer = document.getElementById('pi-bulk-ids-container');
    idsContainer.innerHTML = '';
    var actionInp = document.createElement('input');
    actionInp.type = 'hidden'; actionInp.name = 'bulk_accepter'; actionInp.value = '1';
    idsContainer.appendChild(actionInp);

    document.getElementById('pi-bulk-form-groups').value = JSON.stringify(groups);
    closeBulkModal();
    document.getElementById('pi-bulk-form').submit();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
