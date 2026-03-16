<?php
// rutina.php - Rutina Semanal Base
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$user_id = getCurrentUserId();
$active_page = 'rutina';
$page_title = 'Rutina Semanal - DayTraker';

// Obtener todas las tareas de la rutina del usuario
$stmt = $pdo->prepare("
    SELECT wr.*, t.name as task_name 
    FROM DT_weekly_routine wr 
    JOIN DT_tasks t ON wr.task_id = t.id 
    WHERE wr.user_id = ? 
    ORDER BY wr.day_of_week ASC, wr.task_order ASC, t.name ASC
");
$stmt->execute([$user_id]);
$all_routine = $stmt->fetchAll();

// Organizar por día
$routine_by_day = array_fill(0, 7, []);
foreach ($all_routine as $item) {
    $routine_by_day[$item['day_of_week']][] = $item;
}

$days = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

$extra_css = '
<style>
    .sortable-ghost { opacity: 0.4; background: rgba(99, 102, 241, 0.2) !important; border: 1px dashed #6366f1 !important; }
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
</style>
';

require_once 'includes/header.php';
?>

<header class="mb-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold mb-1 shadow-indigo-500/20">Rutina Semanal <i class="fa-solid fa-repeat text-primary ml-1"></i></h1>
            <p class="text-slate-400">Define y ordena tus tareas para cada día.</p>
        </div>
        <div id="save-order-status" class="hidden animate-pulse">
            <span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-black uppercase rounded-full border border-primary/20">Guardando orden...</span>
        </div>
    </div>
</header>

<div class="grid grid-cols-1 md:grid-cols-7 gap-4 mb-8" id="day-tabs">
    <?php foreach ($days as $idx => $name): ?>
        <button onclick="showDay(<?= $idx ?>)" data-day="<?= $idx ?>" class="day-tab p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all hover:bg-white/[0.05] flex flex-col items-center gap-1 group relative">
            <span class="text-[10px] uppercase font-black text-slate-500 group-hover:text-primary transition-colors"><?= substr($name, 0, 3) ?></span>
            <span class="text-lg font-black text-white"><?= count($routine_by_day[$idx]) ?></span>
            <?php if (count($routine_by_day[$idx]) > 0): ?>
                <div class="absolute -top-1 -right-1 w-2 h-2 bg-primary rounded-full shadow-[0_0_8px_#6366f1]"></div>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
</div>

<div id="day-containers">
    <?php foreach ($days as $idx => $name): ?>
        <div id="container-<?= $idx ?>" class="day-container hidden space-y-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-black text-white"><?= $name ?></h3>
                <button onclick="openModal(<?= $idx ?>)" class="btn-primary flex items-center gap-2 text-xs">
                    <i class="fa-solid fa-plus"></i> Añadir Tareas
                </button>
            </div>

            <div class="space-y-3 sortable-list" id="list-<?= $idx ?>" data-day="<?= $idx ?>">
                <?php if (empty($routine_by_day[$idx])): ?>
                    <p class="text-slate-500 italic text-center py-10 bg-white/[0.02] rounded-2xl border border-dashed border-white/10 no-sort">No hay tareas recurrentes para este día.</p>
                <?php else: ?>
                    <?php foreach ($routine_by_day[$idx] as $item): ?>
                        <div class="flex items-center gap-4 p-4 rounded-xl border border-white/5 bg-white/[0.02] group transition-all hover:bg-white/[0.04]" data-id="<?= $item['id'] ?>">
                            <div class="drag-handle p-2 text-slate-600 hover:text-white transition-colors">
                                <i class="fa-solid fa-grip-vertical"></i>
                            </div>
                            <div class="p-2.5 bg-primary/10 rounded-lg text-primary shrink-0">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-white"><?= htmlspecialchars($item['task_name']) ?></h4>
                            </div>
                            <button onclick="removeTask(<?= $item['id'] ?>, <?= $idx ?>)" class="opacity-0 group-hover:opacity-100 p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Añadir Tareas -->
<div id="routineModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-lg animate-in fade-in zoom-in duration-300 max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-white uppercase">Añadir Tareas</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white text-3xl leading-none">&times;</button>
        </div>
        
        <form id="routineForm" class="space-y-4 text-left flex-1 overflow-y-auto pr-2">
            <input type="hidden" id="modalDay">
            <div id="modal-tasks-container" class="space-y-3"></div>
            <button type="button" onclick="addModalTaskRow()" class="w-full py-3 border-2 border-dashed border-white/10 rounded-xl text-slate-500 font-bold hover:border-primary/50 hover:text-primary transition-all text-[10px] uppercase tracking-widest mt-4">
                <i class="fa-solid fa-plus mr-2"></i>Nueva Tarea
            </button>
        </form>

        <div class="flex gap-3 mt-8 pt-4 border-t border-white/5">
            <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-xl font-bold bg-white/5 text-slate-400 hover:bg-white/10 transition-all">Cancelar</button>
            <button type="button" onclick="saveRoutine()" class="flex-1 btn-primary">Guardar Todo</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
let currentDay = new Date().getDay();
const sortables = [];

function initSortables() {
    document.querySelectorAll('.sortable-list').forEach(list => {
        const s = new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            filter: '.no-sort',
            onEnd: async function() {
                const day = list.dataset.day;
                const order = Array.from(list.children)
                    .filter(el => el.dataset.id)
                    .map(el => el.dataset.id);
                
                await updateTaskOrder(order);
            }
        });
        sortables.push(s);
    });
}

