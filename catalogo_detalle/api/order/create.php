<?php
declare(strict_types=1);

// catalogo_detalle/api/order/create.php
// Crea una reserva desde el carrito y retorna un link de WhatsApp con el detalle.
// Inserta en reservas + reservas_productos con estado=108 (ORDEN DE WHATSAPP).

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$APP = require __DIR__ . '/../../config/app.php';

$cid = require_auth();
$db  = db();

function _clp(int $n): string {
  return '$' . number_format($n, 0, '', '.');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) $payload = [];

$shippingCode            = trim((string)($payload['shipping_code'] ?? 'retiro'));
$notes                   = trim((string)($payload['notes'] ?? ''));
$shippingMethod          = trim((string)($payload['shipping_method'] ?? 'domicilio'));
$shippingAddress         = trim((string)($payload['shipping_address'] ?? ''));
$shippingCommune         = trim((string)($payload['shipping_commune'] ?? ''));
$shippingCostFromPayload = (int)($payload['shipping_cost'] ?? 0);
$shippingAgencyCodeDls   = (int)($payload['shipping_agency_code_dls'] ?? 0);
$shippingAgencyName      = trim((string)($payload['shipping_agency_name'] ?? ''));
$shippingAgencyAddress   = trim((string)($payload['shipping_agency_address'] ?? ''));
$shippingAgencyPhone     = trim((string)($payload['shipping_agency_phone'] ?? ''));

// Cliente
$q = "SELECT id_cliente as id, rut, mail as email, nombre, telefono, region, comuna FROM clientes WHERE id_cliente=? LIMIT 1";
$st = $db->prepare($q);
if (!$st) json_out(['ok'=>false,'error'=>'No se pudo leer cliente'], 500);
$st->bind_param('i', $cid);
$st->execute();
$customer = $st->get_result()->fetch_assoc();
$st->close();
if (!$customer) unauthorized('Sesión inválida');

// Carrito
$cartId = cart_get_or_create($db, $cid);
$cart   = cart_snapshot($db, $cartId);
$items  = $cart['items'] ?? [];
if (!$items) bad_request('Tu carrito está vacío');

// Conectar a BD de producción (donde están reservas / reservas_productos)
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
    $hostStock  = $host;
    $hostUser   = $user;
    $hostPass   = $password;
    $hostDbname = $dbname;
    $found = true;
    break;
  }
}
if (!$found) json_out(['ok'=>false,'error'=>'No se encontró configuración de BD de producción'], 500);

$dbStock = @mysqli_connect($hostStock, $hostUser, $hostPass, $hostDbname);
if (!$dbStock) json_out(['ok'=>false,'error'=>'Error conexión BD producción'], 500);
mysqli_set_charset($dbStock, 'utf8');

// Config WhatsApp
$waPhone    = (string)($APP['WHATSAPP_SELLER_E164'] ?? '');
$waPrefix   = (string)($APP['WHATSAPP_PREFIX'] ?? 'Pedido Roelplant');
$orderPrefix = (string)($APP['ORDER_PREFIX'] ?? 'RP');

// Totales
$subtotal   = (int)($cart['total_clp'] ?? 0);
$packingInfo = calculate_packing($dbStock, $items);
$packingCost = $packingInfo['cost'];
$packingLabel = $packingInfo['label'];
$shippingCost = 0; // El envío es por pagar
$total = $subtotal + $shippingCost + $packingCost;

// Observaciones
$reservaObs = $notes;
if ($packingLabel) {
  $reservaObs .= ($reservaObs ? ' | ' : '') . "Packing: {$packingLabel}";
}

// Label de envío
$shippingLabel = 'Envío por pagar';
if (!empty($APP['SHIPPING_OPTIONS']) && is_array($APP['SHIPPING_OPTIONS'])) {
  foreach ($APP['SHIPPING_OPTIONS'] as $opt) {
    if (($opt['code'] ?? '') === $shippingCode) {
      $lbl = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', (string)($opt['label'] ?? '')));
      if ($lbl !== '') { $shippingLabel = $lbl; }
      break;
    }
  }
}

// Buscar usuario "catalogo" para asignar la reserva
$stUser = $db->prepare("SELECT id FROM usuarios WHERE nombre='catalogo' LIMIT 1");
if ($stUser) {
  $stUser->execute();
  $rowUser = $stUser->get_result()->fetch_assoc();
  $stUser->close();
  $idUsuario = $rowUser ? (int)$rowUser['id'] : 1;
} else {
  $idUsuario = 1;
}

