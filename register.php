<?php
require 'config.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $avatar   = $_POST['avatar'] ?? '🎲';

    $allowed_avatars = ['🎲','🃏','🤑','👑','🦸','🔥','💎','🐉'];
    if (!in_array($avatar, $allowed_avatars)) {
        $avatar = '🎲';
    }

    if (empty($username) || empty($password)) {
        $error = 'Rellena todos los campos.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'El usuario debe tener entre 3 y 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $error = 'El usuario solo puede contener letras, números, guiones y guiones bajos.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = 'Ese nombre de usuario ya está en uso.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO users (username, password, avatar, balance) VALUES (?, ?, ?, 1000.00)");
                $stmt->execute([$username, $hashed, $avatar]);
                $user_id = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO blackjack_stats (user_id) VALUES (?)")->execute([$user_id]);
                $pdo->prepare("INSERT INTO poker_stats (user_id) VALUES (?)")->execute([$user_id]);
                $pdo->prepare("INSERT INTO bingo_stats (user_id) VALUES (?)")->execute([$user_id]);

                $pdo->commit();

                // Regenerar ID de sesión para prevenir session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user_id;
                $_SESSION['username'] = $username;

                header('Location: index.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Register error: ' . $e->getMessage());
                $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Casino Royal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #f0f0f0; font-family: 'Roboto', sans-serif;
               display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #1a1a1a; border: 1px solid #333; border-top: 4px solid #d4af37;
                border-radius: 10px; padding: 40px; width: 100%; max-width: 420px; }
        .logo { text-align: center; font-family: 'Cinzel', serif; color: #d4af37;
                font-size: 28px; margin-bottom: 10px; }
        h1 { font-family: 'Cinzel', serif; color: #d4af37; text-align: center;
             margin-bottom: 25px; font-size: 20px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #aaa; }
        input, select { width: 100%; padding: 12px; margin-bottom: 18px; background: #222;
                        border: 1px solid #444; border-radius: 5px; color: #f0f0f0; font-size: 15px; }
        input:focus, select:focus { outline: none; border-color: #d4af37; }
        select option { background: #222; }
        .avatar-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 18px; }
        .avatar-option { display: none; }
        .avatar-label { display: flex; justify-content: center; align-items: center;
                        font-size: 28px; background: #222; border: 2px solid #444;
                        border-radius: 8px; padding: 10px; cursor: pointer; transition: 0.2s; }
        .avatar-option:checked + .avatar-label { border-color: #d4af37; background: #2a2a1a;
                                                  box-shadow: 0 0 8px rgba(212,175,55,0.4); }
        .avatar-label:hover { border-color: #d4af37; }
        .btn { width: 100%; padding: 13px; background: #d4af37; color: #000;
               border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #c49b2e; }
        .error { background: #3a1a1a; border: 1px solid #dc3545; color: #dc3545;
                 padding: 10px; border-radius: 5px; margin-bottom: 18px; font-size: 14px; }
        .link { text-align: center; margin-top: 18px; font-size: 14px; color: #aaa; }
        .link a { color: #d4af37; text-decoration: none; }
        .link a:hover { text-decoration: underline; }
        .section-label { font-size: 13px; color: #aaa; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">&#9824; CASINO ROYAL &#9829;</div>
        <h1>Crear Cuenta</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Nombre de usuario</label>
            <input type="text" id="username" name="username"
                   placeholder="Letras, números y guiones"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   required minlength="3" maxlength="50">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   placeholder="Mínimo 8 caracteres" required minlength="8">

            <div class="section-label">Elige tu avatar:</div>
            <div class="avatar-grid">
                <?php
                $avatars = ['🎲','🃏','🤑','👑','🦸','🔥','💎','🐉'];
                foreach ($avatars as $i => $a):
                    $checked = (($_POST['avatar'] ?? '🎲') === $a) ? 'checked' : '';
                ?>
                <div>
                    <input type="radio" name="avatar" id="av<?= $i ?>"
                           value="<?= $a ?>" class="avatar-option"
                           <?= $checked ?> <?= $i === 0 ? 'required' : '' ?>>
                    <label for="av<?= $i ?>" class="avatar-label"><?= $a ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn">🎰 Registrarme y Jugar</button>
        </form>

        <p style="text-align:center; margin-top:12px; font-size:13px; color:#888;">
            Al registrarte recibirás <strong style="color:#d4af37">&euro; 1.000,00</strong> de inicio
        </p>

        <div class="link">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </div>
    </div>
</body>
</html>
