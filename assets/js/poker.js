const urlParams = new URLSearchParams(window.location.search);
const roomId = urlParams.get('room_id');
// Necesitas que el body del poker.php pase el userId, ej: <script> const localUserId = <?= $_SESSION ?>; </script>

function renderCard(cardCode, isHidden) {
    if(isHidden) return `<div class="playing-card back"></div>`;
    const parts = cardCode.split('_');
    const suits = { 'S':'♠', 'H':'♥', 'C':'♣', 'D':'♦' };
    const red = (parts==='H'||parts==='D') ? 'red' : '';
    return `<div class="playing-card ${red}">${parts}${suits]}</div>`;
}

function pokerAction(act) {
    let data = new FormData();
    data.append('room_id', roomId);
    data.append('action', act);
    if(act === 'raise') data.append('amount', document.getElementById('raise-amount').value);
    
    fetch('/casino/api/poker_action.php', { method: 'POST', body: data }).then(()=>updatePoker());
}

function updatePoker() {
    fetch(`/casino/api/poker_action.php?action=state&room_id=${roomId}`)
    .then(res => res.json()).then(state => {
        if(state.error) return;
        
        document.getElementById('fase').innerText = `Fase: ${state.phase.toUpperCase()}`;
        document.getElementById('pot').innerText = `Pot Total: €${state.pot}`;

        // Comunitarias
        let commHtml = '';
        if(state.community) state.community.forEach(c => commHtml += renderCard(c, false));
        document.getElementById('community-cards').innerHTML = commHtml;

        // Jugadores (Agrupados en mesa ovalada)
        let pHtml = '';
        for(let uid in state.players) {
            let p = state.players;
            let turnClass = (state.current_turn == uid) ? 'active' : '';
            let cardsHtml = '';
            
            if(p.hole_cards && p.hole_cards.length > 0) {
                // localUserId debe ser definido globalmente en el poker.php antes de cargar este script
                let hide = (uid != localUserId && state.phase != 'showdown');
                p.hole_cards.forEach(c => cardsHtml += renderCard(c, hide));
            }
            
            pHtml += `
                <div class="player-box ${turnClass}">
                    <div class="hole-cards">${cardsHtml}</div>
                    <div class="player-name">${p.avatar} ${p.username}</div>
                    <div class="player-bet">Mesa: €${p.current_bet}</div>
                    <div class="player-status">${p.status}</div>
                </div>
            `;
        }
        document.getElementById('players').innerHTML = pHtml;
    });
}

pokerAction('join');
setInterval(updatePoker, 1500);
