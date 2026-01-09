<?php
/**
 * catalogo_detalle/api/payment/webpay_return.php
 * Maneja el retorno desde Webpay después del pago
 * GET /api/payment/webpay_return?token_ws=...
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../services/WebpayService.php';

// Detectar ruta base dinámicamente
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); // Ej: /catalogo_detalle/api/payment
$basePath = preg_replace('#/api/payment$#', '', $scriptPath); // Remover /api/payment
$baseUrl = rtrim($basePath, '/') . '/'; // Ej: /catalogo_detalle/

$token = (string)($_GET['token_ws'] ?? '');

if (empty($token)) {
  // Redirigir a checkout con error
  header('Location: ' . $baseUrl . 'checkout.php?payment_error=invalid_token');
  exit;
}

$db = db();
$APP = require __DIR__ . '/../../config/app.php';

// Obtener transacción de BD
$st = $db->prepare("SELECT id, id_cliente, buy_order, amount, status, authorized
                    FROM webpay_transactions WHERE token = ? LIMIT 1");
if (!$st) {
  header('Location: ' . buildUrl('checkout.php?payment_error=db_error'));
  exit;
}

$st->bind_param('s', $token);
$st->execute();
$transaction = $st->get_result()->fetch_assoc();
$st->close();

if (!$transaction) {
  header('Location: ' . $baseUrl . 'checkout.php?payment_error=transaction_not_found');
  exit;
}

$transactionId = (int)$transaction['id'];
$cid = (int)$transaction['id_cliente'];

// Inicializar sesión del cliente si es necesario
if (empty($_SESSION['customer_id'])) {
  $_SESSION['customer_id'] = $cid;
}

// Inicializar Webpay
$webpay = new WebpayService(
  $APP['WEBPAY_ENVIRONMENT'],
  $APP['WEBPAY_COMMERCE_CODE'],
  $APP['WEBPAY_API_KEY']
);

// Confirmar transacción con Webpay
$result = $webpay->commitTransaction($token);

// Actualizar estado en BD
$authorized = $result['authorized'] ?? false;
$status = $result['status'] ?? 'UNKNOWN';
$authCode = (string)($result['authorization_code'] ?? '');
$cardNumber = (string)($result['card_number'] ?? '');
$vci = (string)($result['vci'] ?? '');
$responseCode = (int)($result['response_code'] ?? -1);

$st = $db->prepare("UPDATE webpay_transactions SET status = ?, authorized = ?, authorization_code = ?,
                    card_number = ?, vci = ?, response_code = ?, confirmed_at = NOW()
                    WHERE id = ?");
if ($st) {
  $st->bind_param('sisssii', $status, $authorized, $authCode, $cardNumber, $vci, $responseCode, $transactionId);
  $st->execute();
  $st->close();
}

if (!$authorized) {
  // Pago rechazado
  header('Location: ' . $baseUrl . "checkout.php?payment_error=payment_denied&code={$responseCode}");
  exit;
}

// Pago exitoso: vaciar carrito y redirigir a confirmación
$db->begin_transaction();
try {
  // Obtener carrito
  $st = $db->prepare("SELECT id FROM " . CART_TABLE . " WHERE id_cliente = ? AND status = 'open' LIMIT 1");
  if ($st) {
    $st->bind_param('i', $cid);
    $st->execute();
    $cartRow = $st->get_result()->fetch_assoc();
    $st->close();

    if ($cartRow) {
      $cartId = (int)$cartRow['id'];

      // Vaciar items del carrito
      $st = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE cart_id = ?");
      if ($st) {
        $st->bind_param('i', $cartId);
        $st->execute();
        $st->close();
      }
    }
  }

  $db->commit();

  // Guardar datos en sesión para mostrar en página de confirmación
  $_SESSION['webpay_payment_success'] = [
    'transaction_id' => $transactionId,
    'token' => $token,
    'amount' => $transaction['amount'],
    'buy_order' => $transaction['buy_order'],
    'authorization_code' => $authCode,
    'card_number' => $cardNumber
  ];

  // Redirigir a página de confirmación de pago
  header('Location: ' . $baseUrl . 'payment_success.php?transaction_id=' . urlencode((string)$transactionId));
  exit;

} catch (Exception $e) {
  $db->rollback();
  header('Location: ' . $baseUrl . 'checkout.php?payment_error=processing_error');
  exit;
}
