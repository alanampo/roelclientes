<?php
/**
 * catalogo_detalle/api/payment/webpay_create.php
 * Crea una reserva y luego una transacción de pago con Webpay
 * POST /api/payment/webpay_create
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../services/WebpayService.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();
$APP = require __DIR__ . '/../../config/app.php';

// Detectar protocolo y host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); // Ej: /catalogo_detalle/api/payment
$basePath = preg_replace('#/api/payment$#', '', $scriptPath); // Remover /api/payment
$baseUrl = rtrim($basePath, '/') . '/'; // Ej: /catalogo_detalle/
$absoluteBaseUrl = "{$protocol}://{$host}{$baseUrl}"; // Ej: http://localhost/catalogo_detalle/

// Obtener carrito
$cartId = cart_get_or_create($db, $cid);
$cart = cart_snapshot($db, $cartId);
$items = $cart['items'] ?? [];

if (!$items) {
  bad_request('Tu carrito está vacío');
}

// Obtener payload con datos de envío
$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$shippingMethod = trim((string)($payload['shipping_method'] ?? ''));
$shippingCost = (int)($payload['shipping_cost'] ?? 0);
$shippingAddress = trim((string)($payload['shipping_address'] ?? ''));
$shippingCommune = trim((string)($payload['shipping_commune'] ?? ''));
$shippingAgencyCodeDls = (int)($payload['shipping_agency_code_dls'] ?? 0);
$shippingAgencyName = trim((string)($payload['shipping_agency_name'] ?? ''));
$shippingAgencyAddress = trim((string)($payload['shipping_agency_address'] ?? ''));
$notes = trim((string)($payload['notes'] ?? ''));

// Validar método de envío
if (empty($shippingMethod)) {
  bad_request('Método de envío es requerido');
}

// Conectar a BD de producción para validar stock y crear reserva
$conectaPaths = [
  __DIR__ . '/../../class_lib/class_conecta_mysql.php',
  __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
  __DIR__ . '/../../../../class_lib/class_conecta_mysql.php',
];
$found = false;
$hostStock = $hostUser = $hostPass = $hostDbname = null;
foreach ($conectaPaths as $p) {
  if (is_file($p)) {
    require $p;
    $hostStock = $host;
    $hostUser = $user;
    $hostPass = $password;
    $hostDbname = $dbname;
    $found = true;
    break;
  }
}

if (!$found) {
  json_out(['ok'=>false,'error'=>'No se encontró configuración de BD de producción'], 500);
}

$dbStock = @mysqli_connect($hostStock, $hostUser, $hostPass, $hostDbname);
if (!$dbStock) {
  json_out(['ok'=>false,'error'=>'Error conexión BD producción'], 500);
}
mysqli_set_charset($dbStock, 'utf8');

try {
  // Validar stock para cada producto ANTES de crear la reserva
  $products_detailed = [];
  foreach ($items as $item) {
    $id_variedad = (int)($item['id_variedad'] ?? 0);
    $qty = (int)($item['qty'] ?? 0);

    if ($id_variedad <= 0 || $qty <= 0) {
      throw new RuntimeException('Producto o cantidad inválida');
    }

    // Consultar stock disponible desde BD de producción
    $queryStock = "
      SELECT
        (SUM(s.cantidad) -
         IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r WHERE r.id_variedad = ? AND r.estado >= 0), 0)
        ) as disponible
      FROM stock_productos s
      INNER JOIN articulospedidos ap ON s.id_artpedido = ap.id
      WHERE ap.id_variedad = ? AND ap.estado >= 8
    ";

    $stStock = $dbStock->prepare($queryStock);
    if (!$stStock) {
      throw new RuntimeException('Error validando stock: ' . $dbStock->error);
    }
    $stStock->bind_param('ii', $id_variedad, $id_variedad);
    $stStock->execute();
    $resStock = $stStock->get_result();
    $stockData = $resStock->fetch_assoc();
    $stStock->close();

    $disponible = (int)($stockData['disponible'] ?? 0);

    if ($disponible < $qty) {
      throw new RuntimeException("Stock insuficiente para producto ID {$id_variedad}. Solicitado: {$qty}, Disponible: {$disponible}");
    }

    // Guardar detalles para inserción posterior
    $products_detailed[] = [
      'id_variedad' => $id_variedad,
      'qty' => $qty,
      'nombre' => (string)($item['nombre'] ?? ''),
      'referencia' => (string)($item['referencia'] ?? ''),
      'unit_price' => (int)($item['unit_price_clp'] ?? 0),
      'comentario' => ''
    ];
  }

  // Buscar usuario vendedor "catalogo" para asignar a la reserva
  $queryUser = "SELECT id FROM usuarios WHERE nombre = 'catalogo' LIMIT 1";
  $stUser = $db->prepare($queryUser);
  if (!$stUser) throw new RuntimeException('Prepare error: ' . $db->error);
  $stUser->execute();
  $rowUser = $stUser->get_result()->fetch_assoc();
  $stUser->close();

  // Fallback a admin (id=1) si no existe usuario "catalogo"
  $idUsuario = $rowUser ? (int)$rowUser['id'] : 1;

  // Calcular totales
  $subtotal = (int)($cart['total_clp'] ?? 0);
  $qtyTotal = array_reduce($items, fn($acc, $it) => $acc + (int)($it['qty'] ?? 0), 0);

  // Packing
  $packingCost = 0;
  $packingLabel = 'sin packing';
  if ($qtyTotal > 0 && $qtyTotal <= 50) {
    $packingCost = 2500;
    $packingLabel = 'caja chica (1-50)';
  } elseif ($qtyTotal <= 100) {
    $packingCost = 4000;
    $packingLabel = 'caja mediana (51-100)';
  } else {
    $packs = (int)ceil($qtyTotal / 100);
    $packingCost = 4500 * $packs;
    $packingLabel = 'caja grande x'.$packs.' (cada 100 unid.)';
  }

  $total = $subtotal + $packingCost + $shippingCost;

  // Construir observaciones
  $reservaObs = $notes;
  if ($packingLabel) {
    $reservaObs .= ($reservaObs ? ' | ' : '') . "Packing: {$packingLabel}";
  }
  if ($shippingMethod) {
    $reservaObs .= ($reservaObs ? ' | ' : '') . "Envío: {$shippingMethod}";
  }

  // Iniciar transacción en BD de producción
  $dbStock->begin_transaction();

  // Escapar todos los valores para MySQL
  $reservaObsEsc = mysqli_real_escape_string($dbStock, $reservaObs);
  $shippingMethodEsc = mysqli_real_escape_string($dbStock, $shippingMethod);
  $shippingAddressEsc = mysqli_real_escape_string($dbStock, $shippingAddress);
  $shippingCommuneEsc = mysqli_real_escape_string($dbStock, $shippingCommune);
  $shippingAgencyNameEsc = mysqli_real_escape_string($dbStock, $shippingAgencyName);
  $shippingAgencyAddressEsc = mysqli_real_escape_string($dbStock, $shippingAgencyAddress);

  // Crear reserva en BD de producción con payment_status='pending'
  $queryReserva = "INSERT INTO reservas
    (fecha, id_cliente, observaciones, id_usuario,
     subtotal_clp, packing_cost_clp, shipping_cost_clp, total_clp, paid_clp,
     payment_status, payment_method,
     shipping_method, shipping_address, shipping_commune,
     shipping_agency_code_dls, shipping_agency_name, shipping_agency_address,
     cart_id, created_at)
    VALUES (
      NOW(),
      {$cid},
      '{$reservaObsEsc}',
      {$idUsuario},
      {$subtotal},
      {$packingCost},
      {$shippingCost},
      {$total},
      0,
      'pending',
      'webpay',
      '{$shippingMethodEsc}',
      '{$shippingAddressEsc}',
      '{$shippingCommuneEsc}',
      {$shippingAgencyCodeDls},
      '{$shippingAgencyNameEsc}',
      '{$shippingAgencyAddressEsc}',
      {$cartId},
      NOW()
    )";

  if (!mysqli_query($dbStock, $queryReserva)) {
    throw new RuntimeException('Execute reserva failed: ' . mysqli_error($dbStock));
  }
  $idReserva = (int)mysqli_insert_id($dbStock);

  // Insertar productos de la reserva
  $queryProd = "INSERT INTO reservas_productos (id_reserva, id_variedad, cantidad, comentario, estado, origen, id_usuario)
                VALUES (?, ?, ?, ?, 0, 'CATALOGO DETALLE - WEBPAY', ?)";
  $stProd = $dbStock->prepare($queryProd);
  if (!$stProd) throw new RuntimeException('Prepare productos failed: ' . $dbStock->error);

  foreach ($products_detailed as $prod) {
    $stProd->bind_param('iisis', $idReserva, $prod['id_variedad'], $prod['qty'], $prod['comentario'], $idUsuario);
    if (!$stProd->execute()) {
      throw new RuntimeException('Execute productos failed: ' . $stProd->error);
    }
  }
  $stProd->close();

  // Actualizar estado=100 (pago pendiente/aceptado) en todos los productos de la reserva
  $queryUpdateEstado = "UPDATE reservas_productos SET estado=100 WHERE id_reserva=?";
  $stUpdateEstado = $dbStock->prepare($queryUpdateEstado);
  if (!$stUpdateEstado) throw new RuntimeException('Prepare update estado failed: ' . $dbStock->error);
  $stUpdateEstado->bind_param('i', $idReserva);
  if (!$stUpdateEstado->execute()) {
    throw new RuntimeException('Execute update estado failed: ' . $stUpdateEstado->error);
  }
  $stUpdateEstado->close();

  // Commit transacción en BD de producción
  $dbStock->commit();
  mysqli_close($dbStock);

  // Generar order unique
  $buyOrder = strtoupper('RP' . $idReserva . '-' . uniqid());
  $sessionId = session_id() ?: uniqid();

  // Crear tabla de transacciones si no existe
  $db->query("CREATE TABLE IF NOT EXISTS webpay_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_cliente INT NOT NULL,
    id_reserva INT DEFAULT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    buy_order VARCHAR(64) NOT NULL,
    amount INT NOT NULL,
    status VARCHAR(32) DEFAULT 'INITIATED',
    authorized BOOLEAN DEFAULT FALSE,
    authorization_code VARCHAR(6),
    card_number VARCHAR(19),
    vci VARCHAR(10),
    response_code INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    KEY idx_cliente (id_cliente),
    KEY idx_reserva (id_reserva),
    KEY idx_token (token),
    KEY idx_buy_order (buy_order)
  )");

  // Inicializar servicio Webpay
  $webpay = new WebpayService(
    $APP['WEBPAY_ENVIRONMENT'],
    $APP['WEBPAY_COMMERCE_CODE'],
    $APP['WEBPAY_API_KEY']
  );

  // URL de retorno (DEBE ser absoluta con protocolo)
  $returnUrl = $absoluteBaseUrl . 'api/payment/webpay_return.php';

  // Crear transacción en Webpay
  $result = $webpay->createTransaction($total, $buyOrder, $sessionId, $returnUrl);

  if (!$result['ok']) {
    // Rollback: marcar reserva como failed
    $dbStock = mysqli_connect($hostStock, $hostUser, $hostPass, $hostDbname);
    if ($dbStock) {
      $dbStock->query("UPDATE reservas SET payment_status='failed' WHERE id={$idReserva}");
      mysqli_close($dbStock);
    }
    json_out(['ok' => false, 'error' => $result['error'], '_debug' => $result], 400);
  }

  $token = $result['token'];

  // Validar que tenemos token válido
  if (empty($token) || strlen($token) < 20) {
    json_out(['ok' => false, 'error' => 'Token inválido de Webpay', '_debug' => ['token' => $token, 'result' => $result]], 400);
  }

  // Guardar transacción en BD con referencia a reserva
  $st = $db->prepare("INSERT INTO webpay_transactions (id_cliente, id_reserva, token, buy_order, amount, status)
                      VALUES (?, ?, ?, ?, ?, 'INITIATED')");
  if ($st) {
    $st->bind_param('iissi', $cid, $idReserva, $token, $buyOrder, $total);
    $st->execute();
    $st->close();
  }

  // Actualizar reserva con el ID de transacción
  $dbStock = mysqli_connect($hostStock, $hostUser, $hostPass, $hostDbname);
  if ($dbStock) {
    $transactionId = (int)$db->insert_id;
    $dbStock->query("UPDATE reservas SET webpay_transaction_id={$transactionId} WHERE id={$idReserva}");
    mysqli_close($dbStock);
  }

  // Agregar token como parámetro a la URL de Webpay
  $redirectUrl = $result['url'] . '?token=' . urlencode($token);

  json_out([
    'ok' => true,
    'token' => $token,
    'redirect_url' => $redirectUrl,
    'buy_order' => $buyOrder,
    'amount' => $total,
    'reservation_id' => $idReserva
  ]);

} catch (Throwable $e) {
  if ($dbStock) {
    $dbStock->rollback();
    mysqli_close($dbStock);
  }
  json_out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
