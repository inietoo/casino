<?php
require '../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── RECARGA DE SALDO GRATIS ─────────────────────────────────────────────────
if ($action === 'reload') {
    if (!isLoggedIn()) { echo json_encode(['error' => 'No autorizado']); exit; }

    $user_id = $_SESSION['user_id'];

    try {
        // CORRECCIÓN: todo dentro de transacción con FOR UPDATE para evitar
        // race condition donde dos peticiones simultáneas pasan el check de cooldown
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $balance = (float)$stmt->fetchColumn();

        if ($balance >= 5) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Saldo insuficiente para recargar (necesitas menos de €5)']);
            exit;
        }

        // Comprobar cooldown de 1 hora DENTRO de la transacción
        $stmt2 = $pdo->prepare("
            SELECT MAX(created_at) FROM transactions
            WHERE user_id = ? AND type = 'reload'
        ");
        $stmt2->execute([$user_id]);
        $lastReload = $stmt2->fetchColumn();

        if ($lastReload && (time() - strtotime($lastReload)) < 3600) {
            $pdo->rollBack();
            $remaining = 3600 - (time() - strtotime($lastReload));
            $mins = ceil($remaining / 60);
            echo json_encode(['error' => "Debes esperar {$mins} minuto(s) más para recargar"]);
            exit;
        }

        $reloadAmount = 500.00;
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$reloadAmount, $user_id]);
        $pdo->prepare("INSERT INTO transactions (user_id, room_id, type, amount) VALUES (?, 0, 'reload', ?)")->execute([$user_id, $reloadAmount]);

        $pdo->commit();
        echo json_encode(['success' => true, 'amount' => $reloadAmount]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Reload error: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al recargar']);
    }
    exit;
}

// ─── ESTADO DE SESIÓN ─────────────────────────────────────────────────────────
if ($action === 'session') {
    if (!isLoggedIn()) {
        echo json_encode(['logged_in' => false]);
    } else {
        $stmt = $pdo->prepare("SELECT username, avatar, balance FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode(['logged_in' => true, 'user' => $user]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
    }
    exit;
}

// ─── ACTUALIZAR AVATAR ────────────────────────────────────────────────────────
if ($action === 'update_avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) { echo json_encode(['error' => 'No autorizado']); exit; }

    $avatar  = $_POST['avatar'] ?? '🎲';
    $allowed = ['🎲','🃏','🤑','👑','🦸','🔥','💎','🐉'];

    if (!in_array($avatar, $allowed)) {
        echo json_encode(['error' => 'Avatar inválido']);
        exit;
    }

    try {
        $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatar, $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Update avatar: ' . $e->getMessage());
        echo json_encode(['error' => 'Error al actualizar avatar']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
?>
