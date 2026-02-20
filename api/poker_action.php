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

function getState(PDO $pdo, int $room_id): array {
    $stmt = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (json_decode($row['state_json'], true) ?: []) : [];
}

function saveState(PDO $pdo, int $room_id, array $state): void {
    $json  = json_encode($state);
    $phase = $state['phase'] ?? 'waiting';
    $pdo->prepare("UPDATE game_state SET state_json = ?, phase = ?, updated_at = NOW() WHERE room_id = ?")
        ->execute([$json, $phase, $room_id]);
}

function getUserInfo(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare("SELECT username, avatar, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function nextActivePlayer(array $state): ?string {
    $playerIds = array_keys($state['players']);
    if (empty($playerIds)) return null;
    $current = $state['current_turn'];
    $idx = array_search($current, $playerIds);
    if ($idx === false) $idx = -1;
    $count = count($playerIds);
    for ($i = 1; $i <= $count; $i++) {
        $nextIdx = ($idx + $i) % $count;
        $uid = $playerIds[$nextIdx];
        if ($state['players'][$uid]['status'] === 'active') return (string)$uid;
    }
    return null;
}

function checkBettingComplete(array $state): bool {
    $active = array_filter($state['players'], fn($p) => $p['status'] === 'active');
    if (count($active) <= 1) return true;
    $maxBet = max(array_column(array_values($state['players']), 'current_bet'));
    foreach ($active as $p) {
        if ($p['current_bet'] < $maxBet && $p['status'] === 'active') return false;
        if (!($p['has_acted'] ?? false)) return false;
    }
    return true;
}

function advancePhase(PDO $pdo, int $room_id, array &$state): void {
    $phases = ['preflop' => 'flop', 'flop' => 'turn', 'turn' => 'river', 'river' => 'showdown'];

    foreach ($state['players'] as &$p) {
        if ($p['status'] === 'active') {
            $p['current_bet'] = 0;
            $p['has_acted']   = false;
        }
    }
    unset($p);
    $state['current_bet'] = 0;

    if (!isset($phases[$state['phase']])) return;
    $state['phase'] = $phases[$state['phase']];

    switch ($state['phase']) {
        case 'flop':
            array_shift($state['deck']); // burn
            $state['community'] = [
                array_shift($state['deck']),
                array_shift($state['deck']),
                array_shift($state['deck']),
            ];
            break;
        case 'turn':
        case 'river':
            array_shift($state['deck']); // burn
            $state['community'][] = array_shift($state['deck']);
            break;
        case 'showdown':
            settlePokerHands($pdo, $room_id, $state);
            return;
    }

    $active = array_keys(array_filter($state['players'], fn($p) => $p['status'] === 'active'));
    $state['current_turn'] = isset($active[0]) ? (string)$active[0] : null;
}

function settlePokerHands(PDO $pdo, int $room_id, array &$state): void {
    $active = array_filter($state['players'], fn($p) => in_array($p['status'], ['active', 'allin']));

    if (count($active) === 1) {
        // Solo un jugador activo — gana por fold de todos los demás
        $winnerId = array_key_first($active);
        $payout   = $state['pot'];
        $state['players'][$winnerId]['status'] = 'winner';
        $state['players'][$winnerId]['result'] = 'win';
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$payout, $winnerId]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")->execute([$winnerId, $room_id, $payout]);
        updatePokerStats($pdo, $room_id, $state, [$winnerId => $payout]);

    } else {
        // Showdown — evaluar todas las manos
        $scores    = [];
        $bestScore = -1;

        foreach ($active as $uid => $p) {
            $rank = evaluateHand(array_merge($p['hole_cards'], $state['community']));
            $state['players'][$uid]['hand_rank'] = $rank['name'];
            $scores[$uid] = $rank['score'];
            if ($rank['score'] > $bestScore) $bestScore = $rank['score'];
        }

        // CORRECCIÓN: soporte para empates (split pot)
        $winners = array_keys(array_filter($scores, fn($s) => $s === $bestScore));

        if (empty($winners)) {
            // Salvaguarda: no debería ocurrir
            $state['phase']        = 'showdown';
            $state['current_turn'] = null;
            return;
        }

        // Dividir el pot entre los ganadores
        $payoutEach = floor(($state['pot'] / count($winners)) * 100) / 100;
        $payouts    = [];

        foreach ($winners as $winnerId) {
            $state['players'][$winnerId]['status'] = 'winner';
            $state['players'][$winnerId]['result'] = 'win';
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$payoutEach, $winnerId]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")->execute([$winnerId, $room_id, $payoutEach]);
            $payouts[$winnerId] = $payoutEach;
        }

        updatePokerStats($pdo, $room_id, $state, $payouts);
        // En caso de múltiples ganadores, exponer todos
        $state['winner'] = count($winners) === 1 ? $winners[0] : $winners;
    }

    $state['phase']        = 'showdown';
    $state['current_turn'] = null;
}

