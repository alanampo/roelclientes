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
  // Crear tabla de caché global (todas las agencias de Chile, cacheadas una sola vez)
  $createTableSQL = "CREATE TABLE IF NOT EXISTS starken_agencies_global_cache (
    id INT PRIMARY KEY DEFAULT 1,
    all_agencies_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )";
  $db->query($createTableSQL);

  // Intentar obtener caché global (máximo 6 meses, solo si no está vacío)
  $qCache = "SELECT all_agencies_json FROM starken_agencies_global_cache WHERE id=1 AND updated_at > DATE_SUB(NOW(), INTERVAL 6 MONTH) LIMIT 1";
  $stCache = $db->prepare($qCache);
  $useGlobalCache = false;
  $allAgencies = [];

  if ($stCache) {
    $stCache->execute();
    $rowCache = $stCache->get_result()->fetch_assoc();
    $stCache->close();

    if ($rowCache) {
      $allAgencies = json_decode($rowCache['all_agencies_json'], true);
      // Solo usar cache si es un array válido y no está vacío
      if (is_array($allAgencies) && !empty($allAgencies)) {
        $useGlobalCache = true;
        json_out([
          'ok' => true,
          'agencies' => $allAgencies,
          'from_cache' => true
        ]);
      }
    }
  }

  // Si no hay caché válido, llamar a Starken
  if (!extension_loaded('curl')) {
    throw new RuntimeException('Extensión cURL no disponible');
  }

  // Obtener todas las ciudades desde el caché de comunas para saber cuáles existen
  $allCities = [];
  $qCommunes = "SELECT communes_json FROM starken_cache WHERE id=1 LIMIT 1";
  $stCommunes = $db->prepare($qCommunes);
  if ($stCommunes) {
    $stCommunes->execute();
    $rowCommunes = $stCommunes->get_result()->fetch_assoc();
    $stCommunes->close();

    if ($rowCommunes) {
      $communes = json_decode($rowCommunes['communes_json'], true);
      if (is_array($communes)) {
        // Extraer ciudades únicas
        foreach ($communes as $comm) {
          $cityCodeDls = (int)($comm['city_code_dls'] ?? 0);
          if ($cityCodeDls > 0 && !in_array($cityCodeDls, $allCities)) {
            $allCities[] = $cityCodeDls;
          }
        }
      }
    }
  }

  // Obtener agencias de cada ciudad usando /agency/city/
  $agencies = [];
  foreach ($allCities as $cityCodeDls) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $starkenApiUrl . '/agency/city/' . $cityCodeDls);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Cache-Control: no-cache',
      'Authorization: Bearer ' . $starkenToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode >= 200 && $httpCode < 300) {
      $data = json_decode($response, true);
      if (is_array($data) && isset($data['comunas'])) {
        // Procesar cada comuna dentro de esta ciudad
        foreach ($data['comunas'] as $comuna) {
          if (isset($comuna['agencies']) && is_array($comuna['agencies'])) {
            foreach ($comuna['agencies'] as $agency) {
              $agencies[] = [
                'id' => (int)($agency['id'] ?? 0),
                'code_dls' => (int)($agency['code_dls'] ?? 0),
                'name' => (string)($agency['name'] ?? ''),
                'address' => (string)($agency['address'] ?? ''),
                'phone' => (string)($agency['phone'] ?? ''),
                'url_google_maps' => (string)($agency['url_google_maps'] ?? ''),
                'city_code_dls' => $cityCodeDls
              ];
            }
          }
        }
      }
    }
  }

  // Guardar TODAS las agencias en caché global
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
    '_debug' => [
      'total_agencies' => count($agencies),
      'total_cities' => count($allCities),
      'cities_list' => $allCities,
      'first_agency_sample' => !empty($agencies) ? $agencies[0] : null,
      'agencies_per_city' => array_reduce($agencies, function($carry, $item) {
        $city = $item['city_code_dls'];
        $carry[$city] = ($carry[$city] ?? 0) + 1;
        return $carry;
      }, [])
    ]
  ]);

} catch (Throwable $e) {
  json_out([
    'ok' => false,
    'error' => $e->getMessage()
  ], 400);
}
