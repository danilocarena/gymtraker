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
        $stmt = $pdo->prepare("SELECT id, username FROM GT_users WHERE google_id = :google_id");
        $stmt->execute([':google_id' => $google_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Usuario ya existe, iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: ./");
            exit;
        } else {
            // 2. Si no existe por google_id, ver si el email ya existe
            $stmt = $pdo->prepare("SELECT id, username FROM GT_users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $userByEmail = $stmt->fetch();

            if ($userByEmail) {
                // El email existe pero no estaba vinculado. Lo vinculamos.
                $stmt = $pdo->prepare("UPDATE GT_users SET google_id = :google_id WHERE id = :user_id");
                $stmt->execute([':google_id' => $google_id, ':user_id' => $userByEmail['id']]);
                
                $_SESSION['user_id'] = $userByEmail['id'];
                $_SESSION['username'] = $userByEmail['username'];
                header("Location: ./");
                exit;
            } else {
                // 3. El usuario no existe. Lo registramos.
                // Generamos un username único basado en el nombre o email
                $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
                
                $stmt = $pdo->prepare("INSERT INTO GT_users (username, email, google_id) VALUES (:username, :email, :google_id)");
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':google_id' => $google_id
                ]);
                
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
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
