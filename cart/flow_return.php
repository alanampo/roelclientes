<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pago - Roelplant</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .pending { color: #f59e0b; }
        h1 { margin-top: 0; }
        .info { background: #f3f4f6; padding: 16px; border-radius: 8px; margin: 20px 0; }
        .info strong { display: inline-block; min-width: 140px; }
        .btn {
            display: inline-block;
            background: linear-gradient(90deg, #14b8a6, #22d3ee);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $token = $_GET['token'] ?? '';

        if (!$token) {
            echo '<h1 class="error">❌ Error</h1>';
            echo '<p>No se recibió el token de pago.</p>';
        } else {
            // Aquí deberías verificar el pago con Flow API
            // Por ahora mostramos un mensaje genérico
            echo '<h1 class="success">✅ Pago Procesado</h1>';
            echo '<p>Tu pago ha sido recibido y está siendo procesado.</p>';
            echo '<div class="info">';
            echo '<strong>Token:</strong> ' . htmlspecialchars(substr($token, 0, 20)) . '...<br>';
            echo '<strong>Estado:</strong> Procesando<br>';
            echo '</div>';
        }
        ?>
        <a href="/public/bot-alan.html" class="btn">Volver al Bot</a>
    </div>
</body>
</html>
