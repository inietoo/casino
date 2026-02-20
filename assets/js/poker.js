// NOTA: Este archivo es un helper auxiliar.
// La lógica principal del póker está en poker.php (inline).
// Se mantiene aquí por si se quiere refactorizar en el futuro.

const urlParams = new URLSearchParams(window.location.search);
const roomId    = urlParams.get('room_id');

// localUserId debe definirse en poker.php antes de cargar este script:
// <script>const localUserId = <?= intval($_SESSION['user_id']) ?>;</script>

function renderCard(cardCode, isHidden) {
    if (isHidden || !cardCode || cardCode === 'hidden') {
        return `<div class="playing-card back"></div>`;
    }
    const parts = cardCode.split('_');
    const rank  = parts[0];
    const suit  = parts[1];
    const suits = { 'S': '♠', 'H': '♥', 'C': '♣', 'D': '♦' };
    // CORRECCIÓN: antes comparaba parts (array) con string → siempre false
    const isRed = (suit === 'H' || suit === 'D') ? 'red' : '';
    // CORRECCIÓN: antes tenía suits] en lugar de suits[suit]
    return `<div class="playing-card ${isRed}">${rank}${suits[suit] || suit}</div>`;
}

function pokerAction(act) {
    const data = new FormData();
    data.append('room_id', roomId);
    data.append('action', act);
    if (act === 'raise') {
        const el = document.getElementById('raise-amount');
        data.append('amount', el ? el.value : 40);
    }
    fetch('/casino/api/poker_action.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => { if (d && d.error) alert(d.error); else updatePoker(); })
        .catch(e => console.error('Poker action error:', e));
}

function updatePoker() {
    fetch(`/casino/api/poker_action.php?action=state&room_id=${roomId}`)
    .then(res => res.json())
    .then(state => {
        if (!state || state.error) return;

        const phaseEl = document.getElementById('fase');
        const potEl   = document.getElementById('pot');
        const commEl  = document.getElementById('community-cards');
        const playEl  = document.getElementById('players');

        if (phaseEl) phaseEl.innerText = `Fase: ${state.phase.toUpperCase()}`;
        if (potEl)   potEl.innerText   = `Pot Total: €${state.pot || 0}`;

        // Cartas comunitarias
        if (commEl) {
            let commHtml = '';
            (state.community || []).forEach(c => commHtml += renderCard(c, false));
            commEl.innerHTML = commHtml;
        }

        // Jugadores
        if (playEl) {
            let pHtml = '';
            for (const uid in state.players) {
                // CORRECCIÓN: antes era state.players (el objeto entero) en lugar de state.players[uid]
                const p        = state.players[uid];
                const isTurn   = (state.current_turn == uid);
                const turnClass = isTurn ? 'active' : '';
                let cardsHtml  = '';

                if (p.hole_cards && p.hole_cards.length > 0) {
                    // CORRECCIÓN: localUserId puede no estar definido si el script se usa sin el context de poker.php
                    const myId = typeof localUserId !== 'undefined' ? localUserId : -1;
                    const hide = (uid != myId && state.phase !== 'showdown');
                    p.hole_cards.forEach(c => cardsHtml += renderCard(c, hide));
                }

                pHtml += `
                    <div class="player-box ${turnClass}">
                        <div class="hole-cards">${cardsHtml}</div>
                        <div class="player-name">${p.avatar || '🃏'} ${p.username || 'Jugador'}</div>
                        <div class="player-bet">Apuesta: €${p.current_bet || 0}</div>
                        <div class="player-status">${p.status || ''}</div>
                    </div>
                `;
            }
            playEl.innerHTML = pHtml;
        }
    })
    .catch(e => console.error('Poker update error:', e));
}

// Auto-join y polling
if (roomId) {
    const joinData = new FormData();
    joinData.append('room_id', roomId);
    joinData.append('action', 'join');
    fetch('/casino/api/poker_action.php', { method: 'POST', body: joinData })
        .then(() => updatePoker());

    setInterval(updatePoker, 1500);
}