async function updateTaskOrder(order) {
    const statusEl = document.getElementById('save-order-status');
    statusEl.classList.remove('hidden');
    
    try {
        const response = await fetch('api_save_routine.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'reorder', order: order }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (!result.success) alert("Error al guardar el orden");
    } catch (e) { console.error(e); }
    
    setTimeout(() => { statusEl.classList.add('hidden'); }, 1000);
}

function showDay(idx) {
    document.querySelectorAll('.day-container').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(`container-${idx}`).classList.remove('hidden');
    document.querySelector(`.day-tab[data-day="${idx}"]`).classList.add('active');
    currentDay = idx;
}

function openModal(day) {
    document.getElementById('modalDay').value = day;
    document.getElementById('modal-tasks-container').innerHTML = '';
    addModalTaskRow();
    document.getElementById('routineModal').classList.remove('hidden');
}

function closeModal() { document.getElementById('routineModal').classList.add('hidden'); }

function addModalTaskRow() {
    const container = document.getElementById('modal-tasks-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2 relative group-task';
    div.innerHTML = `
        <div class="flex-1 relative">
            <input type="text" class="form-control task-input" placeholder="Nombre de la tarea..." required autocomplete="off">
            <div class="suggestions-list hidden"></div>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="p-3 text-red-500 hover:bg-red-500/10 rounded-xl transition-all"><i class="fa-solid fa-times"></i></button>
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

async function saveRoutine() {
    const day = document.getElementById('modalDay').value;
    const inputs = document.querySelectorAll('#modal-tasks-container .task-input');
    const tasks = Array.from(inputs).map(i => i.value.trim()).filter(v => v !== '');
    if (tasks.length === 0) return;
    try {
        const response = await fetch('api_save_routine.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'add', day_of_week: day, tasks: tasks }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (result.success) location.reload();
        else alert(result.error);
    } catch (e) { console.error(e); }
}

async function removeTask(id, day) {
    if (!confirm('¿Eliminar esta tarea de la rutina?')) return;
    try {
        const response = await fetch('api_save_routine.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete', id: id }),
            headers: { 'Content-Type': 'application/json' }
        });
        if (response.ok) location.reload();
    } catch (e) { console.error(e); }
}

document.addEventListener('DOMContentLoaded', () => {
    showDay(currentDay);
    initSortables();
});
</script>

<?php require_once 'includes/footer.php'; ?>
