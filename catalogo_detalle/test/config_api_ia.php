<?php
// api_ia/config_api_ia.php
declare(strict_types=1);

/* ===== Robust JSON error/fatal handlers ===== */
$IA_DEBUG = (isset($_GET['debug']) && (string)($_GET['debug']) === '1');
@ini_set('display_errors','0');
@ini_set('log_errors','1');

set_error_handler(static function(int $severity, string $message, string $file, int $line) use (&$IA_DEBUG): bool {
  if (!(error_reporting() & $severity)) return false;
  if ($IA_DEBUG) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  }
  error_log('[api_ia] PHP Warning: '.$message.' in '.$file.':'.$line);
  return true;
});

set_exception_handler(static function(Throwable $e) use (&$IA_DEBUG): void {
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
  $msg = $IA_DEBUG ? ($e::class.': '.$e->getMessage()) : 'Error interno';
  echo json_encode([
    'status' => 'error',
    'message' => $msg,
    'meta' => ['generated_at' => gmdate('c')],
  ], JSON_UNESCAPED_UNICODE);
});

register_shutdown_function(static function() use (&$IA_DEBUG): void {
  $err = error_get_last();
  if (!$err) return;
  $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
  if (!in_array($err['type'] ?? 0, $fatal, true)) return;
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
  $msg = $IA_DEBUG ? ('Fatal: '.($err['message'] ?? 'unknown')) : 'Error interno';
  echo json_encode([
    'status' => 'error',
    'message' => $msg,
    'debug' => $IA_DEBUG ? $err : null,
    'meta' => ['generated_at' => gmdate('c')],
  ], JSON_UNESCAPED_UNICODE);
});

/**
 * Config EXCLUSIVO para endpoints IA dentro de /api_ia
 *
 * IMPORTANTE (tu caso):
 * - El usuario roeluser1_usercli NO tiene permisos SELECT sobre roeluser1_crm ni roeluser1_carrito_mayorista.
 * - Por eso fallaban los "probes" y la resolución de schema.
 *
 * Solución:
 * - Abrimos 2 conexiones PDO separadas:
 *     $DB_CRM   -> roeluser1_crm (user roeluser1_iauser)
 *     $DB_SALES -> roeluser1_carrito_mayorista (user roeluser1_cart_user)
 *
 * Exports:
 * - $DB_CRM (PDO), $DB_SALES (PDO)
 * - env: CRM_DB_NAME, SALES_DB_NAME (si no vienen definidos)
 * - helpers: ia_table_exists(), ia_normalize_phone()
 */

date_default_timezone_set('America/Santiago');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Lee env y SOLO la usa si viene definida y NO vacía.
 * (Evita que PHP-FPM "pise" credenciales con env vacía)
 */
function ia_env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v === false) return $default;
    $v = (string)$v;
    if (trim($v) === '') return $default;
    return $v;
}

function ia_json_fail(string $msg, ?string $detail = null, int $code = 500): void {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $msg,
        'detail' => $detail,
        'meta' => ['generated_at' => gmdate('c')],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =======================
// 1) Credenciales MySQL (CRM)
// =======================
$CRM_DB_HOST = ia_env('CRM_DB_HOST', '127.0.0.1');
$CRM_DB_NAME = ia_env('CRM_DB_NAME', 'roeluser1_crm');
$CRM_DB_USER = ia_env('CRM_DB_USER', 'roeluser1_iauser');
$CRM_DB_PASS = ia_env('CRM_DB_PASS', 'a1U}(j8ofq%23HV$!');

// =======================
// 2) Credenciales MySQL (VENTAS / CARRITO)
// =======================
$SALES_DB_HOST = ia_env('SALES_DB_HOST', '127.0.0.1');
$SALES_DB_NAME = ia_env('SALES_DB_NAME', 'roeluser1_carrito_mayorista');
$SALES_DB_USER = ia_env('SALES_DB_USER', 'roeluser1_cart_user');
$SALES_DB_PASS = ia_env('SALES_DB_PASS', 'g]3,+[-*NneM@sA{');

// Exporta env para consumo por endpoints (si no venían ya)
if (getenv('CRM_DB_NAME') === false || trim((string)getenv('CRM_DB_NAME')) === '') {
    putenv('CRM_DB_NAME='.$CRM_DB_NAME);
}
if (getenv('SALES_DB_NAME') === false || trim((string)getenv('SALES_DB_NAME')) === '') {
    putenv('SALES_DB_NAME='.$SALES_DB_NAME);
}

// =======================
// 3) Conexiones PDO
// =======================
try {
    $dsn = "mysql:host={$CRM_DB_HOST};dbname={$CRM_DB_NAME};charset=utf8mb4";
    $DB_CRM  = new PDO($dsn, $CRM_DB_USER, $CRM_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (Throwable $e) {
    ia_json_fail('❌ No se pudo conectar a CRM_DB', $e->getMessage());
}

try {
    $dsn = "mysql:host={$SALES_DB_HOST};dbname={$SALES_DB_NAME};charset=utf8mb4";
    $DB_SALES  = new PDO($dsn, $SALES_DB_USER, $SALES_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (Throwable $e) {
    ia_json_fail('❌ No se pudo conectar a SALES_DB', $e->getMessage());
}

// =======================
// 4) Helpers compartidos
// =======================

/**
 * Chequea existencia/permisos de una tabla en la conexión actual.
 * Retorna true si el usuario puede ejecutar "SELECT 1 FROM `table` LIMIT 1"
 */
function ia_table_exists(PDO $db, string $table): bool {
    $table = trim($table);
    if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    try {
        $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Normaliza teléfono Chile para matching:
 * - digits: solo dígitos (ej 56912345678)
 * - local: 9 dígitos sin 56 (ej 912345678) cuando aplica
 * - e164: +56 + local si local tiene 9 dígitos
 */
function ia_normalize_phone(string $raw): array {
    $d = preg_replace('/\D+/', '', $raw);
    $d = $d ? $d : '';
    if ($d === '') return ['digits'=>'', 'local'=>'', 'e164'=>''];

    if (strpos($d, '00') === 0) $d = substr($d, 2);

    $local = $d;
    if (strpos($d, '56') === 0 && strlen($d) >= 11) {
        $local = substr($d, 2);
    }

    $e164 = (strlen($local) === 9) ? ('+56'.$local) : '';
    return ['digits'=>$d, 'local'=>$local, 'e164'=>$e164];
}

/**
 * Devuelve info de sesión MySQL (útil para debug).
 */
function ia_db_session(PDO $db): array {
    try {
        $row = $db->query("SELECT CURRENT_USER() AS cu, USER() AS u, DATABASE() AS db, @@hostname AS hn")->fetch(PDO::FETCH_ASSOC);
        return [
            'current_user' => $row['cu'] ?? null,
            'user' => $row['u'] ?? null,
            'current_db' => $row['db'] ?? null,
            'hostname' => $row['hn'] ?? null,
        ];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Show grants (solo debug).
 */
function ia_db_grants(PDO $db): array {
    try {
        $rows = $db->query("SHOW GRANTS")->fetchAll(PDO::FETCH_NUM);
        $out = [];
        foreach ($rows as $r) $out[] = $r[0] ?? '';
        return $out;
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}
