<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$app = require __DIR__ . '/../../config/app.php';

json_out([
  'ok' => true,
  'shipping' => $app['SHIPPING_OPTIONS'] ?? [],
  'whatsapp_seller_e164' => $app['WHATSAPP_SELLER_E164'] ?? '',
]);
