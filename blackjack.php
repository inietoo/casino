<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

// ✅ FIX: acceder correctamente al parámetro GET
$room_id  = (int)($_GET['room_id'] ?? 0);
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Jugador';

if (!$room_id) { header('Location: index.php'); exit; }

// Get room info
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room || $room['game_type'] !== 'blackjack') { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Blackjack — <?= htmlspecialchars($room['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #fff; font-family: 'Roboto', sans-serif; display: flex; height: 100vh; overflow: hidden; }

        /* ── MESA ── */
        .game-area {
            flex: 1; background: radial-gradient(circle at 50% 40%, #1a5c2e, #0a1f12);
            padding: 15px 20px; display: flex; flex-direction: column; align-items: center;
            position: relative; overflow: hidden;
        }
        .header {
            width: 100%; display: flex; justify-content: space-between; align-items: center;
            color: #d4af37; font-family: 'Cinzel', serif; margin-bottom: 10px;
        }
        .header a { color: #d4af37; text-decoration: none; border: 1px solid #d4af37; padding: 6px 12px; border-radius: 5px; font-size: 13px; transition: 0.2s; }
        .header a:hover { background: #d4af37; color: #000; }

        /* ── DEALER ── */
        .dealer-area { text-align: center; margin: 10px 0; }
        .dealer-label { color: #d4af37; font-family: 'Cinzel', serif; font-size: 14px; margin-bottom: 8px; letter-spacing: 2px; }
        .dealer-total { color: #aaa; font-size: 13px; margin-top: 5px; }

        /* ── CARTAS ── */
        .cards-row { display: flex; justify-content: center; flex-wrap: wrap; gap: 5px; min-height: 100px; }
        .card {
            width: 65px; height: 90px; background: white; border-radius: 6px; color: black;
            font-weight: bold; font-size: 15px; display: flex; flex-direction: column;
            justify-content: space-between; padding: 5px; box-shadow: 3px 3px 8px rgba(0,0,0,0.6);
            border: 2px solid #ddd; animation: dealCard 0.3s ease;
        }
        @keyframes dealCard { from { transform: scale(0.5) rotate(-10deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }
        .card.red { color: #c00; }
        .card-back {
            background: repeating-linear-gradient(45deg, #0d2137, #0d2137 8px, #153455 8px, #153455 16px);
            border: 3px solid #fff; color: transparent;
        }
        .card-rank { font-size: 18px; font-weight: 900; line-height: 1; }
        .card-suit { font-size: 20px; line-height: 1; }

        /* ── JUGADORES ── */
        .players-area {
            display: flex; justify-content: center; flex-wrap: wrap; gap: 15px;
            width: 100%; margin: 15px 0;
        }
        .player-spot {
            background: rgba(0,0,0,0.6); border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px; padding: 12px 15px; text-align: center; min-width: 130px;
            transition: all 0.3s;
        }
        .player-spot.my-spot { border-color: rgba(212,175,55,0.5); }
        .player-spot.active-turn {
            box-shadow: 0 0 25px #d4af37, 0 0 5px #fff; border-color: #d4af37;
            transform: scale(1.05); border-style: solid;
        }
        .player-name { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
        .player-bet  { color: #ffd700; font-size: 12px; margin-bottom: 6px; }
        .player-status { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 6px; }
        .s-playing   { color: #4caf50; }
        .s-standing  { color: #2196f3; }
        .s-bust      { color: #f44336; }
        .s-blackjack { color: #ffd700; }
        .s-win       { color: #4caf50; }
        .s-loss      { color: #f44336; }
        .s-push      { color: #ff9800; }
        .s-betting   { color: #aaa; }
        .s-waiting   { color: #555; }
        .hand-value  { font-size: 11px; color: #ccc; margin-top: 2px; }

        /* ── RESULTADO ── */
        .result-overlay {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
            background: rgba(0,0,0,0.92); border: 3px solid #d4af37; border-radius: 15px;
            padding: 30px 50px; text-align: center; z-index: 100; display: none;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%,-50%) scale(0.8); } to { opacity: 1; transform: translate(-50%,-50%) scale(1); } }
        .result-overlay h2 { font-family: 'Cinzel', serif; font-size: 28px; margin-bottom: 10px; }
        .result-overlay .result-text { font-size: 18px; margin-bottom: 20px; }
        .result-overlay button { background: #d4af37; color: #000; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .result-overlay button:hover { background: #fff; }

        /* ── CONTROLES ── */
        .controls-bar {
            position: absolute; bottom: 20px; background: rgba(0,0,0,0.9);
            padding: 12px 20px; border-radius: 12px; display: flex; gap: 8px;
            align-items: center; border: 1px solid #333; flex-wrap: wrap; justify-content: center;
        }
        .btn-game {
            background: #d4af37; color: #000; border: none; padding: 10px 18px;
            border-radius: 7px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s;
            text-transform: uppercase;
        }
        .btn-game:hover { background: #fff; transform: scale(1.05); }
        .btn-game:disabled { background: #444; color: #666; cursor: not-allowed; transform: none; }
        .btn-game.btn-start { background: #28a745; color: #fff; }
        .btn-game.btn-start:hover { background: #34ce57; }
        .btn-game.btn-danger { background: #dc3545; color: #fff; }
        .btn-game.btn-danger:hover { background: #e05260; }
        #bet-amount { padding: 9px; width: 75px; border-radius: 7px; border: 2px solid #d4af37; background: #222; color: #fff; font-size: 15px; font-weight: bold; text-align: center; }

        /* ── SALA INFO ── */
        .room-info-bar {
            width: 100%; display: flex; justify-content: center; gap: 20px;
            font-size: 13px; color: #aaa; margin-bottom: 5px; flex-wrap: wrap;
        }
        .room-info-bar span { color: #d4af37; font-weight: bold; }
        .waiting-msg { text-align: center; color: #888; font-style: italic; font-size: 14px; padding: 20px; }
        .players-count-badge {
            background: #d4af37; color: #000; border-radius: 20px; padding: 3px 10px;
            font-size: 12px; font-weight: bold;
        }

        /* ── CHAT ── */
        .chat-area { width: 290px; background: #111; border-left: 2px solid #d4af37; display: flex; flex-direction: column; }
        .chat-header { background: #1a1a1a; padding: 12px 15px; color: #d4af37; font-family: 'Cinzel', serif; font-size: 13px; border-bottom: 1px solid #d4af37; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 12px; font-size: 13px; line-height: 1.5; }
        .chat-input-area { padding: 10px; display: flex; gap: 6px; background: #0a0a0a; border-top: 1px solid #333; }
        .chat-input-area input { flex: 1; padding: 8px 10px; border: 1px solid #444; background: #1a1a1a; color: #fff; border-radius: 5px; font-size: 13px; }
        .chat-input-area input:focus { outline: none; border-color: #d4af37; }
        .chat-send-btn { background: #d4af37; color: #000; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>
    <div class="game-area">
        <!-- Header -->
        <div class="header">
            <a href="index.php">← Lobby</a>
            <div style="font-size:14px;">
                ♠ <?= htmlspecialchars($room['name']) ?>
                &nbsp; <span class="players-count-badge" id="player-badge">0 jugadores</span>
            </div>
            <div id="phase-label" style="font-size:13px; color:#aaa;">Esperando jugadores...</div>
        </div>

        <!-- Room meta -->
        <div class="room-info-bar">
            <div>Min: <span>€<?= number_format($room['min_bet'],2) ?></span></div>
            <div>Max: <span>€<?= number_format($room['max_bet'],2) ?></span></div>
            <div>ID Sala: <span>#<?= $room_id ?></span></div>
        </div>

        <!-- Dealer Zone -->
        <div class="dealer-area">
            <div class="dealer-label">♦ DEALER ♦</div>
            <div class="cards-row" id="dealer-cards"></div>
            <div class="dealer-total" id="dealer-total"></div>
        </div>

        <!-- Players Zone -->
        <div id="waiting-msg" class="waiting-msg">Esperando a que se unan más jugadores...</div>
        <div class="players-area" id="players-zone"></div>

        <!-- Result overlay -->
        <div class="result-overlay" id="result-overlay">
            <h2 id="result-title">¡Fin de Ronda!</h2>
            <div class="result-text" id="result-body"></div>
            <button id="result-btn" onclick="closeResult()">Nueva Ronda</button>
        </div>

        <!-- Controls -->
        <div class="controls-bar" id="controls-bar">
            <div id="action-controls" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:center;"></div>
        </div>
    </div>

    <!-- Chat -->
    <div class="chat-area">
        <div class="chat-header">💬 Chat de Mesa</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input-area">
            <input type="text" id="chat-msg" placeholder="Escribe un mensaje..." maxlength="200"
                   onkeypress="if(event.key==='Enter') sendChat()">
            <button class="chat-send-btn" onclick="sendChat()">›</button>
        </div>
    </div>

    <script>
        // ── CONFIG ──────────────────────────────────────────────────────────────
        const roomId      = <?= $room_id ?>;
        const myUserId    = <?= intval($user_id) ?>;
        const isCreator   = <?= json_encode(isset($_SESSION['user_id'])) ?>;
        let   lastPhase   = '';
        let   polling     = true;

        // ── RENDER CARTA ────────────────────────────────────────────────────────
        function renderCard(cardCode) {
            if (!cardCode || cardCode === 'hidden') {
                return `<div class="card card-back"></div>`;
            }
            // ✅ FIX: destructuring correcto
            const parts = cardCode.split('_');
            const rank  = parts[0];
            const suit  = parts[1];
            const suits = { 'S': '♠', 'H': '♥', 'C': '♣', 'D': '♦' };
            const isRed = (suit === 'H' || suit === 'D') ? 'red' : '';
            // ✅ FIX: acceder al objeto suits con la clave correcta
            return `<div class="card ${isRed}">
                        <div class="card-rank">${rank}</div>
                        <div class="card-suit">${suits[suit] || suit}</div>
                    </div>`;
        }

        function handValue(cards) {
            if (!cards || cards.length === 0) return 0;
            let total = 0, aces = 0;
            for (const c of cards) {
                if (!c || c === 'hidden') continue;
                const r = c.split('_')[0];
                if (['J','Q','K'].includes(r)) total += 10;
                else if (r === 'A') { total += 11; aces++; }
                else total += parseInt(r);
            }
            while (total > 21 && aces > 0) { total -= 10; aces--; }
            return total;
        }

        // ── ACTUALIZAR MESA ──────────────────────────────────────────────────────
        function updateTable() {
            fetch(`api/blackjack_action.php?action=state&room_id=${roomId}`)
            .then(r => r.json())
            .then(state => {
                if (!state || state.error) return;

                const phase        = state.phase || 'waiting';
                const players      = state.players || {};
                const playerCount  = state.player_count || 0;
                const iAmCreator   = state.is_creator;

                // Badge
                document.getElementById('player-badge').textContent = `${playerCount} jugador${playerCount !== 1 ? 'es' : ''}`;

                // Phase label
                const phaseLabels = {
                    waiting: 'Esperando jugadores',
                    betting: 'Fase de apuestas',
                    playing: 'En juego',
                    dealer_turn: 'Turno del dealer',
                    finished: 'Fin de ronda'
                };
                document.getElementById('phase-label').textContent = phaseLabels[phase] || phase.toUpperCase();

                // Dealer cards
                const dealerCards = state.dealer_cards_display || [];
                let dealerHtml = '';
                dealerCards.forEach(c => dealerHtml += renderCard(c));
                document.getElementById('dealer-cards').innerHTML = dealerHtml;

                const dealerTotalEl = document.getElementById('dealer-total');
                if (phase === 'finished' || phase === 'dealer_turn') {
                    const realCards = state.dealer_cards || [];
                    const dv = handValue(realCards);
                    dealerTotalEl.textContent = realCards.length ? `Total dealer: ${dv}` : '';
                } else {
                    dealerTotalEl.textContent = '';
                }

                // Waiting message
                const waitingMsg = document.getElementById('waiting-msg');
                if (playerCount < 2 && phase === 'waiting') {
                    waitingMsg.style.display = 'block';
                    waitingMsg.textContent = `Esperando jugadores... (${playerCount}/2 mínimo)`;
                } else {
                    waitingMsg.style.display = 'none';
                }

                // Players
                let playersHtml = '';
                for (const uid in players) {
                    const p         = players[uid]; // ✅ FIX: acceder al jugador correcto
                    const isMe      = (parseInt(uid) === myUserId);
                    const isTurn    = (state.current_turn == uid);
                    const classes   = ['player-spot', isMe ? 'my-spot' : '', isTurn ? 'active-turn' : ''].join(' ');

                    let cardsHtml = '';
                    if (p.cards && p.cards.length > 0) {
                        p.cards.forEach(c => cardsHtml += renderCard(c));
                    }

                    const hv       = p.cards && p.cards.length ? handValue(p.cards) : 0;
                    const hvText   = hv > 0 ? `<div class="hand-value">Total: ${hv}</div>` : '';
                    const statusClass = `s-${p.status || 'waiting'}`;
                    const bet      = parseFloat(p.bet || 0).toFixed(2);
                    const meLabel  = isMe ? ' <span style="color:#d4af37;font-size:10px;">(Tú)</span>' : '';
                    const turnArrow = isTurn ? '<div style="color:#d4af37;font-size:16px;">▼</div>' : '';

                    const statusMap = {
                        betting: 'Apostando...', waiting: 'Esperando',
                        playing: 'Jugando', standing: 'Plantado',
                        bust: '¡Pasado! (Bust)', blackjack: '¡Blackjack!',
                        win: '✅ Ganó', loss: '❌ Perdió',
                        push: '🤝 Empate', ready: 'Listo'
                    };

                    playersHtml += `
                        <div class="${classes}">
                            ${turnArrow}
                            <div class="player-name">${p.avatar || '🎲'} ${p.username || 'Jugador'}${meLabel}</div>
                            <div class="player-bet">Apuesta: €${bet}</div>
                            <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:3px;margin:6px 0;">${cardsHtml}</div>
                            ${hvText}
                            <div class="player-status ${statusClass}">${statusMap[p.status] || p.status || ''}</div>
                            ${p.payout > 0 ? `<div style="color:#4caf50;font-size:12px;margin-top:3px;">+€${parseFloat(p.payout).toFixed(2)}</div>` : ''}
                        </div>
                    `;
                }
                document.getElementById('players-zone').innerHTML = playersHtml;

                // Controls
                renderControls(state, iAmCreator, phase);

                // Result overlay
                if (phase === 'finished' && lastPhase !== 'finished') {
                    showResult(state, players);
                }
                lastPhase = phase;

            }).catch(e => console.error('Update error:', e));
        }

        function renderControls(state, iAmCreator, phase) {
            const myPlayer    = state.players ? state.players[myUserId] : null;
            const isMyTurn    = state.current_turn == myUserId;
            const playerCount = state.player_count || 0;
            let html = '';

            if (phase === 'waiting') {
                if (iAmCreator && playerCount >= 2) {
                    html += `<button class="btn-game btn-start" onclick="sendAction('start')">🎮 Iniciar Partida</button>`;
                } else if (iAmCreator) {
                    html += `<button class="btn-game" disabled>Esperando jugadores (${playerCount}/2)...</button>`;
                } else {
                    html += `<button class="btn-game" disabled>Esperando que el creador inicie...</button>`;
                }
            } else if (phase === 'betting') {
                if (myPlayer && myPlayer.status === 'betting') {
                    html += `<input type="number" id="bet-amount" value="50" min="10" max="500" placeholder="€">`;
                    html += `<button class="btn-game" onclick="sendAction('bet')">💰 Apostar</button>`;
                } else {
                    html += `<button class="btn-game" disabled>Esperando tu turno de apuesta...</button>`;
                }
            } else if (phase === 'playing') {
                if (isMyTurn) {
                    html += `<button class="btn-game" onclick="sendAction('hit')">🃏 Pedir (Hit)</button>`;
                    html += `<button class="btn-game" onclick="sendAction('stand')">✋ Plantarse (Stand)</button>`;
                    if (myPlayer && myPlayer.cards && myPlayer.cards.length === 2) {
                        html += `<button class="btn-game" onclick="sendAction('double')">⬆️ Doblar (x2)</button>`;
                    }
                } else {
                    html += `<button class="btn-game" disabled>Turno de otro jugador...</button>`;
                }
            } else if (phase === 'dealer_turn') {
                html += `<button class="btn-game" disabled>El dealer está jugando...</button>`;
            } else if (phase === 'finished') {
                if (iAmCreator) {
                    html += `<button class="btn-game btn-start" onclick="sendAction('new_round')">🔄 Nueva Ronda</button>`;
                } else {
                    html += `<button class="btn-game" disabled>Esperando nueva ronda...</button>`;
                }
            }

            document.getElementById('action-controls').innerHTML = html;
        }

        function showResult(state, players) {
            const myPlayer = players[myUserId];
            if (!myPlayer) return;

            const overlay  = document.getElementById('result-overlay');
            const title    = document.getElementById('result-title');
            const body     = document.getElementById('result-body');
            const btn      = document.getElementById('result-btn');

            const resultMap = {
                win: { t: '🏆 ¡Ganaste!', color: '#4caf50' },
                blackjack: { t: '🃏 ¡BLACKJACK!', color: '#ffd700' },
                push: { t: '🤝 Empate', color: '#ff9800' },
                loss: { t: '❌ Perdiste', color: '#f44336' },
                bust: { t: '💥 ¡Te pasaste!', color: '#f44336' },
            };

            const r = resultMap[myPlayer.status] || { t: 'Ronda finalizada', color: '#fff' };
            title.textContent  = r.t;
            title.style.color  = r.color;

            const payout = parseFloat(myPlayer.payout || 0);
            const bet    = parseFloat(myPlayer.bet || 0);
            if (payout > 0) {
                body.textContent = `Cobras: €${payout.toFixed(2)} | Ganancia: +€${(payout - bet).toFixed(2)}`;
            } else {
                body.textContent = `Perdiste: €${bet.toFixed(2)}`;
            }

            const iAmCreator = state.is_creator;
            btn.textContent  = iAmCreator ? 'Nueva Ronda' : 'Volver al Lobby';
            btn.onclick      = iAmCreator ? () => { closeResult(); sendAction('new_round'); } : () => window.location.href = 'index.php';

            overlay.style.display = 'block';
        }

        function closeResult() {
            document.getElementById('result-overlay').style.display = 'none';
        }

        // ── ENVIAR ACCIÓN ────────────────────────────────────────────────────────
        function sendAction(action) {
            const formData = new FormData();
            formData.append('room_id', roomId);
            formData.append('action', action);
            if (action === 'bet') {
                const amtEl = document.getElementById('bet-amount');
                formData.append('amount', amtEl ? amtEl.value : 50);
            }
            fetch('api/blackjack_action.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d && d.error) alert('⚠️ ' + d.error); else updateTable(); })
                .catch(e => console.error('Action error:', e));
        }

        // ── CHAT ─────────────────────────────────────────────────────────────────
        function updateChat() {
            fetch(`api/chat.php?action=get&room_id=${roomId}`)
            .then(r => r.text())
            .then(html => {
                const chatEl = document.getElementById('chat');
                const wasAtBottom = chatEl.scrollHeight - chatEl.scrollTop <= chatEl.clientHeight + 30;
                chatEl.innerHTML = html;
                if (wasAtBottom) chatEl.scrollTop = chatEl.scrollHeight;
            });
        }

        function sendChat() {
            const input = document.getElementById('chat-msg');
            if (!input.value.trim()) return;
            const form = new FormData();
            form.append('message', input.value);
            fetch(`api/chat.php?action=send&room_id=${roomId}`, { method: 'POST', body: form })
                .then(() => { input.value = ''; updateChat(); });
        }

        // ── INIT ─────────────────────────────────────────────────────────────────
        // Join room first
        const joinData = new FormData();
        joinData.append('room_id', roomId);
        joinData.append('action', 'join');
        fetch('api/blackjack_action.php', { method: 'POST', body: joinData })
            .then(() => { updateTable(); updateChat(); });

        // Polling every 1.5s
        setInterval(updateTable, 1500);
        setInterval(updateChat, 2000);
    </script>
</body>
</html>
