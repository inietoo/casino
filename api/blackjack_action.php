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
    $state['current_turn'] = null;
    foreach ($state['players'] as $uid => $p) {
        if (in_array($p['status'], ['playing', 'betting'])) {
            $state['current_turn'] = (string)$uid;
            return;
        }
    }
    $state['phase'] = 'dealer_turn';
}

function dealerPlay(array &$state): void {
    foreach ($state['dealer_cards'] as &$c) { if ($c === 'hidden') $c = array_shift($state['_hidden']); }
    unset($state['_hidden']);
    while (handTotal($state['dealer_cards']) < 17) {
        $state['dealer_cards'][] = array_shift($state['deck']);
    }
}

function settleHands(PDO $pdo, int $room_id, array &$state): void {
    $dealerTotal = handTotal($state['dealer_cards']);
    $dealerBust  = $dealerTotal > 21;

    foreach ($state['players'] as $uid_key => &$player) {
        if (!isset($player['bet']) || $player['bet'] <= 0) continue;
        $real_uid = (int)explode('_', $uid_key)[0]; 
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

        if ($payout > 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$payout, $real_uid]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")->execute([$real_uid, $room_id, $payout]);
        }
        updateBjStats($pdo, $real_uid, $player, $bet, $payout, $result, $state['dealer_cards']);
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
                hands_played = hands_played + 1, hands_won = hands_won + ?, hands_lost = hands_lost + ?,
                hands_push = hands_push + ?, blackjacks_hit = blackjacks_hit + ?, times_busted = times_busted + ?,
                total_wagered = total_wagered + ?, total_won = total_won + ?, biggest_win = GREATEST(biggest_win, ?),
                current_win_streak = IF(? = 1, current_win_streak + 1, 0),
                best_win_streak = GREATEST(best_win_streak, IF(? = 1, current_win_streak + 1, 0)),
                updated_at = NOW()
            WHERE user_id = ?
        ")->execute([$won,$lost,$push,$bj,$bust,$bet,$payout,$biggest,$won,$won,$uid]);

        $pdo->prepare("INSERT INTO blackjack_hand_log
            (user_id, room_id, player_cards, dealer_cards, player_final_value, dealer_final_value, result, amount_bet, amount_won)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$uid, 0, implode(',', $player['cards']), implode(',', $dealerCards), handTotal($player['cards']), handTotal($dealerCards), $result, $bet, $payout]);
    } catch (PDOException $e) { }
}

// ─── OBTENER ESTADO ──────────────────────────────────────────────────────────
if ($action === 'state') {
    $state = getState($pdo, $room_id);
    if (empty($state)) { echo json_encode(['error' => 'Sala no encontrada']); exit; }

    $stmt = $pdo->prepare("SELECT rp.user_id, rp.status, u.username, u.avatar, u.balance FROM room_players rp JOIN users u ON u.id = rp.user_id WHERE rp.room_id = ? AND rp.status = 'active' ORDER BY rp.seat");
    $stmt->execute([$room_id]);
    $dbPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbPlayers as $p) {
        $uid = (string)$p['user_id'];
        if (!isset($state['players'][$uid])) {
            $state['players'][$uid] = [ 'username' => $p['username'], 'avatar' => $p['avatar'], 'cards' => [], 'bet' => 0, 'status' => 'waiting', 'balance' => (float)$p['balance'] ];
        }
        $state['players'][$uid]['username'] = $p['username'];
        $state['players'][$uid]['avatar']   = $p['avatar'];
        $state['players'][$uid]['balance']  = (float)$p['balance'];
    }

    $state['player_count'] = count($dbPlayers);

    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt2->execute([$room_id]);
    $creator = $stmt2->fetchColumn();
    $state['is_creator'] = ($creator == $user_id);
    $state['my_user_id'] = $user_id;

    if (in_array($state['phase'], ['playing', 'dealer_turn'])) {
        $displayDealer = $state['dealer_cards'] ?? [];
        if (count($displayDealer) >= 2 && $state['phase'] === 'playing') { $displayDealer[1] = 'hidden'; }
        $state['dealer_cards_display'] = $displayDealer;
    } else {
        $state['dealer_cards_display'] = $state['dealer_cards'] ?? [];
    }

    unset($state['deck'], $state['_hidden']);
    echo json_encode($state);
    exit;
}

// ─── UNIRSE ─────────────────────────────────────────────────────────
if ($action === 'join') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);
        if (!$stmt->fetch()) {
            $stmt2 = $pdo->prepare("SELECT MAX(seat) FROM room_players WHERE room_id = ?");
            $stmt2->execute([$room_id]);
            $maxSeat = (int)$stmt2->fetchColumn();
            if ($maxSeat + 1 > 6) { echo json_encode(['error' => 'Sala llena']); exit; }
            $pdo->prepare("INSERT INTO room_players (room_id, user_id, seat, status) VALUES (?, ?, ?, 'active')")->execute([$room_id, $user_id, $maxSeat + 1]);
        }
        $state = getState($pdo, $room_id);
        $info  = getUserInfo($pdo, $user_id);
        if (!isset($state['players'][(string)$user_id])) {
            $state['players'][(string)$user_id] = [ 'username' => $info['username'], 'avatar' => $info['avatar'], 'cards' => [], 'bet' => 0, 'status' => 'waiting', 'balance' => (float)$info['balance'] ];
            saveState($pdo, $room_id, $state);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { echo json_encode(['error' => 'Error al unirse']); }
    exit;
}

