<?php
// catalogo_detalle/api/auth/logout.php
declare(strict_types=1);
require __DIR__ . '/../_bootstrap.php';

require_post();
require_csrf();

start_session();
session_unset();
session_destroy();

json_out(['ok'=>true]);
