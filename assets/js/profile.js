document.addEventListener("DOMContentLoaded", function() {
    fetch('api/stats.php')
    .then(r => r.json())
    .then(data => {
        if(data.error) return;

        // Graficar historial evolutivo de balance general
        const canvas = document.getElementById('balanceChart');
        if(canvas) {
            const ctxLine = canvas.getContext('2d');
            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: data.balance_evolution.map((_, i) => `Mov ${i+1}`),
                    datasets: [{
                        label: 'Evolución Saldo (€)',
                        data: data.balance_evolution,
                        borderColor: '#d4af37',
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: 'white' } } }, 
                    scales: { 
                        x: { ticks: { color: '#888' }, grid: { color: '#222' } }, 
                        y: { ticks: { color: '#d4af37' }, grid: { color: '#222' } } 
                    } 
                }
            });
        }
    })
    .catch(e => console.error("Error cargando stats profile:", e));
});
