<?php
// index.php - Dashboard con Plan del Día y Auto-Generación
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = getCurrentUserId();
$username = getCurrentUsername();
$active_page = 'dashboard';
$page_title = 'Dashboard - DayTraker Pro';

// --- AUTO-GENERACIÓN DEL PLAN DEL DÍA ---
$today = date('Y-m-d');
$day_of_week = date('w'); // 0 (domingo) a 6 (sábado)

// 1. Verificar si ya existe un plan para hoy
$stmtToday = $pdo->prepare("SELECT id FROM DT_daily_plans WHERE user_id = ? AND plan_date = ? LIMIT 1");
$stmtToday->execute([$user_id, $today]);
$plan_id = $stmtToday->fetchColumn();

if (!$plan_id) {
    // 2. Si no existe, buscar en la rutina semanal para este día
    $stmtRoutine = $pdo->prepare("SELECT task_id FROM DT_weekly_routine WHERE user_id = ? AND day_of_week = ?");
    $stmtRoutine->execute([$user_id, $day_of_week]);
    $routine_tasks = $stmtRoutine->fetchAll();

    if (!empty($routine_tasks)) {
        try {
            $pdo->beginTransaction();
            // Crear el plan
            $stmtInsertPlan = $pdo->prepare("INSERT INTO DT_daily_plans (user_id, plan_date, plan_name) VALUES (?, ?, ?)");
            $stmtInsertPlan->execute([$user_id, $today, 'Plan del ' . date('l')]);
            $plan_id = $pdo->lastInsertId();

            // Insertar las tareas de la rutina
            $stmtInsertTask = $pdo->prepare("INSERT INTO DT_task_logs (plan_id, task_id, status) VALUES (?, ?, 'pending')");
            foreach ($routine_tasks as $rt) {
                $stmtInsertTask->execute([$plan_id, $rt['task_id']]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// --- DATA FETCHING ---
$stmtUser = $pdo->prepare("SELECT avatar_emoji FROM DT_users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user_data = $stmtUser->fetch();
$avatar = $user_data['avatar_emoji'] ?: '📅';

// Cálculo dinámico de la meta semanal: total de tareas en la rutina
$stmtGoal = $pdo->prepare("SELECT COUNT(*) FROM DT_weekly_routine WHERE user_id = ?");
$stmtGoal->execute([$user_id]);
$weekly_goal = intval($stmtGoal->fetchColumn()) ?: 1;

// 1. Tareas de Hoy
$today_tasks = [];
if ($plan_id) {
    $stmtTasks = $pdo->prepare("
        SELECT tl.id as log_id, t.name as task_name, tl.status 
        FROM DT_task_logs tl 
        JOIN DT_tasks t ON tl.task_id = t.id 
        WHERE tl.plan_id = ? 
        ORDER BY tl.id ASC
    ");
    $stmtTasks->execute([$plan_id]);
    $today_tasks = $stmtTasks->fetchAll();
}

// 1.1 Tareas Pendientes de Ayer
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmtYesterday = $pdo->prepare("
    SELECT tl.id as log_id, t.name as task_name, tl.status 
    FROM DT_task_logs tl 
    JOIN DT_daily_plans dp ON tl.plan_id = dp.id 
    JOIN DT_tasks t ON tl.task_id = t.id 
    WHERE dp.user_id = ? AND dp.plan_date = ? AND tl.status = 'pending'
    ORDER BY tl.id ASC
");
$stmtYesterday->execute([$user_id, $yesterday]);
$yesterday_tasks = $stmtYesterday->fetchAll();

// 2. Progreso Semanal (Completadas esta semana)
$stmtWeekly = $pdo->prepare("
    SELECT COUNT(*) 
    FROM DT_task_logs tl 
    JOIN DT_daily_plans dp ON tl.plan_id = dp.id 
    WHERE dp.user_id = ? 
    AND tl.status = 'completed' 
    AND YEARWEEK(dp.plan_date, 1) = YEARWEEK(CURDATE(), 1)
");
$stmtWeekly->execute([$user_id]);
$weekly_count = $stmtWeekly->fetchColumn();
$weekly_perc = min(($weekly_count / $weekly_goal) * 100, 100);

// 3. Eficiencia
$stmtRate = $pdo->prepare("
    SELECT (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as rate 
    FROM DT_task_logs tl 
    JOIN DT_daily_plans dp ON tl.plan_id = dp.id 
    WHERE dp.user_id = ?
");
$stmtRate->execute([$user_id]);
$completion_rate = round($stmtRate->fetchColumn() ?: 0, 1);
$rate_color = $completion_rate < 50 ? '#ef4444' : ($completion_rate < 80 ? '#f59e0b' : '#22c55e');

// 4. Datos para gráfico
$stmtProd = $pdo->prepare("
    SELECT dp.plan_date, COUNT(*) as count 
    FROM DT_task_logs tl 
    JOIN DT_daily_plans dp ON tl.plan_id = dp.id 
    WHERE dp.user_id = ? AND tl.status = 'completed'
    GROUP BY dp.plan_date ORDER BY dp.plan_date ASC LIMIT 15
");
$stmtProd->execute([$user_id]);
$prod_logs = $stmtProd->fetchAll();
$prod_labels = []; $prod_values = [];
foreach ($prod_logs as $log) {
    $prod_labels[] = date('d/m', strtotime($log['plan_date']));
    $prod_values[] = $log['count'];
}

require_once 'includes/header.php';
?>

<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <div class="flex items-center gap-4">
        <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-3xl shadow-lg border border-primary/20"><?= $avatar ?></div>
        <div>
            <h1 class="text-3xl font-extrabold mb-1"> <span class="text-primary">¡Hola, <?= htmlspecialchars($username) ?>!</span></h1>
            <p class="text-slate-400">Hoy es <?= date('l, d M') ?>.</p>
        </div>
    </div>
</header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-8">
        <section class="glass-panel">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <span class="p-1.5 bg-primary/10 rounded-lg text-primary text-sm"><i class="fa-solid fa-list-check"></i></span>
                    Tareas para Hoy
                </h2>
                <a href="plan_nuevo" class="text-xs text-primary font-bold hover:underline">Añadir Tarea Extra</a>
            </div>

            <?php if (!empty($yesterday_tasks)): ?>
                <div class="mb-8 bg-amber-500/5 border border-amber-500/10 rounded-2xl p-4 overflow-hidden relative">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-500/5 rounded-full blur-3xl"></div>
                    <h3 class="text-amber-500 text-[10px] font-black uppercase tracking-[3px] mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left"></i> Pendientes de Ayer
                    </h3>
                    <div class="space-y-2">
                        <?php foreach($yesterday_tasks as $y_task): ?>
                            <div class="task-item flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] border border-white/5 transition-all hover:bg-white/[0.05]" data-id="<?= $y_task['log_id'] ?>">
                                <button onclick="toggleTask(<?= $y_task['log_id'] ?>)" class="toggle-btn w-5 h-5 rounded border-2 border-amber-500/30 flex items-center justify-center transition-all hover:border-amber-500">
                                    <i class="fa-solid fa-check text-[10px] text-amber-500 hidden"></i>
                                </button>
                                <span class="text-sm font-bold text-slate-200"><?= htmlspecialchars($y_task['task_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="space-y-3" id="task-list">
                <?php if (empty($today_tasks)): ?>
                    <div class="text-center py-10">
                        <p class="text-slate-500 italic mb-4">No tienes tareas para hoy todavía.</p>
                        <a href="rutina" class="btn-primary inline-block text-xs">Configurar mi Rutina</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($today_tasks as $task): 
                        $is_completed = ($task['status'] === 'completed');
                    ?>
                        <div class="task-item flex items-center gap-4 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all hover:bg-white/[0.05] <?= $is_completed ? 'completed opacity-50' : '' ?>" data-id="<?= $task['log_id'] ?>">
                            <button onclick="toggleTask(<?= $task['log_id'] ?>)" class="toggle-btn w-6 h-6 rounded-lg border-2 border-primary/30 flex items-center justify-center transition-all hover:border-primary">
                                <i class="fa-solid fa-check text-xs text-primary <?= $is_completed ? '' : 'hidden' ?>"></i>
                            </button>
                            <div class="flex-1">
                                <h4 class="font-bold text-white transition-all <?= $is_completed ? 'line-through' : '' ?>"><?= htmlspecialchars($task['task_name']) ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="glass-panel min-h-[300px] flex flex-col">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                <span class="p-1.5 bg-indigo-500/10 rounded-lg text-indigo-400 text-sm"><i class="fa-solid fa-chart-line"></i></span>
                Progreso Diario
            </h2>
            <div class="flex-1 w-full relative"><canvas id="prodChart"></canvas></div>
        </section>
    </div>

    <div class="space-y-6">
        <div class="glass-panel">
            <h3 class="text-[10px] text-slate-500 font-bold mb-4 uppercase tracking-widest text-center">Meta: <?= $weekly_goal ?> completas</h3>
            <div class="h-3 bg-white/5 rounded-full mb-3 overflow-hidden border border-white/5">
                <div id="weekly-progress-bar" class="h-full bg-gradient-to-r from-primary to-purple-400 rounded-full transition-all duration-1000 shadow-[0_0_15px_rgba(99,102,241,0.3)]" style="width: <?= $weekly_perc ?>%;"></div>
            </div>
            <p class="text-center text-xs text-slate-500"><span id="weekly-count-text"><?= $weekly_count ?></span> esta semana</p>
        </div>

        <div class="glass-panel text-center">
            <h3 class="text-[10px] text-slate-500 font-bold mb-2 uppercase tracking-widest">Eficiencia Total</h3>
            <div class="relative h-32 flex items-center justify-center">
                <canvas id="prodGauge"></canvas>
                <div id="efficiency-rate-text" class="absolute bottom-2 text-3xl font-black text-white"><?= $completion_rate ?>%</div>
            </div>
        </div>

        <div class="glass-panel bg-gradient-to-br from-primary/10 to-transparent">
            <h3 class="text-xs font-black text-white mb-4 uppercase tracking-widest">Atajos</h3>
            <div class="grid grid-cols-1 gap-2">
                <a href="rutina" class="p-3 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 transition-all flex items-center gap-3 no-underline group">
                    <span class="text-primary group-hover:scale-110 transition-transform"><i class="fa-solid fa-repeat"></i></span>
                    <span class="text-xs font-bold text-white">Configurar Rutina</span>
                </a>
                <a href="tareas" class="p-3 bg-white/5 rounded-xl border border-white/10 hover:bg-white/10 transition-all flex items-center gap-3 no-underline group">
                    <span class="text-purple-400 group-hover:scale-110 transition-transform"><i class="fa-solid fa-list-ul"></i></span>
                    <span class="text-xs font-bold text-white">Catálogo de Tareas</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function toggleTask(logId) {
    const item = document.querySelector(`.task-item[data-id="${logId}"]`);
    const isCompleted = item.classList.contains('completed');
    const newStatus = isCompleted ? 'pending' : 'completed';
    
    try {
        const response = await fetch('api_toggle_task.php', {
            method: 'POST',
            body: JSON.stringify({ log_id: logId, status: newStatus }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        
        if (result.success) {
            const h4 = item.querySelector('h4');
            const check = item.querySelector('.fa-check');
            item.classList.toggle('completed', newStatus === 'completed');
            item.classList.toggle('opacity-50', newStatus === 'completed');
            h4.classList.toggle('line-through', newStatus === 'completed');
            check.classList.toggle('hidden', newStatus !== 'completed');

            // ACTUALIZACIÓN EN TIEMPO REAL
            if (chartGauge && result.today_rate !== undefined) {
                chartGauge.data.datasets[0].data = [result.today_rate, 100 - result.today_rate];
                chartGauge.update();
                document.getElementById('efficiency-rate-text').textContent = result.today_rate + '%';
            }

            if (chartLine && result.today_rate !== undefined) {
                // Actualizar el último punto del gráfico lineal (hoy)
                const lastIdx = chartLine.data.datasets[0].data.length - 1;
                chartLine.data.datasets[0].data[lastIdx] = result.today_rate;
                chartLine.update();
            }

            if (result.weekly_count !== undefined) {
                document.getElementById('weekly-count-text').textContent = result.weekly_count;
                document.getElementById('weekly-progress-bar').style.width = result.weekly_perc + '%';
            }
        }
    } catch (e) { console.error(e); }
}

let chartLine, chartGauge;

document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('prodChart');
    if (ctx) {
        chartLine = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($prod_labels) ?>,
                datasets: [{
                    label: 'Tareas',
                    data: <?= json_encode($prod_values) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8', font: { size: 10 }, callback: v => v + '%' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } }
                }
            }
        });
    }

    const ctxGauge = document.getElementById('prodGauge');
    if (ctxGauge) {
        const rate = <?= $completion_rate ?>;
        chartGauge = new Chart(ctxGauge, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [rate, 100 - rate],
                    backgroundColor: ['<?= $rate_color ?>', 'rgba(255,255,255,0.05)'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                    cutout: '80%',
                    borderRadius: 20
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
    }
});
</script>