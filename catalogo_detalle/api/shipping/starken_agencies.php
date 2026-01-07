<?php
/**
 * catalogo_detalle/api/shipping/starken_agencies.php
 * Obtiene lista de sucursales de Starken para una ciudad
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();

// Parámetros
$raw = file_get_contents('php://input');
$payload_in = json_decode($raw ?: '{}', true);
if (!is_array($payload_in)) {
  bad_request('Payload inválido');
}

$communeCodeDls = (int)($payload_in['commune_code_dls'] ?? 1);  // Code DLS de la comuna

if ($communeCodeDls <= 0) {
  bad_request('commune_code_dls es requerido');
}

// Traducir code_dls de la comuna a city_code_dls (usando la misma función que starken_quote.php)
function getCityDlsFromCommuneDls(mysqli $db, int $communeCodeDls): int {
  $db->query("CREATE TABLE IF NOT EXISTS starken_commune_city_map (
    commune_code_dls INT PRIMARY KEY,
    city_code_dls INT NOT NULL
  )");

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

  return $communeCodeDls;
}

$cityCityDls = getCityDlsFromCommuneDls($db, $communeCodeDls);

// Token de Starken
$starkenToken = '7b14bb8a-9df5-4cea-bb71-c6bc285b2ad7';
$starkenApiUrl = 'https://gateway.starken.cl/externo/integracion';

try {
  // Crear tabla de caché si no existe
  $createTableSQL = "CREATE TABLE IF NOT EXISTS starken_agencies_cache (
    city_code_dls INT PRIMARY KEY,
    agencies_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )";
  $db->query($createTableSQL);

  // Intentar obtener caché de la BD (máximo 24 horas)
  $qCache = "SELECT agencies_json FROM starken_agencies_cache WHERE city_code_dls=? AND updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1";
  $stCache = $db->prepare($qCache);
  if ($stCache) {
    $stCache->bind_param('i', $cityCityDls);
    $stCache->execute();
    $rowCache = $stCache->get_result()->fetch_assoc();
    $stCache->close();

    if ($rowCache) {
      $agencies = json_decode($rowCache['agencies_json'], true);
      if (is_array($agencies)) {
        json_out([
          'ok' => true,
          'agencies' => $agencies,
          'from_cache' => true
        ]);
      }
    }
  }

  // Si no hay caché válido, llamar a Starken
  if (!extension_loaded('curl')) {
    throw new RuntimeException('Extensión cURL no disponible');
  }

  $ch = curl_init();
  // Intentar primero con /agency/agency (endpoint general)
  curl_setopt($ch, CURLOPT_URL, $starkenApiUrl . '/agency/agency');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cache-Control: no-cache',
    'Authorization: Bearer ' . $starkenToken
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 300);  // 5 minutos
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  $curlInfo = curl_getinfo($ch);
  curl_close($ch);

  if ($curlError) {
    throw new RuntimeException("Error de conexión con Starken: {$curlError}");
  }

  if (empty($response)) {
    throw new RuntimeException("Starken devolvió respuesta vacía (HTTP {$httpCode}). URL: " . $curlInfo['url'] . ". Content-length: " . ($curlInfo['content_length_download'] ?? 'N/A'));
  }

  if ($httpCode < 200 || $httpCode >= 300) {
    throw new RuntimeException("Starken API respondió con HTTP {$httpCode}: " . substr($response, 0, 500));
  }

  $data = json_decode($response, true);
  if (!is_array($data)) {
    throw new RuntimeException("Respuesta de Starken tiene formato inválido. Response: " . substr($response, 0, 1000) . ". Type: " . gettype($response));
  }

  // Procesar agencias directamente (respuesta es un array plano)
  $agencies = [];
  if (is_array($data)) {
    foreach ($data as $agency) {
      $agencies[] = [
        'id' => (int)($agency['id'] ?? 0),
        'code_dls' => (int)($agency['code_dls'] ?? 0),
        'name' => (string)($agency['name'] ?? ''),
        'address' => (string)($agency['address'] ?? ''),
        'phone' => (string)($agency['phone'] ?? ''),
        'url_google_maps' => (string)($agency['url_google_maps'] ?? ''),
        'city_code_dls' => (int)($cityCityDls)  // Agregar city_code_dls para referencia
      ];
    }
  }

  // Guardar en caché de BD
  $agenciesJson = json_encode($agencies);
  $qInsert = "INSERT INTO starken_agencies_cache (city_code_dls, agencies_json, updated_at) VALUES (?, ?, NOW())
              ON DUPLICATE KEY UPDATE agencies_json=VALUES(agencies_json), updated_at=NOW()";
  $stInsert = $db->prepare($qInsert);
  if ($stInsert) {
    $stInsert->bind_param('is', $cityCityDls, $agenciesJson);
    $stInsert->execute();
    $stInsert->close();
  }

  json_out([
    'ok' => true,
    'agencies' => $agencies,
    'from_cache' => false,
    '_debug' => [
      'request_params' => [
        'commune_code_dls' => $communeCodeDls,
        'city_code_dls' => $cityCityDls
      ],
      'starken_response_type' => gettype($data),
      'starken_response_sample' => is_array($data) ? array_slice($data, 0, 2) : substr((string)$data, 0, 200)
    ]
  ]);

} catch (Throwable $e) {
  json_out([
    'ok' => false,
    'error' => $e->getMessage(),
    '_request_params' => [
      'commune_code_dls' => $communeCodeDls ?? null,
      'city_code_dls' => $cityCityDls ?? null,
      'http_code' => $httpCode ?? null
    ]
  ], 400);
}
