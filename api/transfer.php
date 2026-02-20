<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id          = $_SESSION['user_id'];
$target_username  = trim($_POST['username'] ?? '');
$amount           = (float)($_POST['amount'] ?? 0);

if ($amount <= 0 || $amount > 999999) {
    echo json_encode(['error' => 'La cantidad debe ser mayor a 0']);
    exit;
}

if (empty($target_username)) {
    echo json_encode(['error' => 'El nombre de usuario no puede estar vacío']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener y bloquear mi saldo actual
    $stmt = $pdo->prepare("SELECT balance, username FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error de sesión']);
        exit;
    }

    if ($me['balance'] < $amount) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Saldo insuficiente']);
        exit;
    }

    // 2. Buscar al destinatario por nombre de usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? FOR UPDATE");
    $stmt->execute([$target_username]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $pdo->rollBack();
        echo json_encode(['error' => 'El usuario "' . htmlspecialchars($target_username) . '" no existe']);
        exit;
    }

    if ($target['id'] == $user_id) {
        $pdo->rollBack();
        echo json_encode(['error' => 'No puedes enviarte dinero a ti mismo, gracioso']);
        exit;
    }

    // 3. Ejecutar la transferencia
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user_id]);
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $target['id']]);

    // 4. Registrar transacción del EMISOR (corrección: antes no se registraba)
    $pdo->prepare("
        INSERT INTO transactions (user_id, room_id, type, amount)
        VALUES (?, 0, 'transfer_out', ?)
    ")->execute([$user_id, $amount]);

    // 5. Registrar transacción del RECEPTOR
    $pdo->prepare("
        INSERT INTO transactions (user_id, room_id, type, amount)
        VALUES (?, 0, 'transfer_in', ?)
    ")->execute([$target['id'], $amount]);

    // 6. Notificación al receptor
    $msg = sprintf(
        'Has recibido un Bizum de €%s de %s',
        number_format($amount, 2),
        $me['username']
    );
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$target['id'], $msg]);

    $pdo->commit();

    $newBalance = $me['balance'] - $amount;
    echo json_encode(['success' => true, 'new_balance' => $newBalance]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Transfer error: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al procesar el envío']);
}
?>
