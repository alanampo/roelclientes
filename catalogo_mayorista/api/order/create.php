<?php
declare(strict_types=1);

// catalogo_mayorista/api/order/create.php
// Ajuste solicitado (mismo que detalle):
// - Packing ANTES del Subtotal en WhatsApp.
// - Subtotal = productos + packing.
// - Total = subtotal + envío (envío por pagar => 0).
// - Packing NO se agrega a "Notas" del WhatsApp (Notas queda solo cliente).
// - Se agrega nota fija: "Nota envío: el envío es por pagar en sucursal de Starken."
// - En BD se guarda notes_db con packing + nota fija (auditoría).

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$APP = require __DIR__ . '/../../config/app.php';

$cid = require_auth();
$db  = db();

function clp(int $n): string {
  return '$' . number_format($n, 0, '', '.');
}

function order_code(string $prefix): string {
  $date = gmdate('Ymd');
  $rand = strtoupper(bin2hex(random_bytes(3)));
  return $prefix . '-' . $date . '-' . $rand;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) $payload = [];

$shippingCode = trim((string)($payload['shipping_code'] ?? 'por_pagar'));
$notes = trim((string)($payload['notes'] ?? '')); // SOLO notas del cliente (WhatsApp)

// Cliente
$st = $db->prepare('SELECT id, rut, email, nombre, telefono, region, comuna FROM customers WHERE id=? LIMIT 1');
if (!$st) json_out(['ok'=>false,'error'=>'No se pudo leer cliente'], 500);
$st->bind_param('i', $cid);
$st->execute();
$customer = $st->get_result()->fetch_assoc();
$st->close();
if (!$customer) unauthorized('Sesión inválida');

// Carrito mayorista
$cartId = cart_get_or_create($db, $cid);
$cart = cart_snapshot($db, $cartId);
$items = $cart['items'] ?? [];
if (!$items) bad_request('Tu carrito está vacío');

// Validaciones mayoristas
$qtyTotal = 0;
$invalidLines = [];
foreach ($items as $it) {
  $q = (int)($it['qty'] ?? 0);
  $qtyTotal += $q;
  if ($q > 0 && $q < 50) {
    $invalidLines[] = (string)($it['nombre'] ?? '') . ' (' . (string)($it['referencia'] ?? '') . ') = ' . $q;
  }
}
if ($invalidLines) {
  bad_request('Mínimo 50 unidades por especie. Revisa: ' . implode(' · ', $invalidLines));
}
if ($qtyTotal < 200) {
  bad_request('El pedido mayorista debe tener total ≥ 200 unidades. Total actual: ' . $qtyTotal);
}

// Shipping (por pagar)
$shippingLabel = 'Envío por pagar';
if (!empty($APP['SHIPPING_OPTIONS']) && is_array($APP['SHIPPING_OPTIONS'])) {
  foreach ($APP['SHIPPING_OPTIONS'] as $opt) {
    if (($opt['code'] ?? '') === $shippingCode) {
      $lbl = (string)($opt['label'] ?? '');
      $lbl = preg_replace('/\s*\(.*?\)\s*/', ' ', $lbl);
      $shippingLabel = trim($lbl) ?: $shippingLabel;
      break;
    }
  }
}
$shippingCost = 0;

// Totales base (productos)
$subtotalProducts = (int)($cart['total_clp'] ?? 0);

// Packing automático según cantidad total de unidades
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

// Subtotal solicitado = productos + packing
$subtotal = $subtotalProducts + $packingCost;
// Total = subtotal + envío
$total = $subtotal + $shippingCost;

// Nota fija solicitada
$shippingFixedNote = 'Nota envío: el envío es por pagar en sucursal de Starken.';

// Notas para BD (auditoría)
$notesDbParts = [];
if ($notes !== '') $notesDbParts[] = $notes;
$notesDbParts[] = 'Packing: ' . $packingLabel . ' - ' . clp($packingCost);
$notesDbParts[] = $shippingFixedNote;
$notesDb = trim(implode("\n", $notesDbParts));

// WhatsApp
$waPhone = (string)($APP['WHATSAPP_SELLER_E164'] ?? '');
$waPrefix = (string)($APP['WHATSAPP_PREFIX'] ?? 'Pedido Roelplant');
$orderPrefix = (string)($APP['ORDER_PREFIX_MAY'] ?? ($APP['ORDER_PREFIX'] ?? 'RP'));

