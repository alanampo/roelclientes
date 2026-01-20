<?php
// catalogo_detalle/api/me.php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

start_session();
$token = csrf_token();

$cid = (int)($_SESSION['customer_id'] ?? 0);
if ($cid <= 0) {
  json_out(['ok'=>true,'logged'=>false,'csrf'=>$token]);
}

$db = db();
$q = "SELECT id, rut, email, nombre FROM customers WHERE id=? LIMIT 1";
$st = $db->prepare($q);
$st->bind_param('i', $cid);
$st->execute();
$res = $st->get_result();
$row = $res->fetch_assoc();
if (!$row) {
  session_unset();
  session_destroy();
  json_out(['ok'=>true,'logged'=>false,'csrf'=>$token]);
}

json_out([
  'ok'=>true,
  'logged'=>true,
  'customer'=>[
    'id'=>(int)$row['id'],
    'rut'=>(string)$row['rut'],
    'email'=>(string)($row['email'] ?? ''),
    'nombre'=>(string)$row['nombre'],
  ],
  'csrf'=>$token
]);
