<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

try {
  // Obtener id_cliente del usuario logeado (desde sesión del catálogo)
  $idCliente = require_auth();

  // Conectar a BD de producción
  $conectaPaths = [
    __DIR__ . '/../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../class_lib/class_conecta_mysql.php',
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
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se encontró configuración de BD']);
    exit;
  }

  $dbStock = @mysqli_connect($hostStock, $hostUser, $hostPass, $hostDbname);
  if (!$dbStock) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error conexión BD']);
    exit;
  }
  mysqli_set_charset($dbStock, 'utf8');

  // Obtener datos del cliente con JOIN a usuarios y comunas para obtener nombre_real y nombre de comuna
  $customerSQL = "SELECT c.id_cliente, c.nombre, c.rut, c.mail, c.telefono, c.region, c.comuna,
                         c.domicilio, c.domicilio2, c.provincia,
                         u.nombre_real,
                         co.nombre as comuna_nombre, co.ciudad as ciudad_nombre
                  FROM clientes c
                  LEFT JOIN usuarios u ON u.id_cliente = c.id_cliente
                  LEFT JOIN comunas co ON co.id = c.comuna
                  WHERE c.id_cliente = ?
                  LIMIT 1";
  $stmt = $dbStock->prepare($customerSQL);
  if (!$stmt) {
    mysqli_close($dbStock);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error preparando consulta cliente']);
    exit;
  }

  $stmt->bind_param('i', $idCliente);
  $stmt->execute();
  $result = $stmt->get_result();
  $customer = $result->fetch_assoc();
  $stmt->close();

  if (!$customer) {
    mysqli_close($dbStock);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
    exit;
  }

  // Extraer primer nombre del nombre completo (ej: "Alan Ampo" → "Alan")
  $nombreCompleto = trim($customer['nombre_real'] ?? '');
  $nombrePrimero = '';
  if ($nombreCompleto) {
    $parts = explode(' ', $nombreCompleto);
    $nombrePrimero = $parts[0]; // Tomar primera palabra
  }

  // Obtener últimas 5 reservas (órdenes) del cliente
  $recentOrders = [];
  $ordersSQL = "SELECT id, fecha as created_at, total_clp, payment_status
                FROM reservas
                WHERE id_cliente = ?
                ORDER BY id DESC
                LIMIT 5";

  $stmt = $dbStock->prepare($ordersSQL);
  if ($stmt) {
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      // Generar order_code como en order/list.php
      $orderCode = 'RP-' . str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT);

      // Mapear payment_status a labels
      $paymentStatus = (string)($row['payment_status'] ?? 'pending');
      $statusLabel = [
        'pending' => 'Pendiente',
        'paid' => 'Pagado',
        'failed' => 'Fallido',
        'refunded' => 'Reembolsado'
      ][$paymentStatus] ?? $paymentStatus;

      $recentOrders[] = [
        'id' => (int)$row['id'],
        'order_code' => $orderCode,
        'fecha' => (string)($row['created_at'] ?? ''),
        'total_clp' => (int)($row['total_clp'] ?? 0),
        'status' => $statusLabel
      ];
    }
    $stmt->close();
  }

  // Obtener estadísticas de compra
  $statsSQL = "SELECT
                  COUNT(*) as total_orders,
                  COALESCE(SUM(total_clp), 0) as total_spent_clp,
                  MAX(fecha) as last_order_at
               FROM reservas
               WHERE id_cliente = ?";

  $stmt = $dbStock->prepare($statsSQL);
  $stmt->bind_param('i', $idCliente);
  $stmt->execute();
  $result = $stmt->get_result();
  $stats = $result->fetch_assoc();
  $stmt->close();

  mysqli_close($dbStock);

  // Formatear respuesta
  echo json_encode([
    'ok' => true,
    'user' => [
      'id' => (int)$customer['id_cliente'],
      'nombre' => $nombrePrimero, // Solo primer nombre para saludos
      'nombre_completo' => $nombreCompleto, // Nombre completo por si se necesita
      'email' => $customer['mail'] ?? '',
      'telefono' => $customer['telefono'] ?? '',
      'region' => $customer['region'] ?? '',
      'provincia' => $customer['provincia'] ?? '',
      'ciudad' => $customer['ciudad_nombre'] ?? '',
      'comuna' => $customer['comuna_nombre'] ?? '',
      'domicilio' => $customer['domicilio'] ?? '',
      'domicilio2' => $customer['domicilio2'] ?? '',
      'rut' => $customer['rut'] ?? ''
    ],
    'recent_orders' => $recentOrders,
    'stats' => [
      'total_orders' => (int)($stats['total_orders'] ?? 0),
      'total_spent_clp' => (int)($stats['total_spent_clp'] ?? 0),
      'last_order_at' => $stats['last_order_at'] ?? null
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $th) {
  error_log('[USER_CONTEXT] Error: ' . $th->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error: ' . $th->getMessage()]);
}
