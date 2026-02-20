<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$room_id  = (int)($_GET['room_id'] ?? 0);
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Jugador';

if (!$room_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room || $room['game_type'] !== 'poker') { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Póker — <?= htmlspecialchars($room['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #050f07; color: #fff; font-family: 'Roboto', sans-serif; display: flex; height: 100vh; overflow: hidden; }

        /* ── ÁREA JUEGO ── */
        .game-area {
            flex: 1; background: url('https://www.transparenttextures.com/patterns/pinstriped-suit.png') #071a0d;
            display: flex; flex-direction: column; align-items: center; position: relative; padding: 12px 15px; overflow: hidden;
        }
        .header { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .header a { color: #d4af37; text-decoration: none; border: 1px solid #d4af37; padding: 6px 12px; border-radius: 5px; font-size: 13px; transition: 0.2s; }
        .header a:hover { background: #d4af37; color: #000; }
        .header-info { font-family: 'Cinzel', serif; color: #d4af37; font-size: 14px; text-align: center; }
        .players-count-badge { background: #d4af37; color: #000; border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: bold; }
        #phase-label { font-size: 13px; color: #aaa; }

        /* ── MESA OVALADA ── */
        .table-wrap { position: relative; width: 100%; display: flex; justify-content: center; margin: 5px 0; }
        .table-oval {
            width: 75%; max-width: 750px; height: 300px;
            background: radial-gradient(ellipse at center, #1e6b35, #0f3d1e);
            border: 16px solid #3d1f0a;
            border-radius: 50% / 50%;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.8), 0 15px 40px rgba(0,0,0,0.7);
            position: relative;
        }
        .table-oval::before {
            content: ''; position: absolute; inset: 6px;
            border: 2px solid rgba(255,255,255,0.05); border-radius: 50%;
        }
        .pot-display {
            background: rgba(0,0,0,0.75); padding: 6px 18px; border-radius: 20px;
            border: 1px solid #d4af37; color: #d4af37; font-weight: bold;
            font-size: 18px; margin-bottom: 12px; box-shadow: 0 0 10px rgba(212,175,55,0.3);
            font-family: 'Cinzel', serif;
        }
        .community-area { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .community-label { color: rgba(255,255,255,0.4); font-size: 11px; text-align: center; margin-top: 5px; letter-spacing: 2px; }

        /* ── CARTAS ── */
        .card {
            width: 55px; height: 78px; background: white; border-radius: 6px; color: black;
            font-weight: bold; font-size: 13px; display: flex; flex-direction: column;
            justify-content: space-between; padding: 4px; box-shadow: 2px 3px 6px rgba(0,0,0,0.7);
            border: 1px solid #ccc; animation: dealCard 0.35s ease;
        }
        @keyframes dealCard { from { transform: scale(0.4) rotate(-5deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }
        .card.red { color: #c00; }
        .card-back {
            background: repeating-linear-gradient(45deg, #0d2137, #0d2137 8px, #1a3a5c 8px, #1a3a5c 16px);
            border: 2px solid #fff; color: transparent;
        }
        .card-rank { font-size: 15px; font-weight: 900; line-height: 1; }
        .card-suit { font-size: 16px; line-height: 1; }

        /* ── JUGADORES ── */
        .players-ring {
            width: 100%; display: flex; justify-content: space-evenly; flex-wrap: wrap;
            gap: 10px; padding: 8px 20px; margin-bottom: 5px;
        }
        .player-box {
            background: rgba(0,0,0,0.85); border: 2px solid rgba(212,175,55,0.3);
            padding: 10px 12px; border-radius: 10px; text-align: center; min-width: 120px; max-width: 160px;
            transition: all 0.3s; position: relative;
        }
        .player-box.my-box { border-color: rgba(212,175,55,0.7); }
        .player-box.active { box-shadow: 0 0 25px #00ff80, 0 0 5px #fff; border-color: #00ff80; transform: scale(1.06); }
        .player-box.folded { opacity: 0.45; }
        .player-box.winner { border-color: #ffd700; box-shadow: 0 0 25px #ffd700; animation: winPulse 1s infinite alternate; }
        @keyframes winPulse { from { box-shadow: 0 0 15px #ffd700; } to { box-shadow: 0 0 40px #ffd700; } }

        .hole-cards { display: flex; justify-content: center; gap: 4px; margin-bottom: 6px; }
        .player-name { font-size: 12px; font-weight: bold; color: #f0f0f0; }
        .player-bet { color: #ffd700; font-size: 11px; margin: 3px 0; }
        .player-balance { color: #28a745; font-size: 11px; }
        .player-status { font-size: 10px; font-weight: bold; text-transform: uppercase; margin-top: 4px; }
        .s-active   { color: #4caf50; }
        .s-folded   { color: #888; }
        .s-allin    { color: #ff9800; }
        .s-winner   { color: #ffd700; }
        .s-waiting  { color: #555; }
        .dealer-chip { position: absolute; top: -8px; right: -8px; background: #d4af37; color: #000; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* ── CONTROLES ── */
        .controls-bar {
            position: absolute; bottom: 15px; background: rgba(0,0,0,0.92);
            padding: 10px 16px; border-radius: 12px; display: flex; gap: 8px;
            align-items: center; border: 1px solid #333; flex-wrap: wrap; justify-content: center;
        }
        .btn-game { background: #d4af37; color: #000; border: none; padding: 9px 16px; border-radius: 7px; font-weight: bold; font-size: 13px; cursor: pointer; transition: 0.2s; text-transform: uppercase; }
        .btn-game:hover { background: #fff; transform: scale(1.04); }
        .btn-game:disabled { background: #333; color: #555; cursor: not-allowed; transform: none; }
        .btn-game.btn-start { background: #28a745; color: #fff; }
        .btn-game.btn-start:hover { background: #34ce57; }
        .btn-game.btn-fold { background: #dc3545; color: #fff; }
        .btn-game.btn-fold:hover { background: #e05260; }
        .btn-game.btn-call { background: #17a2b8; color: #fff; }
        .btn-game.btn-call:hover { background: #1fbcd4; }
        .btn-game.btn-raise { background: #6f42c1; color: #fff; }
        .btn-game.btn-raise:hover { background: #8a57d9; }
        #raise-amount { padding: 8px; width: 70px; border-radius: 7px; border: 2px solid #6f42c1; background: #1a1a1a; color: #fff; font-size: 14px; font-weight: bold; text-align: center; }

        /* ── WAITING ── */
        .waiting-msg { color: #888; font-style: italic; font-size: 14px; text-align: center; padding: 15px; }
        .hand-rank-badge { background: rgba(212,175,55,0.2); border: 1px solid rgba(212,175,55,0.5); border-radius: 5px; padding: 2px 6px; font-size: 10px; color: #d4af37; margin-top: 3px; }

        /* ── CHAT ── */
        .chat-area { width: 280px; background: #0a0f0b; border-left: 2px solid #d4af37; display: flex; flex-direction: column; }
        .chat-header { background: #111; padding: 12px 15px; color: #d4af37; font-family: 'Cinzel', serif; font-size: 13px; border-bottom: 1px solid #d4af37; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 12px; font-size: 13px; line-height: 1.5; }
        .chat-input-area { padding: 10px; display: flex; gap: 6px; background: #050f07; border-top: 1px solid #222; }
        .chat-input-area input { flex: 1; padding: 8px 10px; border: 1px solid #333; background: #111; color: #fff; border-radius: 5px; font-size: 13px; }
        .chat-input-area input:focus { outline: none; border-color: #d4af37; }
        .chat-send-btn { background: #d4af37; color: #000; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="game-area">
        <div class="header">
            <button id="btn-leave" onclick="leaveRoom()" class="btn-game" style="background:#dc3545; color:white; font-size:12px; padding:6px 12px; margin-right: 15px;">← Salir al Lobby</button>
            <div class="header-info">
                ♠ <?= htmlspecialchars($room['name']) ?>
                &nbsp; <span class="players-count-badge" id="player-badge">0 jugadores</span>
            </div>
            <div id="phase-label">Esperando...</div>
        </div>

        <div class="table-wrap">
            <div class="table-oval">
                <div class="pot-display" id="pot">Pot: €0.00</div>
                <div class="community-area" id="community-cards"></div>
                <div class="community-label" id="community-label"></div>
            </div>
        </div>

        <div id="waiting-msg" class="waiting-msg">Esperando a que se unan más jugadores...</div>
        <div class="players-ring" id="players"></div>

        <div class="controls-bar">
            <div id="action-controls" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:center;"></div>
        </div>
    </div>

    <div class="chat-area">
        <div class="chat-header">💬 Chat Póker</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input-area">
            <input type="text" id="chat-msg" placeholder="Mensaje..." maxlength="200"
                   onkeypress="if(event.key==='Enter') sendChat()">
            <button class="chat-send-btn" onclick="sendChat()">›</button>
        </div>
    </div>

    <script>
        const roomId   = <?= $room_id ?>;
        const myUserId = <?= intval($user_id) ?>;
        let   lastPhase = '';

        // Función para actualizar HTML solo si ha cambiado (Evita parpadeos y reanimaciones)
        function updateHTML(id, html) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.dataset.lastHtml !== html) {
                el.innerHTML = html;
                el.dataset.lastHtml = html;
            }
        }

        // ── RENDER CARTA ────────────────────────────────────────────────────────
        function renderCard(cardCode, isHidden) {
            if (isHidden || !cardCode || cardCode === 'hidden') {
                return `<div class="card card-back"></div>`;
            }
            const parts = cardCode.split('_');
            const rank  = parts[0];
            const suit  = parts[1];
            const suits = { 'S': '♠', 'H': '♥', 'C': '♣', 'D': '♦' };
            const isRed = (suit === 'H' || suit === 'D') ? 'red' : '';
            return `<div class="card ${isRed}">
                        <div class="card-rank">${rank}</div>
                        <div class="card-suit">${suits[suit] || suit}</div>
                    </div>`;
        }

        // ── ACTUALIZAR ──────────────────────────────────────────────────────────
        function updatePoker() {
            fetch(`api/poker_action.php?action=state&room_id=${roomId}`)
            .then(r => r.json())
            .then(state => {
                if (!state || state.error) return;

                const phase       = state.phase || 'waiting';
                const players     = state.players || {};
                const playerCount = state.player_count || 0;
                const iAmCreator  = state.is_creator;

                // Badge
                updateHTML('player-badge', `${playerCount} jugador${playerCount !== 1 ? 'es' : ''}`);

                // Phase label
                const phaseMap = {
                    waiting: 'Esperando jugadores', preflop: 'Pre-Flop',
                    flop: 'Flop', turn: 'Turn', river: 'River', showdown: 'Showdown'
                };
                updateHTML('phase-label', phaseMap[phase] || phase.toUpperCase());

                // Pot
                updateHTML('pot', `Pot: €${parseFloat(state.pot || 0).toFixed(2)}`);

                // Community cards
                const community  = state.community || [];
                const commLabels = { 0: '', 3: 'FLOP', 4: 'TURN', 5: 'RIVER' };
                let commHtml     = '';
                community.forEach(c => commHtml += renderCard(c, false));
                updateHTML('community-cards', commHtml);
                updateHTML('community-label', commLabels[community.length] || '');

                // Waiting
                const waitEl = document.getElementById('waiting-msg');
                const showWait = (playerCount < 2 && phase === 'waiting');
                if (waitEl.style.display !== (showWait ? 'block' : 'none')) {
                    waitEl.style.display = showWait ? 'block' : 'none';
                }

                // Players
                let pHtml = '';
                let pIdx  = 0;
                for (const uid in players) {
                    const p        = players[uid];
                    const isMe     = parseInt(uid) === myUserId;
                    const isTurn   = state.current_turn == uid;
                    const isDealer = pIdx === 0;

                    let classes = 'player-box';
                    if (isMe)              classes += ' my-box';
                    if (isTurn)            classes += ' active';
                    if (p.status === 'folded') classes += ' folded';
                    if (p.status === 'winner') classes += ' winner';

                    // Hole cards
                    let cardsHtml = '';
                    if (p.hole_cards && p.hole_cards.length > 0) {
                        const hide = !isMe && phase !== 'showdown';
                        p.hole_cards.forEach(c => cardsHtml += renderCard(c, hide));
                    } else if (phase !== 'waiting') {
                        // Placeholder empty cards
                        cardsHtml = `<div class="card card-back"></div><div class="card card-back"></div>`;
                    }

                    const statusMap = {
                        active: 'En juego', folded: 'Retirado', allin: '💥 ALL-IN',
                        winner: '🏆 GANADOR', waiting: 'Esperando'
                    };

                    const meLabel  = isMe ? '<span style="color:#d4af37;font-size:9px;"> (Tú)</span>' : '';
                    const turnIndicator = isTurn ? '<div style="color:#00ff80;font-size:13px;">▼ Tu turno</div>' : '';
                    const dealerChip   = isDealer && phase !== 'waiting' ? '<div class="dealer-chip">D</div>' : '';
                    const handRankBadge = p.hand_rank ? `<div class="hand-rank-badge">${p.hand_rank.replace('_',' ')}</div>` : '';
                    const myBet  = parseFloat(p.current_bet || 0).toFixed(2);
                    const myBal  = parseFloat(p.balance || 0).toFixed(2);

                    pHtml += `
                        <div class="${classes}">
                            ${dealerChip}
                            ${turnIndicator}
                            <div class="hole-cards">${cardsHtml}</div>
                            <div class="player-name">${p.avatar || '🃏'} ${p.username || 'Jugador'}${meLabel}</div>
                            <div class="player-bet">Apuesta: €${myBet}</div>
                            <div class="player-balance">Saldo: €${myBal}</div>
                            <div class="player-status s-${p.status || 'waiting'}">${statusMap[p.status] || p.status || ''}</div>
                            ${handRankBadge}
                        </div>
                    `;
                    pIdx++;
                }
                updateHTML('players', pHtml);

                // Controls
                renderControls(state, iAmCreator, phase, playerCount);

                lastPhase = phase;
                
                // Add leave logic here
                const canLeave = ['waiting', 'showdown'].includes(phase);
                const btnLeave = document.getElementById('btn-leave');
                if(btnLeave) {
                    btnLeave.disabled = !canLeave;
                    btnLeave.style.opacity = canLeave ? '1' : '0.5';
                }
            }).catch(e => console.error('Poker update error:', e));
        }

        function renderControls(state, iAmCreator, phase, playerCount) {
            const myPlayer    = state.players ? state.players[myUserId] : null;
            const isMyTurn    = state.current_turn == myUserId;
            const maxBet      = state.current_bet || 0;
            const myBet       = myPlayer ? (myPlayer.current_bet || 0) : 0;
            const callAmount  = Math.max(0, maxBet - myBet).toFixed(2);
            let html = '';

            if (phase === 'waiting') {
                if (iAmCreator && playerCount >= 2) {
                    html += `<button class="btn-game btn-start" onclick="pokerAction('start')">🎮 Iniciar Partida</button>`;
                } else if (iAmCreator) {
                    html += `<button class="btn-game" disabled>Esperando jugadores (${playerCount}/2)...</button>`;
                } else {
                    html += `<button class="btn-game" disabled>Esperando que el líder inicie...</button>`;
                }
            } else if (phase === 'showdown') {
                if (iAmCreator) {
                    html += `<button class="btn-game btn-start" onclick="pokerAction('new_hand')">🔄 Nueva Mano</button>`;
                } else {
                    html += `<button class="btn-game" disabled>Esperando nueva mano...</button>`;
                }
            } else if (isMyTurn && myPlayer && myPlayer.status === 'active') {
                // TRADUCCIÓN BOTONES PÓKER
                html += `<button class="btn-game btn-fold" onclick="pokerAction('fold')">Retirarse (Fold)</button>`;
                
                const callLabel = parseFloat(callAmount) > 0 ? `Igualar (Call) €${callAmount}` : 'Pasar (Check)';
                html += `<button class="btn-game btn-call" onclick="pokerAction('check_call')">${callLabel}</button>`;
                
                html += `<input type="number" id="raise-amount" value="${Math.max(maxBet * 2, 40)}" min="${maxBet + 1}" step="10" placeholder="€">`;
                html += `<button class="btn-game btn-raise" onclick="pokerAction('raise')">Subir (Raise)</button>`;
            } else {
                const statusMsg = myPlayer && myPlayer.status === 'folded'
                    ? 'Te has retirado de esta mano'
                    : 'Esperando turno...';
                html += `<button class="btn-game" disabled>${statusMsg}</button>`;
            }

            updateHTML('action-controls', html);
        }

        // ── ACCIÓN PÓKER ────────────────────────────────────────────────────────
        function pokerAction(act) {
            const data = new FormData();
            data.append('room_id', roomId);
            data.append('action', act);
            if (act === 'raise') {
                const el = document.getElementById('raise-amount');
                data.append('amount', el ? el.value : 40);
            }
            fetch('api/poker_action.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(d => { if (d && d.error) alert('⚠️ ' + d.error); else updatePoker(); })
                .catch(e => console.error('Action error:', e));
        }
        
        // ── SALIR DE LA SALA ─────────────────────────────────────────────────────
        function leaveRoom() {
            if (document.getElementById('btn-leave').disabled) {
                alert('No puedes abandonar la sala en medio de una mano activa.');
                return;
            }
            const formData = new FormData();
            formData.append('room_id', roomId);
            formData.append('action', 'leave');
            fetch('api/rooms.php?action=leave', { method: 'POST', body: formData })
                .then(() => window.location.href = 'index.php');
        }

        // ── CHAT ─────────────────────────────────────────────────────────────────
        function updateChat() {
            fetch(`api/chat.php?action=get&room_id=${roomId}`)
            .then(r => r.text())
            .then(html => {
                const chatEl = document.getElementById('chat');
                const wasAtBottom = chatEl.scrollHeight - chatEl.scrollTop <= chatEl.clientHeight + 30;
                if(chatEl.innerHTML !== html) {
                    chatEl.innerHTML = html;
                    if (wasAtBottom) chatEl.scrollTop = chatEl.scrollHeight;
                }
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
        const joinData = new FormData();
        joinData.append('room_id', roomId);
        joinData.append('action', 'join');
        fetch('api/poker_action.php', { method: 'POST', body: joinData })
            .then(() => { updatePoker(); updateChat(); });

        setInterval(updatePoker, 1500);
        setInterval(updateChat, 2000);
    </script>
</body>
</html>
