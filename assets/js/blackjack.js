const urlParams = new URLSearchParams(window.location.search);
const roomId = urlParams.get('room_id');

function sendAction(action) {
    let formData = new FormData();
    formData.append('room_id', roomId);
    formData.append('action', action);
    
    if(action === 'bet') {
        formData.append('amount', document.getElementById('bet-amount').value);
    }

    fetch('/casino/api/blackjack_action.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => { 
            if(d && d.error) alert(d.error); 
            updateTable(); 
        })
        .catch(e => console.error("Error en blackjack action:", e));
}

function renderCard(cardCode) {
    if(cardCode === 'hidden') return `<div class="playing-card back"></div>`;
    const parts = cardCode.split('_');
    const suits = { 'S':'♠', 'H':'♥', 'C':'♣', 'D':'♦' };
    const isRed = (parts === 'H' || parts === 'D') ? 'red' : '';
    return `<div class="playing-card ${isRed}">${parts}${suits]}</div>`;
}

function updateTable() {
    fetch(`/casino/api/blackjack_action.php?action=state&room_id=${roomId}`)
    .then(r => r.json())
    .then(state => {
        if(state.error) return;
        
        document.getElementById('room-status').innerText = `Fase: ${state.status.toUpperCase()}`;
        
        // Dealer
        let dealerHtml = '';
        if(state.dealer_cards) state.dealer_cards.forEach(c => dealerHtml += renderCard(c));
        document.getElementById('dealer-cards').innerHTML = dealerHtml;

        // Players
        let playersHtml = '';
        for(let uid in state.players) {
            let p = state.players;
            let turnClass = (state.current_turn == uid) ? 'active-turn' : '';
            let cardsHtml = '';
            if(p.cards) p.cards.forEach(c => cardsHtml += renderCard(c));
            
            playersHtml += `
                <div class="player-spot ${turnClass}">
                    <div class="player-name">${p.avatar} ${p.username}</div>
                    <div class="player-bet">Apt: €${p.bet}</div>
                    <div class="cards-container">${cardsHtml}</div>
                    <div class="player-status">${p.status.toUpperCase()}</div>
                </div>
            `;
        }
        document.getElementById('players-zone').innerHTML = playersHtml;
    });
}

// Iniciar
sendAction('join');
setInterval(updateTable, 1500); // AJAX Polling UI Mesa Múltiple
