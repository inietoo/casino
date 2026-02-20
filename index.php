<?php
require 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT balance, avatar, username FROM users WHERE id = ?");
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
        .card-panel { width: 30%; min-width: 320px; background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37; border-radius: 10px; padding: 25px; }
        .card-panel h2 { font-family: 'Cinzel', serif; color: #d4af37; text-align: center; margin-top: 0; margin-bottom: 20px; }
        .room-list { max-height: 350px; overflow-y: auto; margin-top: 15px; }
        .room-item { background: #222; margin: 10px 0; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; border-left: 4px solid #d4af37; }
        .btn { background: #d4af37; color: #000; padding: 8px 15px; border: none; border-radius: 3px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn:hover { background: #fff; }
        .status-dot { height: 10px; width: 10px; background-color: #28a745; border-radius: 50%; display: inline-block; animation: blink 1.5s infinite; }
        @keyframes blink { 0%{opacity:1;} 50%{opacity:0.4;} 100%{opacity:1;} }
        .logo { font-family: 'Cinzel', serif; color: #d4af37; text-decoration: none; }

        /* Estilos Tragaperras */
        .slot-machine { background: #000; border: 4px solid #d4af37; border-radius: 10px; padding: 15px; text-align: center; margin-bottom: 15px; box-shadow: inset 0 0 20px rgba(212,175,55,0.2); }
        .reels-container { display: flex; justify-content: center; gap: 10px; margin-bottom: 15px; }
        .reel { background: #fff; width: 70px; height: 90px; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 45px; border: 2px solid #555; box-shadow: inset 0 5px 10px rgba(0,0,0,0.3); }
        .slot-btn { background: linear-gradient(180deg, #ff4d4d, #c82333); color: white; width: 100%; padding: 15px; font-size: 18px; border-radius: 50px; border: 2px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 1px; }
        .slot-btn:hover { background: linear-gradient(180deg, #ff6666, #dc3545); transform: scale(1.02); }
        .slot-btn:disabled { background: #555; border-color: #333; cursor: not-allowed; transform: none; }
        .slot-msg { font-size: 14px; font-weight: bold; min-height: 20px; margin-top: 10px; color: #aaa; }
        .win-anim { animation: winPulse 0.5s infinite alternate; color: #00ff80 !important; font-size: 18px; }
        @keyframes winPulse { from { text-shadow: 0 0 10px #00ff80; transform: scale(1); } to { text-shadow: 0 0 20px #00ff80; transform: scale(1.1); } }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo" style="font-size: 24px;">&#9824; CASINO ROYAL &#9829;</a>
        <div class="nav">
            <span class="status-dot"></span>
            <span style="font-size: 13px; margin: 0 15px;">
                <?= htmlspecialchars($user['avatar']) ?>
                <?= htmlspecialchars($user['username']) ?>
                | Saldo: <span class="balance" id="header-balance">&euro; <?= number_format($user['balance'], 2, ',', '.') ?></span>
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

        <div class="card-panel" style="border-top-color: #ff4d4d;">
            <h2 style="color: #ff4d4d;">Tragaperras (10€)</h2>
            <div class="slot-machine">
                <div class="reels-container">
                    <div class="reel" id="reel1">🍒</div>
                    <div class="reel" id="reel2">💎</div>
                    <div class="reel" id="reel3">🍋</div>
                </div>
                <button id="spin-btn" class="btn slot-btn" onclick="spinSlots()">🎰 TIRAR (10€)</button>
                <div id="slot-msg" class="slot-msg">¡Prueba tu suerte! 💎=500€</div>
            </div>
        </div>

        <div class="card-panel" style="border-top-color: #28a745;">
            <h2 style="color: #28a745;">Bizum Casino 💸</h2>
            <div style="background: #222; padding: 15px; border-radius: 8px; border: 1px solid #333;">
                <p style="font-size: 12px; color: #aaa; margin-bottom: 15px; text-align: center;">¡Pásale saldo a un colega al instante!</p>
                
                <input type="text" id="transfer-user" placeholder="Usuario del colega" 
                       style="width: 100%; padding: 10px; margin-bottom: 10px; background: #111; border: 1px solid #444; color: #fff; border-radius: 5px;">
                
                <input type="number" id="transfer-amount" placeholder="Cantidad (€)" min="1"
                       style="width: 100%; padding: 10px; margin-bottom: 15px; background: #111; border: 1px solid #444; color: #fff; border-radius: 5px;">
                
                <button id="transfer-btn" onclick="sendBizum()" class="btn" style="background: #28a745; color: #fff; width: 100%; padding: 12px;">
                    ENVIAR DINERO
                </button>
                
                <div id="transfer-msg" style="margin-top: 10px; font-size: 13px; text-align: center; font-weight: bold;"></div>
            </div>
        </div>

        <div class="card-panel" style="border-top-color: #17a2b8;">
            <h2 style="color: #17a2b8;">Texas Hold'em</h2>
            <button onclick="createRoom('poker')" class="btn" style="background:#17a2b8; color:#fff; width:100%;">+ Crear Nueva Mesa</button>
            <div id="pk-rooms" class="room-list">Cargando salas...</div>
        </div>
    </div>

    <script src="/casino/assets/js/main.js"></script>

    <script>
        // Lógica de la Tragaperras
        const slotSymbols = ['🍒', '🍋', '🍉', '🔔', '💎'];
        let spinInterval;

        function spinSlots() {
            const btn = document.getElementById('spin-btn');
            const msg = document.getElementById('slot-msg');
            const reels = [document.getElementById('reel1'), document.getElementById('reel2'), document.getElementById('reel3')];
            
            btn.disabled = true;
            msg.className = 'slot-msg';
            msg.textContent = 'Girando rodillos...';

            spinInterval = setInterval(() => {
                reels.forEach(r => r.textContent = slotSymbols[Math.floor(Math.random() * slotSymbols.length)]);
            }, 100);

            fetch('api/slots.php', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    setTimeout(() => {
                        clearInterval(spinInterval);
                        
                        if (data.error) {
                            msg.textContent = data.error;
                            msg.style.color = '#ff4d4d';
                            btn.disabled = false;
                            return;
                        }

                        reels[0].textContent = data.reels[0];
                        reels[1].textContent = data.reels[1];
                        reels[2].textContent = data.reels[2];

                        document.getElementById('header-balance').innerHTML = '&euro; ' + parseFloat(data.new_balance).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                        if (data.win > 10) {
                            msg.innerHTML = `¡JACKPOT! Has ganado ${data.win}€ 🤑`;
                            msg.className = 'slot-msg win-anim';
                        } else if (data.win === 10) {
                            msg.textContent = `¡Casi! Recuperas tus 10€ 🤝`;
                            msg.style.color = '#17a2b8';
                        } else {
                            msg.textContent = `No hubo suerte. ¡Vuelve a tirar!`;
                            msg.style.color = '#aaa';
                        }

                        btn.disabled = false;
                    }, 1000);
                })
                .catch(err => {
                    clearInterval(spinInterval);
                    msg.textContent = 'Error de conexión';
                    btn.disabled = false;
                });
        }

        // Lógica del Bizum Casino
        function sendBizum() {
            const user = document.getElementById('transfer-user').value;
            const amount = document.getElementById('transfer-amount').value;
            const msg = document.getElementById('transfer-msg');
            const btn = document.getElementById('transfer-btn');

            if (!user || !amount) {
                msg.style.color = '#ff4d4d';
                msg.textContent = 'Rellena todos los campos';
                return;
            }

            if (!confirm(`¿Seguro que quieres enviarle ${amount}€ a ${user}?`)) return;

            btn.disabled = true;
            msg.style.color = '#aaa';
            msg.textContent = 'Procesando envío...';

            const formData = new FormData();
            formData.append('username', user);
            formData.append('amount', amount);

            fetch('api/transfer.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        msg.style.color = '#ff4d4d';
                        msg.textContent = data.error;
                    } else {
                        msg.style.color = '#28a745';
                        msg.textContent = `¡Enviado! Tu nuevo saldo: €${data.new_balance.toFixed(2)}`;
                        document.getElementById('header-balance').innerHTML = '&euro; ' + data.new_balance.toLocaleString('es-ES', {minimumFractionDigits: 2});
                        document.getElementById('transfer-user').value = '';
                        document.getElementById('transfer-amount').value = '';
                    }
                    btn.disabled = false;
                })
                .catch(() => {
                    msg.textContent = 'Error de conexión';
                    btn.disabled = false;
                });
        }
    </script>
</body>
</html>
