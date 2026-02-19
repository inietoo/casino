<?php
require 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ✅ CORREGIDO: usar $_SESSION['user_id'] no $_SESSION entero
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT balance, avatar, username FROM users WHERE id = ?");
// ✅ CORREGIDO: pasar el parámetro al execute
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
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
    <link rel="stylesheet" href="/casino/assets/css/style.css">
    <style>
        header { background: var(--nav-bg, #111); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d4af37; }
        .nav a { color: #f0f0f0; margin-left: 20px; font-weight: bold; text-decoration: none; }
        .nav a:hover { color: #d4af37; }
        .balance { color: #28a745; font-weight: bold; }
        .container { padding: 40px; display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; }
        .card-panel { width: 45%; min-width: 320px; background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37; border-radius: 10px; padding: 25px; }
        .card-panel h2 { font-family: 'Cinzel', serif; color: #d4af37; text-align: center; margin-top: 0; }
        .room-list { max-height: 350px; overflow-y: auto; margin-top: 15px; }
        .room-item { background: #222; margin: 10px 0; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; border-left: 4px solid #d4af37; }
        .btn { background: #d4af37; color: #000; padding: 8px 15px; border: none; border-radius: 3px; font-weight: bold; cursor: pointer; text-decoration: none; }
        .status-dot { height: 10px; width: 10px; background-color: #28a745; border-radius: 50%; display: inline-block; animation: blink 1.5s infinite; }
        @keyframes blink { 0%{opacity:1;} 50%{opacity:0.4;} 100%{opacity:1;} }
        .logo { font-family: 'Cinzel', serif; color: #d4af37; text-decoration: none; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo" style="font-size: 24px;">&#9824; CASINO ROYAL &#9829;</a>
        <div class="nav">
            <span class="status-dot"></span>
            <span style="font-size: 13px; margin: 0 15px;">
                <!-- ✅ CORREGIDO: acceso a campos concretos del array $user -->
                <?= htmlspecialchars($user['avatar']) ?>
                <?= htmlspecialchars($user['username']) ?>
                | Saldo: <span class="balance">&euro; <?= number_format($user['balance'], 2, ',', '.') ?></span>
            </span>
            <a href="profile.php">Perfil</a>
            <a href="ranking.php">Ranking</a>
            <a href="logout.php" style="color:#dc3545;">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="card-panel">
            <h2>Blackjack 21</h2>
            <button onclick="createRoom('blackjack')" class="btn" style="background:#28a745; color:#fff; width:100%;">+ Crear Nueva Mesa</button>
            <div id="bj-rooms" class="room-list">Cargando salas...</div>
        </div>
        <div class="card-panel">
            <h2>Texas Hold'em Póker</h2>
            <button onclick="createRoom('poker')" class="btn" style="background:#28a745; color:#fff; width:100%;">+ Crear Nueva Mesa</button>
            <div id="pk-rooms" class="room-list">Cargando salas...</div>
        </div>
    </div>

    <script src="/casino/assets/js/main.js"></script>
</body>
</html>