/**
 * Evaluación de mano correcta para Texas Hold'em (7 cartas).
 *
 * CORRECCIÓN PRINCIPAL: Straight Flush ahora se verifica comprobando
 * si existe una escalera dentro del subconjunto de cartas del mismo palo,
 * en lugar de combinar isFlush e isStraight de forma independiente.
 */
function evaluateHand(array $cards): array {
    $rankMap = [
        '2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,
        '9'=>9,'10'=>10,'J'=>11,'Q'=>12,'K'=>13,'A'=>14,
    ];

    $ranks = []; $suits = [];
    foreach ($cards as $c) {
        [$r, $s] = explode('_', $c);
        $ranks[] = $rankMap[$r];
        $suits[] = $s;
    }

    // ── Detectar el palo con ≥5 cartas ──────────────────────────────────
    $suitCounts = array_count_values($suits);
    $flushSuit  = null;
    foreach ($suitCounts as $s => $count) {
        if ($count >= 5) { $flushSuit = $s; break; }
    }
    $isFlush = ($flushSuit !== null);

    // ── Detectar Straight Flush (y Royal Flush) ──────────────────────────
    // CORRECCIÓN: se evalúa SÓLO con las cartas del palo dominante
    $isStraightFlush = false;
    $sfHighCard      = 0;

    if ($isFlush) {
        $flushRanks = [];
        foreach ($cards as $c) {
            [$r, $s] = explode('_', $c);
            if ($s === $flushSuit) $flushRanks[] = $rankMap[$r];
        }
        sort($flushRanks);
        $uniqueFlushRanks = array_values(array_unique($flushRanks));

        // Caso As bajo (A-2-3-4-5) en el mismo palo
        if (
            in_array(14, $uniqueFlushRanks) && in_array(2, $uniqueFlushRanks) &&
            in_array(3, $uniqueFlushRanks)  && in_array(4, $uniqueFlushRanks) &&
            in_array(5, $uniqueFlushRanks)
        ) {
            $isStraightFlush = true;
            $sfHighCard      = 5;
        }

        for ($i = 0; $i <= count($uniqueFlushRanks) - 5; $i++) {
            if ($uniqueFlushRanks[$i + 4] - $uniqueFlushRanks[$i] === 4) {
                $isStraightFlush = true;
                $sfHighCard      = $uniqueFlushRanks[$i + 4];
            }
        }
    }

    // Royal / Straight Flush — retornar inmediatamente
    if ($isStraightFlush && $sfHighCard === 14) return ['name' => 'Royal Flush',    'score' => 9000];
    if ($isStraightFlush)                        return ['name' => 'Straight Flush', 'score' => 8000 + $sfHighCard];

    // ── Escalera normal (sobre todas las cartas) ─────────────────────────
    sort($ranks);
    $uniqueRanks = array_values(array_unique($ranks));
    $isStraight  = false;
    $highCard    = 0;

    if (
        in_array(14, $uniqueRanks) && in_array(2, $uniqueRanks) &&
        in_array(3, $uniqueRanks)  && in_array(4, $uniqueRanks) &&
        in_array(5, $uniqueRanks)
    ) {
        $isStraight = true;
        $highCard   = 5;
    }

    for ($i = 0; $i <= count($uniqueRanks) - 5; $i++) {
        if ($uniqueRanks[$i + 4] - $uniqueRanks[$i] === 4) {
            $isStraight = true;
            $highCard   = $uniqueRanks[$i + 4];
        }
    }

    // ── Conteo de grupos (pares, tríos, póker) ───────────────────────────
    $rankCounts = array_count_values($ranks);
    arsort($rankCounts);
    $counts = array_values($rankCounts);

    if ($counts[0] === 4)                         return ['name' => 'Four of a Kind',  'score' => 7000 + max($ranks)];
    if ($counts[0] === 3 && ($counts[1] ?? 0) >= 2) return ['name' => 'Full House',   'score' => 6000 + max($ranks)];
    if ($isFlush)                                 return ['name' => 'Flush',            'score' => 5000 + max($ranks)];
    if ($isStraight)                              return ['name' => 'Straight',         'score' => 4000 + $highCard];
    if ($counts[0] === 3)                         return ['name' => 'Three of a Kind',  'score' => 3000 + max($ranks)];
    if ($counts[0] === 2 && ($counts[1] ?? 0) === 2) return ['name' => 'Two Pair',     'score' => 2000 + max($ranks)];
    if ($counts[0] === 2)                         return ['name' => 'Pair',             'score' => 1000 + max($ranks)];

    return ['name' => 'High Card', 'score' => max($ranks)];
}

