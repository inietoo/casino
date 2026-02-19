<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION]);
$u = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM blackjack_stats WHERE user_id = ?");
$stmt->execute([$_SESSION]);
$bj = $stmt->fetch();

// Obtener las últimas 20 manos para gráfica
$stmt = $pdo->prepare("SELECT result FROM blackjack_hand_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 20");
$stmt->execute(]);
$bj_history = array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Perfil - Estadísticas</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background: #0a0a0a; color: #fff; font-family: sans-serif; padding: 20px;}
        .card { background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #d4af37; margin-bottom: 20px;}
        h1 { color: #d4af37; font-family: 'Cinzel', serif; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <a href="index.php" style="color:#d4af37;">← Volver al Lobby</a>
    <h1>Perfil de <?= $u ?> <?= htmlspecialchars($u) ?></h1>
    
    <div class="grid">
        <div class="card">
            <h2>Estadísticas Generales de Blackjack</h2>
            <p><strong>Manos Jugadas:</strong> <?= $bj ?></p>
            <p><strong>Winrate:</strong> <?= $bj > 0 ? round(($bj/$bj)*100,2) : 0 ?>%</p>
            <p><strong>Ganancia Máxima (1 mano):</strong> €<?= $bj ?></p>
            <p><strong>Ganancia Neta Histórica:</strong> €<?= $bj - $bj ?></p>
            <!-- Canvas para Chart.js -->
            <canvas id="bjChart" width="400" height="200"></canvas>
        </div>
        
        <div class="card">
            <h2>Últimas 10 Manos (Historial)</h2>
            <table style="width:100%; border-collapse:collapse; text-align:left;">
                <tr style="border-bottom:1px solid #d4af37;"><th>Cartas</th><th>Resultado</th><th>Apuesta / Win</th></tr>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM blackjack_hand_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 10");
                $stmt->execute([$_SESSION]);
                while($log = $stmt->fetch()): ?>
                    <tr>
                        <td><?= htmlspecialchars($log) ?></td>
                        <td><?= $log ?></td>
                        <td style="<?= $log > 0 ? 'color:green' : 'color:red' ?>">€<?= $log ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <script>
        // Renderizar la gráfica con los datos tomados directo de PHP a JavaScript
        const results = <?= json_encode($bj_history) ?>;
        const colors = results.map(r => r === 'win' || r === 'blackjack' ? 'green' : (r === 'push' ? 'yellow' : 'red'));
        const dataVals = results.map(r => r === 'win' || r === 'blackjack' ? 1 : (r === 'push' ? 0 : -1));

        var ctx = document.getElementById('bjChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: results.map((_, i) => 'Mano ' + (i+1)),
                datasets:
            },
            options: { scales: { y: { display:false } } }
        });
    </script>
</body>
</html>
