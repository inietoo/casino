// Manejo del Lobby
function fetchRooms() {
    fetch('/casino/api/rooms.php?action=list')
    .then(r => r.json())
    .then(data => {
        let bjHtml = ''; let pkHtml = '';
        data.forEach(room => {
            let html = `
            <div class="room-item">
                <div class="room-info">
                    <strong>${room.name}</strong><br>
                    Min: €${room.min_bet} | Máx: €${room.max_bet}<br>
                    <span class="players-count">🧑‍🤝‍🧑 ${room.players}/${room.max_players}</span>
                </div>
                <a href="${room.game_type}.php?room_id=${room.id}" class="btn">Entrar</a>
            </div>`;
            if(room.game_type === 'blackjack') bjHtml += html;
            else pkHtml += html;
        });
        document.getElementById('bj-rooms').innerHTML = bjHtml || '<p class="empty-msg">No hay mesas activas.</p>';
        document.getElementById('pk-rooms').innerHTML = pkHtml || '<p class="empty-msg">No hay mesas activas.</p>';
    });
}

window.createRoom = function(type) {
    fetch(`/casino/api/rooms.php?action=create&type=${type}`)
    .then(r => r.json())
    .then(d => { if(d.success) window.location.href = `${type}.php?room_id=${d.room_id}`; });
}

window.reloadFreeBalance = function() {
    fetch(`/casino/api/auth.php?action=reload`)
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("¡Exito! Has recibido €500.00 de cortesía. ¡Buena suerte!");
            location.reload();
        } else {
            alert(data.error || "No cumples los requisitos (Saldo < €5 y 1 H de espera)");
        }
    });
}

if(document.getElementById('bj-rooms')) {
    setInterval(fetchRooms, 2000);
    fetchRooms();
}