function updatePokerStats(PDO $pdo, int $room_id, array $state, array $payouts): void {
    foreach ($state['players'] as $uid => $p) {
        try {
            $isWinner   = isset($payouts[$uid]);
            $amountWon  = $payouts[$uid] ?? 0;
            $result     = $isWinner ? 'win' : ($p['status'] === 'folded' ? 'fold' : 'loss');
            $bet        = $p['total_bet'] ?? 0;
            $isFoldWin  = $isWinner && ($p['status'] !== 'winner'); // ganó por folds
            $isShowWin  = $isWinner && ($p['status'] === 'winner');

            $pdo->prepare("
                UPDATE poker_stats SET
                    hands_played        = hands_played + 1,
                    hands_won           = hands_won + ?,
                    hands_won_showdown  = hands_won_showdown + ?,
                    hands_won_fold      = hands_won_fold + ?,
                    times_folded        = times_folded + ?,
                    total_wagered       = total_wagered + ?,
                    total_won           = total_won + ?,
                    biggest_pot_won     = GREATEST(biggest_pot_won, ?),
                    current_win_streak  = IF(? = 1, current_win_streak + 1, 0),
                    best_win_streak     = GREATEST(best_win_streak, IF(? = 1, current_win_streak + 1, 0)),
                    updated_at = NOW()
                WHERE user_id = ?
            ")->execute([
                $isWinner  ? 1 : 0,
                $isShowWin ? 1 : 0,
                $isFoldWin ? 1 : 0,
                $result === 'fold' ? 1 : 0,
                $bet, $amountWon, $amountWon,
                $isWinner ? 1 : 0,
                $isWinner ? 1 : 0,
                $uid,
            ]);

            $holeCards = implode(',', $p['hole_cards'] ?? []);
            $community = implode(',', $state['community'] ?? []);
            $pdo->prepare("
                INSERT INTO poker_hand_log
                    (user_id, room_id, hole_cards, community_cards, hand_rank, result, pot_size, amount_won)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $uid, $room_id, $holeCards, $community,
                $p['hand_rank'] ?? '', $result, $state['pot'], $amountWon,
            ]);
        } catch (PDOException $e) {
            error_log('Poker stats error (uid=' . $uid . '): ' . $e->getMessage());
        }
    }
}

// ─── OBTENER ESTADO ──────────────────────────────────────────────────────────
if ($action === 'state') {
    $state = getState($pdo, $room_id);
    if (empty($state)) { echo json_encode(['error' => 'Sala no encontrada']); exit; }

    $stmt = $pdo->prepare("
        SELECT rp.user_id, u.username, u.avatar, u.balance
        FROM room_players rp
        JOIN users u ON u.id = rp.user_id
        WHERE rp.room_id = ? AND rp.status = 'active'
        ORDER BY rp.seat
    ");
    $stmt->execute([$room_id]);
    $dbPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbPlayers as $p) {
        $uid = (string)$p['user_id'];
        if (!isset($state['players'][$uid])) {
            $state['players'][$uid] = [
                'username'    => $p['username'],
                'avatar'      => $p['avatar'],
                'hole_cards'  => [],
                'current_bet' => 0,
                'total_bet'   => 0,
                'status'      => 'waiting',
                'balance'     => (float)$p['balance'],
                'has_acted'   => false,
            ];
        }
        $state['players'][$uid]['username'] = $p['username'];
        $state['players'][$uid]['avatar']   = $p['avatar'];
        $state['players'][$uid]['balance']  = (float)$p['balance'];
    }

    $state['player_count'] = count($dbPlayers);
    $state['my_user_id']   = $user_id;

    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt2->execute([$room_id]);
    $state['is_creator'] = ($stmt2->fetchColumn() == $user_id);

    $outputState = $state;
    if ($state['phase'] !== 'showdown') {
        foreach ($outputState['players'] as $uid => &$p) {
            if ((string)$uid !== (string)$user_id) {
                $p['hole_cards'] = array_fill(0, count($p['hole_cards'] ?? []), 'hidden');
            }
        }
        unset($p);
    }

    unset($outputState['deck']);
    echo json_encode($outputState);
    exit;
}

