<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$active_page = 'tareas';
$page_title = 'Catálogo de Tareas';

// Obtener todas las tareas
$tasks = $pdo->query("
    SELECT t.*, c.name as category_name, c.color as category_color 
    FROM DT_tasks t 
    LEFT JOIN DT_categories c ON t.category_id = c.id 
    ORDER BY c.name ASC, t.name ASC
")->fetchAll();

// Obtener categorías únicas para el filtro
$categories = $pdo->query("SELECT * FROM DT_categories ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <header class="mb-10">
        <h2 class="text-4xl font-black text-white tracking-tight uppercase">Catálogo de Tareas</h2>
        <p class="text-slate-400">Explora las tareas disponibles para tu planificación.</p>
    </header>

    <!-- Filtros y Búsqueda -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="md:col-span-2 relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="taskSearch" placeholder="Buscar tarea..." class="form-control pl-10" onkeyup="filterTasks()">
        </div>
        <div>
            <select id="categoryFilter" class="form-control" onchange="filterTasks()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Lista de Tareas -->
    <div id="taskGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($tasks as $task): ?>
            <div class="glass-panel flex flex-col justify-between hover:border-primary/30 transition-all duration-300 group task-card" 
                 data-name="<?= strtolower(htmlspecialchars($task['name'])) ?>" 
                 data-category="<?= htmlspecialchars($task['category_name']) ?>">
                
                <div>
                    <div class="flex items-start justify-between mb-4">
                        <span class="px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-primary/10 text-primary border border-primary/20" style="border-color: <?= $task['category_color'] ?>44; color: <?= $task['category_color'] ?>;">
                            <?= htmlspecialchars($task['category_name'] ?: 'General') ?>
                        </span>
                    </div>
                    
                    <h3 class="text-xl font-bold text-white mb-2 group-hover:text-primary transition-colors">
                        <?= htmlspecialchars($task['name']) ?>
                    </h3>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="noResults" class="hidden text-center py-20">
        <i class="fa-solid fa-list-check text-slate-700 text-5xl mb-4"></i>
        <p class="text-slate-500 font-bold">No se encontraron tareas con esos filtros.</p>
    </div>
</div>

<script>
function filterTasks() {
    const searchVal = document.getElementById('taskSearch').value.toLowerCase();
    const categoryVal = document.getElementById('categoryFilter').value;
    const cards = document.querySelectorAll('.task-card');
    let hasResults = false;

    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const category = card.getAttribute('data-category');
        
        const matchesSearch = name.includes(searchVal);
        const matchesCategory = categoryVal === "" || category === categoryVal;

        if (matchesSearch && matchesCategory) {
            card.classList.remove('hidden');
            hasResults = true;
        } else {
            card.classList.add('hidden');
        }
    });

    document.getElementById('noResults').classList.toggle('hidden', hasResults);
    document.getElementById('taskGrid').classList.toggle('hidden', !hasResults);
}
</script>

<?php include 'includes/footer.php'; ?>
