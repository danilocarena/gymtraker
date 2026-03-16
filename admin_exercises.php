<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireAdmin();

$active_page = 'admin_exercises';
$page_title = 'Gestión de Ejercicios - Admin';

$success = '';
$error = '';

// Manejar creación/edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'save') {
        $name = trim($_POST['name']);
        $muscle_group = trim($_POST['muscle_group']);
        $category = trim($_POST['category']);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (empty($name) || empty($muscle_group)) {
            $error = "Nombre y grupo muscular son obligatorios.";
        } else {
            if ($id) {
                // Editar
                $stmt = $pdo->prepare("UPDATE GT_exercises SET name = ?, muscle_group = ?, category = ? WHERE id = ?");
                $stmt->execute([$name, $muscle_group, $category, $id]);
                $success = "Ejercicio actualizado.";
            } else {
                // Crear
                $stmt = $pdo->prepare("INSERT INTO GT_exercises (name, muscle_group, category) VALUES (?, ?, ?)");
                $stmt->execute([$name, $muscle_group, $category]);
                $success = "Ejercicio creado.";
            }
        }
    }
}

// Manejar eliminación
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM GT_exercises WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Ejercicio eliminado.";
}

// Obtener ejercicios
$exercises = $pdo->query("SELECT * FROM GT_exercises ORDER BY muscle_group, name")->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-black text-white tracking-tight uppercase">Gestión de Ejercicios</h2>
            <p class="text-slate-400 text-sm">Añade o modifica los ejercicios disponibles en el catálogo.</p>
        </div>
        <button onclick="openModal()" class="btn-primary flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Nuevo Ejercicio
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-primary/10 text-primary p-4 rounded-xl mb-6 font-bold flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <div class="glass-panel overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5">
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Nombre</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Grupo Muscular</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Categoría</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($exercises as $ex): ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($ex['name']) ?></div>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded-lg bg-white/5 text-slate-300 text-xs font-semibold">
                                <?= htmlspecialchars($ex['muscle_group']) ?>
                            </span>
                        </td>
                        <td class="p-4 text-sm text-slate-400"><?= htmlspecialchars($ex['category'] ?? '-') ?></td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <button onclick='editExercise(<?= json_encode($ex) ?>)' class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-all">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <a href="admin_exercises?delete=<?= $ex['id'] ?>" onclick="return confirm('¿Seguro?')" class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Formulario -->
<div id="exerciseModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-md animate-in fade-in zoom-in duration-300">
        <h3 id="modalTitle" class="text-2xl font-black text-white mb-6 uppercase">Nuevo Ejercicio</h3>
        <form action="admin_exercises" method="POST" class="space-y-4 text-left">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ex_id">
            
            <div>
                <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2">Nombre del Ejercicio</label>
                <input type="text" name="name" id="ex_name" class="form-control" required>
            </div>
            
            <div>
                <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2">Grupo Muscular</label>
                <input type="text" name="muscle_group" id="ex_muscle" class="form-control" placeholder="Ej: Pecho, Piernas..." required>
            </div>

            <div>
                <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2">Categoría (Opcional)</label>
                <input type="text" name="category" id="ex_category" class="form-control" placeholder="Ej: Fuerza, HIIT...">
            </div>

            <div class="flex gap-3 mt-8">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-xl font-bold bg-white/5 text-slate-400 hover:bg-white/10 transition-all">Cancelar</button>
                <button type="submit" class="flex-1 btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'Nuevo Ejercicio';
    document.getElementById('ex_id').value = '';
    document.getElementById('ex_name').value = '';
    document.getElementById('ex_muscle').value = '';
    document.getElementById('ex_category').value = '';
    document.getElementById('exerciseModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('exerciseModal').classList.add('hidden');
}

function editExercise(ex) {
    document.getElementById('modalTitle').innerText = 'Editar Ejercicio';
    document.getElementById('ex_id').value = ex.id;
    document.getElementById('ex_name').value = ex.name;
    document.getElementById('ex_muscle').value = ex.muscle_group;
    document.getElementById('ex_category').value = ex.category || '';
    document.getElementById('exerciseModal').classList.remove('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
