<?php
// includes/auth.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login');
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ./');
        exit;
    }
}

function calculateBMI($weight, $height) {
    if ($weight > 0 && $height > 0) {
        $height_m = $height / 100;
        return round($weight / ($height_m * $height_m), 1);
    }
    return 0;
}

function getBMICategory($imc) {
    if ($imc <= 0) return ['label' => 'Sin datos', 'color' => '#64748b'];
    if ($imc < 18.5) return ['label' => 'Bajo Peso', 'color' => '#3b82f6'];
    if ($imc < 25) return ['label' => 'Normal', 'color' => '#22c55e'];
    if ($imc < 30) return ['label' => 'Sobrepeso', 'color' => '#eab308'];
    return ['label' => 'Obesidad', 'color' => '#ef4444'];
}
?>
