<?php
declare(strict_types=1);

// catalogo_detalle_webpay/api/config/packing_prices.php
// Retorna los precios de packing desde la BD (con IVA incluido)

require __DIR__ . '/../_bootstrap.php';

$db = db();

// Obtener precios (ya incluyen IVA)
$prices = get_packing_prices($db);
$ivaPercent = get_iva_percentage();

json_out([
  'ok' => true,
  'prices' => $prices,
  'iva_percentage' => $ivaPercent
]);
