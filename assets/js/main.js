window.createRoom = function(type) {
    fetch(`/casino/api/rooms.php?action=create&type=${type}`)
    .then(r => r.json())
    .then(d => { if(d.success) window.location.href = `${type}.php?room_id=${d.room_id}`; else alert(d.error); });
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
