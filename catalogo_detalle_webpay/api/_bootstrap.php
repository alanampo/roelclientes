<?php
// catalogo_detalle/api/_bootstrap.php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
  header('Content-Type: application/json; charset=utf-8');
} else {
  header('Content-Type: text/html; charset=utf-8');
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

ini_set('display_errors','0'); // producción: 0
error_reporting(E_ALL);

// Cargar archivo .env desde la raíz del proyecto
$envPaths = [
  __DIR__ . '/../../.env',
  __DIR__ . '/../../../.env',
  __DIR__ . '/../../../../.env',
];

foreach ($envPaths as $envPath) {
  if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      // Ignorar comentarios
      if (strpos(trim($line), '#') === 0) continue;

      // Parsear KEY="VALUE" o KEY=VALUE
      if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remover comillas si existen
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
          $value = substr($value, 1, -1);
        }

        // Solo setear si no existe ya
        if (!getenv($key)) {
          putenv("{$key}={$value}");
        }
      }
    }
    break;
  }
}

require __DIR__ . '/../config/cart_db.php';

function json_out(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function bad_request(string $msg, array $extra = []): void {
  json_out(['ok'=>false,'error'=>$msg] + $extra, 400);
}

function unauthorized(string $msg = 'No autorizado'): void {
  json_out(['ok'=>false,'error'=>$msg], 401);
}

function require_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok'=>false,'error'=>'Método no permitido'], 405);
  }
}

function start_session(): void {
  // Detect HTTPS correctly even behind proxies (Cloudflare / reverse proxy)
  $secure = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

  // Aislar la sesión de este módulo (evita choques con otros PHP apps en el mismo dominio)
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('ROELCARTSESSID');
  }

  // Cookie path: '/' para que la sesión sea válida aunque muevas el sistema de carpeta (cart3 -> cart4, etc.)
  // El nombre de sesión es único, así que no afecta a otros sistemas.
  // Lifetime: 7 días (604800 segundos)
  $sessionLifetime = 60 * 60 * 24 * 7; // 7 días

  session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

// Cache raw body so CSRF validation can safely peek JSON payload without consuming it twice.
$GLOBALS['__raw_body_cache'] = null;
function get_raw_body(): string {
  if ($GLOBALS['__raw_body_cache'] !== null) return (string)$GLOBALS['__raw_body_cache'];
  $GLOBALS['__raw_body_cache'] = file_get_contents('php://input') ?: '';
  return (string)$GLOBALS['__raw_body_cache'];
}

function csrf_token(): string {
  start_session();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf'];
}

function require_csrf(): void {
  start_session();
  $want = (string)($_SESSION['csrf'] ?? '');
  if ($want === '') throw new RuntimeException('CSRF inválido');

  // 1) Header-based (preferred)
  $got = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if ($got === '') $got = (string)($_SERVER['HTTP_X_CSRF-TOKEN'] ?? '');

  // 2) Form/query fallback
  if ($got === '') $got = (string)($_POST['csrf'] ?? $_GET['csrf'] ?? '');

  // 3) JSON body fallback: {"csrf":"..."} (safe because we cache raw body)
  if ($got === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = get_raw_body();
    if ($raw !== '') {
      $j = json_decode($raw, true);
      if (is_array($j) && isset($j['csrf'])) $got = (string)$j['csrf'];
    }
  }

  if ($got === '' || !hash_equals($want, $got)) throw new RuntimeException('CSRF inválido');
}


function db(): mysqli {
  static $db = null;
  if ($db instanceof mysqli) return $db;

  $db = @mysqli_connect(CART_DB_HOST, CART_DB_USER, CART_DB_PASS, CART_DB_NAME);
  if (!$db) {
    json_out(['ok'=>false,'error'=>'Error conexión BD carrito'], 500);
  }
  mysqli_set_charset($db, 'utf8mb4');
  return $db;
}

function rut_clean(string $rut): string {
  $rut = strtoupper(trim($rut));
  $rut = preg_replace('/[^0-9K]/', '', str_replace(['.','-',' '], '', $rut));
  return (string)$rut;
}

