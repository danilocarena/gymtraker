<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

$success = '';
$error = '';

// Procesar Guardado de Nueva Rutina o Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_rutina'])) {
    $nombre_rutina = trim($_POST['nombre']);
    $edit_id = isset($_POST['rutina_id']) ? intval($_POST['rutina_id']) : 0;
    
    if (empty($nombre_rutina)) {
        $error = "El nombre de la rutina es obligatorio.";
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($edit_id > 0) {
                // Actualizar rutina existente
                $stmt = $pdo->prepare("UPDATE GT_routines SET name = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$nombre_rutina, $edit_id, $user_id]);
                $rutina_id = $edit_id;
                
                // Borrar días y ejercicios anteriores para recrearlos
                $stmt = $pdo->prepare("DELETE FROM GT_routine_days WHERE routine_id = ?");
                $stmt->execute([$rutina_id]);
            } else {
                // 1. Insertar Rutina Nueva
                $stmt = $pdo->prepare("INSERT INTO GT_routines (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $nombre_rutina]);
                $rutina_id = $pdo->lastInsertId();
            }

            // 2. Insertar Días y Ejercicios
            if (isset($_POST['dias']) && is_array($_POST['dias'])) {
                $orden_dia = 1;
                foreach ($_POST['dias'] as $dia_index => $dia_nombre) {
                    $dia_nombre = trim($dia_nombre);
                    if (!empty($dia_nombre)) {
                        $stmt = $pdo->prepare("INSERT INTO GT_routine_days (routine_id, day_name, day_order) VALUES (?, ?, ?)");
                        $stmt->execute([$rutina_id, $dia_nombre, $orden_dia]);
                        $dia_id = $pdo->lastInsertId();
                        $orden_dia++;

                        // Ejercicios para este día
                        if (isset($_POST['ejercicios'][$dia_index]) && is_array($_POST['ejercicios'][$dia_index])) {
                            $orden_ejercicio = 1;
                            foreach ($_POST['ejercicios'][$dia_index] as $ej_nombre) {
                                $ej_nombre = trim($ej_nombre);
                                if (!empty($ej_nombre)) {
                                    // --- LIBRERÍA GLOBAL: Insertar ejercicio si no existe ---
                                    $stmtLib = $pdo->prepare("INSERT IGNORE INTO GT_exercises (name) VALUES (?)");
                                    $stmtLib->execute([$ej_nombre]);

                                    $stmt = $pdo->prepare("INSERT INTO GT_routine_exercises (routine_day_id, exercise_name, exercise_order) VALUES (?, ?, ?)");
                                    $stmt->execute([$dia_id, $ej_nombre, $orden_ejercicio]);
                                    $orden_ejercicio++;
                                }
                            }
                        }
                    }
                }
            }
            $pdo->commit();
            $success = $edit_id > 0 ? "Rutina '$nombre_rutina' actualizada con éxito." : "Rutina '$nombre_rutina' guardada con éxito.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Procesar Eliminación de Rutina
if (isset($_GET['delete'])) {
     $del_id = intval($_GET['delete']);
     $stmt = $pdo->prepare("DELETE FROM GT_routines WHERE id = ? AND user_id = ?");
     if($stmt->execute([$del_id, $user_id])) {
         header("Location: rutinas");
         exit;
     }
}

// Obtener Listado de Rutinas
$stmt = $pdo->prepare("SELECT r.id, r.name, (SELECT COUNT(*) FROM GT_routine_days WHERE routine_id = r.id) as days_count FROM GT_routines r WHERE r.user_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$user_id]);
$GT_routines = $stmt->fetchAll();

$active_page = 'rutinas';
$page_title = 'Mis Rutinas - GymTracker Pro';

// Datos de edición si aplica
$rutina_edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT id, name FROM GT_routines WHERE id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $rutina_edit = $stmt->fetch();
    if ($rutina_edit) {
        $rutina_edit_data = ['id' => $rutina_edit['id'], 'name' => $rutina_edit['name'], 'days' => []];
        $stmtDays = $pdo->prepare("SELECT id, day_name FROM GT_routine_days WHERE routine_id = ? ORDER BY day_order ASC");
        $stmtDays->execute([$edit_id]);
        foreach ($stmtDays->fetchAll() as $day) {
            $stmtEx = $pdo->prepare("SELECT exercise_name FROM GT_routine_exercises WHERE routine_day_id = ? ORDER BY exercise_order ASC");
            $stmtEx->execute([$day['id']]);
            $rutina_edit_data['days'][] = ['name' => $day['day_name'], 'GT_exercises' => $stmtEx->fetchAll(PDO::FETCH_COLUMN)];
        }
    }
}

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
        max-height: 150px;
        overflow-y: auto;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }
    .suggestion-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        font-size: 0.875rem;
        color: #94a3b8;
    }
    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }
