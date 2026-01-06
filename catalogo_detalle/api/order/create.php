<?php
declare(strict_types=1);

// catalogo_detalle/api/order/create.php
// Crea un pedido desde el carrito abierto y retorna un link de WhatsApp con el detalle.
// Envío: por pagar (shipping_cost_clp = 0).
// Compatible con esquema legacy (orders.user_id/total_amount) y esquema v2 (orders.order_code/total_clp).

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$APP = require __DIR__ . '/../../config/app.php';

$cid = require_auth();
$db  = db();

function _clp(int $n): string {
  return '$' . number_format($n, 0, '', '.');
}

function _table_has_column(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'";
  $res = $db->query($sql);
  if (!$res) return false;
  return (bool)$res->fetch_assoc();
}

function _order_code(string $prefix): string {
  $date = gmdate('Ymd');
  $rand = strtoupper(bin2hex(random_bytes(3)));
  return $prefix . '-' . $date . '-' . $rand;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) $payload = [];

$shippingCode = trim((string)($payload['shipping_code'] ?? 'retiro'));
$notes = trim((string)($payload['notes'] ?? ''));

// Cliente (tabla unificada clientes)
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
$cart = cart_snapshot($db, $cartId);
$items = $cart['items'] ?? [];
if (!$items) bad_request('Tu carrito está vacío');

// Shipping (por pagar)
$shippingLabel = 'Envío por pagar';
if (!empty($APP['SHIPPING_OPTIONS']) && is_array($APP['SHIPPING_OPTIONS'])) {
  foreach ($APP['SHIPPING_OPTIONS'] as $opt) {
    if (($opt['code'] ?? '') === $shippingCode) {
      $lbl = (string)($opt['label'] ?? '');
      $lbl = preg_replace('/\s*\(.*?\)\s*/', ' ', $lbl); // saca "(estimado)"
      $shippingLabel = trim($lbl) ?: $shippingLabel;
      break;
    }
  }
}
$shippingCost = 0;

// Totales
$subtotal = (int)($cart['total_clp'] ?? 0);

// Packing automático según cantidad total de unidades en el carrito.
// Reglas:
//  1-50   => caja chica  $2.500
// 51-100  => caja mediana $4.000
// 101+    => $4.500 por cada 100 unidades (redondeo hacia arriba)
$qtyTotal = 0;
foreach (($cart['items'] ?? []) as $it) { $qtyTotal += (int)($it['qty'] ?? 0); }

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

$total = $subtotal + $shippingCost + $packingCost;


// Adjuntar packing a notas (para auditoría y WhatsApp)
$notesPack = 'Packing: ' . $packingLabel . ' - $' . number_format($packingCost, 0, ',', '.');
$notes = trim($notes);
$notes = $notes !== '' ? ($notes . "\n" . $notesPack) : $notesPack;

// Config WhatsApp
$waPhone = (string)($APP['WHATSAPP_SELLER_E164'] ?? '');
$waPrefix = (string)($APP['WHATSAPP_PREFIX'] ?? 'Pedido Roelplant');
$orderPrefix = (string)($APP['ORDER_PREFIX'] ?? 'RP');

// Detectar esquema
$hasOrderCode = _table_has_column($db, ORDERS_TABLE, 'order_code') && _table_has_column($db, ORDERS_TABLE, 'customer_id');

