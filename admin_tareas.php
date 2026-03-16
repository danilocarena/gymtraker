<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireAdmin();

$active_page = 'admin_tareas';
$page_title = 'Gestión de Tareas - Admin';

$success = '';
$error = '';

// Manejar creación/edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'save') {
        $name = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (empty($name)) {
            $error = "El nombre es obligatorio.";
        } else {
            if ($id) {
                // Editar
                $stmt = $pdo->prepare("UPDATE DT_tasks SET name = ?, category_id = ? WHERE id = ?");
                $stmt->execute([$name, $category_id ?: null, $id]);
                $success = "Tarea actualizada.";
            } else {
                // Crear
                $stmt = $pdo->prepare("INSERT INTO DT_tasks (name, category_id) VALUES (?, ?)");
                $stmt->execute([$name, $category_id ?: null]);
                $success = "Tarea creada.";
            }
        }
    }
}

// Manejar eliminación
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM DT_tasks WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Tarea eliminada.";
}

// Obtener tareas y categorías
$tasks = $pdo->query("SELECT t.*, c.name as category_name FROM DT_tasks t LEFT JOIN DT_categories c ON t.category_id = c.id ORDER BY t.name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM DT_categories ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-black text-white tracking-tight uppercase">Gestión de Tareas</h2>
            <p class="text-slate-400 text-sm">Añade o modifica las tareas disponibles en el catálogo.</p>
        </div>
        <button onclick="openModal()" class="btn-primary flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Nueva Tarea
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
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Categoría</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($tasks as $task): ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($task['name']) ?></div>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded-lg bg-white/5 text-slate-300 text-xs font-semibold">
                                <?= htmlspecialchars($task['category_name'] ?: 'Sin categoría') ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <button onclick='editTask(<?= json_encode($task) ?>)' class="p-2 text-primary hover:bg-primary/10 rounded-lg transition-all">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <a href="admin_tareas.php?delete=<?= $task['id'] ?>" onclick="return confirm('¿Seguro?')" class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all">
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
<div id="taskModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-md animate-in fade-in zoom-in duration-300">
        <h3 id="modalTitle" class="text-2xl font-black text-white mb-6 uppercase">Nueva Tarea</h3>
        <form action="admin_tareas.php" method="POST" class="space-y-4 text-left">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="task_id">
            
            <div>
                <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2">Nombre de la Tarea</label>
                <input type="text" name="name" id="task_name" class="form-control" required>
            </div>
            
            <div>
                <label class="block text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2">Categoría</label>
                <select name="category_id" id="task_category" class="form-control">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
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
    document.getElementById('modalTitle').innerText = 'Nueva Tarea';
    document.getElementById('task_id').value = '';
    document.getElementById('task_name').value = '';
    document.getElementById('task_category').value = '';
    document.getElementById('taskModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('taskModal').classList.add('hidden');
}

function editTask(task) {
    document.getElementById('modalTitle').innerText = 'Editar Tarea';
    document.getElementById('task_id').value = task.id;
    document.getElementById('task_name').value = task.name;
    document.getElementById('task_category').value = task.category_id || '';
    document.getElementById('taskModal').classList.remove('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
