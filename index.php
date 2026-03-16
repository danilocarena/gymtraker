<?php
// Main entry point for the GymTracker application
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = getCurrentUserId();
$username = getCurrentUsername();
$active_page = 'dashboard';
$page_title = 'Dashboard - GymTracker Pro';

// --- DATA FETCHING ---

// 0. Obtener configuración del usuario (Objetivo semanal y Avatar)
$stmtUser = $pdo->prepare("SELECT weekly_goal, avatar_emoji FROM GT_users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user_config = $stmtUser->fetch();
$weekly_goal = $user_config['weekly_goal'] ?: 4;
$avatar = $user_config['avatar_emoji'] ?: '🦍';

// 1. Última Sesión
$stmtLast = $pdo->prepare("SELECT session_name, session_date FROM GT_workout_sessions WHERE user_id = ? ORDER BY session_date DESC, id DESC LIMIT 1");
$stmtLast->execute([$user_id]);
$last_session = $stmtLast->fetch();

// 2. Progreso Semanal
$stmtWeekly = $pdo->prepare("SELECT COUNT(*) FROM GT_workout_sessions WHERE user_id = ? AND session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmtWeekly->execute([$user_id]);
$weekly_count = $stmtWeekly->fetchColumn();
$weekly_perc = ($weekly_count / $weekly_goal) * 100;
if ($weekly_perc > 100) $weekly_perc = 100;

// 3. Récord Personal Reciente
$stmtPR = $pdo->prepare("
    SELECT exercise_name, MAX(weight) as max_weight 
    FROM GT_session_logs sl 
    JOIN GT_workout_sessions ws ON sl.session_id = ws.id 
    WHERE ws.user_id = ? AND ws.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY exercise_name 
    ORDER BY max_weight DESC 
    LIMIT 1
");
$stmtPR->execute([$user_id]);
$pr_data = $stmtPR->fetch();

// 4. Datos para el gráfico de peso (Últimos 15 registros)
$stmtWeight = $pdo->prepare("SELECT weight, log_date FROM GT_weight_logs WHERE user_id = ? ORDER BY log_date ASC LIMIT 15");
$stmtWeight->execute([$user_id]);
$GT_weight_logs = $stmtWeight->fetchAll();

$weight_labels = [];
$weight_values = [];
foreach ($GT_weight_logs as $log) {
    $weight_labels[] = date('d/m', strtotime($log['log_date']));
    $weight_values[] = $log['weight'];
}

// 5. Actividad Reciente
$stmtActivity = $pdo->prepare("
    SELECT ws.id, ws.session_name, ws.session_date,
           (SELECT COUNT(DISTINCT exercise_name) FROM GT_session_logs WHERE session_id = ws.id) as exercise_count,
           (SELECT COUNT(*) FROM GT_session_logs WHERE session_id = ws.id) as series_count
    FROM GT_workout_sessions ws
    WHERE ws.user_id = ?
    ORDER BY ws.session_date DESC, ws.id DESC
    LIMIT 5
");
$stmtActivity->execute([$user_id]);
$recent_activity = $stmtActivity->fetchAll();

function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return "Nunca";
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y'=>'año','m'=>'mes','w'=>'semana','d'=>'día','h'=>'hora','i'=>'minuto','s'=>'segundo');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? ($k == 'm' ? 'es' : 's') : ''); } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Hace ' . implode(', ', $string) : 'Justo ahora';
}

require_once 'includes/header.php';
?>
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-3xl shadow-lg border border-primary/20"><?= $avatar ?></div>
                    <div>
                        <h1 class="text-3xl md:text-4xl font-extrabold mb-1 leading-tight"> <span class="text-primary">¡Hola, <?= htmlspecialchars($username) ?>!</span></h1>
                        <p class="text-slate-400 text-base md:text-lg">Listo para romper tus récords hoy.</p>
                    </div>
                </div>
                <button class="btn-primary w-full sm:w-auto shadow-lg shadow-primary/20" onclick="window.location.href='sesion_nueva.php'"><i class="fa-solid fa-plus mr-2"></i>Nueva Sesión</button>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Widget: Última Sesión -->
                <div class="glass-panel">
                    <h3 class="text-[10px] text-slate-500 font-bold mb-4 uppercase tracking-widest">Última Sesión</h3>
                    <div class="flex flex-col gap-1">
                        <span class="text-xl md:text-2xl font-black text-white leading-tight"><?= $last_session ? htmlspecialchars($last_session['session_name']) : 'Sin sesiones' ?></span>
                        <span class="text-xs text-slate-500 font-medium tracking-tight"><?= $last_session ? time_elapsed_string($last_session['session_date']) : 'Empieza hoy' ?></span>
                    </div>
                </div>

                <!-- Widget: Progreso Semanal -->
                <div class="glass-panel">
                    <h3 class="text-[10px] text-slate-500 font-bold mb-4 uppercase tracking-widest">Meta: <?= $weekly_goal ?> entrenos</h3>
                    <div class="h-2.5 bg-white/5 rounded-full mb-3 overflow-hidden border border-white/5">
                        <div class="h-full bg-gradient-to-r from-primary to-green-400 rounded-full shadow-[0_0_12px_rgba(34,197,94,0.4)] transition-all duration-1000" style="width: <?= $weekly_perc ?>%;"></div>
                    </div>
                    <span class="text-xs text-slate-500 font-medium"><?= $weekly_count ?> realizados esta semana</span>
                </div>

                <!-- Widget: Récord Personal -->
                <div class="glass-panel sm:col-span-2 lg:col-span-1">
                    <h3 class="text-[10px] text-slate-500 font-bold mb-4 uppercase tracking-widest">Mejor marca (30d) <i class="fa-solid fa-trophy text-yellow-500 ml-1"></i></h3>
                    <div class="flex flex-col gap-1">
                        <span class="text-xl md:text-2xl font-black text-white leading-tight break-words"><?= $pr_data ? htmlspecialchars($pr_data['exercise_name']) . ': ' . $pr_data['max_weight'] . 'kg' : 'Aún sin marcas' ?></span>
                        <span class="text-xs text-primary font-bold"><?= $pr_data ? '¡Sigue así!' : 'Registra tu primera sesión' ?></span>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <!-- Gráfico de Peso -->
                <section class="glass-panel min-h-[300px] flex flex-col">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <span class="p-1.5 bg-blue-500/10 rounded-lg text-blue-400 text-sm"><i class="fa-solid fa-chart-line"></i></span>
                        Evolución de Peso
                    </h2>
                    <div class="flex-1 w-full relative">
                        <?php if (empty($weight_values)): ?>
                            <div class="absolute inset-0 flex items-center justify-center text-slate-500 italic text-sm">No hay suficientes datos de peso. Registra tu peso en la sección dedicada.</div>
                        <?php else: ?>
                            <canvas id="weightChart"></canvas>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Actividad Reciente -->
                <section class="glass-panel h-full">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <span class="p-1.5 bg-primary/10 rounded-lg text-primary text-sm"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        Actividad Reciente
                    </h2>
                    <div class="flex flex-col gap-3">
                        <?php if (empty($recent_activity)): ?>
                            <p class="text-slate-500 italic text-center py-4 text-sm">No hay actividad todavía.</p>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $act): ?>
                                <div class="flex items-center gap-4 p-4 rounded-xl bg-white/5 border border-transparent transition-all hover:border-white/10 hover:bg-white/10 group">
                                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-primary/10 flex items-center justify-center text-xl md:text-2xl shrink-0 group-hover:scale-110 transition-transform">
                                        <i class="fa-solid fa-dumbbell text-primary"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-bold mb-0.5 truncate text-sm md:text-base text-white group-hover:text-primary transition-colors"><?= htmlspecialchars($act['session_name'] ?: 'Entrenamiento') ?></h4>
                                        <p class="text-xs text-slate-500"><?= $act['exercise_count'] ?> ejercicios • <?= $act['series_count'] ?> series</p>
                                    </div>
                                    <div class="text-[10px] md:text-xs text-slate-500 font-bold uppercase tracking-wider shrink-0 pr-1"><?= date('d M', strtotime($act['session_date'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

<!-- Chart.js para el gráfico de peso -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('weightChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($weight_labels) ?>,
                datasets: [{
                    label: 'Peso (kg)',
                    data: <?= json_encode($weight_values) ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10 } }
                    }
                }
            }
        });
    }
});
</script>
        
<?php require_once 'includes/footer.php'; ?>