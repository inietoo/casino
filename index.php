<?php
require 'config.php';

if (!isLoggedIn()) { 
    header('Location: login.php'); 
    exit; 
}

// Parámetro corregido para la sesión
$user_id = $_SESSION;
$stmt = $pdo->prepare("SELECT balance, avatar, username FROM users WHERE id = ?");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Si la sesión existe pero el usuario no está en la BD
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lobby - Casino Royal</title>
    <!-- Vincula tu CSS Global -->
    <link rel="stylesheet" href="/casino/assets/css/style.css">
    <style>
        /* Estilos específicos del Header y Cartas del Lobby */
        header { background: var(--nav-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--gold); }
        .nav a { color: var(--text-main); margin-left: 20px; font-weight: bold; }
        .nav a:hover { color: var(--gold); }
        .balance { color: #28a745; font-weight: bold; }
        .container { padding: 40px; display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; }
        .card-panel { width: 45%; min-width: 320px; }
        .room-list { max-height: 350px; overflow-y: auto; text-align: left; margin-top: 15px; }
        .room-item { background: #222; margin: 10px 0; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; border-left: 4px solid var(--gold); }
        .status-dot { height: 10px; width: 10px; background-color: #28a745; border-radius: 50%; display: inline-block; animation: blink 1.5s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        .empty-msg { color: #888; font-style: italic; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo" style="font-size: 24px;">♠ CASINO ROYAL ♥</a>
        <div class="nav">
            <span class="status-dot"></span> <span style="font-size: 14px; margin-right: 15px;">En vivo</span>
            <span>
                <?= htmlspecialchars($user) ?> <?= htmlspecialchars($user) ?> 
                | Saldo: <span class="balance">€ <?= number_format($user, 2, ',', '.') ?></span>
            </span>
            <a href="profile.php">Perfil</a>
            <a href="ranking.php">Ranking</a>
            
            <!-- Botón de recarga configurado para invocar la API -->
            <button onclick="reloadFreeBalance()" class="btn" style="padding: 6px 12px; font-size: 12px; margin-left: 20px; background: #28a745; color: white;">Recargar €500</button>
            <a href="logout.php" style="color: #dc3545;">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="card-panel">
            <h2 style="text-align: center; margin-top: 0;">Blackjack 21</h2>
            <button onclick="createRoom('blackjack')" class="btn" style="background:#28a745; color:#fff; width: 100%;">+ Crear Nueva Mesa</button>
            <div id="bj-rooms" class="room-list">Cargando salas...</div>
        </div>
        
        <div class="card-panel">
            <h2 style="text-align: center; margin-top: 0;">Texas Hold'em Póker</h2>
            <button onclick="createRoom('poker')" class="btn" style="background:#28a745; color:#fff; width: 100%;">+ Crear Nueva Mesa</button>
            <div id="pk-rooms" class="room-list">Cargando salas...</div>
        </div>
    </div>

    <!-- Vincula tu JS de MAIN (donde están las funciones de createRoom, fetchRooms y reloadFreeBalance) -->
    <script src="/casino/assets/js/main.js"></script>
</body>
</html>