// ─── INICIAR PARTIDA ──────────────────────────────────────────
if ($action === 'start') {
    $state = getState($pdo, $room_id);
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) { echo json_encode(['error' => 'Solo el líder de la mesa puede iniciar']); exit; }
    if (count($state['players'] ?? []) < 1) { echo json_encode(['error' => 'Se necesita al menos 1 jugador']); exit; }
    if ($state['phase'] !== 'waiting') { echo json_encode(['error' => 'La partida ya ha comenzado']); exit; }

    $state['phase'] = 'betting';
    $state['deck']  = buildDeck();
    foreach ($state['players'] as &$p) { $p['cards'] = []; $p['bet'] = 0; $p['status'] = 'betting'; }
    $state['dealer_cards'] = []; $state['current_turn'] = null;
    saveState($pdo, $room_id, $state);
    $pdo->prepare("UPDATE rooms SET status = 'playing' WHERE id = ?")->execute([$room_id]);
    echo json_encode(['success' => true]); exit;
}

// ─── APOSTAR ─────────────────────────────────────────────────────────────────
if ($action === 'bet') {
    $amount = (float)($_POST['amount'] ?? 0);
    $state  = getState($pdo, $room_id);
    if ($state['phase'] !== 'betting') { echo json_encode(['error' => 'No es fase de apuestas']); exit; }
    $uid = (string)$user_id;
    if (!isset($state['players'][$uid])) { echo json_encode(['error' => 'No estás en esta sala']); exit; }

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE"); $stmt->execute([$user_id]);
    $balance = (float)$stmt->fetchColumn();

    if ($amount < 10 || $amount > 500 || $amount > $balance) { echo json_encode(['error' => 'Apuesta inválida o saldo insuficiente']); exit; }

    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user_id]);
    $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $amount]);

    $state['players'][$uid]['bet']     = $amount;
    $state['players'][$uid]['balance'] = $balance - $amount;
    $state['players'][$uid]['status']  = 'ready';

    $allReady = true;
    foreach ($state['players'] as $p) { if ($p['status'] === 'betting') { $allReady = false; break; } }

    if ($allReady) {
        foreach ($state['players'] as &$p) {
            $p['cards']  = [array_shift($state['deck']), array_shift($state['deck'])];
            $total = handTotal($p['cards']);
            $p['status'] = ($total === 21) ? 'blackjack' : 'playing';
        }
        $state['dealer_cards'] = [array_shift($state['deck']), array_shift($state['deck'])];
        $state['_hidden']      = [$state['dealer_cards'][1]];
        $state['phase']        = 'playing';

        foreach ($state['players'] as $uid2 => $p2) {
            if ($p2['status'] === 'playing') { $state['current_turn'] = (string)$uid2; break; }
        }
        
        if ($state['current_turn'] === null) { 
            $state['phase'] = 'dealer_turn'; 
            dealerPlay($state);
            settleHands($pdo, $room_id, $state);
        }
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── HIT ─────────────────────────────────────────────────────────────────────
if ($action === 'hit') {
    $state = getState($pdo, $room_id);
    $active_turn = (string)($state['current_turn'] ?? '');
    $base_turn_uid = explode('_', $active_turn)[0];

    if ($state['phase'] !== 'playing' || $base_turn_uid !== (string)$user_id) { echo json_encode(['error' => 'No es tu turno']); exit; }
    $uid = $active_turn;

    $state['players'][$uid]['cards'][] = array_shift($state['deck']);
    $total = handTotal($state['players'][$uid]['cards']);

    if ($total > 21) { $state['players'][$uid]['status'] = 'bust'; nextTurn($state); } 
    elseif ($total === 21) { $state['players'][$uid]['status'] = 'standing'; nextTurn($state); }

    if ($state['phase'] === 'dealer_turn') { dealerPlay($state); settleHands($pdo, $room_id, $state); }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── STAND ────────────────────────────────────────────────────────────────────
if ($action === 'stand') {
    $state = getState($pdo, $room_id);
    $active_turn = (string)($state['current_turn'] ?? '');
    $base_turn_uid = explode('_', $active_turn)[0];

    if ($state['phase'] !== 'playing' || $base_turn_uid !== (string)$user_id) { echo json_encode(['error' => 'No es tu turno']); exit; }
    $uid = $active_turn;

    $state['players'][$uid]['status'] = 'standing';
    nextTurn($state);

    if ($state['phase'] === 'dealer_turn') { dealerPlay($state); settleHands($pdo, $room_id, $state); }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── DOUBLE ───────────────────────────────────────────────────────────────────
if ($action === 'double') {
    $state = getState($pdo, $room_id);
    $active_turn = (string)($state['current_turn'] ?? '');
    $base_turn_uid = explode('_', $active_turn)[0];

    if ($state['phase'] !== 'playing' || $base_turn_uid !== (string)$user_id) { echo json_encode(['error' => 'No es tu turno']); exit; }
    $uid = $active_turn;

    $bet = $state['players'][$uid]['bet'];
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE"); $stmt->execute([$user_id]);
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

    if ($state['phase'] === 'dealer_turn') { dealerPlay($state); settleHands($pdo, $room_id, $state); }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── SPLIT (DIVIDIR MANO) ─────────────────────────────────────────────────────
if ($action === 'split') {
    $state = getState($pdo, $room_id);
    $active_turn = (string)($state['current_turn'] ?? '');
    $base_turn_uid = explode('_', $active_turn)[0];

    if ($state['phase'] !== 'playing' || $base_turn_uid !== (string)$user_id) { echo json_encode(['error' => 'No es tu turno']); exit; }
    $uid = $active_turn;

    // Solo dejar dividir 1 vez
    if (strpos($uid, '_split') !== false) { echo json_encode(['error' => 'No puedes dividir más de una vez']); exit; }

    if (count($state['players'][$uid]['cards']) !== 2) { echo json_encode(['error' => 'Solo puedes dividir con 2 cartas']); exit; }

    $c1 = $state['players'][$uid]['cards'][0];
    $c2 = $state['players'][$uid]['cards'][1];

    if (cardValue($c1) !== cardValue($c2)) { echo json_encode(['error' => 'Las cartas no tienen el mismo valor']); exit; }

    $bet = $state['players'][$uid]['bet'];
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE"); $stmt->execute([$user_id]);
    $balance = (float)$stmt->fetchColumn();

    if ($balance < $bet) { echo json_encode(['error' => 'Saldo insuficiente para dividir']); exit; }

    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$bet, $user_id]);
    $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $bet]);

    $splitUid = $uid . '_split';
    $newPlayers = [];

    // EL BUG ESTABA AQUÍ: Usar comparaciones de texto estrictas `(string)$k === (string)$uid` 
    // asegura que PHP no se confunda cuando los IDs son numéricos.
    foreach ($state['players'] as $k => $p) {
        if ((string)$k === (string)$uid) {
            $p['cards'] = [$c1, array_shift($state['deck'])];
            $p['balance'] = $balance - $bet;
            $total1 = handTotal($p['cards']);
            $p['status'] = ($total1 === 21) ? 'standing' : 'playing';
            $newPlayers[$k] = $p;

            $splitPlayer = $p;
            $splitPlayer['username'] = $p['username'] . ' (Mano 2)';
            $splitPlayer['cards'] = [$c2, array_shift($state['deck'])];
            $splitPlayer['bet'] = $bet;
            $total2 = handTotal($splitPlayer['cards']);
            $splitPlayer['status'] = ($total2 === 21) ? 'standing' : 'playing';
            
            $newPlayers[$splitUid] = $splitPlayer;
        } else {
            $newPlayers[$k] = $p;
        }
    }
    $state['players'] = $newPlayers;

    // Si al dividir saca 21 automático en la primera mano, pasa al siguiente turno
    if ($state['players'][$uid]['status'] !== 'playing') {
        nextTurn($state);
        if ($state['phase'] === 'dealer_turn') { dealerPlay($state); settleHands($pdo, $room_id, $state); }
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── NUEVA RONDA ─────────────────────────────────────────────────────────────
if ($action === 'new_round') {
    $state = getState($pdo, $room_id);

    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) { echo json_encode(['error' => 'Solo el líder de la mesa puede iniciar']); exit; }

    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND status = 'active'");
    $stmt2->execute([$room_id]);
    $activeUids = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $newPlayers = [];
    foreach ($activeUids as $uid2) {
        $info = getUserInfo($pdo, (int)$uid2);
        $newPlayers[(string)$uid2] = [
            'username' => $info['username'], 'avatar' => $info['avatar'], 'cards' => [],
            'bet' => 0, 'status' => 'betting', 'balance' => (float)$info['balance']
        ];
    }

    $state['phase'] = 'betting'; $state['deck'] = buildDeck(); $state['players'] = $newPlayers;
    $state['dealer_cards'] = []; $state['current_turn'] = null; unset($state['_hidden']);

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]); exit;
}

// ─── REINICIAR MESA (EMERGENCIA) ─────────────────────────────────────────────
if ($action === 'reset_table') {
    $state = getState($pdo, $room_id);
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) { echo json_encode(['error' => 'Solo el líder puede reiniciar la mesa']); exit; }

    foreach ($state['players'] as &$p) {
        $p['cards'] = []; $p['bet'] = 0; $p['status'] = 'waiting';
    }
    $state['phase'] = 'waiting';
    $state['dealer_cards'] = [];
    $state['current_turn'] = null;
    unset($state['_hidden']);
    
    saveState($pdo, $room_id, $state);
    $pdo->prepare("UPDATE rooms SET status = 'waiting' WHERE id = ?")->execute([$room_id]);
    echo json_encode(['success' => true]); exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
?>
