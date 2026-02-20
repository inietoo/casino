function pollNotifications() {
    fetch('/casino/api/notifications.php')
    .then(r => r.json())
    .then(data => {
        if (data.error) return;

        // Actualizar saldo global en el lobby si el elemento existe
        const balEl = document.getElementById('header-balance');
        if (balEl) {
            balEl.innerHTML = '&euro; ' + parseFloat(data.balance).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Mostrar las alertas
        if (data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(n => {
                showToast(n.message);
            });
        }
    })
    .catch(e => console.error("Error polling notifications:", e));
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.background = '#d4af37';
    toast.style.color = '#000';
    toast.style.padding = '15px 20px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 4px 15px rgba(0,0,0,0.5)';
    toast.style.zIndex = '9999';
    toast.style.fontWeight = 'bold';
    toast.style.fontFamily = 'Roboto, sans-serif';
    toast.style.transition = 'opacity 0.5s';
    toast.innerText = '🔔 ' + message;

    document.body.appendChild(toast);

    // Desaparecer a los 5 segundos
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}

// Consultar cada 3 segundos
setInterval(pollNotifications, 3000);
setTimeout(pollNotifications, 1000); // Llamada inicial rápida
