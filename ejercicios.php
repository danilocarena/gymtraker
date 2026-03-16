<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$active_page = 'ejercicios';
$page_title = 'Catálogo de Ejercicios';

// Obtener todos los ejercicios
$exercises = $pdo->query("SELECT * FROM GT_exercises ORDER BY muscle_group ASC, name ASC")->fetchAll();

// Obtener grupos musculares únicos para el filtro
$muscle_groups = $pdo->query("SELECT DISTINCT muscle_group FROM GT_exercises ORDER BY muscle_group ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- LÓGICA PARA AÑADIR A RUTINA ---
$user_id = getCurrentUserId();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_routine'])) {
    $routine_day_id = (int)$_POST['day_id'];
    $exercise_name = $_POST['exercise_name'];

    if ($routine_day_id > 0 && !empty($exercise_name)) {
        try {
            // Obtener el orden máximo actual para ese día
            $stmtOrder = $pdo->prepare("SELECT COALESCE(MAX(exercise_order), 0) FROM GT_routine_exercises WHERE routine_day_id = ?");
            $stmtOrder->execute([$routine_day_id]);
            $new_order = $stmtOrder->fetchColumn() + 1;

            $stmtAdd = $pdo->prepare("INSERT INTO GT_routine_exercises (routine_day_id, exercise_name, exercise_order) VALUES (?, ?, ?)");
            if ($stmtAdd->execute([$routine_day_id, $exercise_name, $new_order])) {
                $success = "¡" . htmlspecialchars($exercise_name) . " añadido a tu rutina!";
            }
        } catch (Exception $e) {
            $error = "Error al añadir: " . $e->getMessage();
        }
    }
}

