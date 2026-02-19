<?php
require 'config.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ranking Global - Casino Royal</title>
    <!-- Cargamos los estilos de la carpeta assets -->
    <link rel="stylesheet" href="/casino/assets/css/style.css">
    <link rel="stylesheet" href="/casino/assets/css/ranking.css">
</head>
<body>
    <div class="ranking-header">
        <a href="index.php" class="btn btn-back" style="position: absolute; left: 40px; top: 40px;">← Volver al Lobby</a>
        <h1>🏆 TOP 20 GLADIADORES DEL CASINO</h1>
        <p style="color: var(--gold); text-align: center;">Clasificación Global Ponderada (Balance, Winrate, Ganancias y Rachas)</p>
    </div>
    
    <table class="ranking-table">
        <thead>
            <tr>
                <th>Posición</th>
                <th>Jugador</th>
                <th>Saldo Actual</th>
                <th>Manos Totales</th>
                <th>Winrate %</th>
                <th>Score Total</th>
            </tr>
        </thead>
        <tbody id="rankingBody">
            <!-- Este espacio será rellenado dinámicamente cada 10 segundos por el JS -->
            <tr><td colspan="6" style="text-align: center; padding: 20px;">Calculando clasificaciones en tiempo real...</td></tr>
        </tbody>
    </table>

    <!-- Cargamos el script que hace las peticiones AJAX en tiempo real -->
    <script src="/casino/assets/js/ranking.js"></script>
</body>
</html>
