<?php
// catalogo_detalle/backoffice/_helpers.php
declare(strict_types=1);

require __DIR__ . '/_bo_bootstrap.php';

function bo_badge(string $status): string {
  $s = strtolower(trim($status));
  $cls = 'badge';
  if (in_array($s, ['paid','completed','done','delivered','ok'], true)) $cls .= ' ok';
  else if (in_array($s, ['new','pending','processing','wip'], true)) $cls .= ' warn';
  else if (in_array($s, ['cancelled','canceled','rejected','failed','error'], true)) $cls .= ' bad';

  return '<span class="'.h($cls).'">'.h($status).'</span>';
}

function bo_int($v, int $default=0): int {
  if ($v === null) return $default;
  $n = filter_var($v, FILTER_VALIDATE_INT);
  return ($n === false) ? $default : (int)$n;
}

/**
 * true si la tabla existe en la BD actual.
 * (Protege el backoffice en ambientes donde aún no importan el SQL completo.)
 */
function bo_table_exists(mysqli $db, string $table): bool {
  $table = trim($table);
  if ($table === '') return false;
  $dbNameRes = mysqli_query($db, "SELECT DATABASE() AS db");
  $dbRow = $dbNameRes ? mysqli_fetch_assoc($dbNameRes) : null;
  $dbName = $dbRow['db'] ?? '';
  if ($dbName === '') return false;

  $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1";
  $st = mysqli_prepare($db, $sql);
  if (!$st) return false;
  mysqli_stmt_bind_param($st, 'ss', $dbName, $table);
  mysqli_stmt_execute($st);
  mysqli_stmt_store_result($st);
  $ok = (mysqli_stmt_num_rows($st) > 0);
  mysqli_stmt_close($st);
  return $ok;
}

/**
 * Ejecuta COUNT(*) de forma segura, devolviendo 0 si la tabla no existe.
 */
function bo_safe_count(mysqli $db, string $table, string $where='1=1'): int {
  if (!bo_table_exists($db, $table)) return 0;
  $sql = "SELECT COUNT(*) AS c FROM `".str_replace('`','',$table)."` WHERE {$where}";
  $row = dbq($sql);
  return (int)($row['c'] ?? 0);
}

// ---------------- Backoffice Auth ----------------

function is_logged_admin(): bool {
  return isset($_SESSION['bo_admin']) && $_SESSION['bo_admin'] === 1;
}

function require_auth_admin(): void {
  if (!is_logged_admin()) {
    header('Location: login.php');
    exit;
  }
}

/**
 * Valida credenciales de backoffice.
 * Soporta password plano o hash generado con password_hash() en BO_ADMIN_PASS.
 */
function bo_login_ok(string $user, string $pass): bool {
  $u = defined('BO_ADMIN_USER') ? (string)BO_ADMIN_USER : '';
  $p = defined('BO_ADMIN_PASS') ? (string)BO_ADMIN_PASS : '';

  if ($u === '' || $p === '') return false;
  if (!hash_equals($u, $user)) return false;

  // Si parece hash bcrypt/argon
  if (preg_match('/^\$(2y|2a|argon2id)\$/', $p)) {
    return password_verify($pass, $p);
  }
  return hash_equals($p, $pass);
}

function bo_login_set(): void {
  $_SESSION['bo_admin'] = 1;
  $_SESSION['bo_admin_since'] = time();
}

function bo_logout(): void {
  unset($_SESSION['bo_admin'], $_SESSION['bo_admin_since']);
}


// Backwards-compatible aliases (por si algún archivo antiguo los usa)
function require_admin(): void { require_auth_admin(); }
function bo_is_admin(): bool { return is_logged_admin(); }
