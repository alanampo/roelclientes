<?php
// /catalogo_detalle/backoffice/login.php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

if (bo_is_logged()) {
  header('Location: index.php');
  exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  try {
    bo_require_csrf();

    if ($email === '' || $pass === '') {
      throw new RuntimeException('Ingresa email y contraseña.');
    }

    $db = bo_db();

    // Asegura tabla (si no la creaste aún)
    mysqli_query($db, "CREATE TABLE IF NOT EXISTS backoffice_admins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(190) NOT NULL UNIQUE,
      pass_hash VARCHAR(255) NOT NULL,
      name VARCHAR(120) NOT NULL DEFAULT 'Administrador',
      role VARCHAR(50) NOT NULL DEFAULT 'admin',
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      last_login_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $st = mysqli_prepare($db, "SELECT id,email,pass_hash,name,role,is_active FROM backoffice_admins WHERE email=? LIMIT 1");
    if (!$st) throw new RuntimeException('DB prepare error.');

    mysqli_stmt_bind_param($st, 's', $email);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($st);

    if (!$row || (int)$row['is_active'] !== 1) {
      throw new RuntimeException('Credenciales inválidas.');
    }
    if (!password_verify($pass, (string)$row['pass_hash'])) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    $_SESSION['bo_admin'] = [
      'id' => (int)$row['id'],
      'email' => (string)$row['email'],
      'name' => (string)$row['name'],
      'role' => (string)$row['role'],
    ];

    @mysqli_query($db, "UPDATE backoffice_admins SET last_login_at=NOW() WHERE id=".(int)$row['id']);

    bo_audit('login', ['email'=>$email]);

    header('Location: index.php');
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

        <form method="post" autocomplete="on">
          <input type="hidden" name="_csrf" value="<?=bo_h($csrf)?>">
          <div class="row">
            <div style="flex:1;min-width:260px">
              <div class="muted" style="font-weight:800;font-size:12px;margin-bottom:6px">Email</div>
              <input class="inp" type="email" name="email" value="<?=bo_h($email)?>" required>
            </div>
            <div style="flex:1;min-width:260px">
              <div class="muted" style="font-weight:800;font-size:12px;margin-bottom:6px">Contraseña</div>
              <input class="inp" type="password" name="password" required>
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
