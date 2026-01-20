<?php
declare(strict_types=1);
// catalogo_detalle/api/customer/update.php

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$nombre   = trim((string)($in['nombre'] ?? ''));
$telefono = trim((string)($in['telefono'] ?? ''));
$region   = trim((string)($in['region'] ?? ''));
$comuna   = trim((string)($in['comuna'] ?? ''));

$currentPassword = (string)($in['current_password'] ?? '');
$newPassword     = (string)($in['new_password'] ?? '');

if ($nombre === '' || mb_strlen($nombre) < 3) bad_request('Nombre inválido');
if ($telefono === '' || mb_strlen($telefono) < 6) bad_request('Teléfono inválido');
if ($region === '' || mb_strlen($region) < 2) bad_request('Región inválida');
if ($comuna === '' || mb_strlen($comuna) < 2) bad_request('Comuna inválida');

$changingPass = ($currentPassword !== '' || $newPassword !== '');
if ($changingPass) {
  if (strlen($currentPassword) < 8) bad_request('Contraseña actual inválida');
  if (strlen($newPassword) < 8) bad_request('La nueva contraseña debe tener al menos 8 caracteres');
}

$db = db();

// Verificar existencia y (si aplica) password actual
$q = "SELECT id, password_hash FROM customers WHERE id=? LIMIT 1";
$st = $db->prepare($q);
if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'], 500);
$st->bind_param('i', $cid);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();
if (!$row) unauthorized('Sesión inválida');

if ($changingPass) {
  $hash = (string)$row['password_hash'];
  if (!password_verify($currentPassword, $hash)) {
    bad_request('La contraseña actual no coincide');
  }
  $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
  $qU = "UPDATE customers SET nombre=?, telefono=?, region=?, comuna=?, password_hash=? WHERE id=? LIMIT 1";
  $stU = $db->prepare($qU);
  if (!$stU) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
  $stU->bind_param('sssssi', $nombre, $telefono, $region, $comuna, $newHash, $cid);
  if (!$stU->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar'], 500);
  $stU->close();
} else {
  $qU = "UPDATE customers SET nombre=?, telefono=?, region=?, comuna=? WHERE id=? LIMIT 1";
  $stU = $db->prepare($qU);
  if (!$stU) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
  $stU->bind_param('ssssi', $nombre, $telefono, $region, $comuna, $cid);
  if (!$stU->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar'], 500);
  $stU->close();
}

json_out(['ok'=>true]);
