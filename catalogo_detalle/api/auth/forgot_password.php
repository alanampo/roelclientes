<?php
// catalogo_detalle/api/auth/forgot_password.php
declare(strict_types=1);

// Capturar errores fatales antes de que _bootstrap pueda responder
register_shutdown_function(function() {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'Fatal error', 'debug' => $e['message'] . ' in ' . $e['file'] . ':' . $e['line']]);
  }
});

require __DIR__ . '/../_bootstrap.php';

// Encontrar vendor/autoload.php (PHPMailer)
$autoloadPaths = [
  __DIR__ . '/../../../vendor/autoload.php',   // roelclientes/vendor (desde catalogo_detalle/api/auth)
  __DIR__ . '/../../../../vendor/autoload.php', // un nivel más arriba
];
$autoloadFound = false;
foreach ($autoloadPaths as $p) {
  if (is_file($p)) { require $p; $autoloadFound = true; break; }
}
if (!$autoloadFound) {
  json_out(['ok' => false, 'error' => 'PHPMailer no instalado — vendor/ no encontrado en: ' . implode(', ', $autoloadPaths)], 500);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_post();

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) bad_request('Payload inválido');

$emailRaw = (string)($in['email'] ?? '');
$email = email_normalize($emailRaw);
if (!email_is_valid($email)) bad_request('Email inválido');

$db = db();

// Crear tabla de tokens si no existe
$db->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verificar que el usuario existe
$st = $db->prepare("SELECT u.id FROM usuarios u WHERE u.nombre=? AND u.tipo_usuario=0 LIMIT 1");
$st->bind_param('s', $email);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

// Respuesta siempre exitosa (no revelar si el email existe)
if (!$row) {
  json_out(['ok' => true, 'message' => 'Si el email está registrado, recibirás un correo.']);
}

// Invalidar tokens anteriores para este email
$db->prepare("DELETE FROM password_reset_tokens WHERE email=?")->execute([$email] );
$stDel = $db->prepare("DELETE FROM password_reset_tokens WHERE email=?");
$stDel->bind_param('s', $email);
$stDel->execute();
$stDel->close();

// Crear token
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

$stIns = $db->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?,?,?)");
$stIns->bind_param('sss', $email, $token, $expires);
if (!$stIns->execute()) {
  json_out(['ok' => false, 'error' => 'Error interno'], 500);
}
$stIns->close();

// Construir link de recuperación
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'clientes.roelplant.cl';
$scriptDir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // /catalogo_detalle
$resetUrl = $scheme . '://' . $host . rtrim($scriptDir, '/') . '/reset_password.php?token=' . urlencode($token);

// Enviar email con PHPMailer
$emailUser = getenv('EMAIL_USERNAME') ?: '';
$emailPass = getenv('EMAIL_PASSWORD') ?: '';

$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = $emailUser;
  $mail->Password   = $emailPass;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->CharSet    = 'UTF-8';

  $mail->setFrom('ventas@roelplant.cl', 'Roelplant');
  $mail->addAddress($email);
  $mail->addReplyTo('ventas@roelplant.cl', 'Roelplant');

  $mail->isHTML(true);
  $mail->Subject = 'Recuperación de contraseña – Roelplant';
  $mail->Body    = '
<!DOCTYPE html><html><body style="font-family:sans-serif;color:#222;max-width:520px;margin:0 auto;padding:24px">
<img src="https://roelplant.cl/assets/images/logo-blanco-266x153.png" alt="Roelplant" style="height:60px;margin-bottom:20px;background:#166534;border-radius:8px;padding:8px 12px">
<h2 style="margin:0 0 12px">Recuperación de contraseña</h2>
<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta <strong>' . htmlspecialchars($email, ENT_QUOTES) . '</strong>.</p>
<p>Haz clic en el botón para crear una nueva contraseña. El enlace es válido por <strong>1 hora</strong>.</p>
<p style="text-align:center;margin:28px 0">
  <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES) . '"
     style="background:#16a34a;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px">
    Restablecer contraseña
  </a>
</p>
<p style="font-size:13px;color:#666">Si no solicitaste este cambio, ignora este mensaje. Tu contraseña no cambiará.</p>
<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0">
<p style="font-size:12px;color:#9ca3af">Roelplant · Catálogo al Detalle · <a href="https://roelplant.cl">roelplant.cl</a></p>
</body></html>';
  $mail->AltBody = "Recuperación de contraseña Roelplant\n\nHaz clic en el siguiente enlace para restablecer tu contraseña (válido 1 hora):\n\n" . $resetUrl . "\n\nSi no solicitaste este cambio, ignora este mensaje.";

  $mail->send();
  json_out(['ok' => true, 'message' => 'Si el email está registrado, recibirás un correo.']);
} catch (Exception $e) {
  $errDetail = $mail->ErrorInfo ?: $e->getMessage();
  error_log('[forgot_password] Mailer error: ' . $errDetail);
  // En producción devuelve el detalle para poder diagnosticar; quitar luego
  json_out(['ok' => false, 'error' => 'No se pudo enviar el correo.', 'debug' => $errDetail], 500);
}