$db->begin_transaction();
try {
  $orderId = 0;
  $orderCode = '';

  if ($hasOrderCode) {
    $orderCode = _order_code($orderPrefix);

    $sql = "INSERT INTO " . ORDERS_TABLE . "
      (order_code, customer_id, customer_rut, customer_nombre, customer_telefono, customer_region, customer_comuna, customer_email,
       currency, subtotal_clp, shipping_code, shipping_label, shipping_cost_clp, total_clp, notes, status)
      VALUES (?,?,?,?,?,?,?,?, 'CLP', ?,?,?, ?, ?, ?, 'new')";
    $st = $db->prepare($sql);
    if (!$st) throw new Exception('prepare orders v2 failed: '.$db->error);

    $rut = (string)($customer['rut'] ?? '');
    $nom = (string)($customer['nombre'] ?? '');
    $tel = (string)($customer['telefono'] ?? '');
    $reg = (string)($customer['region'] ?? '');
    $com = (string)($customer['comuna'] ?? '');
    $ema = (string)($customer['email'] ?? '');

    $st->bind_param(
      'sissssssissiis',
      $orderCode,
      $cid,
      $rut,
      $nom,
      $tel,
      $reg,
      $com,
      $ema,
      $subtotal,
      $shippingCode,
      $shippingLabel,
      $shippingCost,
      $total,
      $notes
    );
    if (!$st->execute()) throw new Exception('execute orders v2 failed: '.$st->error);
    $orderId = (int)$st->insert_id;
    $st->close();

    $stItem = $db->prepare("INSERT INTO " . ORDER_ITEMS_TABLE . " (order_id, id_variedad, referencia, nombre, imagen_url, unit_price_clp, qty, line_total_clp)
                            VALUES (?,?,?,?,?,?,?,?)");
    if (!$stItem) throw new Exception('prepare order_items v2 failed: '.$db->error);

    foreach ($items as $it) {
      $idVar = (int)($it['id_variedad'] ?? 0);
      $ref = (string)($it['referencia'] ?? '');
      $name = (string)($it['nombre'] ?? '');
      $img = (string)($it['imagen_url'] ?? '');
      $unit = (int)($it['unit_price_clp'] ?? 0);
      $qty = (int)($it['qty'] ?? 0);
      $line = (int)($it['line_total_clp'] ?? ($unit * $qty));
      $stItem->bind_param('iisssiii', $orderId, $idVar, $ref, $name, $img, $unit, $qty, $line);
      if (!$stItem->execute()) throw new Exception('execute order_items v2 failed: '.$stItem->error);
    }
    $stItem->close();
  } else {
    // Legacy
    $sql = "INSERT INTO " . ORDERS_TABLE . " (user_id, status, shipping_method, shipping_label, shipping_amount, subtotal_amount, total_amount, notes)
            VALUES (?, 'created', 'manual', ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    if (!$st) throw new Exception('prepare orders legacy failed: '.$db->error);

    $st->bind_param('isiiis', $cid, $shippingLabel, $shippingCost, $subtotal, $total, $notes);
    if (!$st->execute()) throw new Exception('execute orders legacy failed: '.$st->error);
    $orderId = (int)$st->insert_id;
    $st->close();

    $stItem = $db->prepare("INSERT INTO " . ORDER_ITEMS_TABLE . " (order_id, product_ref, product_name, unit_price, qty, line_total, image_url)
                            VALUES (?,?,?,?,?,?,?)");
    if (!$stItem) throw new Exception('prepare order_items legacy failed: '.$db->error);

    foreach ($items as $it) {
      $ref = (string)($it['referencia'] ?? '');
      $name = (string)($it['nombre'] ?? '');
      $img = (string)($it['imagen_url'] ?? '');
      $unit = (int)($it['unit_price_clp'] ?? 0);
      $qty = (int)($it['qty'] ?? 0);
      $line = (int)($it['line_total_clp'] ?? ($unit * $qty));
      $stItem->bind_param('issiiis', $orderId, $ref, $name, $unit, $qty, $line, $img);
      if (!$stItem->execute()) throw new Exception('execute order_items legacy failed: '.$stItem->error);
    }
    $stItem->close();
  }

  // Convertir carrito y crear uno nuevo
  $st = $db->prepare("UPDATE " . CART_TABLE . " SET status='converted' WHERE id=?");
  if ($st) { $st->bind_param('i', $cartId); $st->execute(); $st->close(); }

  $st = $db->prepare("INSERT INTO " . CART_TABLE . " (id_cliente, status) VALUES (?, 'open')");
  if ($st) { $st->bind_param('i', $cid); $st->execute(); $st->close(); }

  $st = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE cart_id=?");
  if ($st) { $st->bind_param('i', $cartId); $st->execute(); $st->close(); }

  $db->commit();

  // Mensaje WhatsApp
  $lines = [];
  $lines[] = "🧾 *Nuevo pedido Roelplant*";
  if ($orderCode !== '') $lines[] = "Código: *{$orderCode}*";
  $lines[] = "Pedido ID: *{$orderId}*";
  $lines[] = "";
  $lines[] = "*Cliente*";
  $lines[] = (string)$customer['nombre'] . " (" . (string)$customer['rut'] . ")";
  $lines[] = "Tel: " . (string)$customer['telefono'];
  $lines[] = (string)$customer['comuna'] . ", " . (string)$customer['region'];
  if (!empty($customer['email'])) $lines[] = "Email: " . (string)$customer['email'];
  $lines[] = "";
  $lines[] = "*Detalle*";
  foreach ($items as $it) {
    $qty = (int)($it['qty'] ?? 0);
    $ref = (string)($it['referencia'] ?? '');
    $name = (string)($it['nombre'] ?? '');
    $line = (int)($it['line_total_clp'] ?? 0);
    $lines[] = "• {$qty} x {$name} ({$ref}) = " . _clp($line);
  }
  $lines[] = "";
  $lines[] = "Subtotal: " . _clp($subtotal);
  $lines[] = "Envío: *por pagar* ({$shippingLabel})";
  $lines[] = "Total: *" . _clp($total) . "*";
  if ($notes !== '') {
    $lines[] = "";
    $lines[] = "*Notas:* " . $notes;
  }
  $lines[] = "";
  $lines[] = $waPrefix;

  $msg = implode("\n", $lines);
  $waUrl = $waPhone ? ("https://wa.me/" . $waPhone . "?text=" . rawurlencode($msg)) : "";

  json_out([
    'ok' => true,
    'order_id' => $orderId,
    'order_code' => $orderCode,
    'subtotal_clp' => $subtotal,
    'packing_cost_clp' => $packingCost,
    'packing_label' => $packingLabel,
    'total_clp' => $total,
    'shipping_label' => $shippingLabel,
    'whatsapp_url' => $waUrl,
    'whatsapp_phone' => $waPhone,
    'schema' => $hasOrderCode ? 'v2' : 'legacy',
  ]);
} catch (Throwable $e) {
  $db->rollback();
  json_out(['ok'=>false,'error'=>'Error creando pedido: ' . $e->getMessage()], 500);
}