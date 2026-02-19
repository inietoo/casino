<?php
require 'config.php';
if ($_SERVER == 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($_POST, $user)) {
        $_SESSION = $user;
        $_SESSION = $user;
        header('Location: index.php'); exit;
    } else {
        $error = "Datos incorrectos.";
    }
}
?>
<!-- Usa la misma estructura HTML/CSS que el registro, cambiando los campos a Login -->