// ─── UNIRSE ──────────────────────────────────────────────────────────────────
if ($action === 'join') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);

        if (!$stmt->fetch()) {
            $stmt2 = $pdo->prepare("SELECT MAX(seat) FROM room_players WHERE room_id = ?");
            $stmt2->execute([$room_id]);
            $maxSeat = (int)$stmt2->fetchColumn();
            $newSeat = $maxSeat + 1;
            if ($newSeat > 6) { echo json_encode(['error' => 'Sala llena']); exit; }
            $pdo->prepare("INSERT INTO room_players (room_id, user_id, seat, status) VALUES (?, ?, ?, 'active')")->execute([$room_id, $user_id, $newSeat]);
        }

        $state = getState($pdo, $room_id);
        $info  = getUserInfo($pdo, $user_id);

        if (!isset($state['players'][(string)$user_id])) {
            $state['players'][(string)$user_id] = [
                'username'    => $info['username'],
                'avatar'      => $info['avatar'],
                'hole_cards'  => [],
                'current_bet' => 0,
                'total_bet'   => 0,
                'status'      => 'waiting',
                'balance'     => (float)$info['balance'],
                'has_acted'   => false,
            ];
            saveState($pdo, $room_id, $state);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Poker join: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al unirse']);
    }
    exit;
}

// ─── INICIAR PARTIDA ─────────────────────────────────────────────────────────
if ($action === 'start') {
    $state = getState($pdo, $room_id);

    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el líder de la mesa puede iniciar']); exit;
    }
    if (count($state['players'] ?? []) < 2) {
        echo json_encode(['error' => 'Se necesitan al menos 2 jugadores']); exit;
    }
    if ($state['phase'] !== 'waiting') {
        echo json_encode(['error' => 'La partida ya ha comenzado']); exit;
    }

    // CORRECCIÓN: ciegas dentro de una transacción
    try {
        $pdo->beginTransaction();

        $deck = buildDeck();
        $blindSmall = 10; $blindBig = 20;
        $playerIds  = array_keys($state['players']);

        foreach ($state['players'] as &$p) {
            $p['hole_cards']  = [array_shift($deck), array_shift($deck)];
            $p['status']      = 'active';
            $p['current_bet'] = 0;
            $p['total_bet']   = 0;
            $p['has_acted']   = false;
        }
        unset($p);

        $sb = $playerIds[0]; $bb = $playerIds[1];

        $stmtSB = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmtSB->execute([$sb]);
        $sbBalance = (float)$stmtSB->fetchColumn();

        $stmtBB = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmtBB->execute([$bb]);
        $bbBalance = (float)$stmtBB->fetchColumn();

        $sbAmount = min($blindSmall, $sbBalance);
        $bbAmount = min($blindBig, $bbBalance);

        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$sbAmount, $sb]);
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$bbAmount, $bb]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$sb, $room_id, $sbAmount]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$bb, $room_id, $bbAmount]);

        $state['players'][$sb]['current_bet'] = $sbAmount;
        $state['players'][$sb]['total_bet']   = $sbAmount;
        $state['players'][$bb]['current_bet'] = $bbAmount;
        $state['players'][$bb]['total_bet']   = $bbAmount;

        $state['deck']         = $deck;
        $state['community']    = [];
        $state['pot']          = $sbAmount + $bbAmount;
        $state['current_bet']  = $bbAmount;
        $state['phase']        = 'preflop';
        $state['current_turn'] = (string)$playerIds[count($playerIds) > 2 ? 2 : 0];
        $state['dealer_seat']  = 0;

        saveState($pdo, $room_id, $state);
        $pdo->prepare("UPDATE rooms SET status = 'playing' WHERE id = ?")->execute([$room_id]);
        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Poker start: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al iniciar la partida']);
    }
    exit;
}

