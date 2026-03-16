<?php
// api_save_routine.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'add') {
        $day = intval($data['day_of_week']);
        $tasks = $data['tasks'] ?? [];

        if (empty($tasks)) {
            echo json_encode(['error' => 'No se enviaron tareas']);
            exit;
        }

        $pdo->beginTransaction();
        
        // Obtener el orden máximo actual para ese día
        $stmtMax = $pdo->prepare("SELECT MAX(task_order) FROM DT_weekly_routine WHERE user_id = ? AND day_of_week = ?");
        $stmtMax->execute([$user_id, $day]);
        $currentMax = intval($stmtMax->fetchColumn());

        foreach ($tasks as $task_name) {
            $task_name = trim($task_name);
            if (empty($task_name)) continue;
            
            $currentMax++;

            // 1. Asegurar que la tarea existe en el catálogo
            $stmtTask = $pdo->prepare("INSERT IGNORE INTO DT_tasks (name, category_id) VALUES (?, 2)");
            $stmtTask->execute([$task_name]);
            
            $stmtGetTask = $pdo->prepare("SELECT id FROM DT_tasks WHERE name = ?");
            $stmtGetTask->execute([$task_name]);
            $task_id = $stmtGetTask->fetchColumn();

            // 2. Insertar en la rutina
            $stmt = $pdo->prepare("INSERT INTO DT_weekly_routine (user_id, day_of_week, task_id, task_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $day, $task_id, $currentMax]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        $id = intval($data['id']);
        $stmt = $pdo->prepare("DELETE FROM DT_weekly_routine WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'reorder') {
        $order = $data['order'] ?? []; // Array de IDs en el nuevo orden
        if (empty($order)) {
             echo json_encode(['error' => 'No hay orden que guardar']);
             exit;
        }

        $pdo->beginTransaction();
        $stmtUpdate = $pdo->prepare("UPDATE DT_weekly_routine SET task_order = ? WHERE id = ? AND user_id = ?");
        foreach ($order as $index => $id) {
            $stmtUpdate->execute([$index, $id, $user_id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
