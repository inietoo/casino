<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$action  = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// ─── 1. LISTAR SALAS ───────────────────────────────────────────────────────
if ($action === 'list') {
    $type = $_GET['type'] ?? '';

    if ($type) {
        $query = "
            SELECT r.*,
            (SELECT COUNT(*) FROM room_players WHERE room_id = r.id AND status != 'spectator') as players
            FROM rooms r
            WHERE r.status != 'finished' AND r.game_type = :type
            ORDER BY r.created_at DESC
        ";
    } else {
        $query = "
            SELECT r.*,
            (SELECT COUNT(*) FROM room_players WHERE room_id = r.id AND status != 'spectator') as players
            FROM rooms r
            WHERE r.status != 'finished'
            ORDER BY r.created_at DESC
        ";
    }

    try {
        $stmt = $pdo->prepare($query);
        if ($type) {
            $stmt->execute(['type' => $type]);
        } else {
            $stmt->execute();
        }
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rooms ?: []);
    } catch (PDOException $e) {
        error_log('List rooms: ' . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// ─── 2. CREAR NUEVA SALA ───────────────────────────────────────────────────
elseif ($action === 'create') {
    $reqType  = $_POST['type'] ?? $_GET['type'] ?? '';
    $type     = in_array($reqType, ['blackjack', 'poker', 'bingo']) ? $reqType : 'blackjack';
    $username = $_SESSION['username'] ?? 'Jugador';
    $name     = 'Mesa de ' . htmlspecialchars($username);

    try {
        $pdo->beginTransaction();

        // Insertar sala
        $stmt = $pdo->prepare("INSERT INTO rooms (name, game_type, max_players, min_bet, max_bet, status) VALUES (?, ?, 6, 10.00, 500.00, 'waiting')");
        $stmt->execute([$name, $type]);
        $room_id = $pdo->lastInsertId();

        // Añadir creador como jugador (seat 1)
        $stmt2 = $pdo->prepare('INSERT INTO room_players (room_id, user_id, seat, status) VALUES (?, ?, 1, \'active\')');
        $stmt2->execute([$room_id, $user_id]);

        // Estado inicial del juego
        $start_state = json_encode(['phase' => 'waiting', 'players' => [], 'deck' => []]);
        $stmt3 = $pdo->prepare("INSERT INTO game_state (room_id, state_json, phase) VALUES (?, ?, 'waiting')");
        $stmt3->execute([$room_id, $start_state]);

        $pdo->commit();
        echo json_encode(['success' => true, 'room_id' => $room_id]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Create room: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al crear la sala']);
    }
    exit;
}

// ─── 3. OBTENER ESTADO DE UNA SALA ────────────────────────────────────────
elseif ($action === 'get_state') {
    $room_id = (int)($_GET['room_id'] ?? 0);
    if (!$room_id) { echo json_encode(['error' => 'room_id requerido']); exit; }

    try {
        $stmt = $pdo->prepare('
            SELECT gs.*, r.game_type, r.name, r.min_bet, r.max_bet
            FROM game_state gs
            JOIN rooms r ON r.id = gs.room_id
            WHERE gs.room_id = ?');
        $stmt->execute([$room_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare('
            SELECT rp.seat, rp.status, u.username, u.avatar, u.balance
            FROM room_players rp
            JOIN users u ON u.id = rp.user_id
            WHERE rp.room_id = ? AND rp.status != \'spectator\'
            ORDER BY rp.seat');
        $stmt2->execute([$room_id]);
        $players = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['state' => $state, 'players' => $players]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ─── 4. SALIR DE UNA SALA ──────────────────────────────────────────────────
elseif ($action === 'leave') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    if (!$room_id) { echo json_encode(['error' => 'room_id requerido']); exit; }

    try {
        $pdo->beginTransaction();
        
        // 1. Borrar al jugador de la sala
        $stmt = $pdo->prepare("DELETE FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$room_id, $user_id]);

        // 2. Comprobar si la sala quedó vacía
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM room_players WHERE room_id = ?");
        $stmt2->execute([$room_id]);
        
        if ((int)$stmt2->fetchColumn() === 0) {
            // Si no hay nadie, terminamos la sala permanentemente
            $pdo->prepare("UPDATE rooms SET status = 'finished' WHERE id = ?")->execute([$room_id]);
        } else {
            // Si quedan jugadores, quitamos a este jugador del JSON game_state
            $stmt3 = $pdo->prepare("SELECT state_json FROM game_state WHERE room_id = ?");
            $stmt3->execute([$room_id]);
            $stateRow = $stmt3->fetch(PDO::FETCH_ASSOC);
            if ($stateRow) {
                $state = json_decode($stateRow['state_json'], true);
                if (isset($state['players'][(string)$user_id])) {
                    unset($state['players'][(string)$user_id]);
                    $pdo->prepare("UPDATE game_state SET state_json = ? WHERE room_id = ?")
                        ->execute([json_encode($state), $room_id]);
                }
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al salir de la sala']);
    }
    exit;
}

else {
    echo json_encode(['error' => 'Acción no reconocida']);
}
?>
