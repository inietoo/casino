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
    $query = "
        SELECT r.*,
        (SELECT COUNT(*) FROM room_players WHERE room_id = r.id AND status != 'spectator') as players
        FROM rooms r
        WHERE r.status != 'finished'
        ORDER BY r.created_at DESC
    ";
    try {
        $stmt  = $pdo->query($query);
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
    $type     = ($_GET['type'] ?? '') === 'poker' ? 'poker' : 'blackjack';
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

else {
    echo json_encode(['error' => 'Acción no reconocida']);
}
?>
