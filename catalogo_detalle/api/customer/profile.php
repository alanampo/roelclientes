<?php
// catalogo_detalle/api/customer/profile.php
// Perfil del cliente autenticado (para checkout)
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db  = db();

$q = "SELECT id, rut, email, nombre, telefono, region, comuna FROM customers WHERE id=? LIMIT 1";
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

json_out([
  'ok' => true,
  'customer' => [
    'id' => (int)$row['id'],
    'rut' => (string)$row['rut'],
    'email' => (string)($row['email'] ?? ''),
    'nombre' => (string)$row['nombre'],
    'telefono' => (string)$row['telefono'],
    'region' => (string)$row['region'],
    'comuna' => (string)$row['comuna'],
  ],
]);
