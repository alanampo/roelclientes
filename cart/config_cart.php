<?php
declare(strict_types=1);

// 'DB_USER' => 'plantiroel_user',
//  'DB_PASS' => 'S-23%AOD,L[m',

$ENV = [
  'DB_HOST' => '127.0.0.1',
  'DB_NAME' => 'plantiroel_cart',
  'DB_USER' => 'root',
  'DB_PASS' => '',
  'DB_CHARSET' => 'utf8mb4',
  'CART_COOKIE_NAME' => 'rp_cart',
  'CART_TTL_MINUTES' => 120,
  'SESSION_TTL_MINUTES' => 240,
  // Flow - Credenciales del proyecto (PRODUCCIÓN - mismo que en CodeIgniter)
  'FLOW_API_KEY' => '29FCFEE8-AB36-4F72-9969-5B4CEL225C9E',
  'FLOW_SECRET' => '5602ea935de56f485a55c6b29864a396955b41bd',
  'FLOW_SANDBOX' => false,  // PRODUCCIÓN - Los pagos serán reales
  'FLOW_RETURN_URL' => 'https://plantinera.cl/bot-alan/public/cart/flow_return.php',
  'FLOW_CONFIRM_URL' => 'https://plantinera.cl/bot-alan/public/cart/flow_confirm.php'
];

function db(): PDO {
  global $ENV;
  static $pdo = null;
  if ($pdo) return $pdo;
  $dsn = "mysql:host={$ENV['DB_HOST']};dbname={$ENV['DB_NAME']};charset={$ENV['DB_CHARSET']}";
  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, $ENV['DB_USER'], $ENV['DB_PASS'], $opt);
  return $pdo;
}

function json_out($arr, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

function rand_token(int $bytes=32): string { return bin2hex(random_bytes($bytes)); }
function get_client_ip(): string {
  $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
  if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
  return substr($ip, 0, 45);
}
