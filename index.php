<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$stmt = $pdo->prepare("SELECT balance, avatar, username FROM users WHERE id = ?");
$stmt->execute([$_SESSION]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lobby - Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #0a0a0a; color: #f0f0f0; font-family: 'Roboto', sans-serif; }
        header { background: #111; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d4af37; }
        .logo { font-family: 'Cinzel', serif; font-size: 24px; color: #d4af37; text-decoration: none; }
        .nav a { color: #f0f0f0; margin-left: 20px; text-decoration: none; }
        .nav a:hover { color: #d4af37; }
        .balance { color: #28a745; font-weight: bold; }
        .container { padding: 40px; display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; }
        .card { background: #1a1a1a; pborder: 1px solid #333; border-radius: 10px; width: 45%; padding: 20px; border-top: 4px solid #d4af37; transition: 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(212,175,55,0.1); }
        .card h2 { font-family: 'Cinzel', serif; color: #d4af37; margin-top: 0; }
        .room-list { max-height: 300px; overflow-y: auto; }
        .room { background: #222; margin: 10px 0; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; }
        .btn { background: #d4af37; color: #000; padding: 8px 15px; text-decoration: none; border-radius: 3px; font-weight: bold; }
        .status-dot { height: 10px; width: 10px; background-color: #28a745; border-radius: 50%; display: inline-block; animation: blink 1.5s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">♠ CASINO ROYAL ♥</a>
        <div class="nav">
            <span class="status-dot"></span> En vivo
            <span><?= $user ?> <?= $user ?> | Saldo: <span class="balance">€ <?= number_format($user, 2, ',', '.') ?></span></span>
            <a href="profile.php">Perfil</a>
            <a href="ranking.php">Ranking</a>
            <a href="logout.php">Salir</a>
        </div>
    </header>
    <div class="container">
        <div class="card">
            <h2>Blackjack 21</h2>
            <button onclick="createRoom('blackjack')" class="btn" style="margin-bottom:15px; background:#4CAF50; color:#fff; border:none; cursor:pointer;">+ Crear Sala</button>
            <div id="bj-rooms" class="room-list">Cargando salas...</div>
        </div>
        <div class="card">
            <h2>Texas Hold'em Póker</h2>
            <button onclick="createRoom('poker')" class="btn" style="margin-bottom:15px; background:#4CAF50; color:#fff; border:none; cursor:pointer;">+ Crear Sala</button>
            <div id="pk-rooms" class="room-list">Cargando salas...</div>
        </div>
    </div>

    <script>
        function fetchRooms() {
            fetch('/casino/api/rooms.php?action=list')
            .then(r => r.json())
            .then(data => {
                let bjHtml = ''; let pkHtml = '';
                data.forEach(room => {
                    let html = `<div class="room">
                        <div><strong>${room.name}</strong><br>
                        Apt. Min: €${room.min_bet} | Jugadores: ${room.players}/${room.max_players}</div>
                        <a href="${room.game_type}.php?room_id=${room.id}" class="btn">Entrar</a>
                    </div>`;
                    if(room.game_type === 'blackjack') bjHtml += html;
                    else pkHtml += html;
                });
                document.getElementById('bj-rooms').innerHTML = bjHtml || '<p>No hay salas activas.</p>';
                document.getElementById('pk-rooms').innerHTML = pkHtml || '<p>No hay salas activas.</p>';
            });
        }
        
        function createRoom(type) {
            fetch(`/casino/api/rooms.php?action=create&type=${type}`)
            .then(r => r.json()).then(d => { if(d.success) window.location.href = `${type}.php?room_id=${d.room_id}`; });
        }

        setInterval(fetchRooms, 2000); // AJAX Polling cada 2 seg
        fetchRooms();
    </script>
</body>
</html>
