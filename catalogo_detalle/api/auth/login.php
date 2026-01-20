<?php
// catalogo_detalle/api/auth/login.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$emailRaw = (string)($in['email'] ?? '');
$password = (string)($in['password'] ?? '');

$email = email_normalize($emailRaw);
if (!email_is_valid($email)) bad_request('Email inválido');
if ($password === '') bad_request('Contraseña requerida');

$db = db();

$q = "SELECT id, rut, email, nombre, password_hash, is_active FROM customers WHERE email=? LIMIT 1";
$st = $db->prepare($q);
$st->bind_param('s', $email);
$st->execute();
$res = $st->get_result();
$row = $res->fetch_assoc();
$st->close();

if (!$row) unauthorized('Credenciales inválidas');
if ((int)$row['is_active'] !== 1) unauthorized('Usuario inactivo');
if (!password_verify($password, (string)$row['password_hash'])) unauthorized('Credenciales inválidas');

$cid = (int)$row['id'];
$upd = $db->prepare("UPDATE customers SET last_login_at=NOW() WHERE id=?");
$upd->bind_param('i', $cid);
$upd->execute();

start_session();
$_SESSION['customer_id'] = $cid;
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));

json_out([
  'ok'=>true,
  'customer'=>[
    'id'=>$cid,
    'email'=>(string)$row['email'],
    'rut'=>(string)$row['rut'],
    'nombre'=>(string)$row['nombre']
  ]
]);
