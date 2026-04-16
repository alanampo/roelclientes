<?php
// catalogo_detalle/reset_password.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$token = trim((string)($_GET['token'] ?? ''));

// Validar token contra la BD antes de mostrar el formulario
$tokenValid = false;
$tokenError = '';

if ($token === '') {
  $tokenError = 'Token no proporcionado.';
} else {
  // Cargar conexión
  $conectaPaths = [
    __DIR__ . '/../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../class_lib/class_conecta_mysql.php',
  ];
  foreach ($conectaPaths as $p) {
    if (is_file($p)) { require $p; break; }
  }

  // Cargar .env
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

  $link = @mysqli_connect($host, $user, $password, $dbname);
  if ($link) {
    mysqli_set_charset($link, 'utf8mb4');
    $st = $link->prepare("SELECT id FROM password_reset_tokens WHERE token=? AND used=0 AND expires_at > NOW() LIMIT 1");
    if ($st) {
      $st->bind_param('s', $token);
      $st->execute();
      $row = $st->get_result()->fetch_assoc();
      $st->close();
      if ($row) {
        $tokenValid = true;
      } else {
        $tokenError = 'El enlace de recuperación es inválido o ya expiró.';
      }
    }
  }
}

$tokenH = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

include __DIR__ . '/config/routes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Restablecer contraseña | Roelplant</title>
<meta name="robots" content="noindex,nofollow">
<link rel="shortcut icon" href="https://roelplant.cl/assets/images/favicon-128x128.png" type="image/x-icon">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:36px 32px;width:100%;max-width:400px}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.brand img{height:40px}
h1{font-size:20px;font-weight:700;color:#111827;margin-bottom:6px}
p.sub{font-size:14px;color:#6b7280;margin-bottom:24px}
.inp{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:14px;outline:none;transition:border-color .15s}
.inp:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.12)}
.field{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.btn{width:100%;background:#16a34a;color:#fff;border:none;border-radius:8px;padding:11px;font-size:15px;font-weight:600;cursor:pointer;transition:background .15s;margin-top:4px}
.btn:hover{background:#15803d}
.btn:disabled{opacity:.6;cursor:not-allowed}
.err{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px;display:none}
.ok{background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px;display:none}
.invalid-box{text-align:center;padding:12px 0}
.invalid-box svg{width:48px;height:48px;color:#ef4444;margin-bottom:12px}
.invalid-box p{color:#6b7280;font-size:14px;margin-bottom:20px}
.back-link{display:inline-block;color:#16a34a;font-size:14px;text-decoration:none;font-weight:500}
.back-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <img src="https://roelplant.cl/assets/images/favicon-128x128.png" alt="Roelplant">
    <strong style="font-size:16px;color:#111827">Roelplant</strong>
  </div>

  <?php if (!$tokenValid): ?>
    <div class="invalid-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
      <h1 style="margin-bottom:8px">Enlace inválido</h1>
      <p><?= htmlspecialchars($tokenError, ENT_QUOTES, 'UTF-8') ?></p>
      <br>
      <a class="back-link" href="<?php echo htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/', ENT_QUOTES, 'UTF-8'); ?>">← Volver al catálogo</a>
    </div>
  <?php else: ?>
    <h1>Nueva contraseña</h1>
    <p class="sub">Elige una contraseña segura para tu cuenta.</p>

    <div class="err" id="rpErr"></div>
    <div class="ok" id="rpOk"></div>

    <form id="rpForm" autocomplete="off">
      <input type="hidden" id="rpToken" value="<?= $tokenH ?>">
      <div class="field">
        <label for="rpPass">Nueva contraseña</label>
        <input class="inp" type="password" id="rpPass" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="rpPass2">Repetir contraseña</label>
        <input class="inp" type="password" id="rpPass2" placeholder="Repite la contraseña" autocomplete="new-password">
      </div>
      <button class="btn" id="rpBtn" type="submit">Cambiar contraseña</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($tokenValid): ?>
<script>
(function() {
  const form  = document.getElementById('rpForm');
  const btn   = document.getElementById('rpBtn');
  const errEl = document.getElementById('rpErr');
  const okEl  = document.getElementById('rpOk');

  function showErr(msg) {
    errEl.textContent = msg;
    errEl.style.display = 'block';
    okEl.style.display = 'none';
  }
  function showOk(msg) {
    okEl.textContent = msg;
    okEl.style.display = 'block';
    errEl.style.display = 'none';
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    errEl.style.display = 'none';
    okEl.style.display = 'none';

    const token = document.getElementById('rpToken').value;
    const pass  = document.getElementById('rpPass').value;
    const pass2 = document.getElementById('rpPass2').value;

    if (pass.length < 8) { showErr('La contraseña debe tener al menos 8 caracteres.'); return; }
    if (pass !== pass2)  { showErr('Las contraseñas no coinciden.'); return; }

    btn.disabled = true;
    btn.textContent = 'Guardando…';

    try {
      const apiBase = '<?php echo htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/api/', ENT_QUOTES, 'UTF-8'); ?>';
      const res = await fetch(apiBase + 'auth/reset_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password: pass })
      });
      const j = await res.json();
      if (j.ok) {
        showOk('¡Contraseña actualizada! Redirigiendo…');
        form.style.display = 'none';
        setTimeout(() => {
          window.location.href = '<?php echo htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/', ENT_QUOTES, 'UTF-8'); ?>';
        }, 2000);
      } else {
        showErr(j.error || 'Error al actualizar la contraseña.');
        btn.disabled = false;
        btn.textContent = 'Cambiar contraseña';
      }
    } catch (err) {
      showErr('Error de conexión. Intenta nuevamente.');
      btn.disabled = false;
      btn.textContent = 'Cambiar contraseña';
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
