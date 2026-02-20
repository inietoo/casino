<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$room_id = (int)($_POST['room_id'] ?? $_GET['room_id'] ?? 0);

if (!$room_id) { echo json_encode(['error' => 'room_id requerido']); exit; }

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function buildDeck(): array {
    $suits = ['S','H','C','D'];
    $ranks = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $deck  = [];
    foreach ($suits as $s) foreach ($ranks as $r) $deck[] = $r . '_' . $s;
    shuffle($deck);
    return $deck;
}

function cardValue(string $card): int {
    $rank = explode('_', $card)[0];
    if (in_array($rank, ['J','Q','K'])) return 10;
    if ($rank === 'A') return 11;
    return (int)$rank;
}

function handTotal(array $cards): int {
    $total = 0; $aces = 0;
    foreach ($cards as $c) {
        if ($c === 'hidden') continue;
        $v = cardValue($c);
        if (explode('_', $c)[0] === 'A') $aces++;
        $total += $v;
    }
    while ($total > 21 && $aces > 0) { $total -= 10; $aces--; }
    return $total;
}

function getState(PDO $pdo, int $room_id): array {
    $stmt = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (json_decode($row['state_json'], true) ?: []) : [];
}

function saveState(PDO $pdo, int $room_id, array $state): void {
    $json  = json_encode($state);
    $phase = $state['phase'] ?? 'waiting';
    $stmt  = $pdo->prepare("UPDATE game_state SET state_json = ?, phase = ?, updated_at = NOW() WHERE room_id = ?");
    $stmt->execute([$json, $phase, $room_id]);
}

function getUserInfo(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare("SELECT username, avatar, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function nextTurn(array &$state): void {
    $playerIds  = array_keys($state['players']);
    $activePids = array_filter($playerIds, fn($uid) => in_array($state['players'][$uid]['status'], ['playing','betting']));
    $activePids = array_values($activePids);

    if (empty($activePids)) {
        $state['current_turn'] = null;
        $state['phase'] = 'dealer_turn';
        return;
    }

    $current = $state['current_turn'];
    $idx = array_search($current, $activePids);
    if ($idx === false || $idx >= count($activePids) - 1) {
        $state['current_turn'] = null;
        $state['phase'] = 'dealer_turn';
    } else {
        $state['current_turn'] = $activePids[$idx + 1];
    }
}

function dealerPlay(array &$state): void {
    // Reveal dealer hidden card
    foreach ($state['dealer_cards'] as &$c) { if ($c === 'hidden') $c = array_shift($state['_hidden']); }
    unset($state['_hidden']);

    // Dealer draws to 17+
    while (handTotal($state['dealer_cards']) < 17) {
        $state['dealer_cards'][] = array_shift($state['deck']);
    }
}

function settleHands(PDO $pdo, int $room_id, array &$state): void {
    $dealerTotal = handTotal($state['dealer_cards']);
    $dealerBust  = $dealerTotal > 21;

    foreach ($state['players'] as $uid => &$player) {
        if (!isset($player['bet']) || $player['bet'] <= 0) continue;
        $bet   = (float)$player['bet'];
        $total = handTotal($player['cards']);

        if ($player['status'] === 'bust') {
            $result = 'loss'; $payout = 0;
        } elseif ($player['status'] === 'blackjack') {
            $result = 'blackjack'; $payout = $bet + $bet * 1.5;
        } elseif ($dealerBust || $total > $dealerTotal) {
            $result = 'win'; $payout = $bet * 2;
        } elseif ($total === $dealerTotal) {
            $result = 'push'; $payout = $bet;
        } else {
            $result = 'loss'; $payout = 0;
        }

        $player['result']  = $result;
        $player['payout']  = $payout;
        $player['status']  = $result;

        // Update balance
        if ($payout > 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$payout, $uid]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")->execute([$uid, $room_id, $payout]);
        }

        // Update stats
        updateBjStats($pdo, $uid, $player, $bet, $payout, $result, $state['dealer_cards']);
    }
    unset($player);

    $state['phase']        = 'finished';
    $state['current_turn'] = null;
    $state['dealer_total'] = $dealerTotal;
}

