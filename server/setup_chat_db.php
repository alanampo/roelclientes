<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

try {
  $db = db();

  // Crear tabla chat_sessions
  $sql1 = <<<SQL
CREATE TABLE IF NOT EXISTS chat_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  modo ENUM('texto', 'voz') NOT NULL DEFAULT 'texto',
  fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_fin DATETIME NULL,
  duracion_segundos INT NULL,
  producto_buscado VARCHAR(255) NULL,
  estado VARCHAR(50) DEFAULT 'activa',
  session_uuid VARCHAR(100) UNIQUE,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  INDEX idx_customer (customer_id),
  INDEX idx_fecha_inicio (fecha_inicio),
  INDEX idx_modo (modo),
  INDEX idx_estado (estado)
)
SQL;

  if (!$db->query($sql1)) {
    throw new Exception('Error creando tabla chat_sessions: ' . $db->error);
  }

  // Crear tabla chat_messages
  $sql2 = <<<SQL
CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  role ENUM('user', 'assistant') NOT NULL,
  contenido LONGTEXT NOT NULL,
  tipo ENUM('texto', 'voz_transcripcion') DEFAULT 'texto',
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
  INDEX idx_session (session_id),
  INDEX idx_timestamp (timestamp),
  INDEX idx_role (role)
)
SQL;

  if (!$db->query($sql2)) {
    throw new Exception('Error creando tabla chat_messages: ' . $db->error);
  }

  echo json_encode([
    'ok' => true,
    'message' => 'Tablas creadas/verificadas correctamente'
  ]);

} catch (Throwable $th) {
  error_log('[SETUP_CHAT_DB] Error: ' . $th->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $th->getMessage()
  ]);
}
