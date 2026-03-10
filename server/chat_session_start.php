<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

try {
  // Obtener customer_id del usuario logeado
  $customerId = require_auth();

  $db = db();

  // Datos de la request
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true) ?: [];

  $modo = trim($data['modo'] ?? 'texto'); // 'texto' o 'voz'
  $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

  // Generar session_id único
  $sessionId = bin2hex(random_bytes(16));

  error_log('[CHAT_SESSION] Iniciando sesión para customer ' . $customerId . ', modo: ' . $modo);

  // Insertar nueva sesión
  $sql = "INSERT INTO chat_sessions (customer_id, modo, fecha_inicio, session_uuid, ip_address, user_agent, estado)
          VALUES (?, ?, NOW(), ?, ?, ?, 'activa')";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log('[CHAT_SESSION] Error preparando SQL: ' . $db->error);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
  }

  $stmt->bind_param('issss', $customerId, $modo, $sessionId, $ipAddress, $userAgent);
  $stmt->execute();

  $insertId = $db->insert_id;
  $stmt->close();

  error_log('[CHAT_SESSION] Sesión creada: ID=' . $insertId . ', sessionId=' . $sessionId);

  // Limpiar chats más viejos de 2 meses (ejecutar en background)
  $deleteSQL = "DELETE FROM chat_sessions WHERE fecha_inicio < DATE_SUB(NOW(), INTERVAL 2 MONTH)";
  $db->query($deleteSQL);
  $deletedCount = $db->affected_rows;
  if ($deletedCount > 0) {
    error_log('[CHAT_SESSION] Limpieza ejecutada: ' . $deletedCount . ' sesiones eliminadas');
  }

  echo json_encode([
    'ok' => true,
    'session_id' => $insertId,
    'session_uuid' => $sessionId
  ]);

} catch (Throwable $th) {
  error_log('[CHAT_SESSION] Error: ' . $th->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error: ' . $th->getMessage()]);
}