function updateBjStats(PDO $pdo, int $uid, array $player, float $bet, float $payout, string $result, array $dealerCards): void {
    try {
        $won    = in_array($result, ['win','blackjack']) ? 1 : 0;
        $lost   = $result === 'loss' ? 1 : 0;
        $push   = $result === 'push' ? 1 : 0;
        $bj     = $result === 'blackjack' ? 1 : 0;
        $bust   = $result === 'bust' ? 1 : 0;
        $net    = $payout - $bet;
        $biggest = max(0, $net);

        $pdo->prepare("
            UPDATE blackjack_stats SET
                hands_played = hands_played + 1,
                hands_won    = hands_won + ?,
                hands_lost   = hands_lost + ?,
                hands_push   = hands_push + ?,
                blackjacks_hit = blackjacks_hit + ?,
                times_busted = times_busted + ?,
                total_wagered = total_wagered + ?,
                total_won    = total_won + ?,
                biggest_win  = GREATEST(biggest_win, ?),
                current_win_streak = IF(? = 1, current_win_streak + 1, 0),
                best_win_streak    = GREATEST(best_win_streak, IF(? = 1, current_win_streak + 1, 0)),
                updated_at   = NOW()
            WHERE user_id = ?
        ")->execute([$won,$lost,$push,$bj,$bust,$bet,$payout,$biggest,$won,$won,$uid]);

        // Log hand
        $pdo->prepare("INSERT INTO blackjack_hand_log
            (user_id, room_id, player_cards, dealer_cards, player_final_value, dealer_final_value, result, amount_bet, amount_won)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $uid, 0,
            implode(',', $player['cards']),
            implode(',', $dealerCards),
            handTotal($player['cards']),
            handTotal($dealerCards),
            $result, $bet, $payout
        ]);
    } catch (PDOException $e) {
        error_log('BJ stats: ' . $e->getMessage());
    }
}

