<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    // Compute live ranking from actual stats tables
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username,
            u.avatar,
            u.balance,
            COALESCE(bj.hands_played, 0) + COALESCE(pk.hands_played, 0) AS total_hands,
            COALESCE(bj.hands_won, 0)    + COALESCE(pk.hands_won, 0)    AS total_won_hands,
            COALESCE(bj.total_won, 0)    + COALESCE(pk.total_won, 0)    AS total_earned,
            COALESCE(bj.total_wagered,0) + COALESCE(pk.total_wagered,0) AS total_wagered,
            GREATEST(COALESCE(bj.best_win_streak,0), COALESCE(pk.best_win_streak,0)) AS best_streak
        FROM users u
        LEFT JOIN blackjack_stats bj ON bj.user_id = u.id
        LEFT JOIN poker_stats     pk ON pk.user_id  = u.id
        ORDER BY u.balance DESC
        LIMIT 20
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $totalHands = (int)$row['total_hands'];
        $totalWon   = (int)$row['total_won_hands'];
        $balance    = (float)$row['balance'];
        $netWon     = (float)$row['total_earned'] - (float)$row['total_wagered'];
        $winrate    = $totalHands > 0 ? ($totalWon / $totalHands) * 100 : 0;
        $streak     = (int)$row['best_streak'];

        // Weighted score formula (same as config.php)
        $score = ($balance * 0.40) + ($winrate * 10 * 0.25) + ($netWon * 0.20) + ($totalHands * 0.10) + ($streak * 50 * 0.05);

        $result[] = [
            'id'          => $row['id'],
            'username'    => $row['username'],
            'avatar'      => $row['avatar'],
            'balance'     => number_format($balance, 2, '.', ''),
            'total_hands' => $totalHands,
            'winrate'     => number_format($winrate, 1, '.', ''),
            'score'       => number_format(max(0, $score), 0, '.', ''),
        ];
    }

    // Sort by score descending
    usort($result, fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);

    echo json_encode($result);

} catch (PDOException $e) {
    error_log('Ranking: ' . $e->getMessage());
    echo json_encode([]);
}
?>
