<?php
/**
 * catalogo_detalle/api/shipping/starken_agencies.php
 * Obtiene lista de TODAS las sucursales de Starken
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

require_auth();
$db = db();

// Token de Starken
$starkenToken = '7b14bb8a-9df5-4cea-bb71-c6bc285b2ad7';
$starkenApiUrl = 'https://gateway.starken.cl/externo/integracion';

try {
  // Crear tabla de caché global
  $createTableSQL = "CREATE TABLE IF NOT EXISTS starken_agencies_global_cache (
    id INT PRIMARY KEY DEFAULT 1,
    all_agencies_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )";
  $db->query($createTableSQL);

  // Intentar obtener caché global (máximo 6 meses)
  $qCache = "SELECT all_agencies_json FROM starken_agencies_global_cache WHERE id=1 AND updated_at > DATE_SUB(NOW(), INTERVAL 6 MONTH) LIMIT 1";
  $stCache = $db->prepare($qCache);

  if ($stCache) {
    $stCache->execute();
    $rowCache = $stCache->get_result()->fetch_assoc();
    $stCache->close();

    if ($rowCache) {
      $allAgencies = json_decode($rowCache['all_agencies_json'], true);
      if (is_array($allAgencies) && !empty($allAgencies)) {
        json_out([
          'ok' => true,
          'agencies' => $allAgencies,
          'from_cache' => true
        ]);
      }
    }
  }

  // Si no hay caché válido, llamar a Starken UNA SOLA VEZ
  if (!extension_loaded('curl')) {
    throw new RuntimeException('Extensión cURL no disponible');
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, $starkenApiUrl . '/agency/agency');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Bearer ' . $starkenToken
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

  $resp = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    throw new RuntimeException("Error de conexión con Starken: {$curlError}");
  }

  if (empty($resp)) {
    throw new RuntimeException("Starken devolvió respuesta vacía (HTTP {$httpCode})");
  }

  if ($httpCode < 200 || $httpCode >= 300) {
    throw new RuntimeException("Starken API respondió con HTTP {$httpCode}");
  }

  $respArray = json_decode($resp, true);
  if (!is_array($respArray)) {
    throw new RuntimeException("Respuesta de Starken tiene formato inválido");
  }

  // Procesar sucursales
  $agencies = [];
  foreach ($respArray as $sucursal) {
    // Extraer city_code_dls de la estructura anidada comuna.city.code_dls
    $cityCodeDls = 0;
    if (isset($sucursal['comuna']['city']['code_dls'])) {
      $cityCodeDls = (int)$sucursal['comuna']['city']['code_dls'];
    }

    $agencies[] = [
      'id' => (int)($sucursal['id'] ?? 0),
      'code_dls' => (int)($sucursal['code_dls'] ?? 0),
      'name' => (string)($sucursal['name'] ?? ''),
      'address' => (string)($sucursal['address'] ?? ''),
      'phone' => (string)($sucursal['phone'] ?? ''),
      'url_google_maps' => (string)($sucursal['url_google_maps'] ?? ''),
      'city_code_dls' => $cityCodeDls
    ];
  }

  // Guardar en caché
  $agenciesJson = json_encode($agencies);
  $cacheId = 1;
  $qInsert = "INSERT INTO starken_agencies_global_cache (id, all_agencies_json, updated_at) VALUES (?, ?, NOW())
              ON DUPLICATE KEY UPDATE all_agencies_json=VALUES(all_agencies_json), updated_at=NOW()";
  $stInsert = $db->prepare($qInsert);
  if ($stInsert) {
    $stInsert->bind_param('is', $cacheId, $agenciesJson);
    $stInsert->execute();
    $stInsert->close();
  }

  json_out([
    'ok' => true,
    'agencies' => $agencies,
    'from_cache' => false,
    'total' => count($agencies)
  ]);

} catch (Throwable $e) {
  json_out([
    'ok' => false,
    'error' => $e->getMessage()
  ], 400);
}
