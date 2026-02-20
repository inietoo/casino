<?php
// ── Seguridad de sesión ANTES de session_start() ──────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
// En producción con HTTPS, descomentar:
// ini_set('session.cookie_secure', 1);
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'casino');

try {
    // UTF8mb4 para soporte de emojis 🎲
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // prepared statements reales
} catch (PDOException $e) {
    // En producción no mostrar el mensaje real
    error_log("DB connection error: " . $e->getMessage());
    die("Error de conexión a la Base de Datos. Inténtalo más tarde.");
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
?>
