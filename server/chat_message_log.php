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
  $role = trim($data['role'] ?? ''); // 'user' o 'assistant'
  $contenido = trim($data['contenido'] ?? '');
  $tipo = trim($data['tipo'] ?? 'texto'); // 'texto' o 'voz_transcripcion'

  if (!$sessionId || !$role || !$contenido) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
  }

  error_log('[CHAT_MSG] Loguando mensaje - session=' . $sessionId . ', role=' . $role . ', tipo=' . $tipo);

  // Insertar mensaje
  $sql = "INSERT INTO chat_messages (session_id, role, contenido, tipo, timestamp)
          VALUES (?, ?, ?, ?, NOW())";

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    error_log('[CHAT_MSG] Error preparando SQL: ' . $db->error);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
  }

  $stmt->bind_param('isss', $sessionId, $role, $contenido, $tipo);
  $stmt->execute();

  $messageId = $db->insert_id;
  $stmt->close();

  echo json_encode([
    'ok' => true,
    'message_id' => $messageId
  ]);

} catch (Throwable $th) {
  error_log('[CHAT_MSG] Error: ' . $th->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error: ' . $th->getMessage()]);
}
