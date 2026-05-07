<?php
/**
 * TEST: Envío de email de confirmación de pedido
 * Usa la misma lógica que webpay_return.php
 * ELIMINAR o proteger en producción.
 */
declare(strict_types=1);

// Seguridad mínima: solo accesible desde localhost o con clave
$allowedIps = ['127.0.0.1', '::1'];
$testKey    = 'roeltest2026'; // cambiar si quieres más seguridad
$keyOk      = isset($_GET['key']) && $_GET['key'] === $testKey;
$ipOk       = in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIps, true);
if (!$keyOk && !$ipOk) {
  http_response_code(403);
  die('Acceso denegado. Añade ?key=roeltest2026 a la URL.');
}

// ── Cargar .env ──────────────────────────────────────────────────────────────
$envPaths = [__DIR__ . '/../.env', __DIR__ . '/../../.env'];
foreach ($envPaths as $envPath) {
  if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
      [$k, $v] = explode('=', $line, 2);
      $k = trim($k); $v = trim(trim($v), '"\'');
      if (!getenv($k)) putenv("{$k}={$v}");
    }
    break;
  }
}

// ── Cargar PHPMailer ─────────────────────────────────────────────────────────
$autoloadPaths = [
  __DIR__ . '/../vendor/autoload.php',   // roelclientes/vendor (desde catalogo_detalle/)
  __DIR__ . '/../../vendor/autoload.php',
];
$autoloadFound = false;
foreach ($autoloadPaths as $p) {
  if (is_file($p)) { require_once $p; $autoloadFound = true; break; }
}

// ── Conectar a BD de producción ───────────────────────────────────────────────
$conectaPaths = [
  __DIR__ . '/../class_lib/class_conecta_mysql.php',     // roelclientes/class_lib
  __DIR__ . '/../../class_lib/class_conecta_mysql.php',
];
$dbStock = null;
foreach ($conectaPaths as $p) {
  if (is_file($p)) {
    require_once $p;
    $dbStock = @mysqli_connect($host, $user, $password, $dbname);
    if ($dbStock) { mysqli_set_charset($dbStock, 'utf8'); break; }
  }
}

// ── Obtener última reserva ────────────────────────────────────────────────────
$lastReserva  = null;
$lastItems    = [];
$dbError      = '';

if ($dbStock) {
  $res = mysqli_query($dbStock,
    "SELECT r.id, r.id_cliente, r.subtotal_clp, r.packing_cost_clp, r.shipping_cost_clp,
            r.total_clp, r.paid_clp,
            r.shipping_method, r.shipping_address, r.shipping_commune,
            r.shipping_agency_name, r.shipping_agency_address, r.created_at,
            c.nombre AS customer_nombre, c.mail AS customer_email
     FROM reservas r
     LEFT JOIN clientes c ON c.id_cliente = r.id_cliente
     WHERE r.total_clp > 0
     ORDER BY r.id DESC LIMIT 1"
  );
  if ($res) {
    $lastReserva = mysqli_fetch_assoc($res);
  } else {
    $dbError = mysqli_error($dbStock);
  }

  if ($lastReserva) {
    $idRes = (int)$lastReserva['id'];
    $resItems = mysqli_query($dbStock,
      "SELECT rp.cantidad, v.nombre,
              CONCAT(t.codigo, LPAD(v.id_interno, 4, '0')) AS referencia,
              COALESCE(v.precio_detalle, v.precio, 0) AS unit_price
       FROM reservas_productos rp
       LEFT JOIN variedades_producto v ON v.id = rp.id_variedad
       LEFT JOIN tipos_producto t ON t.id = v.id_tipo
       WHERE rp.id_reserva = {$idRes}
       ORDER BY rp.id ASC"
    );
    if ($resItems) {
      while ($row = mysqli_fetch_assoc($resItems)) {
        $qty   = (int)$row['cantidad'];
        $price = (float)$row['unit_price'];
        $lastItems[] = [
          'name'  => (string)$row['nombre'],
          'ref'   => (string)$row['referencia'],
          'qty'   => $qty,
          'price' => (int)$price,
          'line'  => (int)($price * $qty),
        ];
      }
    }
  }

  mysqli_close($dbStock);
} else {
  $dbError = 'No se pudo conectar a la BD.';
}

