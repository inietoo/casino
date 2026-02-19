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
    die("Error de conexión: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION);
}

function updateRankingScore($pdo, $user_id) {
    // Fórmula de Rating y actualización de snapshot
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute();
    $balance = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT * FROM blackjack_stats WHERE user_id = ?");
    $stmt->execute();
    $bj = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM poker_stats WHERE user_id = ?");
    $stmt->execute();
    $pk = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_hands = ($bj ?? 0) + ($pk ?? 0);
    $total_won_hands = ($bj ?? 0) + ($pk ?? 0);
    $winrate = $total_hands > 0 ? ($total_won_hands / $total_hands) : 0;
    
    $net_won = (($bj ?? 0) - ($bj ?? 0)) + (($pk ?? 0) - ($pk ?? 0));
    $best_streak = max($bj ?? 0, $pk ?? 0);

    // Formula Ponderada
    $score = ($balance * 0.40) + ($winrate * 1000 * 0.25) + ($net_won * 0.20) + ($total_hands * 0.10) + ($best_streak * 50 * 0.05);

    $stmt = $pdo->prepare("INSERT INTO ranking_snapshot (user_id, score) VALUES (?, ?)");
    $stmt->execute();
}
?>
