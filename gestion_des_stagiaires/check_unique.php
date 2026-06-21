<?php
  /**
   * check_unique.php – Vérification d'unicité par AJAX
   *
   * Utilisé lors de la saisie dans les formulaires (pré-inscription, etc.)
   * pour vérifier en temps réel si un CIN ou un email est déjà pris,
   * soit dans la table des stagiaires inscrits, soit dans les demandes en attente.
   *
   * Paramètres GET :
   *   - field   : champ à vérifier ('cin' ou 'email')
   *   - value   : valeur saisie par l'utilisateur
   *   - exclude : (optionnel) id_demande à exclure du contrôle (pour la re-vérification d'une demande existante)
   *
   * Réponse JSON :
   *   { "ok": true }                         – valeur disponible
   *   { "ok": false, "message": "..." }      – valeur déjà utilisée
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  // Toutes les réponses sont en JSON UTF-8
  header('Content-Type: application/json; charset=utf-8');

  // ──────────────────────────────────────────
  // 1. Lecture et validation des paramètres
  // ──────────────────────────────────────────

  /** Champ à vérifier : 'cin' ou 'email' */
  $champDemande = (string)($_GET['field']   ?? '');

  /** Valeur saisie par l'utilisateur (on supprime les espaces superflus) */
  $valeurSaisie = trim((string)($_GET['value']  ?? ''));

  /** Identifiant de la demande à exclure (0 = aucune exclusion) */
  $idDemandeExclue = (int)($_GET['exclude'] ?? 0);

  // Si la valeur est vide ou le champ n'est pas reconnu, on valide sans aller en base
  if ($valeurSaisie === '' || !in_array($champDemande, ['cin', 'email'], true)) {
      echo json_encode(['ok' => true]);
      exit;
  }

  // ──────────────────────────────────────────
  // 2. Contrôle dans les tables de la base
  // ──────────────────────────────────────────

  /** Indique si la valeur est déjà prise */
  $dejaUtilise = false;

  /** Source du conflit : 'stagiaire' ou 'demande' */
  $sourceConflit = '';

  // 2a. Vérification dans la table des stagiaires inscrits
  $requete = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE $champDemande = ?");
  $requete->execute([$valeurSaisie]);

  if ((int)$requete->fetchColumn() > 0) {
      $dejaUtilise = true;
      $sourceConflit = 'stagiaire';
  }

  // 2b. Si pas encore de conflit, vérification dans les demandes en attente
  //     On exclut la demande en cours de modification si un id est fourni
  if (!$dejaUtilise) {
      $sqlDemande = "SELECT COUNT(*) FROM pre_inscription WHERE $champDemande = ? AND statut = 'en_attente'";
      $parametres = [$valeurSaisie];

      if ($idDemandeExclue > 0) {
          $sqlDemande .= " AND id_demande != ?";
          $parametres[] = $idDemandeExclue;
      }

      $requete = $pdo->prepare($sqlDemande);
      $requete->execute($parametres);

      if ((int)$requete->fetchColumn() > 0) {
          $dejaUtilise = true;
          $sourceConflit = 'demande';
      }
  }

  // ──────────────────────────────────────────
  // 3. Construction et envoi de la réponse
  // ──────────────────────────────────────────

  /** Messages d'erreur selon le champ et la source du conflit */
  $messagesErreur = [
      'cin'   => [
          'stagiaire' => 'Ce CIN est déjà utilisé par un stagiaire inscrit.',
          'demande'   => 'Une demande avec ce CIN est déjà en attente.',
      ],
      'email' => [
          'stagiaire' => 'Cet email est déjà utilisé par un stagiaire inscrit.',
          'demande'   => 'Une demande avec cet email est déjà en attente.',
      ],
  ];

  echo json_encode([
      'ok'      => !$dejaUtilise,
      'message' => $dejaUtilise ? ($messagesErreur[$champDemande][$sourceConflit] ?? 'Valeur déjà utilisée.') : '',
  ]);
  