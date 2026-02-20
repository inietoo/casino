<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];

// Datos del usuario
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

// Stats Blackjack
$stmt = $pdo->prepare('SELECT * FROM blackjack_stats WHERE user_id = ?');
$stmt->execute([$user_id]);
$bj = $stmt->fetch(PDO::FETCH_ASSOC);

// Stats Poker
$stmt = $pdo->prepare('SELECT * FROM poker_stats WHERE user_id = ?');
$stmt->execute([$user_id]);
$pk = $stmt->fetch(PDO::FETCH_ASSOC);

// Últimas 20 manos BJ para gráfica
$stmt = $pdo->prepare('SELECT result FROM blackjack_hand_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 20');
$stmt->execute([$user_id]);
$bj_history = array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN));

// Últimas 10 manos BJ para historial
$stmt = $pdo->prepare('SELECT * FROM blackjack_hand_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 10');
$stmt->execute([$user_id]);
$bj_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimas 10 manos Poker para historial
$stmt = $pdo->prepare('SELECT * FROM poker_hand_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 10');
$stmt->execute([$user_id]);
$pk_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Evolución saldo (transacciones ASC)
$stmt = $pdo->prepare('SELECT type, amount, created_at FROM transactions WHERE user_id = ? ORDER BY created_at ASC LIMIT 30');
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── CALCULAR STATS BJ ────────────────────────────────────────────────────
$bj_hands   = (int)($bj['hands_played']   ?? 0);
$bj_won     = (int)($bj['hands_won']      ?? 0);
$bj_lost    = (int)($bj['hands_lost']     ?? 0);
$bj_push    = (int)($bj['hands_push']     ?? 0);
$bj_bj      = (int)($bj['blackjacks_hit'] ?? 0);
$bj_bust    = (int)($bj['times_busted']   ?? 0);
$bj_winrate = $bj_hands > 0 ? round(($bj_won / $bj_hands) * 100, 1) : 0;
$bj_net     = round(($bj['total_won'] ?? 0) - ($bj['total_wagered'] ?? 0), 2);
$bj_streak  = (int)($bj['best_win_streak'] ?? 0);
$bj_biggest = (float)($bj['biggest_win']   ?? 0);

// ─── CALCULAR STATS POKER ─────────────────────────────────────────────────
$pk_hands     = (int)($pk['hands_played'] ?? 0);
$pk_won_count = (int)($pk['hands_won']    ?? 0);
$pk_winrate   = $pk_hands > 0 ? round(($pk_won_count / $pk_hands) * 100, 1) : 0;
$pk_net       = round(($pk['total_won'] ?? 0) - ($pk['total_wagered'] ?? 0), 2);
$pk_vpip      = (float)($pk['vpip'] ?? 0);
$pk_streak    = (int)($pk['best_win_streak'] ?? 0);
$pk_dist      = [
    'Ganadas Showdown' => (int)($pk['hands_won_showdown'] ?? 0),
    'Ganadas por Fold' => (int)($pk['hands_won_fold']     ?? 0),
    'Perdidas'         => max(0, $pk_hands - $pk_won_count),
    'Folds'            => (int)($pk['times_folded']       ?? 0),
];

