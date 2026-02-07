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
  // Escapar todos los valores
  $rutRawEsc = mysqli_real_escape_string($db, $rutRaw);
  $emailEsc = mysqli_real_escape_string($db, $email);
  $nombreEsc = mysqli_real_escape_string($db, $nombre);
  $telefonoEsc = mysqli_real_escape_string($db, $telefono);
  $domicilioEsc = mysqli_real_escape_string($db, $domicilio);
  $ciudadEsc = mysqli_real_escape_string($db, $ciudad);
  $regionEsc = mysqli_real_escape_string($db, $region);
  $comunaRawEsc = mysqli_real_escape_string($db, $comunaRaw);

  // Verificar RUT único
  $qCheck = "SELECT id_cliente FROM clientes WHERE rut='{$rutRawEsc}' LIMIT 1";
  if (mysqli_fetch_assoc(mysqli_query($db, $qCheck))) {
    bad_request('Este RUT ya está registrado');
  }

  // Verificar email único en usuarios
  $qCheckEmail = "SELECT id FROM usuarios WHERE nombre='{$emailEsc}' LIMIT 1";
  if (mysqli_fetch_assoc(mysqli_query($db, $qCheckEmail))) {
    bad_request('Este email ya está registrado');
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $hashEsc = mysqli_real_escape_string($db, $hash);

  // Obtener ID de la comuna
  $qComuna = "SELECT id FROM comunas WHERE nombre='{$comunaRawEsc}' LIMIT 1";
  $resComuna = mysqli_query($db, $qComuna);
  $rowComuna = mysqli_fetch_assoc($resComuna);
  $comunaId = $rowComuna ? (int)$rowComuna['id'] : 0;

  if ($comunaId <= 0) {
    bad_request('Comuna no válida');
  }

  // Si domicilio no viene, usar el nombre como valor por defecto
  if ($domicilioEsc === '') $domicilioEsc = $nombreEsc;

  // Obtener id_vendedor (usuario "catalogo")
  $vendedorId = 0;
  $qVendedor = "SELECT id FROM usuarios WHERE nombre='catalogo' LIMIT 1";
  $resVendedor = mysqli_query($db, $qVendedor);
  if ($resVendedor && ($rowVendedor = mysqli_fetch_assoc($resVendedor))) {
    $vendedorId = (int)$rowVendedor['id'];
  } else {
    // Si no existe, crear usuario "catalogo" con datos random
    $vendedorHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $vendedorHashEsc = mysqli_real_escape_string($db, $vendedorHash);
    $qVendedorInsert = "INSERT INTO usuarios (nombre, nombre_real, password, tipo_usuario, iniciales, inhabilitado)
                        VALUES ('catalogo', 'Catalogo', '{$vendedorHashEsc}', 1, 'CAT', 0)";
    if (!mysqli_query($db, $qVendedorInsert)) {
      bad_request('No se pudo crear usuario vendedor', ['db_error' => mysqli_error($db)]);
    }
    $vendedorId = (int)mysqli_insert_id($db);
  }

  // 1. Insertar en clientes con id_vendedor
  $qCliente = "INSERT INTO clientes (nombre, rut, telefono, domicilio, ciudad, region, comuna, mail, activo, id_vendedor)
               VALUES ('{$nombreEsc}', '{$rutRawEsc}', '{$telefonoEsc}', '{$domicilioEsc}', '{$ciudadEsc}', '{$regionEsc}', {$comunaId}, '{$emailEsc}', 1, {$vendedorId})";

  if (!mysqli_query($db, $qCliente)) {
    bad_request('No se pudo crear cliente', ['db_error' => mysqli_error($db)]);
  }
  $clienteId = (int)mysqli_insert_id($db);

  // 2. Insertar en usuarios (tipo_usuario=0 para cliente)
  $tipo = 0;
  $qUsuario = "INSERT INTO usuarios (nombre, nombre_real, password, tipo_usuario, id_cliente)
               VALUES ('{$emailEsc}', '{$nombreEsc}', '{$hashEsc}', {$tipo}, {$clienteId})";

  if (!mysqli_query($db, $qUsuario)) {
    bad_request('No se pudo crear usuario', ['db_error' => mysqli_error($db)]);
  }
  $usuarioId = (int)mysqli_insert_id($db);

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

