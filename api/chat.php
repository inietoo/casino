<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$action  = $_GET['action'] ?? '';
$room_id = (int)($_GET['room_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$room_id) {
    echo json_encode(['error' => 'room_id requerido']);
    exit;
}

// ─── OBTENER MENSAJES ────────────────────────────────────────────────────────
if ($action === 'get') {
    header('Content-Type: text/html; charset=utf-8');
    try {
        $stmt = $pdo->prepare("
            SELECT cm.message, cm.sent_at, u.username, u.avatar
            FROM chat_messages cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.room_id = ?
            ORDER BY cm.sent_at DESC
            LIMIT 50
        ");
        $stmt->execute([$room_id]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        $html = '';
        foreach ($messages as $msg) {
            $time  = date('H:i', strtotime($msg['sent_at']));
            $html .= '<div style="margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid rgba(255,255,255,0.05);">'
                   . '<span style="color:#d4af37; font-size:11px;">[' . $time . '] </span>'
                   . '<strong>' . htmlspecialchars($msg['avatar'] . ' ' . $msg['username']) . ':</strong> '
                   . htmlspecialchars($msg['message'])
                   . '</div>';
        }
        echo $html ?: '<div style="color:#666; font-style:italic;">Sin mensajes aún...</div>';
    } catch (PDOException $e) {
        error_log('Chat get: ' . $e->getMessage());
        echo '<div style="color:red;">Error al cargar mensajes.</div>';
    }
    exit;
}

// ─── ENVIAR MENSAJE ──────────────────────────────────────────────────────────
if ($action === 'send') {
    header('Content-Type: application/json');

    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['error' => 'Mensaje vacío']);
        exit;
    }

    $message = mb_substr($message, 0, 300);

    try {
        // CORRECCIÓN: Rate limiting — máximo 3 mensajes por segundo por usuario
        $stmtRate = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages
            WHERE user_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
        ");
        $stmtRate->execute([$user_id]);
        if ((int)$stmtRate->fetchColumn() >= 3) {
            echo json_encode(['error' => 'Estás enviando mensajes demasiado rápido. Espera un momento.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$room_id, $user_id, $message]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Chat send: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al enviar']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
?>
