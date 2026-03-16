<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$user_id = getCurrentUserId();

$active_page = 'historial';
$page_title = 'Historial de Actividad - GymTraker';

// Eliminar sesión si se solicita
if (isset($_POST['delete_session'])) {
    $session_id = intval($_POST['session_id']);
    $stmt = $pdo->prepare("DELETE FROM GT_workout_sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$session_id, $user_id]);
    header("Location: historial");
    exit;
}

// Obtener todas las sesiones
$stmt = $pdo->prepare("
    SELECT ws.id, ws.session_name, ws.session_date,
           (SELECT COUNT(DISTINCT exercise_name) FROM GT_session_logs WHERE session_id = ws.id) as exercise_count,
           (SELECT COUNT(*) FROM GT_session_logs WHERE session_id = ws.id) as series_count,
           (SELECT SUM(reps * weight) FROM GT_session_logs WHERE session_id = ws.id) as total_volume
    FROM GT_workout_sessions ws
    WHERE ws.user_id = ?
    ORDER BY ws.session_date DESC, ws.id DESC
");
$stmt->execute([$user_id]);
$sessions = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<header class="mb-8">
    <h1 class="text-3xl font-extrabold mb-1">Historial de Actividad <i class="fa-solid fa-clock-rotate-left text-primary ml-1"></i></h1>
    <p class="text-slate-400">Revisa y edita tus entrenamientos pasados.</p>
</header>

<div class="space-y-4">
    <?php if (empty($sessions)): ?>
        <div class="glass-panel text-center py-10">
            <p class="text-slate-500 italic">No has registrado ningún entrenamiento todavía.</p>
            <a href="sesion_nueva" class="btn-primary mt-4 inline-block">Empezar a entrenar</a>
        </div>
    <?php else: ?>
        <?php foreach ($sessions as $session): ?>
            <div class="glass-panel hover:border-white/20 transition-all group">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-xl text-primary shrink-0">
                            <i class="fa-solid fa-dumbbell"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg group-hover:text-primary transition-colors">
                                <?= htmlspecialchars($session['session_name'] ?: 'Entrenamiento') ?>
                            </h3>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 font-medium">
                                <span><i class="fa-solid fa-calendar-day mr-1"></i> <?= date('d M, Y', strtotime($session['session_date'])) ?></span>
                                <span><i class="fa-solid fa-layer-group mr-1"></i> <?= $session['exercise_count'] ?> Ejercicios</span>
                                <span><i class="fa-solid fa-list-ol mr-1"></i> <?= $session['series_count'] ?> Series</span>
                                <span><i class="fa-solid fa-weight-hanging mr-1"></i> <?= number_format($session['total_volume'], 0) ?> kg volumen</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full md:w-auto mt-2 md:mt-0">
                        <a href="editar_sesion.php?id=<?= $session['id'] ?>" class="flex-1 md:flex-none text-center bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">
                            <i class="fa-solid fa-pen-to-square mr-1"></i> Editar
                        </a>
                        <form action="historial" method="POST" class="flex-1 md:flex-none" onsubmit="return confirm('¿Seguro que quieres borrar este entrenamiento?')">
                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                            <button type="submit" name="delete_session" class="w-full text-center bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">
                                <i class="fa-solid fa-trash-can mr-1"></i> Borrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
