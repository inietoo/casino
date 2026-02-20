<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$target_username = trim($_POST['username'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);

if ($amount <= 0) {
    echo json_encode(['error' => 'La cantidad debe ser mayor a 0']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener y bloquear mi saldo actual
    $stmt = $pdo->prepare("SELECT balance, username FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($me['balance'] < $amount) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Saldo insuficiente']);
        exit;
    }

    // 2. Buscar al colega por nombre de usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? FOR UPDATE");
    $stmt->execute([$target_username]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $pdo->rollBack();
        echo json_encode(['error' => 'El usuario "' . $target_username . '" no existe']);
        exit;
    }

    if ($target['id'] == $user_id) {
        $pdo->rollBack();
        echo json_encode(['error' => 'No puedes enviarte dinero a ti mismo, gracioso']);
        exit;
    }

    // 3. Ejecutar el "Bizum"
    // Restar a mí
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user_id]);
    // Sumar a él
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $target['id']]);

    // 4. Registrar transacciones para el historial (opcional pero recomendado)
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount) VALUES (?, 'reload', ?)")->execute([$target['id'], $amount]);

    $pdo->commit();
    echo json_encode(['success' => true, 'new_balance' => $me['balance'] - $amount]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Error al procesar el envío']);
}
