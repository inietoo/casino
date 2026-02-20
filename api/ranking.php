<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error' => 'No autorizado']); exit; }

try {
    $stmt = $pdo->query("
        SELECT
            u.id, u.username, u.avatar, u.balance,
            COALESCE(bj.hands_played, 0) + COALESCE(pk.hands_played, 0) + COALESCE(bg.games_played, 0) AS total_hands,
            COALESCE(bj.hands_won, 0)    + COALESCE(pk.hands_won, 0)    + COALESCE(bg.games_won, 0)    AS total_won_hands,
            COALESCE(bj.total_won, 0)    + COALESCE(pk.total_won, 0)    + COALESCE(bg.total_won, 0)    AS total_earned,
            COALESCE(bj.total_wagered,0) + COALESCE(pk.total_wagered,0) + COALESCE(bg.total_wagered,0) AS total_wagered
        FROM users u
        LEFT JOIN blackjack_stats bj ON bj.user_id = u.id
        LEFT JOIN poker_stats     pk ON pk.user_id  = u.id
        LEFT JOIN bingo_stats     bg ON bg.user_id  = u.id
        ORDER BY total_won_hands DESC, u.balance DESC
        LIMIT 20
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $totalHands = (int)$row['total_hands'];
        $totalWon   = (int)$row['total_won_hands'];
        $balance    = (float)$row['balance'];
        $winrate    = $totalHands > 0 ? ($totalWon / $totalHands) * 100 : 0;
        $score      = ($totalWon * 100) + ($winrate * 10) + ($balance * 0.01);

        $result[] = [
            'id'          => $row['id'],
            'username'    => $row['username'],
            'avatar'      => $row['avatar'],
            'balance'     => number_format($balance, 2, '.', ''),
            'total_hands' => $totalHands,
            'total_won'   => $totalWon,
            'winrate'     => number_format($winrate, 1, '.', ''),
            'score'       => number_format(max(0, $score), 0, '.', ''),
        ];
    }

    usort($result, fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);
    echo json_encode($result);

} catch (PDOException $e) { echo json_encode([]); }
?>
