<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

// Procesar el formulario de "Guardar Plan"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $fecha_seleccionada = !empty($_POST['plan_date']) ? $_POST['plan_date'] : date('Y-m-d');
    $plan_name = "Plan Personalizado";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO DT_daily_plans (user_id, plan_date, plan_name) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $fecha_seleccionada, $plan_name]);
        $plan_id = $pdo->lastInsertId();

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
        header("Location: ./?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al guardar: " . $e->getMessage();
    }
}

$active_page = 'plan_nuevo';
$page_title = 'Nueva Planificación - DayTraker';
require_once 'includes/header.php';
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Nueva Planificación <i class="fa-solid fa-calendar-day text-primary ml-1"></i></h1>
    <p class="text-slate-400">Crea un registro de tareas para un día específico.</p>
</header>

<div class="max-w-4xl mx-auto">
    <form action="plan_nuevo" method="POST" id="plan-form" class="space-y-8">
        <div class="glass-panel">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Fecha del Plan</label>
            <input type="date" name="plan_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div id="tareas-container" class="space-y-4">
            <!-- Las tareas se añaden dinámicamente -->
        </div>

        <div class="flex flex-col gap-4">
            <button type="button" onclick="addManualTask()" class="w-full py-4 border-2 border-dashed border-white/10 rounded-2xl text-slate-400 font-bold hover:border-primary hover:text-primary transition-all uppercase tracking-widest text-xs"><i class="fa-solid fa-plus mr-2"></i>Añadir Tarea</button>
            <button type="submit" name="save_plan" class="btn-primary w-full py-5 text-xl uppercase tracking-[4px] shadow-2xl shadow-primary/20"><i class="fa-solid fa-check-double mr-3"></i>Guardar Planificación</button>
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
let taskCount = 0;

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
            <select name="tareas[${idx}][status]" class="form-control py-2 text-xs w-32">
                <option value="pending">Pendiente</option>
                <option value="completed">Completada</option>
            </select>
            <button type="button" class="text-red-500/50 hover:text-red-500 transition-colors" onclick="this.closest('.glass-panel').remove()">
                <i class="fa-solid fa-trash"></i>
            </button>
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
            fetch(`api_tareas?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
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

document.addEventListener('DOMContentLoaded', () => {
    addManualTask();
});
</script>

<?php require_once 'includes/footer.php'; ?>
