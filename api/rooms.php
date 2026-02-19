<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode();
    exit;
}

$action = $_GET ?? '';

// 1. LISTAR SALAS
if ($action === 'list') {
    // Busca las salas activas y cuenta los jugadores que NO están en modo espectador
    $query = "
        SELECT r.*, 
        (SELECT COUNT(*) FROM room_players WHERE room_id = r.id AND status != 'spectator') as players 
        FROM rooms r 
        WHERE status != 'finished' 
        ORDER BY r.created_at DESC
    ";
    
    try {
        $stmt = $pdo->query($query);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rooms ?:[]);
    } catch (PDOException $e) {
        echo json_encode();
    }
    exit;
} 

// 2. CREAR NUEVA SALA
elseif ($action === 'create') {
    $type = ($_GET ?? '') === 'poker' ? 'poker' : 'blackjack';
    
    // Si la sesión guarda el username lo cogemos de ahí, si no, usuario genérico
    $username = $_SESSION ?? 'Jugador';
    $name = "Mesa de " . $username;
    
    try {
        $pdo->beginTransaction();
        
        // 2.1 Crear el registro de la Sala (Por defecto: max 6 players, €10 min bet)
        $stmt = $pdo->prepare("INSERT INTO rooms (name, game_type, max_players, min_bet, max_bet, status) VALUES (?, ?, 6, 10.00, 500.00, 'waiting')");
        $stmt->execute();
        $room_id = $pdo->lastInsertId();
        
        // 2.2 Crear el Game State base (JSON limpio)
        $start_state =,
            'players' => [],
            'deck' =>[]
        ];
        $empty_state_json = json_encode($start_state);
        
        $stmt_state = $pdo->prepare("INSERT INTO game_state (room_id, state_json) VALUES (?, ?)");
        $stmt_state->execute();
        
        $pdo->commit();
        
        echo json_encode();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode();
    }
    exit;
}

// 3. ENRUTAMIENTO POR DEFECTO
else {
    echo json_encode();
}
?>
