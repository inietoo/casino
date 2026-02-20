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
        .header { width: 100%; display: flex; justify-content: space-between; align-items: center; color: #f8c8dc; font-family: 'Cinzel', serif; margin-bottom: 12px; }
        .btn-game { background: #9c27b0; color: #fff; border: none; padding: 10px 18px; border-radius: 7px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; text-transform: uppercase; box-shadow: 0 4px 10px rgba(156, 39, 176, 0.4); }
        .btn-game:hover { transform: scale(1.05); background: #ba68c8; }
        .btn-game:disabled { background: #444 !important; color: #888 !important; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── Zona superior: jugadores + bolas + botes ─── */
        .top-info { display: flex; width: 100%; justify-content: space-between; gap: 15px; margin-bottom: 15px; }

        .players-panel { width: 210px; flex-shrink: 0; background: rgba(0,0,0,0.5); border-radius: 12px; padding: 12px; border: 2px solid #9c27b0; }
        .players-panel h3 { color: #f8c8dc; margin-bottom: 8px; font-family: 'Cinzel', serif; font-size: 14px; border-bottom: 1px solid #444; padding-bottom: 4px; }
        .pot-display { font-size: 18px; color: #00ff80; font-weight: bold; text-align: center; margin-bottom: 8px; font-family: 'Cinzel', serif; }
        .player-item { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px; border-bottom: 1px dashed #333; padding-bottom: 3px; }

        /* ── Boles ── */
        .balls-container { flex: 1; background: rgba(0,0,0,0.5); border-radius: 12px; padding: 12px 15px; border: 2px solid #9c27b0; text-align: center; }
        .current-ball { font-size: 56px; font-weight: 900; color: #fff; background: radial-gradient(circle, #e91e63, #880e4f); display: inline-flex; align-items: center; justify-content: center; width: 95px; height: 95px; border-radius: 50%; border: 4px solid #f8c8dc; box-shadow: 0 0 20px #e91e63; margin-bottom: 8px; }
        .history-balls { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; }
        .mini-ball { width: 32px; height: 32px; border-radius: 50%; background: #fff; color: #000; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 13px; border: 2px solid #ccc; opacity: 0.8; }

        /* ── Panel de botes ── */
        .prizes-panel { width: 200px; flex-shrink: 0; background: rgba(0,0,0,0.5); border-radius: 12px; padding: 12px; border: 2px solid #9c27b0; display: flex; flex-direction: column; gap: 10px; }
        .prize-box { border-radius: 10px; padding: 10px 12px; text-align: center; }
        .prize-box.linea  { background: rgba(33,150,243,0.15); border: 2px solid #2196f3; }
        .prize-box.bingo  { background: rgba(233,30,99,0.15);  border: 2px solid #e91e63; }
        .prize-box.linea.paid  { opacity: 0.55; }
        .prize-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 4px; }
        .prize-label.linea  { color: #90caf9; }
        .prize-label.bingo  { color: #f48fb1; }
        .prize-amount { font-size: 22px; font-weight: 900; font-family: 'Cinzel', serif; }
        .prize-amount.linea { color: #2196f3; }
        .prize-amount.bingo { color: #e91e63; }
        .prize-winner { font-size: 11px; color: #00ff80; margin-top: 4px; }

        /* ── Cartones ── */
        .cards-area { display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; width: 100%; margin-bottom: 80px; }
        .bingo-card { background: #fff; color: #000; border-radius: 10px; padding: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); width: 215px; }
        .bingo-header { display: grid; grid-template-columns: repeat(5, 1fr); text-align: center; font-weight: 900; font-size: 20px; color: #fff; background: #9c27b0; border-radius: 5px 5px 0 0; padding: 4px 0; }
        .bingo-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 3px; margin-top: 4px; }
        .bingo-cell { border: 2px solid #ddd; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; border-radius: 4px; position: relative; }
        .bingo-cell.marked::after { content: ''; position: absolute; top: 10%; left: 10%; right: 10%; bottom: 10%; background: rgba(233,30,99,0.6); border-radius: 50%; border: 2px solid #e91e63; }
        .bingo-cell.free { background: #f0f0f0; font-size: 11px; color: #9c27b0; }
        .bingo-cell.free.marked::after { background: rgba(156,39,176,0.6); border-color: #9c27b0; }
        /* Resalta celdas que forman una línea ganada */
        .bingo-cell.line-win { border-color: #2196f3 !important; }
        .bingo-cell.line-win::after { background: rgba(33,150,243,0.65) !important; border-color: #2196f3 !important; }

        /* ── Barra de controles ── */
        .controls-bar { position: fixed; bottom: 20px; background: rgba(0,0,0,0.9); padding: 12px 22px; border-radius: 12px; display: flex; gap: 12px; align-items: center; border: 1px solid #9c27b0; box-shadow: 0 5px 20px rgba(0,0,0,0.8); z-index: 100; }

        /* ── Chat ── */
        .chat-area { width: 285px; background: #111; border-left: 2px solid #9c27b0; display: flex; flex-direction: column; }
        .chat-header { background: #1a1a1a; padding: 12px 15px; color: #f8c8dc; font-family: 'Cinzel', serif; font-size: 13px; border-bottom: 1px solid #9c27b0; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 12px; font-size: 13px; line-height: 1.5; }
        .chat-input-area { padding: 10px; display: flex; gap: 6px; background: #0a0a0a; border-top: 1px solid #333; }
        .chat-input-area input { flex: 1; padding: 8px 10px; border: 1px solid #444; background: #1a1a1a; color: #fff; border-radius: 5px; }
        .chat-send-btn { background: #9c27b0; color: #fff; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }

        /* ── Overlays ── */
        .overlay { position: fixed; top: 0; left: 0; right: 285px; bottom: 0; display: flex; align-items: center; justify-content: center; z-index: 200; pointer-events: none; }

        /* Anuncio de LÍNEA (aparece y desaparece) */
        .line-announcement { background: rgba(10,20,40,0.95); border: 4px solid #2196f3; border-radius: 15px; padding: 28px 40px; text-align: center; pointer-events: auto; display: none; }
        .line-announcement h2 { color: #2196f3; font-size: 36px; font-family: 'Cinzel', serif; margin-bottom: 8px; animation: pulseBlue 1s infinite alternate; }
        @keyframes pulseBlue { from { text-shadow: 0 0 10px #2196f3; } to { text-shadow: 0 0 30px #2196f3, 0 0 10px #fff; } }

        /* Overlay de BINGO COMPLETO */
        .winner-overlay { background: rgba(0,0,0,0.96); border: 4px solid #e91e63; border-radius: 15px; padding: 38px 50px; text-align: center; pointer-events: auto; display: none; }
        .winner-overlay h2 { color: #00ff80; font-size: 42px; font-family: 'Cinzel', serif; margin-bottom: 10px; animation: pulseGreen 1s infinite alternate; }
        @keyframes pulseGreen { from { text-shadow: 0 0 10px #00ff80; } to { text-shadow: 0 0 35px #00ff80, 0 0 10px #fff; } }
    </style>
</head>
<body>
    <div class="game-area">
        <div class="header">
            <button onclick="leaveRoom()" class="btn-game" style="background:#dc3545; font-size:12px; padding:6px 12px;">← Lobby</button>
            <div style="font-size:15px;">🎱 <?= htmlspecialchars($room['name']) ?></div>
            <div id="phase-label" style="font-size:13px; color:#aaa;">Esperando...</div>
        </div>

        <div class="top-info">
            <!-- Jugadores -->
            <div class="players-panel">
                <div class="pot-display" id="pot">BOTE: €0.00</div>
                <h3>Jugadores (<span id="player-count">0</span>)</h3>
                <div id="players-list"></div>
            </div>

            <!-- Bolas -->
            <div class="balls-container">
                <div style="color:#aaa; font-size:11px; margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">Última Bola</div>
                <div class="current-ball" id="current-ball">--</div>
                <div class="history-balls" id="history-balls"></div>
            </div>

            <!-- Botes -->
            <div class="prizes-panel">
                <div class="prize-box linea" id="box-linea">
                    <div class="prize-label linea">🏅 Premio Línea</div>
                    <div class="prize-amount linea" id="prize-linea">€0.00</div>
                    <div class="prize-winner" id="winner-linea" style="display:none;"></div>
                </div>
                <div class="prize-box bingo" id="box-bingo">
                    <div class="prize-label bingo">🎱 Premio Bingo</div>
                    <div class="prize-amount bingo" id="prize-bingo">€0.00</div>
                </div>
                <div style="color:#888; font-size:10px; text-align:center; margin-top:4px;">Botes calculados al iniciar el sorteo</div>
            </div>
        </div>

        <!-- Mis cartones -->
        <div class="cards-area" id="my-cards"></div>

        <!-- Barra de controles -->
        <div class="controls-bar" id="controls-bar"></div>
    </div>

    <!-- Overlay anuncio LÍNEA -->
    <div class="overlay" id="overlay-linea">
        <div class="line-announcement" id="line-announcement">
            <h2>¡LÍNEA!</h2>
            <div id="line-winner-text" style="font-size:18px; margin-bottom:6px;"></div>
            <div style="color:#90caf9; font-size:15px; margin-bottom:16px;">Premio cobrado. El sorteo continúa hacia el BINGO... 🎱</div>
            <button class="btn-game" style="background:#2196f3;" onclick="closeLineAnnouncement()">Continuar</button>
        </div>
    </div>

    <!-- Overlay BINGO COMPLETO -->
    <div class="overlay" id="overlay-bingo">
        <div class="winner-overlay" id="winner-overlay">
            <h2>¡BINGO!</h2>
            <div id="winner-text" style="font-size:20px; margin-bottom:20px;"></div>
            <button class="btn-game" onclick="closeWinner()">Cerrar</button>
            <button class="btn-game" id="btn-new-round" style="display:none; background:#28a745;" onclick="bingoAction('new_round')">Nueva Ronda</button>
        </div>
    </div>

    <!-- Chat -->
    <div class="chat-area">
        <div class="chat-header">💬 Chat Bingo</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input-area">
            <input type="text" id="chat-msg" placeholder="Escribe..." onkeypress="if(event.key==='Enter') sendChat()">
            <button class="chat-send-btn" onclick="sendChat()">›</button>
        </div>
    </div>

    <script>
        const roomId    = <?= $room_id ?>;
        const myUserId  = <?= $user_id ?>;
        let lastPhase        = '';
        let lastLinePaid     = false;
        let lastBingoWinners = null;
        let knownDrawnCount  = 0;

        // ── Renderizado de cartones ──────────────────────────────────────────
        function renderCard(cardData, drawnNumbers) {
            let html = `<div class="bingo-card">
                            <div class="bingo-header"><div>B</div><div>I</div><div>N</div><div>G</div><div>O</div></div>
                            <div class="bingo-grid">`;
            cardData.forEach(num => {
                const isFree   = num === 'FREE';
                const isMarked = isFree || drawnNumbers.includes(num);
                const classes  = `bingo-cell ${isFree ? 'free' : ''} ${isMarked ? 'marked' : ''}`;
                html += `<div class="${classes}">${isFree ? '★' : num}</div>`;
            });
            html += `</div></div>`;
            return html;
        }

        // ── Polling principal ────────────────────────────────────────────────
        function updateBingo() {
            fetch(`api/bingo_action.php?action=state&room_id=${roomId}&_=${Date.now()}`)
            .then(r => r.json())
            .then(state => {
                if (state.error) return;

                const phase   = state.phase || 'waiting';
                const players = state.players || {};
                const drawn   = state.drawn || [];
                const pot     = state.pot || 0;

                // Phase label
                const phaseLabels = {
                    waiting: 'Fase de Compra',
                    playing: 'Sorteo en curso',
                    finished: 'Ronda Finalizada'
                };
                document.getElementById('phase-label').innerText = phaseLabels[phase] || phase;
                document.getElementById('pot').innerText = `BOTE: €${parseFloat(pot).toFixed(2)}`;
                document.getElementById('player-count').innerText = state.player_count;

                // Premios
                const lp = parseFloat(state.line_prize  || 0).toFixed(2);
                const bp = parseFloat(state.bingo_prize || 0).toFixed(2);
                document.getElementById('prize-linea').innerText = `€${lp}`;
                document.getElementById('prize-bingo').innerText = `€${bp}`;

                // Marcar línea pagada visualmente
                const boxLinea = document.getElementById('box-linea');
                const winLineaEl = document.getElementById('winner-linea');
                if (state.line_paid) {
                    boxLinea.classList.add('paid');
                    if (state.line_winners && state.line_winners.length > 0) {
                        const names = state.line_winners.map(w => players[w] ? players[w].username : '?').join(', ');
                        winLineaEl.innerText = `✔ ${names} (€${parseFloat(state.line_payout_each||0).toFixed(2)} c/u)`;
                        winLineaEl.style.display = 'block';
                    }
                } else {
                    boxLinea.classList.remove('paid');
                    winLineaEl.style.display = 'none';
                }

                // Bolas
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

                // Lista de jugadores
                let pList = '';
                for (const uid in players) {
                    const p = players[uid];
                    const count = p.cards ? p.cards.length : (p.cards_count || 0);
                    pList += `<div class="player-item">
                                <span>${p.avatar} ${p.username}</span>
                                <span style="color:#f8c8dc;">${count} 🎫</span>
                              </div>`;
                }
                document.getElementById('players-list').innerHTML = pList;

                // Mis cartones
                const myPlayer = players[myUserId];
                if (myPlayer && myPlayer.cards) {
                    let cardsHtml = '';
                    myPlayer.cards.forEach(c => cardsHtml += renderCard(c, drawn));
                    document.getElementById('my-cards').innerHTML = cardsHtml;
                } else {
                    document.getElementById('my-cards').innerHTML = '<div style="color:#aaa; font-style:italic; margin-top:20px;">No has comprado cartones.</div>';
                }

                // Controles
                let controls = '';
                if (phase === 'waiting') {
                    controls += `<button class="btn-game" onclick="bingoAction('buy')">🛒 Comprar Cartón (10€)</button>`;
                    if (state.is_creator) controls += `<button class="btn-game" style="background:#28a745;" onclick="bingoAction('start')">🎮 Empezar Sorteo</button>`;
                } else if (phase === 'playing') {
                    const lineTxt = state.line_paid ? '✔ Línea pagada' : `Línea: €${lp}`;
                    controls += `<span style="color:#aaa; font-size:13px;">🎱 Sorteo automático — ${drawn.length}/75 bolas | ${lineTxt} | Bingo: €${bp}</span>`;
                } else if (phase === 'finished' && state.is_creator) {
                    controls += `<button class="btn-game" style="background:#28a745;" onclick="bingoAction('new_round')">🔄 Nueva Ronda</button>`;
                }
                document.getElementById('controls-bar').innerHTML = controls;

                // ── Anuncio de LÍNEA (solo aparece una vez) ──────────────────
                if (state.line_paid && !lastLinePaid) {
                    const names = (state.line_winners || []).map(w => players[w] ? players[w].username : '?').join(', ');
                    const each  = parseFloat(state.line_payout_each || 0).toFixed(2);
                    document.getElementById('line-winner-text').innerHTML =
                        `<b style="color:#d4af37;">${names}</b><br>Premio cobrado: €${each} por jugador`;
                    document.getElementById('line-announcement').style.display = 'block';
                }
                lastLinePaid = !!state.line_paid;

                // ── Overlay BINGO COMPLETO ────────────────────────────────────
                const newBingoWinners = JSON.stringify(state.bingo_winners || null);
                if (phase === 'finished' && newBingoWinners !== lastBingoWinners) {
                    lastBingoWinners = newBingoWinners;
                    const bw = state.bingo_winners || [];
                    let wtxt = '';
                    if (bw.length > 0) {
                        const names   = bw.map(w => players[w] ? players[w].username : '?').join(', ');
                        const payout  = parseFloat(state.bingo_payout_each || 0).toFixed(2);
                        const lineInfo = state.line_paid
                            ? `<br><span style="color:#90caf9; font-size:14px;">🏅 Línea (€${parseFloat(state.line_payout_each||0).toFixed(2)} c/u) ya fue pagada durante el sorteo</span>`
                            : '';
                        wtxt = `Ganador/es:<br><b style="color:#d4af37;">${names}</b><br>Premio bingo: €${payout} por jugador${lineInfo}`;
                    } else if (state.no_bingo_winner) {
                        wtxt = state.line_paid
                            ? `Nadie completó el bingo completo.<br><span style="color:#90caf9;">El premio de línea ya fue repartido.</span>`
                            : `Nadie completó ni línea ni bingo. Las apuestas han sido devueltas.`;
                    }
                    document.getElementById('winner-text').innerHTML = wtxt;
                    document.getElementById('btn-new-round').style.display = state.is_creator ? 'inline-block' : 'none';
                    document.getElementById('winner-overlay').style.display = 'block';
                }

                lastPhase = phase;
            });
        }

        function closeLineAnnouncement() {
            document.getElementById('line-announcement').style.display = 'none';
        }
        function closeWinner() {
            document.getElementById('winner-overlay').style.display = 'none';
        }

        function bingoAction(act) {
            fetch(`api/bingo_action.php?action=${act}&room_id=${roomId}`, { method: 'POST' })
            .then(r => r.json())
            .then(d => {
                if (d.error) { alert(d.error); return; }
                if (act === 'new_round') {
                    closeWinner();
                    closeLineAnnouncement();
                    lastLinePaid     = false;
                    lastBingoWinners = null;
                    knownDrawnCount  = 0;
                }
                updateBingo();
            });
        }

        function leaveRoom() {
            const fd = new FormData(); fd.append('room_id', roomId); fd.append('action', 'leave');
            fetch('api/rooms.php?action=leave', { method: 'POST', body: fd })
            .then(() => window.location.href = 'index.php');
        }

        function updateChat() {
            fetch(`api/chat.php?action=get&room_id=${roomId}`)
            .then(r => r.text())
            .then(html => {
                const chatEl = document.getElementById('chat');
                const wasAtBottom = chatEl.scrollHeight - chatEl.scrollTop <= chatEl.clientHeight + 30;
                if (chatEl.innerHTML !== html) {
                    chatEl.innerHTML = html;
                    if (wasAtBottom) chatEl.scrollTop = chatEl.scrollHeight;
                }
            });
        }
        function sendChat() {
            const input = document.getElementById('chat-msg');
            if (!input.value.trim()) return;
            const form = new FormData(); form.append('message', input.value);
            fetch(`api/chat.php?action=send&room_id=${roomId}`, { method: 'POST', body: form })
            .then(() => { input.value = ''; updateChat(); });
        }

        // Unirse y arrancar polling
        fetch(`api/bingo_action.php?action=join&room_id=${roomId}`, { method: 'POST' })
        .then(() => { updateBingo(); updateChat(); });
        setInterval(updateBingo, 1500);
        setInterval(updateChat,  2000);
    </script>
    <script src="/casino/assets/js/notifications.js"></script>
</body>
</html>
