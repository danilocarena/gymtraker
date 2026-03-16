<?php
// api_toggle_task.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);
$log_id = $data['log_id'] ?? null;
$status = $data['status'] ?? null;

if (!$log_id || !$status) {
    echo json_encode(['error' => 'Datos insuficientes']);
    exit;
}

try {
    // Verificar que el log pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT tl.id, dp.plan_date, dp.id as plan_id 
        FROM DT_task_logs tl 
        JOIN DT_daily_plans dp ON tl.plan_id = dp.id 
        WHERE tl.id = ? AND dp.user_id = ?
    ");
    $stmt->execute([$log_id, $user_id]);
    $log_info = $stmt->fetch();
    
    if ($log_info) {
        $stmtUpdate = $pdo->prepare("UPDATE DT_task_logs SET status = ? WHERE id = ?");
        $stmtUpdate->execute([$status, $log_id]);

        // RE-CALCULAR ESTADÍSTICAS PARA RESPUESTA EN TIEMPO REAL
        
        // 1. Eficiencia de hoy
        $plan_id = $log_info['plan_id'];
        $stmtStats = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM DT_task_logs 
            WHERE plan_id = ?
        ");
        $stmtStats->execute([$plan_id]);
        $stats = $stmtStats->fetch();
        $rate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100) : 0;

        // 2. Progreso semanal
        $stmtWeekly = $pdo->prepare("
            SELECT COUNT(*) 
            FROM DT_task_logs tl
            JOIN DT_daily_plans dp ON tl.plan_id = dp.id
            WHERE dp.user_id = ? 
            AND tl.status = 'completed'
            AND YEARWEEK(dp.plan_date, 1) = YEARWEEK(CURDATE(), 1)
        ");
        $stmtWeekly->execute([$user_id]);
        $weekly_count = intval($stmtWeekly->fetchColumn());

        // 3. Obtener meta semanal dinámica (total de la rutina)
        $stmtGoal = $pdo->prepare("SELECT COUNT(*) FROM DT_weekly_routine WHERE user_id = ?");
        $stmtGoal->execute([$user_id]);
        $weekly_goal = intval($stmtGoal->fetchColumn()) ?: 1;
        $weekly_perc = min(100, round(($weekly_count / $weekly_goal) * 100));

        echo json_encode([
            'success' => true, 
            'new_status' => $status,
            'today_rate' => $rate,
            'weekly_count' => $weekly_count,
            'weekly_perc' => $weekly_perc
        ]);
    } else {
        echo json_encode(['error' => 'No encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
