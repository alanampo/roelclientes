<?php
declare(strict_types=1);
// catalogo_detalle/api/order/detail.php
// Obtiene detalle de una reserva desde la tabla reservas (BD producción)
require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) bad_request('ID inválido');

// Conectar a BD de producción para obtener reserva
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

// Consultar reserva
$sql = "SELECT
          id,
          fecha,
          observaciones,
          subtotal_clp,
          packing_cost_clp,
          shipping_cost_clp,
          total_clp,
          paid_clp,
          payment_status,
          payment_method,
          shipping_method,
          shipping_address,
          shipping_commune,
          shipping_agency_code_dls,
          shipping_agency_name,
          shipping_agency_address,
          cart_id,
          created_at
        FROM reservas
        WHERE id = ? AND id_cliente = ?
        LIMIT 1";

$st = $dbStock->prepare($sql);
if (!$st) {
  mysqli_close($dbStock);
  json_out(['ok'=>false,'error'=>'No se pudo preparar consulta: ' . $dbStock->error],500);
}

$st->bind_param('ii', $id, $cid);
$st->execute();
$o = $st->get_result()->fetch_assoc();
$st->close();

if (!$o) {
  mysqli_close($dbStock);
  unauthorized('Reserva no encontrada');
}

// Determinar label de estado de pago y estado del envío
$paymentStatus = (string)($o['payment_status'] ?? 'pending');
$paymentLabel = [
  'pending' => 'Pendiente de pago',
  'paid' => 'Pagado',
  'failed' => 'Pago fallido',
  'refunded' => 'Reembolsado'
][$paymentStatus] ?? $paymentStatus;

// Verificar estado de los productos de la reserva
$stateQuery = "SELECT DISTINCT estado FROM reservas_productos WHERE id_reserva = ?";
$stState = $dbStock->prepare($stateQuery);
$finalStatus = null;

if ($stState) {
  $stState->bind_param('i', $id);
  $stState->execute();
  $stateResult = $stState->get_result();
  $states = [];
  while ($stateRow = $stateResult->fetch_assoc()) {
    $states[] = (int)$stateRow['estado'];
  }
  $stState->close();

  // Determinar estado final basado en productos
  if (!empty($states)) {
    $hasCancelled = count(array_filter($states, fn($s) => $s === -1)) > 0;
    $hasInProcess = count(array_filter($states, fn($s) => $s === 0 || $s === 1)) > 0;
    $allDelivered = count(array_filter($states, fn($s) => $s === 2)) === count($states);

    if ($hasCancelled) {
      $finalStatus = 'CANCELADA';
    } elseif ($hasInProcess) {
      $finalStatus = 'En proceso';
    } elseif ($allDelivered) {
      $finalStatus = 'ENTREGADA';
    }
  }
}

// Si hay estado final basado en productos, usarlo
if ($finalStatus !== null) {
  $paymentLabel = $finalStatus;
}

// Determinar label de método de envío
$shippingMethod = (string)($o['shipping_method'] ?? '');
$shippingLabel = '';
if ($shippingMethod === 'domicilio') {
  $commune = (string)($o['shipping_commune'] ?? '');
  $address = (string)($o['shipping_address'] ?? '');
  $shippingLabel = 'Envío a domicilio';
  if ($address) $shippingLabel .= " - {$address}";
  if ($commune) $shippingLabel .= " ({$commune})";
} elseif ($shippingMethod === 'agencia') {
  $agencyName = (string)($o['shipping_agency_name'] ?? '');
  $agencyAddress = (string)($o['shipping_agency_address'] ?? '');
  $shippingLabel = 'Retiro en sucursal Starken';
  if ($agencyName) $shippingLabel .= " - {$agencyName}";
  if ($agencyAddress) $shippingLabel .= " ({$agencyAddress})";
} elseif ($shippingMethod === 'vivero') {
  $shippingLabel = 'Retiro en vivero (gratis)';
}

// Obtener productos de la reserva
$items = [];
$sqlItems = "SELECT
              rp.id_variedad,
              rp.cantidad,
              rp.comentario,
              v.nombre,
              v.precio_detalle as unit_price_clp,
              CONCAT(t.codigo, LPAD(v.id_interno, 4, '0')) as referencia,
              (SELECT nombre_archivo FROM imagenes_variedades WHERE id_variedad = v.id LIMIT 1) as imagen_url
            FROM reservas_productos rp
            LEFT JOIN variedades_producto v ON rp.id_variedad = v.id
            LEFT JOIN tipos_producto t ON v.id_tipo = t.id
            WHERE rp.id_reserva = ?
            ORDER BY rp.id ASC";

$stItems = $dbStock->prepare($sqlItems);
if (!$stItems) {
  mysqli_close($dbStock);
  json_out(['ok'=>false,'error'=>'No se pudo preparar items: ' . $dbStock->error],500);
}

$stItems->bind_param('i', $id);
$stItems->execute();
$resItems = $stItems->get_result();

while($r = $resItems->fetch_assoc()){
  $qty = (int)($r['cantidad'] ?? 0);
  $unitPrice = (float)($r['unit_price_clp'] ?? 0);

  // Construir URL completa de la imagen
  $imagenArchivo = (string)($r['imagen_url'] ?? '');
  $imageUrl = $imagenArchivo
    ? "https://control.roelplant.cl/uploads/variedades/{$imagenArchivo}"
    : "https://via.placeholder.com/600x400?text=Imagen+pendiente";

  $items[] = [
    'id_variedad' => (int)($r['id_variedad'] ?? 0),
    'ref' => (string)($r['referencia'] ?? ''),
    'name' => (string)($r['nombre'] ?? ''),
    'image_url' => $imageUrl,
    'unit_price_clp' => (int)$unitPrice,
    'qty' => $qty,
    'line_total_clp' => (int)($unitPrice * $qty),
    'comentario' => (string)($r['comentario'] ?? ''),
  ];
}

$stItems->close();
mysqli_close($dbStock);

json_out([
  'ok' => true,
  'schema' => 'reservas',
  'order' => [
    'id' => (int)$o['id'],
    'order_code' => 'RP-' . str_pad((string)$o['id'], 6, '0', STR_PAD_LEFT),
    'status' => $paymentLabel,
    'payment_status' => $paymentStatus,
    'payment_method' => (string)($o['payment_method'] ?? ''),
    'subtotal_clp' => (int)($o['subtotal_clp'] ?? 0),
    'packing_cost_clp' => (int)($o['packing_cost_clp'] ?? 0),
    'shipping_cost_clp' => (int)($o['shipping_cost_clp'] ?? 0),
    'total_clp' => (int)($o['total_clp'] ?? 0),
    'paid_clp' => (int)($o['paid_clp'] ?? 0),
    'shipping_method' => $shippingMethod,
    'shipping_label' => $shippingLabel,
    'shipping_address' => (string)($o['shipping_address'] ?? ''),
    'shipping_commune' => (string)($o['shipping_commune'] ?? ''),
    'shipping_agency_name' => (string)($o['shipping_agency_name'] ?? ''),
    'shipping_agency_address' => (string)($o['shipping_agency_address'] ?? ''),
    'notes' => (string)($o['observaciones'] ?? ''),
    'created_at' => (string)($o['created_at'] ?? $o['fecha'] ?? ''),
  ],
  'items' => $items,
]);
