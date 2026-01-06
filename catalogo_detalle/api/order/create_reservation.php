<?php
/**
 * catalogo_detalle/api/order/create_reservation.php
 * Crea una reserva (compra) desde el carrito del catálogo detalle.
 * Imita la funcionalidad de guardarReserva() en ver_reservas.php
 * pero integrada en la API moderna del catálogo.
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) bad_request('Payload inválido');

$items = $payload['items'] ?? [];
$notes = trim((string)($payload['notes'] ?? ''));

if (!is_array($items) || count($items) === 0) {
  bad_request('Carrito vacío');
}

// Conectar a la BD de producción (stock)
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

try {
  // Validar stock para cada producto ANTES de crear la reserva
  $products_detailed = [];
  foreach ($items as $item) {
    $id_variedad = (int)($item['id_variedad'] ?? 0);
    $qty = (int)($item['qty'] ?? 0);

    if ($id_variedad <= 0 || $qty <= 0) {
      throw new RuntimeException('Producto o cantidad inválida');
    }

    // Consultar stock disponible desde BD de producción
    $queryStock = "
      SELECT
        (SUM(s.cantidad) -
         IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r WHERE r.id_variedad = ? AND r.estado >= 0), 0)
        ) as disponible
      FROM stock_productos s
      INNER JOIN articulospedidos ap ON s.id_artpedido = ap.id
      WHERE ap.id_variedad = ? AND ap.estado >= 8
    ";

    $stStock = $dbStock->prepare($queryStock);
    if (!$stStock) {
      throw new RuntimeException('Error validando stock: ' . $dbStock->error);
    }
    $stStock->bind_param('ii', $id_variedad, $id_variedad);
    $stStock->execute();
    $resStock = $stStock->get_result();
    $stockData = $resStock->fetch_assoc();
    $stStock->close();

    $disponible = (int)($stockData['disponible'] ?? 0);

    if ($disponible < $qty) {
      throw new RuntimeException("Stock insuficiente para producto ID {$id_variedad}. Solicitado: {$qty}, Disponible: {$disponible}");
    }

    // Guardar detalles para inserción posterior
    $products_detailed[] = [
      'id_variedad' => $id_variedad,
      'qty' => $qty,
      'nombre' => (string)($item['nombre'] ?? ''),
      'referencia' => (string)($item['referencia'] ?? ''),
      'unit_price' => (int)($item['unit_price_clp'] ?? 0),
      'comentario' => (string)($item['comentario'] ?? '')
    ];
  }

  // Buscar usuario asociado al cliente desde BD de carrito
  $queryUser = "SELECT id FROM usuarios WHERE id_cliente = ? AND tipo_usuario = 0 LIMIT 1";
  $stUser = $db->prepare($queryUser);
  if (!$stUser) throw new RuntimeException('Prepare error: ' . $db->error);
  $stUser->bind_param('i', $cid);
  $stUser->execute();
  $rowUser = $stUser->get_result()->fetch_assoc();
  $stUser->close();

  $idUsuario = $rowUser ? (int)$rowUser['id'] : null;
  if ($idUsuario === null) {
    throw new RuntimeException('No se encontró usuario asociado al cliente');
  }

  // Iniciar transacción en BD de producción
  $dbStock->begin_transaction();

  // Crear reserva en BD de producción
  $queryReserva = "INSERT INTO reservas (fecha, id_cliente, observaciones, id_usuario) VALUES (NOW(), ?, ?, ?)";
  $stReserva = $dbStock->prepare($queryReserva);
  if (!$stReserva) throw new RuntimeException('Prepare reserva failed: ' . $dbStock->error);

  $stReserva->bind_param('isi', $cid, $notes, $idUsuario);
  if (!$stReserva->execute()) {
    throw new RuntimeException('Execute reserva failed: ' . $stReserva->error);
  }
  $idReserva = (int)$stReserva->insert_id;
  $stReserva->close();

  // Insertar productos de la reserva en BD de producción
  $queryProd = "INSERT INTO reservas_productos (id_reserva, id_variedad, cantidad, comentario, estado, origen, id_usuario) VALUES (?, ?, ?, ?, 0, 'CATALOGO DETALLE', ?)";
  $stProd = $dbStock->prepare($queryProd);
  if (!$stProd) throw new RuntimeException('Prepare productos failed: ' . $dbStock->error);

  foreach ($products_detailed as $prod) {
    $stProd->bind_param('iisis', $idReserva, $prod['id_variedad'], $prod['qty'], $prod['comentario'], $idUsuario);
    if (!$stProd->execute()) {
      throw new RuntimeException('Execute productos failed: ' . $stProd->error);
    }
  }
  $stProd->close();

  // Commit transacción en BD de producción
  $dbStock->commit();

  // Limpiar carrito desde la BD de carrito (eliminar items sin cambiar estado)
  $queryCart = "SELECT id FROM " . CART_TABLE . " WHERE id_cliente = ? AND status = 'open' LIMIT 1";
  $stCart = $db->prepare($queryCart);
  if ($stCart) {
    $stCart->bind_param('i', $cid);
    $stCart->execute();
    $rowCart = $stCart->get_result()->fetch_assoc();
    $stCart->close();

    if ($rowCart) {
      $cartId = (int)$rowCart['id'];

      // Eliminar items del carrito (pero mantener el carrito en estado 'open' para futuras compras)
      $stDel = $db->prepare("DELETE FROM " . CART_ITEMS_TABLE . " WHERE cart_id = ?");
      if ($stDel) {
        $stDel->bind_param('i', $cartId);
        $stDel->execute();
        $stDel->close();
      }
    }
  }

  if ($dbStock) mysqli_close($dbStock);

  json_out([
    'ok' => true,
    'reservation_id' => $idReserva,
    'message' => 'Compra registrada correctamente'
  ]);

} catch (Throwable $e) {
  if ($dbStock) {
    $dbStock->rollback();
    mysqli_close($dbStock);
  }
  json_out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
