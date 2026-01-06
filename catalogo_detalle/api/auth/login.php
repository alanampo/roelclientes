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

// Usar siempre las tablas unificadas de roel
login_unificado($db, $email, $password);

function login_unificado(mysqli $db, string $email, string $password) {
  // Buscar usuario por email (nombre field)
  $q = "SELECT u.id, u.password, u.id_cliente, u.inhabilitado, c.nombre, c.rut
        FROM usuarios u
        LEFT JOIN clientes c ON c.id_cliente = u.id_cliente
        WHERE u.nombre=? AND u.tipo_usuario=0 LIMIT 1";
  $st = $db->prepare($q);
  $st->bind_param('s', $email);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();

  if (!$row) unauthorized('Credenciales inválidas');
  if ((int)$row['inhabilitado'] === 1) unauthorized('Usuario inactivo');
  if (!password_verify($password, (string)$row['password'])) unauthorized('Credenciales inválidas');

  $usuarioId = (int)$row['id'];
  $clienteId = (int)$row['id_cliente'];

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
      'rut'=>(string)$row['rut'],
      'nombre'=>(string)$row['nombre']
    ]
  ]);
}

