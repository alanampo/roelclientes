<?php
// /catalogo_detalle/backoffice/login.php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

if (bo_is_logged()) {
  header('Location: ' . $BACKOFFICE_PATH . '/index.php');
  exit;
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = trim((string)($_POST['usuario'] ?? ''));
  $pass    = (string)($_POST['password'] ?? '');

  try {
    bo_require_csrf();

    if ($usuario === '' || $pass === '') {
      throw new RuntimeException('Ingresa usuario y contraseña.');
    }

    $db = bo_db();

    // Buscar usuario vendedor (tipo_usuario = 1) en tabla usuarios
    $st = mysqli_prepare($db, "SELECT id,nombre,nombre_real,password,tipo_usuario,inhabilitado FROM usuarios WHERE nombre=? AND tipo_usuario=1 LIMIT 1");
    if (!$st) throw new RuntimeException('DB prepare error.');

    mysqli_stmt_bind_param($st, 's', $usuario);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($st);

    if (!$row || (int)$row['inhabilitado'] === 1) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    // Intentar con password_verify (hash password_hash)
    $passValid = password_verify($pass, (string)$row['password']);

    // Si falla, intentar con comparación directa (texto plano)
    if (!$passValid) {
      $passValid = hash_equals((string)$row['password'], (string)$pass);
    }

    if (!$passValid) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    $_SESSION['bo_admin'] = [
      'id' => (int)$row['id'],
      'usuario' => (string)$row['nombre'],
      'name' => (string)($row['nombre_real'] ?: $row['nombre']),
      'tipo_usuario' => (int)$row['tipo_usuario'],
    ];

    bo_audit('login', ['usuario'=>$usuario]);

    header('Location: ' . $BACKOFFICE_PATH . '/index.php');
    exit;

  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

$csrf = bo_csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Backoffice • Login</title>
  <link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
  <div class="wrap" style="max-width:720px">
    <div class="topbar">
      <div class="brand">
        <span style="font-size:18px">Roelplant</span>
        <span class="badge">Backoffice</span>
      </div>
      <a class="btn btn-ghost" href="../index.php">Volver al catálogo</a>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-h">
        <div>
          <h1 class="h1" style="margin:0">Iniciar sesión</h1>
          <div class="muted">Acceso administrativo</div>
        </div>
      </div>
      <div class="card-b">
        <?php if ($error): ?>
          <div class="alert bad"><?=bo_h($error)?></div>
          <div style="height:10px"></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?=bo_h($csrf)?>">
          <div class="row">
            <div style="flex:1;min-width:260px">
              <div class="muted" style="font-weight:800;font-size:12px;margin-bottom:6px">Usuario</div>
              <input class="inp" type="text" name="usuario" value="<?=bo_h($usuario)?>" required autocomplete="off">
            </div>
            <div style="flex:1;min-width:260px">
              <div class="muted" style="font-weight:800;font-size:12px;margin-bottom:6px">Contraseña</div>
              <input class="inp" type="password" name="password" required autocomplete="off">
            </div>
          </div>

          <div style="height:12px"></div>

          <div class="row" style="justify-content:space-between">
            <a class="btn" href="../index.php">Cancelar</a>
            <button class="btn btn-primary" type="submit">Entrar</button>
          </div>
        </form>

        <hr>
        <div class="muted" style="font-size:13px">
          Si te da 500, revisa el <b>error_log</b> del hosting (fatal include/paths).
        </div>
      </div>
    </div>
  </div>
</body>
</html>