// ─── OBTENER ESTADO ──────────────────────────────────────────────────────────
if ($action === 'state') {
    $state = getState($pdo, $room_id);
    if (empty($state)) { echo json_encode(['error' => 'Sala no encontrada']); exit; }

    // Get connected players info from DB
    $stmt = $pdo->prepare("
        SELECT rp.user_id, rp.status, u.username, u.avatar, u.balance
        FROM room_players rp JOIN users u ON u.id = rp.user_id
        WHERE rp.room_id = ? AND rp.status = 'active'
        ORDER BY rp.seat
    ");
    $stmt->execute([$room_id]);
    $dbPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge DB players into state (so new joiners appear)
    foreach ($dbPlayers as $p) {
        $uid = (string)$p['user_id'];
        if (!isset($state['players'][$uid])) {
            $state['players'][$uid] = [
                'username' => $p['username'],
                'avatar'   => $p['avatar'],
                'cards'    => [],
                'bet'      => 0,
                'status'   => 'waiting',
                'balance'  => (float)$p['balance'],
            ];
        }
        // Always refresh username/avatar
        $state['players'][$uid]['username'] = $p['username'];
        $state['players'][$uid]['avatar']   = $p['avatar'];
    }

    // Add player count
    $state['player_count'] = count($dbPlayers);

    // Check if current user is room creator (seat 1)
    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND seat = 1 LIMIT 1");
    $stmt2->execute([$room_id]);
    $creator = $stmt2->fetchColumn();
    $state['is_creator'] = ($creator == $user_id);
    $state['my_user_id'] = $user_id;

    // Hide dealer's second card if playing
    if (in_array($state['phase'], ['playing', 'dealer_turn'])) {
        $displayDealer = $state['dealer_cards'] ?? [];
        if (count($displayDealer) >= 2 && $state['phase'] === 'playing') {
            $displayDealer[1] = 'hidden';
        }
        $state['dealer_cards_display'] = $displayDealer;
    } else {
        $state['dealer_cards_display'] = $state['dealer_cards'] ?? [];
    }

    unset($state['deck'], $state['_hidden']); // Don't send deck to client
    echo json_encode($state);
    exit;
}

// ─── UNIRSE A LA SALA ─────────────────────────────────────────────────────────
if ($action === 'join') {
    try {
        // Check if already in room
        $stmt = $pdo->prepare("SELECT id FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);
        if (!$stmt->fetch()) {
            // Get next available seat
            $stmt2 = $pdo->prepare("SELECT MAX(seat) FROM room_players WHERE room_id = ?");
            $stmt2->execute([$room_id]);
            $maxSeat = (int)$stmt2->fetchColumn();
            $newSeat = $maxSeat + 1;

            if ($newSeat > 6) { echo json_encode(['error' => 'Sala llena']); exit; }

            $pdo->prepare("INSERT INTO room_players (room_id, user_id, seat, status) VALUES (?, ?, ?, 'active')")
                ->execute([$room_id, $user_id, $newSeat]);

            // Add to state
            $state = getState($pdo, $room_id);
            $info  = getUserInfo($pdo, $user_id);
            if (!isset($state['players'][(string)$user_id])) {
                $state['players'][(string)$user_id] = [
                    'username' => $info['username'],
                    'avatar'   => $info['avatar'],
                    'cards'    => [],
                    'bet'      => 0,
                    'status'   => 'waiting',
                    'balance'  => (float)$info['balance'],
                ];
                saveState($pdo, $room_id, $state);
            }
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('BJ join: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al unirse']);
    }
    exit;
}

// ─── INICIAR PARTIDA (solo creador) ──────────────────────────────────────────
if ($action === 'start') {
    $state = getState($pdo, $room_id);

    // Verify creator
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND seat = 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el creador puede iniciar']); exit;
    }

    if (count($state['players'] ?? []) < 2) {
        echo json_encode(['error' => 'Se necesitan al menos 2 jugadores']); exit;
    }
    if ($state['phase'] !== 'waiting') {
        echo json_encode(['error' => 'La partida ya ha comenzado']); exit;
    }

    $state['phase'] = 'betting';
    $state['deck']  = buildDeck();
    foreach ($state['players'] as &$p) {
        $p['cards'] = []; $p['bet'] = 0; $p['status'] = 'betting';
    }
    unset($p);
    $state['dealer_cards'] = [];
    $state['current_turn'] = null;
    saveState($pdo, $room_id, $state);

    // Update room status
    $pdo->prepare("UPDATE rooms SET status = 'playing' WHERE id = ?")->execute([$room_id]);

    echo json_encode(['success' => true]);
    exit;
}

// ─── APOSTAR ─────────────────────────────────────────────────────────────────
if ($action === 'bet') {
    $amount = (float)($_POST['amount'] ?? 0);
    $state  = getState($pdo, $room_id);

    if ($state['phase'] !== 'betting') { echo json_encode(['error' => 'No es fase de apuestas']); exit; }

    $uid = (string)$user_id;
    if (!isset($state['players'][$uid])) { echo json_encode(['error' => 'No estás en esta sala']); exit; }

    // Check balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $balance = (float)$stmt->fetchColumn();

    if ($amount < 10 || $amount > 500 || $amount > $balance) {
        echo json_encode(['error' => 'Apuesta inválida (mín €10, máx €500)']); exit;
    }

    // Deduct bet
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user_id]);
    $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $amount]);

    $state['players'][$uid]['bet']     = $amount;
    $state['players'][$uid]['balance'] = $balance - $amount;
    $state['players'][$uid]['status']  = 'ready';

    // Check if all players have bet
    $allReady = true;
    foreach ($state['players'] as $p) {
        if ($p['status'] === 'betting') { $allReady = false; break; }
    }

    if ($allReady) {
        // Deal cards
        foreach ($state['players'] as &$p) {
            $p['cards']  = [array_shift($state['deck']), array_shift($state['deck'])];
            $total = handTotal($p['cards']);
            $p['status'] = ($total === 21) ? 'blackjack' : 'playing';
        }
        unset($p);

        $state['dealer_cards'] = [array_shift($state['deck']), array_shift($state['deck'])];
        $state['_hidden']      = [$state['dealer_cards'][1]]; // Save hidden card
        $state['phase']        = 'playing';

        // Set first player's turn
        foreach ($state['players'] as $uid2 => $p2) {
            if ($p2['status'] === 'playing') {
                $state['current_turn'] = $uid2;
                break;
            }
        }

        // If all blackjack, go straight to dealer
        if ($state['current_turn'] === null) {
            $state['phase'] = 'dealer_turn';
        }
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true, 'state' => 'updated']);
    exit;
}

