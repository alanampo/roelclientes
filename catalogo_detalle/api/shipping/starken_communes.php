<?php
/**
 * catalogo_detalle/api/shipping/starken_communes.php
 * Obtiene lista de comunas de la API de Starken para llenar selects
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db = db();

// Token de Starken
$starkenToken = '7b14bb8a-9df5-4cea-bb71-c6bc285b2ad7';
$starkenApiUrl = 'https://gateway.starken.cl/externo/integracion';

try {
  // Crear tabla de caché si no existe
  $createTableSQL = "CREATE TABLE IF NOT EXISTS starken_cache (
    id INT PRIMARY KEY,
    communes_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )";
  $db->query($createTableSQL);

  // Intentar obtener caché de la BD (máximo 24 horas)
  $qCache = "SELECT communes_json FROM starken_cache WHERE id=1 AND updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1";
  $stCache = $db->prepare($qCache);
  if ($stCache) {
    $stCache->execute();
    $rowCache = $stCache->get_result()->fetch_assoc();
    $stCache->close();

    if ($rowCache) {
      $communes = json_decode($rowCache['communes_json'], true);
      if (is_array($communes)) {
        json_out([
          'ok' => true,
          'communes' => $communes,
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
  curl_setopt($ch, CURLOPT_URL, $starkenApiUrl . '/agency/comuna');
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
  curl_close($ch);

  if ($curlError) {
    throw new RuntimeException("Error de conexión con Starken: {$curlError}");
  }

  if ($httpCode !== 200) {
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

  // Debug: guardar respuesta completa para inspección
  file_put_contents('/tmp/starken_communes_full.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

  $firstKeys = array_slice(array_keys($data), 0, 5);
  file_put_contents('/tmp/starken_debug.txt', "Type: " . gettype($data) . "\n");
  file_put_contents('/tmp/starken_debug.txt', "First keys: " . json_encode($firstKeys) . "\n", FILE_APPEND);
  file_put_contents('/tmp/starken_debug.txt', "Sample: " . substr(json_encode($data), 0, 1000) . "\n", FILE_APPEND);
  file_put_contents('/tmp/starken_debug.txt', "First item sample: " . json_encode(reset($data)) . "\n", FILE_APPEND);

  // Transformar datos: extraer solo nombre y code_dls de cada comuna/ciudad
  $communes = [];

  // La respuesta puede ser un array de regiones directamente o tener otra estructura
  foreach ($data as $key => $item) {
    // Si es un región con array de comunas
    if (isset($item['comunas']) && is_array($item['comunas'])) {
      foreach ($item['comunas'] as $commune) {
        $communes[] = [
          'id' => (int)($commune['id'] ?? 0),
          'code_dls' => (int)($commune['code_dls'] ?? 0),
          'name' => (string)($commune['name'] ?? ''),
          'city_code_dls' => isset($commune['city']) ? (int)($commune['city']['code_dls'] ?? 0) : 0,
          'city_name' => isset($commune['city']) ? (string)($commune['city']['name'] ?? '') : ''
        ];
      }
    }
    // Si es directamente una comuna
    elseif (isset($item['code_dls']) && isset($item['name'])) {
      $communes[] = [
        'id' => (int)($item['id'] ?? 0),
        'code_dls' => (int)($item['code_dls'] ?? 0),
        'name' => (string)($item['name'] ?? ''),
        'city_code_dls' => isset($item['city']) ? (int)($item['city']['code_dls'] ?? 0) : 0,
        'city_name' => isset($item['city']) ? (string)($item['city']['name'] ?? '') : ''
      ];
    }
  }

  file_put_contents('/tmp/starken_debug.txt', "Communes parsed: " . count($communes) . "\n", FILE_APPEND);

  // Guardar en caché de BD
  $communesJson = json_encode($communes);
  $qInsert = "INSERT INTO starken_cache (id, communes_json, updated_at) VALUES (1, ?, NOW())
              ON DUPLICATE KEY UPDATE communes_json=VALUES(communes_json), updated_at=NOW()";
  $stInsert = $db->prepare($qInsert);
  if ($stInsert) {
    $stInsert->bind_param('s', $communesJson);
    $stInsert->execute();
    $stInsert->close();
  }

  // Guardar mapeo de comunas a ciudades para futuras cotizaciones
  $db->query("CREATE TABLE IF NOT EXISTS starken_commune_city_map (
    commune_code_dls INT PRIMARY KEY,
    city_code_dls INT NOT NULL
  )");

  foreach ($communes as $commune) {
    if (!empty($commune['code_dls']) && !empty($commune['city_code_dls'])) {
      $commCodeDls = (int)$commune['code_dls'];
      $cityCodeDls = (int)$commune['city_code_dls'];
      $qMap = "INSERT INTO starken_commune_city_map (commune_code_dls, city_code_dls) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE city_code_dls=VALUES(city_code_dls)";
      $stMap = $db->prepare($qMap);
      if ($stMap) {
        $stMap->bind_param('ii', $commCodeDls, $cityCodeDls);
        $stMap->execute();
        $stMap->close();
      }
    }
  }

  json_out([
    'ok' => true,
    'communes' => $communes,
    'from_cache' => false,
    '_debug' => [
      'response_type' => gettype($data),
      'communes_count' => count($communes),
      'first_keys' => array_slice(array_keys($data), 0, 3)
    ]
  ]);

} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
