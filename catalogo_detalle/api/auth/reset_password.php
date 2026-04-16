<?php
// catalogo_detalle/api/auth/reset_password.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$token    = trim((string)($in['token'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($token === '')       bad_request('Token requerido');
if (strlen($password) < 8) bad_request('La contraseña debe tener al menos 8 caracteres');

$db = db();

// Buscar token válido, no usado y no expirado
$st = $db->prepare("SELECT id, email FROM password_reset_tokens WHERE token=? AND used=0 AND expires_at > NOW() LIMIT 1");
$st->bind_param('s', $token);
$st->execute();
$tokenRow = $st->get_result()->fetch_assoc();
$st->close();

if (!$tokenRow) {
  json_out(['ok' => false, 'error' => 'El enlace es inválido o ya expiró.'], 400);
}

$email  = (string)$tokenRow['email'];
$hash   = password_hash($password, PASSWORD_BCRYPT);

// Actualizar contraseña en usuarios
$stUp = $db->prepare("UPDATE usuarios SET password=? WHERE nombre=? AND tipo_usuario=0");
$stUp->bind_param('ss', $hash, $email);
$stUp->execute();
$affected = $stUp->affected_rows;
$stUp->close();

if ($affected === 0) {
  json_out(['ok' => false, 'error' => 'Usuario no encontrado.'], 404);
}

// Marcar token como usado
$stMark = $db->prepare("UPDATE password_reset_tokens SET used=1 WHERE id=?");
$stMark->bind_param('i', $tokenRow['id']);
$stMark->execute();
$stMark->close();

json_out(['ok' => true, 'message' => 'Contraseña actualizada correctamente.']);