// ─── HIT ─────────────────────────────────────────────────────────────────────
if ($action === 'hit') {
    $state = getState($pdo, $room_id);
    $uid   = (string)$user_id;

    if ($state['phase'] !== 'playing') { echo json_encode(['error' => 'No es tu turno']); exit; }
    if ($state['current_turn'] !== $uid) { echo json_encode(['error' => 'No es tu turno']); exit; }

    $state['players'][$uid]['cards'][] = array_shift($state['deck']);
    $total = handTotal($state['players'][$uid]['cards']);

    if ($total > 21) {
        $state['players'][$uid]['status'] = 'bust';
        nextTurn($state);
    } elseif ($total === 21) {
        $state['players'][$uid]['status'] = 'standing';
        nextTurn($state);
    }

    if ($state['phase'] === 'dealer_turn') {
        dealerPlay($state);
        settleHands($pdo, $room_id, $state);
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

// ─── STAND ────────────────────────────────────────────────────────────────────
if ($action === 'stand') {
    $state = getState($pdo, $room_id);
    $uid   = (string)$user_id;

    if ($state['current_turn'] !== $uid) { echo json_encode(['error' => 'No es tu turno']); exit; }

    $state['players'][$uid]['status'] = 'standing';
    nextTurn($state);

    if ($state['phase'] === 'dealer_turn') {
        dealerPlay($state);
        settleHands($pdo, $room_id, $state);
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

// ─── DOUBLE ───────────────────────────────────────────────────────────────────
if ($action === 'double') {
    $state = getState($pdo, $room_id);
    $uid   = (string)$user_id;

    if ($state['current_turn'] !== $uid) { echo json_encode(['error' => 'No es tu turno']); exit; }

    $bet = $state['players'][$uid]['bet'];
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $balance = (float)$stmt->fetchColumn();

    if ($balance < $bet) { echo json_encode(['error' => 'Saldo insuficiente para doblar']); exit; }

    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$bet, $user_id]);
    $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $bet]);

    $state['players'][$uid]['bet']     = $bet * 2;
    $state['players'][$uid]['balance'] = $balance - $bet;
    $state['players'][$uid]['cards'][] = array_shift($state['deck']);

    $total = handTotal($state['players'][$uid]['cards']);
    $state['players'][$uid]['status'] = ($total > 21) ? 'bust' : 'standing';
    nextTurn($state);

    if ($state['phase'] === 'dealer_turn') {
        dealerPlay($state);
        settleHands($pdo, $room_id, $state);
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

// ─── NUEVA RONDA ─────────────────────────────────────────────────────────────
if ($action === 'new_round') {
    $state = getState($pdo, $room_id);

    // Only creator can start new round
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND seat = 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el creador puede iniciar nueva ronda']); exit;
    }

    // Remove players who left the room
    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND status = 'active'");
    $stmt2->execute([$room_id]);
    $activeUids = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $newPlayers = [];
    foreach ($activeUids as $uid2) {
        $info = getUserInfo($pdo, (int)$uid2);
        $newPlayers[(string)$uid2] = [
            'username' => $info['username'],
            'avatar'   => $info['avatar'],
            'cards'    => [],
            'bet'      => 0,
            'status'   => 'betting',
            'balance'  => (float)$info['balance'],
        ];
    }

    $state['phase']        = 'betting';
    $state['deck']         = buildDeck();
    $state['players']      = $newPlayers;
    $state['dealer_cards'] = [];
    $state['current_turn'] = null;
    unset($state['_hidden']);

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
?>
