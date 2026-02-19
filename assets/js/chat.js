function initChat(roomId) {
    const chatBox = document.getElementById('chat');
    const chatInput = document.getElementById('chat-msg');

    function updateChat() {
        fetch(`/casino/api/chat.php?action=get&room_id=${roomId}`)
        .then(r => r.text())
        .then(html => {
            chatBox.innerHTML = html;
        });
    }

    window.sendChat = function() { // Expuesto globalmente para el onclick HTML
        if(chatInput.value.trim() === '') return;
        let form = new FormData(); 
        form.append('message', chatInput.value);
        
        fetch(`/casino/api/chat.php?action=send&room_id=${roomId}`, { method: 'POST', body: form })
        .then(() => { 
            chatInput.value = ''; 
            updateChat(); 
            // Auto scroll down
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    }

    setInterval(updateChat, 1500);
}
// En tu poker.php y blackjack.php deberás llamar: initChat(roomId); al final del archivo.
