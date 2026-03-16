<?php
// historial.php - Historial con Heatmap (Estilo GitHub)
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = getCurrentUserId();
$active_page = 'historial';
$page_title = 'Historial - DayTraker';

// 1. Obtener datos para el Heatmap (últimos 6 meses)
$stmtHeat = $pdo->prepare("
    SELECT dp.plan_date, 
           COUNT(*) as total, 
           COUNT(CASE WHEN tl.status = 'completed' THEN 1 END) as completed
    FROM DT_daily_plans dp
    JOIN DT_task_logs tl ON dp.id = tl.plan_id
    WHERE dp.user_id = ? AND dp.plan_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY dp.plan_date
    ORDER BY dp.plan_date ASC
");
$stmtHeat->execute([$user_id]);
$heat_data = [];
while ($row = $stmtHeat->fetch()) {
    $perc = $row['total'] > 0 ? ($row['completed'] / $row['total']) * 100 : 0;
    $heat_data[$row['plan_date']] = [
        'perc' => $perc,
        'count' => $row['completed'],
        'total' => $row['total']
    ];
}

// 2. Obtener lista de planes recientes
$stmt = $pdo->prepare("
    SELECT dp.id, dp.plan_name, dp.plan_date,
           (SELECT COUNT(*) FROM DT_task_logs WHERE plan_id = dp.id) as task_count,
           (SELECT COUNT(*) FROM DT_task_logs WHERE plan_id = dp.id AND status = 'completed') as completed_count
    FROM DT_daily_plans dp
    WHERE dp.user_id = ?
    ORDER BY dp.plan_date DESC, dp.id DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$plans = $stmt->fetchAll();

require_once 'includes/header.php';

// Generar días para el heatmap
$endDate = new DateTime();
$startDate = (new DateTime())->modify('-6 months')->modify('last Monday');
$interval = new DateInterval('P1D');
$period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

function getHeatColor($perc) {
    if ($perc <= 0) return 'bg-white/5';
    if ($perc < 25) return 'bg-indigo-900/40';
    if ($perc < 50) return 'bg-indigo-700/60';
    if ($perc < 75) return 'bg-indigo-500/80';
    return 'bg-primary shadow-[0_0_10px_rgba(99,102,241,0.4)]';
}
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Historial de Productividad <i class="fa-solid fa-chart-column text-primary ml-1"></i></h1>
    <p class="text-slate-400">Visualiza tu constancia y revisa planes pasados.</p>
</header>

<!-- HEATMAP SECTION -->
<section class="glass-panel mb-8 overflow-hidden">
    <h3 class="text-[10px] text-slate-500 font-bold mb-6 uppercase tracking-widest">Actividad de los últimos 6 meses</h3>
    
    <div class="overflow-x-auto pb-4">
        <div class="inline-grid grid-flow-col grid-rows-7 gap-1.5" style="min-width: 800px;">
            <?php 
            $currentMonth = '';
            foreach ($period as $date): 
                $dateStr = $date->format('Y-m-d');
                $d = $heat_data[$dateStr] ?? null;
                $perc = $d ? $d['perc'] : 0;
                $tooltip = $date->format('d M') . ': ' . ($d ? "{$d['count']}/{$d['total']} tareas" : "Sin actividad");
            ?>
                <div class="w-3.5 h-3.5 rounded-sm <?= getHeatColor($perc) ?> transition-all hover:scale-125 cursor-help relative group" title="<?= $tooltip ?>">
                    <!-- Tooltip Custom -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-[10px] text-white rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-10 transition-opacity">
                        <?= $tooltip ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="flex items-center justify-end gap-2 mt-4">
        <span class="text-[10px] text-slate-500 font-bold uppercase">Menos</span>
        <div class="flex gap-1">
            <div class="w-3 h-3 rounded-sm bg-white/5"></div>
            <div class="w-3 h-3 rounded-sm bg-indigo-900/40"></div>
            <div class="w-3 h-3 rounded-sm bg-indigo-700/60"></div>
            <div class="w-3 h-3 rounded-sm bg-indigo-500/80"></div>
            <div class="w-3 h-3 rounded-sm bg-primary border border-white/10"></div>
        </div>
        <span class="text-[10px] text-slate-500 font-bold uppercase">Más</span>
    </div>
</section>

<!-- LIST SECTION -->
<div class="space-y-4">
    <h3 class="text-[10px] text-slate-500 font-bold mb-4 uppercase tracking-widest">Planes Recientes</h3>
    <?php if (empty($plans)): ?>
        <div class="glass-panel text-center py-10">
            <p class="text-slate-500 italic">No hay registros.</p>
        </div>
    <?php else: ?>
        <?php foreach ($plans as $plan): 
            $rate = $plan['task_count'] > 0 ? round(($plan['completed_count'] / $plan['task_count']) * 100) : 0;
            $color = $rate >= 100 ? 'text-primary' : ($rate >= 50 ? 'text-blue-400' : 'text-slate-500');
        ?>
            <div class="glass-panel hover:border-white/20 transition-all group">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-xl text-primary shrink-0">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg group-hover:text-primary transition-colors">
                                <?= htmlspecialchars($plan['plan_name'] ?: 'Planificación') ?>
                            </h3>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 font-medium">
                                <span><i class="fa-solid fa-calendar-day mr-1"></i> <?= date('d M, Y', strtotime($plan['plan_date'])) ?></span>
                                <span><i class="fa-solid fa-list-check mr-1"></i> <?= $plan['task_count'] ?> Tareas</span>
                                <span><i class="fa-solid fa-check-double mr-1"></i> <?= $plan['completed_count'] ?> Completadas</span>
                                <span class="<?= $color ?> font-bold"><i class="fa-solid fa-chart-line mr-1"></i> <?= $rate ?>%</span>
                            </div>
                        </div>
                    </div>
                    <a href="editar_plan.php?id=<?= $plan['id'] ?>" class="bg-white/5 hover:bg-white/10 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition-all">
                        Ver detalles
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
