<?php
/**
 * catalogo_detalle/api/shipping/starken_quote.php
 * Cotiza envío con Starken API
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();

// Leer parámetros del body POST en JSON
$raw = file_get_contents('php://input');
$payload_in = json_decode($raw ?: '{}', true);
if (!is_array($payload_in)) {
  bad_request('Payload inválido');
}

// Parámetros
$originCommuneCodeDls = (int)($payload_in['origin'] ?? 1);
$destinationCommuneCodeDls = (int)($payload_in['destination'] ?? 1);
$quantity = (int)($payload_in['quantity'] ?? 0);

// Dimensiones opcionalmente recibidas del frontend
$clientWeight = isset($payload_in['weight']) ? (float)$payload_in['weight'] : null;
$clientHeight = isset($payload_in['height']) ? (float)$payload_in['height'] : null;
$clientWidth = isset($payload_in['width']) ? (float)$payload_in['width'] : null;
$clientDepth = isset($payload_in['depth']) ? (float)$payload_in['depth'] : null;

// Validar
if ($destinationCommuneCodeDls <= 0) {
  bad_request('Destino es requerido');
}

if ($originCommuneCodeDls <= 0) {
  bad_request('Origen es requerido');
}

if ($quantity <= 0) {
  bad_request('Cantidad es requerida y debe ser mayor a 0');
}

// Especificaciones de cajas (IDÉNTICAS al frontend)
$boxSpecs = [
  'pequeña' => ['height' => 13, 'width' => 29, 'depth' => 27, 'weight' => 1.0],
  'mediana' => ['height' => 13, 'width' => 29, 'depth' => 54, 'weight' => 2.5],
  'grande' => ['height' => 26, 'width' => 29, 'depth' => 54, 'weight' => 3.0]
];

// Calcular tipo de caja y dimensiones basado en cantidad (MISMA LÓGICA que frontend)
function calculateShippingDimensions(int $qty, array $boxSpecs): array {
  if ($qty <= 0) {
    return ['boxType' => '', 'boxCount' => 0, 'weight' => 0, 'height' => 0, 'width' => 0, 'depth' => 0];
  }

  $boxType = '';
  $boxCount = 0;

  if ($qty <= 50) {
    $boxType = 'pequeña';
    $boxCount = 1;
  } elseif ($qty <= 100) {
    $boxType = 'mediana';
    $boxCount = 1;
  } else {
    $boxType = 'grande';
    $boxCount = (int)ceil($qty / 100);
  }

  $specs = $boxSpecs[$boxType];
  $totalWeight = $specs['weight'] * $boxCount;
  $totalHeight = $specs['height'] * $boxCount;
  $totalWidth = $specs['width'];
  $totalDepth = $specs['depth'];

  return [
    'boxType' => $boxType,
    'boxCount' => $boxCount,
    'weight' => $totalWeight,
    'height' => $totalHeight,
    'width' => $totalWidth,
    'depth' => $totalDepth
  ];
}

$dimensions = calculateShippingDimensions($quantity, $boxSpecs);

// Usar dimensiones del cliente si se enviaron, sino usar las calculadas
$weight = $clientWeight !== null ? $clientWeight : (float)$dimensions['weight'];
$height = $clientHeight !== null ? $clientHeight : (float)$dimensions['height'];
$width = $clientWidth !== null ? $clientWidth : (float)$dimensions['width'];
$depth = $clientDepth !== null ? $clientDepth : (float)$dimensions['depth'];

// Traducir code_dls de comunas a city_code_dls
function getCityDlsFromCommuneDls(mysqli $db, int $communeCodeDls): int {
  // Crear tabla de mapeo si no existe
  $db->query("CREATE TABLE IF NOT EXISTS starken_commune_city_map (
    commune_code_dls INT PRIMARY KEY,
    city_code_dls INT NOT NULL
  )");

  // Intentar obtener del caché
  $q = "SELECT city_code_dls FROM starken_commune_city_map WHERE commune_code_dls=? LIMIT 1";
  $st = $db->prepare($q);
  if ($st) {
    $st->bind_param('i', $communeCodeDls);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
      return (int)$row['city_code_dls'];
    }
  }

  // Si no está en caché, retornar el mismo valor (probablemente sea city_code_dls ya)
  return $communeCodeDls;
}

$originCityDls = getCityDlsFromCommuneDls($db, $originCommuneCodeDls);
$destinationCityDls = getCityDlsFromCommuneDls($db, $destinationCommuneCodeDls);

// Token de Starken (por ahora hardcodeado, después desde config)
$starkenToken = '7b14bb8a-9df5-4cea-bb71-c6bc285b2ad7';
$starkenApiUrl = 'https://gateway.starken.cl/externo/integracion';

// Payload según formato de Starken (cotizador-multiple)
$payload = [
  'origen' => (int)$originCityDls,
  'destino' => (int)$destinationCityDls,
  'bulto' => 'BULTO',
  'kilos' => (float)number_format($weight, 2),
  'alto' => (float)number_format($height, 2),
  'ancho' => (float)number_format($width, 2),
  'largo' => (float)number_format($depth, 2),
  'todas_alternativas' => true
];

try {
  if (!extension_loaded('curl')) {
    throw new RuntimeException('Extensión cURL no disponible');
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $starkenApiUrl . '/quote/cotizador-multiple');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cache-Control: no-cache',
    'Authorization: Bearer ' . $starkenToken
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_TIMEOUT, 300);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    throw new RuntimeException("Error de conexión con Starken: {$curlError}");
  }

  // Aceptar códigos 2xx (200, 201, etc)
  if ($httpCode < 200 || $httpCode >= 300) {
    $errorMsg = "Starken API respondió con HTTP {$httpCode}";
    if (!empty($response)) {
      $errorMsg .= ": " . substr($response, 0, 200);
    }
    throw new RuntimeException($errorMsg);
  }

  $data = json_decode($response, true);
  if (!is_array($data)) {
    throw new RuntimeException("Respuesta de Starken tiene formato inválido");
  }

  // Log success for debugging (can be removed in production)
  // error_log("Starken quote success: destination={$destination}, origin={$origin}");

  json_out([
    'ok' => true,
    'destination_city_dls' => (int)$destinationCityDls,
    'origin_city_dls' => (int)$originCityDls,
    'box_type' => $dimensions['boxType'],
    'box_count' => (int)$dimensions['boxCount'],
    'weight' => (float)$dimensions['weight'],
    'height' => (float)$dimensions['height'],
    'width' => (float)$dimensions['width'],
    'depth' => (float)$dimensions['depth'],
    'data' => $data
  ]);

} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
