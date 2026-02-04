<?php
// catalogo_detalle/api/cart/add.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();
$cartId = cart_get_or_create($db, $cid);

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$idVar = (int)($in['id_variedad'] ?? 0);
$ref   = trim((string)($in['referencia'] ?? ''));
$name  = trim((string)($in['nombre'] ?? ''));
$img   = trim((string)($in['imagen_url'] ?? ''));
$price = (int)($in['unit_price_clp'] ?? 0);
$qty   = (int)($in['qty'] ?? 1);

if ($idVar<=0 || $ref==='' || $name==='' || $price<=0) bad_request('Producto inválido');
if ($qty<=0) $qty=1;

$ins = $db->prepare("INSERT INTO " . CART_ITEMS_TABLE . " (cart_id, id_variedad, referencia, nombre, imagen_url, unit_price_clp, qty)
VALUES (?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
  qty = qty + VALUES(qty),
  unit_price_clp = VALUES(unit_price_clp),
  nombre = VALUES(nombre),
  referencia = VALUES(referencia),
  imagen_url = VALUES(imagen_url)");
$ins->bind_param('iisssii', $cartId, $idVar, $ref, $name, $img, $price, $qty);
if (!$ins->execute()) {
  json_out(['ok'=>false,'error'=>'No se pudo agregar'], 500);
}

json_out([
  'ok'=>true,
  'cart'=>cart_snapshot($db, $cartId)
]);
