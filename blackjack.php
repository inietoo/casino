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

        .game-area { flex: 1; background: radial-gradient(circle at 50% 40%, #1a5c2e, #0a1f12); padding: 15px 20px; display: flex; flex-direction: column; align-items: center; position: relative; overflow: hidden; }
        .header { width: 100%; display: flex; justify-content: space-between; align-items: center; color: #d4af37; font-family: 'Cinzel', serif; margin-bottom: 10px; }
        .header a { color: #d4af37; text-decoration: none; border: 1px solid #d4af37; padding: 6px 12px; border-radius: 5px; font-size: 13px; transition: 0.2s; }
        .header a:hover { background: #d4af37; color: #000; }

        .dealer-area { text-align: center; margin: 10px 0; }
        .dealer-label { color: #d4af37; font-family: 'Cinzel', serif; font-size: 14px; margin-bottom: 8px; letter-spacing: 2px; }
        .dealer-total { color: #aaa; font-size: 13px; margin-top: 5px; }

        .cards-row { display: flex; justify-content: center; flex-wrap: wrap; gap: 5px; min-height: 100px; }
        .card { width: 65px; height: 90px; background: white; border-radius: 6px; color: black; font-weight: bold; font-size: 15px; display: flex; flex-direction: column; justify-content: space-between; padding: 5px; box-shadow: 3px 3px 8px rgba(0,0,0,0.6); border: 2px solid #ddd; animation: dealCard 0.3s ease; }
        @keyframes dealCard { from { transform: scale(0.5) rotate(-10deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }
        .card.red { color: #c00; }
        .card-back { background: repeating-linear-gradient(45deg, #0d2137, #0d2137 8px, #153455 8px, #153455 16px); border: 3px solid #fff; color: transparent; }
        .card-rank { font-size: 18px; font-weight: 900; line-height: 1; }
        .card-suit { font-size: 20px; line-height: 1; }

        .players-area { display: flex; justify-content: center; flex-wrap: wrap; gap: 15px; width: 100%; margin: 15px 0; }
        .player-spot { background: rgba(0,0,0,0.6); border: 2px dashed rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 15px; text-align: center; min-width: 130px; transition: all 0.3s; }
        .player-spot.my-spot { border-color: rgba(212,175,55,0.5); }
        .player-spot.active-turn { box-shadow: 0 0 25px #d4af37, 0 0 5px #fff; border-color: #d4af37; transform: scale(1.05); border-style: solid; }
        .player-name { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
        .player-bet  { color: #ffd700; font-size: 12px; margin-bottom: 6px; }
        .player-status { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 6px; }
        .s-playing   { color: #4caf50; } .s-standing  { color: #2196f3; } .s-bust      { color: #f44336; } .s-blackjack { color: #ffd700; } .s-win       { color: #4caf50; } .s-loss      { color: #f44336; } .s-push      { color: #ff9800; } .s-betting   { color: #aaa; } .s-waiting   { color: #555; }
        .hand-value  { font-size: 12px; color: #fff; background: rgba(0,0,0,0.5); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; border: 1px solid rgba(255,255,255,0.2); }

        .result-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); background: rgba(0,0,0,0.92); border: 3px solid #d4af37; border-radius: 15px; padding: 30px 50px; text-align: center; z-index: 100; display: none; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translate(-50%,-50%) scale(0.8); } to { opacity: 1; transform: translate(-50%,-50%) scale(1); } }
        .result-overlay h2 { font-family: 'Cinzel', serif; font-size: 28px; margin-bottom: 10px; }
        .result-overlay .result-text { font-size: 18px; margin-bottom: 20px; }
        .result-overlay button { background: #d4af37; color: #000; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }

        .controls-bar { position: absolute; bottom: 20px; background: rgba(0,0,0,0.9); padding: 15px 25px; border-radius: 12px; display: flex; gap: 8px; align-items: center; border: 1px solid #333; flex-wrap: wrap; justify-content: center; width: 90%; max-width: 700px; box-shadow: 0 5px 20px rgba(0,0,0,0.8); }
        .btn-game { background: #d4af37; color: #000; border: none; padding: 10px 18px; border-radius: 7px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; text-transform: uppercase; }
        .btn-game:hover { background: #fff; transform: scale(1.05); }
        .btn-game:disabled { background: #444 !important; color: #888 !important; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-game.btn-start { background: #28a745; color: #fff; }
        #bet-amount { padding: 9px; width: 85px; border-radius: 7px; border: 2px solid #d4af37; background: #222; color: #fff; font-size: 16px; font-weight: bold; text-align: center; }

        .room-info-bar { width: 100%; display: flex; justify-content: center; gap: 20px; font-size: 13px; color: #aaa; margin-bottom: 5px; flex-wrap: wrap; }
        .room-info-bar span { color: #d4af37; font-weight: bold; }
        .waiting-msg { text-align: center; color: #888; font-style: italic; font-size: 14px; padding: 20px; }
        .players-count-badge { background: #d4af37; color: #000; border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: bold; }

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
        <div class="header">
            <button id="btn-leave" onclick="leaveRoom()" class="btn-game" style="background:#dc3545; color:white; font-size:12px; padding:6px 12px; margin-right: 15px;">← Salir al Lobby</button>
            <div style="font-size:14px;">
                ♠ <?= htmlspecialchars($room['name']) ?>
                &nbsp; <span class="players-count-badge" id="player-badge">0 jugadores</span>
            </div>
            <div id="phase-label" style="font-size:13px; color:#aaa;">Esperando jugadores...</div>
        </div>

        <div class="room-info-bar">
            <div>Min: <span>€<?= number_format($room['min_bet'],2) ?></span></div>
            <div>Max: <span>€<?= number_format($room['max_bet'],2) ?></span></div>
            <div>ID Sala: <span>#<?= $room_id ?></span></div>
        </div>

        <div class="dealer-area">
            <div class="dealer-label">♦ DEALER ♦</div>
            <div class="cards-row" id="dealer-cards"></div>
            <div class="dealer-total" id="dealer-total"></div>
        </div>

        <div id="waiting-msg" class="waiting-msg">Esperando a que se unan jugadores o el lider inicie...</div>
        <div class="players-area" id="players-zone"></div>

        <div class="result-overlay" id="result-overlay">
            <h2 id="result-title">¡Fin de Ronda!</h2>
            <div class="result-text" id="result-body"></div>
            <button id="result-btn" onclick="closeResult()">Nueva Ronda</button>
        </div>

        <div class="controls-bar" id="controls-bar">
            <div id="action-controls" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:center; width:100%;"></div>
        </div>
    </div>

    <div class="chat-area">
        <div class="chat-header">💬 Chat de Mesa</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input-area">
            <input type="text" id="chat-msg" placeholder="Escribe un mensaje..." maxlength="200" onkeypress="if(event.key==='Enter') sendChat()">
            <button class="chat-send-btn" onclick="sendChat()">›</button>
        </div>
    </div>

    <script>
        const roomId      = <?= $room_id ?>;
        const myUserId    = <?= intval($user_id) ?>;
        const isCreator   = <?= json_encode(isset($_SESSION['user_id'])) ?>;
        let   lastPhase   = '';

        function updateHTML(id, html) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.dataset.lastHtml !== html) { el.innerHTML = html; el.dataset.lastHtml = html; }
        }

        function renderCard(cardCode) {
            if (!cardCode || cardCode === 'hidden') return `<div class="card card-back"></div>`;
            const parts = cardCode.split('_'); const rank = parts[0]; const suit = parts[1];
            const suits = { 'S': '♠', 'H': '♥', 'C': '♣', 'D': '♦' };
            const isRed = (suit === 'H' || suit === 'D') ? 'red' : '';
            return `<div class="card ${isRed}"><div class="card-rank">${rank}</div><div class="card-suit">${suits[suit] || suit}</div></div>`;
        }

        function getHandValues(cards) {
            if (!cards || cards.length === 0) return { total: 0, isSoft: false, softTotal: 0 };
            let total = 0, aces = 0;
            for (const c of cards) {
                if (!c || c === 'hidden') continue;
                const r = c.split('_')[0];
                if (['J','Q','K'].includes(r)) total += 10;
                else if (r === 'A') { total += 11; aces++; }
                else total += parseInt(r);
            }
            let isSoft = false; let softTotal = total;
            while (total > 21 && aces > 0) { total -= 10; aces--; }
            if (aces > 0 && total <= 21) { isSoft = true; softTotal = total - 10; }
            return { total: total, isSoft: isSoft, softTotal: softTotal };
        }

        function updateTable() {
            // Añadido _=Date.now() para evitar que el navegador guarde la caché de tu jugada
            fetch(`api/blackjack_action.php?action=state&room_id=${roomId}&_=${Date.now()}`).then(r => r.json()).then(state => {
                if (!state || state.error) return;

                const phase        = state.phase || 'waiting';
                const players      = state.players || {};
                const playerCount  = state.player_count || 0;
                const iAmCreator   = state.is_creator;

                updateHTML('player-badge', `${playerCount} jugador${playerCount !== 1 ? 'es' : ''}`);
                const phaseLabels = { waiting: 'Esperando jugadores', betting: 'Fase de apuestas', playing: 'En juego', dealer_turn: 'Turno del dealer', finished: 'Fin de ronda' };
                updateHTML('phase-label', phaseLabels[phase] || phase.toUpperCase());

                const dealerCards = state.dealer_cards_display || [];
                let dealerHtml = ''; dealerCards.forEach(c => dealerHtml += renderCard(c));
                updateHTML('dealer-cards', dealerHtml);

                if (phase === 'finished' || phase === 'dealer_turn') {
                    const realCards = state.dealer_cards || [];
                    const dv = getHandValues(realCards);
                    updateHTML('dealer-total', realCards.length ? `Total dealer: ${dv.isSoft ? dv.softTotal + ' / ' + dv.total : dv.total}` : '');
                } else { updateHTML('dealer-total', ''); }

                const waitingMsg = document.getElementById('waiting-msg');
                const showWaiting = (playerCount < 1 && phase === 'waiting'); 
                if (waitingMsg.style.display !== (showWaiting ? 'block' : 'none')) waitingMsg.style.display = showWaiting ? 'block' : 'none';

                let playersHtml = '';
                for (const uid in players) {
                    const p = players[uid];
                    const realBaseUid = parseInt(uid.toString().split('_')[0]);
                    const isMe = (realBaseUid === myUserId);
                    const isTurn = (state.current_turn == uid);
                    const classes = ['player-spot', isMe ? 'my-spot' : '', isTurn ? 'active-turn' : ''].join(' ');

                    let cardsHtml = '';
                    if (p.cards && p.cards.length > 0) p.cards.forEach(c => cardsHtml += renderCard(c));

                    const hvObj = p.cards && p.cards.length ? getHandValues(p.cards) : {total:0};
                    const hvText = hvObj.total > 0 ? `<div class="hand-value">Total: ${hvObj.isSoft && p.status === 'playing' ? hvObj.softTotal + ' / ' + hvObj.total : hvObj.total}</div>` : '';

                    const statusClass = `s-${p.status || 'waiting'}`;
                    const bet = parseFloat(p.bet || 0).toFixed(2);
                    const meLabel = isMe ? ' <span style="color:#d4af37;font-size:10px;">(Tú)</span>' : '';
                    const turnArrow = isTurn ? '<div style="color:#d4af37;font-size:16px;">▼</div>' : '';

                    const statusMap = { betting: 'Apostando...', waiting: 'Esperando', playing: 'Jugando', standing: 'Plantado', bust: '¡Pasado! (Bust)', blackjack: '¡Blackjack!', win: '✅ Ganó', loss: '❌ Perdió', push: '🤝 Empate', ready: 'Listo' };

                    playersHtml += `
                        <div class="${classes}">
                            ${turnArrow}
                            <div class="player-name">${p.avatar || '🎲'} ${p.username || 'Jugador'}${meLabel}</div>
                            <div class="player-bet">Apuesta: €${bet}</div>
                            <div class="player-balance" style="color:#28a745; font-size:12px; margin-bottom:4px; font-weight:bold;">Saldo: €${parseFloat(p.balance || 0).toFixed(2)}</div>
                            <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:3px;margin:6px 0;">${cardsHtml}</div>
                            ${hvText}
                            <div class="player-status ${statusClass}">${statusMap[p.status] || p.status || ''}</div>
                            ${p.payout > 0 ? `<div style="color:#4caf50;font-size:12px;margin-top:3px;">+€${parseFloat(p.payout).toFixed(2)}</div>` : ''}
                        </div>`;
                }
                updateHTML('players-zone', playersHtml);
                renderControls(state, iAmCreator, phase);

                if (phase === 'finished' && lastPhase !== 'finished') { showResult(state, players); }
                lastPhase = phase;
                
                const canLeave = ['waiting', 'finished'].includes(phase);
                const btnLeave = document.getElementById('btn-leave');
                if(btnLeave) { btnLeave.disabled = !canLeave; btnLeave.style.opacity = canLeave ? '1' : '0.5'; }
            }).catch(e => console.error('Update error:', e));
        }

        function renderControls(state, iAmCreator, phase) {
            const activeTurnId = (state.current_turn || '').toString();
            
            // LA CORRECCION CLAVE ESTA AQUI: Una comprobación estricta en lugar de "startsWith" para que no haya confusiones.
            const isMyTurn = (activeTurnId === myUserId.toString() || activeTurnId === myUserId.toString() + '_split'); 
            
            const myPlayer = (isMyTurn && state.players[activeTurnId]) ? state.players[activeTurnId] : (state.players ? state.players[myUserId] : null);
            const playerCount = state.player_count || 0;
            
            let lastBet = localStorage.getItem('bj_last_bet') || 50;
            let customBet = localStorage.getItem('bj_custom_bet') || 150;
            let html = '';

            let resetBtn = iAmCreator ? `<button class="btn-game" style="background:#dc3545; color:white; font-size:10px; position:absolute; right:10px; top:-35px;" onclick="if(confirm('¿Seguro que quieres forzar el reinicio de la mesa?')) sendAction('reset_table')">⚠️ Resetear Mesa</button>` : '';

            if (phase === 'waiting') {
                if (iAmCreator && playerCount >= 1) html += `${resetBtn}<button class="btn-game btn-start" onclick="sendAction('start')">🎮 Iniciar Partida</button>`;
                else html += `${resetBtn}<button class="btn-game" disabled>Esperando que el líder inicie...</button>`;
            } 
            else if (phase === 'betting') {
                if (myPlayer && myPlayer.status === 'betting') {
                    // CÓDIGO MEJORADO PARA APUESTAS RÁPIDAS
                    html += `
                    <div style="display:flex; flex-direction:column; gap:12px; width:100%; position:relative; align-items:center;">
                        ${resetBtn}
                        <div style="text-align:center; color:#aaa; font-size:13px; text-transform:uppercase; font-family:'Cinzel', serif; letter-spacing:1px;">Apuestas Instantáneas</div>
                        
                        <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                            <button class="btn-game" style="font-size:13px; padding:10px 15px; background:#222; color:#fff; border: 2px solid #555;" onclick="setBetAndSend(50)">€50</button>
                            <button class="btn-game" style="font-size:13px; padding:10px 15px; background:#222; color:#fff; border: 2px solid #555;" onclick="setBetAndSend(100)">€100</button>
                            <button class="btn-game" style="font-size:13px; padding:10px 15px; background:#222; color:#fff; border: 2px solid #555;" onclick="setBetAndSend(200)">€200</button>
                            <button class="btn-game" style="font-size:13px; padding:10px 15px; background:#17a2b8; color:#fff;" onclick="setBetAndSend(${lastBet})">Última (€${lastBet})</button>
                            <button class="btn-game" style="font-size:13px; padding:10px 15px; background:#6f42c1; color:white; box-shadow: 0 0 10px rgba(111,66,193,0.5);" onclick="setBetAndSend(${customBet})">★ Custom (€${customBet})</button>
                        </div>

                        <div style="display:flex; gap:10px; justify-content:center; align-items:center; margin-top:5px; padding-top:15px; border-top: 1px solid #333; width:80%;">
                            <span style="color:#aaa; font-size:12px;">Otra cantidad:</span>
                            <input type="number" id="bet-amount" value="50" min="10" max="500" placeholder="€">
                            <button class="btn-game" onclick="saveBetAndSend()">💰 Apostar</button>
                        </div>
                    </div>`;
                } else html += `${resetBtn}<button class="btn-game" disabled>Esperando tu turno de apuesta...</button>`;
            } 
            else if (phase === 'playing') {
                if (isMyTurn) {
                    html += `${resetBtn}
                            <button class="btn-game" onclick="sendAction('hit')" style="background:#28a745;">🃏 Pedir</button>
                            <button class="btn-game" onclick="sendAction('stand')" style="background:#dc3545;">✋ Plantarse</button>`;
                    
                    if (myPlayer && myPlayer.cards && myPlayer.cards.length === 2) {
                        html += `<button class="btn-game" onclick="sendAction('double')" style="background:#ff9800; color:#000;">⬆️ Doblar x2</button>`;
                        
                        const r1 = myPlayer.cards[0].split('_')[0]; 
                        const r2 = myPlayer.cards[1].split('_')[0];
                        const val1 = ['J','Q','K'].includes(r1) ? 10 : (r1 === 'A' ? 11 : parseInt(r1));
                        const val2 = ['J','Q','K'].includes(r2) ? 10 : (r2 === 'A' ? 11 : parseInt(r2));
                        
                        // Si las cartas valen lo mismo, dejamos usar Split. Si no, lo mostramos bloqueado.
                        if (val1 === val2 && !activeTurnId.includes('_split')) {
                            html += `<button class="btn-game" onclick="sendAction('split')" style="background:#17a2b8; color:#fff;">➗ Dividir</button>`;
                        } else {
                            html += `<button class="btn-game" disabled style="background:#333 !important; color:#777 !important;" title="Necesitas 2 cartas con el mismo valor (ej. dos 8, o un 10 y una J)">➗ Dividir (Bloqueado)</button>`;
                        }
                    }
                } else html += `${resetBtn}<button class="btn-game" disabled>Turno de otro jugador...</button>`;
            } 
            else if (phase === 'dealer_turn') {
                html += `${resetBtn}<button class="btn-game" disabled>El dealer está jugando...</button>`;
            } 
            else if (phase === 'finished') {
                if (iAmCreator) html += `${resetBtn}<button class="btn-game btn-start" onclick="sendAction('new_round')">🔄 Nueva Ronda</button>`;
                else html += `<button class="btn-game" disabled>Esperando nueva ronda...</button>`;
            }
            updateHTML('action-controls', html);
        }

        // FUNCION PARA ENVIAR LA APUESTA AL INSTANTE (1 CLIC)
        function setBetAndSend(amount) {
            document.getElementById('bet-amount').value = amount;
            saveBetAndSend();
        }

        function saveBetAndSend() {
            const amt = document.getElementById('bet-amount').value;
            localStorage.setItem('bj_last_bet', amt);
            sendAction('bet');
        }

        function showResult(state, players) {
            const mainHand = players[myUserId];
            const splitHand = players[myUserId + '_split'];
            if (!mainHand) return;

            const overlay  = document.getElementById('result-overlay');
            const title    = document.getElementById('result-title');
            const body     = document.getElementById('result-body');
            const btn      = document.getElementById('result-btn');

            let totalPayout = parseFloat(mainHand.payout || 0);
            let totalBet = parseFloat(mainHand.bet || 0);
            let msg = `Mano principal: ${mainHand.status.toUpperCase()} <br>`;

            if (splitHand) {
                totalPayout += parseFloat(splitHand.payout || 0);
                totalBet += parseFloat(splitHand.bet || 0);
                msg += `Mano dividida: ${splitHand.status.toUpperCase()} <br>`;
            }

            title.textContent = totalPayout > totalBet ? '🏆 ¡Ganaste!' : (totalPayout == totalBet && totalBet > 0 ? '🤝 Empate' : 'Ronda Finalizada');
            title.style.color = totalPayout > totalBet ? '#4caf50' : '#fff';

            if (totalPayout > 0) msg += `<br>Cobras: €${totalPayout.toFixed(2)} | Ganancia: +€${(totalPayout - totalBet).toFixed(2)}`;
            else msg += `<br>Perdiste: €${totalBet.toFixed(2)}`;

            body.innerHTML = msg;
            btn.textContent  = state.is_creator ? 'Nueva Ronda' : 'Cerrar';
            btn.onclick      = state.is_creator ? () => { closeResult(); sendAction('new_round'); } : () => closeResult();
            overlay.style.display = 'block';
        }

        function closeResult() { document.getElementById('result-overlay').style.display = 'none'; }

        function sendAction(action) {
            const formData = new FormData(); formData.append('room_id', roomId); formData.append('action', action);
            if (action === 'bet') { const amtEl = document.getElementById('bet-amount'); formData.append('amount', amtEl ? amtEl.value : 50); }
            fetch('api/blackjack_action.php', { method: 'POST', body: formData }).then(r => r.json()).then(d => { if (d && d.error) alert('⚠️ ' + d.error); else updateTable(); }).catch(e => console.error('Action error:', e));
        }
        
        function leaveRoom() {
            if (document.getElementById('btn-leave').disabled) { alert('No puedes abandonar la sala en medio de una mano activa.'); return; }
            const formData = new FormData(); formData.append('room_id', roomId); formData.append('action', 'leave');
            fetch('api/rooms.php?action=leave', { method: 'POST', body: formData }).then(() => window.location.href = 'index.php');
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

        const joinData = new FormData(); joinData.append('room_id', roomId); joinData.append('action', 'join');
        fetch('api/blackjack_action.php', { method: 'POST', body: joinData }).then(() => { updateTable(); updateChat(); });

        setInterval(updateTable, 1500); setInterval(updateChat, 2000);
    </script>
    <script src="/casino/assets/js/notifications.js"></script>
</body>
</html>
