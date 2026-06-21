<?php
  /**
   * cotisations.php — Gestion des cotisations mensuelles des stagiaires
   *
   * Fonctionnalités :
   *   - Enregistrement d'un paiement individuel (nouveau ou ajout sur existant)
   *   - Marquage en masse comme « payé » (Directeur uniquement)
   *   - Versement partiel en masse
   *   - Détail annuel des paiements d'un stagiaire (tiroir latéral)
   *   - Filtrage par filière → niveau → classe → mois
   *   - Impression de la liste des impayés et du reçu de paiement
   *
   * Tables : mensualites, stagiaires, classes, filieres
   */
  declare(strict_types=1);
  require __DIR__ . '/includes/bootstrap.php';

  $pageTitle = 'Gestion des Paiements';
  $curPage   = 'cotisations';

  // ── Tarifs mensuels par filière (MAD) — clé = id_filiere ─────────────────
  $tarifsDefaut = [2 => 700.0, 3 => 600.0, 4 => 800.0];

  /**
   * Retourne le tarif mensuel pour une filière donnée.
   * Utilise 700 MAD comme valeur par défaut si la filière n'est pas dans le tableau.
   */
  function getTarif(int $idFiliere, array $tarifsDefaut): float {
      return $tarifsDefaut[$idFiliere] ?? 700.0;
  }

  /**
   * Charge le tarif et la remise mensuelle d'un stagiaire depuis la base.
   * Retourne ['tarif' => float, 'remise' => float, 'id_filiere' => int].
   */
  function chargerInfoTarifStag(PDO $pdo, int $idStagiaire, array $tarifsDefaut): ?array {
      $req = $pdo->prepare(
          'SELECT c.id_filiere, COALESCE(s.remise_mensuelle, 0) AS remise_mensuelle
             FROM stagiaires s
             JOIN classes c ON c.id_classe = s.id_classe
            WHERE s.id_stagiaire = ?'
      );
      $req->execute([$idStagiaire]);
      $ligne = $req->fetch();
      if (!$ligne) return null;
      $tarif    = getTarif((int)$ligne['id_filiere'], $tarifsDefaut);
      $remise   = (float)$ligne['remise_mensuelle'];
      $effectif = max(0.0, $tarif - $remise);
      return ['tarif' => $tarif, 'remise' => $remise, 'effectif' => $effectif, 'id_filiere' => (int)$ligne['id_filiere']];
  }

  /**
   * Calcule le statut de paiement à partir des montants.
   * Retourne 'payé', 'partiel' ou 'impayé'.
   */
  function calculerStatutPaiement(float $montantRestant, float $montantPaye): string {
      if ($montantRestant <= 0)     return 'payé';
      if ($montantPaye    >  0)     return 'partiel';
      return 'impayé';
  }


  // ============================================================
  //  SECTION 1 : Gestionnaires POST (toutes les réponses sont JSON)
  // ============================================================

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
      header('Content-Type: application/json');

      // ── 1a. Enregistrement d'un paiement individuel ──────────────────────
      if (isset($_POST['save_payment'])) {
          $idStagiaire  = (int)($_POST['id_stagiaire']     ?? 0);
          $refMois      = trim((string)($_POST['mois_ref']  ?? ''));
          $statutPost   = trim((string)($_POST['statut_paiement'] ?? 'impayé'));
          $datePaiement = ($_POST['date_paiement'] ?? '') !== '' ? (string)$_POST['date_paiement'] : null;
          $nouveauVers  = (float)($_POST['nouveau_versement'] ?? 0);
          $estAjout     = isset($_POST['is_ajout']) && (string)$_POST['is_ajout'] === '1';
          $remiseSaisie = max(0.0, (float)($_POST['remise'] ?? 0));

          if ($idStagiaire <= 0 || !preg_match('/^\d{4}-\d{2}$/', $refMois)) {
              echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
          }
          try {
              // Récupérer les informations tarifaires du stagiaire
              $reqFiliere = $pdo->prepare(
                  'SELECT c.id_filiere FROM stagiaires s JOIN classes c ON c.id_classe = s.id_classe WHERE s.id_stagiaire = ?'
              );
              $reqFiliere->execute([$idStagiaire]);
              $ligneFiliere = $reqFiliere->fetch();
              if (!$ligneFiliere) { echo json_encode(['success' => false, 'error' => 'Stagiaire introuvable.']); exit; }
              $tarif = getTarif((int)$ligneFiliere['id_filiere'], $tarifsDefaut);

              // Vérifier si un enregistrement existe déjà pour ce mois
              $reqExistant = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire = ? AND mois_ref = ?');
              $reqExistant->execute([$idStagiaire, $refMois]);
              $ligneExistante = $reqExistant->fetch();

              if ($estAjout && $ligneExistante) {
                  // ── Mode ajout : additionner un versement au paiement existant ──
                  // Conserver l'ancienne remise si l'utilisateur n'en a pas saisi de nouvelle
                  $remiseEff    = isset($_POST['remise']) ? $remiseSaisie : max(0.0, (float)($ligneExistante['remise'] ?? 0));
                  $effectif     = max(0.0, $tarif - $remiseEff);
                  $ancienPaye   = (float)($ligneExistante['montant_paye'] ?? 0);
                  $nouveauPaye  = min($effectif, $ancienPaye + $nouveauVers);
                  $nouveauRest  = max(0.0, $effectif - $nouveauPaye);
                  $nouveauStatut = calculerStatutPaiement($nouveauRest, $nouveauPaye);
                  $pdo->prepare(
                      "UPDATE mensualites
                          SET remise = ?, montant_paye = ?, montant_restant = ?,
                              statut_paiement = ?, est_paye = ?,
                              date_paiement = COALESCE(?, date_paiement), marque_le = NOW()
                        WHERE id_stagiaire = ? AND mois_ref = ?"
                  )->execute([$remiseEff, $nouveauPaye, $nouveauRest, $nouveauStatut, ($nouveauRest <= 0 ? 1 : 0), $datePaiement, $idStagiaire, $refMois]);
              } else {
                  // ── Mode création / remplacement : INSERT ou UPDATE complet ──
                  $montantTotal   = $tarif;
                  $remiseSaisie   = min($remiseSaisie, $tarif);
                  $effectif       = max(0.0, $tarif - $remiseSaisie);
                  $montantPaye    = (float)($_POST['montant_paye'] ?? 0);
                  if ($statutPost === 'payé')  { $montantPaye = $effectif; }
                  $montantPaye    = min($montantPaye, $effectif);
                  $montantRestant = $statutPost === 'payé' ? 0.0 : max(0.0, $effectif - $montantPaye);
                  $estPaye        = ($statutPost === 'payé') ? 1 : 0;

                  if ($ligneExistante) {
                      $pdo->prepare(
                          "UPDATE mensualites
                              SET est_paye = ?, montant_total = ?, remise = ?, montant_paye = ?,
                                  montant_restant = ?, statut_paiement = ?, date_paiement = ?, marque_le = NOW()
                            WHERE id_stagiaire = ? AND mois_ref = ?"
                      )->execute([$estPaye, $montantTotal, $remiseSaisie, $montantPaye, $montantRestant, $statutPost, $datePaiement, $idStagiaire, $refMois]);
                  } else {
                      $pdo->prepare(
                          "INSERT INTO mensualites
                              (id_stagiaire, mois_ref, est_paye, montant_total, remise,
                               montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())"
                      )->execute([$idStagiaire, $refMois, $estPaye, $montantTotal, $remiseSaisie, $montantPaye, $montantRestant, $statutPost, $datePaiement]);
                  }
              }

              // Retourner la ligne mise à jour pour mise à jour du DOM côté client
              $reqMaj = $pdo->prepare(
                  'SELECT * FROM mensualites WHERE id_stagiaire = ? AND mois_ref = ? ORDER BY id_mensualite DESC LIMIT 1'
              );
              $reqMaj->execute([$idStagiaire, $refMois]);
              $ligneMaj = $reqMaj->fetch() ?: null;
              echo json_encode(['success' => true, 'msg' => 'Paiement enregistré.', 'row' => $ligneMaj, 'tarif' => $tarif]);
          } catch (\Throwable $e) {
              error_log('[cotisations.php save_payment] ' . $e->getMessage());
              echo json_encode(['success' => false, 'error' => 'Une erreur est survenue. Veuillez réessayer.']);
          }
          exit;
      }

      // ── 1b. Marquage en masse comme « payé » (Directeur uniquement) ───────
      if (isset($_POST['bulk_mark_paid'])) {
          if (!gds_is_directeur()) {
              echo json_encode(['success' => false, 'error' => 'Action réservée au Directeur.']); exit;
          }
          $idsStagiaires = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
          $refMois       = trim((string)($_POST['mois_ref'] ?? ''));
          if (empty($idsStagiaires) || !preg_match('/^\d{4}-\d{2}$/', $refMois)) {
              echo json_encode(['success' => false, 'error' => 'Données invalides.']); exit;
          }
          $nbMisAJour = 0;
          try {
              $pdo->beginTransaction();
              foreach ($idsStagiaires as $idStagiaire) {
                  // Charger tarif + remise via le helper mutualisé
                  $infoTarif = chargerInfoTarifStag($pdo, $idStagiaire, $tarifsDefaut);
                  if (!$infoTarif) continue;
                  $pdo->prepare(
                      "INSERT INTO mensualites
                          (id_stagiaire, mois_ref, est_paye, montant_total, remise,
                           montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                       VALUES (?, ?, 1, ?, ?, ?, 0, 0, 'payé', NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                          est_paye = 1, montant_total = VALUES(montant_total),
                          remise = VALUES(remise), montant_paye = VALUES(montant_paye),
                          montant_restant = 0, cumul_restant = 0,
                          statut_paiement = 'payé', date_paiement = NOW(), marque_le = NOW()"
                  )->execute([$idStagiaire, $refMois, $infoTarif['tarif'], $infoTarif['remise'], $infoTarif['effectif']]);
                  $nbMisAJour++;
              }
              $pdo->commit();
              echo json_encode(['success' => true, 'updated' => $nbMisAJour]);
          } catch (\Throwable $e) {
              $pdo->rollBack();
              echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
          }
          exit;
      }

      // ── 1c. Versement partiel en masse ─────────────────────────────────────
      if (isset($_POST['bulk_partial'])) {
          $idsStagiaires = array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])));
          $refMois       = trim((string)($_POST['mois_ref'] ?? ''));
          $montantPartiel = (float)($_POST['montant_partiel'] ?? 0);
          $datePaiement  = ($_POST['date_paiement'] ?? '') !== '' ? (string)$_POST['date_paiement'] : null;
          if (empty($idsStagiaires) || !preg_match('/^\d{4}-\d{2}$/', $refMois) || $montantPartiel <= 0) {
              echo json_encode(['success' => false, 'error' => 'Données invalides (montant ou sélection manquant).']); exit;
          }
          $nbMisAJour = 0;
          try {
              $pdo->beginTransaction();
              foreach ($idsStagiaires as $idStagiaire) {
                  // Charger tarif + remise via le helper mutualisé
                  $infoTarif = chargerInfoTarifStag($pdo, $idStagiaire, $tarifsDefaut);
                  if (!$infoTarif) continue;
                  $effectif = $infoTarif['effectif'];

                  // Vérifier si un paiement partiel existe déjà pour ce mois
                  $reqExistant = $pdo->prepare('SELECT * FROM mensualites WHERE id_stagiaire = ? AND mois_ref = ?');
                  $reqExistant->execute([$idStagiaire, $refMois]);
                  $ligneExistante = $reqExistant->fetch();

                  if ($ligneExistante) {
                      // Additionner le versement au paiement existant
                      $ancienPaye  = (float)($ligneExistante['montant_paye'] ?? 0);
                      $nouveauPaye = min($effectif, $ancienPaye + $montantPartiel);
                      $nouveauRest = max(0.0, $effectif - $nouveauPaye);
                      $nouveauStatut = calculerStatutPaiement($nouveauRest, $nouveauPaye);
                      $pdo->prepare(
                          "UPDATE mensualites
                              SET remise = ?, montant_paye = ?, montant_restant = ?,
                                  statut_paiement = ?, est_paye = ?,
                                  date_paiement = COALESCE(?, date_paiement), marque_le = NOW()
                            WHERE id_stagiaire = ? AND mois_ref = ?"
                      )->execute([$infoTarif['remise'], $nouveauPaye, $nouveauRest, $nouveauStatut, ($nouveauRest <= 0 ? 1 : 0), $datePaiement, $idStagiaire, $refMois]);
                  } else {
                      // Créer un nouvel enregistrement de paiement partiel
                      $nouveauPaye = min($effectif, $montantPartiel);
                      $nouveauRest = max(0.0, $effectif - $nouveauPaye);
                      $nouveauStatut = calculerStatutPaiement($nouveauRest, $nouveauPaye);
                      $pdo->prepare(
                          "INSERT INTO mensualites
                              (id_stagiaire, mois_ref, est_paye, montant_total, remise,
                               montant_paye, montant_restant, cumul_restant, statut_paiement, date_paiement, marque_le)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())"
                      )->execute([$idStagiaire, $refMois, ($nouveauRest <= 0 ? 1 : 0), $infoTarif['tarif'], $infoTarif['remise'], $nouveauPaye, $nouveauRest, $nouveauStatut, $datePaiement]);
                  }
                  $nbMisAJour++;
              }
              $pdo->commit();
              echo json_encode(['success' => true, 'updated' => $nbMisAJour]);
          } catch (\Throwable $e) {
              $pdo->rollBack();
              echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour.']);
          }
          exit;
      }

      // ── 1d. Détail annuel des paiements d'un stagiaire (pour le tiroir) ───
      if (isset($_POST['get_student_payments'])) {
          $idStagiaire = (int)($_POST['id_stagiaire'] ?? 0);
          if ($idStagiaire <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalide.']); exit; }

          // Informations générales du stagiaire (nom, filière, année scolaire, remise)
          $reqInfoStag = $pdo->prepare(
              'SELECT s.nom, s.prenom, s.num_inscri, c.annee_scolaire, c.id_filiere,
                      COALESCE(s.remise_mensuelle, 0) AS remise_mensuelle
                 FROM stagiaires s
                 JOIN classes c ON c.id_classe = s.id_classe
                WHERE s.id_stagiaire = ?'
          );
          $reqInfoStag->execute([$idStagiaire]);
          $infoStag = $reqInfoStag->fetch();
          if (!$infoStag) { echo json_encode(['success' => false, 'error' => 'Stagiaire introuvable.']); exit; }

          $tarif          = getTarif((int)$infoStag['id_filiere'], $tarifsDefaut);
          $anneeScol      = (string)$infoStag['annee_scolaire'];   // ex : "2025/2026"
          $partiesAnnee   = explode('/', $anneeScol);
          $annee1         = (int)($partiesAnnee[0] ?? date('Y'));
          $annee2         = (int)($partiesAnnee[1] ?? ($annee1 + 1));
          $remiseMensDef  = max(0.0, (float)$infoStag['remise_mensuelle']);

          // Liste des 10 mois de l'année scolaire (sept N → juin N+1)
          $listeMois = [
              sprintf('%04d-09', $annee1), sprintf('%04d-10', $annee1),
              sprintf('%04d-11', $annee1), sprintf('%04d-12', $annee1),
              sprintf('%04d-01', $annee2), sprintf('%04d-02', $annee2),
              sprintf('%04d-03', $annee2), sprintf('%04d-04', $annee2),
              sprintf('%04d-05', $annee2), sprintf('%04d-06', $annee2),
          ];

          // Récupérer tous les enregistrements de paiement pour ces mois
          $placeholders     = implode(',', array_fill(0, count($listeMois), '?'));
          $reqPaiements = $pdo->prepare(
              "SELECT mois_ref, montant_total, remise, montant_paye, montant_restant,
                      statut_paiement, est_paye, date_paiement
                 FROM mensualites
                WHERE id_stagiaire = ? AND mois_ref IN ($placeholders)"
          );
          $reqPaiements->execute(array_merge([$idStagiaire], $listeMois));
          $paiementsParMois = [];
          foreach ($reqPaiements->fetchAll() as $lignePmt) {
              $paiementsParMois[$lignePmt['mois_ref']] = $lignePmt;
          }

          // Construire les lignes de synthèse par mois avec totaux cumulatifs
          $lignesMois        = [];
          $totalMoisDu       = 0.0;
          $totalMoisPaye     = 0.0;
          $totalMoisRestant  = 0.0;
          $derniereDatePaie  = null;

          foreach ($listeMois as $refMois) {
              $ligneExistante = $paiementsParMois[$refMois] ?? null;
              $remiseLigne    = $ligneExistante ? max(0.0, (float)($ligneExistante['remise'] ?? 0)) : $remiseMensDef;
              $montantDu      = $ligneExistante
                  ? max(0.0, (float)$ligneExistante['montant_total'] - $remiseLigne)
                  : max(0.0, $tarif - $remiseMensDef);
              $montantPaye    = $ligneExistante ? (float)$ligneExistante['montant_paye']    : 0.0;
              $montantRestant = $ligneExistante ? (float)$ligneExistante['montant_restant'] : $montantDu;
              $statutMois     = $ligneExistante ? (string)$ligneExistante['statut_paiement'] : '';
              $datePaie       = $ligneExistante ? $ligneExistante['date_paiement'] : null;
              if ($datePaie) $derniereDatePaie = $datePaie;

              $totalMoisDu      += $montantDu;
              $totalMoisPaye    += $montantPaye;
              $totalMoisRestant += $montantRestant;
              $lignesMois[] = [
                  'mois'          => $refMois,
                  'du'            => $montantDu,
                  'remise'        => $remiseLigne,
                  'paye'          => $montantPaye,
                  'restant'       => $montantRestant,
                  'statut'        => $statutMois,
                  'date_paiement' => $datePaie,
              ];
          }

          echo json_encode([
              'success'       => true,
              'nom'           => trim((string)$infoStag['nom'] . ' ' . (string)$infoStag['prenom']),
              'num_inscri'    => (string)$infoStag['num_inscri'],
              'annee'         => $anneeScol,
              'tarif'         => $tarif,
              'rows'          => $lignesMois,
              'total_du'      => $totalMoisDu,
              'total_paye'    => $totalMoisPaye,
              'total_rest'    => $totalMoisRestant,
              'last_pay_date' => $derniereDatePaie,
          ]);
          exit;
      }

      echo json_encode(['success' => false, 'error' => 'Action inconnue.']); exit;
  }


  // ============================================================
  //  SECTION 2 : Paramètres de filtrage (GET)
  // ============================================================

  $anneeSelectionnee  = trim((string)($_GET['annee']      ?? ''));
  $idFiliereSelecte   = (int)($_GET['id_filiere'] ?? 0);
  $niveauSelectionne  = trim((string)($_GET['niveau']     ?? ''));
  $idClasseSelecte    = (int)($_GET['id_classe']  ?? 0);
  $moisSelectionne    = trim((string)($_GET['mois']       ?? date('Y-m')));
  if (!preg_match('/^\d{4}-\d{2}$/', $moisSelectionne)) $moisSelectionne = date('Y-m');


  // ============================================================
  //  SECTION 3 : Données en cascade filière → niveau → classe
  // ============================================================

  // Années scolaires disponibles
  $toutesAnnees = $pdo->query(
      "SELECT DISTINCT annee_scolaire FROM classes
        WHERE annee_scolaire REGEXP '^[0-9]{4}/[0-9]{4}$'
        ORDER BY annee_scolaire DESC"
  )->fetchAll(PDO::FETCH_COLUMN);
  if ($anneeSelectionnee === '') {
      $anneeSelectionnee = $_SESSION['global_annee_scolaire'] ?? ($toutesAnnees[0] ?? '');
  }

  // Filières disponibles
  $toutesFilières = $pdo->query(
      "SELECT DISTINCT f.id_filiere, f.nom_filiere
         FROM filieres f
        INNER JOIN classes c ON c.id_filiere = f.id_filiere
        ORDER BY f.nom_filiere"
  )->fetchAll();
  if ($idFiliereSelecte === 0 && !empty($toutesFilières)) {
      $idFiliereSelecte = (int)$toutesFilières[0]['id_filiere'];
  }

  // Niveaux pour la filière + année sélectionnées
  $tousNiveaux = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '') {
      $reqNiveaux = $pdo->prepare(
          "SELECT DISTINCT niveau FROM classes WHERE id_filiere = ? AND annee_scolaire = ? ORDER BY niveau"
      );
      $reqNiveaux->execute([$idFiliereSelecte, $anneeSelectionnee]);
      $tousNiveaux = $reqNiveaux->fetchAll(PDO::FETCH_COLUMN);
      if (!empty($tousNiveaux) && !in_array($niveauSelectionne, $tousNiveaux, true)) {
          $niveauSelectionne = $tousNiveaux[0];
      }
  }

  // Classes pour filière + année + niveau
  $toutesLesClasses = [];
  if ($idFiliereSelecte > 0 && $anneeSelectionnee !== '' && $niveauSelectionne !== '') {
      $reqClasses = $pdo->prepare(
          "SELECT id_classe, nom_classe FROM classes
            WHERE id_filiere = ? AND annee_scolaire = ? AND niveau = ?
            ORDER BY nom_classe"
      );
      $reqClasses->execute([$idFiliereSelecte, $anneeSelectionnee, $niveauSelectionne]);
      $toutesLesClasses = $reqClasses->fetchAll();
      $idsClassesValides = array_map('intval', array_column($toutesLesClasses, 'id_classe'));
      if (!empty($toutesLesClasses) && !in_array($idClasseSelecte, $idsClassesValides, true)) {
          $idClasseSelecte = (int)$toutesLesClasses[0]['id_classe'];
      }
  }


  // ============================================================
  //  SECTION 4 : Chargement des stagiaires et de leurs paiements du mois
  // ============================================================

  $stagiaires  = [];
  $infoClasse  = null;
  $tarifMensuel = 700.0;   // tarif par défaut avant de connaître la filière

  if ($idClasseSelecte > 0) {
      // Informations de la classe sélectionnée
      $reqClasse = $pdo->prepare(
          "SELECT c.nom_classe, f.nom_filiere, c.annee_scolaire, c.niveau, c.id_filiere
             FROM classes c
             JOIN filieres f ON f.id_filiere = c.id_filiere
            WHERE c.id_classe = ?"
      );
      $reqClasse->execute([$idClasseSelecte]);
      $infoClasse = $reqClasse->fetch();
      if ($infoClasse) {
          $tarifMensuel = getTarif((int)$infoClasse['id_filiere'], $tarifsDefaut);
      }

      // Stagiaires avec leurs paiements du mois sélectionné (LEFT JOIN pour inclure les non-payeurs)
      $reqStagiaires = $pdo->prepare("
          SELECT s.id_stagiaire, s.num_inscri, s.nom, s.prenom,
                 COALESCE(s.remise_mensuelle, 0) AS remise_mensuelle,
                 m.montant_total, m.remise, m.montant_paye,
                 m.montant_restant, m.statut_paiement, m.est_paye, m.date_paiement
            FROM stagiaires s
            LEFT JOIN mensualites m
              ON m.id_stagiaire = s.id_stagiaire AND m.mois_ref = ?
           WHERE s.id_classe = ?
           ORDER BY s.nom, s.prenom
      ");
      $reqStagiaires->execute([$moisSelectionne, $idClasseSelecte]);
      $stagiaires = $reqStagiaires->fetchAll();
  }


  // ============================================================
  //  SECTION 5 : Statistiques récapitulatives (totaux + compteurs)
  // ============================================================

  $totalMontantDu      = 0.0;
  $totalMontantPaye    = 0.0;
  $totalMontantRestant = 0.0;
  $nbPayes   = 0;
  $nbPartiels = 0;
  $nbImpayes = 0;

  foreach ($stagiaires as $stagiaire) {
      $remiseLigne    = $stagiaire['remise'] !== null
          ? max(0.0, (float)$stagiaire['remise'])
          : max(0.0, (float)($stagiaire['remise_mensuelle'] ?? 0));
      $montantEffectif = $stagiaire['montant_total'] !== null
          ? max(0.0, (float)$stagiaire['montant_total'] - $remiseLigne)
          : max(0.0, $tarifMensuel - (float)($stagiaire['remise_mensuelle'] ?? 0));
      $montantPaye    = $stagiaire['montant_paye']    !== null ? (float)$stagiaire['montant_paye']    : 0.0;
      $montantRestant = $stagiaire['montant_restant'] !== null ? (float)$stagiaire['montant_restant'] : $montantEffectif;
      $statutLigne    = (string)($stagiaire['statut_paiement'] ?? '');
      $estPaye        = (int)($stagiaire['est_paye'] ?? 0) === 1 || $statutLigne === 'payé';
      $estPartiel     = $statutLigne === 'partiel';

      $totalMontantDu      += $montantEffectif;
      $totalMontantPaye    += $montantPaye;
      $totalMontantRestant += $montantRestant;

      if ($estPaye)         $nbPayes++;
      elseif ($estPartiel)  $nbPartiels++;
      else                  $nbImpayes++;
  }

  // Liste des 12 derniers mois pour le filtre Mois
  $optionsMois = [];
  for ($i = 11; $i >= 0; $i--) {
      $optionsMois[] = date('Y-m', strtotime("-$i months"));
  }

  require __DIR__ . '/includes/header.php';
  ?>

?>

<style>
.cot-filter-card{background:linear-gradient(135deg,#1c1c20,#18181b);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.25);}
.cot-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;align-items:end;}
.cot-filter-grid label{display:flex;flex-direction:column;gap:0.35rem;font-size:0.78rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.cot-filter-grid select,.cot-filter-grid input[type="month"]{background:#09090b;border:1px solid rgba(255,255,255,0.12);color:#e4e4e7;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.9rem;width:100%;color-scheme:dark;}
.cot-filter-grid select:disabled{opacity:0.4;cursor:not-allowed;}
.cot-filter-grid select:focus,.cot-filter-grid input:focus{outline:none;border-color:rgba(168,85,247,0.5);box-shadow:0 0 0 2px rgba(168,85,247,0.15);}
.cot-stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;}
.cot-stat-card{background:linear-gradient(135deg,#1c1c20,#18181b);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1rem 1.25rem;text-align:center;transition:border-color .2s,box-shadow .2s;}.cot-stat-card:hover{border-color:rgba(168,85,247,.25);box-shadow:0 4px 16px rgba(168,85,247,.1);}
.cot-stat-val{font-size:1.6rem;font-weight:800;line-height:1.1;}
.cot-stat-lbl{font-size:0.72rem;color:#71717a;margin-top:0.35rem;text-transform:uppercase;letter-spacing:.05em;}
.cot-table-wrap{background:#18181b;border:1px solid rgba(255,255,255,0.08);border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.2);}
.cot-table-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.cot-table{width:100%;border-collapse:collapse;}
.cot-table th{padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#71717a;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);}
.cot-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:.88rem;color:#e4e4e7;vertical-align:middle;}
.cot-table tbody tr:hover td{background:rgba(168,85,247,0.06);}
.cot-table .cb-col{width:40px;text-align:center;}
.cot-table input[type="checkbox"]{accent-color:#a855f7;width:16px;height:16px;cursor:pointer;}
.badge-cot{display:inline-flex;align-items:center;gap:4px;padding:3px 12px;border-radius:20px;font-size:.73rem;font-weight:700;white-space:nowrap;letter-spacing:.02em;}
.badge-cot.paye{background:rgba(52,211,153,.13);color:#34d399;border:1px solid rgba(52,211,153,.3);}
.badge-cot.partiel{background:rgba(251,146,60,.13);color:#fb923c;border:1px solid rgba(251,146,60,.3);}
.badge-cot.impaye{background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.3);}
.badge-cot.aucun{background:rgba(113,113,122,.13);color:#a1a1aa;border:1px solid rgba(113,113,122,.2);}
.btn-cot{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:all .18s cubic-bezier(.16,1,.3,1);}
.btn-cot.primary{background:linear-gradient(135deg,#9333ea,#a855f7);color:#fff;box-shadow:0 4px 14px rgba(168,85,247,.38);transition:all .2s;}.btn-cot.primary:hover{background:linear-gradient(135deg,#a855f7,#9333ea);box-shadow:0 6px 20px rgba(168,85,247,.52);transform:translateY(-1px);}
.btn-cot.ghost{background:linear-gradient(135deg,rgba(168,85,247,.14),rgba(139,92,246,.08));color:#c084fc;border:1px solid rgba(168,85,247,.32);position:relative;overflow:hidden;}.btn-cot.ghost:hover{background:linear-gradient(135deg,rgba(168,85,247,.28),rgba(139,92,246,.18));border-color:rgba(168,85,247,.6);box-shadow:0 0 14px rgba(168,85,247,.28),0 3px 10px rgba(0,0,0,.25);transform:translateY(-1px);color:#d8b4fe;}
.btn-cot.success{background:linear-gradient(135deg,rgba(52,211,153,.15),rgba(16,185,129,.1));color:#34d399;border:1px solid rgba(52,211,153,.32);}.btn-cot.success:hover{background:linear-gradient(135deg,rgba(52,211,153,.28),rgba(16,185,129,.18));box-shadow:0 0 12px rgba(52,211,153,.22);transform:translateY(-1px);}
.btn-cot.danger{background:rgba(239,68,68,.12);color:#fca5a5;border:1px solid rgba(239,68,68,.2);}.btn-cot.danger:hover{background:rgba(239,68,68,.25);}
.btn-cot.sm{padding:3px 9px;font-size:.75rem;}
.bulk-bar{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:14px;padding:.85rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:900;opacity:0;transition:all .25s;pointer-events:none;flex-wrap:wrap;min-width:460px;}
.bulk-bar.visible{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:all;}
.bulk-count{font-size:.85rem;font-weight:700;color:#c084fc;white-space:nowrap;}
.bulk-sep{width:1px;height:24px;background:rgba(255,255,255,.1);}
.empty-state{text-align:center;padding:3.5rem 2rem;color:#52525b;}
.empty-state i{font-size:2.5rem;margin-bottom:1rem;display:block;color:#3f3f46;}
.cot-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:9999;display:none;align-items:center;justify-content:center;}
.cot-modal-card{background:#18181b;border:1px solid rgba(168,85,247,.18);border-radius:20px;padding:0;width:min(480px,95vw);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.85),0 0 0 1px rgba(168,85,247,.1);animation:cotModalIn .28s cubic-bezier(.16,1,.3,1);}@keyframes cotModalIn{from{transform:translateY(18px) scale(.98);opacity:0;}to{transform:translateY(0) scale(1);opacity:1;}}
.cot-modal-header{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;border-bottom:1px solid rgba(168,85,247,.18);background:linear-gradient(135deg,rgba(168,85,247,.1),rgba(139,92,246,.05));position:relative;}.cot-modal-header::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#7c3aed,#a855f7,#c084fc,#a855f7,#7c3aed);border-radius:20px 20px 0 0;}
.cot-modal-header h3{margin:0;font-size:1.05rem;font-weight:700;color:#f4f4f5;}
.cot-modal-body{padding:1.5rem;display:flex;flex-direction:column;gap:1rem;}
.cot-modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:flex-end;gap:.75rem;}
.cot-info-row{display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.04);border-radius:8px;padding:.6rem .9rem;font-size:.87rem;}
.cot-info-row .label{color:#71717a;}
.cot-info-row .val{font-weight:700;color:#e4e4e7;}
.cot-form-group{display:flex;flex-direction:column;gap:.3rem;}
.cot-form-group label{font-size:.76rem;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.05em;}
.cot-form-group input,.cot-form-group select{background:#09090b;border:1px solid rgba(255,255,255,.1);color:#e4e4e7;border-radius:8px;padding:.5rem .75rem;font-size:.9rem;width:100%;box-sizing:border-box;}
.cot-form-group input:focus,.cot-form-group select:focus{outline:none;border-color:rgba(168,85,247,.5);}
.ajout-banner{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:.75rem 1rem;font-size:.84rem;display:flex;flex-direction:column;gap:.4rem;}
.gds-toast{position:fixed;top:1.25rem;right:1.25rem;z-index:99998;border-radius:12px;padding:.85rem 1.35rem;font-weight:600;font-size:.88rem;box-shadow:0 8px 32px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04);border:1px solid;max-width:380px;line-height:1.45;animation:toastIn .22s cubic-bezier(.16,1,.3,1);}
@keyframes toastIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.gds-toast.success{background:#18181b;border-color:rgba(34,197,94,.4);color:#86efac;}
.gds-toast.error{background:#18181b;border-color:rgba(239,68,68,.4);color:#fca5a5;}
.gds-toast.info{background:#18181b;border-color:rgba(168,85,247,.4);color:#c084fc;}
.gds-confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:99999;display:none;align-items:center;justify-content:center;}
.gds-confirm-card{background:#1c1c1f;border:1px solid rgba(168,85,247,.35);border-radius:16px;padding:2rem 2rem 1.5rem;width:min(380px,92vw);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.6);}
.gds-confirm-icon{font-size:2rem;margin-bottom:.75rem;}
.gds-confirm-msg{color:#e4e4e7;font-size:.95rem;margin:0 0 1.5rem;line-height:1.55;}
.gds-confirm-btns{display:flex;gap:.75rem;justify-content:center;}
.page-header-cot{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.page-header-cot h1{font-size:1.6rem;font-weight:800;color:#f4f4f5;margin:0;}
.page-header-cot p{margin:.3rem 0 0;font-size:.88rem;color:#71717a;}
.amount-green{color:#34d399;font-weight:700;}
.amount-red{color:#f87171;font-weight:700;}
.amount-gray{color:#71717a;}

/* ── Student payment drawer ── */
.pmt-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10000;display:none;backdrop-filter:blur(2px);}
.pmt-overlay.open{display:block;}
.pmt-drawer{position:fixed;top:0;right:0;height:100%;width:min(480px,100vw);background:#111113;border-left:1px solid rgba(255,255,255,.08);z-index:10001;transform:translateX(100%);transition:transform .32s cubic-bezier(.16,1,.3,1);display:flex;flex-direction:column;overflow:hidden;box-shadow:-12px 0 48px rgba(0,0,0,.6);}
.pmt-drawer.open{transform:translateX(0);}

/* head */
.pmt-drawer-head{display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.4rem;border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0;background:rgba(168,85,247,.06);}
.pmt-drawer-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff;flex-shrink:0;margin-right:.85rem;}
.pmt-drawer-title{font-size:1rem;font-weight:700;color:#f4f4f5;margin:0 0 .15rem;line-height:1.2;}
.pmt-drawer-sub{font-size:.74rem;color:#71717a;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;}
.pmt-drawer-sub .pip{width:3px;height:3px;border-radius:50%;background:#3f3f46;display:inline-block;}

/* body */
.pmt-drawer-body{flex:1;overflow-y:auto;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.55rem;}
.pmt-drawer-body::-webkit-scrollbar{width:4px;}.pmt-drawer-body::-webkit-scrollbar-track{background:transparent;}.pmt-drawer-body::-webkit-scrollbar-thumb{background:#3f3f46;border-radius:4px;}

/* month cards */
.pmt-card{background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:.85rem 1rem;transition:border-color .15s;}
.pmt-card:hover{border-color:rgba(168,85,247,.3);}
.pmt-card.is-current{border-color:rgba(168,85,247,.4);background:rgba(168,85,247,.06);}
.pmt-card.is-paye{border-color:rgba(52,211,153,.2);}
.pmt-card.is-impaye{border-color:rgba(248,113,113,.2);}
.pmt-card.is-partiel{border-color:rgba(251,146,60,.2);}
.pmt-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;}
.pmt-card-month{font-size:.88rem;font-weight:700;color:#e4e4e7;display:flex;align-items:center;gap:.4rem;}
.pmt-card-right{display:flex;align-items:center;gap:.5rem;}
.pmt-badge{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:20px;font-size:.69rem;font-weight:700;white-space:nowrap;}
.pmt-badge.paye{background:rgba(52,211,153,.13);color:#34d399;border:1px solid rgba(52,211,153,.25);}
.pmt-badge.partiel{background:rgba(251,146,60,.13);color:#fb923c;border:1px solid rgba(251,146,60,.25);}
.pmt-badge.impaye{background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.25);}
.pmt-badge.aucun{background:rgba(113,113,122,.1);color:#71717a;border:1px solid rgba(113,113,122,.18);}
.pmt-edit-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:7px;font-size:.72rem;font-weight:600;border:1px solid rgba(168,85,247,.3);background:rgba(168,85,247,.08);color:#c084fc;cursor:pointer;transition:all .15s;white-space:nowrap;}
.pmt-edit-btn:hover{background:rgba(168,85,247,.2);border-color:rgba(168,85,247,.5);}

/* progress bar */
.pmt-progress-wrap{position:relative;height:5px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;margin-bottom:.65rem;}
.pmt-progress-fill{position:absolute;left:0;top:0;height:100%;border-radius:99px;transition:width .5s ease;}

/* amounts row */
.pmt-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;}
.pmt-amount-item{display:flex;flex-direction:column;gap:.1rem;}
.pmt-amount-lbl{font-size:.64rem;text-transform:uppercase;letter-spacing:.06em;color:#52525b;font-weight:600;}
.pmt-amount-val{font-size:.82rem;font-weight:700;font-variant-numeric:tabular-nums;}
.pmt-date-line{font-size:.71rem;color:#52525b;margin-top:.5rem;display:flex;align-items:center;gap:.3rem;}

/* footer */
.pmt-drawer-foot{flex-shrink:0;border-top:1px solid rgba(255,255,255,.07);padding:1rem 1.1rem;background:#111113;}
.pmt-summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.7rem;}
.pmt-sum-card{background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:.65rem .75rem;text-align:center;}
.pmt-sum-val{font-size:.95rem;font-weight:800;line-height:1.15;margin-bottom:.15rem;font-variant-numeric:tabular-nums;}
.pmt-sum-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:#52525b;font-weight:600;}
.pmt-last-pay{font-size:.76rem;color:#71717a;text-align:center;display:flex;align-items:center;justify-content:center;gap:.35rem;}

/* name trigger */
.btn-stag-name{background:none;border:none;color:#e4e4e7;font-weight:600;cursor:pointer;padding:0;text-align:left;text-decoration:underline;text-underline-offset:2px;text-decoration-color:rgba(168,85,247,.4);transition:color .15s;}
.btn-stag-name:hover{color:#c084fc;}
.btn-eye{background:none;border:none;color:#52525b;cursor:pointer;padding:0 0 0 6px;font-size:.82rem;transition:color .15s;vertical-align:middle;}
.btn-eye:hover{color:#a855f7;}

/* skeleton */
.pmt-skeleton{display:flex;flex-direction:column;gap:.55rem;}
.pmt-skel-row{height:96px;background:rgba(255,255,255,.04);border-radius:12px;animation:skel-pulse 1.3s ease-in-out infinite;}
@keyframes skel-pulse{0%,100%{opacity:.4;}50%{opacity:.8;}}
</style>

<div style="max-width:1200px;margin:0 auto;padding:1.5rem;">

  <!-- Page header -->
  <div class="page-header-cot">
    <div>
      <h1><i class="fa-solid fa-money-bill-transfer" style="color:#a855f7;margin-right:.5rem;"></i>Gestion des Paiements</h1>
      <p>Système centralisé de gestion des paiements par classe</p>
    </div>
  </div>

  <!-- Filter card -->
  <div class="cot-filter-card">
    <form method="get" id="filter-form">
      <div class="cot-filter-grid">
        <label>
          Année scolaire
          <select name="annee" onchange="this.form.submit()">
            <?php foreach ($toutesAnnees as $a): ?>
              <option value="<?= h($a) ?>" <?= $anneeSelectionnee === $a ? 'selected' : '' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Filière
          <select name="id_filiere" onchange="this.form.submit()">
            <option value="0">— Choisir —</option>
            <?php foreach ($toutesFilières as $filiere): ?>
              <option value="<?= (int)$filiere['id_filiere'] ?>" <?= $idFiliereSelecte === (int)$filiere['id_filiere'] ? 'selected' : '' ?>><?= h($filiere['nom_filiere']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Niveau
          <select name="niveau" onchange="this.form.submit()" <?= empty($tousNiveaux) ? 'disabled' : '' ?>>
            <?php if (empty($tousNiveaux)): ?><option value="">— Aucun —</option><?php endif; ?>
            <?php foreach ($tousNiveaux as $niveau): ?>
              <option value="<?= h($niveau) ?>" <?= $niveauSelectionne === $niveau ? 'selected' : '' ?>><?= h($niveau) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Classe
          <select name="id_classe" onchange="this.form.submit()" <?= empty($toutesLesClasses) ? 'disabled' : '' ?>>
            <?php if (empty($toutesLesClasses)): ?><option value="0">— Aucune —</option><?php endif; ?>
            <?php foreach ($toutesLesClasses as $classe): ?>
              <option value="<?= (int)$classe['id_classe'] ?>" <?= $idClasseSelecte === (int)$classe['id_classe'] ? 'selected' : '' ?>><?= h($classe['nom_classe']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Mois
          <input type="month" name="mois" value="<?= h($moisSelectionne) ?>" onchange="this.form.submit()">
        </label>
      </div>
    </form>
  </div>

  <?php if ($idClasseSelecte > 0 && $infoClasse): ?>
  <!-- Summary stats + print button row -->
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div class="cot-stats-row" style="grid-template-columns:repeat(2,minmax(150px,220px));margin-bottom:0;flex:0 0 auto;">
      <div class="cot-stat-card">
        <div class="cot-stat-val" style="color:#e4e4e7;"><?= count($stagiaires) ?></div>
        <div class="cot-stat-lbl">Total stagiaires</div>
      </div>
      <div class="cot-stat-card" style="border-color:rgba(248,113,113,.25);">
        <div class="cot-stat-val" style="color:#f87171;"><?= $nbImpayes ?></div>
        <div class="cot-stat-lbl">Impayés</div>
      </div>
    </div>
    <?php if ($nbImpayes > 0): ?>
    <?php
      $urlImpressionImpayes = 'print_liste_impayes.php?' . http_build_query([
        'id_filiere'    => $idFiliereSelecte,
        'id_classe'     => $idClasseSelecte,
        'annee_scolaire'=> $anneeSelectionnee,
        'niveau'        => $niveauSelectionne,
        'mois'          => $moisSelectionne,
        'impaye'        => '1',
        'auto'          => '1',
      ]);
    ?>
    <a href="<?= h($urlImpressionImpayes) ?>" target="_blank"
       style="display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1.2rem;background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.3);border-radius:9px;font-size:.85rem;font-weight:700;text-decoration:none;white-space:nowrap;transition:background .15s;"
       onmouseover="this.style.background='rgba(248,113,113,.22)'" onmouseout="this.style.background='rgba(248,113,113,.13)'">
        <i class="fa-solid fa-print"></i> Imprimer liste des impayés
    </a>
    <?php endif; ?>
  </div>

  <!-- Table -->
  <div class="cot-table-wrap">
    <div class="cot-table-header">
      <div>
        <strong style="color:#f4f4f5;"><?= h($infoClasse['nom_classe']) ?></strong>
        <span style="color:#71717a;font-size:.85rem;margin-left:.75rem;"><?= h($infoClasse['nom_filiere']) ?> · <?= h($infoClasse['niveau']) ?> · <?= h($infoClasse['annee_scolaire']) ?></span>
        <span style="background:rgba(168,85,247,.15);color:#c084fc;border:1px solid rgba(168,85,247,.3);border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;margin-left:.75rem;">
          <?= h(date('M Y', strtotime($moisSelectionne . '-01'))) ?> · Tarif <?= number_format($tarifMensuel, 0, ',', ' ') ?> MAD
        </span>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;">
        <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;color:#a1a1aa;cursor:pointer;">
          <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" style="accent-color:#a855f7;width:15px;height:15px;">
          Tout sélectionner
        </label>
      </div>
    </div>

    <?php if (empty($stagiaires)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-users-slash"></i>
        <p>Aucun stagiaire dans cette classe.</p>
      </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="cot-table">
      <thead>
        <tr>
          <th class="cb-col"><i class="fa-solid fa-check-square" style="color:#52525b;"></i></th>
          <th>Stagiaire</th>
          <th style="text-align:right;">Montant dû</th>
          <th style="text-align:right;">Payé</th>
          <th style="text-align:right;">Restant</th>
          <th style="text-align:center;">Statut</th>
          <th style="text-align:center;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($stagiaires as $stagiaire):
        $statutLigne = (string)($stagiaire['statut_paiement'] ?? '');
        $estPaye        = (int)($stagiaire['est_paye'] ?? 0) === 1 || $statutLigne === 'payé';
        $estPartiel     = $statutLigne === 'partiel';
        $estImpaye      = !$estPaye && !$estPartiel;
        $remiseMensuelleStag = max(0.0, (float)($stagiaire['remise_mensuelle'] ?? 0));
        $montantRemise       = $stagiaire['remise'] !== null ? max(0.0, (float)$stagiaire['remise']) : $remiseMensuelleStag;
        $montantTotal        = $stagiaire['montant_total']   !== null ? (float)$stagiaire['montant_total']   : $tarifMensuel;
        $montantEffectif     = max(0.0, $montantTotal - $montantRemise);
        $montantPayeStag     = $stagiaire['montant_paye']    !== null ? (float)$stagiaire['montant_paye']    : 0.0;
        $montantRestantStag  = $stagiaire['montant_restant'] !== null ? (float)$stagiaire['montant_restant'] : $montantEffectif;
        $aPaiement           = $stagiaire['statut_paiement'] !== null;
        if (!$aPaiement) { $montantPayeStag = 0; $montantRestantStag = $montantEffectif; }
        $classeStatut        = $estPaye ? 'paye' : ($estPartiel ? 'partiel' : ($aPaiement ? 'impaye' : 'aucun'));
        $libelleStatut       = $estPaye ? 'Payé' : ($estPartiel ? 'Partiel' : ($aPaiement ? 'Impayé' : 'Aucun'));
        $donneesLigneJson = json_encode([
          'id_stagiaire'    => (int)$stagiaire['id_stagiaire'],
          'nom'             => trim($stagiaire['nom'] . ' ' . $stagiaire['prenom']),
          'mois_ref'        => $moisSelectionne,
          'tarif'           => $tarifMensuel,
          'remise'          => $montantRemise,
          'remise_mensuelle'=> $remiseMensuelleStag,
          'montant_paye'    => $montantPayeStag,
          'montant_restant' => $montantRestantStag,
          'has_record'      => $aPaiement,
          'statut'          => $statutLigne,
        ]);
      ?>
        <tr data-sid="<?= (int)$stagiaire['id_stagiaire'] ?>" id="row-<?= (int)$stagiaire['id_stagiaire'] ?>"<?= $estImpaye ? ' style="background:rgba(255,60,60,.07);"' : '' ?>>
          <td class="cb-col">
            <input type="checkbox" class="row-cb" value="<?= (int)$stagiaire['id_stagiaire'] ?>" onchange="updateBulkBar()">
          </td>
          <td>
            <button type="button" class="btn-stag-name" onclick="openPmtDrawer(<?= (int)$stagiaire['id_stagiaire'] ?>)"><?= h(trim($stagiaire['nom'].' '.$stagiaire['prenom'])) ?></button>
            <button type="button" class="btn-eye" onclick="openPmtDrawer(<?= (int)$stagiaire['id_stagiaire'] ?>)" title="Voir historique annuel"><i class="fa-solid fa-eye"></i></button>
            <div style="font-size:.76rem;color:#71717a;"><?= h((string)($stagiaire['num_inscri'] ?? '')) ?></div>
          </td>
          <td style="text-align:right;" class="col-du">
            <?= number_format($montantEffectif, 2) ?> MAD
            <?php if ($montantRemise > 0): ?><br><span style="font-size:.7rem;color:#a855f7;font-weight:600;">-<?= number_format($montantRemise, 2) ?> réduc.</span><?php endif; ?>
          </td>
          <td style="text-align:right;" class="col-paye <?= $montantPayeStag > 0 ? 'amount-green' : 'amount-gray' ?>"><?= number_format($montantPayeStag, 2) ?> MAD</td>
          <td style="text-align:right;" class="col-restant <?= $montantRestantStag > 0 ? 'amount-red' : 'amount-gray' ?>"><?= number_format($montantRestantStag, 2) ?> MAD</td>
          <td style="text-align:center;" class="col-statut">
            <span class="badge-cot <?= $classeStatut ?>"><?= $libelleStatut ?></span>
          </td>
          <td style="text-align:center;">
            <div style="display:flex;gap:.4rem;justify-content:center;flex-wrap:wrap;">
              <button type="button" class="btn-cot ghost btn-cot sm" onclick='openPayModal(<?= htmlspecialchars($donneesLigneJson, ENT_QUOTES) ?>)'>
                <i class="fa-solid fa-money-bill-wave"></i> Paiement
              </button>
              <?php if ($aPaiement): ?>
              <a href="print_recu_paiement.php?id=<?= (int)$stagiaire['id_stagiaire'] ?>&mois=<?= h($moisSelectionne) ?>" target="_blank"
                 class="btn-cot btn-cot sm"
                 style="text-decoration:none;background:rgba(250,204,21,.1);color:#fde047;border:1px solid rgba(250,204,21,.25);"
                 onmouseover="this.style.background='rgba(250,204,21,.2)'" onmouseout="this.style.background='rgba(250,204,21,.1)'">
                <i class="fa-solid fa-receipt"></i> Reçu
              </a>
              <?php endif; ?>
              <a href="print_etat_paiements_annuel.php?id_stagiaire=<?= (int)$stagiaire['id_stagiaire'] ?>&auto=1" target="_blank"
                 class="btn-cot btn-cot sm"
                 style="text-decoration:none;background:rgba(59,130,246,.1);color:#93c5fd;border:1px solid rgba(59,130,246,.25);"
                 onmouseover="this.style.background='rgba(59,130,246,.2)'" onmouseout="this.style.background='rgba(59,130,246,.1)'"
                 title="État annuel des paiements">
                <i class="fa-solid fa-calendar-check"></i> État annuel
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03);">
          <td colspan="2" style="font-weight:700;color:#e4e4e7;padding:.65rem 1rem;">TOTAUX</td>
          <td style="text-align:right;color:#a1a1aa;font-weight:700;padding:.65rem 1rem;"><?= number_format($totalMontantDu, 2) ?> MAD</td>
          <td style="text-align:right;font-weight:700;color:#34d399;padding:.65rem 1rem;"><?= number_format($totalMontantPaye, 2) ?> MAD</td>
          <td style="text-align:right;font-weight:700;color:<?= $totalMontantRestant > 0 ? '#f87171' : '#71717a' ?>;padding:.65rem 1rem;"><?= number_format($totalMontantRestant, 2) ?> MAD</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <?php elseif ($idClasseSelecte === 0 && $idFiliereSelecte > 0): ?>
  <div class="empty-state" style="background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:14px;">
    <i class="fa-solid fa-arrow-pointer"></i>
    <p>Sélectionnez un niveau et une classe pour afficher les cotisations.</p>
  </div>
  <?php elseif ($idFiliereSelecte === 0): ?>
  <div class="empty-state" style="background:#18181b;border:1px solid rgba(255,255,255,.07);border-radius:14px;">
    <i class="fa-solid fa-filter"></i>
    <p>Sélectionnez une filière pour commencer.</p>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<!-- ── BULK ACTION BAR ──────────────────────────────────────────────────── -->
<div id="bulk-bar" class="bulk-bar">
  <span class="bulk-count"><i class="fa-solid fa-users"></i> <span id="bulk-count-label">0</span> sélectionné(s)</span>
  <div class="bulk-sep"></div>
  <?php if (gds_is_directeur()): ?>
  <button type="button" class="btn-cot success" onclick="doBulkMarkPaid()">
    <i class="fa-solid fa-circle-check"></i> Marquer payé
  </button>
  <?php endif; ?>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <input type="number" id="bulk-montant" placeholder="Montant (MAD)" min="0" step="0.01"
           style="background:#09090b;border:1px solid rgba(255,255,255,.12);color:#e4e4e7;border-radius:7px;padding:5px 10px;font-size:.82rem;width:140px;">
    <button type="button" class="btn-cot primary" onclick="doBulkPartial()">
      <i class="fa-solid fa-coins"></i> Paiement partiel
    </button>
  </div>
</div>

<!-- ── SINGLE PAYMENT MODAL ─────────────────────────────────────────────── -->
<div id="modal-pay" class="cot-modal-overlay">
  <div class="cot-modal-card">
    <div class="cot-modal-header">
      <h3><i class="fa-solid fa-money-bill-wave" style="color:#a855f7;margin-right:.4rem;"></i><span id="pay-modal-title">Enregistrer un paiement</span></h3>
      <button type="button" onclick="closePayModal()" style="background:none;border:none;color:#71717a;cursor:pointer;font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cot-modal-body">
      <!-- Context info -->
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <div class="cot-info-row"><span class="label">Mois</span><span class="val" id="pay-info-mois">—</span></div>
        <div class="cot-info-row" id="pay-info-existant-row" style="display:none;">
          <span class="label">Déjà payé</span><span class="val amount-green" id="pay-info-paye">—</span>
        </div>
        <div class="cot-info-row" id="pay-info-restant-row" style="display:none;">
          <span class="label">Restant dû</span><span class="val amount-red" id="pay-info-restant">—</span>
        </div>
      </div>

      <!-- Mode: new record -->
      <div id="pay-section-new">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="cot-form-group">
            <label>Statut</label>
            <select id="pay-statut" onchange="payUpdateFields()">
              <option value="payé">✅ Payé (complet)</option>
              <option value="partiel">⚠️ Partiel</option>
              <option value="impayé">❌ Impayé</option>
            </select>
          </div>
          <div class="cot-form-group">
            <label>Date paiement</label>
            <input type="date" id="pay-date" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="cot-form-group">
          <label style="display:flex;align-items:center;gap:.4rem;">
            Réduction (MAD)
            <span style="font-size:.68rem;font-weight:400;color:#71717a;text-transform:none;letter-spacing:0;">(bourse, cas spécial)</span>
          </label>
          <input type="number" id="pay-remise" min="0" step="0.01" placeholder="0.00" oninput="payUpdateEffectif()" style="border-color:rgba(168,85,247,.3);">
          <div id="pay-effectif-info" style="display:none;margin-top:.3rem;font-size:.78rem;color:#c084fc;"></div>
        </div>
        <div class="cot-form-group" id="pay-montant-wrap">
          <label>Montant payé (MAD)</label>
          <input type="number" id="pay-montant-paye" min="0" step="0.01" placeholder="0.00" oninput="payCapMontant()">
        </div>
      </div>

      <!-- Mode: add to existing -->
      <div id="pay-section-ajout" style="display:none;">
        <div class="ajout-banner">
          <div style="color:#c7d2fe;font-weight:600;font-size:.84rem;"><i class="fa-solid fa-circle-info"></i> Ajout sur paiement existant</div>
          <div style="color:#a1a1aa;font-size:.82rem;">Un paiement existe déjà pour ce mois. Entrez le montant supplémentaire à ajouter.</div>
        </div>
        <div class="cot-form-group" style="margin-top:.5rem;">
          <label style="display:flex;align-items:center;gap:.4rem;">
            Réduction (MAD)
            <span style="font-size:.68rem;font-weight:400;color:#71717a;text-transform:none;letter-spacing:0;">(modifier si besoin)</span>
          </label>
          <input type="number" id="pay-remise-ajout" min="0" step="0.01" placeholder="0.00" oninput="payPreviewAjout()" style="border-color:rgba(168,85,247,.3);">
        </div>
        <div class="cot-form-group">
          <label>Nouveau versement (MAD)</label>
          <input type="number" id="pay-nouveau-versement" min="0" step="0.01" placeholder="0.00" oninput="payPreviewAjout()">
        </div>
        <div id="pay-ajout-preview" style="display:none;background:rgba(0,0,0,.2);border-radius:8px;padding:.6rem .9rem;font-size:.82rem;color:#a1a1aa;margin-top:.25rem;"></div>
        <div class="cot-form-group" style="margin-top:.5rem;">
          <label>Date paiement</label>
          <input type="date" id="pay-date-ajout" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
    </div>
    <div class="cot-modal-footer">
      <button type="button" class="btn-cot ghost" onclick="closePayModal()">Annuler</button>
      <button type="button" class="btn-cot primary" id="pay-save-btn" onclick="savePayment()" style="padding:.65rem 1.75rem;font-size:.9rem;border-radius:10px;letter-spacing:.02em;">
        <i class="fa-solid fa-floppy-disk"></i> Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- ── STUDENT PAYMENT DRAWER ─────────────────────────────────────────────── -->
<div id="pmt-overlay" class="pmt-overlay" onclick="closePmtDrawer()"></div>
<div id="pmt-drawer" class="pmt-drawer">
  <div class="pmt-drawer-head">
    <div style="display:flex;align-items:center;">
      <div class="pmt-drawer-avatar" id="pmt-drawer-avatar">?</div>
      <div>
        <div class="pmt-drawer-title" id="pmt-drawer-name">Chargement…</div>
        <div class="pmt-drawer-sub" id="pmt-drawer-sub"></div>
      </div>
    </div>
    <button type="button" onclick="closePmtDrawer()" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#71717a;cursor:pointer;font-size:.9rem;padding:.35rem .55rem;border-radius:8px;transition:all .15s;" onmouseover="this.style.color='#f4f4f5'" onmouseout="this.style.color='#71717a'"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="pmt-drawer-body" id="pmt-drawer-body">
    <div class="pmt-skeleton">
      <?php for ($i=0;$i<10;$i++): ?><div class="pmt-skel-row"></div><?php endfor; ?>
    </div>
  </div>
  <div class="pmt-drawer-foot" id="pmt-drawer-foot" style="display:none;">
    <div class="pmt-summary-grid">
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-du" style="color:#a1a1aa;">—</div>
        <div class="pmt-sum-lbl">Total Dû</div>
      </div>
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-paye" style="color:#34d399;">—</div>
        <div class="pmt-sum-lbl">Total Payé</div>
      </div>
      <div class="pmt-sum-card">
        <div class="pmt-sum-val" id="pmt-tot-rest">—</div>
        <div class="pmt-sum-lbl">Solde Restant</div>
      </div>
    </div>
    <div class="pmt-last-pay" id="pmt-last-pay"></div>
  </div>
</div>

<!-- ── CUSTOM CONFIRM ─────────────────────────────────────────────────────── -->
<div id="gds-confirm-overlay" class="gds-confirm-overlay">
  <div class="gds-confirm-card">
    <div class="gds-confirm-icon">⚠️</div>
    <p class="gds-confirm-msg" id="gds-confirm-msg">Confirmer l'action ?</p>
    <div class="gds-confirm-btns">
      <button class="btn-cot ghost" onclick="gdsConfirmResolve(false)"><i class="fa-solid fa-xmark"></i> Annuler</button>
      <button class="btn-cot primary" onclick="gdsConfirmResolve(true)"><i class="fa-solid fa-check"></i> Confirmer</button>
    </div>
  </div>
</div>

<script>
const GDS_CSRF         = <?= json_encode(csrf_token()) ?>;
const GDS_IS_DIRECTEUR = <?= json_encode(gds_is_directeur()) ?>;
const SEL_MOIS         = <?= json_encode($moisSelectionne) ?>;
const TARIF_CLASSE     = <?= json_encode($tarifMensuel) ?>;

// ── Checkbox helpers ─────────────────────────────────────────────────────
function getSelectedIds() {
  return [...document.querySelectorAll('.row-cb:checked')].map(cb => parseInt(cb.value));
}
function toggleSelectAll(masterCb) {
  document.querySelectorAll('.row-cb').forEach(cb => cb.checked = masterCb.checked);
  updateBulkBar();
}
function updateBulkBar() {
  const ids = getSelectedIds();
  const bar = document.getElementById('bulk-bar');
  document.getElementById('bulk-count-label').textContent = ids.length;
  bar.classList.toggle('visible', ids.length > 0);
  // sync master checkbox
  const all = document.querySelectorAll('.row-cb');
  const masterCb = document.getElementById('select-all');
  if (masterCb) masterCb.checked = all.length > 0 && ids.length === all.length;
}

// ── Payment modal ────────────────────────────────────────────────────────
let _payCurrentRow = null;

function openPayModal(rowData) {
  _payCurrentRow = rowData;
  const hasRecord = rowData.has_record;
  document.getElementById('pay-modal-title').textContent = 'Paiement — ' + rowData.nom;
  document.getElementById('pay-info-mois').textContent = formatMois(rowData.mois_ref);

  if (hasRecord) {
    // Ajout mode
    document.getElementById('pay-section-new').style.display  = 'none';
    document.getElementById('pay-section-ajout').style.display = '';
    document.getElementById('pay-info-existant-row').style.display = '';
    document.getElementById('pay-info-restant-row').style.display  = '';
    document.getElementById('pay-info-paye').textContent    = fmtAmt(rowData.montant_paye);
    document.getElementById('pay-info-restant').textContent = fmtAmt(rowData.montant_restant);
    const _defRemiseAjout = (rowData.remise || 0) > 0 ? (rowData.remise || 0) : (rowData.remise_mensuelle || 0);
    document.getElementById('pay-remise-ajout').value = _defRemiseAjout > 0 ? _defRemiseAjout : '';
    document.getElementById('pay-nouveau-versement').value = '';
    document.getElementById('pay-nouveau-versement').max = rowData.montant_restant;
    document.getElementById('pay-ajout-preview').style.display = 'none';
  } else {
    // New record mode
    document.getElementById('pay-section-new').style.display   = '';
    document.getElementById('pay-section-ajout').style.display = 'none';
    document.getElementById('pay-info-existant-row').style.display = 'none';
    document.getElementById('pay-info-restant-row').style.display  = 'none';
    const _defRemiseNew = (rowData.remise || 0) > 0 ? (rowData.remise || 0) : (rowData.remise_mensuelle || 0);
    document.getElementById('pay-remise').value = _defRemiseNew > 0 ? _defRemiseNew : '';
    document.getElementById('pay-effectif-info').style.display = 'none';
    document.getElementById('pay-statut').value = 'payé';
    payUpdateFields();
  }
  document.getElementById('modal-pay').style.display = 'flex';
}

function closePayModal() {
  document.getElementById('modal-pay').style.display = 'none';
  _payCurrentRow = null;
}

function payUpdateFields() {
  const statut = document.getElementById('pay-statut').value;
  const mWrap  = document.getElementById('pay-montant-wrap');
  if (statut === 'payé' || statut === 'impayé') {
    mWrap.style.display = 'none';
  } else {
    mWrap.style.display = '';
  }
}

function payGetEffectif() {
  const remise = parseFloat(document.getElementById('pay-remise')?.value || 0) || 0;
  return Math.max(0, TARIF_CLASSE - remise);
}

function payUpdateEffectif() {
  const effectif = payGetEffectif();
  const remise   = parseFloat(document.getElementById('pay-remise')?.value || 0) || 0;
  const infoEl   = document.getElementById('pay-effectif-info');
  if (remise > 0) {
    infoEl.style.display = '';
    infoEl.textContent = `Montant effectif après réduction : ${fmtAmt(effectif)}`;
  } else {
    infoEl.style.display = 'none';
  }
  payCapMontant();
}

function payCapMontant() {
  const inp = document.getElementById('pay-montant-paye');
  const effectif = payGetEffectif();
  if (parseFloat(inp.value) > effectif) inp.value = effectif;
}

function payPreviewAjout() {
  if (!_payCurrentRow) return;
  const remise    = parseFloat(document.getElementById('pay-remise-ajout')?.value || 0) || 0;
  const effectif  = Math.max(0, TARIF_CLASSE - remise);
  const v         = parseFloat(document.getElementById('pay-nouveau-versement').value) || 0;
  const newPaye   = Math.min(effectif, _payCurrentRow.montant_paye + v);
  const newRestant= Math.max(0, effectif - newPaye);
  const preview   = document.getElementById('pay-ajout-preview');
  if (v > 0 || remise !== (_payCurrentRow.remise || 0)) {
    preview.style.display = '';
    const newStatut = newRestant <= 0 ? '✅ Payé' : (newPaye > 0 ? '⚠️ Partiel' : '❌ Impayé');
    const remiseLine = remise > 0 ? `Réduction : <strong style="color:#a855f7;">${fmtAmt(remise)}</strong> → Effectif : <strong>${fmtAmt(effectif)}</strong><br>` : '';
    preview.innerHTML = `${remiseLine}Nouveau total payé : <strong style="color:#34d399;">${fmtAmt(newPaye)}</strong> · Restant : <strong style="color:${newRestant>0?'#f87171':'#34d399'};">${fmtAmt(newRestant)}</strong> · Statut → <strong>${newStatut}</strong>`;
  } else {
    preview.style.display = 'none';
  }
}

function savePayment() {
  if (!_payCurrentRow) return;
  const fd = new FormData();
  fd.append('save_payment', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_stagiaire', _payCurrentRow.id_stagiaire);
  fd.append('mois_ref', _payCurrentRow.mois_ref);

  if (_payCurrentRow.has_record) {
    // Ajout mode
    const vers = parseFloat(document.getElementById('pay-nouveau-versement').value) || 0;
    const remiseAjout = parseFloat(document.getElementById('pay-remise-ajout').value) || 0;
    if (vers <= 0 && remiseAjout === (_payCurrentRow.remise || 0)) {
      showToast('Entrez un montant à ajouter ou modifiez la réduction.', 'error'); return;
    }
    fd.append('is_ajout', '1');
    fd.append('remise', remiseAjout);
    fd.append('nouveau_versement', vers);
    fd.append('statut_paiement', 'partiel');
    fd.append('date_paiement', document.getElementById('pay-date-ajout').value);
  } else {
    // New record
    const statut  = document.getElementById('pay-statut').value;
    const remise  = parseFloat(document.getElementById('pay-remise').value) || 0;
    const effectif = Math.max(0, TARIF_CLASSE - remise);
    fd.append('remise', remise);
    fd.append('statut_paiement', statut);
    fd.append('date_paiement', document.getElementById('pay-date').value);
    if (statut === 'partiel') {
      const mp = parseFloat(document.getElementById('pay-montant-paye').value) || 0;
      if (mp <= 0) { showToast('Entrez un montant payé.', 'error'); return; }
      fd.append('montant_paye', mp);
    } else if (statut === 'payé') {
      fd.append('montant_paye', effectif);
    } else {
      fd.append('montant_paye', 0);
    }
  }

  document.getElementById('pay-save-btn').disabled = true;
  document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';

  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(data => {
      document.getElementById('pay-save-btn').disabled = false;
      document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
      if (data.success) {
        const prevRow = _payCurrentRow;
        closePayModal();
        showToast('Paiement enregistré.', 'success');
        updateRow(prevRow.id_stagiaire, data.row, data.tarif, prevRow.nom);
      } else {
        showToast('Erreur : ' + data.error, 'error');
      }
    }).catch(e => {
      document.getElementById('pay-save-btn').disabled = false;
      document.getElementById('pay-save-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer';
      showToast('Erreur réseau (' + e.message + ').', 'error');
    });
}

// ── Update a row in place after save ────────────────────────────────────
function updateRow(sid, row, tarif, nomFallback) {
  const tr = document.getElementById('row-' + sid);
  if (!tr) { setTimeout(() => location.reload(), 800); return; }
  const mRemise  = row && row.remise          ? parseFloat(row.remise)          : 0;
  const mRawTotal= row && row.montant_total   ? parseFloat(row.montant_total)   : tarif;
  const mEffectif= Math.max(0, mRawTotal - mRemise);
  const mPaye    = row && row.montant_paye    ? parseFloat(row.montant_paye)    : 0;
  const mRest    = row && row.montant_restant ? parseFloat(row.montant_restant) : mEffectif;
  const sp       = row ? (row.statut_paiement || '') : '';
  const isPaye   = (row ? (parseInt(row.est_paye) === 1) : false) || sp === 'payé';
  const isPartiel= sp === 'partiel';
  const statusClass = isPaye ? 'paye' : (isPartiel ? 'partiel' : 'impaye');
  const statusLabel = isPaye ? 'Payé' : (isPartiel ? 'Partiel' : 'Impayé');
  const duCell = tr.querySelector('.col-du');
  if (duCell) {
    duCell.innerHTML = fmtAmt(mEffectif) +
      (mRemise > 0 ? `<br><span style="font-size:.7rem;color:#a855f7;font-weight:600;">-${fmtAmt(mRemise)} réduc.</span>` : '');
  }
  tr.querySelector('.col-paye').textContent  = fmtAmt(mPaye);
  tr.querySelector('.col-paye').className    = 'col-paye ' + (mPaye > 0 ? 'amount-green' : 'amount-gray');
  tr.querySelector('.col-restant').textContent = fmtAmt(mRest);
  tr.querySelector('.col-restant').className   = 'col-restant ' + (mRest > 0 ? 'amount-red' : 'amount-gray');
  tr.querySelector('.col-statut').innerHTML  = `<span class="badge-cot ${statusClass}">${statusLabel}</span>`;
  // Update the openPayModal data for next click
  tr.querySelector('button').setAttribute('onclick',
    `openPayModal(${JSON.stringify({
      id_stagiaire: sid,
      nom: nomFallback || '',
      mois_ref: SEL_MOIS,
      tarif: tarif,
      remise: mRemise,
      montant_paye: mPaye,
      montant_restant: mRest,
      has_record: true,
      statut: sp,
    })})`
  );
}

// ── Bulk mark as paid ────────────────────────────────────────────────────
async function doBulkMarkPaid() {
  const ids = getSelectedIds();
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire.', 'error'); return; }
  const ok = await gdsConfirm(`Marquer ${ids.length} stagiaire(s) comme entièrement payé(s) pour ${formatMois(SEL_MOIS)} ?`);
  if (!ok) return;
  const fd = new FormData();
  fd.append('bulk_mark_paid', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('mois_ref', SEL_MOIS);
  ids.forEach(id => fd.append('student_ids[]', id));
  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`${data.updated} cotisation(s) marquée(s) comme payée(s).`, 'success');
        ids.forEach(id => {
          const tr = document.getElementById('row-' + id);
          if (!tr) return;
          tr.querySelector('.col-paye').textContent = fmtAmt(TARIF_CLASSE);
          tr.querySelector('.col-paye').className = 'col-paye amount-green';
          tr.querySelector('.col-restant').textContent = fmtAmt(0);
          tr.querySelector('.col-restant').className = 'col-restant amount-gray';
          tr.querySelector('.col-statut').innerHTML = '<span class="badge-cot paye">Payé</span>';
          tr.style.transition = 'background .4s';
          tr.style.background = 'rgba(52,211,153,.1)';
          setTimeout(() => { tr.style.background = ''; }, 1400);
        });
        document.querySelectorAll('.row-cb:checked').forEach(cb => { cb.checked = false; });
        updateBulkBar();
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e => showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Bulk partial payment ─────────────────────────────────────────────────
async function doBulkPartial() {
  const ids = getSelectedIds();
  const montant = parseFloat(document.getElementById('bulk-montant').value) || 0;
  if (!ids.length) { showToast('Sélectionnez au moins un stagiaire.', 'error'); return; }
  if (montant <= 0) { showToast('Entrez un montant dans le champ "Montant (MAD)".', 'error'); document.getElementById('bulk-montant').focus(); return; }
  const ok = await gdsConfirm(`Ajouter ${fmtAmt(montant)} de paiement pour ${ids.length} stagiaire(s) — ${formatMois(SEL_MOIS)} ?`);
  if (!ok) return;
  const fd = new FormData();
  fd.append('bulk_partial', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('mois_ref', SEL_MOIS);
  fd.append('montant_partiel', montant);
  fd.append('date_paiement', new Date().toISOString().slice(0, 10));
  ids.forEach(id => fd.append('student_ids[]', id));
  fetch('cotisations.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`Paiement partiel appliqué à ${data.updated} stagiaire(s).`, 'success');
        ids.forEach(id => {
          const tr = document.getElementById('row-' + id);
          if (!tr) return;
          const payeCell = tr.querySelector('.col-paye');
          const restCell = tr.querySelector('.col-restant');
          const curPaye = parseFloat(payeCell.textContent) || 0;
          const newPaye = Math.min(TARIF_CLASSE, curPaye + montant);
          const newRest = Math.max(0, TARIF_CLASSE - newPaye);
          const newStatut = newRest <= 0 ? 'paye' : 'partiel';
          const newLabel  = newRest <= 0 ? 'Payé' : 'Partiel';
          payeCell.textContent = fmtAmt(newPaye);
          payeCell.className   = 'col-paye amount-green';
          restCell.textContent = fmtAmt(newRest);
          restCell.className   = 'col-restant ' + (newRest > 0 ? 'amount-red' : 'amount-gray');
          tr.querySelector('.col-statut').innerHTML = `<span class="badge-cot ${newStatut}">${newLabel}</span>`;
          tr.style.transition = 'background .4s';
          tr.style.background = 'rgba(168,85,247,.1)';
          setTimeout(() => { tr.style.background = ''; }, 1400);
        });
        document.getElementById('bulk-montant').value = '';
        document.querySelectorAll('.row-cb:checked').forEach(cb => { cb.checked = false; });
        updateBulkBar();
      } else { showToast('Erreur : ' + data.error, 'error'); }
    }).catch(e => showToast('Erreur réseau (' + e.message + ').', 'error'));
}

// ── Helpers ──────────────────────────────────────────────────────────────
function fmtAmt(v) {
  return parseFloat(v || 0).toFixed(2) + ' MAD';
}
function formatMois(m) {
  const [y, mo] = (m || '').split('-');
  const noms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
  return (noms[parseInt(mo)] || mo) + ' ' + y;
}
function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'gds-toast ' + (type || 'info');
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, type === 'error' ? 5000 : 3500);
}
let _gdsConfirmCb = null;
function gdsConfirm(msg) {
  return new Promise(resolve => {
    _gdsConfirmCb = resolve;
    document.getElementById('gds-confirm-msg').textContent = msg;
    document.getElementById('gds-confirm-overlay').style.display = 'flex';
  });
}
function gdsConfirmResolve(result) {
  document.getElementById('gds-confirm-overlay').style.display = 'none';
  if (_gdsConfirmCb) { _gdsConfirmCb(result); _gdsConfirmCb = null; }
}
document.getElementById('gds-confirm-overlay')?.addEventListener('click', function(e) {
  if (e.target === this) gdsConfirmResolve(false);
});
document.getElementById('modal-pay')?.addEventListener('click', function(e) {
  if (e.target === this) closePayModal();
});

// ── Student full-year payment drawer ─────────────────────────────────────
const MOIS_NOMS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const MOIS_ICONS = { paye:'fa-circle-check', partiel:'fa-circle-half-stroke', impayé:'fa-circle-xmark', '':'fa-circle' };

let _pmtDrawerSid  = null;
let _pmtDrawerData = null; // last fetched data, used for refresh after modal save

function openPmtDrawer(sid) {
  _pmtDrawerSid = sid;
  const body = document.getElementById('pmt-drawer-body');
  const foot = document.getElementById('pmt-drawer-foot');

  document.getElementById('pmt-drawer-name').textContent = 'Chargement…';
  document.getElementById('pmt-drawer-sub').innerHTML    = '';
  document.getElementById('pmt-drawer-avatar').textContent = '?';
  body.innerHTML = '<div class="pmt-skeleton">' + Array(10).fill('<div class="pmt-skel-row"></div>').join('') + '</div>';
  foot.style.display = 'none';

  document.getElementById('pmt-overlay').classList.add('open');
  document.getElementById('pmt-drawer').classList.add('open');
  document.body.style.overflow = 'hidden';

  _fetchPmtDrawer(sid);
}

function _fetchPmtDrawer(sid) {
  const fd = new FormData();
  fd.append('get_student_payments', '1');
  fd.append('csrf_token', GDS_CSRF);
  fd.append('id_stagiaire', sid);

  fetch('cotisations.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('pmt-drawer-body').innerHTML =
          `<div style="color:#f87171;padding:1.5rem;text-align:center;"><i class="fa-solid fa-triangle-exclamation" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>${data.error||'Erreur.'}</div>`;
        return;
      }
      _pmtDrawerData = data;
      _renderPmtDrawer(data);
    })
    .catch(e => {
      document.getElementById('pmt-drawer-body').innerHTML =
        `<div style="color:#f87171;padding:1rem;">Erreur réseau : ${e.message}</div>`;
    });
}

function _renderPmtDrawer(data) {
  // Avatar initials
  const parts    = data.nom.trim().split(' ');
  const initials = (parts[0]?.[0]||'') + (parts[1]?.[0]||'');
  document.getElementById('pmt-drawer-avatar').textContent = initials.toUpperCase();
  document.getElementById('pmt-drawer-name').textContent   = data.nom;
  document.getElementById('pmt-drawer-sub').innerHTML =
    `<span>${data.num_inscri||''}</span>` +
    (data.num_inscri ? '<span class="pip"></span>' : '') +
    `<span>${data.annee||''}</span>` +
    `<span class="pip"></span><span>Tarif : <strong style="color:#c084fc;">${fmtAmtN(data.tarif)}</strong>/mois</span>`;

  // Build month cards
  let html = '';
  data.rows.forEach(row => {
    const [y, m]    = row.mois.split('-');
    const moisLabel = (MOIS_NOMS[parseInt(m)]||m) + ' ' + y;
    const isPaye    = row.statut === 'payé';
    const isPartiel = row.statut === 'partiel';
    const isImpaye  = row.statut === 'impayé';
    const isEmpty   = !row.statut;
    const isCurrent = row.mois === SEL_MOIS;

    const cardCls   = 'pmt-card' +
      (isCurrent ? ' is-current' : '') +
      (isPaye    ? ' is-paye'    : '') +
      (isPartiel ? ' is-partiel' : '') +
      (isImpaye  ? ' is-impaye'  : '');

    const badgeCls  = isPaye ? 'paye' : isPartiel ? 'partiel' : isImpaye ? 'impaye' : 'aucun';
    const badgeIcon = isPaye ? 'fa-circle-check' : isPartiel ? 'fa-circle-half-stroke' : isImpaye ? 'fa-circle-xmark' : 'fa-circle';
    const badgeLbl  = isPaye ? 'Payé' : isPartiel ? 'Partiel' : isImpaye ? 'Impayé' : 'Non enregistré';

    // progress bar — based on effective amount
    const effectif  = row.du; // already = montant_total - remise (computed in PHP)
    const pct       = effectif > 0 ? Math.min(100, (row.paye / effectif) * 100) : 0;
    const barColor  = isPaye ? '#34d399' : isPartiel ? '#fb923c' : '#3f3f46';

    // amounts colors
    const payeColor = row.paye > 0   ? '#34d399' : '#52525b';
    const restColor = row.restant > 0 ? '#f87171' : '#52525b';

    // date
    const dateStr = row.date_paiement
      ? '<i class="fa-regular fa-calendar-check" style="color:#34d399;"></i> ' + row.date_paiement.slice(0,10)
      : '<i class="fa-regular fa-calendar" style="color:#3f3f46;"></i> <span style="color:#3f3f46;">Aucun paiement</span>';

    // edit button — show always so you can record a new payment or update
    const editRowData = JSON.stringify({
      id_stagiaire: _pmtDrawerSid,
      nom: data.nom,
      mois_ref: row.mois,
      tarif: data.tarif,
      remise: row.remise || 0,
      montant_paye: row.paye,
      montant_restant: row.restant,
      has_record: !!row.statut,
      statut: row.statut || '',
    });
    const remiseBadge = (row.remise || 0) > 0
      ? `<span style="display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;font-size:.65rem;font-weight:700;background:rgba(168,85,247,.15);color:#c084fc;border:1px solid rgba(168,85,247,.3);">-${fmtAmtN(row.remise)} réduc.</span>`
      : '';

    html += `<div class="${cardCls}">
      <div class="pmt-card-top">
        <div class="pmt-card-month">
          ${isCurrent ? '<i class="fa-solid fa-bookmark" style="color:#a855f7;font-size:.7rem;"></i>' : ''}
          ${moisLabel} ${remiseBadge}
        </div>
        <div class="pmt-card-right">
          <span class="pmt-badge ${badgeCls}"><i class="fa-solid ${badgeIcon}"></i> ${badgeLbl}</span>
          ${row.statut ? `<a href="print_recu_paiement.php?id=${_pmtDrawerSid}&mois=${row.mois}" target="_blank"
            style="display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:7px;font-size:.72rem;font-weight:600;border:1px solid rgba(250,204,21,.3);background:rgba(250,204,21,.08);color:#fde047;text-decoration:none;white-space:nowrap;transition:all .15s;"
            onmouseover="this.style.background='rgba(250,204,21,.18)'" onmouseout="this.style.background='rgba(250,204,21,.08)'">
            <i class="fa-solid fa-receipt"></i> Reçu</a>` : ''}
          <button type="button" class="pmt-edit-btn" onclick='openPayModalFromDrawer(${editRowData})'>
            <i class="fa-solid fa-pen-to-square"></i> Modifier
          </button>
        </div>
      </div>
      <div class="pmt-progress-wrap">
        <div class="pmt-progress-fill" style="width:${pct}%;background:${barColor};"></div>
      </div>
      <div class="pmt-amounts">
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Dû</span>
          <span class="pmt-amount-val" style="color:#a1a1aa;">${fmtAmtN(row.du)}</span>
        </div>
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Payé</span>
          <span class="pmt-amount-val" style="color:${payeColor};">${fmtAmtN(row.paye)}</span>
        </div>
        <div class="pmt-amount-item">
          <span class="pmt-amount-lbl">Restant</span>
          <span class="pmt-amount-val" style="color:${restColor};">${fmtAmtN(row.restant)}</span>
        </div>
      </div>
      <div class="pmt-date-line">${dateStr}</div>
    </div>`;
  });

  document.getElementById('pmt-drawer-body').innerHTML = html;

  // Footer
  const restCol = data.total_rest > 0 ? '#f87171' : '#34d399';
  document.getElementById('pmt-tot-du').textContent    = fmtAmtN(data.total_du);
  document.getElementById('pmt-tot-paye').textContent  = fmtAmtN(data.total_paye);
  document.getElementById('pmt-tot-rest').textContent  = fmtAmtN(data.total_rest);
  document.getElementById('pmt-tot-rest').style.color  = restCol;

  const lastEl = document.getElementById('pmt-last-pay');
  if (data.last_pay_date) {
    lastEl.innerHTML = '<i class="fa-regular fa-calendar-check" style="color:#34d399;"></i> Dernier paiement : <strong style="color:#e4e4e7;">' + data.last_pay_date.slice(0,10) + '</strong>';
  } else {
    lastEl.innerHTML = '<i class="fa-regular fa-calendar-xmark" style="color:#f87171;"></i> Aucun paiement enregistré';
  }
  document.getElementById('pmt-drawer-foot').style.display = '';
}

// Open pay modal pre-filled from drawer, then refresh drawer on save
function openPayModalFromDrawer(rowData) {
  openPayModal(rowData);
  // After save, the existing savePayment() success path calls updateRow().
  // We hook in via _pmtDrawerAfterSave flag.
  _pmtDrawerAfterSaveNeeded = true;
}

let _pmtDrawerAfterSaveNeeded = false;

// Patch into the existing savePayment success flow
const _origUpdateRow = window.updateRow || function(){};
// We override savePayment's success callback by wrapping it
const _origSavePayment = window.savePayment;

function closePmtDrawer() {
  document.getElementById('pmt-overlay').classList.remove('open');
  document.getElementById('pmt-drawer').classList.remove('open');
  document.body.style.overflow = '';
  _pmtDrawerAfterSaveNeeded = false;
}

function fmtAmtN(v) {
  return parseFloat(v||0).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MAD';
}

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closePmtDrawer(); closePayModal(); }
});

// ── Hook: after a payment modal save, refresh the drawer if open ──────────
// We do this by monkey-patching the fetch response in savePayment.
// Instead, we watch for the pay-save-btn to re-enable (save done) and reload.
(function() {
  const btn = document.getElementById('pay-save-btn');
  if (!btn) return;
  const observer = new MutationObserver(() => {
    if (!btn.disabled && _pmtDrawerAfterSaveNeeded && _pmtDrawerSid) {
      _pmtDrawerAfterSaveNeeded = false;
      // Small delay so the modal close animation finishes
      setTimeout(() => {
        document.getElementById('pmt-drawer-body').innerHTML =
          '<div class="pmt-skeleton">' + Array(10).fill('<div class="pmt-skel-row"></div>').join('') + '</div>';
        document.getElementById('pmt-drawer-foot').style.display = 'none';
        _fetchPmtDrawer(_pmtDrawerSid);
      }, 350);
    }
  });
  observer.observe(btn, { attributes: true, attributeFilter: ['disabled'] });
})();
</script>

<?php
$highlightSid = (int)($_GET['highlight'] ?? 0);
if ($highlightSid > 0):
?>
<style>
@keyframes gds-row-flash {
  0%   { background: transparent; }
  15%  { background: rgba(168,85,247,0.28); box-shadow: inset 0 0 0 2px rgba(168,85,247,0.6); }
  50%  { background: rgba(168,85,247,0.14); }
  85%  { background: rgba(168,85,247,0.28); box-shadow: inset 0 0 0 2px rgba(168,85,247,0.6); }
  100% { background: transparent; }
}
.gds-highlight-row td { animation: gds-row-flash 1.6s ease 2; }
.gds-highlight-row td:first-child { border-left: 3px solid #a855f7; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const sid = <?= $highlightSid ?>;
  const tr = document.getElementById('row-' + sid);
  if (!tr) return;
  tr.classList.add('gds-highlight-row');
  setTimeout(function () { tr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200);
  setTimeout(function () { tr.classList.remove('gds-highlight-row'); }, 4000);
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