$db->begin_transaction();
try {
  $orderCode = order_code($orderPrefix);

  // Crear pedido (tabla orders_may)
  $sql = "INSERT INTO orders_may
    (order_code, customer_id, customer_rut, customer_nombre, customer_telefono, customer_region, customer_comuna, customer_email,
     currency, subtotal_clp, shipping_code, shipping_label, shipping_cost_clp, total_clp, notes, status)
    VALUES (?,?,?,?,?,?,?,?, 'CLP', ?,?,?, ?, ?, ?, 'new')";

  $rut = (string)($customer['rut'] ?? '');
  $nom = (string)($customer['nombre'] ?? '');
  $tel = (string)($customer['telefono'] ?? '');
  $reg = (string)($customer['region'] ?? '');
  $com = (string)($customer['comuna'] ?? '');
  $ema = (string)($customer['email'] ?? '');

  $st = $db->prepare($sql);
  if (!$st) throw new RuntimeException('prepare orders_may failed: ' . $db->error);
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
    $subtotal,       // subtotal incluye packing
    $shippingCode,
    $shippingLabel,
    $shippingCost,
    $total,
    $notesDb         // auditoría
  );
  if (!$st->execute()) throw new RuntimeException('execute orders_may failed: ' . $st->error);
  $orderId = (int)$st->insert_id;
  $st->close();

  // Ítems (tabla order_items_may)
  $stItem = $db->prepare(
    'INSERT INTO order_items_may (order_id, id_variedad, referencia, nombre, imagen_url, unit_price_clp, qty, line_total_clp)
     VALUES (?,?,?,?,?,?,?,?)'
  );
  if (!$stItem) throw new RuntimeException('prepare order_items_may failed: ' . $db->error);

  foreach ($items as $it) {
    $idVar = (int)($it['id_variedad'] ?? 0);
    $ref = (string)($it['referencia'] ?? '');
    $name = (string)($it['nombre'] ?? '');
    $img = (string)($it['imagen_url'] ?? '');
    $unit = (int)($it['unit_price_clp'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    $line = (int)($it['line_total_clp'] ?? ($unit * $qty));

    $stItem->bind_param('iisssiii', $orderId, $idVar, $ref, $name, $img, $unit, $qty, $line);
    if (!$stItem->execute()) throw new RuntimeException('execute order_items_may failed: ' . $stItem->error);
  }
  $stItem->close();

  // Convertir carrito y abrir uno nuevo (tablas carts_may/cart_items_may)
  $st = $db->prepare("UPDATE carts_may SET status='converted' WHERE id=?");
  if ($st) { $st->bind_param('i', $cartId); $st->execute(); $st->close(); }

  $st = $db->prepare("INSERT INTO carts_may (customer_id, status) VALUES (?, 'open')");
  if ($st) { $st->bind_param('i', $cid); $st->execute(); $st->close(); }

  $st = $db->prepare("DELETE FROM cart_items_may WHERE cart_id=?");
  if ($st) { $st->bind_param('i', $cartId); $st->execute(); $st->close(); }

  $db->commit();

  // Mensaje WhatsApp (Packing antes del subtotal; subtotal incluye packing)
  $lines = [];
  $lines[] = "🧾 *Nuevo pedido MAYORISTA Roelplant*";
  $lines[] = "Código: *{$orderCode}*";
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
    $lines[] = "• {$qty} x {$name} ({$ref}) = " . clp($line);
  }
  $lines[] = "";
  $lines[] = "Packing: {$packingLabel} - " . clp($packingCost);
  $lines[] = "Subtotal: " . clp($subtotal);
  $lines[] = "Envío: *por pagar* (Starken)";
  $lines[] = "Total: *" . clp($total) . "*";
  $lines[] = $shippingFixedNote;

  // Notas del cliente (sin packing ni nota fija)
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
    'subtotal_clp' => $subtotal,              // incluye packing
    'packing_cost_clp' => $packingCost,
    'packing_label' => $packingLabel,
    'total_clp' => $total,
    'shipping_label' => $shippingLabel,
    'whatsapp_url' => $waUrl,
    'whatsapp_phone' => $waPhone,
    'schema' => 'v2',
  ]);

} catch (Throwable $e) {
  $db->rollback();
  json_out(['ok'=>false,'error'=>'No se pudo crear el pedido mayorista','detail'=>$e->getMessage()], 500);
}
