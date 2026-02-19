<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }
$room_id = $_GET ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mesa de Póker Texas Hold'em</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Unificado y Resumido para el Póker Ovalado */
        body { margin: 0; background: #0a0a0a; color: #fff; font-family: 'Roboto', sans-serif; display: flex; height: 100vh; }
        .game-area { flex: 1; background: url('https://www.transparenttextures.com/patterns/pinstriped-suit.png') #0b3d1f; padding: 20px; display: flex; flex-direction: column; align-items: center; position: relative; }
        .chat-area { width: 300px; background: #111; border-left: 2px solid #d4af37; display: flex; flex-direction: column; }
        .table-oval { width: 80%; max-width: 800px; height: 350px; background: #1a472a; border: 15px solid #4a2f1d; border-radius: 200px; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 0 30px rgba(0,0,0,0.8) inset; margin-top: 50px; }
        .pot { background: rgba(0,0,0,0.6); padding: 5px 15px; border-radius: 20px; color: gold; font-weight: bold; border: 1px solid gold; margin-bottom: 20px; font-size: 20px;}
        .community-cards { display: flex; gap: 10px; }
        .card { width: 60px; height: 85px; background: white; border-radius: 5px; color: black; font-weight: bold; font-size: 18px; line-height: 85px; text-align: center; border: 1px solid #333; }
        .card.red { color: #d00; }
        .card-back { background: repeating-linear-gradient(45deg, #001f3f, #001f3f 10px, #003366 10px, #003366 20px); border: 2px solid #fff; color: transparent; }
        
        .players-container { display: flex; width: 100%; justify-content: space-around; margin-top: 30px; z-index: 10; padding: 0 50px;}
        .player-box { background: rgba(0,0,0,0.8); border: 1px solid #d4af37; padding: 10px; border-radius: 10px; text-align: center; min-width: 120px; }
        .player-box.active { box-shadow: 0 0 20px #0f0; border-color: #0f0; }
        .hole-cards { display: flex; justify-content: center; margin-top: -30px; }
        
        .controls { position: absolute; bottom: 20px; background: rgba(0,0,0,0.9); padding: 15px; border-radius: 10px; border: 1px solid #555;}
        button { background: #d4af37; color: #000; border: none; padding: 10px 15px; margin: 0 5px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background: #fff; }
    </style>
</head>
<body>
    <div class="game-area">
        <div style="width: 100%; display: flex; justify-content: space-between; color: #d4af37;">
            <a href="index.php" style="color:#d4af37; text-decoration:none;">← Salir</a>
            <span id="fase">Esperando jugadores...</span>
        </div>

        <div class="table-oval">
            <div class="pot" id="pot">Pot Total: €0.00</div>
            <div class="community-cards" id="community-cards">
                <!-- Cartas comunitarias -->
            </div>
        </div>

        <div class="players-container" id="players">
            <!-- Jugadores renderizados aquí -->
        </div>

        <div class="controls">
            <button onclick="pokerAction('fold')">Fold</button>
            <button onclick="pokerAction('check_call')">Check / Call</button>
            <input type="number" id="raise-amount" value="50" style="width:70px; padding:5px;">
            <button onclick="pokerAction('raise')">Raise</button>
        </div>
    </div>

    <!-- Implementación Chat re-utilizada -->
    <div class="chat-area">
        <div style="background:#222; padding:15px; color:#d4af37; text-align:center;">Chat Póker</div>
        <div id="chat" style="flex:1; padding:15px; overflow-y:auto; font-size: 14px;"></div>
        <div style="padding:10px; display:flex;">
            <input type="text" id="chat-msg" style="flex:1; padding:8px; border-radius:3px;" placeholder="Mensaje..." onkeypress="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()">Enviar</button>
        </div>
    </div>

    <script>
        const roomId = <?= $room_id ?>;
        const localUserId = <?= $_SESSION ?>;

        function renderCard(cardCode, isHidden) {
            if(isHidden) return `<div class="card card-back"></div>`;
            const = cardCode.split('_');
            const suits = { 'S':'♠', 'H':'♥', 'C':'♣', 'D':'♦' };
            const red = (s==='H'||s==='D')?'red':'';
            return `<div class="card ${red}">${r}${suits}</div>`;
        }

        function updatePoker() {
            fetch(`api/poker_action.php?action=state&room_id=${roomId}`)
            .then(res => res.json()).then(state => {
                if(state.error) return;
                
                document.getElementById('fase').innerText = `Fase: ${state.phase.toUpperCase()}`;
                document.getElementById('pot').innerText = `Pot Total: €${state.pot}`;

                // Comunitarias
                let commHtml = '';
                if(state.community) state.community.forEach(c => commHtml += renderCard(c, false));
                document.getElementById('community-cards').innerHTML = commHtml;

                // Jugadores
                let pHtml = '';
                for(let uid in state.players) {
                    let p = state.players;
                    let turnClass = (state.current_turn == uid) ? 'active' : '';
                    let cardsHtml = '';
                    if(p.hole_cards && p.hole_cards.length > 0) {
                        // Ocultar las cartas si no son del usuario logueado en su explorador, a menos que sea Showdown
                        let hide = (uid != localUserId && state.phase != 'showdown');
                        p.hole_cards.forEach(c => cardsHtml += renderCard(c, hide));
                    }
                    
                    pHtml += `
                        <div class="player-box ${turnClass}">
                            <div class="hole-cards">${cardsHtml}</div>
                            <div>${p.avatar} ${p.username}</div>
                            <div style="color:yellow; font-size:12px;">Bet Mesa: €${p.current_bet}</div>
                            <div style="color:#aaa; font-size:12px;">${p.status}</div>
                        </div>
                    `;
                }
                document.getElementById('players').innerHTML = pHtml;
            });
        }

        function pokerAction(act) {
            let data = new FormData();
            data.append('room_id', roomId);
            data.append('action', act);
            if(act === 'raise') data.append('amount', document.getElementById('raise-amount').value);
            
            fetch('api/poker_action.php', { method: 'POST', body: data }).then(()=>updatePoker());
        }

        function updateChat() { /* Igual que Blackjack */ 
            fetch(`api/chat.php?action=get&room_id=${roomId}`)
            .then(r => r.text()).then(html => document.getElementById('chat').innerHTML = html);
        }
        function sendChat() {
            let i = document.getElementById('chat-msg');
            let form = new FormData(); form.append('message', i.value);
            fetch(`api/chat.php?action=send&room_id=${roomId}`,{method:'POST',body:form}).then(()=>{i.value=''; updateChat()});
        }

        pokerAction('join'); // Unirse automático
        setInterval(updatePoker, 1500); setInterval(updateChat, 1500); updatePoker(); updateChat();
    </script>
</body>
</html>
