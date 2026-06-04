<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

unset($_SESSION['gds_admin']);
session_regenerate_id(true);
flash_set('Déconnexion effectuée.');
redirect('login.php');