$dbStock->begin_transaction();
try {
  // Insertar reserva con payment_method='whatsapp'
  $obsEsc            = mysqli_real_escape_string($dbStock, $reservaObs);
  $shippingMethodEsc = mysqli_real_escape_string($dbStock, $shippingMethod);
  $shippingAddressEsc = mysqli_real_escape_string($dbStock, $shippingAddress);
  $shippingCommuneEsc = mysqli_real_escape_string($dbStock, $shippingCommune);
  $shippingAgencyNameEsc    = mysqli_real_escape_string($dbStock, $shippingAgencyName);
  $shippingAgencyAddressEsc = mysqli_real_escape_string($dbStock, $shippingAgencyAddress);

  $queryReserva = "INSERT INTO reservas
    (fecha, id_cliente, observaciones, id_usuario,
     subtotal_clp, packing_cost_clp, shipping_cost_clp, total_clp, paid_clp,
     payment_status, payment_method,
     shipping_method, shipping_address, shipping_commune,
     shipping_agency_code_dls, shipping_agency_name, shipping_agency_address,
     cart_id, created_at)
    VALUES (
      NOW(), {$cid}, '{$obsEsc}', {$idUsuario},
      {$subtotal}, {$packingCost}, {$shippingCost}, {$total}, 0,
      'pending', 'whatsapp',
      '{$shippingMethodEsc}', '{$shippingAddressEsc}', '{$shippingCommuneEsc}',
      {$shippingAgencyCodeDls}, '{$shippingAgencyNameEsc}', '{$shippingAgencyAddressEsc}',
      {$cartId}, NOW()
    )";

  if (!mysqli_query($dbStock, $queryReserva)) {
    throw new RuntimeException('Execute reserva failed: ' . mysqli_error($dbStock));
  }
  $idReserva = (int)mysqli_insert_id($dbStock);

  // Insertar productos con estado=108 (ORDEN DE WHATSAPP)
  $stProd = $dbStock->prepare(
    "INSERT INTO reservas_productos (id_reserva, id_variedad, cantidad, comentario, estado, origen, id_usuario)
     VALUES (?, ?, ?, '', 108, 'CATALOGO DETALLE - WHATSAPP', ?)"
  );
  if (!$stProd) throw new RuntimeException('Prepare productos failed: ' . $dbStock->error);

  foreach ($items as $it) {
    $idVar = (int)($it['id_variedad'] ?? 0);
    $qty   = (int)($it['qty'] ?? 0);
    if ($idVar <= 0 || $qty <= 0) continue;
    $stProd->bind_param('iiii', $idReserva, $idVar, $qty, $idUsuario);
    if (!$stProd->execute()) {
      throw new RuntimeException('Execute productos failed: ' . $stProd->error);
    }
  }
  $stProd->close();

  // Generar código de orden legible
  $orderCode = $orderPrefix . '-' . str_pad((string)$idReserva, 6, '0', STR_PAD_LEFT);

  // Vaciar carrito
  $st = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE cart_id=?");
  if ($st) { $st->bind_param('i', $cartId); $st->execute(); $st->close(); }

  $dbStock->commit();

  // Mensaje WhatsApp
  $lines = [];
  $lines[] = "🧾 *Nuevo pedido Roelplant*";
  $lines[] = "Código: *{$orderCode}*";
  $lines[] = "Pedido ID: *{$idReserva}*";
  $lines[] = "";
  $lines[] = "*Cliente*";
  $lines[] = (string)$customer['nombre'] . " (" . (string)$customer['rut'] . ")";
  $lines[] = "Tel: " . (string)$customer['telefono'];
  $lines[] = (string)$customer['comuna'] . ", " . (string)$customer['region'];
  if (!empty($customer['email'])) $lines[] = "Email: " . (string)$customer['email'];
  $lines[] = "";
  $lines[] = "*Detalle*";
  foreach ($items as $it) {
    $qty  = (int)($it['qty'] ?? 0);
    $ref  = (string)($it['referencia'] ?? '');
    $name = (string)($it['nombre'] ?? '');
    $line = (int)($it['line_total_clp'] ?? 0);
    $lines[] = "• {$qty} x {$name} ({$ref}) = " . _clp($line);
  }
  $lines[] = "";
  $lines[] = "Subtotal: " . _clp($subtotal);
  $lines[] = "Packing: " . _clp($packingCost) . " ({$packingLabel})";

  if ($shippingMethod === 'domicilio' && !empty($shippingAddress)) {
    $lines[] = "*Método de Entrega: Envío a Domicilio*";
    $lines[] = "Dirección: {$shippingAddress}";
    $lines[] = "Comuna: {$shippingCommune}";
    $lines[] = $shippingCostFromPayload > 0 ? "Costo envío: " . _clp($shippingCostFromPayload) : "Costo envío: Por cotizar";
  } elseif ($shippingMethod === 'agencia' && !empty($shippingAgencyName)) {
    $lines[] = "*Método de Entrega: Retiro en Sucursal Starken*";
    $lines[] = "Sucursal: {$shippingAgencyName}";
    if (!empty($shippingAgencyAddress)) $lines[] = "Dirección: {$shippingAgencyAddress}";
    if (!empty($shippingAgencyPhone))   $lines[] = "Teléfono: {$shippingAgencyPhone}";
    $lines[] = $shippingCostFromPayload > 0 ? "Costo envío: " . _clp($shippingCostFromPayload) : "Costo envío: Por cobrar al retiro";
  } elseif ($shippingMethod === 'vivero') {
    $lines[] = "*Método de Entrega: Retiro en Vivero (Gratis)*";
  }

  $lines[] = "Envío: *por pagar* ({$shippingLabel})";
  $lines[] = "Total: *" . _clp($total) . "*";
  if ($notes !== '') {
    $lines[] = "";
    $lines[] = "*Notas:* " . $notes;
  }
  $lines[] = "";
  $lines[] = $waPrefix;

  $msg   = implode("\n", $lines);
  $waUrl = $waPhone ? ("https://wa.me/" . $waPhone . "?text=" . rawurlencode($msg)) : "";

  json_out([
    'ok'              => true,
    'order_id'        => $idReserva,
    'order_code'      => $orderCode,
    'subtotal_clp'    => $subtotal,
    'packing_cost_clp'=> $packingCost,
    'packing_label'   => $packingLabel,
    'total_clp'       => $total,
    'shipping_label'  => $shippingLabel,
    'whatsapp_url'    => $waUrl,
    'whatsapp_phone'  => $waPhone,
    'schema'          => 'reservas',
  ]);
} catch (Throwable $e) {
  $dbStock->rollback();
  json_out(['ok'=>false,'error'=>'Error creando pedido: ' . $e->getMessage()], 500);
}