</style>

<!-- Modal: Añadir/Editar Rutina -->
<div id="routineModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm p-4">
    <div class="flex min-h-full items-center justify-center">
        <div class="glass-panel w-full max-w-2xl bg-[#1e293b] border border-white/10 shadow-2xl relative">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
                <h2 class="text-xl md:text-2xl font-black text-white" id="modalTitle">Diseñar Nueva Rutina</h2>
                <button type="button" class="text-slate-400 hover:text-white text-3xl leading-none" onclick="closeModal()">&times;</button>
            </div>

            <form id="routineForm" method="POST" class="space-y-6">
                <input type="hidden" name="rutina_id" id="routine_id">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Nombre de la Rutina</label>
                    <input type="text" name="nombre" id="routine_name" class="form-control" placeholder="ej. Push Pull Legs" required>
                </div>
                <div id="days-container" class="space-y-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
                    <!-- Días se añaden dinámicamente -->
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                    <button type="button" onclick="addDay()" class="flex-1 bg-white/5 border border-white/10 text-white py-3 rounded-xl font-bold hover:bg-white/10 transition-all text-sm uppercase tracking-wide">+ Añadir Día</button>
                    <button type="submit" name="guardar_rutina" class="flex-2 btn-primary text-sm uppercase tracking-widest"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar Rutina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<header class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-extrabold mb-1">Tus Rutinas <i class="fa-solid fa-clipboard-list text-primary ml-1"></i></h1>
        <p class="text-slate-400">Crea o edita tu plan maestro de entrenamiento.</p>
    </div>
    <button class="bg-primary hover:bg-primary-hover text-white py-3 px-6 rounded-xl font-bold transition-all shadow-lg hover:-translate-y-0.5" onclick="initNewRoutine()"><i class="fa-solid fa-plus mr-2"></i>Nueva Rutina</button>
</header>

