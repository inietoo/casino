<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error' => 'No autorizado']); exit; }

$user_id = $_SESSION['user_id'];

// Obtener notificaciones no leídas
$stmt = $pdo->prepare("SELECT id, message FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marcar como leídas
if (!empty($notifs)) {
    $ids = array_column($notifs, 'id');
    $in  = str_repeat('?,', count($ids) - 1) . '?';
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($in)")->execute($ids);
}

// Obtener el saldo más reciente
$stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmtBal->execute([$user_id]);
$balance = (float)$stmtBal->fetchColumn();

echo json_encode(['notifications' => $notifs, 'balance' => $balance]);
?>
