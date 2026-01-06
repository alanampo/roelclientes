<?php
// catalogo_detalle/api/cart/update.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();
$cartId = cart_get_or_create($db, $cid);

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$itemId = (int)($in['item_id'] ?? 0);
$qty    = (int)($in['qty'] ?? 0);
if ($itemId <= 0) bad_request('Ítem inválido');
if ($qty < 0) $qty = 0;

if ($qty === 0) {
  $del = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE id=? AND cart_id=?");
  $del->bind_param('ii', $itemId, $cartId);
  $del->execute();
  json_out(['ok'=>true,'cart'=>cart_snapshot($db, $cartId)]);
}

$up = $db->prepare("UPDATE " . CART_ITEMS_TABLE . " SET qty=? WHERE id=? AND cart_id=?");
$up->bind_param('iii', $qty, $itemId, $cartId);
if (!$up->execute()) {
  json_out(['ok'=>false,'error'=>'No se pudo actualizar'], 500);
}

json_out([
  'ok'=>true,
  'cart'=>cart_snapshot($db, $cartId)
]);