// Obtener todas las rutinas del usuario con sus días para el modal
$stmtRoutines = $pdo->prepare("
    SELECT r.id as routine_id, r.name as routine_name, rd.id as day_id, rd.day_name 
    FROM GT_routines r 
    JOIN GT_routine_days rd ON r.id = rd.routine_id 
    WHERE r.user_id = ? 
    ORDER BY r.name, rd.day_order
");
$stmtRoutines->execute([$user_id]);
$routines_raw = $stmtRoutines->fetchAll();

$user_routines = [];
foreach ($routines_raw as $row) {
    if (!isset($user_routines[$row['routine_id']])) {
        $user_routines[$row['routine_id']] = [
            'name' => $row['routine_name'],
            'days' => []
        ];
    }
    $user_routines[$row['routine_id']]['days'][] = [
        'id' => $row['day_id'],
        'name' => $row['day_name']
    ];
}

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <header class="mb-10">
        <h2 class="text-4xl font-black text-white tracking-tight uppercase">Catálogo de Ejercicios</h2>
        <p class="text-slate-400">Aprende la técnica correcta y descubre nuevos movimientos.</p>
    </header>

    <?php if ($success): ?>
        <div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> <?= $success ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-xmark"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Filtros y Búsqueda -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="md:col-span-2 relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="exerciseSearch" placeholder="Buscar ejercicio..." class="form-control pl-10" onkeyup="filterExercises()">
        </div>
        <div>
            <select id="muscleFilter" class="form-control" onchange="filterExercises()">
                <option value="">Todos los grupos</option>
                <?php foreach ($muscle_groups as $group): ?>
                    <option value="<?= htmlspecialchars($group) ?>"><?= htmlspecialchars($group) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Lista de Ejercicios -->
    <div id="exerciseGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($exercises as $ex): ?>
            <div class="glass-panel flex flex-col justify-between hover:border-primary/30 transition-all duration-300 group exercise-card" 
                 data-name="<?= strtolower(htmlspecialchars($ex['name'])) ?>" 
                 data-muscle="<?= htmlspecialchars($ex['muscle_group']) ?>">
                
                <div>
                    <div class="flex items-start justify-between mb-4">
                        <span class="px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
                            <?= htmlspecialchars($ex['muscle_group']) ?>
                        </span>
                        <?php if ($ex['category']): ?>
                            <span class="text-[10px] text-slate-500 font-bold uppercase"><?= htmlspecialchars($ex['category']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="text-xl font-bold text-white mb-2 group-hover:text-primary transition-colors">
                        <?= htmlspecialchars($ex['name']) ?>
                    </h3>
                    
                    <?php if ($ex['description']): ?>
                        <p class="text-slate-400 text-sm line-clamp-2 mb-4">
                            <?= htmlspecialchars($ex['description']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="mt-6 pt-4 border-t border-white/5 flex flex-col gap-2">
                    <a href="https://www.youtube.com/results?search_query=tecnica+<?= urlencode($ex['name']) ?>" 
                       target="_blank" 
                       class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-red-600/10 text-red-500 border border-red-600/20 hover:bg-red-600 hover:text-white transition-all font-bold text-xs uppercase tracking-widest">
                        <i class="fa-brands fa-youtube text-lg"></i>
                        Ver Técnica
                    </a>
                    <button onclick='openAddModal(<?= json_encode($ex["name"]) ?>)' 
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-white/5 text-slate-300 border border-white/10 hover:bg-primary/20 hover:text-primary hover:border-primary/30 transition-all font-bold text-[10px] uppercase tracking-widest">
                        <i class="fa-solid fa-plus text-xs"></i>
                        Añadir a Rutina
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="noResults" class="hidden text-center py-20">
        <i class="fa-solid fa-dumbbell text-slate-700 text-5xl mb-4"></i>
        <p class="text-slate-500 font-bold">No se encontraron ejercicios con esos filtros.</p>
    </div>
</div>

<!-- Modal: Añadir a Rutina -->
<div id="addRoutineModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/80 backdrop-blur-md p-4 flex items-center justify-center">
    <div class="glass-panel w-full max-w-sm bg-[#1e293b] border border-white/10 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
            <h2 class="text-xl font-black text-white uppercase tracking-tighter">Añadir a Rutina</h2>
            <button type="button" class="text-slate-400 hover:text-white text-3xl leading-none" onclick="closeAddModal()">&times;</button>
        </div>

        <p id="targetExerciseName" class="text-primary font-bold text-sm mb-6 text-center"></p>

        <?php if (empty($user_routines)): ?>
            <div class="text-center p-6">
                <p class="text-slate-400 text-sm mb-4">No tienes rutinas creadas.</p>
                <a href="rutinas" class="btn-primary block no-underline text-xs">Crear mi primera rutina</a>
            </div>
        <?php else: ?>
            <form action="ejercicios" method="POST" class="space-y-6 text-left">
                <input type="hidden" name="add_to_routine" value="1">
                <input type="hidden" name="exercise_name" id="modalExName">
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Selecciona la Rutina</label>
                    <select id="routineSelect" class="form-control" onchange="updateDaysList()">
                        <option value="">Elegir rutina...</option>
                        <?php foreach ($user_routines as $id => $r): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="daySelectionWrapper" class="hidden">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Selecciona el Día</label>
                    <select name="day_id" id="daySelect" class="form-control" required>
                        <option value="">Elegir día...</option>
                    </select>
                </div>

                <button type="submit" id="confirmAddBtn" class="w-full btn-primary uppercase tracking-widest mt-4 opacity-50 cursor-not-allowed" disabled>Añadir Ejercicio</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function filterExercises() {
    const searchVal = document.getElementById('exerciseSearch').value.toLowerCase();
    const muscleVal = document.getElementById('muscleFilter').value;
    const cards = document.querySelectorAll('.exercise-card');
    let hasResults = false;

    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const muscle = card.getAttribute('data-muscle');
        
        const matchesSearch = name.includes(searchVal);
        const matchesMuscle = muscleVal === "" || muscle === muscleVal;

        if (matchesSearch && matchesMuscle) {
            card.classList.remove('hidden');
            hasResults = true;
        } else {
            card.classList.add('hidden');
        }
    });

    document.getElementById('noResults').classList.toggle('hidden', hasResults);
    document.getElementById('exerciseGrid').classList.toggle('hidden', !hasResults);
}

const userRoutines = <?= json_encode($user_routines) ?>;

function openAddModal(name) {
    document.getElementById('modalExName').value = name;
    document.getElementById('targetExerciseName').textContent = name;
    document.getElementById('addRoutineModal').classList.remove('hidden');
    document.getElementById('addRoutineModal').classList.add('flex');
}

function closeAddModal() {
    document.getElementById('addRoutineModal').classList.add('hidden');
    document.getElementById('addRoutineModal').classList.remove('flex');
    document.getElementById('routineSelect').value = '';
    document.getElementById('daySelectionWrapper').classList.add('hidden');
    document.getElementById('confirmAddBtn').disabled = true;
    document.getElementById('confirmAddBtn').classList.add('opacity-50', 'cursor-not-allowed');
}

function updateDaysList() {
    const routineId = document.getElementById('routineSelect').value;
    const daySelect = document.getElementById('daySelect');
    const wrapper = document.getElementById('daySelectionWrapper');
    const confirmBtn = document.getElementById('confirmAddBtn');

    daySelect.innerHTML = '<option value="">Elegir día...</option>';
    
    if (routineId && userRoutines[routineId]) {
        userRoutines[routineId].days.forEach(day => {
            const opt = document.createElement('option');
            opt.value = day.id;
            opt.textContent = day.name;
            daySelect.appendChild(opt);
        });
        wrapper.classList.remove('hidden');
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        wrapper.classList.add('hidden');
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
