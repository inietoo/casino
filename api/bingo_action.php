<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error' => 'No autorizado']); exit; }

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$room_id = (int)($_POST['room_id'] ?? $_GET['room_id'] ?? 0);

if (!$room_id) { echo json_encode(['error' => 'room_id requerido']); exit; }

// ─── HELPERS ─────────────────────────────────────────────────────────────────

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

function generateBingoCard(): array {
    $card = [];
    $b = range(1, 15);  shuffle($b); $card = array_merge($card, array_slice($b, 0, 5));
    $i = range(16, 30); shuffle($i); $card = array_merge($card, array_slice($i, 0, 5));
    $n = range(31, 45); shuffle($n);
    $n_slice = array_slice($n, 0, 5);
    $n_slice[2] = 'FREE';
    $card = array_merge($card, $n_slice);
    $g = range(46, 60); shuffle($g); $card = array_merge($card, array_slice($g, 0, 5));
    $o = range(61, 75); shuffle($o); $card = array_merge($card, array_slice($o, 0, 5));
    return $card;
}

/**
 * Comprueba si un cartón tiene al menos UNA LÍNEA completa (horizontal o vertical).
 * FREE del centro siempre cuenta como marcada.
 */
function checkLineWinner(array $drawn, array $card): bool {
    // Filas horizontales
    for ($row = 0; $row < 5; $row++) {
        $ok = true;
        for ($col = 0; $col < 5; $col++) {
            $num = $card[$row * 5 + $col];
            if ($num !== 'FREE' && !in_array($num, $drawn)) { $ok = false; break; }
        }
        if ($ok) return true;
    }
    // Columnas verticales
    for ($col = 0; $col < 5; $col++) {
        $ok = true;
        for ($row = 0; $row < 5; $row++) {
            $num = $card[$row * 5 + $col];
            if ($num !== 'FREE' && !in_array($num, $drawn)) { $ok = false; break; }
        }
        if ($ok) return true;
    }
    return false;
}

/**
 * Comprueba si un cartón tiene BINGO COMPLETO (todos los 25 números marcados).
 */
function checkBingoWinner(array $drawn, array $card): bool {
    foreach ($card as $num) {
        if ($num !== 'FREE' && !in_array($num, $drawn)) return false;
    }
    return true;
}

/**
 * Calcula los dos botes al inicio del sorteo:
 *   - Bote LÍNEA:  entre el 25% y el 40% del pot total (aleatorio)
 *   - Bote BINGO:  entre el 50% y el 70% del pot total (aleatorio)
 *
 * Los porcentajes son independientes, así que hay un margen de "casa" variable.
 */
function calculatePrizes(float $pot): array {
    $linePct  = mt_rand(25, 40) / 100;
    $bingoPct = mt_rand(50, 70) / 100;
    return [
        'line_prize'  => round($pot * $linePct,  2),
        'bingo_prize' => round($pot * $bingoPct, 2),
    ];
}

/**
 * Paga el bote de LÍNEA y lo registra. El sorteo CONTINÚA después.
 */