// ─── FOLD ─────────────────────────────────────────────────────────────────────
if ($action === 'fold') {
    $state = getState($pdo, $room_id);
    $uid   = (string)$user_id;

    if ((string)$state['current_turn'] !== (string)$user_id) {
        echo json_encode(['error' => 'No es tu turno']); exit;
    }

    $state['players'][$uid]['status']    = 'folded';
    $state['players'][$uid]['has_acted'] = true;

    $active = array_filter($state['players'], fn($p) => $p['status'] === 'active');
    if (count($active) === 1) {
        settlePokerHands($pdo, $room_id, $state);
    } else {
        $state['current_turn'] = nextActivePlayer($state);
        if (checkBettingComplete($state)) advancePhase($pdo, $room_id, $state);
    }

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

// ─── CHECK / CALL ─────────────────────────────────────────────────────────────
if ($action === 'check_call') {
    try {
        $pdo->beginTransaction();
        $state = getState($pdo, $room_id);
        $uid   = (string)$user_id;

        if ((string)$state['current_turn'] !== (string)$user_id) {
            $pdo->rollBack();
            echo json_encode(['error' => 'No es tu turno']); exit;
        }

        $maxBet  = $state['current_bet'] ?? 0;
        $myBet   = $state['players'][$uid]['current_bet'] ?? 0;
        $callAmt = $maxBet - $myBet;

        if ($callAmt > 0) {
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $balance = (float)$stmt->fetchColumn();

            $actualCall = min($callAmt, $balance);
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$actualCall, $user_id]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $actualCall]);

            $state['players'][$uid]['current_bet'] += $actualCall;
            $state['players'][$uid]['total_bet']   += $actualCall;
            $state['pot'] = ($state['pot'] ?? 0) + $actualCall;

            // CORRECCIÓN: all-in solo si el saldo era exactamente lo que se necesitaba
            // (no si el balance coincide con el callAmt por otros motivos)
            if ($balance <= $callAmt) {
                $state['players'][$uid]['status'] = 'allin';
            }
        }
        // else: Check (sin coste)

        $state['players'][$uid]['has_acted'] = true;
        $state['current_turn'] = nextActivePlayer($state);

        if (checkBettingComplete($state)) advancePhase($pdo, $room_id, $state);

        saveState($pdo, $room_id, $state);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Poker check_call: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al igualar']);
    }
    exit;
}

// ─── RAISE ───────────────────────────────────────────────────────────────────
if ($action === 'raise') {
    try {
        $pdo->beginTransaction();
        $state  = getState($pdo, $room_id);
        $uid    = (string)$user_id;
        $amount = (float)($_POST['amount'] ?? 0);

        if ((string)$state['current_turn'] !== (string)$user_id) {
            $pdo->rollBack();
            echo json_encode(['error' => 'No es tu turno']); exit;
        }
        if ($amount <= ($state['current_bet'] ?? 0)) {
            $pdo->rollBack();
            echo json_encode(['error' => 'La subida debe ser mayor que la apuesta actual']); exit;
        }

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $balance = (float)$stmt->fetchColumn();

        $myBet = $state['players'][$uid]['current_bet'] ?? 0;
        $toPay = $amount - $myBet;

        if ($toPay > $balance) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Saldo insuficiente']); exit;
        }

        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$toPay, $user_id]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $toPay]);

        $state['players'][$uid]['current_bet'] = $amount;
        $state['players'][$uid]['total_bet']  += $toPay;
        $state['current_bet']  = $amount;
        $state['pot']          = ($state['pot'] ?? 0) + $toPay;

        foreach ($state['players'] as $pid => &$p) {
            if ($pid !== $uid && $p['status'] === 'active') $p['has_acted'] = false;
        }
        unset($p);
        $state['players'][$uid]['has_acted'] = true;
        $state['current_turn'] = nextActivePlayer($state);

        if (checkBettingComplete($state)) advancePhase($pdo, $room_id, $state);

        saveState($pdo, $room_id, $state);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Poker raise: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al subir la apuesta']);
    }
    exit;
}

// ─── NUEVA MANO ──────────────────────────────────────────────────────────────
if ($action === 'new_hand') {
    $state = getState($pdo, $room_id);

    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el líder de la mesa puede iniciar nueva mano']); exit;
    }

    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND status = 'active'");
    $stmt2->execute([$room_id]);
    $activeUids = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $newPlayers = [];
    foreach ($activeUids as $uid2) {
        $info = getUserInfo($pdo, (int)$uid2);
        $newPlayers[(string)$uid2] = [
            'username'    => $info['username'],
            'avatar'      => $info['avatar'],
            'hole_cards'  => [],
            'current_bet' => 0,
            'total_bet'   => 0,
            'status'      => 'waiting',
            'balance'     => (float)$info['balance'],
            'has_acted'   => false,
        ];
    }

    $state['phase']        = 'waiting';
    $state['players']      = $newPlayers;
    $state['community']    = [];
    $state['pot']          = 0;
    $state['current_bet']  = 0;
    $state['current_turn'] = null;
    unset($state['deck'], $state['winner']);

    saveState($pdo, $room_id, $state);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida: ' . htmlspecialchars($action)]);
?>
