<?php
// catalogo_detalle/backoffice/_bo_bootstrap.php
// Bootstrap exclusivo para Backoffice (HTML).
// NO incluir ../api/_bootstrap.php porque ese archivo fija Content-Type JSON.

declare(strict_types=1);

// Detecta dinámicamente la ruta base del backoffice desde $_SERVER['SCRIPT_NAME']
// Ejemplo: /catalogo_detalle_webpay/backoffice/login.php => /catalogo_detalle_webpay/backoffice
$BACKOFFICE_PATH = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($BACKOFFICE_PATH === '.') {
  $BACKOFFICE_PATH = '';
}

date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/../config/cart_db.php';

/**
 * Sesión compartida con el frontend.
 * Mantiene el mismo nombre para que el usuario/cliente y el admin
 * puedan coexistir sin conflicto.
 */
function start_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  // Mismo nombre que el frontend del carrito (si existe):
  // Si en el frontend cambia el nombre, actualizar acá también.
  if (function_exists('session_name')) {
    session_name('ROELCARTSESSID');
  }

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

start_session();

// ---------- DB (mysqli) ----------
function db(): mysqli {
  static $db = null;
  if ($db instanceof mysqli) return $db;

  $db = mysqli_connect(CART_DB_HOST, CART_DB_USER, CART_DB_PASS, CART_DB_NAME);
  if (!$db) {
    throw new RuntimeException('Error conexión BD backoffice: ' . mysqli_connect_error());
  }
  mysqli_set_charset($db, 'utf8mb4');
  return $db;
}

function dbq(string $sql): mysqli_result {
  $res = mysqli_query(db(), $sql);
  if (!$res) {
    throw new RuntimeException('SQL error: ' . mysqli_error(db()));
  }
  return $res;
}

// ---------- CSRF ----------
function csrf_token(): string {
  if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

function require_csrf_post(): void {
  $tok = (string)($_POST['csrf'] ?? '');
  if ($tok === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $tok)) {
    throw new RuntimeException('CSRF inválido');
  }
}

function require_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    throw new RuntimeException('Método inválido');
  }
}

// ---------- Auth Backoffice ----------
function bo_admin_id(): ?int {
  $id = $_SESSION['bo_admin_id'] ?? null;
  return is_int($id) ? $id : (ctype_digit((string)$id) ? (int)$id : null);
}

function require_auth_admin(): int {
  $id = bo_admin_id();
  if (!$id) {
    $to = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
    header('Location: ' . $GLOBALS['BACKOFFICE_PATH'] . '/login.php?next=' . $to);
    exit;
  }
  return $id;
}

function bo_logout(): void {
  unset($_SESSION['bo_admin_id']);
}

// ---------- Schema Backoffice (idempotente) ----------
function bo_ensure_schema(): void {
  // Tabla de admins
  $sql = "
    CREATE TABLE IF NOT EXISTS bo_admin_users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(64) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      name VARCHAR(120) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      last_login_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  mysqli_query(db(), $sql);

  // Auditoría simple
  $sql2 = "
    CREATE TABLE IF NOT EXISTS bo_audit (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      admin_id INT NULL,
      action VARCHAR(64) NOT NULL,
      entity VARCHAR(64) NULL,
      entity_id VARCHAR(64) NULL,
      meta TEXT NULL,
      ip VARCHAR(64) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_admin (admin_id),
      KEY idx_action (action),
      KEY idx_entity (entity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  mysqli_query(db(), $sql2);
}

function bo_seed_default_admin(): void {
  bo_ensure_schema();
  $res = mysqli_query(db(), "SELECT COUNT(*) c FROM bo_admin_users");
  $row = $res ? mysqli_fetch_assoc($res) : null;
  $count = (int)($row['c'] ?? 0);
  if ($count > 0) return;

  // Credenciales por defecto (cambiar apenas entres)
  $user = 'admin';
  $pass = 'ChangeMe123!';
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $st = mysqli_prepare(db(), "INSERT INTO bo_admin_users (username, password_hash, name) VALUES (?,?,?)");
  $name = 'Administrador';
  mysqli_stmt_bind_param($st, 'sss', $user, $hash, $name);
  mysqli_stmt_execute($st);
  mysqli_stmt_close($st);

  // Guarda para mostrar una sola vez en login
  $_SESSION['bo_seed_notice'] = 'Se creó usuario admin por defecto: admin / ChangeMe123! (cámbialo)';
}

// Asegura tablas y usuario default
bo_seed_default_admin();

// ---------- Helpers UI ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function money_clp(int $n): string {
  return '$' . number_format($n, 0, ',', '.');
}

function now_iso(): string { return date('Y-m-d H:i:s'); }

function bo_audit(string $action, ?string $entity=null, ?string $entityId=null, ?array $meta=null): void {
  bo_ensure_schema();
  $st = mysqli_prepare(db(), "INSERT INTO bo_audit (admin_id, action, entity, entity_id, meta, ip) VALUES (?,?,?,?,?,?)");
  $aid = bo_admin_id();
  $m = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  mysqli_stmt_bind_param($st, 'isssss', $aid, $action, $entity, $entityId, $m, $ip);
  @mysqli_stmt_execute($st);
  mysqli_stmt_close($st);
}
