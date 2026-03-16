<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

// Obtener altura y peso objetivo para el IMC y progreso
$stmtUser = $pdo->prepare("SELECT height, target_weight FROM GT_users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user_data = $stmtUser->fetch();
$altura = $user_data['height'] ?: 0;
$target_weight = $user_data['target_weight'] ?: 0;

$success = '';
$error = '';

// 1. Manejo de Acciones (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AGREGAR o EDITAR
    if (isset($_POST['save_weight'])) {
        $weight_id = $_POST['weight_id'] ?? null;
        $weight = floatval($_POST['weight']);
        $date = $_POST['log_date'];

        if ($weight > 0 && !empty($date)) {
            try {
                if ($weight_id) {
                    // Editar
                    $stmt = $pdo->prepare("UPDATE GT_weight_logs SET weight = ?, log_date = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$weight, $date, $weight_id, $user_id]);
                    $success = "Registro actualizado.";
                } else {
                    // Agregar
                    $stmt = $pdo->prepare("INSERT INTO GT_weight_logs (user_id, weight, log_date) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $weight, $date]);
                    $success = "Nuevo peso registrado.";
                }
                
                // Actualizar el peso actual del usuario (el más reciente)
                $stmtLast = $pdo->prepare("SELECT weight FROM GT_weight_logs WHERE user_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
                $stmtLast->execute([$user_id]);
                $last_weight = $stmtLast->fetchColumn();
                if ($last_weight) {
                    $pdo->prepare("UPDATE GT_users SET weight = ? WHERE id = ?")->execute([$last_weight, $user_id]);
                }

            } catch (Exception $e) {
                $error = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }

    // ELIMINAR
    if (isset($_POST['delete_weight'])) {
        $weight_id = $_POST['weight_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM GT_weight_logs WHERE id = ? AND user_id = ?");
            $stmt->execute([$weight_id, $user_id]);
            $success = "Registro eliminado.";
            
            // Re-sincronizar peso actual
            $stmtLast = $pdo->prepare("SELECT weight FROM GT_weight_logs WHERE user_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
            $stmtLast->execute([$user_id]);
            $last_weight = $stmtLast->fetchColumn();
            $pdo->prepare("UPDATE GT_users SET weight = ? WHERE id = ?")->execute([$last_weight ?: null, $user_id]);
            
        } catch (Exception $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    }
}

// 2. Obtener Historial Completo
$stmtHist = $pdo->prepare("SELECT id, weight, log_date FROM GT_weight_logs WHERE user_id = ? ORDER BY log_date DESC, id DESC");
$stmtHist->execute([$user_id]);
$history = $stmtHist->fetchAll();

// 3. Estadísticas Básicas
$stats = [
    'actual' => $history[0]['weight'] ?? '--',
    'inicial' => (count($history) > 0) ? end($history)['weight'] : '--',
    'min' => '--',
    'max' => '--',
    'cambio' => 0
];

if (!empty($history)) {
    $weights = array_column($history, 'weight');
    $stats['min'] = min($weights);
    $stats['max'] = max($weights);
    if ($stats['inicial'] !== '--' && $stats['actual'] !== '--') {
        $stats['cambio'] = round($stats['actual'] - $stats['inicial'], 1);
    }
}

$imc = calculateBMI($stats['actual'], $altura);
$bmi_info = getBMICategory($imc);

$active_page = 'peso';
$page_title = 'Control de Peso - GymTraker';

// Datos para el gráfico (invertir el historial para que sea cronológico para los últimos 20 registros)
$chart_history = array_reverse(array_slice($history, 0, 20));
$weight_labels = [];
$weight_values = [];
foreach ($chart_history as $log) {
    $weight_labels[] = date('d/m', strtotime($log['log_date']));
    $weight_values[] = $log['weight'];
}

require_once 'includes/header.php';
?>

<!-- Modal: Agregar/Editar Peso -->
<div id="modalPeso" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm p-4">
    <div class="flex min-h-full items-center justify-center">
        <div class="glass-panel w-full max-w-md bg-[#1e293b] border border-white/10 shadow-2xl relative">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
                <h2 id="modalTitle" class="text-xl font-black text-white">Registrar Peso</h2>
                <button type="button" class="text-slate-400 hover:text-white text-3xl leading-none" onclick="closeModal()">&times;</button>
            </div>

            <form action="peso" method="POST" class="space-y-6">
                <input type="hidden" name="weight_id" id="edit_id">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Peso (kg)</label>
                    <input type="number" step="0.1" name="weight" id="field_weight" class="form-control" placeholder="Ej: 75.5" required>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Fecha</label>
                    <input type="date" name="log_date" id="field_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit" name="save_weight" class="w-full btn-primary uppercase tracking-widest">Guardar Registro</button>
            </form>
        </div>
    </div>
</div>

<header class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-3xl font-extrabold mb-1">Control de Peso <i class="fa-solid fa-chart-line text-primary ml-1"></i></h1>
        <p class="text-slate-400">Tu evolución física detallada.</p>
    </div>
    <button onclick="openAddModal()" class="w-full sm:w-auto btn-primary"><i class="fa-solid fa-plus mr-2"></i>Nuevo Registro</button>
</header>

<?php if ($target_weight > 0 && $stats['actual'] !== '--'): 
    $diff_to_target = abs($stats['actual'] - $target_weight);
    $total_diff = abs($stats['inicial'] - $target_weight);
    $progress_perc = $total_diff > 0 ? (1 - ($diff_to_target / $total_diff)) * 100 : 0;
    if ($progress_perc < 0) $progress_perc = 0;
    if ($progress_perc > 100) $progress_perc = 100;
?>
    <div class="glass-panel mb-8 bg-gradient-to-r from-blue-500/10 to-primary/10 border-blue-500/20">
        <div class="flex justify-between items-center mb-3">
            <span class="text-xs font-black text-white uppercase tracking-widest">Progreso hacia tu meta de <?= $target_weight ?> kg</span>
            <span class="text-xs font-black text-primary"><?= round($progress_perc) ?>%</span>
        </div>
        <div class="h-3 bg-white/5 rounded-full overflow-hidden border border-white/5 p-0.5">
            <div class="h-full bg-gradient-to-r from-blue-500 to-primary rounded-full shadow-[0_0_15px_rgba(59,130,246,0.4)] transition-all duration-1000" style="width: <?= $progress_perc ?>%;"></div>
        </div>
        <p class="text-[10px] text-slate-500 mt-3 font-bold uppercase tracking-wide">Faltan <?= round($diff_to_target, 1) ?> kg para alcanzar tu objetivo. ¡Tú puedes!</p>
    </div>
<?php endif; ?>

<?php if($success): ?>
    <div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="glass-panel text-center">
        <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Peso Actual</span>
        <span class="text-2xl font-black text-white"><?= $stats['actual'] ?> <small class="text-xs opacity-50">kg</small></span>
    </div>
    <div class="glass-panel text-center">
        <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Cambio Total</span>
        <span class="text-2xl font-black <?= $stats['cambio'] <= 0 ? 'text-primary' : 'text-red-400' ?>">
            <?= $stats['cambio'] > 0 ? '+' : '' ?><?= $stats['cambio'] ?> <small class="text-xs opacity-50">kg</small>
        </span>
    </div>
    <div class="glass-panel text-center">
        <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Mínimo</span>
        <span class="text-2xl font-black text-blue-400"><?= $stats['min'] ?> <small class="text-xs opacity-50">kg</small></span>
    </div>
    <div class="glass-panel text-center">
        <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Máximo</span>
        <span class="text-2xl font-black text-red-400"><?= $stats['max'] ?> <small class="text-xs opacity-50">kg</small></span>
    </div>
    <div class="glass-panel text-center col-span-2 md:col-span-1 flex flex-col justify-center p-4">
        <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2">Tu IMC</span>
        <div class="relative h-20 flex items-center justify-center overflow-hidden mb-1">
            <canvas id="bmiGauge" class="absolute inset-0"></canvas>
            <div class="absolute bottom-1 text-center">
                <span class="text-xl font-black text-white"><?= $imc > 0 ? $imc : '--' ?></span>
            </div>
        </div>
        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-white/5 self-center" style="color: <?= $bmi_info['color'] ?>;">
            <?= $bmi_info['label'] ?>
        </span>
    </div>
</div>

<!-- Gráfico de Evolución -->
<div class="glass-panel mb-8">
    <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-6 pb-4 border-b border-white/5">Gráfico de Evolución</h3>
    <div class="w-full h-[300px] relative">
        <?php if (empty($weight_values)): ?>
            <div class="absolute inset-0 flex items-center justify-center text-slate-500 italic text-sm">No hay suficientes datos para mostrar el gráfico.</div>
        <?php else: ?>
            <canvas id="weightHistoryChart"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="glass-panel overflow-hidden">
    <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-6 pb-4 border-b border-white/5">Historial Completo</h3>
    
    <?php if (empty($history)): ?>
        <p class="text-center text-slate-500 italic py-8">No hay registros de peso todavía.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] text-slate-500 font-black uppercase tracking-widest border-b border-white/5">
                        <th class="py-4 px-2">Fecha</th>
                        <th class="py-4 px-2 text-center">Peso</th>
                        <th class="py-4 px-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($history as $row): ?>
                        <tr class="group hover:bg-white/5 transition-colors">
                            <td class="py-4 px-2 text-sm font-bold text-slate-300"><?= date('d M, Y', strtotime($row['log_date'])) ?></td>
                            <td class="py-4 px-2 text-center text-lg font-black text-white"><?= $row['weight'] ?> kg</td>
                            <td class="py-4 px-2 text-right space-x-3">
                                <button onclick="openEditModal(<?= $row['id'] ?>, <?= $row['weight'] ?>, '<?= $row['log_date'] ?>')" class="text-blue-400 hover:text-blue-300 text-xs font-black uppercase tracking-widest">Editar</button>
                                <form action="peso" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro?')">
                                    <input type="hidden" name="weight_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="delete_weight" class="text-red-500/50 hover:text-red-500 text-xs font-black uppercase tracking-widest">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const modal = document.getElementById('modalPeso');
    const modalTitle = document.getElementById('modalTitle');
    const editId = document.getElementById('edit_id');
    const fieldWeight = document.getElementById('field_weight');
    const fieldDate = document.getElementById('field_date');

    function openAddModal() {
        modalTitle.textContent = "Registrar Peso";
        editId.value = "";
        fieldWeight.value = "";
        fieldDate.value = "<?= date('Y-m-d') ?>";
        modal.classList.remove('hidden');
    }

    function openEditModal(id, weight, date) {
        modalTitle.textContent = "Editar Registro";
        editId.value = id;
        fieldWeight.value = weight;
        fieldDate.value = date;
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    // Inicializar Gráfico
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('weightHistoryChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($weight_labels) ?>,
                    datasets: [{
                        label: 'Peso (kg)',
                        data: <?= json_encode($weight_values) ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#22c55e',
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

        // Gráfico de IMC (Gauge)
        const ctxBMI = document.getElementById('bmiGauge');
        if (ctxBMI) {
            const imcValue = <?= $imc ?>;
            let normalized = imcValue;
            if (normalized < 15) normalized = 15;
            if (normalized > 40) normalized = 40;
            
            new Chart(ctxBMI, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [normalized - 15, 40 - normalized],
                        backgroundColor: ['<?= $bmi_info['color'] ?>', 'rgba(255,255,255,0.05)'],
                        borderWidth: 0,
                        circumference: 180,
                        rotation: 270,
                        cutout: '75%',
                        borderRadius: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: { bottom: 10 }
                    },
                    plugins: { legend: { display: false }, tooltip: { enabled: false } }
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