<?php if($success): ?>
    <div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if(empty($GT_routines)): ?>
        <p class="text-slate-400 italic col-span-full text-center py-10">No tienes rutinas configuradas. ¡Crea una para empezar!</p>
    <?php else: ?>
        <?php foreach ($GT_routines as $routine): ?>
            <div class="glass-panel group hover:border-primary/30 transition-all duration-300">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-1"><?= htmlspecialchars($routine['name']) ?></h3>
                        <span class="text-xs text-slate-500 font-bold uppercase tracking-wider"><?= $routine['days_count'] ?> Días configurados</span>
                    </div>
                    <span class="text-2xl group-hover:scale-110 transition-transform"><i class="fa-solid fa-clipboard-list text-primary"></i></span>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-6">
                    <a href="sesion_nueva.php?routine_id=<?= $routine['id'] ?>" class="bg-primary/10 text-primary hover:bg-primary text-center py-2 px-1 rounded-lg text-[10px] font-bold uppercase tracking-tighter transition-all hover:text-white"><i class="fa-solid fa-play mr-1"></i> Empezar</a>
                    <a href="?edit=<?= $routine['id'] ?>" class="bg-blue-500/10 text-blue-400 hover:bg-blue-500 text-center py-2 px-1 rounded-lg text-[10px] font-bold uppercase tracking-tighter transition-all hover:text-white"><i class="fa-solid fa-pen-to-square mr-1"></i> Editar</a>
                    <a href="?delete=<?= $routine['id'] ?>" class="bg-red-500/10 text-red-400 hover:bg-red-500 text-center py-2 px-1 rounded-lg text-[10px] font-bold uppercase tracking-tighter transition-all hover:text-white" onclick="return confirm('¿Borrar rutina?')"><i class="fa-solid fa-trash-can mr-1"></i> Borrar</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<script>
    let dayCount = 0;

    function addDay(dayName = '') {
        const container = document.getElementById('days-container');
        const dIdx = dayCount++;
        const div = document.createElement('div');
        div.className = 'bg-black/20 p-4 rounded-xl border border-white/5';
        div.id = `dia-bloque-${dIdx}`;
        div.innerHTML = `
            <div class="flex justify-between items-center mb-4">
                <input type="text" name="dias[${dIdx}]" class="form-control" placeholder="Nombre del día (ej. Día 1 Pecho)" value="${dayName}" required autocomplete="off">
                <button type="button" class="text-red-500 cursor-pointer ml-3 text-xl hover:text-red-400" onclick="this.closest('[id^=dia-bloque-]').remove()"><i class="fa-solid fa-trash-can"></i></button>
            </div>
            <div id="ejercicios-dia-${dIdx}" class="space-y-2"></div>
            <button type="button" class="w-full bg-transparent text-primary hover:bg-primary/5 text-sm py-2 px-4 border border-dashed border-primary/50 rounded-lg mt-3 transition-colors" onclick="addExercise(${dIdx})">+ Añadir Ejercicio a este Día</button>
        `;
        container.appendChild(div);
        return dIdx;
    }

    function addExercise(dayIndex, exName = '') {
        const container = document.getElementById(`ejercicios-dia-${dayIndex}`);
        const div = document.createElement('div');
        div.className = 'flex gap-2 relative';
        const safeName = exName.replace(/"/g, '&quot;');
        div.innerHTML = `
            <div class="flex-1 relative">
                <input type="text" name="ejercicios[${dayIndex}][]" class="form-control py-2 text-sm exercise-input" placeholder="Nombre del Ejercicio" value="${safeName}" required autocomplete="off">
                <div class="suggestions-list hidden"></div>
            </div>
            <button type="button" class="text-red-500 text-2xl px-1 hover:text-red-400" onclick="this.parentElement.remove()">&times;</button>
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

    function openModal() { document.getElementById('routineModal').classList.remove('hidden'); }
    function closeModal() { document.getElementById('routineModal').classList.add('hidden'); }

    function initNewRoutine() {
        document.getElementById('modalTitle').textContent = 'Diseñar Nueva Rutina';
        document.getElementById('routine_id').value = '';
        document.getElementById('routine_name').value = '';
        document.getElementById('days-container').innerHTML = '';
        dayCount = 0;
        addDay();
        openModal();
    }

    <?php if ($rutina_edit_data): ?>
    window.onload = function() {
        document.getElementById('modalTitle').textContent = 'Editar Rutina';
        document.getElementById('routine_id').value = '<?= $rutina_edit_data['id'] ?>';
        document.getElementById('routine_name').value = <?= json_encode($rutina_edit_data['name']) ?>;
        document.getElementById('days-container').innerHTML = '';
        dayCount = 0;
        const days = <?= json_encode($rutina_edit_data['days']) ?>;
        days.forEach(day => {
            const dIdx = addDay(day.name);
            if (day.GT_exercises && day.GT_exercises.length > 0) {
                day.GT_exercises.forEach(ex => addExercise(dIdx, ex));
            } else { addExercise(dIdx); }
        });
        openModal();
    };
    <?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
