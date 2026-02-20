const urlParams = new URLSearchParams(window.location.search);
const roomId = urlParams.get('room_id');

function sendAction(action) {
    let formData = new FormData();
    formData.append('room_id', roomId);
    formData.append('action', action);
    
    if(action === 'bet') {
        const betEl = document.getElementById('bet-amount');
        formData.append('amount', betEl ? betEl.value : 50);
    }

    fetch('api/blackjack_action.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => { 
            if(d && d.error) alert(d.error); 
            updateTable(); 
        })
        .catch(e => console.error("Error en blackjack action:", e));
}

function renderCard(cardCode) {
    if(!cardCode || cardCode === 'hidden') return `<div class="playing-card back"></div>`;
    const parts = cardCode.split('_');
    const suits = { 'S':'♠', 'H':'♥', 'C':'♣', 'D':'♦' };
    const isRed = (parts[1] === 'H' || parts[1] === 'D') ? 'red' : '';
    return `<div class="playing-card ${isRed}">${parts[0]}${suits[parts[1]]}</div>`;
}

function updateTable() {
    fetch(`api/blackjack_action.php?action=state&room_id=${roomId}`)
    .then(r => r.json())
    .then(state => {
        if(!state || state.error) return;
        
        const phaseLabel = document.getElementById('phase-label');
        if(phaseLabel) phaseLabel.innerText = `Fase: ${state.phase.toUpperCase()}`;
        
        // Dealer
        let dealerHtml = '';
        if(state.dealer_cards_display) state.dealer_cards_display.forEach(c => dealerHtml += renderCard(c));
        const dealerCardsEl = document.getElementById('dealer-cards');
        if(dealerCardsEl) dealerCardsEl.innerHTML = dealerHtml;

        // Players
        let playersHtml = '';
        const players = state.players || {};
        for(let uid in players) {
            let p = players[uid];
            let turnClass = (state.current_turn == uid) ? 'active-turn' : '';
            let cardsHtml = '';
            if(p.cards) p.cards.forEach(c => cardsHtml += renderCard(c));
            
            playersHtml += `
                <div class="player-spot ${turnClass}">
                    <div class="player-name">${p.avatar || '🎲'} ${p.username}</div>
                    <div class="player-bet">Apt: €${parseFloat(p.bet).toFixed(2)}</div>
                    <div class="cards-container">${cardsHtml}</div>
                    <div class="player-status">${p.status.toUpperCase()}</div>
                </div>
            `;
        }
        const playersZone = document.getElementById('players-zone');
        if(playersZone) playersZone.innerHTML = playersHtml;
    });
}

// Iniciar
setInterval(updateTable, 1500);
updateTable();
