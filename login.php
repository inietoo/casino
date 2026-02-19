<?php
require 'config.php';

// Si ya está logueado, redirigir al lobby
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, rellena todos los campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Casino Royal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #f0f0f0; font-family: 'Roboto', sans-serif;
               display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37;
                border-radius: 10px; padding: 40px; width: 100%; max-width: 400px; }
        h1 { font-family: 'Cinzel', serif; color: #d4af37; text-align: center; margin-bottom: 30px; font-size: 22px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #aaa; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; background: #222;
                border: 1px solid #444; border-radius: 5px; color: #f0f0f0; font-size: 15px; }
        input:focus { outline: none; border-color: #d4af37; }
        .btn { width: 100%; padding: 13px; background: #d4af37; color: #000;
               border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #c49b2e; }
        .error { background: #3a1a1a; border: 1px solid #dc3545; color: #dc3545;
                 padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .link { text-align: center; margin-top: 20px; font-size: 14px; color: #aaa; }
        .link a { color: #d4af37; text-decoration: none; }
        .link a:hover { text-decoration: underline; }
        .logo { text-align: center; font-family: 'Cinzel', serif; color: #d4af37;
                font-size: 28px; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">&#9824; CASINO ROYAL &#9829;</div>
        <h1>Iniciar Sesión</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Usuario</label>
            <input type="text" id="username" name="username" placeholder="Tu nombre de usuario"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Tu contraseña" required>

            <button type="submit" class="btn">Entrar al Casino</button>
        </form>

        <div class="link">
            ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
        </div>
    </div>
</body>
</html>
