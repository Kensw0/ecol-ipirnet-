<?php
declare(strict_types=1);

// ============================================================
//  logout.php — Déconnexion de l'utilisateur
//
//  Supprime les variables de session liées à l'authentification,
//  régénère l'ID de session pour invalider l'ancienne session
//  (protection contre le vol de cookie de session),
//  puis redirige vers login.php avec un message de confirmation.
// ============================================================

require __DIR__ . '/includes/bootstrap.php';

// Efface les données de session propres à l'application.
// Note : session_destroy() n'est pas appelé pour préserver d'éventuelles
// données non liées à l'auth (ex. messages flash en transit).
unset($_SESSION['gds_admin'], $_SESSION['gds_role'], $_SESSION['gds_username']);

// Régénère l'identifiant de session pour empêcher la réutilisation
// de l'ancien ID par un attaquant qui l'aurait intercepté.
session_regenerate_id(true);

flash_set('Déconnexion effectuée.');
redirect('login.php');
