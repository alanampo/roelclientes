<?php
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

$SECRET = getenv('NOTIFY_HTTP_KEY') ?: 'dsdsSYy765rfjiUUUYYYTRRddddcvbh65eddD';

$MAP = [
  'new_customer' => __DIR__ . '/notify_new_customer_cart.php',
  'new_order'    => __DIR__ . '/notify_new_order_cart.php',
  'new_cart'     => __DIR__ . '/notify_new_cart.php',
  'abandoned'    => __DIR__ . '/notify_abandoned_carts.php',
  'status'       => __DIR__ . '/notify_order_status_changes_cart.php',
  'kpi'          => __DIR__ . '/notify_daily_kpi_cart.php',
];

function isCli(): bool { return PHP_SAPI === 'cli'; }
function param(string $name, string $default=''): string {
  if (isCli()) {
    global $argv;
    foreach (($argv ?? []) as $a) {
      if ($a === "--{$name}") return '1';
      if (strpos($a, "--{$name}=") === 0) return substr($a, strlen($name) + 3);
    }
    return $default;
  }
  return (string)($_GET[$name] ?? $default);
}
function fail(int $code, string $msg): void {
  if (!isCli()) http_response_code($code);
  if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

$debug = param('debug','') === '1' || param('debug','') === 'true';

ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function(Throwable $e) use ($debug) {
  if (!headers_sent()) http_response_code(500);
  if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
  echo "HTTP 500\n";
  echo $debug ? ("EXCEPTION: ".$e->getMessage()."\n".$e->getTraceAsString()."\n") : "Error interno.\n";
  error_log("notify/run.php exception: ".$e->getMessage()." | ".$e->getTraceAsString());
  exit;
});

$k = param('k','');
if ($k === '' || !hash_equals($SECRET, $k)) fail(403, 'Forbidden');

$job = param('job','');
if ($job === '' || !isset($MAP[$job])) fail(400, 'job inválido. Opciones: ' . implode(',', array_keys($MAP)));

$script = $MAP[$job];
if (!is_file($script)) fail(500, "Script no encontrado: {$script}");

require $script;

echo "OK\n";
