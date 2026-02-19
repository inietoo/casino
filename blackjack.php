<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }
$room_id = $_GET ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mesa de Blackjack</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; background: #0a0a0a; color: #fff; font-family: 'Roboto', sans-serif; display: flex; height: 100vh; overflow: hidden; }
        .game-area { flex: 1; background: radial-gradient(circle, #1a472a, #0a1f12); padding: 20px; display: flex; flex-direction: column; align-items: center; position: relative; }
        .chat-area { width: 300px; background: #111; border-left: 2px solid #d4af37; display: flex; flex-direction: column; }
        .header { width: 100%; display: flex; justify-content: space-between; color: #d4af37; font-weight: bold; }
        .dealer-zone, .players-zone { display: flex; justify-content: center; gap: 20px; width: 100%; margin: 20px 0; min-height: 150px; }
        .card { width: 70px; height: 100px; background: white; border-radius: 5px; color: black; font-weight: bold; font-size: 20px; line-height: 100px; text-align: center; display: inline-block; margin: -20px 5px 0 5px; box-shadow: 2px 2px 5px rgba(0,0,0,0.5); border: 2px solid #ddd; }
        .card.red { color: #d00; }
        .card-back { background: repeating-linear-gradient(45deg, #b00, #b00 10px, #800 10px, #800 20px); border: 2px solid #fff; color: transparent; }
        .player-spot { border: 2px dashed rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; text-align: center; min-width: 120px; }
        .player-spot.active-turn { box-shadow: 0 0 15px #d4af37; border-color: #d4af37; }
        .controls { background: rgba(0,0,0,0.8); padding: 20px; border-radius: 10px; position: absolute; bottom: 20px; }
        button { background: #d4af37; color: #000; border: none; padding: 10px 20px; margin: 0 5px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background: #fff; }
        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; font-size: 14px; }
        .chat-input { padding: 10px; display: flex; }
        .chat-input input { flex: 1; padding: 8px; background: #222; color: #fff; border: 1px solid #555; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="game-area">
        <div class="header">
            <a href="index.php" style="color:#d4af37; text-decoration:none;">← Salir al Lobby</a>
            <span id="room-status">Cargando...</span>
        </div>
        
        <div style="text-align:center; color:#d4af37; margin-top:20px;">Dealer</div>
        <div class="dealer-zone" id="dealer-cards">
            <!-- Renderizado por JS -->
        </div>

        <div class="players-zone" id="players-zone">
            <!-- Renderizado por JS -->
        </div>

        <div class="controls" id="controls">
            <input type="number" id="bet-amount" value="50" min="10" style="padding:10px; width:80px;">
            <button onclick="sendAction('bet')">Apostar</button>
            <button onclick="sendAction('hit')">Pedir Carta (Hit)</button>
            <button onclick="sendAction('stand')">Plantarse (Stand)</button>
            <button onclick="sendAction('double')">Doblar (Double)</button>
        </div>
    </div>

    <div class="chat-area">
        <div style="background:#222; padding:15px; color:#d4af37; font-weight:bold; text-align:center; border-bottom:1px solid #d4af37;">Chat de la Mesa</div>
        <div class="chat-messages" id="chat"></div>
        <div class="chat-input">
            <input type="text" id="chat-msg" placeholder="Escribe un mensaje..." onkeypress="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()">Enviar</button>
        </div>
    </div>

    <script>
        const roomId = <?= $room_id ?>;
        
        // Función para enviar comandos al servidor
        function sendAction(action) {
            let formData = new FormData();
            formData.append('room_id', roomId);
            formData.append('action', action);
            if(action === 'bet') formData.append('amount', document.getElementById('bet-amount').value);

            fetch('api/blackjack_action.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(d => { if(d.error) alert(d.error); updateTable(); });
        }

        function renderCard(cardCode) {
            if(cardCode === 'hidden') return `<div class="card card-back"></div>`;
            const = cardCode.split('_');
            const suits = { 'S':'♠', 'H':'♥', 'C':'♣', 'D':'♦' };
            const isRed = (suit === 'H' || suit === 'D') ? 'red' : '';
            return `<div class="card ${isRed}">${rank}${suits}</div>`;
        }

        function updateTable() {
            fetch(`api/blackjack_action.php?action=state&room_id=${roomId}`)
            .then(r => r.json()).then(state => {
                if(state.error) return;
                
                document.getElementById('room-status').innerText = `Fase: ${state.status.toUpperCase()}`;
                
                // Mapeo dealer
                let dealerHtml = '';
                if(state.dealer_cards) state.dealer_cards.forEach(c => dealerHtml += renderCard(c));
                document.getElementById('dealer-cards').innerHTML = dealerHtml;

                // Mapeo jugadores
                let playersHtml = '';
                for(let uid in state.players) {
                    let p = state.players;
                    let turnClass = (state.current_turn == uid) ? 'active-turn' : '';
                    let cardsHtml = '';
                    if(p.cards) p.cards.forEach(c => cardsHtml += renderCard(c));
                    
                    playersHtml += `
                        <div class="player-spot ${turnClass}">
                            <div>${p.avatar} ${p.username}</div>
                            <div style="color:yellow;">Apt: €${p.bet}</div>
                            <div>${cardsHtml}</div>
                            <div style="margin-top:10px; font-weight:bold; color:#0f0;">${p.status}</div>
                        </div>
                    `;
                }
                document.getElementById('players-zone').innerHTML = playersHtml;
            });
        }

        function updateChat() {
            fetch(`api/chat.php?action=get&room_id=${roomId}`)
            .then(r => r.text()).then(html => document.getElementById('chat').innerHTML = html);
        }

        function sendChat() {
            let input = document.getElementById('chat-msg');
            if(input.value.trim() === '') return;
            let form = new FormData();
            form.append('message', input.value);
            fetch(`api/chat.php?action=send&room_id=${roomId}`, { method: 'POST', body: form }).then(() => { input.value = ''; updateChat(); });
        }

        // Unirse automáticamente al cargar
        sendAction('join');
        
        // Polling cada 1.5s
        setInterval(updateTable, 1500);
        setInterval(updateChat, 1500);
        updateTable(); updateChat();
    </script>
</body>
</html>
