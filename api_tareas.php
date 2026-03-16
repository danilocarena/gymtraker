<?php
// api_tareas.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$q = $_GET['q'] ?? '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM DT_tasks WHERE name LIKE ? ORDER BY name ASC LIMIT 10");
    $stmt->execute(['%' . $q . '%']);
    $tareas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($tareas);
} catch (Exception $e) {
    echo json_encode([]);
}
