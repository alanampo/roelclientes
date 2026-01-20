<?php
/**
 * catalogo_detalle/api/payment/webpay_check.php
 * Verifica que la configuración de Webpay esté correcta
 * Útil para debugging
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

// Obtener configuración
$APP = require __DIR__ . '/../../config/app.php';

$checks = [];

// 1. Verificar .env existe
$envFile = __DIR__ . '/../../.env';
$checks['env_file_exists'] = [
  'name' => '.env archivo existe',
  'passed' => file_exists($envFile),
  'hint' => $envFile
];

// 2. Verificar variables de ambiente
$checks['webpay_env_var'] = [
  'name' => 'WEBPAY_ENVIRONMENT está configurado',
  'passed' => !empty($APP['WEBPAY_ENVIRONMENT']),
  'value' => $APP['WEBPAY_ENVIRONMENT'] ?? 'NO DEFINIDO'
];

$checks['webpay_commerce_code'] = [
  'name' => 'WEBPAY_COMMERCE_CODE está configurado',
  'passed' => !empty($APP['WEBPAY_COMMERCE_CODE']),
  'value' => $APP['WEBPAY_COMMERCE_CODE'] ?? 'NO DEFINIDO'
];

$checks['webpay_api_key'] = [
  'name' => 'WEBPAY_API_KEY está configurado',
  'passed' => !empty($APP['WEBPAY_API_KEY']),
  'value' => strlen($APP['WEBPAY_API_KEY'] ?? '') > 0 ? '✓ (configurado)' : 'NO DEFINIDO'
];

// 3. Verificar conexión a internet (intentar conectar a Webpay)
$url = 'integration' === ($APP['WEBPAY_ENVIRONMENT'] ?? '')
  ? 'https://webpay3gint.transbank.cl/webpayserver/initTransaction'
  : 'https://webpay3g.transbank.cl/webpayserver/initTransaction';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);
@curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$checks['webpay_connectivity'] = [
  'name' => 'Conexión a Webpay',
  'passed' => $httpCode > 0 && $httpCode < 500,
  'value' => "HTTP {$httpCode}"
];

// 4. Verificar tabla de transacciones
$db = db();
$st = $db->prepare("SHOW TABLES LIKE 'webpay_transactions'");
if ($st) {
  $st->execute();
  $result = $st->get_result()->fetch_row();
  $st->close();
  $hasTable = !empty($result);
} else {
  $hasTable = false;
}

$checks['webpay_table'] = [
  'name' => 'Tabla webpay_transactions existe',
  'passed' => $hasTable,
  'hint' => $hasTable ? 'Tabla lista' : 'Se creará automáticamente al procesar primer pago'
];

// 5. Verificar archivos necesarios
$files = [
  'WebpayService.php' => __DIR__ . '/../services/WebpayService.php',
  'webpay_create.php' => __DIR__ . '/webpay_create.php',
  'webpay_return.php' => __DIR__ . '/webpay_return.php',
  'payment_success.php' => __DIR__ . '/../../payment_success.php',
];

foreach ($files as $name => $path) {
  $checks["file_{$name}"] = [
    'name' => "Archivo {$name}",
    'passed' => file_exists($path),
    'path' => $path
  ];
}

// Determinar resultado general
$allPassed = array_reduce($checks, fn($acc, $check) => $acc && ($check['passed'] ?? false), true);

// Responder con JSON
header('Content-Type: application/json');
echo json_encode([
  'ok' => $allPassed,
  'environment' => $APP['WEBPAY_ENVIRONMENT'] ?? 'UNKNOWN',
  'status' => $allPassed ? 'LISTO PARA USAR ✓' : 'REQUIERE CONFIGURACIÓN',
  'checks' => $checks,
  'message' => $allPassed
    ? 'Todo está configurado correctamente. Puedes comenzar a probar.'
    : 'Revisa los items que dicen "NO" o "FAILED" arriba.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