function payLinePrize(PDO $pdo, int $room_id, array &$state, array $winners): void {
    $payout = count($winners) > 0 ? round($state['line_prize'] / count($winners), 2) : 0;
    foreach ($winners as $uid) {
        if ($payout > 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                ->execute([$payout, $uid]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")
                ->execute([$uid, $room_id, $payout]);
        }
    }
    $state['line_winners']     = $winners;
    $state['line_payout_each'] = $payout;
    $state['line_paid']        = true;
    // ¡NO cambiamos phase! El sorteo sigue hasta el bingo completo.
}

/**
 * Paga el bote de BINGO COMPLETO, registra estadísticas y cierra la ronda.
 */
function payBingoPrize(PDO $pdo, int $room_id, array &$state, array $winners): void {
    $payout = count($winners) > 0 ? round($state['bingo_prize'] / count($winners), 2) : 0;
    foreach ($winners as $uid) {
        if ($payout > 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                ->execute([$payout, $uid]);
            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")
                ->execute([$uid, $room_id, $payout]);
        }
    }

    // Estadísticas de todos los jugadores de la ronda
    $bingoSet = array_flip($winners);
    foreach ($state['players'] as $uid => $player) {
        $isLineWinner  = in_array((string)$uid, array_map('strval', $state['line_winners'] ?? []));
        $isBingoWinner = isset($bingoSet[(string)$uid]);
        $cardsBought   = count($player['cards'] ?? []);
        $bet           = $player['total_bet'] ?? 0;
        $totalWon      = ($isLineWinner  ? ($state['line_payout_each']  ?? 0) : 0)
                       + ($isBingoWinner ? $payout : 0);
        try {
            $pdo->prepare("
                INSERT INTO bingo_stats (user_id, games_played, games_won, cards_bought, total_wagered, total_won)
                VALUES (?, 1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    games_played  = games_played  + 1,
                    games_won     = games_won     + VALUES(games_won),
                    cards_bought  = cards_bought  + VALUES(cards_bought),
                    total_wagered = total_wagered + VALUES(total_wagered),
                    total_won     = total_won     + VALUES(total_won)
            ")->execute([$uid, $isBingoWinner ? 1 : 0, $cardsBought, $bet, $totalWon]);

            $pdo->prepare("
                INSERT INTO bingo_log (user_id, room_id, cards_bought, result, amount_bet, amount_won)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$uid, $room_id, $cardsBought, $isBingoWinner ? 'win' : 'loss', $bet, $totalWon]);
        } catch (PDOException $e) {
            error_log('Bingo stats: ' . $e->getMessage());
        }
    }

    $state['bingo_winners']     = $winners;
    $state['bingo_payout_each'] = $payout;
    $state['phase']             = 'finished';
}

// ─── OBTENER ESTADO ──────────────────────────────────────────────────────────
if ($action === 'state') {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ? FOR UPDATE");
    $stmt->execute([$room_id]);
    $row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $state = $row ? (json_decode($row['state_json'], true) ?: []) : [];

    // Sincronizar jugadores desde la DB
    $stmtP = $pdo->prepare("
        SELECT rp.user_id, u.username, u.avatar, u.balance
        FROM room_players rp
        JOIN users u ON u.id = rp.user_id
        WHERE rp.room_id = ? AND rp.status = 'active'
    ");
    $stmtP->execute([$room_id]);
    $dbPlayers = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    $needsSave = false;

    foreach ($dbPlayers as $p) {
        $uid = (string)$p['user_id'];
        if (!isset($state['players'][$uid])) {
            $state['players'][$uid] = [
                'username'  => $p['username'],
                'avatar'    => $p['avatar'],
                'cards'     => [],
                'total_bet' => 0,
                'balance'   => (float)$p['balance'],
            ];
            $needsSave = true;
        } else {
            $state['players'][$uid]['username'] = $p['username'];
            $state['players'][$uid]['avatar']   = $p['avatar'];
            $state['players'][$uid]['balance']  = (float)$p['balance'];
        }
    }

    // ── Motor de sorteo: saca una bola cada 3 segundos ─────────────────────
    if (($state['phase'] ?? '') === 'playing') {
        $last_draw = $state['last_draw'] ?? time();

        if (time() - $last_draw >= 3 && !empty($state['deck'])) {
            $drawn_num        = array_shift($state['deck']);
            $state['drawn'][] = $drawn_num;
            $state['last_draw'] = time();
            $needsSave = true;

            // ── PASO 1: comprobar LÍNEA (solo si aún no se ha pagado) ────────
            if (empty($state['line_paid'])) {
                $lineWinners = [];
                foreach ($state['players'] as $uid => $player) {
                    foreach ($player['cards'] as $card) {
                        if (checkLineWinner($state['drawn'], $card)) {
                            $lineWinners[] = (string)$uid;
                            break; // un jugador solo gana línea una vez
                        }
                    }
                }
                if (!empty($lineWinners)) {
                    payLinePrize($pdo, $room_id, $state, $lineWinners);
                    // El sorteo CONTINÚA — no cambiamos phase
                }
            }

            // ── PASO 2: comprobar BINGO COMPLETO ─────────────────────────────
            $bingoWinners = [];
            foreach ($state['players'] as $uid => $player) {
                foreach ($player['cards'] as $card) {
                    if (checkBingoWinner($state['drawn'], $card)) {
                        $bingoWinners[] = (string)$uid;
                        break;
                    }
                }
            }
            if (!empty($bingoWinners)) {
                payBingoPrize($pdo, $room_id, $state, $bingoWinners);
                // phase pasa a 'finished' dentro de payBingoPrize
            }

            // ── PASO 3: mazo agotado sin bingo completo ───────────────────────
            if (empty($state['deck']) && ($state['phase'] ?? '') === 'playing') {
                // Si nadie hizo ni línea, devolver apuestas
                if (empty($state['line_paid'])) {
                    foreach ($state['players'] as $uid => $player) {
                        $refund = $player['total_bet'] ?? 0;
                        if ($refund > 0) {
                            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                                ->execute([$refund, $uid]);
                            $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'win', ?)")
                                ->execute([$uid, $room_id, $refund]);
                        }
                    }
                }
                $state['phase']         = 'finished';
                $state['bingo_winners'] = [];
                $state['no_bingo_winner'] = true;
            }
        }
    }

    if ($needsSave) {
        saveState($pdo, $room_id, $state);
    }

    $pdo->commit();

    // Meta para el cliente
    $stmt2 = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt2->execute([$room_id]);
    $state['is_creator']   = ($stmt2->fetchColumn() == $user_id);
    $state['my_user_id']   = $user_id;
    $state['player_count'] = count($dbPlayers);

    // Ocultar cartones ajenos (solo mostrar conteo)
    foreach ($state['players'] as $uid => &$p) {
        if ((string)$uid !== (string)$user_id) {
            $p['cards_count'] = count($p['cards'] ?? []);
            unset($p['cards']);
        }
    }
    unset($p);
    unset($state['deck']); // Nunca exponer el mazo

    echo json_encode($state);
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
            $pdo->prepare("INSERT INTO room_players (room_id, user_id, seat, status) VALUES (?, ?, ?, 'active')")
                ->execute([$room_id, $user_id, $maxSeat + 1]);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ? FOR UPDATE");
        $stmt->execute([$room_id]);
        $state = json_decode($stmt->fetchColumn(), true) ?: [];

        $stmtU = $pdo->prepare("SELECT username, avatar, balance FROM users WHERE id = ?");
        $stmtU->execute([$user_id]);
        $info = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!isset($state['players'][(string)$user_id])) {
            $state['players'][(string)$user_id] = [
                'username'  => $info['username'],
                'avatar'    => $info['avatar'],
                'cards'     => [],
                'total_bet' => 0,
                'balance'   => (float)$info['balance'],
            ];
            saveState($pdo, $room_id, $state);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Bingo join: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al unirse']);
    }
    exit;
}

// ─── COMPRAR CARTÓN ──────────────────────────────────────────────────────────
if ($action === 'buy') {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ? FOR UPDATE");
        $stmt->execute([$room_id]);
        $state = json_decode($stmt->fetchColumn(), true);

        if (($state['phase'] ?? 'waiting') !== 'waiting') {
            $pdo->rollBack();
            echo json_encode(['error' => 'Ya no puedes comprar cartones, el sorteo ha comenzado']);
            exit;
        }

        $cardCost = 10;
        $stmtBal  = $pdo->prepare("SELECT balance, username, avatar FROM users WHERE id = ? FOR UPDATE");
        $stmtBal->execute([$user_id]);
        $uInfo = $stmtBal->fetch(PDO::FETCH_ASSOC);

        if ((float)$uInfo['balance'] < $cardCost) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Saldo insuficiente']);
            exit;
        }

        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$cardCost, $user_id]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, ?, 'bet', ?)")->execute([$user_id, $room_id, $cardCost]);

        $uid = (string)$user_id;
        if (!isset($state['players'][$uid])) {
            $state['players'][$uid] = [
                'username'  => $uInfo['username'],
                'avatar'    => $uInfo['avatar'],
                'cards'     => [],
                'total_bet' => 0,
            ];
        }
        $state['players'][$uid]['username']  = $uInfo['username'];
        $state['players'][$uid]['avatar']    = $uInfo['avatar'];
        $state['players'][$uid]['cards'][]   = generateBingoCard();
        $state['players'][$uid]['total_bet'] = ($state['players'][$uid]['total_bet'] ?? 0) + $cardCost;
        $state['pot'] = ($state['pot'] ?? 0) + $cardCost;

        saveState($pdo, $room_id, $state);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Bingo buy: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al comprar cartón']);
    }
    exit;
}

