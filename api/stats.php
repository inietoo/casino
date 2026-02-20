<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Obtener evolución de saldo (últimas 30 transacciones)
    $stmt = $pdo->prepare("SELECT type, amount, created_at FROM transactions WHERE user_id = ? ORDER BY created_at ASC LIMIT 30");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $balance_evolution = [];
    $current_balance = 1000.00; // Valor inicial por defecto

    foreach ($transactions as $t) {
        if ($t['type'] === 'win' || $t['type'] === 'reload') {
            $current_balance += (float)$t['amount'];
        } else {
            $current_balance -= (float)$t['amount'];
        }
        $balance_evolution[] = round($current_balance, 2);
    }

    if (empty($balance_evolution)) {
        // Si no hay transacciones, obtener el saldo actual
        $stmtBalance = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmtBalance->execute([$user_id]);
        $balance_evolution[] = (float)$stmtBalance->fetchColumn();
    }

    echo json_encode([
        'balance_evolution' => $balance_evolution
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al obtener estadísticas']);
}
