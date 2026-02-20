<?php
require '../config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
session_write_close(); // Truco anti-lag para XAMPP

$cost = 10.00;
$symbols = ['🍒', '🍋', '🍉', '🔔', '💎'];

try {
    $pdo->beginTransaction();

    // 1. Comprobar saldo y bloquear fila (seguridad)
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $balance = (float)$stmt->fetchColumn();

    if ($balance < $cost) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Saldo insuficiente. Necesitas 10€ para tirar.']);
        exit;
    }

    // 2. Descontar la tirada
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$cost, $user_id]);

    // 3. Generar la tirada aleatoria
    $r1 = $symbols[array_rand($symbols)];
    $r2 = $symbols[array_rand($symbols)];
    $r3 = $symbols[array_rand($symbols)];
    $reels = [$r1, $r2, $r3];

    // 4. Calcular premios
    $win = 0;
    if ($r1 === $r2 && $r2 === $r3) {
        // Premio gordo (3 iguales)
        if ($r1 === '🍒') $win = 50;
        if ($r1 === '🍋') $win = 50;
        if ($r1 === '🍉') $win = 100;
        if ($r1 === '🔔') $win = 150;
        if ($r1 === '💎') $win = 500; // JACKPOT
    } elseif ($r1 === $r2 || $r2 === $r3 || $r1 === $r3) {
        // Premio de consolación (2 iguales = recuperas la tirada)
        $win = 10;
    }

    // 5. Pagar si ha ganado algo
    if ($win > 0) {
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$win, $user_id]);
    }

    // Calcular saldo final para devolvérselo al HTML
    $new_balance = $balance - $cost + $win;

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'reels' => $reels,
        'win' => $win,
        'new_balance' => $new_balance
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Error en el servidor']);
}
?>
