<?php
require_once 'includes/db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Por favor, completa todos los campos.";
    } elseif ($password != $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verificar si el usuario o el email ya existen
        $stmt = $pdo->prepare("SELECT id FROM DT_users WHERE username = :username OR email = :email");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = "El usuario o correo electrónico ya está registrado.";
        } else {
            // Insertar nuevo usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO DT_users (username, email, password_hash) VALUES (:username, :email, :password)");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);

            if ($stmt->execute()) {
                $success = "¡Registro exitoso! Ya puedes iniciar sesión.";
            } else {
                $error = "Algo salió mal. Por favor, intenta de nuevo más tarde.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regístrate - DayTraker Pro</title>
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
        <h2 class="text-3xl font-extrabold text-primary mb-2">Crear Cuenta</h2>
        <p class="text-slate-400 mb-8 text-sm">Únete y organiza tu vida hoy mismo</p>

        <?php if (!empty($error)) : ?>
            <div class="bg-red-500/10 text-red-500 p-3 rounded-lg mb-6 font-semibold text-sm"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)) : ?>
            <div class="bg-primary/10 text-primary p-6 rounded-lg mb-6">
                <p class="font-bold mb-4"><?php echo $success; ?></p>
                <a href="login" class="btn-primary block no-underline">Ir al Login</a>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="text-left space-y-4">
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Usuario</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn-primary mt-4">Registrarse</button>
            </form>

            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-white/10"></span></div>
                <div class="relative flex justify-center text-xs uppercase"><span class="bg-[#1e293b] px-2 text-slate-500">O crea cuenta con</span></div>
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
                 data-text="signup_with"
                 data-shape="rectangular"
                 data-logo_alignment="left">
            </div>

            <div class="mt-8 text-slate-500 text-sm">
                ¿Ya tienes cuenta? <a href="login" class="text-primary font-bold hover:underline">Inicia Sesión aquí</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
