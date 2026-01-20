<?php
// /catalogo_detalle/backoffice/_boot.php
declare(strict_types=1);

$ROOT = realpath(__DIR__ . '/..'); // apunta a /catalogo_detalle
if ($ROOT === false) {
  http_response_code(500);
  echo "Backoffice fatal: ROOT no resolvió.";
  exit;
}

// Carga bootstrap del proyecto (carrito)
$BOOT = $ROOT . '/api/_bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  echo "Backoffice fatal: no existe api/_bootstrap.php en: " . htmlspecialchars($BOOT);
  exit;
}

require $BOOT;

// Fuerza sesión (en algunos hosts no queda iniciada si bootstrap no la abre)
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Helpers mínimos
function bo_db(): mysqli {
  $db = db(); // debe venir del bootstrap
  if (!($db instanceof mysqli)) {
    throw new RuntimeException('Conexión DB inválida (mysqli).');
  }
  return $db;
}

function bo_is_logged(): bool {
  return !empty($_SESSION['bo_admin']['id']);
}

function bo_require_login(): void {
  if (!bo_is_logged()) {
    header('Location: login.php');
    exit;
  }
}

function bo_admin_id(): int {
  return (int)($_SESSION['bo_admin']['id'] ?? 0);
}

function bo_h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bo_csrf_token(): string {
  if (empty($_SESSION['bo_csrf'])) {
    $_SESSION['bo_csrf'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['bo_csrf'];
}

function bo_require_csrf(): void {
  $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $sess = $_SESSION['bo_csrf'] ?? '';
  if (!$sent || !$sess || !hash_equals((string)$sess, (string)$sent)) {
    http_response_code(400);
    echo "CSRF inválido";
    exit;
  }
}

function bo_audit(string $action, array $meta = []): void {
  try {
    $db = bo_db();
    $adminId = bo_admin_id() ?: null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $j = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

    $st = mysqli_prepare($db, "INSERT INTO backoffice_audit (admin_id, action, meta, ip) VALUES (?,?,?,?)");
    if ($st) {
      mysqli_stmt_bind_param($st, 'isss', $adminId, $action, $j, $ip);
      @mysqli_stmt_execute($st);
      @mysqli_stmt_close($st);
    }
  } catch (Throwable $e) {
    // silencio
  }
}
