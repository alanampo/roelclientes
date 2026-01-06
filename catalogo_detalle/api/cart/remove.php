<?php
// catalogo_detalle/api/cart/remove.php
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
if ($itemId <= 0) bad_request('Ítem inválido');

$del = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE id=? AND cart_id=?");
$del->bind_param('ii', $itemId, $cartId);
$del->execute();

json_out([
  'ok'=>true,
  'cart'=>cart_snapshot($db, $cartId)
]);
