<?php
// catalogo_detalle/api/customer/profile.php
// Perfil del cliente autenticado (para checkout)
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db  = db();

// Obtener datos del cliente desde tabla unificada clientes
$q = "SELECT id_cliente, rut, mail as email, nombre, telefono, region, ciudad, domicilio, domicilio2, comuna FROM clientes WHERE id_cliente=? LIMIT 1";
$st = $db->prepare($q);
if (!$st) {
  json_out(['ok' => false, 'error' => 'No se pudo preparar consulta de cliente'], 500);
}

$st->bind_param('i', $cid);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
  // sesión inconsistente
  start_session();
  session_unset();
  session_destroy();
  unauthorized('Sesión inválida');
}

// Obtener nombre de la comuna
$comunaName = '';
if ($row['comuna']) {
  $qC = "SELECT nombre FROM comunas WHERE id=? LIMIT 1";
  $stC = $db->prepare($qC);
  $stC->bind_param('i', $row['comuna']);
  $stC->execute();
  $rowC = $stC->get_result()->fetch_assoc();
  if ($rowC) $comunaName = $rowC['nombre'];
  $stC->close();
}

json_out([
  'ok' => true,
  'customer' => [
    'id' => (int)$row['id_cliente'],
    'rut' => (string)$row['rut'],
    'email' => (string)($row['email'] ?? ''),
    'nombre' => (string)$row['nombre'],
    'telefono' => (string)$row['telefono'],
    'domicilio' => (string)$row['domicilio'],
    'domicilio2' => (string)($row['domicilio2'] ?? ''),
    'ciudad' => (string)$row['ciudad'],
    'region' => (string)$row['region'],
    'comuna' => (string)$comunaName,
  ],
]);
