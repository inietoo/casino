<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$room_id = (int)($_GET['room_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Jugador';

if (!$room_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room || $room['game_type'] !== 'bingo') { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bingo — <?= htmlspecialchars($room['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #1a0b2e; color: #fff; font-family: 'Roboto', sans-serif; display: flex; height: 100vh; overflow: hidden; }

        .game-area { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; padding: 15px 20px; overflow-y: auto; background: radial-gradient(circle at top, #2d1055, #0a0412); }
        .header { width: 100%; display: flex; justify-content: space-between; align-items: center; color: #f8c8dc; font-family: 'Cinzel', serif; margin-bottom: 20px; }
        .btn-game { background: #9c27b0; color: #fff; border: none; padding: 10px 18px; border-radius: 7px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; text-transform: uppercase; box-shadow: 0 4px 10px rgba(156, 39, 176, 0.4); }
        .btn-game:hover { transform: scale(1.05); background: #ba68c8; }
        .btn-game:disabled { background: #444 !important; color: #888 !important; cursor: not-allowed; transform: none; box-shadow: none; }

        .top-info { display: flex; width: 100%; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
        
        .balls-container { flex: 1; background: rgba(0,0,0,0.5); border-radius: 12px; padding: 15px; border: 2px solid #9c27b0; text-align: center; }
        .current-ball { font-size: 60px; font-weight: 900; color: #fff; background: radial-gradient(circle, #e91e63, #880e4f); display: inline-flex; align-items: center; justify-content: center; width: 100px; height: 100px; border-radius: 50%; border: 4px solid #f8c8dc; box-shadow: 0 0 20px #e91e63; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); margin-bottom: 10px; }
        .history-balls { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
        .mini-ball { width: 35px; height: 35px; border-radius: 50%; background: #fff; color: #000; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 2px solid #ccc; opacity: 0.8; }

        .cards-area { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; width: 100%; margin-bottom: 80px; }
        .bingo-card { background: #fff; color: #000; border-radius: 10px; padding: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); width: 220px; }
        .bingo-header { display: grid; grid-template-columns: repeat(5, 1fr); text-align: center; font-weight: 900; font-size: 22px; color: #fff; background: #9c27b0; border-radius: 5px 5px 0 0; padding: 5px 0; }
        .bingo-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-top: 5px; }
        .bingo-cell { border: 2px solid #ddd; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold; border-radius: 4px; position: relative; }
        .bingo-cell.marked::after { content: ''; position: absolute; top: 10%; left: 10%; right: 10%; bottom: 10%; background: rgba(233, 30, 99, 0.6); border-radius: 50%; border: 2px solid #e91e63; }
        .bingo-cell.free { background: #f0f0f0; font-size: 12px; color: #9c27b0; }
        .bingo-cell.free.marked::after { background: rgba(156, 39, 176, 0.6); border-color: #9c27b0; }

        .players-panel { width: 250px; background: rgba(0,0,0,0.5); border-radius: 12px; padding: 15px; border: 2px solid #9c27b0; }
        .players-panel h3 { color: #f8c8dc; margin-bottom: 10px; font-family: 'Cinzel', serif; font-size: 16px; border-bottom: 1px solid #444; padding-bottom: 5px; }
        .player-item { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; border-bottom: 1px dashed #333; padding-bottom: 4px; }
        .pot-display { font-size: 24px; color: #00ff80; font-weight: bold; text-align: center; margin-bottom: 15px; font-family: 'Cinzel', serif; }

        .controls-bar { position: fixed; bottom: 20px; background: rgba(0,0,0,0.9); padding: 15px 25px; border-radius: 12px; display: flex; gap: 15px; align-items: center; border: 1px solid #9c27b0; box-shadow: 0 5px 20px rgba(0,0,0,0.8); z-index: 100; }
        
        .chat-area { width: 290px; background: #111; border-left: 2px solid #9c27b0; display: flex; flex-direction: column; }
        .chat-header { background: #1a1a1a; padding: 12px 15px; color: #f8c8dc; font-family: 'Cinzel', serif; font-size: 13px; border-bottom: 1px solid #9c27b0; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 12px; font-size: 13px; line-height: 1.5; }
        .chat-input-area { padding: 10px; display: flex; gap: 6px; background: #0a0a0a; border-top: 1px solid #333; }
        .chat-input-area input { flex: 1; padding: 8px 10px; border: 1px solid #444; background: #1a1a1a; color: #fff; border-radius: 5px; }
        .chat-send-btn { background: #9c27b0; color: #fff; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }

        .winner-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); background: rgba(0,0,0,0.95); border: 4px solid #e91e63; border-radius: 15px; padding: 40px; text-align: center; z-index: 200; display: none; }
        .winner-overlay h2 { color: #00ff80; font-size: 40px; font-family: 'Cinzel', serif; margin-bottom: 10px; animation: pulse 1s infinite alternate; }
        @keyframes pulse { from { text-shadow: 0 0 10px #00ff80; } to { text-shadow: 0 0 30px #00ff80, 0 0 10px #fff; } }
    </style>
</head>
<body>
    <div class="game-area">
        <div class="header">
            <button onclick="leaveRoom()" class="btn-game" style="background:#dc3545; font-size:12px; padding:6px 12px;">← Lobby</button>
            <div style="font-size:16px;">🎱 <?= htmlspecialchars($room['name']) ?></div>
            <div id="phase-label" style="font-size:14px; color:#aaa;">Esperando...</div>
        </div>

        <div class="top-info">
            <div class="players-panel">
                <div class="pot-display" id="pot">BOTE: €0.00</div>
                <h3>Jugadores (<span id="player-count">0</span>)</h3>
                <div id="players-list"></div>
            </div>

            <div class="balls-container">
                <div style="color:#aaa; font-size:12px; margin-bottom:5px; text-transform:uppercase;">Última Bola</div>
                <div class="current-ball" id="current-ball">--</div>
                <div class="history-balls" id="history-balls"></div>
            </div>
        </div>

        <div class="cards-area" id="my-cards"></div>

        <div class="controls-bar" id="controls-bar"></div>

        <div class="winner-overlay" id="winner-overlay">
            <h2>¡BINGO!</h2>
            <div id="winner-text" style="font-size: 20px; margin-bottom: 20px;"></div>
            <button class="btn-game" onclick="document.getElementById('winner-overlay').style.display='none'">Cerrar</button>
            <button class="btn-game" id="btn-new-round" style="display:none; background:#28a745;" onclick="bingoAction('new_round')">Nueva Ronda</button>
        </div>
    </div>

    <div class="chat-area">
        <div class="chat-header">💬 Chat Bingo</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input-area">
            <input type="text" id="chat-msg" placeholder="Escribe..." onkeypress="if(event.key==='Enter') sendChat()">
            <button class="chat-send-btn" onclick="sendChat()">›</button>
        </div>
    </div>

    <script>
        const roomId = <?= $room_id ?>;
        const myUserId = <?= $user_id ?>;
        let lastPhase = '';
        let knownDrawnCount = 0;

        function renderCard(cardData, drawnNumbers) {
            let html = `<div class="bingo-card">
                            <div class="bingo-header"><div>B</div><div>I</div><div>N</div><div>G</div><div>O</div></div>
                            <div class="bingo-grid">`;
            cardData.forEach(num => {
                const isFree = num === 'FREE';
                const isMarked = isFree || drawnNumbers.includes(num);
                const classes = `bingo-cell ${isFree ? 'free' : ''} ${isMarked ? 'marked' : ''}`;
                html += `<div class="${classes}">${isFree ? '★' : num}</div>`;
            });
            html += `</div></div>`;
            return html;
        }

        function updateBingo() {
            fetch(`api/bingo_action.php?action=state&room_id=${roomId}&_=${Date.now()}`).then(r => r.json()).then(state => {
                if(state.error) return;
                
                const phase = state.phase || 'waiting';
                const players = state.players || {};
                const drawn = state.drawn || [];
                const pot = state.pot || 0;
                
                document.getElementById('phase-label').innerText = phase === 'waiting' ? 'Fase de Compra' : (phase === 'playing' ? 'Sorteo en curso' : 'Ronda Finalizada');
                document.getElementById('pot').innerText = `BOTE: €${parseFloat(pot).toFixed(2)}`;
                document.getElementById('player-count').innerText = state.player_count;

                if (drawn.length > 0) {
                    document.getElementById('current-ball').innerText = drawn[drawn.length - 1];
                    if (drawn.length !== knownDrawnCount) {
                        let histHtml = '';
                        const recent = drawn.slice(Math.max(0, drawn.length - 15), drawn.length - 1).reverse();
                        recent.forEach(b => histHtml += `<div class="mini-ball">${b}</div>`);
                        document.getElementById('history-balls').innerHTML = histHtml;
                        knownDrawnCount = drawn.length;
                    }
                } else {
                    document.getElementById('current-ball').innerText = '--';
                    document.getElementById('history-balls').innerHTML = '';
                    knownDrawnCount = 0;
                }

                let pList = '';
                for(const uid in players) {
                    const p = players[uid];
                    const count = p.cards ? p.cards.length : (p.cards_count || 0);
                    pList += `<div class="player-item">
                                <span>${p.avatar} ${p.username}</span>
                                <span style="color:#f8c8dc;">${count} cartones</span>
                              </div>`;
                }
                document.getElementById('players-list').innerHTML = pList;

                const myPlayer = players[myUserId];
                if (myPlayer && myPlayer.cards) {
                    let cardsHtml = '';
                    myPlayer.cards.forEach(c => cardsHtml += renderCard(c, drawn));
                    document.getElementById('my-cards').innerHTML = cardsHtml;
                } else {
                    document.getElementById('my-cards').innerHTML = '<div style="color:#aaa; font-style:italic; margin-top:20px;">No has comprado cartones.</div>';
                }

                let controls = '';
                if (phase === 'waiting') {
                    controls += `<button class="btn-game" onclick="bingoAction('buy')">🛒 Comprar Cartón (10€)</button>`;
                    if (state.is_creator) controls += `<button class="btn-game" style="background:#28a745;" onclick="bingoAction('start')">🎮 Empezar Sorteo</button>`;
                } else if (phase === 'finished' && state.is_creator) {
                    controls += `<button class="btn-game" style="background:#28a745;" onclick="bingoAction('new_round')">🔄 Nueva Ronda</button>`;
                } else if (phase === 'playing') {
                    controls += `<span style="color:#aaa;">Sorteo automático en curso... Bolas extraídas: ${drawn.length}/75</span>`;
                }
                document.getElementById('controls-bar').innerHTML = controls;

                if (phase === 'finished' && lastPhase !== 'finished') {
                    const winners = state.winners || [];
                    const names = winners.map(w => players[w].username).join(', ');
                    document.getElementById('winner-text').innerHTML = `Ganadores:<br><b style="color:#d4af37;">${names}</b><br><br>Bote repartido: €${parseFloat(pot).toFixed(2)}`;
                    document.getElementById('btn-new-round').style.display = state.is_creator ? 'inline-block' : 'none';
                    document.getElementById('winner-overlay').style.display = 'block';
                }
                lastPhase = phase;
            });
        }

        function bingoAction(act) {
            fetch(`api/bingo_action.php?action=${act}&room_id=${roomId}`, { method:'POST' })
            .then(r => r.json()).then(d => { if(d.error) alert(d.error); else { if(act==='new_round') document.getElementById('winner-overlay').style.display='none'; updateBingo(); }});
        }

        function leaveRoom() {
            const fd = new FormData(); fd.append('room_id', roomId); fd.append('action', 'leave');
            fetch('api/rooms.php?action=leave', { method:'POST', body:fd }).then(() => window.location.href = 'index.php');
        }

        function updateChat() {
            fetch(`api/chat.php?action=get&room_id=${roomId}`).then(r => r.text()).then(html => {
                const chatEl = document.getElementById('chat');
                const wasAtBottom = chatEl.scrollHeight - chatEl.scrollTop <= chatEl.clientHeight + 30;
                if(chatEl.innerHTML !== html) { chatEl.innerHTML = html; if (wasAtBottom) chatEl.scrollTop = chatEl.scrollHeight; }
            });
        }
        function sendChat() {
            const input = document.getElementById('chat-msg'); if (!input.value.trim()) return;
            const form = new FormData(); form.append('message', input.value);
            fetch(`api/chat.php?action=send&room_id=${roomId}`, { method: 'POST', body: form }).then(() => { input.value = ''; updateChat(); });
        }

        fetch(`api/bingo_action.php?action=join&room_id=${roomId}`, { method:'POST' }).then(() => { updateBingo(); updateChat(); });
        setInterval(updateBingo, 1500);
        setInterval(updateChat, 2000);
    </script>
    <script src="/casino/assets/js/notifications.js"></script>
</body>
</html>
