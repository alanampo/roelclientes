<?php
// catalogo_detalle/payment_success.php
// Página de confirmación de pago exitoso

require __DIR__ . '/config/routes.php';
require __DIR__ . '/api/_bootstrap.php';
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$APP = require __DIR__ . '/config/app.php';

// Verificar si hay información de pago exitoso
$paymentData = $_SESSION['webpay_payment_success'] ?? null;
unset($_SESSION['webpay_payment_success']);

$transactionId = (int)($_GET['transaction_id'] ?? 0);
$logged = !empty($_SESSION['customer_id']);

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pago Exitoso - Roelplant</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/checkout.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    .success-box {
      text-align: center;
      padding: 40px 20px;
      background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
      color: white;
      border-radius: 8px;
      margin: 40px auto;
      max-width: 500px;
    }
    .success-box h1 {
      font-size: 2.5em;
      margin-bottom: 20px;
    }
    .success-icon {
      font-size: 4em;
      margin-bottom: 20px;
    }
    .transaction-details {
      background: #f5f5f5;
      padding: 20px;
      border-radius: 4px;
      margin: 20px 0;
      text-align: left;
      color: #333;
    }
    .transaction-details div {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid #ddd;
    }
    .transaction-details div:last-child {
      border-bottom: none;
    }
    .label {
      font-weight: 600;
      color: #666;
    }
    .value {
      color: #333;
      font-family: monospace;
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="brand" aria-label="Roelplant">
      <span class="dot" aria-hidden="true"></span>
      <span>Roelplant</span>
    </div>
    <div class="actions">
      <a class="btn" href="<?php echo htmlspecialchars(buildUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Volver al catálogo</a>
    </div>
  </header>

  <main class="container">
    <div class="success-box">
      <div class="success-icon">✓</div>
      <h1>¡Pago Exitoso!</h1>
      <p>Tu compra ha sido procesada correctamente.</p>

      <?php if ($paymentData): ?>
      <div class="transaction-details">
        <div>
          <span class="label">ID Transacción:</span>
          <span class="value"><?php echo htmlspecialchars((string)$paymentData['transaction_id']); ?></span>
        </div>
        <div>
          <span class="label">Orden de Compra:</span>
          <span class="value"><?php echo htmlspecialchars((string)$paymentData['buy_order']); ?></span>
        </div>
        <div>
          <span class="label">Monto:</span>
          <span class="value">$<?php echo number_format((int)$paymentData['amount'], 0, ',', '.'); ?></span>
        </div>
        <div>
          <span class="label">Código de Autorización:</span>
          <span class="value"><?php echo htmlspecialchars((string)$paymentData['authorization_code']); ?></span>
        </div>
        <div>
          <span class="label">Últimos dígitos tarjeta:</span>
          <span class="value">****<?php echo substr($paymentData['card_number'], -4); ?></span>
        </div>
      </div>

      <p style="font-size: 0.9em; color: #f0f0f0; margin-top: 20px;">
        Se ha enviado un email de confirmación con los detalles de tu compra.<br>
        Tu carrito ha sido vaciado automáticamente.
      </p>
      <?php else: ?>
      <p style="margin-top: 20px; font-size: 0.9em;">
        Pago procesado correctamente. Por favor verifica tu email para más detalles.
      </p>
      <?php endif; ?>

      <a href="<?php echo htmlspecialchars(buildUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" style="margin-top: 30px; display: inline-block;">
        Seguir Comprando
      </a>
    </div>
  </main>

</body>
</html>
