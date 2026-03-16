<?php
// google-login.php
require_once 'includes/db.php';
require_once 'includes/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];

    // Validar el token con Google API
    // En un entorno de producción con Composer, se usaría 'google/apiclient'
    // Como alternativa ligera, usamos file_get_contents con la URL de validación de Google
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = file_get_contents($url);
    $payload = json_decode($response, true);

    if ($payload && isset($payload['aud']) && $payload['aud'] === GOOGLE_CLIENT_ID) {
        $google_id = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'] ?? $payload['given_name'] ?? 'Usuario';

        // 1. Buscar si el usuario ya existe por google_id
        $stmt = $pdo->prepare("SELECT id, username, role, is_active FROM DT_users WHERE google_id = :google_id");
        $stmt->execute([':google_id' => $google_id]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['is_active'] == 0) {
                die("Tu cuenta ha sido desactivada por un administrador.");
            }
            // Usuario ya existe, iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: ./");
            exit;
        } else {
            // 2. Si no existe por google_id, ver si el email ya existe
            $stmt = $pdo->prepare("SELECT id, username, role, is_active FROM DT_users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $userByEmail = $stmt->fetch();

            if ($userByEmail) {
                if ($userByEmail['is_active'] == 0) {
                    die("Tu cuenta ha sido desactivada por un administrador.");
                }
                // El email existe pero no estaba vinculado. Lo vinculamos.
                $stmt = $pdo->prepare("UPDATE DT_users SET google_id = :google_id WHERE id = :user_id");
                $stmt->execute([':google_id' => $google_id, ':user_id' => $userByEmail['id']]);
                
                $_SESSION['user_id'] = $userByEmail['id'];
                $_SESSION['username'] = $userByEmail['username'];
                $_SESSION['role'] = $userByEmail['role'];
                header("Location: ./");
                exit;
            } else {
                // 3. El usuario no existe. Lo registramos.
                $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
                
                $stmt = $pdo->prepare("INSERT INTO DT_users (username, email, google_id, role) VALUES (:username, :email, :google_id, 'user')");
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':google_id' => $google_id
                ]);
                
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                header("Location: ./");
                exit;
            }
        }
    } else {
        die("Validación de token fallida.");
    }
} else {
    header("Location: login");
    exit;
}
