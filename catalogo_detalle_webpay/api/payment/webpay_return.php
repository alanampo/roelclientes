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
$st = $db->prepare("SELECT id, id_cliente, id_reserva, buy_order, amount, status, authorized
                    FROM webpay_transactions WHERE token = ? LIMIT 1");
if (!$st) {
  header('Location: ' . $baseUrl . 'checkout.php?payment_error=db_error');
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
$idReserva = (int)($transaction['id_reserva'] ?? 0);

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

// LOG: Guardar respuesta de Transbank para debug
$logFile = __DIR__ . '/../../logs/webpay_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logData = [
  'timestamp' => date('Y-m-d H:i:s'),
  'token' => $token,
  'transaction_id' => $transactionId,
  'id_reserva' => $idReserva,
  'result' => $result
];
@file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);

// Actualizar estado en BD
$authorized = $result['authorized'] ?? false;
$status = $result['status'] ?? 'UNKNOWN';
$authCode = (string)($result['authorization_code'] ?? '');
$cardNumber = (string)($result['card_number'] ?? '');
$cardLastDigits = (string)($result['card_last_digits'] ?? '');
$vci = (string)($result['vci'] ?? '');
$responseCode = (int)($result['response_code'] ?? -1);
$transactionDate = (string)($result['transaction_date'] ?? '');
$buyOrder = (string)($result['buy_order'] ?? '');
$installmentsNumber = (int)($result['installments_number'] ?? 0);
$amount = (int)($result['amount'] ?? 0);

// Extraer payment_type_code de la respuesta
$paymentTypeCode = (string)($result['payment_type_code'] ?? '');

