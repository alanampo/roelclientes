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
  $customerId = require_auth();
  $db = db();

  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true) ?: [];

  $sessionId = (int)($data['session_id'] ?? 0);

  if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing session_id']);
    exit;
  }

  error_log('[CHAT_SESSION_END] Cerrando sesión: ' . $sessionId);

  // Actualizar sesión: calcular duración, marcar como cerrada
  $sql = "UPDATE chat_sessions
          SET fecha_fin = NOW(),
              duracion_segundos = TIMESTAMPDIFF(SECOND, fecha_inicio, NOW()),
              estado = 'cerrada'
          WHERE id = ? AND customer_id = ?";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log('[CHAT_SESSION_END] Error preparando SQL: ' . $db->error);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
  }

  $stmt->bind_param('ii', $sessionId, $customerId);
  $stmt->execute();

  if ($stmt->affected_rows === 0) {
    error_log('[CHAT_SESSION_END] Sesión no encontrada: ' . $sessionId);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Session not found']);
    exit;
  }

  $stmt->close();

  error_log('[CHAT_SESSION_END] Sesión cerrada exitosamente');

  echo json_encode(['ok' => true]);

} catch (Throwable $th) {
  error_log('[CHAT_SESSION_END] Error: ' . $th->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error: ' . $th->getMessage()]);
}
