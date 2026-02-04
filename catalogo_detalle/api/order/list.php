<?php
declare(strict_types=1);
// catalogo_detalle/api/order/list.php
// Lista reservas del cliente desde la tabla reservas (BD producción)
require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db = db();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;

// Conectar a BD de producción para obtener reservas
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

// Consultar reservas del cliente ordenadas por más recientes
$sql = "SELECT
          id,
          fecha as created_at,
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
          shipping_agency_name,
          cart_id,
          created_at
        FROM reservas
        WHERE id_cliente = ?
        ORDER BY id DESC
        LIMIT ?";

$st = $dbStock->prepare($sql);
if (!$st) {
  mysqli_close($dbStock);
  json_out(['ok'=>false,'error'=>'No se pudo preparar consulta: ' . $dbStock->error],500);
}

$st->bind_param('ii', $cid, $limit);
$st->execute();
$res = $st->get_result();
$rows = [];

while($r = $res->fetch_assoc()){
  $reservaId = (int)$r['id'];

  // Verificar estado de los productos de la reserva
  $stateQuery = "SELECT DISTINCT estado FROM reservas_productos WHERE id_reserva = ?";
  $stState = $dbStock->prepare($stateQuery);
  $finalStatus = null;

  if ($stState) {
    $stState->bind_param('i', $reservaId);
    $stState->execute();
    $stateResult = $stState->get_result();
    $states = [];
    while ($stateRow = $stateResult->fetch_assoc()) {
      $states[] = (int)$stateRow['estado'];
    }
    $stState->close();

    // Determinar estado final basado en productos
    if (!empty($states)) {
      $allCancelled = count(array_filter($states, fn($s) => $s === -1)) === count($states);
      $allDelivered = count(array_filter($states, fn($s) => $s === 2)) === count($states);

      if ($allCancelled) {
        $finalStatus = 'CANCELADA';
      } elseif ($allDelivered) {
        $finalStatus = 'ENTREGADA';
      }
    }
  }

  // Determinar label de estado de pago (solo si no hay override)
  $paymentStatus = (string)($r['payment_status'] ?? 'pending');
  if ($finalStatus !== null) {
    $paymentLabel = $finalStatus;
  } else {
    $paymentLabel = [
      'pending' => 'Pendiente de pago',
      'paid' => 'Pagado',
      'failed' => 'Pago fallido',
      'refunded' => 'Reembolsado'
    ][$paymentStatus] ?? $paymentStatus;
  }

  // Determinar label de método de envío
  $shippingMethod = (string)($r['shipping_method'] ?? '');
  $shippingLabel = '';
  if ($shippingMethod === 'domicilio') {
    $commune = (string)($r['shipping_commune'] ?? '');
    $shippingLabel = 'Envío a domicilio' . ($commune ? " ({$commune})" : '');
  } elseif ($shippingMethod === 'agencia') {
    $agencyName = (string)($r['shipping_agency_name'] ?? '');
    $shippingLabel = 'Retiro en sucursal' . ($agencyName ? " ({$agencyName})" : '');
  } elseif ($shippingMethod === 'vivero') {
    $shippingLabel = 'Retiro en vivero';
  }

  $rows[] = [
    'id' => (int)$r['id'],
    'order_code' => 'RP-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT), // Generar código basado en ID
    'status' => $paymentLabel,
    'payment_status' => $paymentStatus,
    'payment_method' => (string)($r['payment_method'] ?? ''),
    'subtotal_clp' => (int)($r['subtotal_clp'] ?? 0),
    'packing_cost_clp' => (int)($r['packing_cost_clp'] ?? 0),
    'shipping_cost_clp' => (int)($r['shipping_cost_clp'] ?? 0),
    'total_clp' => (int)($r['total_clp'] ?? 0),
    'paid_clp' => (int)($r['paid_clp'] ?? 0),
    'shipping_method' => $shippingMethod,
    'shipping_label' => $shippingLabel,
    'created_at' => (string)($r['created_at'] ?? $r['fecha'] ?? ''),
    'observaciones' => (string)($r['observaciones'] ?? ''),
  ];
}

$st->close();
mysqli_close($dbStock);

json_out(['ok'=>true,'schema'=>'reservas','orders'=>$rows]);