// UPDATE webpay_transactions - guardar toda la información de Transbank
$st = $db->prepare("UPDATE webpay_transactions
                    SET status = ?, authorized = ?, authorization_code = ?,
                        card_number = ?, vci = ?, response_code = ?,
                        transaction_date = ?, payment_type_code = ?, installments_number = ?,
                        confirmed_at = NOW()
                    WHERE id = ?");

// LOG: Debug del UPDATE
$updateLog = [
  'prepare_ok' => ($st !== false),
  'prepare_error' => $db->error,
  'params' => [
    'status' => $status,
    'authorized' => $authorized,
    'authCode' => $authCode,
    'cardNumber' => $cardNumber,
    'vci' => $vci,
    'responseCode' => $responseCode,
    'transactionDate' => $transactionDate,
    'paymentTypeCode' => $paymentTypeCode,
    'installmentsNumber' => $installmentsNumber,
    'transactionId' => $transactionId
  ]
];

if ($st) {
  $authorizedInt = $authorized ? 1 : 0;
  $st->bind_param('sisssissii', $status, $authorizedInt, $authCode, $cardNumber, $vci, $responseCode,
                  $transactionDate, $paymentTypeCode, $installmentsNumber, $transactionId);
  $executeResult = $st->execute();
  $updateLog['execute_ok'] = $executeResult;
  $updateLog['execute_error'] = $st->error;
  $updateLog['affected_rows'] = $st->affected_rows;
  $st->close();
}

@file_put_contents($logFile, "UPDATE LOG: " . json_encode($updateLog, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

if (!$authorized) {
  // Pago rechazado - eliminar la reserva y sus productos
  if ($idReserva > 0) {
    // Conectar a BD de producción
    $conectaPaths = [
      __DIR__ . '/../../class_lib/class_conecta_mysql.php',
      __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
      __DIR__ . '/../../../../class_lib/class_conecta_mysql.php',
    ];
    foreach ($conectaPaths as $p) {
      if (is_file($p)) {
        require $p;
        $dbStock = @mysqli_connect($host, $user, $password, $dbname);
        if ($dbStock) {
          // Eliminar productos de la reserva
          mysqli_query($dbStock, "DELETE FROM reservas_productos WHERE id_reserva={$idReserva}");
          // Eliminar la reserva
          mysqli_query($dbStock, "DELETE FROM reservas WHERE id={$idReserva}");
          mysqli_close($dbStock);
        }
        break;
      }
    }
  }

  header('Location: ' . $baseUrl . "checkout.php?payment_error=payment_denied&code={$responseCode}");
  exit;
}

// Pago exitoso: actualizar reserva y vaciar carrito
$db->begin_transaction();
try {
  // Conectar a BD de producción para actualizar reserva
  $conectaPaths = [
    __DIR__ . '/../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../../class_lib/class_conecta_mysql.php',
  ];
  $dbStock = null;
  foreach ($conectaPaths as $p) {
    if (is_file($p)) {
      require $p;
      $dbStock = @mysqli_connect($host, $user, $password, $dbname);
      break;
    }
  }

  if ($dbStock && $idReserva > 0) {
    // Actualizar estado de pago de la reserva - pago exitoso
    // estado=0 (completado), payment_status='paid'

    // Determinar tipo de tarjeta usando payment_type_code: VD=Débito, VN/VC/etc=Crédito
    $cardType = (!empty($vci)) ? 'Débito' : 'Crédito';

    // Convertir fecha ISO 8601 a formato MySQL
    $transactionDateMysql = '';
    if (!empty($transactionDate)) {
      $timestamp = strtotime($transactionDate);
      if ($timestamp !== false) {
        $transactionDateMysql = date('Y-m-d H:i:s', $timestamp);
      }
    }

    // Escapar valores para MySQL
    $cardType = mysqli_real_escape_string($dbStock, $cardType);
    $cardLastDigits = mysqli_real_escape_string($dbStock, $cardLastDigits);
    $authCode = mysqli_real_escape_string($dbStock, $authCode);
    $status = mysqli_real_escape_string($dbStock, $status);
    $buyOrder = mysqli_real_escape_string($dbStock, $buyOrder);
    $transactionDateMysql = mysqli_real_escape_string($dbStock, $transactionDateMysql);
    $token = mysqli_real_escape_string($dbStock, $token);

    $updateQuery = "UPDATE reservas
                    SET payment_status='paid',
                        paid_clp={$amount},
                        webpay_transaction_date='{$transactionDateMysql}',
                        webpay_card_type='{$cardType}',
                        webpay_installment_count={$installmentsNumber},
                        webpay_card_last_digits='{$cardLastDigits}',
                        webpay_amount={$amount},
                        webpay_authorization_code='{$authCode}',
                        webpay_bank_response='{$status}',
                        webpay_order_number='{$buyOrder}',
                        webpay_response_code={$responseCode},
                        webpay_token='{$token}',
                        updated_at=NOW()
                    WHERE id={$idReserva}";
    $updateResult = mysqli_query($dbStock, $updateQuery);
    $affectedRows = mysqli_affected_rows($dbStock);
    $updateError = mysqli_error($dbStock);

    // LOG: Debug del UPDATE de reservas
    $reservaUpdateLog = [
      'timestamp' => date('Y-m-d H:i:s'),
      'update_result' => $updateResult,
      'affected_rows' => $affectedRows,
      'error' => $updateError,
      'id_reserva' => $idReserva,
      'dbStock_connected' => ($dbStock && mysqli_ping($dbStock)),
      'query' => $updateQuery
    ];
    @file_put_contents($logFile, "RESERVA UPDATE LOG: " . json_encode($reservaUpdateLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);

    // Actualizar estado=0 (pago aceptado) en todos los productos de la reserva
    $queryUpdateEstado = "UPDATE reservas_productos SET estado=0 WHERE id_reserva=?";
    $stUpdateEstado = $dbStock->prepare($queryUpdateEstado);
    if ($stUpdateEstado) {
      $stUpdateEstado->bind_param('i', $idReserva);
      $stUpdateEstado->execute();
      $stUpdateEstado->close();
    }

    mysqli_close($dbStock);
  }

  // Vaciar carrito
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
    'reservation_id' => $idReserva,
    'token' => $token,
    'amount' => $transaction['amount'],
    'buy_order' => $transaction['buy_order'],
    'authorization_code' => $authCode,
    'card_number' => $cardNumber
  ];

  // Redirigir a página de confirmación de pago
  header('Location: ' . $baseUrl . 'payment_success.php?transaction_id=' . urlencode((string)$transactionId) . '&reservation_id=' . urlencode((string)$idReserva));
  exit;

} catch (Exception $e) {
  $db->rollback();
  header('Location: ' . $baseUrl . 'checkout.php?payment_error=processing_error');
  exit;
}
