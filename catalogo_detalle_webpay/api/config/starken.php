<?php
/**
 * catalogo_detalle/api/config/starken.php
 * Devuelve configuración de Starken (origen, etc)
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

$db = db();

// Cargar .env (subir 3 niveles: /api/config/ -> /catalogo_detalle/ -> /clientes/)
$envFile = __DIR__ . '/../../../.env';
$starkenOriginCityCodeDls = null;

if (file_exists($envFile)) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), 'STARKEN_ORIGIN_CITY_CODE_DLS') === 0) {
      $parts = explode('=', $line, 2);
      if (count($parts) === 2) {
        $rawValue = trim($parts[1], '"\'');
        $starkenOriginCityCodeDls = (int)$rawValue;
      }
    }
  }
}

// Validar que esté configurado
if (!$starkenOriginCityCodeDls) {
  json_out([
    'ok' => false,
    'error' => 'STARKEN_ORIGIN_CITY_CODE_DLS no está configurado en .env o tiene valor inválido'
  ], 500);
}

json_out([
  'ok' => true,
  'origin_city_code_dls' => $starkenOriginCityCodeDls
]);
