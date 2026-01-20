<?php
// catalogo_detalle/api/cart/get.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db = db();
$cartId = cart_get_or_create($db, $cid);

json_out([
  'ok'=>true,
  'cart'=>cart_snapshot($db, $cartId)
]);
