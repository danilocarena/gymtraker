<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();
$username = getCurrentUsername();

$success = '';
$error = '';

// Procesar Actualización de Perfil (Avatar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_avatar = $_POST['avatar_emoji'] ?? '🚀';
    
    try {
        $stmtUpdate = $pdo->prepare("UPDATE DT_users SET avatar_emoji = ? WHERE id = ?");
        $stmtUpdate->execute([
            $new_avatar,
            $user_id
        ]);
        
        $success = "Perfil actualizado con éxito.";
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Procesar Cambio de Contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password_hash FROM DT_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $stored_hash = $stmt->fetchColumn();

    if ($stored_hash && !password_verify($current_pass, $stored_hash)) {
        $error = "La contraseña actual es incorrecta.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Las nuevas contraseñas no coinciden.";
    } elseif (strlen($new_pass) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE DT_users SET password_hash = ? WHERE id = ?");
        if ($stmt->execute([$new_hash, $user_id])) {
            $success = "¡Contraseña actualizada con éxito!";
        } else {
            $error = "Error al actualizar la contraseña.";
        }
    }
}

// Fetch Info
$stmtInfo = $pdo->prepare("SELECT email, created_at, avatar_emoji, password_hash, google_id FROM DT_users WHERE id = ?");
$stmtInfo->execute([$user_id]);
$user_info = $stmtInfo->fetch();

// Meta automática
$stmtGoal = $pdo->prepare("SELECT COUNT(*) FROM DT_weekly_routine WHERE user_id = ?");
$stmtGoal->execute([$user_id]);
$goal = intval($stmtGoal->fetchColumn()) ?: 0;
$avatar = $user_info['avatar_emoji'] ?: '🚀';

// Stats
$stmtTotalPlans = $pdo->prepare("SELECT COUNT(*) FROM DT_daily_plans WHERE user_id = ?");
$stmtTotalPlans->execute([$user_id]);
$total_plans = $stmtTotalPlans->fetchColumn();

$stmtTasks = $pdo->prepare("SELECT COUNT(*) FROM DT_task_logs sl JOIN DT_daily_plans ws ON sl.plan_id = ws.id WHERE ws.user_id = ? AND sl.status = 'completed'");
$stmtTasks->execute([$user_id]);
$total_tasks_completed = $stmtTasks->fetchColumn() ?: 0;

$stmtFav = $pdo->prepare("
    SELECT t.name as task_name, COUNT(*) as done_count 
    FROM DT_task_logs sl 
    JOIN DT_daily_plans ws ON sl.plan_id = ws.id 
    JOIN DT_tasks t ON sl.task_id = t.id
    WHERE ws.user_id = ? AND sl.status = 'completed'
    GROUP BY t.id 
    ORDER BY done_count DESC 
    LIMIT 1
");
$stmtFav->execute([$user_id]);
$fav_task = $stmtFav->fetch() ?: ['task_name' => 'Sin datos', 'done_count' => 0];

$active_page = 'perfil';
$page_title = 'Mi Perfil - DayTraker';

require_once 'includes/header.php';
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Perfil <i class="fa-solid fa-user text-primary ml-1"></i></h1>
    <p class="text-slate-400">Personaliza tu cuenta y revisa tu rendimiento.</p>
</header>

<?php if($success): ?>
    <div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-panel text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-blue-500"></div>
            <div class="w-24 h-24 bg-primary/10 border-2 border-primary rounded-full flex items-center justify-center text-4xl mx-auto mb-4 mt-2"><?= $avatar ?></div>
            <h2 class="text-2xl font-black text-white">@<?= htmlspecialchars($username) ?></h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-6"><?= htmlspecialchars($user_info['email']) ?></p>
            
            <div class="bg-white/5 p-4 rounded-xl border border-white/5 mb-6">
                <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Meta Semanal</span>
                <span class="text-sm font-black text-primary"><?= $goal ?> tareas programadas</span>
            </div>

            <button onclick="openModal()" class="w-full bg-white/5 border border-white/10 hover:bg-white/10 text-white py-3 rounded-xl font-bold transition-all text-xs uppercase tracking-widest mb-3">Editar Perfil</button>
            <?php if (!$user_info['google_id'] || $user_info['password_hash']): ?>
                <button onclick="openPassModal()" class="w-full bg-white/0 text-slate-500 hover:text-primary py-2 rounded-xl font-bold transition-all text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock"></i> Cambiar Contraseña
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="glass-panel text-center">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 block">Planes Realizados</span>
                <span class="text-4xl font-black text-primary"><?= $total_plans ?></span>
            </div>
            <div class="glass-panel text-center">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 block">Tareas Completadas</span>
                <span class="text-4xl font-black text-blue-400"><?= $total_tasks_completed ?></span>
            </div>
            <div class="glass-panel text-center md:col-span-2">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 block">Tarea más frecuente</span>
                <span class="text-xl font-black text-white uppercase"><?= htmlspecialchars($fav_task['task_name']) ?></span>
                <p class="text-xs text-slate-500 font-bold mt-1"><?= $fav_task['done_count'] ?> veces realizada</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Perfil -->
<div id="modalPerfil" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm p-4 flex items-center justify-center">
    <div class="glass-panel w-full max-w-md animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
            <h2 class="text-xl font-black text-white uppercase">Perfil</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white text-3xl leading-none">&times;</button>
        </div>
        <form action="perfil" method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Elige tu Avatar</label>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach(['🚀', '🎯', '🔥', '🧠', '💼', '📅', '📝', '⚡', '🌟', '💪', '🦁', '🦊', '🐺', '🦉', '💎'] as $e): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="avatar_emoji" value="<?= $e ?>" class="hidden peer" <?= $avatar == $e ? 'checked' : '' ?>>
                            <div class="w-12 h-12 flex items-center justify-center text-2xl bg-white/5 rounded-xl border border-white/10 peer-checked:bg-primary/20 peer-checked:border-primary transition-all"><?= $e ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="text-[10px] text-slate-500 font-bold text-center mt-2 italic px-4">Tu meta semanal ahora se calcula automáticamente sumando todas las tareas de tu Rutina Semanal.</p>
            <button type="submit" name="update_profile" class="w-full btn-primary uppercase tracking-widest">Guardar</button>
        </form>
    </div>
</div>

<!-- Modal: Contraseña -->
<div id="modalPassword" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm p-4 flex items-center justify-center">
    <div class="glass-panel w-full max-w-sm animate-in fade-in zoom-in duration-200">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
            <h2 class="text-xl font-black text-white uppercase">Seguridad</h2>
            <button onclick="closePassModal()" class="text-slate-400 hover:text-white text-3xl leading-none">&times;</button>
        </div>
        <form action="perfil" method="POST" class="space-y-5">
            <input type="hidden" name="change_password" value="1">
            <?php if ($user_info['password_hash']): ?>
                <input type="password" name="current_password" class="form-control" placeholder="Contraseña Actual" required>
            <?php endif; ?>
            <input type="password" name="new_password" class="form-control" placeholder="Nueva Contraseña" required minlength="6">
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirmar" required minlength="6">
            <button type="submit" class="w-full btn-primary uppercase tracking-widest">Actualizar</button>
        </form>
    </div>
</div>

<script>
    function openModal() { document.getElementById('modalPerfil').classList.remove('hidden'); }
    function closeModal() { document.getElementById('modalPerfil').classList.add('hidden'); }
    function openPassModal() { document.getElementById('modalPassword').classList.remove('hidden'); }
    function closePassModal() { document.getElementById('modalPassword').classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>