// ── Procesar envío ────────────────────────────────────────────────────────────
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lastReserva) {
  $toEmail = trim((string)($_POST['email'] ?? ''));

  if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    $result = ['ok' => false, 'msg' => 'Email inválido.'];
  } elseif (!$autoloadFound) {
    $result = ['ok' => false, 'msg' => 'PHPMailer no encontrado. Verifica que vendor/ esté instalado.'];
  } else {
    $r = $lastReserva;
    $subtotal  = (int)($r['subtotal_clp'] ?? 0);
    $packing   = (int)($r['packing_cost_clp'] ?? 0);
    $shipping  = (int)($r['shipping_cost_clp'] ?? 0);
    $paidClp   = (int)($r['paid_clp'] ?? 0);
    $total     = $paidClp > 0 ? $paidClp : (int)($r['total_clp'] ?? 0);
    $shMethod  = (string)($r['shipping_method'] ?? '');
    $createdAt = (string)($r['created_at'] ?? '');
    $toNombre  = (string)($r['customer_nombre'] ?? '');
    $orderCode = 'RP-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT);

    if ($shMethod === 'vivero') {
      $shippingLabel = 'Retiro en vivero (gratis)';
    } elseif ($shMethod === 'agencia') {
      $agName = (string)($r['shipping_agency_name'] ?? '');
      $agAddr = (string)($r['shipping_agency_address'] ?? '');
      $shippingLabel = 'Retiro en sucursal Starken';
      if ($agName) $shippingLabel .= " — {$agName}";
      if ($agAddr) $shippingLabel .= " ({$agAddr})";
    } elseif ($shMethod === 'domicilio') {
      $addr    = (string)($r['shipping_address'] ?? '');
      $commune = (string)($r['shipping_commune'] ?? '');
      $shippingLabel = 'Envío a domicilio';
      if ($addr)    $shippingLabel .= " — {$addr}";
      if ($commune) $shippingLabel .= " ({$commune})";
    } else {
      $shippingLabel = 'Por definir';
    }

    $clp = fn(int $n): string => '$' . number_format($n, 0, ',', '.');
    $esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $itemRows = '';
    foreach ($lastItems as $it) {
      $itemRows .= '<tr>
        <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6">
          <strong style="color:#111827">' . $esc($it['name']) . '</strong><br>
          <span style="font-size:12px;color:#6b7280">' . $esc($it['ref']) . ' · ' . $it['qty'] . ' x ' . $clp($it['price']) . '</span>
        </td>
        <td style="padding:10px 8px;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700;color:#111827;white-space:nowrap">' . $clp($it['line']) . '</td>
      </tr>';
    }

    $packingRow = '';
    if ($packing > 0) {
      $packingRow = '<tr>
        <td style="padding:8px 8px;color:#6b7280">Packing</td>
        <td style="padding:8px 8px;text-align:right;color:#6b7280">' . $clp($packing) . '</td>
      </tr>';
    }

    $shippingRow = '<tr>
      <td style="padding:8px 8px;color:#6b7280">Envío</td>
      <td style="padding:8px 8px;text-align:right;color:' . ($shipping > 0 ? '#111827' : '#16a34a') . ';font-weight:' . ($shipping > 0 ? '400' : '600') . '">'
      . ($shipping > 0 ? $clp($shipping) : $esc($shippingLabel))
      . '</td>
    </tr>';

    $saludo = $toNombre ? ('Hola ' . $esc(explode(' ', $toNombre)[0]) . ',') : 'Hola,';

    $html = '<!DOCTYPE html><html lang="es"><body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px">
<tr><td align="center">
<table width="100%" style="max-width:520px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">
  <tr><td style="background:#166534;padding:24px 28px;text-align:center">
    <img src="https://roelplant.cl/assets/images/logo-blanco-266x153.png" alt="Roelplant" style="height:52px">
  </td></tr>
  <tr><td style="padding:28px 28px 0">
    <p style="margin:0 0 4px;font-size:14px;color:#6b7280">' . $esc($createdAt) . '</p>
    <h1 style="margin:0 0 6px;font-size:22px;color:#111827">¡Compra realizada!</h1>
    <p style="margin:0 0 20px;font-size:14px;color:#374151">' . $saludo . ' Tu pago fue procesado exitosamente.</p>
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:20px">
      <span style="color:#166534;font-weight:700;font-size:13px">✓ PAGO ACEPTADO</span>
      <span style="color:#374151;font-size:13px;margin-left:12px">Código: <strong>' . $esc($orderCode) . '</strong></span>
    </div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:16px">
      ' . $itemRows . '
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #e5e7eb;padding-top:4px">
      <tr>
        <td style="padding:8px 8px;color:#6b7280">Subtotal</td>
        <td style="padding:8px 8px;text-align:right;color:#6b7280">' . $clp($subtotal) . '</td>
      </tr>
      ' . $packingRow . '
      ' . $shippingRow . '
      <tr>
        <td style="padding:10px 8px;font-weight:700;font-size:16px;color:#111827;border-top:1px solid #e5e7eb">Total pagado</td>
        <td style="padding:10px 8px;text-align:right;font-weight:700;font-size:16px;color:#111827;border-top:1px solid #e5e7eb">' . $clp($total) . '</td>
      </tr>
    </table>
    ' . ($shMethod !== 'vivero' && $shipping > 0 ? '<p style="margin:12px 0 0;font-size:13px;color:#6b7280">' . $esc($shippingLabel) . '</p>' : '') . '
  </td></tr>
  <tr><td style="padding:24px 28px;border-top:1px solid #f3f4f6;text-align:center">
    <p style="margin:0 0 6px;font-size:13px;color:#6b7280">¿Tienes dudas? Escríbenos a <a href="mailto:ventas@roelplant.cl" style="color:#16a34a">ventas@roelplant.cl</a></p>
    <p style="margin:0;font-size:12px;color:#9ca3af">Roelplant · <a href="https://roelplant.cl" style="color:#9ca3af">roelplant.cl</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>';

    $altBody  = "¡Compra realizada! Código: {$orderCode}\nEstado: PAGO ACEPTADO\n\n";
    foreach ($lastItems as $it) {
      $altBody .= "{$it['name']} ({$it['ref']}) · {$it['qty']} x " . $clp($it['price']) . " = " . $clp($it['line']) . "\n";
    }
    $altBody .= "\nSubtotal: " . $clp($subtotal);
    if ($packing > 0) $altBody .= "\nPacking: " . $clp($packing);
    $altBody .= "\nEnvío: " . ($shipping > 0 ? $clp($shipping) : $shippingLabel);
    $altBody .= "\nTotal pagado: " . $clp($total);

    try {
      $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = getenv('EMAIL_USERNAME') ?: '';
      $mail->Password   = getenv('EMAIL_PASSWORD') ?: '';
      $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;
      $mail->CharSet    = 'UTF-8';
      $mail->setFrom('ventas@roelplant.cl', 'Roelplant');
      $mail->addAddress($toEmail);
      $mail->addReplyTo('ventas@roelplant.cl', 'Roelplant');
      $mail->isHTML(true);
      $mail->Subject = "[TEST] Compra confirmada {$orderCode} – Roelplant";
      $mail->Body    = $html;
      $mail->AltBody = $altBody;
      $mail->send();
      $result = ['ok' => true, 'msg' => "Email enviado a {$toEmail} (reserva #{$r['id']} · {$orderCode})"];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'msg' => 'Error PHPMailer: ' . $mail->ErrorInfo ?: $e->getMessage()];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test Email Pedido – Roelplant</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;padding:32px 16px;color:#111827}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:28px;max-width:560px;margin:0 auto}
h1{font-size:18px;font-weight:700;margin-bottom:4px}
.sub{font-size:13px;color:#6b7280;margin-bottom:24px}
.section{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px}
.section strong{display:block;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px;margin-bottom:8px}
.row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f3f4f6}
.row:last-child{border-bottom:none}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
input[type=email]{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:14px;outline:none}
input[type=email]:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.12)}
.btn{background:#16a34a;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:600;cursor:pointer;margin-top:12px}
.btn:hover{background:#15803d}
.ok{background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:8px;padding:12px;font-size:13px;margin-bottom:16px}
.err{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:8px;padding:12px;font-size:13px;margin-bottom:16px}
.warn{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;border-radius:8px;padding:12px;font-size:13px;margin-bottom:16px}
.tag{display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-left:8px}
</style>
</head>
<body>
<div class="card">
  <h1>Test Email — Confirmación de pedido <span class="tag">TEST</span></h1>
  <p class="sub">Envía el email de confirmación usando la última reserva de la BD.</p>

  <?php if ($result): ?>
    <div class="<?= $result['ok'] ? 'ok' : 'err' ?>">
      <?= htmlspecialchars($result['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($dbError): ?>
    <div class="err">BD: <?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if (!$autoloadFound): ?>
    <div class="warn">⚠ PHPMailer no encontrado. Verifica que <code>vendor/</code> esté instalado.</div>
  <?php endif; ?>

  <?php if ($lastReserva): ?>
    <div class="section">
      <strong>Reserva que se usará</strong>
      <div class="row"><span>ID</span><span><?= (int)$lastReserva['id'] ?> · RP-<?= str_pad((string)$lastReserva['id'], 6, '0', STR_PAD_LEFT) ?></span></div>
      <div class="row"><span>Fecha</span><span><?= htmlspecialchars($lastReserva['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="row"><span>Cliente</span><span><?= htmlspecialchars($lastReserva['customer_nombre'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="row"><span>Email real</span><span><?= htmlspecialchars($lastReserva['customer_email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="row"><span>Envío</span><span><?= htmlspecialchars($lastReserva['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="row"><span>Total</span><span>$<?= number_format((int)($lastReserva['total_clp'] ?? 0), 0, ',', '.') ?></span></div>
      <div class="row"><span>Productos</span><span><?= count($lastItems) ?> línea(s)</span></div>
    </div>

    <form method="POST">
      <label for="email">Enviar a este email:</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? ($lastReserva['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
             placeholder="test@ejemplo.com" required>
      <button class="btn" type="submit">Enviar email de prueba</button>
    </form>
  <?php else: ?>
    <div class="warn">No se encontraron reservas en la BD.</div>
  <?php endif; ?>
</div>
</body>
</html>
