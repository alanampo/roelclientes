<?php
// catalogo_detalle/backoffice/_layout.php
declare(strict_types=1);

require __DIR__ . '/_bo_bootstrap.php';

function bo_header(string $title): void {
  $is = is_logged_admin();
  $csrf = csrf_token();
  header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — Backoffice</title>
  <link rel="stylesheet" href="assets/bo.css?v=1">
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo">R</div>
      <div>
        <div class="brand-name">Roelplant</div>
        <div class="brand-sub">Backoffice</div>
      </div>
    </div>

    <nav class="nav">
      <?php if ($is): ?>
        <a class="navlink" href="index.php">Dashboard</a>
        <a class="navlink" href="customers.php">Clientes</a>
        <a class="navlink" href="orders.php">Pedidos stock</a>
        <a class="navlink" href="production_requests.php">Pedidos producción</a>
        <a class="navlink" href="../index.php" target="_blank" rel="noopener">Ver catálogo</a>
      <?php endif; ?>
    </nav>

    <div class="right">
      <?php if ($is): ?>
        <form method="post" action="logout.php" class="inline">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <button class="btn btn-danger" type="submit">Salir</button>
        </form>
      <?php else: ?>
        <a class="btn" href="login.php">Ingresar</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="wrap">
<?php
}

function bo_footer(): void {
?>
  </main>
  <footer class="footer">
    <small>Roelplant Backoffice</small>
  </footer>
</body>
</html>
<?php
}
