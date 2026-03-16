<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireAdmin();

$active_page = 'admin_users';
$page_title = 'Gestión de Usuarios - Admin';

// Manejar cambios de estado (Activar/Desactivar/Eliminar)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    // Evitar que el admin se desactive a sí mismo
    if ($target_id != getCurrentUserId()) {
        if ($_GET['action'] == 'toggle') {
            $stmt = $pdo->prepare("UPDATE GT_users SET is_active = 1 - is_active WHERE id = ?");
            $stmt->execute([$target_id]);
        } elseif ($_GET['action'] == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM GT_users WHERE id = ?");
            $stmt->execute([$target_id]);
        }
    }
    header("Location: admin_users");
    exit;
}

// Obtener lista de usuarios
$users = $pdo->query("SELECT id, username, email, role, is_active, created_at FROM GT_users ORDER BY id DESC")->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-black text-white tracking-tight uppercase">Gestión de Usuarios</h2>
            <p class="text-slate-400 text-sm">Controla el acceso y estado de los miembros.</p>
        </div>
    </div>

    <div class="glass-panel overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5">
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">ID</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Usuario</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Email</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Rol</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Estado</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Registro</th>
                        <th class="p-4 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-white/10">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="p-4 text-sm text-slate-400 font-mono"><?= $user['id'] ?></td>
                        <td class="p-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($user['username']) ?></div>
                        </td>
                        <td class="p-4 text-sm text-slate-400"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?= $user['role'] == 'admin' ? 'bg-primary/20 text-primary' : 'bg-blue-500/20 text-blue-400' ?>">
                                <?= $user['role'] ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <?php if ($user['is_active']): ?>
                                <span class="flex items-center gap-1.5 text-green-500 text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Activo
                                </span>
                            <?php else: ?>
                                <span class="flex items-center gap-1.5 text-red-500 text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactivo
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-slate-500"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <?php if ($user['id'] != getCurrentUserId()): ?>
                                    <a href="admin_users?action=toggle&id=<?= $user['id'] ?>" class="p-2 <?= $user['is_active'] ? 'text-orange-400 hover:bg-orange-400/10' : 'text-green-400 hover:bg-green-400/10' ?> rounded-lg transition-all" title="<?= $user['is_active'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fa-solid <?= $user['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    </a>
                                    <a href="admin_users?action=delete&id=<?= $user['id'] ?>" onclick="return confirm('¿Seguro que quieres eliminar este usuario?')" class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-600 font-bold uppercase">Eres tú</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
