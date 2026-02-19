document.addEventListener("DOMContentLoaded", function() {
    fetch('/casino/api/stats.php')
    .then(r => r.json())
    .then(data => {
        // Graficar historial evolutivo de balance general
        if(document.getElementById('balanceChart')) {
            const ctxLine = document.getElementById('balanceChart').getContext('2d');
            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: data.balance_evolution.map((_, i) => `Movimiento ${i+1}`),
                    datasets:
                },
                options: { responsive: true, plugins: { legend: { labels: { color: 'white' } } }, scales: { x: { ticks: { color: 'white' } }, y: { ticks: { color: '#d4af37' } } } }
            });
        }
    })
    .catch(e => console.error("Error cargando stats profile:", e));
});
