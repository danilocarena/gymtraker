<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

// 1. Obtener todas las rutinas del usuario
$stmt = $pdo->prepare("SELECT id, name FROM GT_routines WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute(['user_id' => $user_id]);
$rutinas = $stmt->fetchAll();

// 2. Obtener la rutina actual y su día
$rutina_seleccionada = $_GET['rutina_id'] ?? ($rutinas[0]['id'] ?? null);
$dias_rutina = [];
$ejercicios_dia = [];

if ($rutina_seleccionada) {
    $stmt = $pdo->prepare("SELECT id, day_name FROM GT_routine_days WHERE routine_id = :routine_id ORDER BY day_order ASC, id ASC");
    $stmt->execute(['routine_id' => $rutina_seleccionada]);
    $dias_rutina = $stmt->fetchAll();
        
    $dia_seleccionado = $_GET['dia_id'] ?? ($dias_rutina[0]['id'] ?? null);
    
    if ($dia_seleccionado) {
        $stmt = $pdo->prepare("SELECT id, exercise_name FROM GT_routine_exercises WHERE routine_day_id = :day_id ORDER BY exercise_order ASC, id ASC");
        $stmt->execute(['day_id' => $dia_seleccionado]);
        $ejercicios_dia = $stmt->fetchAll();
    }
}

// 3. Procesar el formulario de "Guardar Sesión"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    $r_id = $_POST['rutina_id'];
    $d_id = $_POST['dia_id'];
    $fecha_seleccionada = !empty($_POST['session_date']) ? $_POST['session_date'] : date('Y-m-d');
    
    $rutina_name = "Rutina Libre";
    $dia_name = "";
    if ($r_id) {
        $stmt = $pdo->prepare("SELECT name FROM GT_routines WHERE id = ?");
        $stmt->execute([$r_id]);
        $rutina_name = $stmt->fetchColumn() ?: $rutina_name;
    }
    if ($d_id) {
        $stmt = $pdo->prepare("SELECT day_name FROM GT_routine_days WHERE id = ?");
        $stmt->execute([$d_id]);
        $dia_name = $stmt->fetchColumn() ?: "";
    }
    $session_name = $dia_name ? "$rutina_name - $dia_name" : $rutina_name;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO GT_workout_sessions (user_id, session_date, routine_id, routine_day_id, session_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $fecha_seleccionada, $r_id ?: null, $d_id ?: null, $session_name]);
        $session_id = $pdo->lastInsertId();

        if (isset($_POST['ejercicios'])) {
            foreach ($_POST['ejercicios'] as $ejercicio) {
                $ej_name = trim($ejercicio['name']);
                if (empty($ej_name)) continue;

                // --- LIBRERÍA GLOBAL: Insertar ejercicio si no existe ---
                $stmtLib = $pdo->prepare("INSERT IGNORE INTO GT_exercises (name) VALUES (?)");
                $stmtLib->execute([$ej_name]);

                if (isset($ejercicio['series'])) {
                    foreach ($ejercicio['series'] as $index => $serie) {
                         $peso = floatval($serie['peso']);
                         $reps = intval($serie['reps']);
                         $comentario = $serie['comentario'] ?? null;
                         if ($peso > 0 || $reps > 0) {
                             $stmt = $pdo->prepare("INSERT INTO GT_session_logs (session_id, exercise_name, sets, reps, weight, comment) VALUES (?, ?, ?, ?, ?, ?)");
                             $stmt->execute([$session_id, $ej_name, ($index + 1), $reps, $peso, $comentario]);
                         }
                    }
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

$active_page = 'sesion';
$page_title = 'Registrar Entrenamiento - GymTraker';
require_once 'includes/header.php';
?>

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
    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }
</style>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Nueva Sesión <i class="fa-solid fa-dumbbell text-primary ml-1"></i></h1>
    <p class="text-slate-400">Registra tu progreso de hoy.</p>
</header>

<div class="max-w-4xl mx-auto">
    <!-- Selector de Rutina -->
    <form action="sesion_nueva" method="GET" class="glass-panel mb-8" id="selector-form">
        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Seleccionar Plan</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <select name="rutina_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- Rutina --</option>
                <?php foreach($rutinas as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $r['id'] == $rutina_seleccionada ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if(!empty($dias_rutina)): ?>
                <select name="dia_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach($dias_rutina as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $dia_seleccionado ? 'selected' : '' ?>><?= htmlspecialchars($d['day_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </form>

    <form action="sesion_nueva" method="POST" id="session-form" class="space-y-8">
        <input type="hidden" name="rutina_id" value="<?= htmlspecialchars($rutina_seleccionada ?? '') ?>">
        <input type="hidden" name="dia_id" value="<?= htmlspecialchars($dia_seleccionado ?? '') ?>">
        
        <div class="glass-panel">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Fecha</label>
            <input type="date" name="session_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div id="ejercicios-container" class="space-y-6">
            <?php if(!empty($ejercicios_dia)): ?>
                <?php foreach($ejercicios_dia as $idx => $ej): ?>
                    <div class="glass-panel border-l-4 border-l-primary/50">
                        <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/5 relative">
                            <div class="w-full">
                                <h3 class="text-lg font-black text-white"><?= htmlspecialchars($ej['exercise_name']) ?></h3>
                                <input type="hidden" name="ejercicios[<?= $idx ?>][name]" value="<?= htmlspecialchars($ej['exercise_name']) ?>">
                            </div>
                        </div>
                        <div id="sets-container-<?= $idx ?>" class="space-y-3">
                            <div class="grid grid-cols-[30px,1fr,1fr,auto] gap-3 items-center set-row">
                                <span class="text-xs font-black text-slate-600 text-center set-num">1</span>
                                <input type="number" step="0.5" name="ejercicios[<?= $idx ?>][series][0][peso]" class="form-control py-2 text-center" placeholder="kg" required>
                                <input type="number" name="ejercicios[<?= $idx ?>][series][0][reps]" class="form-control py-2 text-center" placeholder="reps" required>
                                <button type="button" class="text-red-500/50 hover:text-red-500 text-xl px-2" onclick="removeSet(this)">&times;</button>
                            </div>
                        </div>
                        <button type="button" onclick="addSet(<?= $idx ?>)" class="mt-4 text-xs font-black text-primary uppercase tracking-widest hover:text-white transition-colors">+ Añadir Serie</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="flex flex-col gap-4">
            <button type="button" onclick="addManualExercise()" class="w-full py-4 border-2 border-dashed border-white/10 rounded-2xl text-slate-400 font-bold hover:border-primary hover:text-primary transition-all uppercase tracking-widest text-xs"><i class="fa-solid fa-plus mr-2"></i>Añadir Ejercicio Libre</button>
            <button type="submit" name="save_session" class="btn-primary w-full py-5 text-xl uppercase tracking-[4px] shadow-2xl shadow-primary/20"><i class="fa-solid fa-check-double mr-3"></i>Guardar Entrenamiento</button>
        </div>
    </form>
</div>

<script>
let exerciseCount = <?= count($ejercicios_dia) ?>;

function addSet(ejIdx) {
    const container = document.getElementById(`sets-container-${ejIdx}`);
    if (!container) return;
    const setIdx = container.children.length;
    const div = document.createElement('div');
    div.className = 'grid grid-cols-[30px,1fr,1fr,auto] gap-3 items-center set-row';
    div.innerHTML = `
        <span class="text-xs font-black text-slate-600 text-center set-num">${setIdx + 1}</span>
        <input type="number" step="0.5" name="ejercicios[${ejIdx}][series][${setIdx}][peso]" class="form-control py-2 text-center" placeholder="kg" required>
        <input type="number" name="ejercicios[${ejIdx}][series][${setIdx}][reps]" class="form-control py-2 text-center" placeholder="reps" required>
        <button type="button" class="text-red-500/50 hover:text-red-500 text-xl px-2" onclick="removeSet(this)">&times;</button>
    `;
    container.appendChild(div);
}

function removeSet(btn) {
    const container = btn.closest('[id^=sets-container-]');
    btn.parentElement.remove();
    Array.from(container.children).forEach((row, i) => {
        row.querySelector('.set-num').textContent = i + 1;
    });
}

function addManualExercise() {
    const container = document.getElementById('ejercicios-container');
    const idx = exerciseCount++;
    const div = document.createElement('div');
    div.className = 'glass-panel border-l-4 border-l-blue-500/50';
    div.innerHTML = `
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/5 relative">
            <div class="w-full relative">
                <input type="text" name="ejercicios[${idx}][name]" class="bg-transparent border-none text-lg font-black text-white focus:outline-none placeholder-white/20 w-full exercise-input" placeholder="Nombre del Ejercicio..." required autocomplete="off">
                <div class="suggestions-list hidden"></div>
            </div>
            <button type="button" class="text-red-500/50 hover:text-red-500 text-xs font-bold uppercase ml-4" onclick="this.closest('.glass-panel').remove()">Eliminar</button>
        </div>
        <div id="sets-container-${idx}" class="space-y-3">
            <div class="grid grid-cols-[30px,1fr,1fr,auto] gap-3 items-center set-row">
                <span class="text-xs font-black text-slate-600 text-center set-num">1</span>
                <input type="number" step="0.5" name="ejercicios[${idx}][series][0][peso]" class="form-control py-2 text-center" placeholder="kg" required>
                <input type="number" name="ejercicios[${idx}][series][0][reps]" class="form-control py-2 text-center" placeholder="reps" required>
                <button type="button" class="text-red-500/50 hover:text-red-500 text-xl px-2" onclick="removeSet(this)">&times;</button>
            </div>
        </div>
        <button type="button" onclick="addSet(${idx})" class="mt-4 text-xs font-black text-blue-400 uppercase tracking-widest hover:text-white transition-colors">+ Añadir Serie</button>
    `;
    container.appendChild(div);
    initAutocomplete(div.querySelector('.exercise-input'));
}

function initAutocomplete(input) {
    const suggestionsList = input.nextElementSibling;
    let debounceTimer;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = input.value.trim();

        if (query.length < 1) {
            suggestionsList.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`api_ejercicios?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length > 0) {
                        suggestionsList.innerHTML = '';
                        data.forEach(name => {
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.textContent = name;
                            item.onclick = () => {
                                input.value = name;
                                suggestionsList.classList.add('hidden');
                            };
                            suggestionsList.appendChild(item);
                        });
                        suggestionsList.classList.remove('hidden');
                    } else {
                        suggestionsList.classList.add('hidden');
                    }
                });
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !suggestionsList.contains(e.target)) {
            suggestionsList.classList.add('hidden');
        }
    });
}

// Inicializar autocompletado en inputs manuales existentes si los hubiera
document.querySelectorAll('.exercise-input').forEach(initAutocomplete);
</script>

<?php require_once 'includes/footer.php'; ?>
