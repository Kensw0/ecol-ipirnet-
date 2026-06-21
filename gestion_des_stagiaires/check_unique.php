<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$field    = (string)($_GET['field']   ?? '');
$value    = trim((string)($_GET['value']  ?? ''));
$exclude  = (int)($_GET['exclude'] ?? 0);   // id_demande to exclude (for re-checks)

if ($value === '' || !in_array($field, ['cin', 'email'], true)) {
    echo json_encode(['ok' => true]);
    exit;
}

$taken  = false;
$source = '';

// Check stagiaires table
$colMap = ['cin' => 'cin', 'email' => 'email'];
$col    = $colMap[$field];

$st = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE $col = ?");
$st->execute([$value]);
if ((int)$st->fetchColumn() > 0) {
    $taken  = true;
    $source = 'stagiaire';
}

// Check pending demandes (don't block on same demande)
if (!$taken) {
    $sql = "SELECT COUNT(*) FROM pre_inscription WHERE $col = ? AND statut = 'en_attente'";
    $params = [$value];
    if ($exclude > 0) {
        $sql .= " AND id_demande != ?";
        $params[] = $exclude;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ((int)$st->fetchColumn() > 0) {
        $taken  = true;
        $source = 'demande';
    }
}

$messages = [
    'cin'   => ['stagiaire' => 'Ce CIN est déjà utilisé par un stagiaire inscrit.', 'demande' => 'Une demande avec ce CIN est déjà en attente.'],
    'email' => ['stagiaire' => 'Cet email est déjà utilisé par un stagiaire inscrit.', 'demande' => 'Une demande avec cet email est déjà en attente.'],
];

echo json_encode([
    'ok'      => !$taken,
    'message' => $taken ? ($messages[$field][$source] ?? 'Valeur déjà utilisée.') : '',
]);