// ─── INICIAR SORTEO ───────────────────────────────────────────────────────────
if ($action === 'start') {
    $state = getState($pdo, $room_id);

    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el líder puede iniciar el sorteo']);
        exit;
    }
    if (($state['phase'] ?? 'waiting') !== 'waiting') {
        echo json_encode(['error' => 'El sorteo ya ha comenzado']);
        exit;
    }

    $totalCards = 0;
    foreach ($state['players'] ?? [] as $p) {
        $totalCards += count($p['cards'] ?? []);
    }
    if ($totalCards === 0) {
        echo json_encode(['error' => 'Nadie ha comprado cartones todavía']);
        exit;
    }

    // Calcular los dos botes aleatoriamente al inicio
    $prizes = calculatePrizes((float)($state['pot'] ?? 0));

    $deck = range(1, 75);
    shuffle($deck);

    $state['phase']        = 'playing';
    $state['deck']         = $deck;
    $state['drawn']        = [];
    $state['last_draw']    = time() - 3; // primera bola sale casi al instante
    $state['line_prize']   = $prizes['line_prize'];
    $state['bingo_prize']  = $prizes['bingo_prize'];
    $state['line_paid']    = false;
    $state['line_winners'] = [];
    unset($state['bingo_winners'], $state['no_bingo_winner'],
          $state['line_payout_each'], $state['bingo_payout_each']);

    saveState($pdo, $room_id, $state);
    $pdo->prepare("UPDATE rooms SET status = 'playing' WHERE id = ?")->execute([$room_id]);

    echo json_encode([
        'success'     => true,
        'line_prize'  => $prizes['line_prize'],
        'bingo_prize' => $prizes['bingo_prize'],
    ]);
    exit;
}

// ─── NUEVA RONDA ─────────────────────────────────────────────────────────────
if ($action === 'new_round') {
    $state = getState($pdo, $room_id);

    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC LIMIT 1");
    $stmt->execute([$room_id]);
    if ($stmt->fetchColumn() != $user_id) {
        echo json_encode(['error' => 'Solo el líder puede reiniciar la ronda']);
        exit;
    }

    foreach ($state['players'] as &$p) {
        $p['cards']     = [];
        $p['total_bet'] = 0;
    }
    unset($p);

    $state['phase']        = 'waiting';
    $state['drawn']        = [];
    $state['deck']         = [];
    $state['pot']          = 0;
    $state['line_prize']   = 0;
    $state['bingo_prize']  = 0;
    $state['line_paid']    = false;
    $state['line_winners'] = [];
    unset($state['bingo_winners'], $state['no_bingo_winner'],
          $state['line_payout_each'], $state['bingo_payout_each']);

    saveState($pdo, $room_id, $state);
    $pdo->prepare("UPDATE rooms SET status = 'waiting' WHERE id = ?")->execute([$room_id]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Acción inválida: ' . htmlspecialchars($action)]);
?>
