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
$comuna   = trim((string)($in['comuna'] ?? ''));
$emailRaw = (string)($in['email'] ?? '');
$password = (string)($in['password'] ?? '');

$rutClean = rut_clean($rutRaw);
$email = email_normalize($emailRaw);

if ($nombre === '' || mb_strlen($nombre) < 3) bad_request('Nombre inválido');
if ($telefono === '' || mb_strlen($telefono) < 6) bad_request('Teléfono inválido');
if ($region === '' || mb_strlen($region) < 2) bad_request('Región inválida');
if ($comuna === '' || mb_strlen($comuna) < 2) bad_request('Comuna inválida');
if (!rut_is_valid($rutClean)) bad_request('RUT inválido');
if (!email_is_valid($email)) bad_request('Email inválido');
if (strlen($password) < 8) bad_request('La contraseña debe tener al menos 8 caracteres');

$db = db();

// verificar rut único
$q = "SELECT id FROM customers WHERE rut_clean=? LIMIT 1";
$st = $db->prepare($q);
$st->bind_param('s', $rutClean);
$st->execute();
$res = $st->get_result();
if ($res->fetch_assoc()) bad_request('Este RUT ya está registrado');
$st->close();

// verificar email único (ignora NULL)
$qE = "SELECT id FROM customers WHERE email=? LIMIT 1";
$stE = $db->prepare($qE);
$stE->bind_param('s', $email);
$stE->execute();
$resE = $stE->get_result();
if ($resE->fetch_assoc()) bad_request('Este email ya está registrado');
$stE->close();

$hash = password_hash($password, PASSWORD_DEFAULT);

$rutFmt = rut_format($rutClean);
$q2 = "INSERT INTO customers (rut, rut_clean, nombre, telefono, region, comuna, email, password_hash) VALUES (?,?,?,?,?,?,?,?)";
$st2 = $db->prepare($q2);
$st2->bind_param('ssssssss', $rutFmt, $rutClean, $nombre, $telefono, $region, $comuna, $email, $hash);
if (!$st2->execute()) {
  json_out(['ok'=>false,'error'=>'No se pudo registrar'], 500);
}
$customerId = (int)$st2->insert_id;

start_session();
$_SESSION['customer_id'] = $customerId;
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32)); // mantener

json_out([
  'ok'=>true,
  'customer'=>[
    'id'=>$customerId,
    'email'=>$email,
    'rut'=>$rutFmt,
    'nombre'=>$nombre
  ]
]);