function rut_format(string $rutClean): string {
  $rutClean = strtoupper(trim($rutClean));
  if (!preg_match('/^\d{7,8}[0-9K]$/', $rutClean)) return $rutClean;
  $dv = substr($rutClean, -1);
  $num = substr($rutClean, 0, -1);
  return number_format((int)$num, 0, '', '.') . '-' . $dv;
}

function rut_is_valid(string $rutClean): bool {
  $rutClean = strtoupper(trim($rutClean));
  if (!preg_match('/^\d{7,8}[0-9K]$/', $rutClean)) return false;
  $dv = substr($rutClean, -1);
  $num = substr($rutClean, 0, -1);

  $sum = 0;
  $mul = 2;
  for ($i = strlen($num)-1; $i >= 0; $i--) {
    $sum += ((int)$num[$i]) * $mul;
    $mul = ($mul === 7) ? 2 : ($mul + 1);
  }
  $res = 11 - ($sum % 11);
  $dvCalc = ($res === 11) ? '0' : (($res === 10) ? 'K' : (string)$res);
  return $dvCalc === $dv;
}

function email_normalize(string $email): string {
  $email = trim(mb_strtolower($email));
  return $email;
}

function email_is_valid(string $email): bool {
  return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function require_auth(): int {
  start_session();
  $cid = (int)($_SESSION['customer_id'] ?? 0);
  if ($cid <= 0) unauthorized();
  return $cid;
}

function cart_get_or_create(mysqli $db, int $customerId): int {
  // 1) buscar carrito open (usa id_cliente en tabla carrito_carts de roel)
  $q = "SELECT id FROM " . CART_TABLE . " WHERE id_cliente=? AND status='open' LIMIT 1";
  $st = $db->prepare($q);
  $st->bind_param('i', $customerId);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();

  if ($row) {
    return (int)$row['id'];
  }

  // 2) crear
  $q2 = "INSERT INTO " . CART_TABLE . " (id_cliente, status) VALUES (?, 'open')";
  $st2 = $db->prepare($q2);
  $st2->bind_param('i', $customerId);
  if (!$st2->execute()) {
    json_out(['ok'=>false,'error'=>'No se pudo crear carrito'], 500);
  }
  $insertId = (int)$st2->insert_id;
  $st2->close();
  return $insertId;
}


function cart_snapshot(mysqli $db, int $cartId): array {
  $q = "SELECT ci.id, ci.id_variedad, ci.referencia, ci.nombre, ci.imagen_url, ci.unit_price_clp, ci.qty,
                GROUP_CONCAT(DISTINCT
                  CASE
                    WHEN a.nombre IS NULL THEN NULL
                    WHEN a.nombre = 'TIPO DE PLANTA' THEN NULL
                    WHEN NULLIF(TRIM(av.valor),'') IS NULL THEN NULL
                    ELSE CONCAT(a.nombre, ': ', TRIM(av.valor))
                  END
                  ORDER BY a.nombre
                  SEPARATOR '||'
                ) AS attrs_activos
         FROM " . CART_ITEMS_TABLE . " ci
         LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = ci.id_variedad
         LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
         LEFT JOIN atributos a ON a.id = av.id_atributo
         WHERE ci.cart_id = ?
         GROUP BY ci.id, ci.id_variedad, ci.referencia, ci.nombre, ci.imagen_url, ci.unit_price_clp, ci.qty
         ORDER BY ci.updated_at DESC";

  $st = $db->prepare($q);
  $st->bind_param('i', $cartId);
  $st->execute();
  $res = $st->get_result();

  $items = [];
  $total = 0;
  $count = 0;
  while ($r = $res->fetch_assoc()) {
    $qty = (int)$r['qty'];
    $price = (int)$r['unit_price_clp'];
    $line = $qty * $price;
    $total += $line;
    $count += $qty;
    $items[] = [
      'item_id'=>(int)$r['id'],
      'id_variedad'=>(int)$r['id_variedad'],
      'referencia'=>(string)$r['referencia'],
      'nombre'=>(string)$r['nombre'],
      'imagen_url'=>$r['imagen_url'],
      'unit_price_clp'=>$price,
      'qty'=>$qty,
      'line_total_clp'=>$line,
      'attrs_activos'=>(string)($r['attrs_activos'] ?? ''),
    ];
  }

  return [
    'id'=>$cartId,
    'item_count'=>$count,
    'total_clp'=>$total,
    'items'=>$items,
  ];
}