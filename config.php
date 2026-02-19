<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'casino');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la Base de Datos: " . $e->getMessage());
}

// ✅ CORREGIDO: comprueba 'user_id' en sesión, no el array entero
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function updateRankingScore($pdo, $user_id) {
    try {
        // ✅ CORREGIDO: execute([$user_id]) en todas las queries
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $balance = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare("SELECT * FROM blackjack_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $bj = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM poker_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $pk = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ CORREGIDO: acceso a campos concretos del array
        $bj_hands = $bj ? (int)$bj['hands_played'] : 0;
        $pk_hands = $pk ? (int)$pk['hands_played'] : 0;
        $total_hands = $bj_hands + $pk_hands;

        $bj_won = $bj ? (int)$bj['hands_won'] : 0;
        $pk_won = $pk ? (int)$pk['hands_won'] : 0;
        $total_won_hands = $bj_won + $pk_won;

        $winrate = $total_hands > 0 ? ($total_won_hands / $total_hands) : 0;

        $bj_net = $bj ? ((float)$bj['total_won'] - (float)$bj['total_wagered']) : 0;
        $pk_net = $pk ? ((float)$pk['total_won'] - (float)$pk['total_wagered']) : 0;
        $net_won = $bj_net + $pk_net;

        $bj_streak = $bj ? (int)$bj['best_win_streak'] : 0;
        $pk_streak = $pk ? (int)$pk['best_win_streak'] : 0;
        $best_streak = max($bj_streak, $pk_streak);

        // Fórmula ponderada de ranking
        $score = ($balance * 0.40) + ($winrate * 1000 * 0.25) + ($net_won * 0.20) + ($total_hands * 0.10) + ($best_streak * 50 * 0.05);

        // ✅ CORREGIDO: execute con ambos parámetros
        $stmt = $pdo->prepare("INSERT INTO ranking_snapshot (user_id, score) VALUES (?, ?)");
        $stmt->execute([$user_id, $score]);

    } catch (PDOException $e) {
        error_log("Error actualizando ranking para user $user_id: " . $e->getMessage());
    }
}
?>
