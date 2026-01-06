<?php
// catalogo_detalle/api/auth/register.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$rutRaw   = (string)($in['rut'] ?? '');
$nombre   = trim((string)($in['nombre'] ?? ''));
$telefono = trim((string)($in['telefono'] ?? ''));
$region   = trim((string)($in['region'] ?? ''));
$comunaRaw = trim((string)($in['comuna'] ?? ''));
$emailRaw = (string)($in['email'] ?? '');
$password = (string)($in['password'] ?? '');
$domicilio = trim((string)($in['domicilio'] ?? ''));
$ciudad = trim((string)($in['ciudad'] ?? ''));

$rutClean = rut_clean($rutRaw);
$email = email_normalize($emailRaw);

if ($nombre === '' || mb_strlen($nombre) < 3) bad_request('Nombre inválido');
if ($telefono === '' || mb_strlen($telefono) < 6) bad_request('Teléfono inválido');
if ($region === '' || mb_strlen($region) < 2) bad_request('Región inválida');
if ($comunaRaw === '' || mb_strlen($comunaRaw) < 2) bad_request('Comuna inválida');
if (!rut_is_valid($rutClean)) bad_request('RUT inválido');
if (!email_is_valid($email)) bad_request('Email inválido');
if (strlen($password) < 8) bad_request('La contraseña debe tener al menos 8 caracteres');

$db = db();

// Usar siempre las tablas unificadas de roel
register_roel_unificado($db, $rutClean, $rutRaw, $nombre, $telefono, $domicilio, $ciudad, $region, $comunaRaw, $email, $password);

function register_roel_unificado(mysqli $db, string $rutClean, string $rutRaw, string $nombre, string $telefono, string $domicilio, string $ciudad, string $region, string $comunaRaw, string $email, string $password) {
  // Verificar RUT único
  $q = "SELECT rut FROM clientes WHERE rut=? LIMIT 1";
  $st = $db->prepare($q);
  $st->bind_param('s', $rutRaw);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) bad_request('Este RUT ya está registrado');
  $st->close();

  // Verificar email único en usuarios
  $qE = "SELECT nombre FROM usuarios WHERE nombre=? LIMIT 1";
  $stE = $db->prepare($qE);
  $stE->bind_param('s', $email);
  $stE->execute();
  if ($stE->get_result()->fetch_assoc()) bad_request('Este email ya está registrado');
  $stE->close();

  $hash = password_hash($password, PASSWORD_DEFAULT);

  // Obtener ID de la comuna (debe existir en tabla comunas)
  $comunaId = 0;
  $qC = "SELECT id FROM comunas WHERE nombre=? LIMIT 1";
  $stC = $db->prepare($qC);
  $stC->bind_param('s', $comunaRaw);
  $stC->execute();
  $rowC = $stC->get_result()->fetch_assoc();
  if ($rowC) $comunaId = (int)$rowC['id'];
  $stC->close();

  if ($comunaId <= 0) bad_request('Comuna no válida');

  // 1. Insertar en clientes
  // Si domicilio no viene, usar el nombre como valor por defecto
  if ($domicilio === '') $domicilio = $nombre;

  $qCliente = "INSERT INTO clientes (nombre, rut, telefono, domicilio, ciudad, region, comuna, mail, activo) VALUES (?,?,?,?,?,?,?,?,1)";
  $stCliente = $db->prepare($qCliente);
  $stCliente->bind_param('ssssssis', $nombre, $rutRaw, $telefono, $domicilio, $ciudad, $region, $comunaId, $email);
  if (!$stCliente->execute()) bad_request('No se pudo crear cliente', ['db_error' => $stCliente->error]);
  $clienteId = (int)$stCliente->insert_id;
  $stCliente->close();

  // 2. Insertar en usuarios (tipo_usuario=0 para cliente)
  $tipo = 0;
  $qUsuario = "INSERT INTO usuarios (nombre, nombre_real, password, tipo_usuario, id_cliente) VALUES (?,?,?,?,?)";
  $stUsuario = $db->prepare($qUsuario);
  $stUsuario->bind_param('sssii', $email, $nombre, $hash, $tipo, $clienteId);
  if (!$stUsuario->execute()) bad_request('No se pudo crear usuario', ['db_error' => $stUsuario->error]);
  $usuarioId = (int)$stUsuario->insert_id;
  $stUsuario->close();

  start_session();
  $_SESSION['usuario_id'] = $usuarioId;      // Usado en roel
  $_SESSION['id_cliente'] = $clienteId;       // Usado en roel
  $_SESSION['customer_id'] = $clienteId;      // Para compatibilidad con catalogo_detalle
  $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));

  json_out([
    'ok'=>true,
    'customer'=>[
      'id'=>$usuarioId,
      'cliente_id'=>$clienteId,
      'email'=>$email,
      'rut'=>$rutRaw,
      'nombre'=>$nombre
    ]
  ]);
}

