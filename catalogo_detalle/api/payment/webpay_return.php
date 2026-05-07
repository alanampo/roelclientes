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

  // Enviar email de confirmación al cliente (no bloqueante)
  try {
    send_order_confirmation_email($idReserva, $cid);
  } catch (\Throwable $emailErr) {
    error_log('[webpay_return] Email error: ' . $emailErr->getMessage());
  }

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

/* ================================================================
   EMAIL DE CONFIRMACIÓN DE PAGO
   ================================================================ */
function send_order_confirmation_email(int $idReserva, int $cid): void {
  // Cargar PHPMailer (mismas rutas que forgot_password.php)
  $autoloadPaths = [
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
  ];
  $autoloadFound = false;
  foreach ($autoloadPaths as $p) {
    if (is_file($p)) { require_once $p; $autoloadFound = true; break; }
  }
  if (!$autoloadFound) {
    error_log('[webpay_return] PHPMailer autoload no encontrado');
    return;
  }

  // Conectar a BD de producción
  $conectaPaths = [
    __DIR__ . '/../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../../class_lib/class_conecta_mysql.php',
  ];
  $dbStock = null;
  foreach ($conectaPaths as $p) {
    if (is_file($p)) {
      require_once $p;
      $dbStock = @mysqli_connect($host, $user, $password, $dbname);
      if ($dbStock) { mysqli_set_charset($dbStock, 'utf8'); break; }
    }
  }
  if (!$dbStock) {
    error_log('[webpay_return] No se pudo conectar a BD producción para email');
    return;
  }

  // Obtener reserva + email del cliente
  $stRes = $dbStock->prepare(
    "SELECT r.subtotal_clp, r.packing_cost_clp, r.shipping_cost_clp, r.total_clp, r.paid_clp,
            r.shipping_method, r.shipping_address, r.shipping_commune,
            r.shipping_agency_name, r.shipping_agency_address, r.created_at,
            c.nombre AS customer_nombre, c.mail AS customer_email
     FROM reservas r
     LEFT JOIN clientes c ON c.id_cliente = r.id_cliente
     WHERE r.id = ? AND r.id_cliente = ?
     LIMIT 1"
  );
  if (!$stRes) { mysqli_close($dbStock); return; }
  $stRes->bind_param('ii', $idReserva, $cid);
  $stRes->execute();
  $reserva = $stRes->get_result()->fetch_assoc();
  $stRes->close();

  if (!$reserva || empty($reserva['customer_email'])) {
    mysqli_close($dbStock);
    return;
  }

  $toEmail   = (string)$reserva['customer_email'];
  $toNombre  = (string)($reserva['customer_nombre'] ?? '');
  $subtotal  = (int)($reserva['subtotal_clp'] ?? 0);
  $packing   = (int)($reserva['packing_cost_clp'] ?? 0);
  $shipping  = (int)($reserva['shipping_cost_clp'] ?? 0);
  $paidClp   = (int)($reserva['paid_clp'] ?? 0);
  $total     = $paidClp > 0 ? $paidClp : (int)($reserva['total_clp'] ?? 0);
  $shMethod  = (string)($reserva['shipping_method'] ?? '');
  $createdAt = (string)($reserva['created_at'] ?? '');

  // Label de envío
  if ($shMethod === 'vivero') {
    $shippingLabel = 'Retiro en vivero (gratis)';
  } elseif ($shMethod === 'agencia') {
    $agName = (string)($reserva['shipping_agency_name'] ?? '');
    $agAddr = (string)($reserva['shipping_agency_address'] ?? '');
    $shippingLabel = 'Retiro en sucursal Starken';
    if ($agName) $shippingLabel .= " — {$agName}";
    if ($agAddr) $shippingLabel .= " ({$agAddr})";
  } elseif ($shMethod === 'domicilio') {
    $addr   = (string)($reserva['shipping_address'] ?? '');
    $commune= (string)($reserva['shipping_commune'] ?? '');
    $shippingLabel = 'Envío a domicilio';
    if ($addr)   $shippingLabel .= " — {$addr}";
    if ($commune) $shippingLabel .= " ({$commune})";
  } else {
    $shippingLabel = 'Por definir';
  }

  // Obtener items de la reserva
  $stItems = $dbStock->prepare(
    "SELECT rp.cantidad, v.nombre,
            CONCAT(t.codigo, LPAD(v.id_interno, 4, '0')) AS referencia,
            COALESCE(v.precio_detalle, v.precio, 0) AS unit_price
     FROM reservas_productos rp
     LEFT JOIN variedades_producto v ON v.id = rp.id_variedad
     LEFT JOIN tipos_producto t ON t.id = v.id_tipo
     WHERE rp.id_reserva = ?
     ORDER BY rp.id ASC"
  );
  $items = [];
  if ($stItems) {
    $stItems->bind_param('i', $idReserva);
    $stItems->execute();
    $resItems = $stItems->get_result();
    while ($row = $resItems->fetch_assoc()) {
      $qty   = (int)$row['cantidad'];
      $price = (float)$row['unit_price'];
      $items[] = [
        'name'  => (string)($row['nombre'] ?? ''),
        'ref'   => (string)($row['referencia'] ?? ''),
        'qty'   => $qty,
        'price' => (int)$price,
        'line'  => (int)($price * $qty),
      ];
    }
    $stItems->close();
  }
  mysqli_close($dbStock);

  // Helpers
  $clp = fn(int $n): string => '$' . number_format($n, 0, ',', '.');
  $esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $orderCode = 'RP-' . str_pad((string)$idReserva, 6, '0', STR_PAD_LEFT);

  // Construir filas de items
  $itemRows = '';
  foreach ($items as $it) {
    $itemRows .= '<tr>
      <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6">
        <strong style="color:#111827">' . $esc($it['name']) . '</strong><br>
        <span style="font-size:12px;color:#6b7280">' . $esc($it['ref']) . ' · ' . $it['qty'] . ' x ' . $clp($it['price']) . '</span>
      </td>
      <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700;color:#111827;white-space:nowrap">' . $clp($it['line']) . '</td>
    </tr>';
  }

  // Fila de packing (si aplica)
  $packingRow = '';
  if ($packing > 0) {
    $packingRow = '<tr>
      <td style="padding:8px 8px;color:#6b7280">Packing</td>
      <td style="padding:8px 8px;text-align:right;color:#6b7280">' . $clp($packing) . '</td>
    </tr>';
  }

  // Fila de envío
  $shippingRow = '<tr>
    <td style="padding:8px 8px;color:#6b7280">Envío</td>
    <td style="padding:8px 8px;text-align:right;color:' . ($shipping > 0 ? '#111827' : '#16a34a') . ';font-weight:' . ($shipping > 0 ? '400' : '600') . '">'
    . ($shipping > 0 ? $clp($shipping) : $esc($shippingLabel))
    . '</td>
  </tr>';

  $saludo = $toNombre ? ('Hola ' . $esc(explode(' ', $toNombre)[0]) . ',') : 'Hola,';

  $html = '<!DOCTYPE html><html lang="es"><body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px">
<tr><td align="center">
<table width="100%" style="max-width:520px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">

  <!-- Header -->
  <tr><td style="background:#166534;padding:24px 28px;text-align:center">
    <img src="https://roelplant.cl/assets/images/logo-blanco-266x153.png" alt="Roelplant" style="height:52px">
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:28px 28px 0">
    <p style="margin:0 0 4px;font-size:14px;color:#6b7280">' . $esc($createdAt) . '</p>
    <h1 style="margin:0 0 6px;font-size:22px;color:#111827">¡Compra realizada!</h1>
    <p style="margin:0 0 20px;font-size:14px;color:#374151">' . $saludo . ' Tu pago fue procesado exitosamente.</p>

    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:20px">
      <span style="color:#166534;font-weight:700;font-size:13px">✓ PAGO ACEPTADO</span>
      <span style="color:#374151;font-size:13px;margin-left:12px">Código: <strong>' . $esc($orderCode) . '</strong></span>
    </div>

    <!-- Items -->
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:16px">
      ' . $itemRows . '
    </table>

    <!-- Totales -->
    <table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #e5e7eb;padding-top:4px">
      <tr>
        <td style="padding:8px 8px;color:#6b7280">Subtotal</td>
        <td style="padding:8px 8px;text-align:right;color:#6b7280">' . $clp($subtotal) . '</td>
      </tr>
      ' . $packingRow . '
      ' . $shippingRow . '
      <tr>
        <td style="padding:10px 8px;font-weight:700;font-size:16px;color:#111827;border-top:1px solid #e5e7eb">Total pagado</td>
        <td style="padding:10px 8px;text-align:right;font-weight:700;font-size:16px;color:#111827;border-top:1px solid #e5e7eb">' . $clp($total) . '</td>
      </tr>
    </table>

    ' . ($shMethod !== 'vivero' && $shipping > 0 ? '<p style="margin:12px 0 0;font-size:13px;color:#6b7280">' . $esc($shippingLabel) . '</p>' : '') . '
  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:24px 28px;border-top:1px solid #f3f4f6;text-align:center">
    <p style="margin:0 0 6px;font-size:13px;color:#6b7280">¿Tienes dudas? Escríbenos a <a href="mailto:ventas@roelplant.cl" style="color:#16a34a">ventas@roelplant.cl</a></p>
    <p style="margin:0;font-size:12px;color:#9ca3af">Roelplant · <a href="https://roelplant.cl" style="color:#9ca3af">roelplant.cl</a></p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>';

  $altBody = "¡Compra realizada! Código: {$orderCode}\n\nEstado: PAGO ACEPTADO\n\n";
  foreach ($items as $it) {
    $altBody .= "{$it['name']} ({$it['ref']}) · {$it['qty']} x " . $clp($it['price']) . " = " . $clp($it['line']) . "\n";
  }
  $altBody .= "\nSubtotal: " . $clp($subtotal);
  if ($packing > 0) $altBody .= "\nPacking: " . $clp($packing);
  $altBody .= "\nEnvío: " . ($shipping > 0 ? $clp($shipping) : $shippingLabel);
  $altBody .= "\nTotal pagado: " . $clp($total);
  $altBody .= "\n\nRoelplant · ventas@roelplant.cl";

  $emailUser = getenv('EMAIL_USERNAME') ?: '';
  $emailPass = getenv('EMAIL_PASSWORD') ?: '';

  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = $emailUser;
  $mail->Password   = $emailPass;
  $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->CharSet    = 'UTF-8';

  $mail->setFrom('ventas@roelplant.cl', 'Roelplant');
  $mail->addAddress($toEmail, $toNombre);
  $mail->addBCC('ventas@roelplant.cl', 'Roelplant Ventas');
  $mail->addReplyTo('ventas@roelplant.cl', 'Roelplant');

  $mail->isHTML(true);
  $mail->Subject = "Compra confirmada {$orderCode} – Roelplant";
  $mail->Body    = $html;
  $mail->AltBody = $altBody;

  $mail->send();
  error_log("[webpay_return] Email de confirmación enviado a {$toEmail} para reserva {$idReserva}");
}
