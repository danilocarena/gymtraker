<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();
$username = getCurrentUsername();

$success = '';
$error = '';

// Procesar Actualización de Perfil (Datos biométricos, objetivo y avatar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_height = floatval($_POST['height']);
    $new_goal = intval($_POST['weekly_goal']);
    $new_avatar = $_POST['avatar_emoji'] ?? '🦍';
    
    try {
        $stmtUpdate = $pdo->prepare("UPDATE GT_users SET height = ?, weekly_goal = ?, avatar_emoji = ? WHERE id = ?");
        $stmtUpdate->execute([
            $new_height > 0 ? $new_height : null, 
            $new_goal > 0 ? $new_goal : 4,
            $new_avatar,
            $user_id
        ]);
        
        if ($stmtUpdate->rowCount() >= 0) {
            $success = "Perfil actualizado con éxito.";
        } else {
            $error = "No se realizaron cambios en el perfil.";
        }
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// 1. Info del usuario (Fetch fresco después del post)
$stmtInfo = $pdo->prepare("SELECT email, created_at, weight, height, weekly_goal, avatar_emoji FROM GT_users WHERE id = ?");
$stmtInfo->execute([$user_id]);
$user_info = $stmtInfo->fetch();

if (!$user_info) {
    die("Error: No se pudo encontrar la información del usuario ID: " . htmlspecialchars($user_id));
}

$peso = $user_info['weight'] ? floatval($user_info['weight']) : 0;
$altura = $user_info['height'] ? floatval($user_info['height']) : 0;
$goal = $user_info['weekly_goal'] ?: 4;
$avatar = $user_info['avatar_emoji'] ?: '🦍';
$imc = 0;
if ($peso > 0 && $altura > 0) {
    $altura_m = $altura / 100;
    $imc = $peso / ($altura_m * $altura_m);
}

// Stats
$stmtTotalSessions = $pdo->prepare("SELECT COUNT(*) FROM GT_workout_sessions WHERE user_id = ?");
$stmtTotalSessions->execute([$user_id]);
$total_sessions = $stmtTotalSessions->fetchColumn();

$stmtVolume = $pdo->prepare("SELECT SUM(reps * weight) FROM GT_session_logs sl JOIN GT_workout_sessions ws ON sl.session_id = ws.id WHERE ws.user_id = ?");
$stmtVolume->execute([$user_id]);
$total_volume = $stmtVolume->fetchColumn() ?: 0;

$stmtFav = $pdo->prepare("SELECT exercise_name, COUNT(*) as sets_done FROM GT_session_logs sl JOIN GT_workout_sessions ws ON sl.session_id = ws.id WHERE ws.user_id = ? GROUP BY exercise_name ORDER BY sets_done DESC LIMIT 1");
$stmtFav->execute([$user_id]);
$fav_exercise = $stmtFav->fetch() ?: ['exercise_name' => 'Sin datos', 'sets_done' => 0];

$active_page = 'perfil';
$page_title = 'Mi Perfil - GymTraker';

require_once 'includes/header.php';
?>


<!-- Modal: Editar Perfil -->
<div id="modalPerfil" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm p-4">
    <div class="flex min-h-full items-center justify-center">
        <div class="glass-panel w-full max-w-md bg-[#1e293b] border border-white/10 shadow-2xl relative">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
                <h2 class="text-xl font-black text-white">Configurar Perfil</h2>
                <button type="button" class="text-slate-400 hover:text-white text-3xl leading-none" onclick="closeModal()">&times;</button>
            </div>

            <form action="perfil" method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Tu Avatar</label>
                    <div class="grid grid-cols-5 gap-2" id="avatar-selector">
                        <?php 
                        $emojis = ['🦍', '🦁', '🐺', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐣', '🦖'];
                        foreach($emojis as $e): 
                        ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="avatar_emoji" value="<?= $e ?>" class="hidden peer" <?= $avatar == $e ? 'checked' : '' ?>>
                                <div class="w-12 h-12 flex items-center justify-center text-2xl bg-white/5 rounded-xl border border-white/10 peer-checked:bg-primary/20 peer-checked:border-primary transition-all hover:bg-white/10">
                                    <?= $e ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Altura (cm)</label>
                    <input type="number" step="0.1" name="height" class="form-control" value="<?= $altura ?: '' ?>" placeholder="178">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Objetivo Semanal (Días)</label>
                    <input type="number" name="weekly_goal" class="form-control" value="<?= $goal ?>" min="1" max="7">
                </div>
                <button type="submit" name="update_profile" class="w-full btn-primary uppercase tracking-widest">Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Perfil y Estadísticas <i class="fa-solid fa-user text-primary ml-1"></i></h1>
    <p class="text-slate-400">Personaliza tu experiencia y objetivos.</p>
</header>

<?php if($success): ?>
    <div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl mb-6 font-bold"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Card Principal de Perfil -->
    <div class="lg:col-span-1">
        <div class="glass-panel text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-blue-500"></div>
            <div class="w-24 h-24 bg-primary/10 border-2 border-primary rounded-full flex items-center justify-center text-4xl mx-auto mb-4 mt-2 shadow-[0_0_20px_rgba(34,197,94,0.2)]"><?= $avatar ?></div>
            <h2 class="text-2xl font-black text-white">@<?= htmlspecialchars($username) ?></h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-6"><?= htmlspecialchars($user_info['email']) ?></p>
            
            <div class="grid grid-cols-2 gap-4 border-t border-white/5 pt-6 mb-6">
                <div class="bg-white/5 p-3 rounded-xl border border-white/5">
                    <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Estado IMC</span>
                    <span class="text-sm font-black <?= $imc > 0 ? ($imc < 25 ? 'text-primary' : 'text-yellow-500') : 'text-slate-600' ?>">
                        <?= $imc > 0 ? number_format($imc, 1) : '--' ?>
                    </span>
                </div>
                <div class="bg-white/5 p-3 rounded-xl border border-white/5">
                    <span class="block text-[10px] text-slate-500 font-black uppercase tracking-widest mb-1">Meta Semanal</span>
                    <span class="text-sm font-black text-primary"><?= $goal ?> días</span>
                </div>
            </div>

            <button onclick="openModal()" class="w-full bg-white/5 border border-white/10 hover:bg-white/10 text-white py-3 rounded-xl font-bold transition-all text-xs uppercase tracking-widest">Editar Perfil</button>
        </div>
    </div>

    <!-- Stats y Actividad -->
    <div class="lg:col-span-2 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="glass-panel flex flex-col items-center justify-center p-4">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2">Peso Actual</span>
                <span class="text-3xl font-black text-primary"><?= $peso ?> <small class="text-xs opacity-50">kg</small></span>
                <a href="peso" class="text-[10px] text-blue-400 font-black uppercase mt-2 hover:underline">Gestionar Peso</a>
            </div>
            <div class="glass-panel flex flex-col items-center justify-center p-4">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2">Entrenos</span>
                <span class="text-3xl font-black text-primary"><?= $total_sessions ?></span>
            </div>
            <div class="glass-panel flex flex-col items-center justify-center p-4 col-span-2 md:col-span-1">
                <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2">Favorito</span>
                <span class="text-sm font-black text-white truncate w-full text-center"><?= $fav_exercise['exercise_name'] ?></span>
            </div>
        </div>

        <div class="glass-panel bg-gradient-to-br from-primary/10 to-blue-500/10 border-primary/20">
            <h3 class="text-base font-black text-white mb-2">Tu Espíritu Animal</h3>
            <p class="text-sm text-slate-400 mb-4">Has elegido el <?= $avatar ?> para representarte. ¡Mantén esa fuerza en cada entrenamiento!</p>
            <div class="flex gap-2">
                <?php for($i=1; $i<=7; $i++): ?>
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold <?= $i<=$goal ? 'bg-primary text-white' : 'bg-white/5 text-slate-600' ?>">
                        <?= $i ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal() { document.getElementById('modalPerfil').classList.remove('hidden'); }
    function closeModal() { document.getElementById('modalPerfil').classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>