// ─── EVOLUCIÓN SALDO ─────────────────────────────────────────────────────
$balance_labels = [];
$balance_data   = [];
$running = 1000.00;
foreach ($transactions as $t) {
    $running += $t['type'] === 'win' ? (float)$t['amount'] : -(float)$t['amount'];
    $balance_labels[] = date('d/m H:i', strtotime($t['created_at']));
    $balance_data[]   = round($running, 2);
}
if (empty($balance_data)) {
    $balance_labels = ['Inicio'];
    $balance_data   = [(float)($u['balance'] ?? 1000)];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil - <?= htmlspecialchars($u['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #f0f0f0; font-family: 'Roboto', sans-serif; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center;
                       border-bottom: 2px solid #d4af37; padding-bottom: 15px; margin-bottom: 25px; }
        .page-header h1 { font-family: 'Cinzel', serif; color: #d4af37; font-size: 22px; }
        .back-btn { color: #d4af37; text-decoration: none; font-weight: bold;
                    border: 1px solid #d4af37; padding: 8px 15px; border-radius: 5px; transition: 0.3s; }
        .back-btn:hover { background: #d4af37; color: #000; }
        .user-info { display: flex; align-items: center; gap: 20px; background: #1a1a1a;
                     border: 1px solid #333; border-top: 4px solid #d4af37;
                     border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .avatar-big { font-size: 60px; }
        .balance-big { font-size: 26px; color: #28a745; font-weight: bold; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37;
                border-radius: 10px; padding: 20px; }
        .card h2 { font-family: 'Cinzel', serif; color: #d4af37; font-size: 15px;
                   margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .stat-row { display: flex; justify-content: space-between; padding: 7px 0;
                    border-bottom: 1px solid #1f1f1f; font-size: 14px; }
        .stat-val { color: #d4af37; font-weight: bold; }
        .stat-val.green { color: #28a745; }
        .stat-val.red   { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table th { background: #111; color: #d4af37; padding: 9px 8px; text-align: left; }
        table td { padding: 7px 8px; border-bottom: 1px solid #222; }
        .rw { color: #28a745; font-weight: bold; }
        .rl { color: #dc3545; font-weight: bold; }
        .rp { color: #ffc107; font-weight: bold; }
        .chart-wrap { position: relative; height: 180px; margin-top: 15px; }
        .full-card { background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37;
                     border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .full-card h2 { font-family: 'Cinzel', serif; color: #d4af37; font-size: 15px; margin-bottom: 15px; }
        .empty-msg { color: #666; text-align: center; margin-top: 20px; font-style: italic; }
        @media(max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="page-header">
    <h1>&#9824; Casino Royal — Perfil</h1>
    <a href="index.php" class="back-btn">← Volver al Lobby</a>
</div>

<!-- USUARIO -->
<div class="user-info">
    <div class="avatar-big"><?= htmlspecialchars($u['avatar'] ?? '🎲') ?></div>
    <div>
        <h2 style="font-family:'Cinzel',serif;color:#d4af37;font-size:22px;margin-bottom:6px;">
            <?= htmlspecialchars($u['username']) ?>
        </h2>
        <div class="balance-big">&euro; <?= number_format($u['balance'], 2, ',', '.') ?></div>
        <div style="color:#888;font-size:13px;margin-top:5px;">
            Miembro desde: <?= date('d/m/Y', strtotime($u['created_at'])) ?>
        </div>
    </div>
</div>

<!-- STATS BJ + HISTORIAL BJ -->
<div class="grid-2">
    <div class="card">
        <h2>&#9824; Estadísticas Blackjack</h2>
        <div class="stat-row"><span>Manos jugadas</span><span class="stat-val"><?= $bj_hands ?></span></div>
        <div class="stat-row"><span>Ganadas / Perdidas / Empate</span><span class="stat-val"><?= $bj_won ?> / <?= $bj_lost ?> / <?= $bj_push ?></span></div>
        <div class="stat-row"><span>Winrate</span><span class="stat-val"><?= $bj_winrate ?>%</span></div>
        <div class="stat-row"><span>Blackjacks naturales</span><span class="stat-val"><?= $bj_bj ?></span></div>
        <div class="stat-row"><span>Veces pasado de 21</span><span class="stat-val"><?= $bj_bust ?></span></div>
        <div class="stat-row"><span>Mayor ganancia (1 mano)</span><span class="stat-val green">&euro; <?= number_format($bj_biggest, 2, ',', '.') ?></span></div>
        <div class="stat-row">
            <span>Ganancia neta histórica</span>
            <span class="stat-val <?= $bj_net >= 0 ? 'green' : 'red' ?>">
                <?= $bj_net >= 0 ? '+' : '' ?>&euro; <?= number_format(abs($bj_net), 2, ',', '.') ?>
            </span>
        </div>
        <div class="stat-row"><span>Mejor racha victorias</span><span class="stat-val"><?= $bj_streak ?></span></div>
        <div class="chart-wrap"><canvas id="bjChart"></canvas></div>
    </div>

    <div class="card">
        <h2>&#9824; Últimas 10 Manos — Blackjack</h2>
        <?php if (empty($bj_log)): ?>
            <p class="empty-msg">Aún no has jugado ninguna mano.</p>
        <?php else: ?>
        <table>
            <tr><th>Tus cartas</th><th>Dealer</th><th>Acción</th><th>Resultado</th><th>&euro;</th></tr>
            <?php foreach ($bj_log as $log):
                $rc = in_array($log['result'], ['win','blackjack']) ? 'rw' : ($log['result'] === 'push' ? 'rp' : 'rl');
            ?><tr>
                <td><?= htmlspecialchars($log['player_cards'] ?? '-') ?></td>
                <td><?= htmlspecialchars($log['dealer_cards'] ?? '-') ?></td>
                <td><?= htmlspecialchars($log['actions_taken'] ?? '-') ?></td>
                <td class="<?= $rc ?>"><?= ucfirst($log['result'] ?? '-') ?></td>
                <td class="<?= (float)($log['amount_won'] ?? 0) >= 0 ? 'rw' : 'rl' ?>">
                    <?= (float)($log['amount_won'] ?? 0) >= 0 ? '+' : '' ?>&euro;<?= number_format($log['amount_won'] ?? 0, 2, ',', '.') ?>
                </td>
            </tr><?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- STATS POKER + HISTORIAL POKER -->
<div class="grid-2">
    <div class="card">
        <h2>🃏 Estadísticas Póker</h2>
        <div class="stat-row"><span>Manos jugadas</span><span class="stat-val"><?= $pk_hands ?></span></div>
        <div class="stat-row"><span>Winrate</span><span class="stat-val"><?= $pk_winrate ?>%</span></div>
        <div class="stat-row"><span>VPIP</span><span class="stat-val"><?= $pk_vpip ?>%</span></div>
        <div class="stat-row"><span>All-ins realizados</span><span class="stat-val"><?= (int)($pk['times_allin'] ?? 0) ?></span></div>
        <div class="stat-row"><span>Mejor racha victorias</span><span class="stat-val"><?= $pk_streak ?></span></div>
        <div class="stat-row"><span>Mayor pot ganado</span><span class="stat-val green">&euro; <?= number_format($pk['biggest_pot_won'] ?? 0, 2, ',', '.') ?></span></div>
        <div class="stat-row">
            <span>Ganancia neta histórica</span>
            <span class="stat-val <?= $pk_net >= 0 ? 'green' : 'red' ?>">
                <?= $pk_net >= 0 ? '+' : '' ?>&euro; <?= number_format(abs($pk_net), 2, ',', '.') ?>
            </span>
        </div>
        <div style="display:flex;justify-content:center;margin-top:15px;"><div style="height:170px;width:170px;"><canvas id="pkDonut"></canvas></div></div>
    </div>

    <div class="card">
        <h2>🃏 Últimas 10 Manos — Póker</h2>
        <?php if (empty($pk_log)): ?>
            <p class="empty-msg">Aún no has jugado ninguna mano.</p>
        <?php else: ?>
        <table>
            <tr><th>Hole cards</th><th>Mejor mano</th><th>Pot</th><th>Resultado</th><th>&euro;</th></tr>
            <?php foreach ($pk_log as $log):
                $rc = $log['result'] === 'win' ? 'rw' : ($log['result'] === 'fold' ? 'rp' : 'rl');
            ?><tr>
                <td><?= htmlspecialchars($log['hole_cards'] ?? '-') ?></td>
                <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($log['hand_rank'] ?? '-'))) ?></td>
                <td>&euro;<?= number_format($log['pot_size'] ?? 0, 2, ',', '.') ?></td>
                <td class="<?= $rc ?>"><?= ucfirst($log['result'] ?? '-') ?></td>
                <td class="<?= (float)($log['amount_won'] ?? 0) >= 0 ? 'rw' : 'rl' ?>">
                    <?= (float)($log['amount_won'] ?? 0) >= 0 ? '+' : '' ?>&euro;<?= number_format($log['amount_won'] ?? 0, 2, ',', '.') ?>
                </td>
            </tr><?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- EVOLUCIÓN SALDO -->
<div class="full-card">
    <h2>&#128200; Evolución del Saldo</h2>
    <div style="height:220px;"><canvas id="balanceChart"></canvas></div>
</div>

<script>
// Gráfico barras BJ
const bjR = <?= json_encode($bj_history) ?>;
new Chart(document.getElementById('bjChart'), {
    type: 'bar',
    data: {
        labels: bjR.map((_,i) => 'M'+(i+1)),
        datasets: [{
            data: bjR.map(r => (r==='win'||r==='blackjack') ? 1 : (r==='push' ? 0.3 : -1)),
            backgroundColor: bjR.map(r => (r==='win'||r==='blackjack') ? '#28a745' : (r==='push' ? '#ffc107' : '#dc3545')),
            borderRadius: 3
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#aaa' }, grid: { color: '#222' } },
            y: { display: false }
        }
    }
});

// Gráfico dona Poker
new Chart(document.getElementById('pkDonut'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($pk_dist)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($pk_dist)) ?>,
            backgroundColor: ['#28a745','#17a2b8','#dc3545','#ffc107'],
            borderColor: '#0a0a0a', borderWidth: 3
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#f0f0f0', font: { size: 11 } } } } }
});

// Gráfico línea saldo
new Chart(document.getElementById('balanceChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($balance_labels) ?>,
        datasets: [{
            label: 'Saldo (€)',
            data: <?= json_encode($balance_data) ?>,
            borderColor: '#d4af37',
            backgroundColor: 'rgba(212,175,55,0.1)',
            borderWidth: 2, pointRadius: 3, fill: true, tension: 0.3
        }]
    },
    options: {
        plugins: { legend: { labels: { color: '#f0f0f0' } } },
        scales: {
            x: { ticks: { color: '#aaa', maxRotation: 45 }, grid: { color: '#222' } },
            y: { ticks: { color: '#aaa', callback: v => '€' + v.toLocaleString('es-ES') }, grid: { color: '#222' } }
        }
    }
});
</script>
</body>
</html>
