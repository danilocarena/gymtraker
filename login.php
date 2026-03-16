<?php
require_once 'includes/db.php';
session_start();

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        $error = "Por favor, ingresa tu usuario y contraseña.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM DT_users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            if ($row = $stmt->fetch()) {
                if ($row['is_active'] == 0) {
                    $error = "Tu cuenta ha sido desactivada por un administrador.";
                } else {
                    $id = $row['id'];
                    $hashed_password = $row['password_hash'];
                    if (password_verify($password, $hashed_password)) {
                        // Contraseña correcta
                        $_SESSION['user_id'] = $id;
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];
                        header("Location: ./");
                        exit;
                    } else {
                        $error = "La contraseña no es válida.";
                    }
                }
            }
        } else {
            $error = "No existe una cuenta con ese nombre de usuario.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicia Sesión - DayTraker Pro</title>
    <link rel="icon" type="image/png" href="components/favicon.png">
    
    <!-- App Mode Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DayTraker">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="components/favicon.png">
    <link rel="manifest" href="manifest.json">

    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#22c55e',
                        'primary-hover': '#16a34a',
                        'bg-color': '#0f172a',
                        'panel-bg': 'rgba(30, 41, 59, 0.7)',
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body {
                @apply bg-bg-color text-slate-50 font-sans min-h-screen flex items-center justify-center p-4;
                background-image: radial-gradient(circle at 15% 50%, rgba(34, 197, 94, 0.08) 0%, transparent 50%), radial-gradient(circle at 85% 30%, rgba(59, 130, 246, 0.08) 0%, transparent 50%);
            }
        }
        @layer components {
            .glass-panel { @apply bg-panel-bg backdrop-blur-xl border border-white/10 rounded-2xl p-8 shadow-[0_4px_30px_rgba(0,0,0,0.1)] w-full max-w-[400px] text-center; }
            .form-control { @apply w-full p-3 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-primary transition-all; }
            .btn-primary { @apply w-full bg-primary text-white py-3 rounded-lg font-bold hover:bg-primary-hover transition-all shadow-lg; }
        }
    </style>

</head>
<body>
    <div class="glass-panel">
        <h2 class="text-3xl font-extrabold text-primary mb-2">DayTraker</h2>
        <p class="text-slate-400 mb-8 text-sm">Ingresa para ver tu progreso</p>

        <?php if (!empty($error)) : ?>
            <div class="bg-red-500/10 text-red-500 p-3 rounded-lg mb-6 font-semibold text-sm"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="text-left space-y-5">
            <div>
                <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Usuario</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div>
                <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary mt-2">Iniciar Sesión</button>
        </form>

        <div class="relative my-8">
            <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-white/10"></span></div>
            <div class="relative flex justify-center text-xs uppercase"><span class="bg-[#1e293b] px-2 text-slate-500">O continúa con</span></div>
        </div>

        <div id="g_id_onload"
             data-client_id="<?php require_once 'includes/config.php'; echo GOOGLE_CLIENT_ID; ?>"
             data-login_uri="https://<?php echo $_SERVER['HTTP_HOST']; ?>/google-login.php"
             data-auto_prompt="false">
        </div>
        <div class="g_id_signin flex justify-center"
             data-type="standard"
             data-size="large"
             data-theme="outline"
             data-text="sign_in_with"
             data-shape="rectangular"
             data-logo_alignment="left">
        </div>

        <div class="mt-8 text-slate-500 text-sm">
            ¿No tienes cuenta? <a href="register" class="text-primary font-bold hover:underline">Regístrate aquí</a>
        </div>
    </div>
</body>
</html>
