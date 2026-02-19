<?php
require 'config.php';
if ($_SERVER == 'POST') {
    $user = $_POST;
    $pass = password_hash($_POST, PASSWORD_BCRYPT);
    $avatar = $_POST;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, avatar, balance) VALUES (?, ?, ?, 1000)");
        $stmt->execute();
        $user_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO blackjack_stats (user_id) VALUES (?)")->execute();
        $pdo->prepare("INSERT INTO poker_stats (user_id) VALUES (?)")->execute();
        $pdo->commit();
        
        $_SESSION = $user_id;
        $_SESSION = $user;
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "El usuario ya existe.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro - Casino Local</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <style>
        body { background: #0a0a0a; color: #d4af37; font-family: 'Roboto', sans-serif; display: flex; justify-content: center; height: 100vh; align-items: center; }
        .box { background: #111; padding: 40px; border-radius: 10px; border: 1px solid #d4af37; box-shadow: 0 0 20px rgba(212, 175, 55, 0.2); text-align: center;}
        h1 { font-family: 'Cinzel', serif; margin-bottom: 20px;}
        input, select { display: block; width: 100%; margin: 10px 0; padding: 10px; background: #222; color: #fff; border: 1px solid #333; }
        button { background: #d4af37; color: #000; padding: 10px 20px; font-weight: bold; border: none; cursor: pointer; transition: 0.3s; }
        button:hover { box-shadow: 0 0 15px #d4af37; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Registrarse</h1>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="password" minlength="8" placeholder="Contraseña" required>
            <select name="avatar" required>
                <option value="🎲">🎲 Dados</option><option value="🃏">🃏 Joker</option>
                <option value="👑">👑 Corona</option><option value="🔥">🔥 Fuego</option>
                <option value="💎">💎 Diamante</option><option value="🐉">🐉 Dragón</option>
            </select>
            <button type="submit">Entrar al Casino</button>
        </form>
        <p><a href="login.php" style="color:#d4af37;">Ya tengo cuenta</a></p>
    </div>
</body>
</html>
