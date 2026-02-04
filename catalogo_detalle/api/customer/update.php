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
$comunaRaw = trim((string)($in['comuna'] ?? ''));
$domicilio = trim((string)($in['domicilio'] ?? ''));
$comunaCodeDls = (int)($in['comuna_code_dls'] ?? 0);

$currentPassword = (string)($in['current_password'] ?? '');
$newPassword     = (string)($in['new_password'] ?? '');

// Validar si se está intentando actualizar campos básicos
$updatingBasic = !empty($nombre) || !empty($telefono) || !empty($region) || !empty($comunaRaw) || $changingPass;
if ($updatingBasic) {
  if ($nombre === '' || mb_strlen($nombre) < 3) bad_request('Nombre inválido');
  if ($telefono === '' || mb_strlen($telefono) < 6) bad_request('Teléfono inválido');
  if ($region === '' || mb_strlen($region) < 2) bad_request('Región inválida');
  if ($comunaRaw === '' || mb_strlen($comunaRaw) < 2) bad_request('Comuna inválida');
}

$changingPass = ($currentPassword !== '' || $newPassword !== '');
if ($changingPass) {
  if (strlen($currentPassword) < 8) bad_request('Contraseña actual inválida');
  if (strlen($newPassword) < 8) bad_request('La nueva contraseña debe tener al menos 8 caracteres');
}

$db = db();

// Usar siempre las tablas unificadas de roel
if ($updatingBasic) {
  update_unificado($db, $cid, $nombre, $telefono, $region, $comunaRaw, $currentPassword, $newPassword, $changingPass);
}

// Actualizar datos de envío si se proporcionan
if ($domicilio !== '' || $comunaCodeDls > 0) {
  update_shipping_address($db, $cid, $domicilio, $comunaCodeDls);
}

function update_unificado(mysqli $db, int $cid, string $nombre, string $telefono, string $region, string $comunaRaw, string $currentPassword, string $newPassword, bool $changingPass) {
  // Obtener ID de la comuna
  $comunaId = 0;
  $qC = "SELECT id FROM comunas WHERE nombre=? LIMIT 1";
  $stC = $db->prepare($qC);
  $stC->bind_param('s', $comunaRaw);
  $stC->execute();
  $rowC = $stC->get_result()->fetch_assoc();
  if ($rowC) $comunaId = (int)$rowC['id'];
  $stC->close();

  if ($comunaId <= 0) bad_request('Comuna no válida');

  // Verificar existencia y (si aplica) password actual
  $q = "SELECT u.id, u.password FROM usuarios u WHERE u.id_cliente=? LIMIT 1";
  $st = $db->prepare($q);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'], 500);
  $st->bind_param('i', $cid);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$row) unauthorized('Sesión inválida');

  if ($changingPass) {
    $hash = (string)$row['password'];
    if (!password_verify($currentPassword, $hash)) {
      bad_request('La contraseña actual no coincide');
    }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Actualizar clientes
    $qU = "UPDATE clientes SET nombre=?, telefono=?, region=?, comuna=? WHERE id_cliente=? LIMIT 1";
    $stU = $db->prepare($qU);
    if (!$stU) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
    $stU->bind_param('sssii', $nombre, $telefono, $region, $comunaId, $cid);
    if (!$stU->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar cliente'], 500);
    $stU->close();

    // Actualizar usuarios (password + nombre_real)
    $qU2 = "UPDATE usuarios SET password=?, nombre_real=? WHERE id_cliente=? LIMIT 1";
    $stU2 = $db->prepare($qU2);
    if (!$stU2) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
    $stU2->bind_param('ssi', $newHash, $nombre, $cid);
    if (!$stU2->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar usuario'], 500);
    $stU2->close();
  } else {
    // Solo actualizar datos sin contraseña
    $qU = "UPDATE clientes SET nombre=?, telefono=?, region=?, comuna=? WHERE id_cliente=? LIMIT 1";
    $stU = $db->prepare($qU);
    if (!$stU) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
    $stU->bind_param('sssii', $nombre, $telefono, $region, $comunaId, $cid);
    if (!$stU->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar cliente'], 500);
    $stU->close();

    // Actualizar nombre_real en usuarios
    $qU2 = "UPDATE usuarios SET nombre_real=? WHERE id_cliente=? LIMIT 1";
    $stU2 = $db->prepare($qU2);
    if (!$stU2) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);
    $stU2->bind_param('si', $nombre, $cid);
    if (!$stU2->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar usuario'], 500);
    $stU2->close();
  }

  json_out(['ok'=>true]);
}

function update_shipping_address(mysqli $db, int $cid, string $domicilio, int $comunaCodeDls) {
  // Actualizar domicilio y domicilio2 (codigo de la comuna de Starken)
  $q = "UPDATE clientes SET domicilio=?, domicilio2=? WHERE id_cliente=? LIMIT 1";
  $st = $db->prepare($q);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar actualización'], 500);

  $st->bind_param('sii', $domicilio, $comunaCodeDls, $cid);
  if (!$st->execute()) json_out(['ok'=>false,'error'=>'No se pudo actualizar dirección'], 500);
  $st->close();

  json_out(['ok'=>true]);
}

