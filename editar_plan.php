<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

$plan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$plan_id) { header("Location: historial"); exit; }

$stmt = $pdo->prepare("SELECT * FROM DT_daily_plans WHERE id = ? AND user_id = ?");
$stmt->execute([$plan_id, $user_id]);
$plan = $stmt->fetch();
if (!$plan) { header("Location: historial"); exit; }

$stmt = $pdo->prepare("
    SELECT tl.*, t.name as task_name 
    FROM DT_task_logs tl 
    JOIN DT_tasks t ON tl.task_id = t.id 
    WHERE tl.plan_id = ? 
    ORDER BY tl.id ASC
");
$stmt->execute([$plan_id]);
$task_logs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
    $fecha_seleccionada = !empty($_POST['plan_date']) ? $_POST['plan_date'] : date('Y-m-d');
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE DT_daily_plans SET plan_date = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$fecha_seleccionada, $plan_id, $user_id]);
        $stmt = $pdo->prepare("DELETE FROM DT_task_logs WHERE plan_id = ?");
        $stmt->execute([$plan_id]);

        if (isset($_POST['tareas'])) {
            foreach ($_POST['tareas'] as $tarea) {
                $task_name = trim($tarea['name']);
                if (empty($task_name)) continue;
                $stmtLib = $pdo->prepare("INSERT IGNORE INTO DT_tasks (name, category_id) VALUES (?, 2)");
                $stmtLib->execute([$task_name]);
                $stmtTaskId = $pdo->prepare("SELECT id FROM DT_tasks WHERE name = ?");
                $stmtTaskId->execute([$task_name]);
                $task_id = $stmtTaskId->fetchColumn();

                if ($task_id) {
                    $status = $tarea['status'] ?? 'pending';
                    $stmt = $pdo->prepare("INSERT INTO DT_task_logs (plan_id, task_id, status) VALUES (?, ?, ?)");
                    $stmt->execute([$plan_id, $task_id, $status]);
                }
            }
        }
        $pdo->commit();
        header("Location: historial?success=edit");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

$active_page = 'historial';
$page_title = 'Editar Planificación - DayTraker';
require_once 'includes/header.php';
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Editar Plan <i class="fa-solid fa-pen-to-square text-primary ml-1"></i></h1>
    <p class="text-slate-400">Modifica las tareas del <?= date('d/m/Y', strtotime($plan['plan_date'])) ?>.</p>
</header>

<div class="max-w-4xl mx-auto">
    <form action="editar_plan.php?id=<?= $plan_id ?>" method="POST" id="plan-form" class="space-y-8">
        <div class="glass-panel">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Fecha del Plan</label>
            <input type="date" name="plan_date" class="form-control" value="<?= $plan['plan_date'] ?>" required>
        </div>

        <div id="tareas-container" class="space-y-4">
            <?php foreach($task_logs as $idx => $log): ?>
                <div class="glass-panel flex items-center gap-4 py-4">
                    <div class="flex-1 relative">
                        <input type="text" name="tareas[<?= $idx ?>][name]" class="bg-transparent border-none text-lg font-black text-white focus:outline-none placeholder-white/20 w-full task-input" value="<?= htmlspecialchars($log['task_name']) ?>" required autocomplete="off">
                        <div class="suggestions-list hidden"></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <select name="tareas[<?= $idx ?>][status]" class="form-control py-2 text-xs w-32">
                            <option value="pending" <?= $log['status'] == 'pending' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="completed" <?= $log['status'] == 'completed' ? 'selected' : '' ?>>Completada</option>
                        </select>
                        <button type="button" class="text-red-500/50 hover:text-red-500 transition-colors" onclick="this.closest('.glass-panel').remove()">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-col gap-4">
            <button type="button" onclick="addManualTask()" class="w-full py-4 border-2 border-dashed border-white/10 rounded-2xl text-slate-400 font-bold hover:border-primary hover:text-primary transition-all uppercase tracking-widest text-xs"><i class="fa-solid fa-plus mr-2"></i>Añadir Tarea</button>
            <button type="submit" name="update_plan" class="btn-primary w-full py-5 text-xl uppercase tracking-[4px] shadow-2xl shadow-primary/20"><i class="fa-solid fa-floppy-disk mr-3"></i>Guardar Cambios</button>
            <a href="historial" class="text-center text-slate-500 hover:text-white text-sm font-bold uppercase tracking-widest mt-2">Cancelar</a>
        </div>
    </form>
</div>

<style>
    .suggestions-list {
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 0.5rem;
        position: absolute;
        z-index: 50;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }
    .suggestion-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .suggestion-item:hover { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
</style>

<script>
let taskCount = <?= count($task_logs) ?>;
function addManualTask() {
    const container = document.getElementById('tareas-container');
    const idx = taskCount++;
    const div = document.createElement('div');
    div.className = 'glass-panel flex items-center gap-4 py-4';
    div.innerHTML = `
        <div class="flex-1 relative">
            <input type="text" name="tareas[${idx}][name]" class="bg-transparent border-none text-lg font-black text-white focus:outline-none placeholder-white/20 w-full task-input" placeholder="Nombre de la Tarea..." required autocomplete="off">
            <div class="suggestions-list hidden"></div>
        </div>
        <div class="flex items-center gap-4">
            <select name="tareas[${idx}][status]" class="form-control py-2 text-xs w-32"><option value="pending">Pendiente</option><option value="completed">Completada</option></select>
            <button type="button" class="text-red-500/50 hover:text-red-500 transition-colors" onclick="this.closest('.glass-panel').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
    `;
    container.appendChild(div);
    initAutocomplete(div.querySelector('.task-input'));
}
function initAutocomplete(input) {
    const suggestionsList = input.nextElementSibling;
    let debounceTimer;
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = input.value.trim();
        if (query.length < 1) { suggestionsList.classList.add('hidden'); return; }
        debounceTimer = setTimeout(() => {
            fetch(`api_tareas?q=${encodeURIComponent(query)}`).then(res => res.json()).then(data => {
                if (data && data.length > 0) {
                    suggestionsList.innerHTML = '';
                    data.forEach(name => {
                        const item = document.createElement('div');
                        item.className = 'suggestion-item';
                        item.textContent = name;
                        item.onclick = () => { input.value = name; suggestionsList.classList.add('hidden'); };
                        suggestionsList.appendChild(item);
                    });
                    suggestionsList.classList.remove('hidden');
                } else { suggestionsList.classList.add('hidden'); }
            });
        }, 300);
    });
}
document.querySelectorAll('.task-input').forEach(initAutocomplete);
</script>

<?php require_once 'includes/footer.php'; ?>
