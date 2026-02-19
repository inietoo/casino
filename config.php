<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'casino');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    // Modo de errores estricto para detectar fallos en SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la Base de Datos: " . $e->getMessage());
}

// Validación de sesión corregida
function isLoggedIn() {
    return isset($_SESSION);
}

// Función de actualización de Ranking corregida (parámetros en execute y acceso seguro a arrays)
function updateRankingScore($pdo, $user_id) {
    try {
        // 1. Obtenemos el balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute();
        $balance = (float)($stmt->fetchColumn() ?: 0);

        // 2. Obtenemos stats de blackjack
        $stmt = $pdo->prepare("SELECT * FROM blackjack_stats WHERE user_id = ?");
        $stmt->execute();
        $bj = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Obtenemos stats de poker
        $stmt = $pdo->prepare("SELECT * FROM poker_stats WHERE user_id = ?");
        $stmt->execute();
        $pk = $stmt->fetch(PDO::FETCH_ASSOC);

        // Extracción segura (evita errores si el jugador no ha jugado a algo aún)
        $bj_hands = $bj ? (int)$bj : 0;
        $pk_hands = $pk ? (int)$pk : 0;
        $total_hands = $bj_hands + $pk_hands;

        $bj_won = $bj ? (int)$bj : 0;
        $pk_won = $pk ? (int)$pk : 0;
        $total_won_hands = $bj_won + $pk_won;

        $winrate = $total_hands > 0 ? ($total_won_hands / $total_hands) : 0;
        
        $bj_net = $bj ? ((float)$bj - (float)$bj) : 0;
        $pk_net = $pk ? ((float)$pk - (float)$pk) : 0;
        $net_won = $bj_net + $pk_net;

        $bj_streak = $bj ? (int)$bj : 0;
        $pk_streak = $pk ? (int)$pk : 0;
        $best_streak = max($bj_streak, $pk_streak);

        // Fórmula Ponderada de Ranking
        $score = ($balance * 0.40) + ($winrate * 1000 * 0.25) + ($net_won * 0.20) + ($total_hands * 0.10) + ($best_streak * 50 * 0.05);

        // Insertar el Snapshot (Cálculo histórico)
        $stmt = $pdo->prepare("INSERT INTO ranking_snapshot (user_id, score) VALUES (?, ?)");
        $stmt->execute();

    } catch (PDOException $e) {
        // Log error silencioso para no interrumpir la partida en curso
        error_log("Error actualizando ranking para user $user_id: " . $e->getMessage());
    }
}
?>
