function updateRanking() {
    fetch('/casino/api/ranking.php')
    .then(res => res.json())
    .then(data => {
        let html = '';
        data.forEach((row, index) => {
            let posHtml = index === 0 ? '<span class="medal gold">🥇</span>' : 
                          index === 1 ? '<span class="medal silver">🥈</span>' : 
                          index === 2 ? '<span class="medal bronze">🥉</span>' : 
                          `#${index+1}`;
            
            html += `<tr>
                <td class="pos-cell">${posHtml}</td>
                <td><strong>${row.avatar} ${row.username}</strong></td>
                <td class="balance-cell">€${parseFloat(row.balance).toFixed(2)}</td>
                <td>${row.total_hands}</td>
                <td>${parseFloat(row.winrate).toFixed(1)}%</td>
                <td class="score-cell">${parseFloat(row.score).toFixed(0)} pts</td>
            </tr>`;
        });
        document.getElementById('rankingBody').innerHTML = html;
    });
}
setInterval(updateRanking, 10000); // Polling relajado cada 10s
updateRanking();
