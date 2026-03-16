<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$session_id) {
    header("Location: historial");
    exit;
}

// Verificar que la sesión pertenezca al usuario
$stmt = $pdo->prepare("SELECT * FROM GT_workout_sessions WHERE id = ? AND user_id = ?");
$stmt->execute([$session_id, $user_id]);
$session = $stmt->fetch();

if (!$session) {
    header("Location: historial");
    exit;
}

// Cargar ejercicios y series
$stmt = $pdo->prepare("SELECT * FROM GT_session_logs WHERE session_id = ? ORDER BY id ASC");
$stmt->execute([$session_id]);
$logs = $stmt->fetchAll();

// Agrupar por ejercicio
$ejercicios_agrupados = [];
foreach ($logs as $log) {
    $e_name = $log['exercise_name'];
    if (!isset($ejercicios_agrupados[$e_name])) {
        $ejercicios_agrupados[$e_name] = [];
    }
    $ejercicios_agrupados[$e_name][] = $log;
}

// Procesar Actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_session'])) {
    $fecha_seleccionada = !empty($_POST['session_date']) ? $_POST['session_date'] : date('Y-m-d');
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar datos de la sesión
        $stmt = $pdo->prepare("UPDATE GT_workout_sessions SET session_date = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$fecha_seleccionada, $session_id, $user_id]);

        // Borrar logs anteriores para re-insertar (más simple que actualizar uno por uno)
        $stmt = $pdo->prepare("DELETE FROM GT_session_logs WHERE session_id = ?");
        $stmt->execute([$session_id]);

        if (isset($_POST['ejercicios'])) {
            foreach ($_POST['ejercicios'] as $ejercicio) {
                $ej_name = trim($ejercicio['name']);
                if (empty($ej_name)) continue;

                // --- LIBRERÍA GLOBAL ---
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
        header("Location: historial?success=edit");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

$active_page = 'historial';
$page_title = 'Editar Entrenamiento - GymTraker';
require_once 'includes/header.php';
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Editar Sesión <i class="fa-solid fa-pen-to-square text-primary ml-1"></i></h1>
    <p class="text-slate-400">Modifica los detalles de tu entrenamiento del <?= date('d/m/Y', strtotime($session['session_date'])) ?>.</p>
</header>

<div class="max-w-4xl mx-auto">
    <form action="editar_sesion.php?id=<?= $session_id ?>" method="POST" id="session-form" class="space-y-8">
        <div class="glass-panel">
            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Fecha del Entrenamiento</label>
            <input type="date" name="session_date" class="form-control" value="<?= $session['session_date'] ?>" required>
        </div>

        <div id="ejercicios-container" class="space-y-6">
            <?php 
            $idx = 0;
            foreach($ejercicios_agrupados as $nombre_ex => $series): 
            ?>
                <div class="glass-panel border-l-4 border-l-primary/50">
                    <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/5 relative">
                        <div class="w-full relative">
                            <input type="text" name="ejercicios[<?= $idx ?>][name]" class="bg-transparent border-none text-lg font-black text-white focus:outline-none placeholder-white/20 w-full exercise-input" value="<?= htmlspecialchars($nombre_ex) ?>" required autocomplete="off">
                            <div class="suggestions-list hidden"></div>
                        </div>
                        <button type="button" class="text-red-500/50 hover:text-red-500 text-xs font-bold uppercase ml-4" onclick="this.closest('.glass-panel').remove()">Eliminar</button>
                    </div>
                    <div id="sets-container-<?= $idx ?>" class="space-y-3">
                        <?php foreach($series as $s_idx => $serie): ?>
                            <div class="grid grid-cols-[30px,1fr,1fr,auto] gap-3 items-center set-row">
                                <span class="text-xs font-black text-slate-600 text-center set-num"><?= $s_idx + 1 ?></span>
                                <input type="number" step="0.5" name="ejercicios[<?= $idx ?>][series][<?= $s_idx ?>][peso]" class="form-control py-2 text-center" value="<?= $serie['weight'] ?>" placeholder="kg" required>
                                <input type="number" name="ejercicios[<?= $idx ?>][series][<?= $s_idx ?>][reps]" class="form-control py-2 text-center" value="<?= $serie['reps'] ?>" placeholder="reps" required>
                                <button type="button" class="text-red-500/50 hover:text-red-500 text-xl px-2" onclick="removeSet(this)">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addSet(<?= $idx ?>)" class="mt-4 text-xs font-black text-primary uppercase tracking-widest hover:text-white transition-colors">+ Añadir Serie</button>
                </div>
            <?php 
                $idx++;
            endforeach; 
            ?>
        </div>

        <div class="flex flex-col gap-4">
            <button type="button" onclick="addManualExercise()" class="w-full py-4 border-2 border-dashed border-white/10 rounded-2xl text-slate-400 font-bold hover:border-primary hover:text-primary transition-all uppercase tracking-widest text-xs"><i class="fa-solid fa-plus mr-2"></i>Añadir Ejercicio</button>
            <button type="submit" name="update_session" class="btn-primary w-full py-5 text-xl uppercase tracking-[4px] shadow-2xl shadow-primary/20"><i class="fa-solid fa-floppy-disk mr-3"></i>Guardar Cambios</button>
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
    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }
</style>

<script>
let exerciseCount = <?= $idx ?>;

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

document.querySelectorAll('.exercise-input').forEach(initAutocomplete);
</script>

<?php require_once 'includes/footer.php'; ?>
